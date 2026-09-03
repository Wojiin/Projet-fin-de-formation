<?php

namespace App\Tests\Unit;

use App\Entity\ChirurgiePlanifiee;
use App\Service\ChirurgieAuditTrail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ChirurgieAuditTrailTest extends TestCase
{
    public function testItUsesTheApplicationClockForCreationAndModificationMetadata(): void
    {
        $clock = new MockClock('2030-01-15 08:30:00 UTC');
        $auditTrail = new ChirurgieAuditTrail($clock);
        $chirurgie = new ChirurgiePlanifiee();

        $createdAt = $auditTrail->markCreated($chirurgie, 'creator@chirorg.test');

        self::assertSame('2030-01-15T08:30:00+00:00', $createdAt->format(\DateTimeInterface::ATOM));
        self::assertSame($createdAt, $chirurgie->getCreeLe());
        self::assertSame($createdAt, $chirurgie->getModifieLe());
        self::assertSame('creator@chirorg.test', $chirurgie->getCreePar());

        $clock->sleep(3600);
        $modifiedAt = $auditTrail->markModified($chirurgie, 'editor@chirorg.test');

        self::assertSame('2030-01-15T09:30:00+00:00', $modifiedAt->format(\DateTimeInterface::ATOM));
        self::assertSame($createdAt, $chirurgie->getCreeLe());
        self::assertSame($modifiedAt, $chirurgie->getModifieLe());
        self::assertSame('editor@chirorg.test', $chirurgie->getModifiePar());
    }
}
