<?php

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_token')]
/** Entité de persistance des refresh tokens liés à un utilisateur ChirOrg. */
class RefreshToken implements RefreshTokenInterface
{
    // Les méthodes implémentent le contrat du bundle de refresh token et relient le token à son utilisateur.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $valid = null;

    #[ORM\ManyToOne(inversedBy: 'refreshTokens')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Construit un refresh token expirant après le délai configuré et le rattache
     * à l'utilisateur ChirOrg quand son type permet de conserver cette relation.
     */
    public static function createForUserWithTtl(string $refreshToken, UserInterface $user, int $ttl): static
    {
        $valid = new DateTime();
        $valid->modify(sprintf('%+d seconds', $ttl));

        $token = (new static())
            ->setRefreshToken($refreshToken)
            ->setUsername($user->getUserIdentifier())
            ->setValid($valid);

        if ($user instanceof User) {
            $token->setUser($user);
        }

        return $token;
    }

    /** Retourne le token sous forme texte lorsque le bundle doit l'émettre ou le comparer. */
    public function __toString(): string
    {
        return $this->refreshToken ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setRefreshToken(string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setValid(DateTimeInterface $valid): static
    {
        $this->valid = $valid;

        return $this;
    }

    public function getValid(): ?DateTimeInterface
    {
        return $this->valid;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    /** Indique si la date de validité du refresh token n'est pas dépassée. */
    public function isValid(): bool
    {
        return null !== $this->valid && $this->valid >= new DateTime();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
