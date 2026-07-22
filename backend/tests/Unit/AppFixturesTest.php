<?php

namespace App\Tests\Unit;

use App\DataFixtures\AppFixtures;
use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\FicheTechnique;
use App\Entity\ListeMateriel;
use App\Entity\Materiel;
use App\Entity\PreparationMateriel;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixturesTest extends TestCase
{
    /** @var list<object> */
    private array $entities;

    protected function setUp(): void
    {
        $this->entities = [];

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects(self::exactly(2))
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), 'password')
            ->willReturn('mot-de-passe-hache');

        $manager = $this->createMock(ObjectManager::class);
        $manager
            ->expects(self::exactly(52))
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->entities[] = $entity;
            });
        $manager->expects(self::once())->method('flush');

        (new AppFixtures($passwordHasher))->load($manager);
    }

    public function testFixturesCreentLeBonNombreDentites(): void
    {
        self::assertCount(2, $this->entitiesOfType(User::class));
        self::assertCount(3, $this->entitiesOfType(Chirurgien::class));
        self::assertCount(4, $this->entitiesOfType(ChirurgieModele::class));
        self::assertCount(4, $this->entitiesOfType(FicheTechnique::class));
        self::assertCount(8, $this->entitiesOfType(Materiel::class));
        self::assertCount(4, $this->entitiesOfType(ListeMateriel::class));
        self::assertCount(4, $this->entitiesOfType(ChirurgiePlanifiee::class));
        self::assertCount(23, $this->entitiesOfType(PreparationMateriel::class));
    }

    public function testFixturesCreentLesComptesUtilisateurEtAdministrateur(): void
    {
        $usersByEmail = [];
        foreach ($this->entitiesOfType(User::class) as $user) {
            $usersByEmail[$user->getEmail()] = $user;
        }

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], array_keys([
            $usersByEmail['admin@chirorg.test']->getRoles()[0] => true,
            $usersByEmail['user@chirorg.test']->getRoles()[0] => true,
        ]));
        self::assertSame('mot-de-passe-hache', $usersByEmail['admin@chirorg.test']->getPassword());
        self::assertSame('mot-de-passe-hache', $usersByEmail['user@chirorg.test']->getPassword());
    }

    public function testChaqueModelePossedeSaFicheTechnique(): void
    {
        $fiches = $this->entitiesOfType(FicheTechnique::class);

        self::assertCount(4, $fiches);
        foreach ($fiches as $fiche) {
            self::assertInstanceOf(ChirurgieModele::class, $fiche->getChirurgieModele());
            self::assertSame(1, $fiche->getOrdre());
            self::assertNotEmpty($fiche->getTitre());
            self::assertNotEmpty($fiche->getDescription());
        }
    }

    public function testListesMaterielRelientChirurgienModeleEtMateriels(): void
    {
        $listes = $this->entitiesOfType(ListeMateriel::class);

        self::assertSame([6, 6, 5, 6], array_map(
            static fn (ListeMateriel $liste): int => $liste->getMateriels()->count(),
            $listes,
        ));

        foreach ($listes as $liste) {
            self::assertInstanceOf(Chirurgien::class, $liste->getChirurgien());
            self::assertInstanceOf(ChirurgieModele::class, $liste->getChirurgieModele());
            self::assertNotEmpty($liste->getIntitule());
        }
    }

    public function testPlanningEtPreparationsRespectentLeScenarioInitial(): void
    {
        $chirurgies = $this->entitiesOfType(ChirurgiePlanifiee::class);
        $users = $this->entitiesOfType(User::class);
        $simpleUser = array_values(array_filter(
            $users,
            static fn (User $user): bool => 'user@chirorg.test' === $user->getEmail(),
        ))[0];

        self::assertSame(['Salle A', 'Salle B', 'Salle C', 'Salle A'], array_map(
            static fn (ChirurgiePlanifiee $chirurgie): ?string => $chirurgie->getSalle(),
            $chirurgies,
        ));
        self::assertSame([6, 6, 5, 6], array_map(
            static fn (ChirurgiePlanifiee $chirurgie): int => $chirurgie->getPreparationsMateriel()->count(),
            $chirurgies,
        ));

        self::assertTrue($chirurgies[0]->isValide());
        self::assertSame($simpleUser, $chirurgies[0]->getValidePar());
        self::assertNotNull($chirurgies[0]->getValideLe());
        self::assertTrue($chirurgies[0]->getPreparationsMateriel()->forAll(
            static fn (int $index, PreparationMateriel $preparation): bool => $preparation->isCoche()
                && null !== $preparation->getCocheLe()
                && $simpleUser === $preparation->getCochePar(),
        ));

        $deuxiemePreparation = $chirurgies[1]->getPreparationsMateriel();
        self::assertSame(3, $deuxiemePreparation->filter(
            static fn (PreparationMateriel $preparation): bool => $preparation->isCoche(),
        )->count());
        self::assertFalse($chirurgies[1]->isValide());
        self::assertTrue($chirurgies[2]->getPreparationsMateriel()->forAll(
            static fn (int $index, PreparationMateriel $preparation): bool => !$preparation->isCoche(),
        ));
        self::assertTrue($chirurgies[3]->getPreparationsMateriel()->forAll(
            static fn (int $index, PreparationMateriel $preparation): bool => !$preparation->isCoche(),
        ));
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return list<T>
     */
    private function entitiesOfType(string $className): array
    {
        return array_values(array_filter(
            $this->entities,
            static fn (object $entity): bool => $entity instanceof $className,
        ));
    }
}
