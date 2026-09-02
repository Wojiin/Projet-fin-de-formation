<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\ChirurgienRepository;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChirurgienRepository::class)]
#[ORM\Index(name: 'idx_chirurgien_nom', columns: ['nom'])]
#[ApiResource(
    description: 'Référentiel des chirurgiens utilisés pour la planification des interventions.',
    operations: [
        new GetCollection(uriTemplate: '/chirurgiens', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['chirurgien:list']], paginationEnabled: false, parameters: [
            'nom' => new QueryParameter(property: 'nom', filter: new PartialSearchFilter()),
            'prenom' => new QueryParameter(property: 'prenom', filter: new PartialSearchFilter()),
            'specialite' => new QueryParameter(property: 'specialite', filter: new ExactFilter()),
        ], openapi: new OpenApiOperation(summary: 'Lister les chirurgiens', description: 'Retourne les chirurgiens enregistrés dans le référentiel.')),
        new Get(uriTemplate: '/chirurgiens/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['chirurgien:read']], openapi: new OpenApiOperation(summary: 'Consulter un chirurgien', description: 'Retourne le détail d’un chirurgien à partir de son identifiant.')),
        new Post(uriTemplate: '/chirurgiens', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['chirurgien:write']], normalizationContext: ['groups' => ['chirurgien:read']], openapi: new OpenApiOperation(summary: 'Créer un chirurgien', description: 'Ajoute un chirurgien au référentiel de planification.')),
        new Patch(uriTemplate: '/chirurgiens/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['chirurgien:write']], normalizationContext: ['groups' => ['chirurgien:read']], openapi: new OpenApiOperation(summary: 'Modifier un chirurgien', description: 'Met à jour les informations d’un chirurgien existant.')),
        new Delete(uriTemplate: '/chirurgiens/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer un chirurgien', description: 'Supprime un chirurgien si aucune donnée liée ne bloque la suppression.')),
    ]
)]
/** Référentiel des praticiens pouvant porter un programme et une liste de matériel. */
class Chirurgien
{
    // Les accesseurs exposent l'identité du praticien ; les méthodes de collection synchronisent les relations Doctrine.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['chirurgien:read', 'chirurgien:list', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['chirurgien:read', 'chirurgien:list', 'chirurgien:write', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    #[Groups(['chirurgien:read', 'chirurgien:list', 'chirurgien:write', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'chirurgiens')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['chirurgien:read', 'chirurgien:list', 'chirurgien:write', 'liste_materiel:read', 'liste_materiel:list', 'chirurgie_planifiee:read', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?Specialite $specialite = null;

    /** @var Collection<int, ListeMateriel> */
    #[ORM\OneToMany(targetEntity: ListeMateriel::class, mappedBy: 'chirurgien')]
    private Collection $listesMateriel;

    /** @var Collection<int, ChirurgiePlanifiee> */
    #[ORM\OneToMany(targetEntity: ChirurgiePlanifiee::class, mappedBy: 'chirurgien')]
    private Collection $chirurgiesPlanifiees;

    /** Initialise les collections de listes et de chirurgies du praticien. */
    public function __construct()
    {
        $this->listesMateriel = new ArrayCollection();
        $this->chirurgiesPlanifiees = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
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

    /** @return Collection<int, ListeMateriel> */
    public function getListesMateriel(): Collection
    {
        return $this->listesMateriel;
    }

    /** Associe une liste de matériel au chirurgien en maintenant la relation inverse. */
    public function addListeMateriel(ListeMateriel $liste): static
    {
        if (!$this->listesMateriel->contains($liste)) {
            $this->listesMateriel->add($liste);
            $liste->setChirurgien($this);
        }
        return $this;
    }

    /** Retire une liste de matériel de la collection du chirurgien. */
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

    /** Associe une chirurgie planifiée au chirurgien et synchronise son inverse. */
    public function addChirurgiePlanifiee(ChirurgiePlanifiee $chirurgie): static
    {
        if (!$this->chirurgiesPlanifiees->contains($chirurgie)) {
            $this->chirurgiesPlanifiees->add($chirurgie);
            $chirurgie->setChirurgien($this);
        }
        return $this;
    }

    /** Retire une chirurgie planifiée de la collection du chirurgien. */
    public function removeChirurgiePlanifiee(ChirurgiePlanifiee $chirurgie): static
    {
        $this->chirurgiesPlanifiees->removeElement($chirurgie);
        return $this;
    }
}
