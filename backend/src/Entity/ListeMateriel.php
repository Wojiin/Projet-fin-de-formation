<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Repository\ListeMaterielRepository;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ListeMaterielRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_liste_chirurgien_modele', columns: ['chirurgien_id', 'chirurgie_modele_id'])]
#[ApiResource(
    description: 'Liste de matériel personnalisée pour un couple chirurgien / chirurgie modèle.',
    operations: [
        new GetCollection(uriTemplate: '/listes-materiel', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:list']], paginationEnabled: false, parameters: [
            'chirurgien' => new QueryParameter(property: 'chirurgien', filter: new ExactFilter()),
            'chirurgieModele' => new QueryParameter(property: 'chirurgieModele', filter: new ExactFilter()),
            'specialite' => new QueryParameter(property: 'chirurgieModele.specialite', filter: new ExactFilter()),
        ], openapi: new OpenApiOperation(summary: 'Lister les listes de matériel', description: 'Retourne les listes de matériel personnalisées par chirurgien et chirurgie modèle.')),
        new GetCollection(uriTemplate: '/chirurgiens/{id}/listes-materiel', uriVariables: ['id' => new Link(fromClass: Chirurgien::class, toProperty: 'chirurgien')], security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:read']], order: ['intitule' => 'ASC'], openapi: new OpenApiOperation(summary: 'Lister les listes d’un chirurgien', description: 'Retourne les listes de matériel associées à un chirurgien donné.')),
        new GetCollection(uriTemplate: '/chirurgie-modeles/{id}/listes-materiel', uriVariables: ['id' => new Link(fromClass: ChirurgieModele::class, toProperty: 'chirurgieModele')], security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:read']], order: ['intitule' => 'ASC'], openapi: new OpenApiOperation(summary: 'Lister les listes d’une chirurgie modèle', description: 'Retourne les listes de matériel associées à une intervention type.')),
        new Get(uriTemplate: '/listes-materiel/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['liste_materiel:read']], openapi: new OpenApiOperation(summary: 'Consulter une liste de matériel', description: 'Retourne le détail d’une liste de matériel et ses éléments.')),
        new Post(uriTemplate: '/listes-materiel', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['liste_materiel:write']], normalizationContext: ['groups' => ['liste_materiel:read']], openapi: new OpenApiOperation(summary: 'Créer une liste de matériel', description: 'Crée une liste personnalisée pour un couple chirurgien et chirurgie modèle.')),
        new Patch(uriTemplate: '/listes-materiel/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['liste_materiel:write']], normalizationContext: ['groups' => ['liste_materiel:read']], openapi: new OpenApiOperation(summary: 'Modifier une liste de matériel', description: 'Met à jour l’intitulé ou le contenu d’une liste de matériel.')),
        new Delete(uriTemplate: '/listes-materiel/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer une liste de matériel', description: 'Supprime une liste de matériel si elle n’est pas référencée par une préparation.')),
    ]
)]
/** Définit le matériel requis pour un couple chirurgien / chirurgie modèle. */
class ListeMateriel
{
    // Les accesseurs décrivent le couple de référence et la collection de matériels qui en découle.
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
    #[Groups(['liste_materiel:read', 'liste_materiel:list', 'liste_materiel:write'])]
    #[Assert\NotNull]
    private ?Chirurgien $chirurgien = null;
    #[ORM\ManyToOne(inversedBy: 'listesMateriel')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['liste_materiel:read', 'liste_materiel:list', 'liste_materiel:write'])]
    #[Assert\NotNull]
    private ?ChirurgieModele $chirurgieModele = null;
    /** @var Collection<int, Materiel> */
    #[ORM\ManyToMany(targetEntity: Materiel::class, inversedBy: 'listesMateriel')]
    #[ORM\JoinTable(name: 'liste_materiel_materiel')]
    #[Groups(['liste_materiel:read', 'liste_materiel:write', 'preparation:read'])]
    #[Assert\Count(min: 1, minMessage: 'La liste doit contenir au moins un matériel.')]
    private Collection $materiels;

    /** Initialise la collection des matériels de la liste. */
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

    /** Ajoute un matériel sans doublon à la liste utilisée lors des préparations. */
    public function addMateriel(Materiel $materiel): static
    {
        if (!$this->materiels->contains($materiel)) {
            $this->materiels->add($materiel);
        }
        return $this;
    }

    /** Retire un matériel de la liste de référence. */
    public function removeMateriel(Materiel $materiel): static
    {
        $this->materiels->removeElement($materiel);
        return $this;
    }

    #[Assert\Callback]
    public function validateMaterielSpecialities(ExecutionContextInterface $context): void
    {
        $specialite = $this->chirurgien?->getSpecialite();
        if (null === $specialite) {
            return;
        }

        foreach ($this->materiels as $materiel) {
            if ($materiel->getSpecialite() !== $specialite) {
                $context->buildViolation('Tous les matériels doivent appartenir à la spécialité du chirurgien.')
                    ->atPath('materiels')
                    ->addViolation();
                return;
            }
        }
    }
}
