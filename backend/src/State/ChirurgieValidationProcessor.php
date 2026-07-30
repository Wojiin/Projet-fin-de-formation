<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use App\Service\ChirurgieValidationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Délègue la validation d'une chirurgie au service après identification de l'utilisateur connecté. */
final readonly class ChirurgieValidationProcessor implements ProcessorInterface
{
    public function __construct(
        private ChirurgieValidationService $validationService,
        private Security $security,
    ) {
    }

    /** Vérifie le contexte de sécurité puis applique la règle de validation métier. */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePlanifiee
    {
        if (!$data instanceof ChirurgiePlanifiee) {
            throw new \InvalidArgumentException('Une chirurgie planifiée est attendue.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        return $this->validationService->validate($data, $user);
    }
}
