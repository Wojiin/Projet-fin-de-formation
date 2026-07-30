<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;

/** DTO de lecture : expose la synthèse figée disponible après validation d'une chirurgie. */
final class ChirurgieVueFinale
{
    public function __construct(
        public readonly int $id,
        #[ApiProperty(openapiContext: ['type' => 'string', 'format' => 'date'])]
        public readonly string $dateProgrammee,
        public readonly string $salle,
        public readonly ?int $ordre,
        public readonly bool $valide,
        public readonly ?string $valideLe,
        public readonly ?array $validePar,
        public readonly array $chirurgien,
        public readonly array $chirurgieModele,
        public readonly array $materielsValides,
        public readonly array $ficheTechnique,
    ) {
    }
}
