<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
/** Implémente les requêtes attendues par le bundle de nettoyage des refresh tokens. */
class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /** Retourne tous les refresh tokens expirés à la date de référence. */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        return $this->createQueryBuilder('refreshToken')
            ->andWhere('refreshToken.valid < :now')
            ->setParameter('now', $datetime ?? new DateTime())
            ->getQuery()
            ->getResult();
    }

    /** Retourne une page de refresh tokens expirés pour le nettoyage par lots. */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        return $this->createQueryBuilder('refreshToken')
            ->andWhere('refreshToken.valid < :now')
            ->setParameter('now', $datetime ?? new DateTime())
            ->setFirstResult($offset)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();
    }
}
