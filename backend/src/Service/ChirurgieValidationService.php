<?php

namespace App\Service;

use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ChirurgieValidationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function validate(ChirurgiePlanifiee $chirurgie, User $user): ChirurgiePlanifiee
    {
        if ($chirurgie->isValide()) {
            return $chirurgie;
        }

        if ($chirurgie->getPreparationsMateriel()->isEmpty()) {
            throw new ConflictHttpException('La chirurgie ne possède aucune préparation de matériel.');
        }

        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            if (!$preparation->isCoche()) {
                throw new ConflictHttpException('Tout le matériel doit être coché avant la validation.');
            }
        }

        $chirurgie
            ->setValide(true)
            ->setValideLe(new \DateTimeImmutable())
            ->setValidePar($user);

        $this->entityManager->flush();

        return $chirurgie;
    }
}
