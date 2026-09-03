<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ProgrammeOperatoire;
use App\Service\ProgrammeOperatoireService;
use App\Service\ProgrammeReferenceResolver;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traduit les paramètres HTTP en critères métier et délègue la lecture des
 * programmes agrégés au service dédié.
 */
final readonly class ProgrammeOperatoireProvider implements ProviderInterface
{
    public function __construct(
        private ProgrammeOperatoireService $service,
        private ProgrammeReferenceResolver $referenceResolver,
    ) {
    }

    /**
     * Retourne un programme ciblé ou une collection filtrée, en validant les dates
     * et identifiants avant toute lecture en base.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProgrammeOperatoire|array
    {
        if (isset($uriVariables['salle'], $uriVariables['chirurgien'], $uriVariables['date'])) {
            $reference = $this->referenceResolver->resolve($uriVariables);

            $programme = $this->service->getProgramme(
                date: $reference->date,
                salle: $reference->salle,
                chirurgienId: $reference->chirurgienId,
                vueFinale: str_ends_with($operation->getUriTemplate() ?? '', '/vue-finale'),
            );

            return $programme ?? throw new NotFoundHttpException('Programme opératoire introuvable.');
        }

        $dateValue = $this->parameter($operation, 'date');
        $dateDebutValue = $this->parameter($operation, 'dateDebut');
        $dateFinValue = $this->parameter($operation, 'dateFin');
        $salle = $this->parameter($operation, 'salle');
        $chirurgienId = $this->parameter($operation, 'chirurgien');
        $salle = null === $salle ? null : (string) $salle;
        $chirurgienId = null === $chirurgienId ? null : (int) $chirurgienId;

        if (null !== $dateValue) {
            return $this->service->getProgrammes(
                date: new DateTimeImmutable((string) $dateValue),
                salle: $salle,
                chirurgienId: $chirurgienId,
            );
        }

        if (null === $dateDebutValue && null === $dateFinValue) {
            return $this->service->getProgrammes(
                salle: $salle,
                chirurgienId: $chirurgienId,
            );
        }

        $dateDebut = null !== $dateDebutValue ? new DateTimeImmutable((string) $dateDebutValue) : null;
        $dateFin = null !== $dateFinValue ? new DateTimeImmutable((string) $dateFinValue) : null;
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

    /** Lit un paramètre API Platform et normalise l'absence de valeur en null. */
    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }
}
