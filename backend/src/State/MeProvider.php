<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Service\AuthenticatedUserProvider;

/** Expose le profil de l'utilisateur courant sans permettre d'usurper son identifiant. */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(private AuthenticatedUserProvider $authenticatedUser)
    {
    }

    /** Retourne l'utilisateur authentifié ou refuse explicitement l'accès anonyme. */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): User
    {
        return $this->authenticatedUser->getUser();
    }
}
