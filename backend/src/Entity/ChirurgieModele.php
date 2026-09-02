<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Repository\ChirurgieModeleRepository;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChirurgieModeleRepository::class)]
#[ORM\Index(name: 'idx_chirurgie_modele_intitule', columns: ['intitule'])]
#[ApiResource(
    description: 'Référentiel des interventions ou chirurgies modèles.',
    operations: [
        new GetCollection(uriTemplate: '/chirurgie-modeles', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['chirurgie_modele:list']], parameters: [
            'intitule' => new QueryParameter(property: 'intitule', filter: new PartialSearchFilter()),
            'specialite' => new QueryParameter(property: 'specialite', filter: new ExactFilter()),
        ], openapi: new OpenApiOperation(summary: 'Lister les chirurgies modèles', description: 'Retourne le référentiel des interventions types disponibles pour la planification.')),
        new Get(uriTemplate: '/chirurgie-modeles/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['chirurgie_modele:read']], openapi: new OpenApiOperation(summary: 'Consulter une chirurgie modèle', description: 'Retourne le détail d’une intervention type à partir de son identifiant.')),
        new Post(uriTemplate: '/chirurgie-modeles', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['chirurgie_modele:write']], normalizationContext: ['groups' => ['chirurgie_modele:read']], openapi: new OpenApiOperation(summary: 'Créer une chirurgie modèle', description: 'Ajoute une nouvelle intervention type au référentiel.')),
        new Patch(uriTemplate: '/chirurgie-modeles/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['chirurgie_modele:write']], normalizationContext: ['groups' => ['chirurgie_modele:read']], openapi: new OpenApiOperation(summary: 'Modifier une chirurgie modèle', description: 'Met à jour les informations d’une intervention type existante.')),
        new Delete(uriTemplate: '/chirurgie-modeles/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer une chirurgie modèle', description: 'Supprime une intervention type si elle n’est pas utilisée par des données liées.')),
    ]
)]
/** Référentiel d'interventions types : spécialité, fiches techniques et listes de matériel associées. */
class ChirurgieModele
{
    // Les accesseurs exposent les attributs du référentiel ; les méthodes add/remove maintiennent les relations Doctrine.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['chirurgie_modele:read', 'chirurgie_modele:list', 'fiche_technique:read', 'fiche_technique:list', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Groups(['chirurgie_modele:read', 'chirurgie_modele:list', 'chirurgie_modele:write', 'fiche_technique:read', 'fiche_technique:list', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $intitule = null;

    #[ORM\ManyToOne(inversedBy: 'chirurgiesModeles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['chirurgie_modele:read', 'chirurgie_modele:list', 'chirurgie_modele:write', 'fiche_technique:read', 'fiche_technique:list', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?Specialite $specialite = null;

    /** @var Collection<int, FicheTechnique> */
    #[ORM\OneToMany(targetEntity: FicheTechnique::class, mappedBy: 'chirurgieModele', orphanRemoval: true)]
    #[Groups(['vue_finale:read'])]
    private Collection $fichesTechniques;

    /** @var Collection<int, ListeMateriel> */
    #[ORM\OneToMany(targetEntity: ListeMateriel::class, mappedBy: 'chirurgieModele')]
    private Collection $listesMateriel;

    /** @var Collection<int, ChirurgiePlanifiee> */
    #[ORM\OneToMany(targetEntity: ChirurgiePlanifiee::class, mappedBy: 'chirurgieModele')]
    private Collection $chirurgiesPlanifiees;

    /** Initialise les collections relationnelles maintenues des deux côtés. */
    public function __construct()
    {
        $this->fichesTechniques = new ArrayCollection();
        $this->listesMateriel = new ArrayCollection();
        $this->chirurgiesPlanifiees = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(string $intitule): static
    {
        $this->intitule = $intitule;
        return $this;
    }

    public function getSpecialite(): ?Specialite
    {
        return $this->specialite;
    }

    public function setSpecialite(?Specialite $specialite): static
    {
        $this->specialite = $specialite;

        return $this;
    }

    /** @return Collection<int, FicheTechnique> */
    public function getFichesTechniques(): Collection
    {
        return $this->fichesTechniques;
    }

    /** Attache une fiche technique en synchronisant la relation inverse. */
    public function addFicheTechnique(FicheTechnique $fiche): static
    {
        if (!$this->fichesTechniques->contains($fiche)) {
            $this->fichesTechniques->add($fiche);
            $fiche->setChirurgieModele($this);
        }
        return $this;
    }

    /** Retire une fiche technique et libère sa relation avec le modèle. */
    public function removeFicheTechnique(FicheTechnique $fiche): static
    {
        if ($this->fichesTechniques->removeElement($fiche) && $fiche->getChirurgieModele() === $this) {
            $fiche->setChirurgieModele(null);
        }
        return $this;
    }

    /** @return Collection<int, ListeMateriel> */
    public function getListesMateriel(): Collection
    {
        return $this->listesMateriel;
    }

    /** Attache une liste de matériel au modèle en maintenant la relation inverse. */
    public function addListeMateriel(ListeMateriel $liste): static
    {
        if (!$this->listesMateriel->contains($liste)) {
            $this->listesMateriel->add($liste);
            $liste->setChirurgieModele($this);
        }
        return $this;
    }

    /** Retire une liste liée de la collection locale. */
    public function removeListeMateriel(ListeMateriel $liste): static
    {
        $this->listesMateriel->removeElement($liste);
        return $this;
    }

    /** @return Collection<int, ChirurgiePlanifiee> */
    public function getChirurgiesPlanifiees(): Collection
    {
        return $this->chirurgiesPlanifiees;
    }

    /** Associe une chirurgie planifiée au modèle et synchronise son inverse. */
    public function addChirurgiePlanifiee(ChirurgiePlanifiee $chirurgie): static
    {
        if (!$this->chirurgiesPlanifiees->contains($chirurgie)) {
            $this->chirurgiesPlanifiees->add($chirurgie);
            $chirurgie->setChirurgieModele($this);
        }
        return $this;
    }

    /** Retire une chirurgie planifiée de la collection du modèle. */
    public function removeChirurgiePlanifiee(ChirurgiePlanifiee $chirurgie): static
    {
        $this->chirurgiesPlanifiees->removeElement($chirurgie);
        return $this;
    }
}
