<?php

namespace App\Dto;

/** Identifie sans ambiguïté un programme par son jour, sa salle et son chirurgien. */
final readonly class ProgrammeReference
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $salle,
        public int $chirurgienId,
    ) {
    }
}
