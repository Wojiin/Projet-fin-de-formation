<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\ProgrammeOperatoireProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    description: 'Vue de lecture du workflow operatoire : programmes, preparations et chirurgies validees.',
    operations: [
        new GetCollection(
            uriTemplate: '/programmes-operatoires',
            provider: ProgrammeOperatoireProvider::class,
            security: "is_granted('ROLE_USER')",
            paginationEnabled: false,
            strictQueryParameterValidation: true,
            parameters: [
                'date' => new QueryParameter(description: 'Jour exact au format YYYY-MM-DD.', constraints: [new Assert\Date()]),
                'dateDebut' => new QueryParameter(description: 'Début de la période au format YYYY-MM-DD.', constraints: [new Assert\Date()]),
                'dateFin' => new QueryParameter(description: 'Fin de la période au format YYYY-MM-DD.', constraints: [new Assert\Date()]),
                'salle' => new QueryParameter(description: 'Nom exact de la salle.', constraints: [new Assert\Length(max: 50)]),
                'chirurgien' => new QueryParameter(description: 'Identifiant du chirurgien.', schema: ['type' => 'integer', 'minimum' => 1], castToNativeType: true, constraints: [new Assert\Positive()]),
            ],
            openapi: new OpenApiOperation(
                summary: 'Lister les programmes operatoires',
                description: 'Regroupe les chirurgies planifiees par jour, salle et chirurgien. Les filtres date, dateDebut, dateFin, salle et chirurgien sont acceptes.',
            ),
        ),
        new Get(
            uriTemplate: '/programmes-operatoires/{date}/{salle}/{chirurgien}',
            provider: ProgrammeOperatoireProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                summary: 'Consulter le detail d un programme operatoire',
                description: 'Retourne les chirurgies planifiees et leur checklist pour le jour, la salle et le chirurgien demandes.',
            ),
        ),
        new Get(
            uriTemplate: '/programmes-operatoires/{date}/{salle}/{chirurgien}/vue-finale',
            provider: ProgrammeOperatoireProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                summary: 'Consulter la vue finale d un programme',
                description: 'Retourne uniquement les chirurgies validees, en lecture seule, avec leurs fiches techniques.',
            ),
        ),
    ],
)]
final class ProgrammeOperatoire
{
    /**
     * @param array{id: int|null, prenom: string|null, nom: string|null} $chirurgien
     * @param list<array<string, mixed>> $chirurgies
     */
    public function __construct(
        public readonly string $id,
        public readonly string $date,
        public readonly string $salle,
        public readonly array $chirurgien,
        public readonly int $nombreChirurgies,
        public readonly int $nombreChirurgiesValidees,
        public readonly array $chirurgies,
    ) {
    }
}
