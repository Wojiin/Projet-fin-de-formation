<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PreparationMaterielInput
{
    public function __construct(
        #[Assert\NotNull]
        public readonly ?bool $coche = null,
    ) {
    }
}
