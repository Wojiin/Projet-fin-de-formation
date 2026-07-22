<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\ListeMaterielRepository;
use App\State\ListeMaterielParChirurgienProvider;
use App\State\ListeMaterielParModeleProvider;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ListeMaterielRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_liste_chirurgien_modele', columns: ['chirurgien_id', 'chirurgie_modele_id'])]
#[ApiResource(
    description: 'Liste de matériel personnalisée pour un couple chirurgien / chirurgie modèle.',
    operations: [
        new GetCollection(uriTemplate: '/listes-materiel', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:list']], openapi: new OpenApiOperation(summary: 'Lister les listes de matériel', description: 'Retourne les listes de matériel personnalisées par chirurgien et chirurgie modèle.')),
        new GetCollection(uriTemplate: '/chirurgiens/{id}/listes-materiel', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:read']], provider: ListeMaterielParChirurgienProvider::class, openapi: new OpenApiOperation(summary: 'Lister les listes d’un chirurgien', description: 'Retourne les listes de matériel associées à un chirurgien donné.')),
        new GetCollection(uriTemplate: '/chirurgie-modeles/{id}/listes-materiel', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:read']], provider: ListeMaterielParModeleProvider::class, openapi: new OpenApiOperation(summary: 'Lister les listes d’une chirurgie modèle', description: 'Retourne les listes de matériel associées à une intervention type.')),
        new Get(uriTemplate: '/listes-materiel/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:read']], openapi: new OpenApiOperation(summary: 'Consulter une liste de matériel', description: 'Retourne le détail d’une liste de matériel et ses éléments.')),
        new Post(uriTemplate: '/listes-materiel', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['liste_materiel:write']], normalizationContext: ['groups' => ['liste_materiel:read']], openapi: new OpenApiOperation(summary: 'Créer une liste de matériel', description: 'Crée une liste personnalisée pour un couple chirurgien et chirurgie modèle.')),
        new Patch(uriTemplate: '/listes-materiel/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['liste_materiel:write']], normalizationContext: ['groups' => ['liste_materiel:read']], openapi: new OpenApiOperation(summary: 'Modifier une liste de matériel', description: 'Met à jour l’intitulé ou le contenu d’une liste de matériel.')),
        new Delete(uriTemplate: '/listes-materiel/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer une liste de matériel', description: 'Supprime une liste de matériel si elle n’est pas référencée par une préparation.')),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['chirurgien' => 'exact', 'chirurgieModele' => 'exact'])]
class ListeMateriel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['liste_materiel:read', 'liste_materiel:list', 'preparation:read'])]
    private ?int $id = null;
    #[ORM\Column(length: 150)]
    #[Groups(['liste_materiel:read', 'liste_materiel:list', 'liste_materiel:write', 'preparation:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $intitule = null;
    #[ORM\ManyToOne(inversedBy: 'listesMateriel')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['liste_materiel:read', 'liste_materiel:write'])]
    #[Assert\NotNull]
    private ?Chirurgien $chirurgien = null;
    #[ORM\ManyToOne(inversedBy: 'listesMateriel')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['liste_materiel:read', 'liste_materiel:write'])]
    #[Assert\NotNull]
    private ?ChirurgieModele $chirurgieModele = null;
    /** @var Collection<int, Materiel> */
    #[ORM\ManyToMany(targetEntity: Materiel::class, inversedBy: 'listesMateriel')]
    #[ORM\JoinTable(name: 'liste_materiel_materiel')]
    #[Groups(['liste_materiel:read', 'liste_materiel:write', 'preparation:read'])]
    private Collection $materiels;

    public function __construct()
    {
        $this->materiels = new ArrayCollection();
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

    public function getChirurgien(): ?Chirurgien
    {
        return $this->chirurgien;
    }

    public function setChirurgien(?Chirurgien $chirurgien): static
    {
        $this->chirurgien = $chirurgien;
        return $this;
    }

    public function getChirurgieModele(): ?ChirurgieModele
    {
        return $this->chirurgieModele;
    }

    public function setChirurgieModele(?ChirurgieModele $chirurgieModele): static
    {
        $this->chirurgieModele = $chirurgieModele;
        return $this;
    }

    /** @return Collection<int, Materiel> */
    public function getMateriels(): Collection
    {
        return $this->materiels;
    }

    public function addMateriel(Materiel $materiel): static
    {
        if (!$this->materiels->contains($materiel)) {
            $this->materiels->add($materiel);
        }
        return $this;
    }

    public function removeMateriel(Materiel $materiel): static
    {
        $this->materiels->removeElement($materiel);
        return $this;
    }
}
