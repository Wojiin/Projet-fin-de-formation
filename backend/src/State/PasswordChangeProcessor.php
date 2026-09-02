<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PasswordChangeInput;
use App\Entity\User;
use App\Exception\ApiProblemException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Modifie exclusivement le mot de passe du compte porté par le JWT courant. */
final readonly class PasswordChangeProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof PasswordChangeInput) {
            throw new \InvalidArgumentException('Une demande de changement de mot de passe est attendue.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data->currentPassword)) {
            throw new ApiProblemException(
                'CURRENT_PASSWORD_INVALID',
                'Le mot de passe actuel est incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->passwordHasher->isPasswordValid($user, $data->newPassword)) {
            throw new ApiProblemException(
                'PASSWORD_UNCHANGED',
                'Le nouveau mot de passe doit être différent du mot de passe actuel.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $data->newPassword));
        $this->entityManager->flush();
    }
}
