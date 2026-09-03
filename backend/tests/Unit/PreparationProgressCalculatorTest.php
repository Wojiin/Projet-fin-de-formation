<?php

namespace App\Tests\Unit;

use App\Entity\PreparationMateriel;
use App\Service\PreparationProgressCalculator;
use PHPUnit\Framework\TestCase;

final class PreparationProgressCalculatorTest extends TestCase
{
    public function testItCalculatesProgressAndPartialValidationState(): void
    {
        $ready = (new PreparationMateriel())->setCoche(true);
        $absent = (new PreparationMateriel())->setAbsent(true);
        $calculator = new PreparationProgressCalculator();

        $progress = $calculator->calculate([$ready, $absent]);

        self::assertSame([
            'total' => 2,
            'coches' => 1,
            'absents' => 1,
            'traites' => 2,
            'complete' => true,
        ], $progress);
        self::assertSame(
            PreparationProgressCalculator::STATE_PARTIAL,
            $calculator->validationState(false, $progress),
        );
        self::assertSame(
            PreparationProgressCalculator::STATE_VALIDATED,
            $calculator->validationState(true, $progress),
        );
    }

    public function testItMergesProgressWithoutLosingDerivedCounters(): void
    {
        $calculator = new PreparationProgressCalculator();
        $progress = $calculator->merge(
            $calculator->calculate([(new PreparationMateriel())->setCoche(true)]),
            $calculator->calculate([new PreparationMateriel()]),
        );

        self::assertSame(2, $progress['total']);
        self::assertSame(1, $progress['traites']);
        self::assertFalse($progress['complete']);
        self::assertSame(
            PreparationProgressCalculator::STATE_PREPARING,
            $calculator->validationState(false, $progress),
        );
    }
}
