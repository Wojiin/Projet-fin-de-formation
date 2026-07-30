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
use App\Repository\MaterielRepository;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
#[ORM\Index(name: 'idx_materiel_intitule', columns: ['intitule'])]
#[ApiResource(
    description: 'Référentiel du matériel utilisé pour préparer les chirurgies.',
    operations: [
        new GetCollection(uriTemplate: '/materiels', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['materiel:list']], parameters: [
            'intitule' => new QueryParameter(property: 'intitule', filter: new PartialSearchFilter()),
            'typeMateriel' => new QueryParameter(property: 'typeMateriel', filter: new ExactFilter()),
            'adresse' => new QueryParameter(property: 'adresse', filter: new PartialSearchFilter()),
            'specialite' => new QueryParameter(property: 'specialite', filter: new ExactFilter()),
        ], openapi: new OpenApiOperation(summary: 'Lister le matériel', description: 'Retourne le référentiel du matériel utilisé pour les préparations opératoires.')),
        new Get(uriTemplate: '/materiels/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['materiel:read']], openapi: new OpenApiOperation(summary: 'Consulter un matériel', description: 'Retourne le détail d’un matériel à partir de son identifiant.')),
        new Post(uriTemplate: '/materiels', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['materiel:write']], normalizationContext: ['groups' => ['materiel:read']], openapi: new OpenApiOperation(summary: 'Créer un matériel', description: 'Ajoute un matériel au référentiel.')),
        new Patch(uriTemplate: '/materiels/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['materiel:write']], normalizationContext: ['groups' => ['materiel:read']], openapi: new OpenApiOperation(summary: 'Modifier un matériel', description: 'Met à jour l’intitulé, le type ou l’adresse d’un matériel.')),
        new Delete(uriTemplate: '/materiels/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer un matériel', description: 'Supprime un matériel si aucune liste ou préparation ne le référence.')),
    ]
)]
/** Référentiel d'un élément matériel et de sa localisation de stockage. */
class Materiel
{
    // Les accesseurs décrivent le matériel ; les méthodes de collection en maintiennent les utilisations référentielles.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['materiel:read', 'materiel:list', 'liste_materiel:read', 'preparation_materiel:read', 'preparation:read', 'vue_finale:read'])]
    private ?int $id = null;
    #[ORM\Column(length: 150)]
    #[Groups(['materiel:read', 'materiel:list', 'materiel:write', 'liste_materiel:read', 'preparation_materiel:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $intitule = null;
    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['materiel:read', 'materiel:list', 'materiel:write', 'liste_materiel:read', 'preparation_materiel:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\Length(max: 150)]
    private ?string $adresse = null;
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['materiel:read', 'materiel:list', 'materiel:write', 'liste_materiel:read', 'preparation_materiel:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\Length(max: 100)]
    private ?string $typeMateriel = null;

    #[ORM\ManyToOne(inversedBy: 'materiels')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['materiel:read', 'materiel:list', 'materiel:write', 'liste_materiel:read', 'preparation_materiel:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?Specialite $specialite = null;

    /** @var Collection<int, ListeMateriel> */
    #[ORM\ManyToMany(targetEntity: ListeMateriel::class, mappedBy: 'materiels')]
    private Collection $listesMateriel;
    /** @var Collection<int, PreparationMateriel> */
    #[ORM\OneToMany(targetEntity: PreparationMateriel::class, mappedBy: 'materiel', orphanRemoval: true)]
    private Collection $preparationsMateriel;

    /** Initialise les collections de listes et de préparations qui référencent ce matériel. */
    public function __construct()
    {
        $this->listesMateriel = new ArrayCollection();
        $this->preparationsMateriel = new ArrayCollection();
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getTypeMateriel(): ?string
    {
        return $this->typeMateriel;
    }

    public function setTypeMateriel(?string $typeMateriel): static
    {
        $this->typeMateriel = $typeMateriel;
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

    /** Ajoute une liste utilisatrice et synchronise la relation many-to-many. */
    public function addListeMateriel(ListeMateriel $liste): static
    {
        if (!$this->listesMateriel->contains($liste)) {
            $this->listesMateriel->add($liste);
            $liste->addMateriel($this);
        }
        return $this;
    }

    /** Retire une liste utilisatrice et synchronise la relation inverse. */
    public function removeListeMateriel(ListeMateriel $liste): static
    {
        if ($this->listesMateriel->removeElement($liste)) {
            $liste->removeMateriel($this);
        }
        return $this;
    }

    /** @return Collection<int, PreparationMateriel> */
    public function getPreparationsMateriel(): Collection
    {
        return $this->preparationsMateriel;
    }

    /** Rattache une ligne de préparation au matériel concerné. */
    public function addPreparationMateriel(PreparationMateriel $preparation): static
    {
        if (!$this->preparationsMateriel->contains($preparation)) {
            $this->preparationsMateriel->add($preparation);
            $preparation->setMateriel($this);
        }
        return $this;
    }

    /** Retire une ligne de préparation du matériel. */
    public function removePreparationMateriel(PreparationMateriel $preparation): static
    {
        if ($this->preparationsMateriel->removeElement($preparation) && $preparation->getMateriel() === $this) {
            $preparation->setMateriel(null);
        }
        return $this;
    }
}
