<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** DTO d'écriture limité à la transition cochée / décochée d'un matériel. */
final class PreparationMaterielInput
{
    public function __construct(
        #[Assert\NotNull]
        public readonly ?bool $coche = null,
    ) {
    }
}
