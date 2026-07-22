<?php

namespace App\Dto;

/** Vue utilisee par la page d accueil. */
final class ProgrammeOperatoireResume
{
    /** @param array{id: int|null, prenom: string|null, nom: string|null} $chirurgien */
    public function __construct(
        public readonly string $date,
        public readonly string $salle,
        public readonly array $chirurgien,
        public readonly int $nombreChirurgies,
        public readonly int $nombreChirurgiesValidees,
        public readonly array $progressionPreparation,
    ) {
    }
}
