<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ProgrammeOperatoire;
use App\Service\ProgrammeOperatoireService;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ProgrammeOperatoireProvider implements ProviderInterface
{
    public function __construct(private ProgrammeOperatoireService $service)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProgrammeOperatoire|array
    {
        if (isset($uriVariables['salle'], $uriVariables['chirurgien'], $uriVariables['date'])) {
            $chirurgienId = filter_var($uriVariables['chirurgien'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (false === $chirurgienId) {
                throw new BadRequestHttpException('Le chirurgien doit être un identifiant entier positif.');
            }

            $programme = $this->service->getProgramme(
                date: $this->parseDate((string) $uriVariables['date']),
                salle: trim((string) $uriVariables['salle']),
                chirurgienId: $chirurgienId,
                vueFinale: str_ends_with($operation->getUriTemplate() ?? '', '/vue-finale'),
            );

            return $programme ?? throw new NotFoundHttpException('Programme opératoire introuvable.');
        }

        $dateValue = $this->parameter($operation, 'date');
        $dateDebutValue = $this->parameter($operation, 'dateDebut');
        $dateFinValue = $this->parameter($operation, 'dateFin');
        $salle = $this->parameter($operation, 'salle');
        $chirurgienId = $this->parameter($operation, 'chirurgien');

        if (null !== $dateValue) {
            return $this->service->getProgrammes(
                date: new DateTimeImmutable($dateValue),
                salle: $salle,
                chirurgienId: $chirurgienId,
            );
        }

        if (null === $dateDebutValue && null === $dateFinValue) {
            return $this->service->getProgrammes(
                date: new DateTimeImmutable('today'),
                salle: $salle,
                chirurgienId: $chirurgienId,
            );
        }

        $dateDebut = null !== $dateDebutValue ? new DateTimeImmutable($dateDebutValue) : null;
        $dateFin = null !== $dateFinValue ? new DateTimeImmutable($dateFinValue) : null;
        if (null !== $dateDebut && null !== $dateFin && $dateFin < $dateDebut) {
            throw new BadRequestHttpException('dateFin doit être postérieure ou égale à dateDebut.');
        }

        return $this->service->getProgrammes(
            dateDebut: $dateDebut,
            dateFin: $dateFin,
            salle: $salle,
            chirurgienId: $chirurgienId,
        );
    }

    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(sprintf('Date invalide "%s". Format attendu : YYYY-MM-DD.', $value));
        }

        return $date;
    }
}
