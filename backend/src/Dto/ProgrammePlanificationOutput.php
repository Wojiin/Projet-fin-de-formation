<?php

namespace App\Dto;

/** Réponse métier renvoyée après la création atomique d'un programme opératoire. */
final class ProgrammePlanificationOutput
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

    /** Convertit l'agrégat de lecture en contrat de réponse de planification. */
    public static function fromProgramme(ProgrammeOperatoire $programme): self
    {
        return new self(
            id: $programme->id,
            date: $programme->date,
            salle: $programme->salle,
            chirurgien: $programme->chirurgien,
            nombreChirurgies: $programme->nombreChirurgies,
            nombreChirurgiesValidees: $programme->nombreChirurgiesValidees,
            chirurgies: $programme->chirurgies,
        );
    }
}
