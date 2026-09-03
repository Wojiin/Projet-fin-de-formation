<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** DTO d'écriture d'une ligne de matériel : prête, absente ou à traiter. */
#[Assert\Expression(
    expression: 'this.coche !== null or this.absent !== null',
    message: 'Un état prêt ou absent doit être fourni.',
)]
final class PreparationMaterielInput
{
    public function __construct(
        public readonly ?bool $coche = null,
        public readonly ?bool $absent = null,
    ) {
    }
}
