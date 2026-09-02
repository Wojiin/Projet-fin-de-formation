<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;

/** DTO de lecture : porte la checklist et la progression d'une chirurgie planifiée. */
final class ChirurgiePreparation
{
    public function __construct(
        public readonly int $id,
        #[ApiProperty(openapiContext: ['type' => 'string', 'format' => 'date'])]
        public readonly string $dateProgrammee,
        public readonly string $salle,
        public readonly ?int $ordre,
        public readonly bool $valide,
        public readonly string $etatValidation,
        public readonly array $chirurgien,
        public readonly array $chirurgieModele,
        public readonly array $preparationsMateriel,
        public readonly array $progressionPreparation,
    ) {
    }
}
