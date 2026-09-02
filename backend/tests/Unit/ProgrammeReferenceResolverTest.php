<?php

namespace App\Tests\Unit;

use App\Exception\ApiProblemException;
use App\Service\ProgrammeReferenceResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProgrammeReferenceResolverTest extends TestCase
{
    public function testItNormalizesAValidProgrammeReference(): void
    {
        $reference = (new ProgrammeReferenceResolver())->resolve([
            'date' => '2030-01-15',
            'salle' => ' Salle A ',
            'chirurgien' => '12',
        ]);

        self::assertSame('2030-01-15', $reference->date->format('Y-m-d'));
        self::assertSame('Salle A', $reference->salle);
        self::assertSame(12, $reference->chirurgienId);
    }

    #[DataProvider('invalidReferences')]
    public function testItRejectsInvalidReferences(array $uriVariables): void
    {
        try {
            (new ProgrammeReferenceResolver())->resolve($uriVariables);
            self::fail('Une référence invalide doit être refusée.');
        } catch (ApiProblemException $exception) {
            self::assertSame('PROGRAMME_INVALIDE', $exception->errorCode);
            self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatus());
        }
    }

    public static function invalidReferences(): iterable
    {
        yield 'invalid date' => [[
            'date' => '15/01/2030',
            'salle' => 'Salle A',
            'chirurgien' => 12,
        ]];
        yield 'invalid surgeon' => [[
            'date' => '2030-01-15',
            'salle' => 'Salle A',
            'chirurgien' => 0,
        ]];
    }
}
