<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ChirurgiePlanifiee;
use App\Service\AuthenticatedUserProvider;
use App\Service\ChirurgieValidationService;

/** Délègue la validation d'une chirurgie au service après identification de l'utilisateur connecté. */
final readonly class ChirurgieValidationProcessor implements ProcessorInterface
{
    public function __construct(
        private ChirurgieValidationService $validationService,
        private AuthenticatedUserProvider $authenticatedUser,
    ) {
    }

    /** Vérifie le contexte de sécurité puis applique la règle de validation métier. */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePlanifiee
    {
        if (!$data instanceof ChirurgiePlanifiee) {
            throw new \InvalidArgumentException('Une chirurgie planifiée est attendue.');
        }

        return $this->validationService->validate($data, $this->authenticatedUser->getUser());
    }
}
