<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Repository\SpecialiteRepository;
use App\State\SpecialiteDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SpecialiteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_specialite_intitule', columns: ['intitule'])]
#[ApiResource(
    description: 'Spécialité chirurgicale reliant les chirurgiens, les modèles de chirurgie et le matériel.',
    operations: [
        new GetCollection(
            uriTemplate: '/specialites',
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['specialite:list']],
            parameters: [
                'intitule' => new QueryParameter(property: 'intitule', filter: new PartialSearchFilter()),
            ],
            openapi: new OpenApiOperation(summary: 'Lister les spécialités chirurgicales'),
        ),
        new Get(uriTemplate: '/specialites/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['specialite:read']], openapi: new OpenApiOperation(summary: 'Consulter une spécialité chirurgicale')),
        new Post(uriTemplate: '/specialites', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['specialite:write']], normalizationContext: ['groups' => ['specialite:read']], openapi: new OpenApiOperation(summary: 'Créer une spécialité chirurgicale')),
        new Patch(uriTemplate: '/specialites/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['specialite:write']], normalizationContext: ['groups' => ['specialite:read']], openapi: new OpenApiOperation(summary: 'Modifier une spécialité chirurgicale')),
        new Delete(uriTemplate: '/specialites/{id}', security: "is_granted('ROLE_ADMIN')", processor: SpecialiteDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer une spécialité et réaffecter ses références')),
    ],
)]
/** Référentiel de spécialités, avec une valeur de repli protégée pour préserver les liens. */
class Specialite
{
    // Les accesseurs exposent le libellé et les collections réaffectées lors d'une suppression de spécialité.
    public const SANS_SPECIALITE = 'Sans spécialité';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['specialite:read', 'specialite:list', 'chirurgien:read', 'chirurgien:list', 'liste_materiel:read', 'chirurgie_planifiee:read', 'chirurgie_modele:read', 'chirurgie_modele:list', 'materiel:read', 'materiel:list', 'preparation_materiel:read', 'preparation:read', 'programme:read', 'vue_finale:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['specialite:read', 'specialite:list', 'specialite:write', 'chirurgien:read', 'chirurgien:list', 'liste_materiel:read', 'chirurgie_planifiee:read', 'chirurgie_modele:read', 'chirurgie_modele:list', 'materiel:read', 'materiel:list', 'preparation_materiel:read', 'preparation:read', 'programme:read', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $intitule = null;

    /** @var Collection<int, Chirurgien> */
    #[ORM\OneToMany(targetEntity: Chirurgien::class, mappedBy: 'specialite')]
    private Collection $chirurgiens;

    /** @var Collection<int, Materiel> */
    #[ORM\OneToMany(targetEntity: Materiel::class, mappedBy: 'specialite')]
    private Collection $materiels;

    /** @var Collection<int, ChirurgieModele> */
    #[ORM\OneToMany(targetEntity: ChirurgieModele::class, mappedBy: 'specialite')]
    private Collection $chirurgiesModeles;

    /** Initialise les collections des référentiels classés par spécialité. */
    public function __construct()
    {
        $this->chirurgiens = new ArrayCollection();
        $this->materiels = new ArrayCollection();
        $this->chirurgiesModeles = new ArrayCollection();
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

    /** @return Collection<int, Chirurgien> */
    public function getChirurgiens(): Collection
    {
        return $this->chirurgiens;
    }

    /** Affecte un chirurgien à la spécialité et synchronise la relation inverse. */
    public function addChirurgien(Chirurgien $chirurgien): static
    {
        if (!$this->chirurgiens->contains($chirurgien)) {
            $this->chirurgiens->add($chirurgien);
            $chirurgien->setSpecialite($this);
        }

        return $this;
    }

    /** Retire un chirurgien de la collection de spécialité. */
    public function removeChirurgien(Chirurgien $chirurgien): static
    {
        if ($this->chirurgiens->removeElement($chirurgien) && $chirurgien->getSpecialite() === $this) {
            $chirurgien->setSpecialite(null);
        }

        return $this;
    }

    /** @return Collection<int, Materiel> */
    public function getMateriels(): Collection
    {
        return $this->materiels;
    }

    /** Affecte un matériel à la spécialité et synchronise la relation inverse. */
    public function addMateriel(Materiel $materiel): static
    {
        if (!$this->materiels->contains($materiel)) {
            $this->materiels->add($materiel);
            $materiel->setSpecialite($this);
        }

        return $this;
    }

    /** Retire un matériel de la collection de spécialité. */
    public function removeMateriel(Materiel $materiel): static
    {
        if ($this->materiels->removeElement($materiel) && $materiel->getSpecialite() === $this) {
            $materiel->setSpecialite(null);
        }

        return $this;
    }

    /** @return Collection<int, ChirurgieModele> */
    public function getChirurgiesModeles(): Collection
    {
        return $this->chirurgiesModeles;
    }

    /** Affecte une chirurgie modèle à la spécialité et synchronise la relation inverse. */
    public function addChirurgieModele(ChirurgieModele $chirurgieModele): static
    {
        if (!$this->chirurgiesModeles->contains($chirurgieModele)) {
            $this->chirurgiesModeles->add($chirurgieModele);
            $chirurgieModele->setSpecialite($this);
        }

        return $this;
    }

    /** Retire une chirurgie modèle de la collection de spécialité. */
    public function removeChirurgieModele(ChirurgieModele $chirurgieModele): static
    {
        if ($this->chirurgiesModeles->removeElement($chirurgieModele) && $chirurgieModele->getSpecialite() === $this) {
            $chirurgieModele->setSpecialite(null);
        }

        return $this;
    }
}
