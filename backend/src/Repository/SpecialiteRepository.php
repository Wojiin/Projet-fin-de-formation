<?php

namespace App\Repository;

use App\Entity\Specialite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Specialite>
 */
/** Fournit l'accès à la spécialité de repli utilisée lors des suppressions. */
class SpecialiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Specialite::class);
    }

    /** Recherche la spécialité système « Sans spécialité ». */
    public function findDefault(): ?Specialite
    {
        return $this->findOneBy(['intitule' => Specialite::SANS_SPECIALITE]);
    }
}
