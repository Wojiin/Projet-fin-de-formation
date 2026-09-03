<?php

namespace App\Tests\Unit;

use App\Entity\Chirurgien;
use App\Entity\ChirurgiePlanifiee;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\ProgrammeOrderAllocator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProgrammeOrderAllocatorTest extends TestCase
{
    public function testItLocksTheSurgeonBeforeComputingTheNextOrder(): void
    {
        $chirurgien = new Chirurgien();
        $id = new \ReflectionProperty(Chirurgien::class, 'id');
        $id->setValue($chirurgien, 7);

        $first = (new ChirurgiePlanifiee())->setOrdre(2);
        $second = (new ChirurgiePlanifiee())->setOrdre(5);
        $lockAcquired = false;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('lock')
            ->with($chirurgien, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(static function () use (&$lockAcquired): void {
                $lockAcquired = true;
            });

        $repository = $this->createMock(ChirurgiePlanifieeRepository::class);
        $repository->expects(self::once())
            ->method('findForProgrammeOperatoire')
            ->willReturnCallback(static function () use (&$lockAcquired, $first, $second): array {
                self::assertTrue($lockAcquired, 'Le verrou doit précéder la lecture du maximum.');

                return [$first, $second];
            });

        $allocator = new ProgrammeOrderAllocator($repository, $entityManager);

        self::assertSame(6, $allocator->reserveNextOrder(
            new \DateTimeImmutable('2030-01-15'),
            ' Salle A ',
            $chirurgien,
        ));
    }

    public function testItRejectsAnUnpersistedSurgeon(): void
    {
        $allocator = new ProgrammeOrderAllocator(
            $this->createStub(ChirurgiePlanifieeRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(\LogicException::class);
        $allocator->reserveNextOrder(new \DateTimeImmutable(), 'Salle A', new Chirurgien());
    }
}
