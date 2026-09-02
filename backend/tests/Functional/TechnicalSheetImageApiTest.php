<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\AppFixtures;
use App\Repository\ChirurgieModeleRepository;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TechnicalSheetImageApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public static function setUpBeforeClass(): void
    {
        $kernel = static::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        (new ORMPurger($entityManager))->purge();
        $container->get(AppFixtures::class)->load($entityManager);
        $entityManager->clear();
        static::ensureKernelShutdown();
    }

    public function testTechnicalSheetAcceptsTextImageOrBoth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/fiche-technique-images', ['extra' => ['files' => []]]);
        self::assertResponseStatusCodeSame(401);

        $token = $this->login($client);
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
        $temporaryImage = tempnam(sys_get_temp_dir(), 'chirorg-image-');
        self::assertIsString($temporaryImage);
        file_put_contents($temporaryImage, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        $response = $client->request('POST', '/api/fiche-technique-images', [
            'headers' => $headers,
            'extra' => ['files' => [
                'image' => new UploadedFile($temporaryImage, 'consigne.png', 'image/png', null, true),
            ]],
        ]);
        self::assertResponseStatusCodeSame(201);
        $imageUrl = $response->toArray()['url'];
        self::assertMatchesRegularExpression('#^/uploads/fiches-techniques/[a-f0-9]{32}\.png$#', $imageUrl);
        self::assertFileExists(static::getContainer()->getParameter('kernel.project_dir').'/public'.$imageUrl);

        $modele = static::getContainer()->get(ChirurgieModeleRepository::class)->findOneBy([]);
        self::assertNotNull($modele);
        $basePayload = [
            'titre' => 'Consigne illustrée',
            'ordre' => 99,
            'chirurgieModele' => '/api/chirurgie-modeles/'.$modele->getId(),
        ];

        $client->request('POST', '/api/fiches-techniques', [
            'headers' => $headers,
            'json' => $basePayload,
        ]);
        self::assertResponseStatusCodeSame(422);

        $imageOnly = $client->request('POST', '/api/fiches-techniques', [
            'headers' => $headers,
            'json' => $basePayload + ['lienImage' => $imageUrl],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertNull($imageOnly->toArray()['description']);
        self::assertSame($imageUrl, $imageOnly->toArray()['lienImage']);

        $textOnly = $client->request('POST', '/api/fiches-techniques', [
            'headers' => $headers,
            'json' => $basePayload + ['titre' => 'Consigne écrite', 'ordre' => 100, 'description' => 'Préparer le patient.'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $textOnlyData = $textOnly->toArray();
        self::assertSame('Préparer le patient.', $textOnlyData['description']);

        $collection = $client->request('GET', '/api/fiches-techniques', ['headers' => $headers])->toArray();
        $members = $collection['member'] ?? $collection['hydra:member'] ?? $collection;
        $listedSheet = array_values(array_filter(
            $members,
            static fn (array $sheet): bool => $textOnlyData['id'] === ($sheet['id'] ?? null),
        ))[0] ?? null;
        self::assertNotNull($listedSheet);
        self::assertSame($modele->getIntitule(), $listedSheet['chirurgieModele']['intitule']);
        self::assertSame(
            $modele->getSpecialite()?->getIntitule(),
            $listedSheet['chirurgieModele']['specialite']['intitule'],
        );

        @unlink(static::getContainer()->getParameter('kernel.project_dir').'/public'.$imageUrl);
    }

    private function login(Client $client): string
    {
        $response = $client->request('POST', '/api/login', ['json' => [
            'email' => 'admin@chirorg.test',
            'password' => 'password',
        ]]);
        self::assertResponseIsSuccessful();

        return $response->toArray()['token'];
    }
}
