<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammePlanificationInput;
use App\Dto\ProgrammePlanificationOutput;
use App\Entity\ChirurgiePlanifiee;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgienRepository;
use App\Repository\ChirurgieModeleRepository;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\PreparationMaterielInitializer;
use App\Service\ProgrammeOperatoireService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ProgrammePlanificationInput, ProgrammePlanificationOutput>
 */
/**
 * Crée un programme entier à partir de modèles de chirurgie et initialise leur
 * matériel dans une transaction unique.
 */
final readonly class ProgrammePlanificationProcessor implements ProcessorInterface
{
    public function __construct(
        private ChirurgienRepository $chirurgienRepository,
        private ChirurgieModeleRepository $modeleRepository,
        private ChirurgiePlanifieeRepository $chirurgieRepository,
        private PreparationMaterielInitializer $initializer,
        private ProgrammeOperatoireService $programmeService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Valide les références, réserve les ordres suivants du programme et retourne
     * la représentation agrégée après persistance.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProgrammePlanificationOutput
    {
        if (!$data instanceof ProgrammePlanificationInput
            || null === $data->dateProgrammee
            || null === $data->salle
            || null === $data->chirurgienId
        ) {
            throw new \InvalidArgumentException('Un programme opératoire valide est attendu.');
        }

        $chirurgien = $this->chirurgienRepository->find($data->chirurgienId)
            ?? throw new NotFoundHttpException('Chirurgien introuvable.');

        $modeles = [];
        foreach ($data->chirurgieModeleIds as $modeleId) {
            $modele = $this->modeleRepository->find($modeleId)
                ?? throw new NotFoundHttpException(sprintf('Modèle de chirurgie %d introuvable.', $modeleId));

            $chirurgie = (new ChirurgiePlanifiee())
                ->setDateProgrammee($data->dateProgrammee)
                ->setSalle(trim($data->salle))
                ->setChirurgien($chirurgien)
                ->setChirurgieModele($modele);
            $this->initializer->findListe($chirurgie);
            $modeles[] = $modele;
        }

        $date = $data->dateProgrammee;
        $programme = $this->entityManager->wrapInTransaction(function () use ($data, $date, $chirurgien, $modeles): ProgrammeOperatoire {
            $chirurgiesExistantes = $this->chirurgieRepository->findForProgrammeOperatoire(
                date: $date,
                salle: trim($data->salle),
                chirurgienId: $chirurgien->getId(),
            );
            $ordre = array_reduce(
                $chirurgiesExistantes,
                static fn (int $maximum, ChirurgiePlanifiee $chirurgie): int => max($maximum, $chirurgie->getOrdre() ?? 0),
                0,
            );

            foreach ($modeles as $modele) {
                $chirurgie = (new ChirurgiePlanifiee())
                    ->setDateProgrammee($data->dateProgrammee)
                    ->setSalle(trim($data->salle))
                    ->setOrdre(++$ordre)
                    ->setChirurgien($chirurgien)
                    ->setChirurgieModele($modele);
                $this->entityManager->persist($chirurgie);
                $this->initializer->initializeForChirurgie($chirurgie, flush: false);
            }

            $this->entityManager->flush();

            return $this->programmeService->getProgramme($date, trim($data->salle), $chirurgien->getId())
                ?? throw new ApiProblemException(
                    'PROGRAMME_CREATION_ECHOUEE',
                    'Le programme opératoire n’a pas pu être relu après sa création.',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
        });

        return ProgrammePlanificationOutput::fromProgramme($programme);
    }
}
