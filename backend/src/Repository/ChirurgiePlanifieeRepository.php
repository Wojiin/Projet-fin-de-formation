<?php

namespace App\Repository;

use App\Entity\ChirurgiePlanifiee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * @extends ServiceEntityRepository<ChirurgiePlanifiee>
 */
/** Centralise les requêtes de lecture nécessaires aux programmes, préparations et vues finales. */
class ChirurgiePlanifieeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChirurgiePlanifiee::class);
    }

    /**
     * Charge les chirurgies d'un ou plusieurs programmes avec leurs relations
     * nécessaires, en appliquant les filtres métier fournis.
     *
     * @return list<ChirurgiePlanifiee>
     */
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
            $qb->andWhere('chirurgie.dateProgrammee = :date')
                ->setParameter('date', DateTimeImmutable::createFromInterface($date), Types::DATE_IMMUTABLE);
        } else {
            if (null !== $dateDebut) {
                $qb->andWhere('chirurgie.dateProgrammee >= :dateDebut')
                    ->setParameter('dateDebut', DateTimeImmutable::createFromInterface($dateDebut), Types::DATE_IMMUTABLE);
            }
            if (null !== $dateFin) {
                $qb->andWhere('chirurgie.dateProgrammee <= :dateFin')
                    ->setParameter('dateFin', DateTimeImmutable::createFromInterface($dateFin), Types::DATE_IMMUTABLE);
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

    /** Charge une chirurgie avec sa checklist complète pour l'écran de préparation. */
    public function findPreparationData(int $id): ?ChirurgiePlanifiee
    {
        return $this->baseDataQuery()
            ->andWhere('chirurgie.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Charge les données de clôture, y compris les fiches techniques du modèle. */
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

    /** Construit la jointure commune évitant les requêtes N+1 sur les programmes. */
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
