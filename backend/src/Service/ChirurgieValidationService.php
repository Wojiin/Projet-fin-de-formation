<?php

namespace App\Service;

use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use App\Exception\ApiProblemException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique la règle de clôture d'une chirurgie : une chirurgie n'est validable
 * lorsque chaque matériel est soit prêt, soit explicitement déclaré absent.
 */
final readonly class ChirurgieValidationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChirurgieAuditTrail $auditTrail,
    ) {
    }

    /**
     * Valide la chirurgie et mémorise l'utilisateur ainsi que la date de validation.
     * La méthode est idempotente afin qu'une seconde demande ne modifie pas l'historique.
     */
    public function validate(ChirurgiePlanifiee $chirurgie, User $user): ChirurgiePlanifiee
    {
        if ($chirurgie->isValide()) {
            return $chirurgie;
        }

        if ($chirurgie->getPreparationsMateriel()->isEmpty()) {
            throw new ApiProblemException('MATERIEL_PREPARATION_INCOMPLETE', 'La chirurgie ne possède aucune préparation de matériel.');
        }

        $hasAbsent = false;
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            if (!$preparation->isCoche() && !$preparation->isAbsent()) {
                throw new ApiProblemException('MATERIEL_PREPARATION_INCOMPLETE', 'Tout le matériel doit être déclaré prêt ou absent avant la validation.');
            }
            $hasAbsent = $hasAbsent || $preparation->isAbsent();
        }

        $now = $this->auditTrail->now();
        if ($hasAbsent) {
            $this->auditTrail->markModified($chirurgie, $user->getUserIdentifier(), $now);
            $this->entityManager->flush();

            return $chirurgie;
        }

        $chirurgie
            ->setValide(true)
            ->setValideLe($now)
            ->setValidePar($user);
        $this->auditTrail->markModified($chirurgie, $user->getUserIdentifier(), $now);

        $this->entityManager->flush();

        return $chirurgie;
    }
}
