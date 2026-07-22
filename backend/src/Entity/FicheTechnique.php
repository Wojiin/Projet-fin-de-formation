<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Repository\FicheTechniqueRepository;
use App\State\FicheTechniqueParModeleProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FicheTechniqueRepository::class)]
#[ApiResource(
    description: 'Fiche technique décrivant les consignes liées à une chirurgie modèle.',
    operations: [
        new GetCollection(uriTemplate: '/fiches-techniques', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['fiche_technique:list']], openapi: new OpenApiOperation(summary: 'Lister les fiches techniques', description: 'Retourne les fiches techniques disponibles pour les chirurgies modèles.')),
        new GetCollection(uriTemplate: '/chirurgie-modeles/{id}/fiches-techniques', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['fiche_technique:read']], provider: FicheTechniqueParModeleProvider::class, openapi: new OpenApiOperation(summary: 'Lister les fiches d’une chirurgie modèle', description: 'Retourne les consignes techniques rattachées à une intervention type.')),
        new Get(uriTemplate: '/fiches-techniques/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['fiche_technique:read']], openapi: new OpenApiOperation(summary: 'Consulter une fiche technique', description: 'Retourne le détail d’une consigne technique à partir de son identifiant.')),
        new Post(uriTemplate: '/fiches-techniques', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['fiche_technique:write']], normalizationContext: ['groups' => ['fiche_technique:read']], openapi: new OpenApiOperation(summary: 'Créer une fiche technique', description: 'Ajoute une consigne technique à une chirurgie modèle.')),
        new Patch(uriTemplate: '/fiches-techniques/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['fiche_technique:write']], normalizationContext: ['groups' => ['fiche_technique:read']], openapi: new OpenApiOperation(summary: 'Modifier une fiche technique', description: 'Met à jour le contenu, l’ordre ou l’image d’une fiche technique.')),
        new Delete(uriTemplate: '/fiches-techniques/{id}', security: "is_granted('ROLE_ADMIN')", openapi: new OpenApiOperation(summary: 'Supprimer une fiche technique', description: 'Supprime une consigne technique du référentiel.')),
    ]
)]
class FicheTechnique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['fiche_technique:read', 'fiche_technique:list', 'vue_finale:read'])]
    private ?int $id = null;
    #[ORM\Column(length: 150)]
    #[Groups(['fiche_technique:read', 'fiche_technique:list', 'fiche_technique:write', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $titre = null;
    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['fiche_technique:read', 'fiche_technique:write', 'vue_finale:read'])]
    #[Assert\NotBlank]
    private ?string $description = null;
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['fiche_technique:read', 'fiche_technique:write', 'vue_finale:read'])]
    #[Assert\Length(max: 255)]
    private ?string $lienImage = null;
    #[ORM\Column]
    #[Groups(['fiche_technique:read', 'fiche_technique:list', 'fiche_technique:write', 'vue_finale:read'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private ?int $ordre = null;
    #[ORM\ManyToOne(inversedBy: 'fichesTechniques')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['fiche_technique:read', 'fiche_technique:write'])]
    #[Assert\NotNull]
    private ?ChirurgieModele $chirurgieModele = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLienImage(): ?string
    {
        return $this->lienImage;
    }

    public function setLienImage(?string $lienImage): static
    {
        $this->lienImage = $lienImage;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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
}
