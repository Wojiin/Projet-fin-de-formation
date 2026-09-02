<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\ApiProblemException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Porte les règles de chiffrement et de changement des mots de passe utilisateur. */
final readonly class UserPasswordService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function hashPlainPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();
        if (null === $plainPassword) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new ApiProblemException(
                'CURRENT_PASSWORD_INVALID',
                'Le mot de passe actuel est incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->passwordHasher->isPasswordValid($user, $newPassword)) {
            throw new ApiProblemException(
                'PASSWORD_UNCHANGED',
                'Le nouveau mot de passe doit être différent du mot de passe actuel.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
    }
}
