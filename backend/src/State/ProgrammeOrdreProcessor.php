<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeOrdreInput;
use App\Service\AuthenticatedUserProvider;
use App\Service\ProgrammeOrderingService;
use App\Service\ProgrammeReferenceResolver;

/**
 * Adapte la commande API de réordonnancement au service métier.
 *
 * @implements ProcessorInterface<ProgrammeOrdreInput, ProgrammeOperatoire>
 */
final readonly class ProgrammeOrdreProcessor implements ProcessorInterface
{
    public function __construct(
        private ProgrammeReferenceResolver $referenceResolver,
        private ProgrammeOrderingService $orderingService,
        private AuthenticatedUserProvider $authenticatedUser,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ProgrammeOperatoire {
        if (!$data instanceof ProgrammeOrdreInput) {
            throw new \InvalidArgumentException('Une liste ordonnée de chirurgies est attendue.');
        }

        return $this->orderingService->reorder(
            $this->referenceResolver->resolve($uriVariables),
            $data->chirurgieIds,
            $this->authenticatedUser->getUser()->getUserIdentifier(),
        );
    }
}
