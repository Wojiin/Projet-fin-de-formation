<?php

namespace App\DataFixtures;

use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\FicheTechnique;
use App\Entity\ListeMateriel;
use App\Entity\Materiel;
use App\Entity\PreparationMateriel;
use App\Entity\Specialite;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Constitue un jeu de données cohérent pour démontrer les règles de planification,
 * de checklist et de validation sans dépendre de données de production.
 */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    /** Crée les utilisateurs, référentiels, programmes et états de préparation de démonstration. */
    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser('admin@chirorg.test', ['ROLE_ADMIN']);
        $user = $this->createUser('user@chirorg.test', ['ROLE_USER']);
        $manager->persist($admin);
        $manager->persist($user);

        $specialites = [];
        foreach (['Orthopédie', 'Chirurgie viscérale et digestive', 'Chirurgie générale', 'Traumatologie', 'Urologie', Specialite::SANS_SPECIALITE] as $intitule) {
            $specialite = (new Specialite())->setIntitule($intitule);
            $specialites[] = $specialite;
            $manager->persist($specialite);
        }

        $chirurgiens = [
            $this->createChirurgien('Jean', 'Dupont', $specialites[0]),
            $this->createChirurgien('Claire', 'Martin', $specialites[1]),
            $this->createChirurgien('Alain', 'Bernard', $specialites[2]),
            $this->createChirurgien('Nadia', 'Petit', $specialites[3]),
            $this->createChirurgien('Hugo', 'Leroy', $specialites[4]),
        ];
        foreach ($chirurgiens as $chirurgien) {
            $manager->persist($chirurgien);
        }

        $modeles = [];
        $modelesParSpecialite = array_fill(0, count($specialites) - 1, []);
        foreach ($this->getChirurgieModeleData() as [$intitule, $specialiteIndex]) {
            $modele = (new ChirurgieModele())
                ->setIntitule($intitule)
                ->setSpecialite($specialites[$specialiteIndex]);
            $modeles[] = $modele;
            $modelesParSpecialite[$specialiteIndex][] = $modele;
            $manager->persist($modele);

            foreach ($this->createFichesTechniques($modele, $intitule) as $fiche) {
                $manager->persist($fiche);
            }
        }

        $materiels = [];
        foreach ($this->createMaterielsData() as $materielIndex => [$intitule, $adresse, $type]) {
            $materiel = (new Materiel())
                ->setIntitule($intitule)
                ->setAdresse($adresse)
                ->setTypeMateriel($type)
                ->setSpecialite($specialites[$materielIndex % (count($specialites) - 1)]);
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
            $modele = $modelesParSpecialite[$chirurgienIndex][$chirurgieIndex % count($modelesParSpecialite[$chirurgienIndex])];
            $modeleIndex = array_search($modele, $modeles, true);
            $date = $aujourdhui->modify(sprintf('+%d day', intdiv($chirurgieIndex, 6)));

            $chirurgie = $this->createChirurgie(
                $date,
                $salles[$groupeIndex % count($salles)],
                ($chirurgieIndex % 2) + 1,
                $chirurgiens[$chirurgienIndex],
                $modele,
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

    /** Crée un utilisateur de fixture en chiffrant le mot de passe de démonstration. */
    private function createUser(string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setRoles($roles);
        return $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
    }

    /** Crée un chirurgien relié à la spécialité attendue par les référentiels. */
    private function createChirurgien(string $prenom, string $nom, Specialite $specialite): Chirurgien
    {
        return (new Chirurgien())->setPrenom($prenom)->setNom($nom)->setSpecialite($specialite);
    }

    /** Crée une chirurgie planifiée avec son identité de programme et son ordre initial. */
    private function createChirurgie(\DateTimeImmutable $date, string $salle, int $ordre, Chirurgien $chirurgien, ChirurgieModele $modele): ChirurgiePlanifiee
    {
        return (new ChirurgiePlanifiee())
            ->setDateProgrammee($date)
            ->setSalle($salle)
            ->setOrdre($ordre)
            ->setChirurgien($chirurgien)
            ->setChirurgieModele($modele);
    }

    /** Retourne les modèles de chirurgie et l'index de leur spécialité de fixture.
     *
     * @return list<array{string, int}>
     */
    private function getChirurgieModeleData(): array
    {
        return [
            ['Prothèse totale de genou', 0],
            ['Prothèse totale de hanche', 0],
            ['Arthroscopie de l’épaule', 0],
            ['Arthroscopie du genou', 0],
            ['Appendicectomie', 1],
            ['Cholécystectomie cœlioscopique', 1],
            ['Hernie inguinale', 1],
            ['Hémicolectomie droite', 1],
            ['Thyroïdectomie partielle', 2],
            ['Biopsie ganglionnaire', 2],
            ['Pose de chambre implantable', 2],
            ['Cure d’éventration', 2],
            ['Ligamentoplastie du croisé antérieur', 3],
            ['Ostéosynthèse de cheville', 3],
            ['Ostéosynthèse de poignet', 3],
            ['Suture du tendon d’Achille', 3],
            ['Résection transurétrale de prostate', 4],
            ['Urétéroscopie', 4],
            ['Néphrectomie', 4],
            ['Prostatectomie', 4],
        ];
    }

    /** Construit les étapes techniques minimales communes à un modèle de chirurgie.
     *
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

    /** Génère le catalogue de matériel de démonstration réparti dans les salles de stockage.
     *
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

    /** Sélectionne de façon déterministe un sous-ensemble de matériel pour une liste.
     *
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
