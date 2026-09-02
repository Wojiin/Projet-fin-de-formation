<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammePlanificationInput;
use App\Dto\ProgrammePlanificationOutput;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgienRepository;
use App\Repository\ChirurgieModeleRepository;
use App\Service\PreparationMaterielInitializer;
use App\Service\ProgrammeOrderAllocator;
use App\Service\ProgrammeOperatoireService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        private PreparationMaterielInitializer $initializer,
        private ProgrammeOrderAllocator $orderAllocator,
        private ProgrammeOperatoireService $programmeService,
        private EntityManagerInterface $entityManager,
        private Security $security,
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

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }
        $identifier = $user->getUserIdentifier();

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
        $programme = $this->entityManager->wrapInTransaction(function () use ($data, $date, $chirurgien, $modeles, $identifier): ProgrammeOperatoire {
            $ordre = $this->orderAllocator->reserveNextOrder($date, $data->salle, $chirurgien) - 1;

            foreach ($modeles as $modele) {
                $now = new \DateTimeImmutable();
                $chirurgie = (new ChirurgiePlanifiee())
                    ->setDateProgrammee($data->dateProgrammee)
                    ->setSalle(trim($data->salle))
                    ->setOrdre(++$ordre)
                    ->setChirurgien($chirurgien)
                    ->setChirurgieModele($modele)
                    ->setCreeLe($now)
                    ->setCreePar($identifier)
                    ->setModifieLe($now)
                    ->setModifiePar($identifier);
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
