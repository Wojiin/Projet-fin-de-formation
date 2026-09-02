<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Centralise l'accès typé à l'utilisateur authentifié pour les adaptateurs API. */
final readonly class AuthenticatedUserProvider
{
    public function __construct(private Security $security)
    {
    }

    public function getUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        return $user;
    }
}
