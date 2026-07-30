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
use App\Entity\Specialite;
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
            ->expects(self::exactly(696))
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
        self::assertCount(6, $this->entitiesOfType(Specialite::class));
        self::assertCount(5, $this->entitiesOfType(Chirurgien::class));
        self::assertCount(20, $this->entitiesOfType(ChirurgieModele::class));
        self::assertCount(60, $this->entitiesOfType(FicheTechnique::class));
        self::assertCount(100, $this->entitiesOfType(Materiel::class));
        self::assertCount(100, $this->entitiesOfType(ListeMateriel::class));
        self::assertCount(30, $this->entitiesOfType(ChirurgiePlanifiee::class));
        self::assertCount(373, $this->entitiesOfType(PreparationMateriel::class));
    }

    public function testSpecialitesRelientLesReferentielsChirurgicaux(): void
    {
        $specialites = $this->entitiesOfType(Specialite::class);
        self::assertContains('Urologie', array_map(
            static fn (Specialite $specialite): ?string => $specialite->getIntitule(),
            $specialites,
        ));
        self::assertContains(Specialite::SANS_SPECIALITE, array_map(
            static fn (Specialite $specialite): ?string => $specialite->getIntitule(),
            $specialites,
        ));

        foreach ($this->entitiesOfType(Chirurgien::class) as $chirurgien) {
            self::assertInstanceOf(Specialite::class, $chirurgien->getSpecialite());
        }
        foreach ($this->entitiesOfType(Materiel::class) as $materiel) {
            self::assertInstanceOf(Specialite::class, $materiel->getSpecialite());
        }
        foreach ($this->entitiesOfType(ChirurgieModele::class) as $modele) {
            self::assertInstanceOf(Specialite::class, $modele->getSpecialite());
        }
        foreach ($this->entitiesOfType(ChirurgiePlanifiee::class) as $chirurgie) {
            self::assertSame(
                $chirurgie->getChirurgien()?->getSpecialite(),
                $chirurgie->getChirurgieModele()?->getSpecialite(),
            );
        }
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

        self::assertCount(60, $fiches);
        foreach ($fiches as $fiche) {
            self::assertInstanceOf(ChirurgieModele::class, $fiche->getChirurgieModele());
            self::assertContains($fiche->getOrdre(), [1, 2, 3]);
            self::assertNotEmpty($fiche->getTitre());
            self::assertNotEmpty($fiche->getDescription());
        }
    }

    public function testListesMaterielRelientChirurgienModeleEtMateriels(): void
    {
        $listes = $this->entitiesOfType(ListeMateriel::class);

        self::assertCount(100, $listes);
        foreach ($listes as $liste) {
            self::assertGreaterThanOrEqual(10, $liste->getMateriels()->count());
            self::assertLessThanOrEqual(15, $liste->getMateriels()->count());
            self::assertInstanceOf(Chirurgien::class, $liste->getChirurgien());
            self::assertInstanceOf(ChirurgieModele::class, $liste->getChirurgieModele());
            self::assertNotEmpty($liste->getIntitule());
        }
    }

    public function testPlanningEtPreparationsRespectentLeScenarioInitial(): void
    {
        $chirurgies = $this->entitiesOfType(ChirurgiePlanifiee::class);
        $listes = $this->entitiesOfType(ListeMateriel::class);
        $users = $this->entitiesOfType(User::class);
        $simpleUser = array_values(array_filter(
            $users,
            static fn (User $user): bool => 'user@chirorg.test' === $user->getEmail(),
        ))[0];

        self::assertCount(30, $chirurgies);
        self::assertSame(['Salle A', 'Salle A', 'Salle B', 'Salle B'], array_slice(array_map(
            static fn (ChirurgiePlanifiee $chirurgie): ?string => $chirurgie->getSalle(),
            $chirurgies,
        ), 0, 4));
        self::assertSame([10, 11, 12, 13, 14], array_slice(array_map(
            static fn (ListeMateriel $liste): int => $liste->getMateriels()->count(),
            $listes,
        ), 0, 5));
        self::assertSame([10, 11, 11, 12, 14], array_slice(array_map(
            static fn (ChirurgiePlanifiee $chirurgie): int => $chirurgie->getPreparationsMateriel()->count(),
            $chirurgies,
        ), 0, 5));

        self::assertTrue($chirurgies[0]->isValide());
        self::assertSame($simpleUser, $chirurgies[0]->getValidePar());
        self::assertNotNull($chirurgies[0]->getValideLe());
        self::assertTrue($chirurgies[0]->getPreparationsMateriel()->forAll(
            static fn (int $index, PreparationMateriel $preparation): bool => $preparation->isCoche()
                && null !== $preparation->getCocheLe()
                && $simpleUser === $preparation->getCochePar(),
        ));

        self::assertTrue($chirurgies[1]->isValide());
        self::assertTrue($chirurgies[2]->getPreparationsMateriel()->forAll(
            static fn (int $index, PreparationMateriel $preparation): bool => $preparation->isCoche()
                && null !== $preparation->getCocheLe()
                && $simpleUser === $preparation->getCochePar(),
        ));

        $sixiemePreparation = $chirurgies[5]->getPreparationsMateriel();
        self::assertSame($sixiemePreparation->count(), $sixiemePreparation->filter(
            static fn (PreparationMateriel $preparation): bool => $preparation->isCoche(),
        )->count());
        self::assertTrue($chirurgies[5]->isValide());
        self::assertFalse($chirurgies[6]->isValide());
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
