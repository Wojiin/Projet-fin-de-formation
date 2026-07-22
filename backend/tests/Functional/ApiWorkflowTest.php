<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\ChirurgiePlanifiee;
use App\Repository\ChirurgiePlanifieeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ApiWorkflowTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testAuthenticationAndProtectedRoutes(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', ['json' => ['email' => 'user@chirorg.test', 'password' => 'password']]);
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $client->getResponse()->toArray());
        $cookies = $client->getResponse()->getHeaders(false)['set-cookie'] ?? [];
        self::assertStringContainsString('refresh_token=', implode('; ', $cookies));

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

        $client->request('GET', '/api/users', ['headers' => $this->headers($token)]);
        self::assertResponseStatusCodeSame(403);

        $adminToken = $this->login($client, 'admin@chirorg.test');
        $client->request('GET', '/api/users', ['headers' => $this->headers($adminToken)]);
        self::assertResponseIsSuccessful();
    }

    public function testProgrammeIsFlatAndCanBeFiltered(): void
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
        self::assertArrayNotHasKey('salles', $programme[0]);

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

        $client->request('GET', '/api/programmes-operatoires?date=20-07-2026', ['headers' => $headers]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testPreparationAndValidationWorkflow(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user@chirorg.test');
        $headers = $this->headers($token);
        $chirurgie = $this->chirurgieRepository()->findOneBy(['salle' => 'Salle C']);
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgie);
        $this->resetPreparation($chirurgie);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/preparation', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        $preparation = $client->getResponse()->toArray();
        self::assertArrayNotHasKey('ficheTechnique', $preparation);
        self::assertNotEmpty($preparation['preparationsMateriel']);

        $client->request('POST', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/validation', ['headers' => $headers]);
        self::assertResponseStatusCodeSame(409);

        foreach ($preparation['preparationsMateriel'] as $item) {
            $client->request('PATCH', '/api/preparations-materiel/'.$item['id'].'/cocher', [
                'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
                'body' => json_encode(['coche' => true], JSON_THROW_ON_ERROR),
            ]);
            self::assertResponseIsSuccessful();
            self::assertTrue($client->getResponse()->toArray()['coche']);
        }

        $client->request('POST', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/validation', ['headers' => $headers]);
        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->toArray()['valide']);

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
        $chirurgie = $this->chirurgieRepository()->findOneBy(['salle' => 'Salle B']);
        self::assertInstanceOf(ChirurgiePlanifiee::class, $chirurgie);

        $client->request('GET', '/api/chirurgies-planifiees/'.$chirurgie->getId().'/vue-finale', ['headers' => $this->headers($token)]);
        self::assertResponseStatusCodeSame(409);
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
            $preparation->setCoche(false)->setCocheLe(null)->setCochePar(null);
        }

        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
