<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackendArchitectureTest extends TestCase
{
    public function testStateAdaptersDoNotOwnSecurityOrReadModelProjection(): void
    {
        foreach ($this->phpFiles('src/State') as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString(
                'Symfony\\Bundle\\SecurityBundle\\Security',
                $source,
                basename($path).' doit déléguer la résolution de l’utilisateur courant.',
            );
            self::assertStringNotContainsString(
                "'progressionPreparation' =>",
                $source,
                basename($path).' ne doit pas construire de projection métier.',
            );
            self::assertStringNotContainsString(
                "'typeMateriel' =>",
                $source,
                basename($path).' ne doit pas mapper le matériel.',
            );
        }
    }

    public function testControllersDoNotOwnFilesystemOperations(): void
    {
        foreach ($this->phpFiles('src/Controller') as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);

            foreach (['Symfony\\Component\\Filesystem', 'new \\finfo', 'random_bytes(', '->move('] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source,
                    basename($path).' doit déléguer le stockage à un service.',
                );
            }
        }
    }

    public function testServicesStayIndependentFromApiPlatformAdapters(): void
    {
        foreach ($this->phpFiles('src/Service') as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('ApiPlatform\\Metadata', $source, basename($path));
            self::assertStringNotContainsString('ApiPlatform\\State', $source, basename($path));
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $relativeDirectory): array
    {
        $paths = glob(dirname(__DIR__, 2).'/'.$relativeDirectory.'/*.php');
        self::assertIsArray($paths);
        sort($paths);

        return $paths;
    }
}
