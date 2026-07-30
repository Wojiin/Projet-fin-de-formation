<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Expose le profil de l'utilisateur courant sans permettre d'usurper son identifiant. */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    /** Retourne l'utilisateur authentifié ou refuse explicitement l'accès anonyme. */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        return $user;
    }
}
