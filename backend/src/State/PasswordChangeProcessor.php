<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PasswordChangeInput;
use App\Service\AuthenticatedUserProvider;
use App\Service\UserPasswordService;

/** Modifie exclusivement le mot de passe du compte porté par le JWT courant. */
final readonly class PasswordChangeProcessor implements ProcessorInterface
{
    public function __construct(
        private AuthenticatedUserProvider $authenticatedUser,
        private UserPasswordService $passwordService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof PasswordChangeInput) {
            throw new \InvalidArgumentException('Une demande de changement de mot de passe est attendue.');
        }

        $user = $this->authenticatedUser->getUser();
        $this->passwordService->changePassword($user, $data->currentPassword, $data->newPassword);
    }
}
