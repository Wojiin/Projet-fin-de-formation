<?php

namespace App\Repository;

use App\Entity\ChirurgieModele;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChirurgieModele>
 */
/** Dépôt Doctrine du référentiel des chirurgies modèles. */
class ChirurgieModeleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChirurgieModele::class);
    }
}
