<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\PasswordPolicy;
use App\State\PasswordChangeProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [
    new Post(
        uriTemplate: '/me/password',
        status: Response::HTTP_NO_CONTENT,
        read: false,
        output: false,
        processor: PasswordChangeProcessor::class,
        security: "is_granted('ROLE_USER')",
        openapi: new OpenApiOperation(
            summary: 'Modifier mon mot de passe',
            description: 'Vérifie le mot de passe actuel puis enregistre le nouveau mot de passe chiffré.',
        ),
    ),
])]
/** Commande sécurisée de changement du mot de passe de l'utilisateur connecté. */
final class PasswordChangeInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le mot de passe actuel est obligatoire.')]
        public readonly string $currentPassword = '',

        #[Assert\NotBlank(message: 'Le nouveau mot de passe est obligatoire.')]
        #[Assert\Length(
            min: PasswordPolicy::MIN_LENGTH,
            max: PasswordPolicy::MAX_LENGTH,
            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
            maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
        )]
        #[Assert\Regex(pattern: PasswordPolicy::REGEX, message: PasswordPolicy::MESSAGE)]
        public readonly string $newPassword = '',

        #[Assert\NotBlank(message: 'La confirmation du mot de passe est obligatoire.')]
        #[Assert\EqualTo(propertyPath: 'newPassword', message: 'La confirmation ne correspond pas au nouveau mot de passe.')]
        public readonly string $newPasswordConfirmation = '',
    ) {
    }
}
