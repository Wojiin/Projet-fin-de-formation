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
            $this->createChirurgien('Jean', 'Dupont', 'Orthopedie'),
            $this->createChirurgien('Claire', 'Martin', 'Chirurgie viscerale'),
            $this->createChirurgien('Alain', 'Bernard', 'Chirurgie generale'),
            $this->createChirurgien('Nadia', 'Petit', 'Traumatologie'),
            $this->createChirurgien('Hugo', 'Leroy', 'Chirurgie ambulatoire'),
        ];
        foreach ($chirurgiens as $chirurgien) {
            $manager->persist($chirurgien);
        }

        $modeles = [];
        foreach ($this->getChirurgieModeleNames() as $intitule) {
            $modele = (new ChirurgieModele())->setIntitule($intitule);
            $modeles[] = $modele;
            $manager->persist($modele);

            foreach ($this->createFichesTechniques($modele, $intitule) as $fiche) {
                $manager->persist($fiche);
            }
        }

        $materiels = [];
        foreach ($this->createMaterielsData() as [$intitule, $adresse, $type]) {
            $materiel = (new Materiel())->setIntitule($intitule)->setAdresse($adresse)->setTypeMateriel($type);
            $materiels[] = $materiel;
            $manager->persist($materiel);
        }

        $listes = [];
        foreach ($chirurgiens as $chirurgienIndex => $chirurgien) {
            foreach ($modeles as $modeleIndex => $modele) {
                $liste = (new ListeMateriel())
                    ->setIntitule(sprintf('Liste %s %s - %s', $chirurgien->getNom(), $chirurgien->getPrenom(), $modele->getIntitule()))
                    ->setChirurgien($chirurgien)
                    ->setChirurgieModele($modele);

                foreach ($this->pickMaterielsForList($materiels, $chirurgienIndex, $modeleIndex) as $materiel) {
                    $liste->addMateriel($materiel);
                }

                $listes[$chirurgienIndex.'-'.$modeleIndex] = $liste;
                $manager->persist($liste);
            }
        }

        $aujourdhui = new \DateTimeImmutable('today');
        $salles = ['Salle A', 'Salle B', 'Salle C'];
        $chirurgies = [];

        for ($chirurgieIndex = 0; $chirurgieIndex < 30; ++$chirurgieIndex) {
            $groupeIndex = intdiv($chirurgieIndex, 2);
            $chirurgienIndex = $groupeIndex % count($chirurgiens);
            $modeleIndex = $chirurgieIndex % count($modeles);
            $date = $aujourdhui
                ->modify(sprintf('+%d day', intdiv($chirurgieIndex, 6)))
                ->setTime(8 + (($chirurgieIndex % 2) * 2), 0);

            $chirurgie = $this->createChirurgie(
                $date,
                $salles[$groupeIndex % count($salles)],
                ($chirurgieIndex % 2) + 1,
                $chirurgiens[$chirurgienIndex],
                $modeles[$modeleIndex],
            );

            $chirurgies[] = $chirurgie;
            $manager->persist($chirurgie);

            $liste = $listes[$chirurgienIndex.'-'.$modeleIndex];
            foreach ($liste->getMateriels() as $materielIndex => $materiel) {
                $preparation = (new PreparationMateriel())->setChirurgiePlanifiee($chirurgie)->setMateriel($materiel);
                if (0 === $chirurgieIndex || (0 === $chirurgieIndex % 5 && $materielIndex < 4)) {
                    $preparation->setCoche(true)->setCocheLe(new \DateTimeImmutable())->setCochePar($user);
                }
                $chirurgie->addPreparationMateriel($preparation);
                $manager->persist($preparation);
            }
        }

        foreach (array_slice($chirurgies, 0, 6) as $chirurgie) {
            foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
                $preparation->setCoche(true)->setCocheLe(new \DateTimeImmutable())->setCochePar($user);
            }
            $chirurgie->setValide(true)->setValideLe(new \DateTimeImmutable())->setValidePar($user);
        }

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

    /**
     * @return list<string>
     */
    private function getChirurgieModeleNames(): array
    {
        return [
            'Prothese totale de genou',
            'Prothese totale de hanche',
            'Appendicectomie',
            'Cholecystectomie coelioscopique',
            'Arthroscopie epaule',
            'Arthroscopie genou',
            'Canal carpien',
            'Hernie inguinale',
            'Coelioscopie exploratrice',
            'Ligamentoplastie croise anterieur',
            'Osteosynthese cheville',
            'Osteosynthese poignet',
            'Ablation materiel orthopedique',
            'Cure eventration',
            'Thyroidectomie partielle',
            'Biopsie ganglionnaire',
            'Suture tendon achille',
            'Reparation coiffe rotateurs',
            'Hemicolectomie droite',
            'Pose chambre implantable',
        ];
    }

    /**
     * @return list<FicheTechnique>
     */
    private function createFichesTechniques(ChirurgieModele $modele, string $intitule): array
    {
        $etapes = [
            ['Installation', 'Installer le patient selon le protocole et verifier les points d appui.'],
            ['Preparation de salle', 'Controler l aspiration, l eclairage, l imagerie et la disponibilite du plateau.'],
            ['Temps operatoire', 'Confirmer les instruments critiques et les consommables specifiques a l intervention.'],
        ];

        $fiches = [];
        foreach ($etapes as $index => [$titre, $description]) {
            $fiches[] = (new FicheTechnique())
                ->setTitre($titre.' - '.$intitule)
                ->setDescription($description)
                ->setOrdre($index + 1)
                ->setChirurgieModele($modele);
        }

        return $fiches;
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function createMaterielsData(): array
    {
        $noms = [
            'Scalpel n10', 'Scalpel n15', 'Manche bistouri n3', 'Manche bistouri n4',
            'Ciseaux Mayo droits', 'Ciseaux Mayo courbes', 'Ciseaux Metzenbaum', 'Pince Kocher droite',
            'Pince Kocher courbe', 'Pince Kelly', 'Pince Halsted', 'Pince Adson',
            'Pince a dissequer', 'Pince anatomique', 'Pince chirurgicale', 'Pince porte aiguille',
            'Ecarteur Farabeuf', 'Ecarteur Gelpi', 'Ecarteur Weitlaner', 'Ecarteur abdominal',
            'Valve de Doyen', 'Valve de Richardson', 'Aiguille courbe', 'Aiguille droite',
            'Fil resorbable 2-0', 'Fil resorbable 3-0', 'Fil non resorbable 2-0', 'Fil non resorbable 3-0',
            'Agrafeuse cutanee', 'Ote agrafes', 'Compresses steriles 10x10', 'Compresses steriles 5x5',
            'Champs operatoires', 'Casaque sterile', 'Gants steriles taille 6', 'Gants steriles taille 7',
            'Gants steriles taille 8', 'Seringue 10 ml', 'Seringue 20 ml', 'Aiguille injection',
            'Canule aspiration', 'Tuyau aspiration', 'Bocal aspiration', 'Electrode bistouri',
            'Plaque bistouri electrique', 'Cable bistouri electrique', 'Poignee lumiere sterile', 'Sonde urinaire',
            'Poche recueil', 'Drain Redon', 'Drain aspiratif', 'Lame de drainage',
            'Set perfusion', 'Tubulure perfusion', 'Pansement sterile', 'Pansement compressif',
            'Bande adhesive', 'Bande elastique', 'Garrot pneumatique', 'Moteur orthopedique',
            'Scie oscillante', 'Foret 2 mm', 'Foret 3 mm', 'Foret 4 mm',
            'Broche Kirschner', 'Plaque verrouillee', 'Vis corticale', 'Vis spongieuse',
            'Guide de coupe', 'Ancillaire genou', 'Ancillaire hanche', 'Cotyle essai',
            'Tige femorale essai', 'Rape femorale', 'Curette osseuse', 'Maillet orthopedique',
            'Osteotome', 'Rugine', 'Pince a os', 'Camera arthroscopie',
            'Optique 30 degres', 'Trocart arthroscopie', 'Shaver', 'Pompe arthroscopie',
            'Canule arthroscopie', 'Fil guide', 'Trocart coelioscopie', 'Optique coelioscopie',
            'Insufflateur', 'Clip applier', 'Pinces coelioscopie', 'Sac extraction',
            'Ligasure', 'Hemolock', 'Plateau anesthesie', 'Masque oxygene',
            'Capteur saturation', 'Couverture chauffante', 'Solution antiseptique', 'Brosse chirurgicale',
        ];
        $types = ['Instrument', 'Consommable', 'Equipement', 'Boite operatoire', 'Implant', 'Plateau'];
        $adresses = ['Armoire A', 'Armoire B', 'Armoire C', 'Reserve sterile', 'Salle technique', 'Zone anesthesie'];

        $materiels = [];
        foreach ($noms as $index => $nom) {
            $materiels[] = [
                $nom,
                sprintf('%s%d', $adresses[$index % count($adresses)], intdiv($index, count($adresses)) + 1),
                $types[$index % count($types)],
            ];
        }

        return $materiels;
    }

    /**
     * @param list<Materiel> $materiels
     *
     * @return list<Materiel>
     */
    private function pickMaterielsForList(array $materiels, int $chirurgienIndex, int $modeleIndex): array
    {
        $selection = [];
        $start = (($modeleIndex * 5) + ($chirurgienIndex * 3)) % count($materiels);
        $count = 10 + (($modeleIndex + $chirurgienIndex) % 6);

        for ($offset = 0; $offset < $count; ++$offset) {
            $selection[] = $materiels[($start + ($offset * 7)) % count($materiels)];
        }

        return $selection;
    }
}
