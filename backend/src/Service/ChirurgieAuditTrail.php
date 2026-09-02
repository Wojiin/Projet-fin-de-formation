<?php

namespace App\Service;

use App\Entity\ChirurgiePlanifiee;
use Psr\Clock\ClockInterface;

/** Applique de façon cohérente les métadonnées de création et de modification. */
final readonly class ChirurgieAuditTrail
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }

    public function markCreated(
        ChirurgiePlanifiee $chirurgie,
        string $actor,
        ?\DateTimeImmutable $at = null,
    ): \DateTimeImmutable {
        $at ??= $this->now();
        $chirurgie
            ->setCreeLe($at)
            ->setCreePar($actor)
            ->setModifieLe($at)
            ->setModifiePar($actor);

        return $at;
    }

    public function markModified(
        ChirurgiePlanifiee $chirurgie,
        string $actor,
        ?\DateTimeImmutable $at = null,
    ): \DateTimeImmutable {
        $at ??= $this->now();
        $chirurgie
            ->setModifieLe($at)
            ->setModifiePar($actor);

        return $at;
    }
}
