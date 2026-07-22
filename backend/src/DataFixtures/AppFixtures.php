<?php

namespace App\DataFixtures;

use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\FicheTechnique;
use App\Entity\ListeMateriel;
use App\Entity\Materiel;
use App\Entity\PreparationMateriel;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser('admin@chirorg.test', ['ROLE_ADMIN']);
        $user = $this->createUser('user@chirorg.test', ['ROLE_USER']);
        $manager->persist($admin);
        $manager->persist($user);

        $chirurgiens = [
            $this->createChirurgien('Jean', 'Dupont', 'Orthopédie'),
            $this->createChirurgien('Claire', 'Martin', 'Chirurgie viscérale'),
            $this->createChirurgien('Alain', 'Bernard', 'Chirurgie générale'),
        ];
        foreach ($chirurgiens as $chirurgien) {
            $manager->persist($chirurgien);
        }

        $modeles = [];
        foreach (['Prothèse de genou', 'Appendicectomie', 'Arthroscopie épaule', 'Coelioscopie'] as $index => $intitule) {
            $modele = (new ChirurgieModele())->setIntitule($intitule);
            $fiche = (new FicheTechnique())
                ->setTitre('Préparation - '.$intitule)
                ->setDescription('Installer le patient, vérifier la salle et respecter les points de vigilance de l’intervention.')
                ->setOrdre(1)
                ->setChirurgieModele($modele);
            $modeles[] = $modele;
            $manager->persist($modele);
            $manager->persist($fiche);
        }

        $materiels = [];
        foreach ([
            ['Scalpel n°10', 'Armoire A1', 'Instrument'],
            ['Compresses stériles', 'Réserve B2', 'Consommable'],
            ['Gants stériles', 'Réserve B1', 'Consommable'],
            ['Boîte orthopédie', 'Armoire C3', 'Boîte opératoire'],
            ['Champ opératoire', 'Réserve B3', 'Consommable'],
            ['Pinces', 'Armoire A2', 'Instrument'],
            ['Aspiration', 'Salle technique', 'Équipement'],
            ['Plateau anesthésie', 'Zone anesthésie', 'Plateau'],
        ] as [$intitule, $adresse, $type]) {
            $materiel = (new Materiel())->setIntitule($intitule)->setAdresse($adresse)->setTypeMateriel($type);
            $materiels[] = $materiel;
            $manager->persist($materiel);
        }

        $couples = [
            [$chirurgiens[0], $modeles[0], 'Liste genou Dupont', [0, 1, 2, 3, 4, 5]],
            [$chirurgiens[1], $modeles[1], 'Liste appendicectomie Martin', [0, 1, 2, 4, 5, 6]],
            [$chirurgiens[2], $modeles[2], 'Liste arthroscopie Bernard', [0, 1, 2, 3, 6]],
            [$chirurgiens[1], $modeles[3], 'Liste coelioscopie Martin', [0, 1, 2, 4, 6, 7]],
        ];

        $listes = [];
        foreach ($couples as [$chirurgien, $modele, $intitule, $indices]) {
            $liste = (new ListeMateriel())->setIntitule($intitule)->setChirurgien($chirurgien)->setChirurgieModele($modele);
            foreach ($indices as $indice) {
                $liste->addMateriel($materiels[$indice]);
            }
            $listes[] = $liste;
            $manager->persist($liste);
        }

        $aujourdhui = new \DateTimeImmutable('today');
        $chirurgies = [
            $this->createChirurgie($aujourdhui->setTime(8, 0), 'Salle A', 1, $chirurgiens[0], $modeles[0]),
            $this->createChirurgie($aujourdhui->setTime(10, 30), 'Salle B', 1, $chirurgiens[1], $modeles[1]),
            $this->createChirurgie($aujourdhui->modify('+1 day')->setTime(9, 0), 'Salle C', 1, $chirurgiens[2], $modeles[2]),
            $this->createChirurgie($aujourdhui->modify('+1 day')->setTime(13, 30), 'Salle A', 2, $chirurgiens[1], $modeles[3]),
        ];

        foreach ($chirurgies as $chirurgieIndex => $chirurgie) {
            $manager->persist($chirurgie);
            foreach ($listes[$chirurgieIndex]->getMateriels() as $materielIndex => $materiel) {
                $preparation = (new PreparationMateriel())->setChirurgiePlanifiee($chirurgie)->setMateriel($materiel);
                if (0 === $chirurgieIndex || (1 === $chirurgieIndex && $materielIndex < 3)) {
                    $preparation->setCoche(true)->setCocheLe(new \DateTimeImmutable())->setCochePar($user);
                }
                $chirurgie->addPreparationMateriel($preparation);
                $manager->persist($preparation);
            }
        }

        $chirurgies[0]->setValide(true)->setValideLe(new \DateTimeImmutable())->setValidePar($user);
        $manager->flush();
    }

    private function createUser(string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setRoles($roles);
        return $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
    }

    private function createChirurgien(string $prenom, string $nom, string $specialite): Chirurgien
    {
        return (new Chirurgien())->setPrenom($prenom)->setNom($nom)->setSpecialite($specialite);
    }

    private function createChirurgie(\DateTimeImmutable $date, string $salle, int $ordre, Chirurgien $chirurgien, ChirurgieModele $modele): ChirurgiePlanifiee
    {
        return (new ChirurgiePlanifiee())
            ->setDateProgrammee($date)
            ->setSalle($salle)
            ->setOrdre($ordre)
            ->setChirurgien($chirurgien)
            ->setChirurgieModele($modele);
    }
}
