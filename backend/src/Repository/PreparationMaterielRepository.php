<?php

namespace App\Repository;

use App\Entity\PreparationMateriel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreparationMateriel>
 */
/** Dépôt Doctrine des lignes de checklist matériel par chirurgie. */
class PreparationMaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationMateriel::class);
    }
}
