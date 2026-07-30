<?php

namespace App\Service;

use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use App\Exception\ApiProblemException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique la règle de clôture d'une chirurgie : une chirurgie n'est validable
 * que lorsque chacune de ses préparations de matériel est cochée.
 */
final readonly class ChirurgieValidationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
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

        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            if (!$preparation->isCoche()) {
                throw new ApiProblemException('MATERIEL_PREPARATION_INCOMPLETE', 'Tout le matériel doit être coché avant la validation.');
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
