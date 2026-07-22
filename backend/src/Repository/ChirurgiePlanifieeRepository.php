<?php

namespace App\Repository;

use App\Entity\ChirurgiePlanifiee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * @extends ServiceEntityRepository<ChirurgiePlanifiee>
 */
class ChirurgiePlanifieeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChirurgiePlanifiee::class);
    }

    /** @return list<ChirurgiePlanifiee> */
    public function findForProgrammeOperatoire(
        ?DateTimeInterface $date = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
        ?string $salle = null,
        ?int $chirurgienId = null,
        ?bool $valide = null,
        bool $withFichesTechniques = false,
    ): array {
        $qb = $this->baseDataQuery();

        if (null !== $date) {
            $debut = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
            $fin = $debut->setTime(23, 59, 59, 999999);
            $qb->andWhere('chirurgie.dateProgrammee BETWEEN :debut AND :fin')
                ->setParameter('debut', $debut)
                ->setParameter('fin', $fin);
        } else {
            if (null !== $dateDebut) {
                $qb->andWhere('chirurgie.dateProgrammee >= :dateDebut')
                    ->setParameter('dateDebut', DateTimeImmutable::createFromInterface($dateDebut)->setTime(0, 0));
            }
            if (null !== $dateFin) {
                $qb->andWhere('chirurgie.dateProgrammee <= :dateFin')
                    ->setParameter('dateFin', DateTimeImmutable::createFromInterface($dateFin)->setTime(23, 59, 59, 999999));
            }
        }

        if (null !== $salle && '' !== trim($salle)) {
            $qb->andWhere('chirurgie.salle = :salle')->setParameter('salle', trim($salle));
        }

        if (null !== $chirurgienId) {
            $qb->andWhere('chirurgien.id = :chirurgienId')->setParameter('chirurgienId', $chirurgienId);
        }

        if (null !== $valide) {
            $qb->andWhere('chirurgie.valide = :valide')->setParameter('valide', $valide);
        }

        if ($withFichesTechniques) {
            $qb->leftJoin('modele.fichesTechniques', 'fiche')->addSelect('fiche');
        }

        return $qb
            ->orderBy('chirurgie.dateProgrammee', 'ASC')
            ->addOrderBy('chirurgie.salle', 'ASC')
            ->addOrderBy('chirurgien.nom', 'ASC')
            ->addOrderBy('chirurgien.prenom', 'ASC')
            ->addOrderBy('chirurgie.ordre', 'ASC')
            ->addOrderBy('chirurgie.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPreparationData(int $id): ?ChirurgiePlanifiee
    {
        return $this->baseDataQuery()
            ->andWhere('chirurgie.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findVueFinaleData(int $id): ?ChirurgiePlanifiee
    {
        return $this->baseDataQuery()
            ->leftJoin('modele.fichesTechniques', 'fiche')
            ->addSelect('fiche')
            ->andWhere('chirurgie.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function baseDataQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('chirurgie')
            ->select('DISTINCT chirurgie', 'chirurgien', 'modele', 'preparation', 'materiel', 'validePar')
            ->innerJoin('chirurgie.chirurgien', 'chirurgien')
            ->innerJoin('chirurgie.chirurgieModele', 'modele')
            ->leftJoin('chirurgie.preparationsMateriel', 'preparation')
            ->leftJoin('preparation.materiel', 'materiel')
            ->leftJoin('chirurgie.validePar', 'validePar');
    }
}
