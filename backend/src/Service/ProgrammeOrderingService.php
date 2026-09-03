<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeReference;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgiePlanifieeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Valide puis applique atomiquement la permutation complète d'un programme. */
final readonly class ProgrammeOrderingService
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private ProgrammeOperatoireService $programmeService,
        private ChirurgieAuditTrail $auditTrail,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<int> $chirurgieIds
     */
    public function reorder(ProgrammeReference $reference, array $chirurgieIds, string $actor): ProgrammeOperatoire
    {
        $chirurgies = $this->repository->findForProgrammeOperatoire(
            date: $reference->date,
            salle: $reference->salle,
            chirurgienId: $reference->chirurgienId,
        );
        if ([] === $chirurgies) {
            throw new NotFoundHttpException('Programme opératoire introuvable.');
        }

        $byId = [];
        foreach ($chirurgies as $chirurgie) {
            $byId[$chirurgie->getId()] = $chirurgie;
        }

        if (count($chirurgieIds) !== count($byId)
            || count(array_unique($chirurgieIds)) !== count($chirurgieIds)
            || array_diff($chirurgieIds, array_keys($byId))
            || array_diff(array_keys($byId), $chirurgieIds)
        ) {
            throw new ApiProblemException(
                'ORDRE_PROGRAMME_INVALIDE',
                'La liste doit contenir exactement une fois chaque chirurgie du programme.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->entityManager->wrapInTransaction(function () use (
            $reference,
            $chirurgieIds,
            $byId,
            $actor,
        ): ProgrammeOperatoire {
            $now = $this->auditTrail->now();
            foreach ($chirurgieIds as $index => $chirurgieId) {
                $byId[$chirurgieId]->setOrdre($index + 1);
                $this->auditTrail->markModified($byId[$chirurgieId], $actor, $now);
            }
            $this->entityManager->flush();

            return $this->programmeService->getProgramme(
                $reference->date,
                $reference->salle,
                $reference->chirurgienId,
            ) ?? throw new NotFoundHttpException('Programme opératoire introuvable.');
        });
    }
}
