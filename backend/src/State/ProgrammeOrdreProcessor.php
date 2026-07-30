<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeOrdreInput;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\ProgrammeOperatoireService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ProgrammeOrdreInput, ProgrammeOperatoire>
 */
/**
 * Réordonne atomiquement toutes les chirurgies d'un programme en garantissant
 * que la liste reçue contient exactement les chirurgies concernées.
 */
final readonly class ProgrammeOrdreProcessor implements ProcessorInterface
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private ProgrammeOperatoireService $programmeService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Vérifie l'intégrité de l'ordre fourni, recalcule les positions consécutives
     * puis relit le programme dans la même transaction.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProgrammeOperatoire
    {
        if (!$data instanceof ProgrammeOrdreInput) {
            throw new \InvalidArgumentException('Une liste ordonnée de chirurgies est attendue.');
        }

        $date = $this->parseDate((string) ($uriVariables['date'] ?? ''));
        $salle = trim((string) ($uriVariables['salle'] ?? ''));
        $chirurgienId = filter_var($uriVariables['chirurgien'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $chirurgienId) {
            throw new ApiProblemException('PROGRAMME_INVALIDE', 'Le chirurgien doit être un identifiant entier positif.', Response::HTTP_BAD_REQUEST);
        }

        $chirurgies = $this->repository->findForProgrammeOperatoire(
            date: $date,
            salle: $salle,
            chirurgienId: $chirurgienId,
        );
        if ([] === $chirurgies) {
            throw new NotFoundHttpException('Programme opératoire introuvable.');
        }

        $parId = [];
        foreach ($chirurgies as $chirurgie) {
            $parId[$chirurgie->getId()] = $chirurgie;
        }

        if (count($data->chirurgieIds) !== count($parId)
            || count(array_unique($data->chirurgieIds)) !== count($data->chirurgieIds)
            || array_diff($data->chirurgieIds, array_keys($parId))
            || array_diff(array_keys($parId), $data->chirurgieIds)
        ) {
            throw new ApiProblemException(
                'ORDRE_PROGRAMME_INVALIDE',
                'La liste doit contenir exactement une fois chaque chirurgie du programme.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->entityManager->wrapInTransaction(function () use ($data, $date, $salle, $chirurgienId, $parId): ProgrammeOperatoire {
            foreach ($data->chirurgieIds as $index => $chirurgieId) {
                $parId[$chirurgieId]->setOrdre($index + 1);
            }
            $this->entityManager->flush();

            return $this->programmeService->getProgramme($date, $salle, $chirurgienId)
                ?? throw new NotFoundHttpException('Programme opératoire introuvable.');
        });
    }

    /** Convertit une date d'URI en date immuable ou signale une requête invalide. */
    private function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new ApiProblemException('PROGRAMME_INVALIDE', 'La date doit respecter le format YYYY-MM-DD.', Response::HTTP_BAD_REQUEST);
        }

        return $date;
    }
}
