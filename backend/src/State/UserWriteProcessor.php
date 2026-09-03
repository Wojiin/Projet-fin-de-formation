<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\UserPasswordService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Garantit qu'aucun mot de passe en clair n'est persisté pour un utilisateur. */
final readonly class UserWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private UserPasswordService $passwordService,
    ) {
    }

    /**
     * Exige un mot de passe à la création, chiffre toute nouvelle valeur puis
     * délègue la persistance à Doctrine.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if (!$data instanceof User) {
            throw new \InvalidArgumentException('Un utilisateur est attendu.');
        }

        $plainPassword = $data->getPlainPassword();
        if ($operation instanceof Post && null === $plainPassword) {
            throw new UnprocessableEntityHttpException('Le mot de passe est obligatoire à la création.');
        }

        $this->passwordService->hashPlainPassword($data);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
