<?php

namespace App\Tests\Unit;

use App\Command\PurgeProgrammesOperatoiresCommand;
use App\Repository\ChirurgiePlanifieeRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeProgrammesOperatoiresCommandTest extends TestCase
{
    public function testItDeletesProgrammesBeforeToday(): void
    {
        $repository = $this->createMock(ChirurgiePlanifieeRepository::class);
        $repository
            ->expects(self::once())
            ->method('deleteProgrammesBefore')
            ->with(self::callback(static fn (\DateTimeInterface $date): bool => $date->format('Y-m-d') === (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->format('Y-m-d')))
            ->willReturn(4);

        $tester = new CommandTester(new PurgeProgrammesOperatoiresCommand($repository));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('4 chirurgie(s)', $tester->getDisplay());
    }
}
