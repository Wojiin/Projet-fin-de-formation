<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\ProgrammeOperatoireProvider;

#[ApiResource(
    description: 'Programme opératoire utilisé pour consulter les chirurgies planifiées par date.',
    operations: [
        new GetCollection(
            uriTemplate: '/programmes-operatoires',
            provider: ProgrammeOperatoireProvider::class,
            security: "is_granted('ROLE_USER')",
            paginationEnabled: false,
            openapi: new OpenApiOperation(
                summary: 'Consulter le programme opératoire',
                description: 'Retourne une liste plate de chirurgies planifiées, filtrée et triée pour le frontend Vue 3.',
            ),
        ),
        new GetCollection(
            uriTemplate: '/programmes-operatoires/{date}',
            provider: ProgrammeOperatoireProvider::class,
            security: "is_granted('ROLE_USER')",
            paginationEnabled: false,
            openapi: new OpenApiOperation(
                summary: 'Consulter le programme opératoire d’une date',
                description: 'Retourne la liste plate des chirurgies planifiées à la date YYYY-MM-DD demandée.',
            ),
        ),
    ],
)]
final class ProgrammeOperatoire
{
    /**
     * @param array{id: int|null, prenom: string|null, nom: string|null} $chirurgien
     * @param array{id: int|null, intitule: string|null} $chirurgieModele
     * @param array{total: int, coches: int, complete: bool} $progressionPreparation
     */
    public function __construct(
        public readonly int $id,
        public readonly string $date,
        public readonly string $heure,
        public readonly string $dateProgrammee,
        public readonly string $salle,
        public readonly ?int $ordre,
        public readonly bool $valide,
        public readonly array $chirurgien,
        public readonly array $chirurgieModele,
        public readonly array $progressionPreparation,
    ) {
    }
}
