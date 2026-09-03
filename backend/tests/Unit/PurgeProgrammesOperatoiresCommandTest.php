<?php

namespace App\Tests\Unit;

use App\Command\PurgeProgrammesOperatoiresCommand;
use App\Repository\ChirurgiePlanifieeRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Clock\MockClock;

final class PurgeProgrammesOperatoiresCommandTest extends TestCase
{
    public function testItDeletesProgrammesBeforeToday(): void
    {
        $repository = $this->createMock(ChirurgiePlanifieeRepository::class);
        $clock = new MockClock('2030-01-15 23:30:00 UTC');
        $repository
            ->expects(self::once())
            ->method('deleteProgrammesBefore')
            ->with(self::callback(static fn (\DateTimeInterface $date): bool => '2030-01-16' === $date->format('Y-m-d')))
            ->willReturn(4);

        $tester = new CommandTester(new PurgeProgrammesOperatoiresCommand($repository, $clock));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('4 chirurgie(s)', $tester->getDisplay());
    }
}
