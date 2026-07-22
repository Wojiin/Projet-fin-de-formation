<?php

namespace App\Dto;

final class ChirurgiePreparation
{
    public function __construct(
        public readonly int $id,
        public readonly string $dateProgrammee,
        public readonly string $salle,
        public readonly ?int $ordre,
        public readonly bool $valide,
        public readonly array $chirurgien,
        public readonly array $chirurgieModele,
        public readonly array $preparationsMateriel,
        public readonly array $progressionPreparation,
    ) {
    }
}
