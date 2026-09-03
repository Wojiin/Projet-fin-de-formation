<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordChangeApiTest extends ApiTestCase
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

    public function testAuthenticatedUserCanChangeOwnPassword(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/me/password', ['json' => []]);
        self::assertResponseStatusCodeSame(401);

        $token = $this->login($client, 'password');
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
        $validPassword = 'NouveauMotDePasse1!';

        $client->request('POST', '/api/me/password', [
            'headers' => $headers,
            'json' => [
                'currentPassword' => 'incorrect',
                'newPassword' => $validPassword,
                'newPasswordConfirmation' => $validPassword,
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('CURRENT_PASSWORD_INVALID', $client->getResponse()->toArray(false)['errorCode']);

        $client->request('POST', '/api/me/password', [
            'headers' => $headers,
            'json' => [
                'currentPassword' => 'password',
                'newPassword' => 'trop-faible',
                'newPasswordConfirmation' => 'trop-faible',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/api/me/password', [
            'headers' => $headers,
            'json' => [
                'currentPassword' => 'password',
                'newPassword' => $validPassword,
                'newPasswordConfirmation' => 'AutreMotDePasse2!',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/api/me/password', [
            'headers' => $headers,
            'json' => [
                'currentPassword' => 'password',
                'newPassword' => $validPassword,
                'newPasswordConfirmation' => $validPassword,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        $client->request('POST', '/api/me/password', [
            'headers' => $headers,
            'json' => [
                'currentPassword' => $validPassword,
                'newPassword' => $validPassword,
                'newPasswordConfirmation' => $validPassword,
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('PASSWORD_UNCHANGED', $client->getResponse()->toArray(false)['errorCode']);

        $client->request('POST', '/api/login', ['json' => [
            'email' => 'user@chirorg.test',
            'password' => 'password',
        ]]);
        self::assertResponseStatusCodeSame(401);
        $this->login($client, $validPassword);

        $this->restoreFixturePassword();
    }

    private function login(Client $client, string $password): string
    {
        $response = $client->request('POST', '/api/login', ['json' => [
            'email' => 'user@chirorg.test',
            'password' => $password,
        ]]);
        self::assertResponseIsSuccessful();

        return $response->toArray()['token'];
    }

    private function restoreFixturePassword(): void
    {
        $container = static::getContainer();
        $user = $container->get(UserRepository::class)->findOneBy(['email' => 'user@chirorg.test']);
        self::assertInstanceOf(User::class, $user);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $container->get(EntityManagerInterface::class)->flush();
    }
}
