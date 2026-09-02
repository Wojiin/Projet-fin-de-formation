<?php

namespace App\Service;

use App\Entity\Chirurgien;
use App\Entity\ChirurgiePlanifiee;
use App\Repository\ChirurgiePlanifieeRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sérialise l'attribution des positions d'un programme pour éviter que deux
 * créations concurrentes ne réservent le même ordre.
 */
final readonly class ProgrammeOrderAllocator
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Réserve le prochain ordre. L'appelant doit conserver la transaction
     * ouverte jusqu'à la persistance de la chirurgie qui utilisera cet ordre.
     */
    public function reserveNextOrder(\DateTimeInterface $date, string $salle, Chirurgien $chirurgien): int
    {
        $chirurgienId = $chirurgien->getId();
        if (null === $chirurgienId) {
            throw new \LogicException('Le chirurgien doit être persisté avant de réserver un ordre.');
        }

        // Tous les chemins de planification verrouillent la même ligne avant MAX + 1.
        $this->entityManager->lock($chirurgien, LockMode::PESSIMISTIC_WRITE);

        $maximum = array_reduce(
            $this->repository->findForProgrammeOperatoire(
                date: $date,
                salle: trim($salle),
                chirurgienId: $chirurgienId,
            ),
            static fn (int $order, ChirurgiePlanifiee $chirurgie): int => max($order, $chirurgie->getOrdre() ?? 0),
            0,
        );

        return $maximum + 1;
    }
}
