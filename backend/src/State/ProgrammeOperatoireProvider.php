<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\ProgrammeOperatoireService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ProgrammeOperatoireProvider implements ProviderInterface
{
    public function __construct(
        private ProgrammeOperatoireService $service,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $dateValue = $uriVariables['date'] ?? $request?->query->getString('date');
        $dateDebutValue = $request?->query->getString('dateDebut');
        $dateFinValue = $request?->query->getString('dateFin');
        $salle = $request?->query->getString('salle');

        if (is_string($dateValue) && '' !== $dateValue) {
            return $this->service->getProgramme(date: $this->parseDate($dateValue), salle: $salle ?: null);
        }

        if (!$dateDebutValue && !$dateFinValue) {
            return $this->service->getProgramme(date: new DateTimeImmutable('today'), salle: $salle ?: null);
        }

        $dateDebut = $dateDebutValue ? $this->parseDate($dateDebutValue) : null;
        $dateFin = $dateFinValue ? $this->parseDate($dateFinValue) : null;

        if (null !== $dateDebut && null !== $dateFin && $dateFin < $dateDebut) {
            throw new BadRequestHttpException('dateFin doit être postérieure ou égale à dateDebut.');
        }

        return $this->service->getProgramme(
            dateDebut: $dateDebut,
            dateFin: $dateFin,
            salle: $salle ?: null,
        );
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (false === $date || $date->format('Y-m-d') !== $value || (false !== $errors && (0 < $errors['warning_count'] || 0 < $errors['error_count']))) {
            throw new BadRequestHttpException(sprintf('Date invalide "%s". Format attendu : YYYY-MM-DD.', $value));
        }

        return $date;
    }
}
