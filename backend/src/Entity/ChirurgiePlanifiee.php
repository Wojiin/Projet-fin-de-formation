<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Dto\ChirurgiePreparation;
use App\Dto\ChirurgieVueFinale;
use App\State\ChirurgiePlanifieeWriteProcessor;
use App\State\ChirurgiePreparationProvider;
use App\State\ChirurgieValidationProcessor;
use App\State\VueFinaleProvider;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChirurgiePlanifieeRepository::class)]
#[ORM\Index(name: 'idx_chirurgie_date', columns: ['date_programmee'])]
#[ORM\Index(name: 'idx_chirurgie_salle', columns: ['salle'])]
#[ORM\Index(name: 'idx_chirurgie_valide', columns: ['valide'])]
#[ApiResource(
    description: 'Chirurgie planifiée dans une salle et à une date donnée.',
    operations: [
        new GetCollection(uriTemplate: '/chirurgies-planifiees', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['chirurgie_planifiee:list']], parameters: [
            'dateProgrammee' => new QueryParameter(property: 'dateProgrammee', filter: DateFilter::class),
            'salle' => new QueryParameter(property: 'salle', filter: new ExactFilter()),
            'chirurgien' => new QueryParameter(property: 'chirurgien', filter: new ExactFilter()),
            'chirurgieModele' => new QueryParameter(property: 'chirurgieModele', filter: new ExactFilter()),
            'valide' => new QueryParameter(property: 'valide', filter: new ExactFilter(), schema: ['type' => 'boolean'], castToNativeType: true),
            'order[dateProgrammee]' => new QueryParameter(property: 'dateProgrammee', filter: new SortFilter()),
            'order[ordre]' => new QueryParameter(property: 'ordre', filter: new SortFilter()),
        ], openapi: new OpenApiOperation(summary: 'Lister les chirurgies planifiées', description: 'Retourne les chirurgies programmées, avec filtres possibles par date, salle, chirurgien, modèle ou validation.')),
        new Get(uriTemplate: '/chirurgies-planifiees/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['chirurgie_planifiee:read']], openapi: new OpenApiOperation(summary: 'Consulter une chirurgie planifiée', description: 'Retourne le détail d’une chirurgie planifiée à partir de son identifiant.')),
        new Get(uriTemplate: '/chirurgies-planifiees/{id}/preparation', security: "is_granted('ROLE_USER')", output: ChirurgiePreparation::class, provider: ChirurgiePreparationProvider::class, openapi: new OpenApiOperation(summary: 'Consulter la préparation matériel', description: 'Retourne la liste de préparation du matériel associée à une chirurgie planifiée.')),
        new Get(uriTemplate: '/chirurgies-planifiees/{id}/vue-finale', security: "is_granted('ROLE_USER')", output: ChirurgieVueFinale::class, provider: VueFinaleProvider::class, openapi: new OpenApiOperation(summary: 'Consulter la vue finale', description: 'Retourne la synthèse finale d’une chirurgie avec les consignes et l’état du matériel.')),
        new Post(uriTemplate: '/chirurgies-planifiees', security: "is_granted('ROLE_USER')", denormalizationContext: ['groups' => ['chirurgie_planifiee:write']], normalizationContext: ['groups' => ['chirurgie_planifiee:read']], processor: ChirurgiePlanifieeWriteProcessor::class, openapi: new OpenApiOperation(summary: 'Planifier une chirurgie', description: 'Crée une chirurgie planifiée et initialise sa préparation matériel.')),
        new Post(uriTemplate: '/chirurgies-planifiees/{id}/validation', security: "is_granted('ROLE_USER')", input: false, output: ChirurgiePlanifiee::class, normalizationContext: ['groups' => ['chirurgie_planifiee:read']], processor: ChirurgieValidationProcessor::class, openapi: new OpenApiOperation(summary: 'Valider une chirurgie', description: 'Marque une chirurgie planifiée comme validée par l’utilisateur connecté.')),
        new Patch(uriTemplate: '/chirurgies-planifiees/{id}', security: "is_granted('ROLE_USER')", denormalizationContext: ['groups' => ['chirurgie_planifiee:write']], normalizationContext: ['groups' => ['chirurgie_planifiee:read']], openapi: new OpenApiOperation(summary: 'Modifier une chirurgie planifiée', description: 'Met à jour la date, la salle, l’ordre ou les références d’une chirurgie planifiée.')),
        new Delete(uriTemplate: '/chirurgies-planifiees/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer une chirurgie planifiée', description: 'Supprime une chirurgie planifiée si les règles métier l’autorisent.')),
    ]
)]
class ChirurgiePlanifiee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'programme:read', 'preparation:read', 'vue_finale:read', 'preparation_materiel:read'])]
    private ?int $id = null;
    #[ORM\Column]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'chirurgie_planifiee:write', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $dateProgrammee = null;
    #[ORM\Column(length: 50)]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'chirurgie_planifiee:write', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $salle = null;
    #[ORM\Column(nullable: true)]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'chirurgie_planifiee:write', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\PositiveOrZero]
    private ?int $ordre = null;
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'programme:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private bool $valide = false;
    #[ORM\Column(nullable: true)]
    #[Groups(['chirurgie_planifiee:read', 'vue_finale:read'])]
    private ?\DateTimeImmutable $valideLe = null;
    #[ORM\ManyToOne(inversedBy: 'chirurgiesPlanifiees')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'chirurgie_planifiee:write', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?Chirurgien $chirurgien = null;
    #[ORM\ManyToOne(inversedBy: 'chirurgiesPlanifiees')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['chirurgie_planifiee:read', 'chirurgie_planifiee:list', 'chirurgie_planifiee:write', 'programme:read', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?ChirurgieModele $chirurgieModele = null;
    #[ORM\ManyToOne(inversedBy: 'chirurgiesValidees')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['chirurgie_planifiee:read', 'vue_finale:read'])]
    private ?User $validePar = null;
    /** @var Collection<int, PreparationMateriel> */
    #[ORM\OneToMany(targetEntity: PreparationMateriel::class, mappedBy: 'chirurgiePlanifiee', orphanRemoval: true)]
    #[Groups(['preparation:read', 'vue_finale:read'])]
    private Collection $preparationsMateriel;

    public function __construct()
    {
        $this->preparationsMateriel = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateProgrammee(): ?\DateTimeImmutable
    {
        return $this->dateProgrammee;
    }

    public function setDateProgrammee(\DateTimeImmutable $date): static
    {
        $this->dateProgrammee = $date;
        return $this;
    }

    public function getSalle(): ?string
    {
        return $this->salle;
    }

    public function setSalle(string $salle): static
    {
        $this->salle = $salle;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function isValide(): bool
    {
        return $this->valide;
    }

    public function setValide(bool $valide): static
    {
        $this->valide = $valide;
        return $this;
    }

    public function getValideLe(): ?\DateTimeImmutable
    {
        return $this->valideLe;
    }

    public function setValideLe(?\DateTimeImmutable $date): static
    {
        $this->valideLe = $date;
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

    public function setChirurgieModele(?ChirurgieModele $modele): static
    {
        $this->chirurgieModele = $modele;
        return $this;
    }

    public function getValidePar(): ?User
    {
        return $this->validePar;
    }

    public function setValidePar(?User $user): static
    {
        $this->validePar = $user;
        return $this;
    }

    /** @return Collection<int, PreparationMateriel> */
    public function getPreparationsMateriel(): Collection
    {
        return $this->preparationsMateriel;
    }

    public function addPreparationMateriel(PreparationMateriel $preparation): static
    {
        if (!$this->preparationsMateriel->contains($preparation)) {
            $this->preparationsMateriel->add($preparation);
            $preparation->setChirurgiePlanifiee($this);
        }
        return $this;
    }

    public function removePreparationMateriel(PreparationMateriel $preparation): static
    {
        if ($this->preparationsMateriel->removeElement($preparation) && $preparation->getChirurgiePlanifiee() === $this) {
            $preparation->setChirurgiePlanifiee(null);
        }
        return $this;
    }
}
