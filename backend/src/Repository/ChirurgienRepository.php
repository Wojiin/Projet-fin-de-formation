<?php

namespace App\Repository;

use App\Entity\Chirurgien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chirurgien>
 */
/** Dépôt Doctrine du référentiel des chirurgiens. */
class ChirurgienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chirurgien::class);
    }
}
