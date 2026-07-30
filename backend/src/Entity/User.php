<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Repository\UserRepository;
use App\State\MeProvider;
use App\State\UserWriteProcessor;
use App\State\ReferenceDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
#[ApiResource(
    description: 'Utilisateur authentifié de l’application ChirOrg.',
    operations: [
        new GetCollection(uriTemplate: '/users', security: "is_granted('ROLE_ADMIN')", normalizationContext: ['groups' => ['user:list']], openapi: new OpenApiOperation(summary: 'Lister les utilisateurs', description: 'Retourne les comptes utilisateurs de l’application.')),
        new Get(uriTemplate: '/users/{id}', security: "is_granted('ROLE_ADMIN')", normalizationContext: ['groups' => ['user:read']], openapi: new OpenApiOperation(summary: 'Consulter un utilisateur', description: 'Retourne le détail d’un compte utilisateur à partir de son identifiant.')),
        new Get(uriTemplate: '/me', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['user:read']], provider: MeProvider::class, openapi: new OpenApiOperation(summary: 'Consulter mon profil', description: 'Retourne les informations de l’utilisateur connecté.')),
        new Post(uriTemplate: '/users', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['user:write']], normalizationContext: ['groups' => ['user:read']], processor: UserWriteProcessor::class, openapi: new OpenApiOperation(summary: 'Créer un utilisateur', description: 'Crée un compte utilisateur et chiffre son mot de passe.')),
        new Patch(uriTemplate: '/users/{id}', security: "is_granted('ROLE_ADMIN')", denormalizationContext: ['groups' => ['user:write']], normalizationContext: ['groups' => ['user:read']], processor: UserWriteProcessor::class, openapi: new OpenApiOperation(summary: 'Modifier un utilisateur', description: 'Met à jour l’email, les rôles ou le mot de passe d’un compte utilisateur.')),
        new Delete(uriTemplate: '/users/{id}', security: "is_granted('ROLE_ADMIN')", processor: ReferenceDeleteProcessor::class, openapi: new OpenApiOperation(summary: 'Supprimer un utilisateur', description: 'Supprime un compte utilisateur si aucune donnée métier ne le référence.')),
    ]
)]
/** Utilisateur de l'intranet, responsable des validations et de la traçabilité des préparations. */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Les accesseurs de sécurité sont complétés par des collections qui conservent les traces métier de l'utilisateur.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'user:list', 'chirurgie_planifiee:read', 'preparation_materiel:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read', 'user:list', 'user:write', 'chirurgie_planifiee:read', 'preparation_materiel:read'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['user:read', 'user:list', 'user:write'])]
    #[Assert\Type('array')]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[Groups(['user:write'])]
    #[SerializedName('password')]
    #[Assert\Length(min: 8, max: 4096)]
    private ?string $plainPassword = null;

    /** @var Collection<int, RefreshToken> */
    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'user')]
    private Collection $refreshTokens;

    /** @var Collection<int, ChirurgiePlanifiee> */
    #[ORM\OneToMany(targetEntity: ChirurgiePlanifiee::class, mappedBy: 'validePar')]
    private Collection $chirurgiesValidees;

    /** @var Collection<int, PreparationMateriel> */
    #[ORM\OneToMany(targetEntity: PreparationMateriel::class, mappedBy: 'cochePar')]
    private Collection $preparationsCochees;

    /** Initialise les collections de traces métier et de refresh tokens de l'utilisateur. */
    public function __construct()
    {
        $this->refreshTokens = new ArrayCollection();
        $this->chirurgiesValidees = new ArrayCollection();
        $this->preparationsCochees = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    /** Retourne l'email comme identifiant stable exigé par Symfony Security. */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    /** Garantit le rôle minimal ROLE_USER, même lorsqu'aucun rôle explicite n'est stocké. */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /** @return Collection<int, RefreshToken> */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    /** Associe un refresh token à son propriétaire et synchronise l'inverse. */
    public function addRefreshToken(RefreshToken $token): static
    {
        if (!$this->refreshTokens->contains($token)) {
            $this->refreshTokens->add($token);
            $token->setUser($this);
        }
        return $this;
    }

    /** Retire un refresh token et libère sa relation utilisateur. */
    public function removeRefreshToken(RefreshToken $token): static
    {
        if ($this->refreshTokens->removeElement($token) && $token->getUser() === $this) {
            $token->setUser(null);
        }
        return $this;
    }

    /** @return Collection<int, ChirurgiePlanifiee> */
    public function getChirurgiesValidees(): Collection
    {
        return $this->chirurgiesValidees;
    }

    /** Lie une validation de chirurgie à son auteur pour conserver l'audit. */
    public function addChirurgieValidee(ChirurgiePlanifiee $chirurgie): static
    {
        if (!$this->chirurgiesValidees->contains($chirurgie)) {
            $this->chirurgiesValidees->add($chirurgie);
            $chirurgie->setValidePar($this);
        }
        return $this;
    }

    /** Retire une validation de la collection d'audit de l'utilisateur. */
    public function removeChirurgieValidee(ChirurgiePlanifiee $chirurgie): static
    {
        if ($this->chirurgiesValidees->removeElement($chirurgie) && $chirurgie->getValidePar() === $this) {
            $chirurgie->setValidePar(null);
        }
        return $this;
    }

    /** @return Collection<int, PreparationMateriel> */
    public function getPreparationsCochees(): Collection
    {
        return $this->preparationsCochees;
    }

    /** Lie une ligne cochée à son auteur afin de préserver la traçabilité. */
    public function addPreparationCochee(PreparationMateriel $preparation): static
    {
        if (!$this->preparationsCochees->contains($preparation)) {
            $this->preparationsCochees->add($preparation);
            $preparation->setCochePar($this);
        }
        return $this;
    }

    /** Retire une ligne cochée de la collection d'audit de l'utilisateur. */
    public function removePreparationCochee(PreparationMateriel $preparation): static
    {
        if ($this->preparationsCochees->removeElement($preparation) && $preparation->getCochePar() === $this) {
            $preparation->setCochePar(null);
        }
        return $this;
    }

    /** Remplace le hash réel par une empreinte dans la session afin de ne jamais l'y exposer. */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
