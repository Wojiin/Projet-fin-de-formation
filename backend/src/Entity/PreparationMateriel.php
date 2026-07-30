<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\PreparationMaterielInput;
use App\Repository\PreparationMaterielRepository;
use App\State\PreparationMaterielCocherProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PreparationMaterielRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_preparation_chirurgie_materiel', columns: ['chirurgie_planifiee_id', 'materiel_id'])]
#[ORM\Index(name: 'idx_preparation_coche', columns: ['coche'])]
#[ApiResource(
    description: 'État de préparation d’un matériel pour une chirurgie planifiée.',
    operations: [
        new GetCollection(uriTemplate: '/preparations-materiel', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['preparation_materiel:list']], parameters: [
            'chirurgiePlanifiee' => new QueryParameter(property: 'chirurgiePlanifiee', filter: new ExactFilter()),
            'materiel' => new QueryParameter(property: 'materiel', filter: new ExactFilter()),
            'coche' => new QueryParameter(property: 'coche', filter: new ExactFilter(), schema: ['type' => 'boolean'], castToNativeType: true),
        ], openapi: new OpenApiOperation(summary: 'Lister les préparations matériel', description: 'Retourne les lignes de préparation matériel avec leur état coché ou non coché.')),
        new GetCollection(uriTemplate: '/chirurgies-planifiees/{id}/preparations-materiel', uriVariables: ['id' => new Link(fromClass: ChirurgiePlanifiee::class, toProperty: 'chirurgiePlanifiee')], security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['preparation_materiel:read']], order: ['id' => 'ASC'], openapi: new OpenApiOperation(summary: 'Lister la préparation d’une chirurgie', description: 'Retourne les matériels à préparer pour une chirurgie planifiée donnée.')),
        new Get(uriTemplate: '/preparations-materiel/{id}', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['preparation_materiel:read']], openapi: new OpenApiOperation(summary: 'Consulter une préparation matériel', description: 'Retourne le détail d’une ligne de préparation matériel.')),
        new Patch(uriTemplate: '/preparations-materiel/{id}', security: "is_granted('ROLE_USER')", read: false, input: PreparationMaterielInput::class, output: PreparationMateriel::class, normalizationContext: ['groups' => ['preparation_materiel:read']], processor: PreparationMaterielCocherProcessor::class, openapi: new OpenApiOperation(summary: 'Modifier l’état de préparation', description: 'Met à jour l’état coché d’un matériel dans une préparation.')),
        new Patch(uriTemplate: '/preparations-materiel/{id}/cocher', security: "is_granted('ROLE_USER')", read: false, input: PreparationMaterielInput::class, output: PreparationMateriel::class, normalizationContext: ['groups' => ['preparation_materiel:read']], processor: PreparationMaterielCocherProcessor::class, openapi: new OpenApiOperation(summary: 'Cocher ou décocher un matériel', description: 'Change l’état de préparation d’un matériel et conserve l’utilisateur ayant effectué l’action.')),
        new Delete(uriTemplate: '/preparations-materiel/{id}', security: "is_granted('ROLE_ADMIN')", openapi: new OpenApiOperation(summary: 'Supprimer une préparation matériel', description: 'Supprime une ligne de préparation matériel.')),
    ]
)]
/** Trace l'état et l'auteur de la préparation d'un matériel pour une chirurgie donnée. */
class PreparationMateriel
{
    // Les accesseurs permettent au processeur métier de modifier l'état et sa traçabilité.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['preparation_materiel:read', 'preparation_materiel:list', 'preparation:read', 'vue_finale:read'])]
    private ?int $id = null;
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['preparation_materiel:read', 'preparation_materiel:list', 'preparation_materiel:write', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private bool $coche = false;
    #[ORM\Column(nullable: true)]
    #[Groups(['preparation_materiel:read', 'preparation_materiel:list', 'preparation:read', 'vue_finale:read'])]
    private ?\DateTimeImmutable $cocheLe = null;
    #[ORM\ManyToOne(inversedBy: 'preparationsMateriel')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['preparation_materiel:read'])]
    #[Assert\NotNull]
    private ?ChirurgiePlanifiee $chirurgiePlanifiee = null;
    #[ORM\ManyToOne(inversedBy: 'preparationsMateriel')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['preparation_materiel:read', 'preparation_materiel:list', 'preparation:read', 'vue_finale:read'])]
    #[Assert\NotNull]
    private ?Materiel $materiel = null;
    #[ORM\ManyToOne(inversedBy: 'preparationsCochees')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['preparation_materiel:read'])]
    private ?User $cochePar = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isCoche(): bool
    {
        return $this->coche;
    }

    public function setCoche(bool $coche): static
    {
        $this->coche = $coche;
        return $this;
    }

    public function getCocheLe(): ?\DateTimeImmutable
    {
        return $this->cocheLe;
    }

    public function setCocheLe(?\DateTimeImmutable $date): static
    {
        $this->cocheLe = $date;
        return $this;
    }

    public function getChirurgiePlanifiee(): ?ChirurgiePlanifiee
    {
        return $this->chirurgiePlanifiee;
    }

    public function setChirurgiePlanifiee(?ChirurgiePlanifiee $chirurgie): static
    {
        $this->chirurgiePlanifiee = $chirurgie;
        return $this;
    }

    public function getMateriel(): ?Materiel
    {
        return $this->materiel;
    }

    public function setMateriel(?Materiel $materiel): static
    {
        $this->materiel = $materiel;
        return $this;
    }

    public function getCochePar(): ?User
    {
        return $this->cochePar;
    }

    public function setCochePar(?User $user): static
    {
        $this->cochePar = $user;
        return $this;
    }
}
