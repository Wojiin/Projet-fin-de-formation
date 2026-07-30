<?php

namespace App\Repository;

use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ListeMateriel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListeMateriel>
 */
/** Accède aux listes de matériel définies pour chaque couple chirurgien / modèle. */
class ListeMaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListeMateriel::class);
    }

    /** Retourne la liste unique et ses matériels pour initialiser une préparation. */
    public function findOneForChirurgienAndChirurgieModele(
        Chirurgien $chirurgien,
        ChirurgieModele $chirurgieModele,
    ): ?ListeMateriel {
        return $this->createQueryBuilder('liste')
            ->leftJoin('liste.materiels', 'materiel')
            ->addSelect('materiel')
            ->andWhere('liste.chirurgien = :chirurgien')
            ->andWhere('liste.chirurgieModele = :modele')
            ->setParameter('chirurgien', $chirurgien)
            ->setParameter('modele', $chirurgieModele)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
