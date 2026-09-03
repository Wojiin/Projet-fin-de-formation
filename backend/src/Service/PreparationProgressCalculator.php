<?php

namespace App\Service;

use App\Entity\PreparationMateriel;

/** Calcule les compteurs et l'état fonctionnel d'une checklist de matériel. */
final readonly class PreparationProgressCalculator
{
    public const string STATE_VALIDATED = 'VALIDEE';
    public const string STATE_PARTIAL = 'VALIDATION_PARTIELLE';
    public const string STATE_PREPARING = 'EN_PREPARATION';

    /**
     * @param iterable<PreparationMateriel> $preparations
     *
     * @return array{total: int, coches: int, absents: int, traites: int, complete: bool}
     */
    public function calculate(iterable $preparations): array
    {
        $progress = $this->emptyProgress();

        foreach ($preparations as $preparation) {
            ++$progress['total'];
            $progress['coches'] += $preparation->isCoche() ? 1 : 0;
            $progress['absents'] += $preparation->isAbsent() ? 1 : 0;
        }

        return $this->complete($progress);
    }

    /**
     * @return array{total: int, coches: int, absents: int, traites: int, complete: bool}
     */
    public function emptyProgress(): array
    {
        return ['total' => 0, 'coches' => 0, 'absents' => 0, 'traites' => 0, 'complete' => false];
    }

    /**
     * @param array{total: int, coches: int, absents: int, traites: int, complete: bool} $left
     * @param array{total: int, coches: int, absents: int, traites: int, complete: bool} $right
     *
     * @return array{total: int, coches: int, absents: int, traites: int, complete: bool}
     */
    public function merge(array $left, array $right): array
    {
        return $this->complete([
            'total' => $left['total'] + $right['total'],
            'coches' => $left['coches'] + $right['coches'],
            'absents' => $left['absents'] + $right['absents'],
            'traites' => 0,
            'complete' => false,
        ]);
    }

    /**
     * @param array{total: int, coches: int, absents: int, traites: int, complete: bool} $progress
     */
    public function validationState(bool $validated, array $progress): string
    {
        if ($validated) {
            return self::STATE_VALIDATED;
        }

        return $progress['complete'] && $progress['absents'] > 0
            ? self::STATE_PARTIAL
            : self::STATE_PREPARING;
    }

    /**
     * @param array{total: int, coches: int, absents: int, traites: int, complete: bool} $progress
     *
     * @return array{total: int, coches: int, absents: int, traites: int, complete: bool}
     */
    private function complete(array $progress): array
    {
        $progress['traites'] = $progress['coches'] + $progress['absents'];
        $progress['complete'] = $progress['total'] > 0 && $progress['total'] === $progress['traites'];

        return $progress;
    }
}
