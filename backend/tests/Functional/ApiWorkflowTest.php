<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\AppFixtures;
use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\Materiel;
use App\Entity\Specialite;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Repository\ListeMaterielRepository;
use App\Repository\SpecialiteRepository;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

final class ApiWorkflowTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public static function setUpBeforeClass(): void
    {
        $kernel = static::bootKernel();
        if ('test' !== $kernel->getEnvironment()) {
            throw new \LogicException('Les fixtures de test ne doivent être chargées que dans l’environnement test.');
        }

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        (new ORMPurger($entityManager))->purge();
        $container->get(AppFixtures::class)->load($entityManager);
        $entityManager->clear();

        static::ensureKernelShutdown();
    }

    public function testAuthenticationAndProtectedRoutes(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', ['json' => ['email' => 'user@chirorg.test', 'password' => 'password']]);
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $client->getResponse()->toArray());
        $cookies = $client->getResponse()->getHeaders(false)['set-cookie'] ?? [];
        self::assertStringContainsString('refresh_token=', implode('; ', $cookies));

        $client->request('POST', '/api/token/refresh');
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $client->getResponse()->toArray());

        $client->request('POST', '/api/logout');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('refresh_token=deleted', implode('; ', $client->getResponse()->getHeaders(false)['set-cookie'] ?? []));

        $client->request('POST', '/api/login', ['json' => ['email' => 'user@chirorg.test', 'password' => 'incorrect']]);
        self::assertResponseStatusCodeSame(401);

        static::createClient()->request('GET', '/api/programmes-operatoires', ['headers' => ['Accept' => 'application/json']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testMeAndAdminAuthorization(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $client->request('GET', '/api/me', ['headers' => $this->headers($token)]);
        self::assertResponseIsSuccessful();
        $me = $client->getResponse()->toArray();
        self::assertSame('user@chirorg.test', $me['email']);
        self::assertArrayNotHasKey('password', $me);

        $client->request('GET', '/api/specialites', ['headers' => $this->headers($token)]);
        self::assertResponseIsSuccessful();
        self::assertContains('Urologie', array_column($client->getResponse()->toArray(), 'intitule'));

        $client->request('GET', '/api/users', ['headers' => $this->headers($token)]);
        self::assertResponseStatusCodeSame(403);

        $adminToken = $this->login($client, 'admin@chirorg.test');
        $client->request('GET', '/api/users', ['headers' => $this->headers($adminToken)]);
        self::assertResponseIsSuccessful();
    }

    public function testProgrammesSontRegroupesEtLeDetailSuitLeWorkflow(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $headers = $this->headers($token);

        $client->request('GET', '/api/programmes-operatoires', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $programme = $client->getResponse()->toArray();
        self::assertIsList($programme);
        self::assertNotEmpty($programme);
        self::assertArrayHasKey('progressionPreparation', $programme[0]);
        self::assertArrayHasKey('nombreChirurgies', $programme[0]);
        self::assertSame('admin@chirorg.test', $programme[0]['creePar']);
        self::assertNotEmpty(array_filter(
            $programme,
            static fn (array $item): bool => $item['nombreChirurgies'] >= 2,
        ));

        $date = $programme[0]['date'];
        $client->request('GET', '/api/programmes-operatoires?date='.$date, ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        foreach ($client->getResponse()->toArray() as $item) {
            self::assertSame($date, $item['date']);
        }

        $client->request('GET', '/api/programmes-operatoires?salle=Salle%20A', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        foreach ($client->getResponse()->toArray() as $item) {
            self::assertSame('Salle A', $item['salle']);
        }

        $resume = $programme[0];
        $detailUrl = sprintf(
            '/api/programmes-operatoires/%s/%s/%d',
            $resume['date'],
            rawurlencode($resume['salle']),
            $resume['chirurgien']['id'],
        );
        $client->request('GET', $detailUrl, ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $detail = $client->getResponse()->toArray();
        self::assertSame($resume['date'], $detail['date']);
        self::assertSame($resume['salle'], $detail['salle']);
        self::assertCount($resume['nombreChirurgies'], $detail['chirurgies']);
        self::assertNotEmpty($detail['chirurgies'][0]['preparationsMateriel']);

        $programmesValides = array_values(array_filter(
            $programme,
            static fn (array $item): bool => 0 < $item['nombreChirurgiesValidees'],
        ));
        self::assertNotEmpty($programmesValides, 'Les fixtures doivent contenir au moins un programme validé.');
        $programmeValide = $programmesValides[0];
        $vueFinaleUrl = sprintf(
            '/api/programmes-operatoires/%s/%s/%d/vue-finale',
            $programmeValide['date'],
            rawurlencode($programmeValide['salle']),
            $programmeValide['chirurgien']['id'],
        );

        $client->request('GET', $vueFinaleUrl, ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $vueFinale = $client->getResponse()->toArray();
        self::assertNotEmpty($vueFinale['chirurgies']);
        foreach ($vueFinale['chirurgies'] as $chirurgie) {
            self::assertTrue($chirurgie['valide']);
            self::assertNotEmpty($chirurgie['fichesTechniques']);
        }

        $client->request('GET', '/api/programmes-operatoires?date=20-07-2026', ['headers' => $headers]);
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', '/api/programmes-operatoires?inconnu=valeur', ['headers' => $headers]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testPlanificationGroupeeEtReordonnancementDuProgramme(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $headers = $this->headers($token);
        $listes = static::getContainer()->get(ListeMaterielRepository::class)->findAll();
        self::assertNotEmpty($listes);

        $premiereListe = $listes[0];
        $listesDuChirurgien = array_values(array_filter(
            $listes,
            static fn ($liste): bool => $liste->getChirurgien()?->getId() === $premiereListe->getChirurgien()?->getId(),
        ));
        self::assertGreaterThanOrEqual(2, count($listesDuChirurgien));

        $chirurgienId = $premiereListe->getChirurgien()?->getId();
        $modeleIds = [
            $listesDuChirurgien[0]->getChirurgieModele()?->getId(),
            $listesDuChirurgien[1]->getChirurgieModele()?->getId(),
        ];
        self::assertIsInt($chirurgienId);
        self::assertNotContains(null, $modeleIds);

        $client->request('POST', '/api/programmes-operatoires', [
            'headers' => $headers + ['Content-Type' => 'application/json'],
            'json' => [
                'dateProgrammee' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'salle' => 'Salle Test Programme',
                'chirurgienId' => $chirurgienId,
                'chirurgieModeleIds' => $modeleIds,
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $programmeDate = new \DateTimeImmutable('+2 days');
        $client->request('POST', '/api/programmes-operatoires', [
            'headers' => $headers + ['Content-Type' => 'application/json'],
            'json' => [
                'dateProgrammee' => $programmeDate->format('Y-m-d'),
                'salle' => 'Salle Test Programme',
                'chirurgienId' => $chirurgienId,
                'chirurgieModeleIds' => $modeleIds,
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $programme = $client->getResponse()->toArray();
        self::assertCount(2, $programme['chirurgies']);
        self::assertSame([1, 2], array_column($programme['chirurgies'], 'ordre'));
        foreach ($programme['chirurgies'] as $chirurgie) {
            self::assertSame($programmeDate->format('Y-m-d'), $chirurgie['dateProgrammee']);
            self::assertStringNotContainsString('T', $chirurgie['dateProgrammee']);
            self::assertArrayNotHasKey('heure', $chirurgie);
            self::assertSame('user@chirorg.test', $chirurgie['creePar']);
            self::assertSame('user@chirorg.test', $chirurgie['modifiePar']);
            self::assertNotEmpty($chirurgie['creeLe']);
            self::assertNotEmpty($chirurgie['modifieLe']);
        }

        $client->request('GET', '/api/programmes-operatoires', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        self::assertContains($programmeDate->format('Y-m-d'), array_column($client->getResponse()->toArray(), 'date'));

        $idsInverses = array_reverse(array_column($programme['chirurgies'], 'id'));
        $client->request(
            'PATCH',
            sprintf('/api/programmes-operatoires/%s/%s/%d/ordre', $programmeDate->format('Y-m-d'), rawurlencode('Salle Test Programme'), $chirurgienId),
            [
                'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
                'json' => ['chirurgieIds' => $idsInverses],
            ],
        );
        self::assertResponseIsSuccessful();
        $programmeReordonne = $client->getResponse()->toArray();
        self::assertSame($idsInverses, array_column($programmeReordonne['chirurgies'], 'id'));
        self::assertSame([1, 2], array_column($programmeReordonne['chirurgies'], 'ordre'));
        foreach ($programmeReordonne['chirurgies'] as $chirurgie) {
            self::assertSame('user@chirorg.test', $chirurgie['modifiePar']);
            self::assertNotEmpty($chirurgie['modifieLe']);
        }

        $client->request('DELETE', '/api/chirurgies-planifiees/'.$idsInverses[0], ['headers' => $headers]);
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->chirurgieRepository()->find($idsInverses[0]));

        $chirurgieValidee = $this->chirurgieRepository()->findOneBy(['valide' => true]);
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgieValidee);
        $client->request('DELETE', '/api/chirurgies-planifiees/'.$chirurgieValidee->getId(), ['headers' => $headers]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('RESOURCE_ALREADY_USED', $client->getResponse()->toArray(false)['errorCode']);
    }

    public function testPreparationAndValidationWorkflow(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $headers = $this->headers($token);
        $chirurgie = $this->chirurgieRepository()->findOneBy(['salle' => 'Salle C', 'valide' => false]);
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgie);
        $this->resetPreparation($chirurgie);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/preparation', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $preparation = $client->getResponse()->toArray();
        self::assertArrayNotHasKey('ficheTechnique', $preparation);
        self::assertNotEmpty($preparation['preparationsMateriel']);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/preparations-materiel', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        self::assertCount(count($preparation['preparationsMateriel']), $client->getResponse()->toArray());

        $client->request('POST', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/validation', ['headers' => $headers]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('MATERIEL_PREPARATION_INCOMPLETE', $client->getResponse()->toArray(false)['errorCode']);

        $client->request('PATCH', '/api/preparations-materiel/'.$preparation['preparationsMateriel'][0]['id'].'/cocher', [
            'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
            'json' => [],
        ]);
        self::assertResponseStatusCodeSame(422);

        foreach ($preparation['preparationsMateriel'] as $item) {
            $client->request('PATCH', '/api/preparations-materiel/'.$item['id'].'/cocher', [
                'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
                'body' => json_encode(['coche' => true], JSON_THROW_ON_ERROR),
            ]);
            self::assertResponseIsSuccessful();
            self::assertTrue($client->getResponse()->toArray()['coche']);
        }

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $chirurgieModifiee = $this->chirurgieRepository()->find($chirurgie->getId());
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgieModifiee);
        self::assertSame('user@chirorg.test', $chirurgieModifiee->getModifiePar());
        self::assertNotNull($chirurgieModifiee->getModifieLe());

        $client->request('POST', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/validation', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $chirurgieValidee = $client->getResponse()->toArray();
        self::assertTrue($chirurgieValidee['valide']);
        self::assertSame('user@chirorg.test', $chirurgieValidee['modifiePar']);
        self::assertNotEmpty($chirurgieValidee['modifieLe']);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/vue-finale', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $vueFinale = $client->getResponse()->toArray();
        self::assertNotEmpty($vueFinale['ficheTechnique']);
        self::assertNotEmpty($vueFinale['materielsValides']);

        $firstPreparationId = $preparation['preparationsMateriel'][0]['id'];
        $client->request('PATCH', '/api/preparations-materiel/'.$firstPreparationId.'/cocher', [
            'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode(['coche' => false], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testViewFinaleIsRefusedBeforeValidation(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $chirurgie = $this->chirurgieRepository()->findOneBy(['salle' => 'Salle B', 'valide' => false]);
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgie);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/vue-finale', ['headers' => $this->headers($token)]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testAbsentMaterialCreatesPartialValidationThenCanBeResolved(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $headers = $this->headers($token);
        $chirurgie = $this->chirurgieRepository()->findOneBy(['salle' => 'Salle C', 'valide' => false]);
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgie);
        $this->resetPreparation($chirurgie);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/preparation', ['headers' => $headers]);
        $items = $client->getResponse()->toArray()['preparationsMateriel'];

        foreach ($items as $index => $item) {
            $client->request('PATCH', '/api/preparations-materiel/'.$item['id'].'/cocher', [
                'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
                'json' => ['coche' => 0 !== $index, 'absent' => 0 === $index],
            ]);
            self::assertResponseIsSuccessful();
        }

        $client->request('POST', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/validation', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->toArray()['valide']);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/preparation', ['headers' => $headers]);
        $partial = $client->getResponse()->toArray();
        self::assertSame('VALIDATION_PARTIELLE', $partial['etatValidation']);
        self::assertSame(1, $partial['progressionPreparation']['absents']);

        $client->request('PATCH', '/api/preparations-materiel/'.$items[0]['id'].'/cocher', [
            'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['coche' => true, 'absent' => false],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/validation', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->toArray()['valide']);
    }

    public function testSuppressionSpecialiteReaffecteToutesLesReferences(): void
    {
        $client = static::createClient();
        $adminToken = $this->login($client, 'admin@chirorg.test');
        $headers = $this->headers($adminToken);
        $repository = static::getContainer()->get(SpecialiteRepository::class);

        $specialite = $repository->findOneBy(['intitule' => 'Orthopédie']);
        $specialiteParDefaut = $repository->findDefault();
        self::assertInstanceOf(Specialite::class, $specialite);
        self::assertInstanceOf(Specialite::class, $specialiteParDefaut);

        $chirurgienId = $specialite->getChirurgiens()->first()?->getId();
        $materielId = $specialite->getMateriels()->first()?->getId();
        $modeleId = $specialite->getChirurgiesModeles()->first()?->getId();
        self::assertIsInt($chirurgienId);
        self::assertIsInt($materielId);
        self::assertIsInt($modeleId);

        $client->request('DELETE', '/api/specialites/'.$specialite->getId(), ['headers' => $headers]);
        self::assertResponseStatusCodeSame(204);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $specialiteParDefautId = $specialiteParDefaut->getId();
        self::assertSame($specialiteParDefautId, $entityManager->find(Chirurgien::class, $chirurgienId)?->getSpecialite()?->getId());
        self::assertSame($specialiteParDefautId, $entityManager->find(Materiel::class, $materielId)?->getSpecialite()?->getId());
        self::assertSame($specialiteParDefautId, $entityManager->find(ChirurgieModele::class, $modeleId)?->getSpecialite()?->getId());

        $client->request('DELETE', '/api/specialites/'.$specialiteParDefautId, ['headers' => $headers]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('DEFAULT_SPECIALITE_PROTECTED', $client->getResponse()->toArray(false)['errorCode']);
    }

    private function login(Client $client, string $email): string
    {
        $response = $client->request('POST', '/api/login', ['json' => ['email' => $email, 'password' => 'password']]);
        self::assertResponseIsSuccessful();

        return $response->toArray()['token'];
    }

    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    private function chirurgieRepository(): ChirurgiePlanifieeRepository
    {
        return static::getContainer()->get(ChirurgiePlanifieeRepository::class);
    }

    private function resetPreparation(ChirurgiePlanifiee $chirurgie): void
    {
        $chirurgie->setValide(false)->setValideLe(null)->setValidePar(null);
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            $preparation->setCoche(false)->setAbsent(false)->setCocheLe(null)->setCochePar(null);
        }

        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
