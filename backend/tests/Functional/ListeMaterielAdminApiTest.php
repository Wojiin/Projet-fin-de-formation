<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\AppFixtures;
use App\Repository\ListeMaterielRepository;
use App\Repository\MaterielRepository;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

final class ListeMaterielAdminApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public static function setUpBeforeClass(): void
    {
        $kernel = static::bootKernel();
        if ('test' !== $kernel->getEnvironment()) {
            throw new \LogicException('Les fixtures ne doivent être chargées que dans l’environnement de test.');
        }

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        (new ORMPurger($entityManager))->purge();
        $container->get(AppFixtures::class)->load($entityManager);
        $entityManager->clear();
        static::ensureKernelShutdown();
    }

    public function testAdminCanReplaceMaterialsByApiRelations(): void
    {
        $client = static::createClient();
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->login($client),
            'Content-Type' => 'application/merge-patch+json',
        ];
        $list = static::getContainer()->get(ListeMaterielRepository::class)->findOneBy([]);
        $allMaterials = static::getContainer()->get(MaterielRepository::class)->findBy([], ['id' => 'ASC']);
        $materials = array_values(array_filter(
            $allMaterials,
            static fn ($material): bool => $material->getSpecialite() === $list?->getChirurgien()?->getSpecialite(),
        ));
        $incompatibleMaterial = array_values(array_filter(
            $allMaterials,
            static fn ($material): bool => $material->getSpecialite() !== $list?->getChirurgien()?->getSpecialite(),
        ))[0] ?? null;
        self::assertNotNull($list);
        self::assertNotEmpty($materials);
        self::assertNotNull($incompatibleMaterial);

        $collection = $client->request('GET', '/api/listes-materiel', [
            'headers' => ['Accept' => 'application/json', 'Authorization' => $headers['Authorization']],
        ])->toArray();
        $listedItems = $collection['member'] ?? $collection['hydra:member'] ?? $collection;
        self::assertNotEmpty($listedItems);
        self::assertIsString($listedItems[0]['chirurgieModele']['specialite']['intitule']);

        $specialityId = $listedItems[0]['chirurgieModele']['specialite']['id'];
        $filteredCollection = $client->request(
            'GET',
            '/api/listes-materiel?specialite='.$specialityId,
            ['headers' => ['Accept' => 'application/json', 'Authorization' => $headers['Authorization']]],
        )->toArray();
        $filteredItems = $filteredCollection['member'] ?? $filteredCollection['hydra:member'] ?? $filteredCollection;
        self::assertNotEmpty($filteredItems);
        foreach ($filteredItems as $filteredItem) {
            self::assertSame($specialityId, $filteredItem['chirurgieModele']['specialite']['id']);
        }

        $surgeonId = $filteredItems[0]['chirurgien']['id'];
        $combinedCollection = $client->request(
            'GET',
            '/api/listes-materiel?specialite='.$specialityId.'&chirurgien='.$surgeonId,
            ['headers' => ['Accept' => 'application/json', 'Authorization' => $headers['Authorization']]],
        )->toArray();
        $combinedItems = $combinedCollection['member'] ?? $combinedCollection['hydra:member'] ?? $combinedCollection;
        self::assertNotEmpty($combinedItems);
        foreach ($combinedItems as $combinedItem) {
            self::assertSame($specialityId, $combinedItem['chirurgieModele']['specialite']['id']);
            self::assertSame($surgeonId, $combinedItem['chirurgien']['id']);
        }

        $client->request('PATCH', '/api/listes-materiel/'.$list->getId(), [
            'headers' => $headers,
            'json' => ['materiels' => ['/api/materiels/'.$materials[0]->getId()]],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $client->getResponse()->toArray()['materiels']);
        self::assertSame($materials[0]->getIntitule(), $client->getResponse()->toArray()['materiels'][0]['intitule']);

        $client->request('PATCH', '/api/listes-materiel/'.$list->getId(), [
            'headers' => $headers,
            'json' => ['materiels' => ['/api/materiels/'.$incompatibleMaterial->getId()]],
        ]);
        self::assertResponseStatusCodeSame(422);

        $client->request('PATCH', '/api/listes-materiel/'.$list->getId(), [
            'headers' => $headers,
            'json' => ['materiels' => []],
        ]);
        self::assertResponseStatusCodeSame(422);
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
