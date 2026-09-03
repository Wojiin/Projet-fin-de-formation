<?php

namespace App\Service;

use App\Dto\ProgrammeReference;
use App\Exception\ApiProblemException;
use Symfony\Component\HttpFoundation\Response;

/** Normalise et valide les trois segments d'URI qui identifient un programme. */
final readonly class ProgrammeReferenceResolver
{
    /**
     * @param array<string, mixed> $uriVariables
     */
    public function resolve(array $uriVariables): ProgrammeReference
    {
        $dateValue = (string) ($uriVariables['date'] ?? '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if (false === $date || $date->format('Y-m-d') !== $dateValue) {
            throw new ApiProblemException(
                'PROGRAMME_INVALIDE',
                'La date doit respecter le format YYYY-MM-DD.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $chirurgienId = filter_var(
            $uriVariables['chirurgien'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        if (false === $chirurgienId) {
            throw new ApiProblemException(
                'PROGRAMME_INVALIDE',
                'Le chirurgien doit être un identifiant entier positif.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new ProgrammeReference(
            date: $date,
            salle: trim((string) ($uriVariables['salle'] ?? '')),
            chirurgienId: $chirurgienId,
        );
    }
}
