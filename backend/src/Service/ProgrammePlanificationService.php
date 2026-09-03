<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Entity\ChirurgiePlanifiee;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgienRepository;
use App\Repository\ChirurgieModeleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Crée atomiquement les chirurgies et checklists composant un programme. */
final readonly class ProgrammePlanificationService
{
    public function __construct(
        private ChirurgienRepository $chirurgienRepository,
        private ChirurgieModeleRepository $modeleRepository,
        private PreparationMaterielInitializer $initializer,
        private ProgrammeOrderAllocator $orderAllocator,
        private ProgrammeOperatoireService $programmeService,
        private ChirurgieAuditTrail $auditTrail,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<int> $modelIds
     */
    public function plan(
        \DateTimeImmutable $date,
        string $room,
        int $surgeonId,
        array $modelIds,
        string $actor,
    ): ProgrammeOperatoire {
        $room = trim($room);
        $surgeon = $this->chirurgienRepository->find($surgeonId)
            ?? throw new NotFoundHttpException('Chirurgien introuvable.');

        $models = [];
        foreach ($modelIds as $modelId) {
            $model = $this->modeleRepository->find($modelId)
                ?? throw new NotFoundHttpException(sprintf('Modèle de chirurgie %d introuvable.', $modelId));

            $candidate = (new ChirurgiePlanifiee())
                ->setDateProgrammee($date)
                ->setSalle($room)
                ->setChirurgien($surgeon)
                ->setChirurgieModele($model);
            $this->initializer->findListe($candidate);
            $models[] = $model;
        }

        return $this->entityManager->wrapInTransaction(function () use (
            $date,
            $room,
            $surgeon,
            $surgeonId,
            $models,
            $actor,
        ): ProgrammeOperatoire {
            $order = $this->orderAllocator->reserveNextOrder($date, $room, $surgeon) - 1;
            $now = $this->auditTrail->now();

            foreach ($models as $model) {
                $chirurgie = (new ChirurgiePlanifiee())
                    ->setDateProgrammee($date)
                    ->setSalle($room)
                    ->setOrdre(++$order)
                    ->setChirurgien($surgeon)
                    ->setChirurgieModele($model);
                $this->auditTrail->markCreated($chirurgie, $actor, $now);
                $this->entityManager->persist($chirurgie);
                $this->initializer->initializeForChirurgie($chirurgie, flush: false);
            }

            $this->entityManager->flush();

            return $this->programmeService->getProgramme($date, $room, $surgeonId)
                ?? throw new ApiProblemException(
                    'PROGRAMME_CREATION_ECHOUEE',
                    'Le programme opératoire n’a pas pu être relu après sa création.',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
        });
    }
}
