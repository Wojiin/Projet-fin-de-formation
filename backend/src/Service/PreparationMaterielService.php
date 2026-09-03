<?php

namespace App\Service;

use App\Entity\PreparationMateriel;
use App\Entity\User;
use App\Exception\ApiProblemException;
use Doctrine\ORM\EntityManagerInterface;

/** Applique les transitions métier d'une ligne de préparation de matériel. */
final readonly class PreparationMaterielService
{
    public function __construct(
        private ChirurgieAuditTrail $auditTrail,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function updateState(
        PreparationMateriel $preparation,
        bool $checked,
        bool $absent,
        User $user,
    ): PreparationMateriel {
        if ($checked && $absent) {
            throw new \InvalidArgumentException('Un matériel ne peut pas être à la fois prêt et absent.');
        }

        $chirurgie = $preparation->getChirurgiePlanifiee();
        if ($chirurgie?->isValide()) {
            throw new ApiProblemException(
                'PREPARATION_VERROUILLEE',
                'Le matériel d’une chirurgie validée ne peut plus être modifié.',
            );
        }

        $now = $this->auditTrail->now();
        $preparation->setCoche($checked)->setAbsent($absent);
        if ($checked) {
            $preparation->setCocheLe($now)->setCochePar($user);
        } else {
            $preparation->setCocheLe(null)->setCochePar(null);
        }

        if (null !== $chirurgie) {
            $this->auditTrail->markModified($chirurgie, $user->getUserIdentifier(), $now);
        }

        $this->entityManager->flush();

        return $preparation;
    }
}
