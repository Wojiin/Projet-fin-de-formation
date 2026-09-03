<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProgrammePlanificationInput;
use App\Dto\ProgrammePlanificationOutput;
use App\Service\AuthenticatedUserProvider;
use App\Service\ProgrammePlanificationService;

/**
 * Adapte la commande API de planification au service métier.
 *
 * @implements ProcessorInterface<ProgrammePlanificationInput, ProgrammePlanificationOutput>
 */
final readonly class ProgrammePlanificationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProgrammePlanificationService $planificationService,
        private AuthenticatedUserProvider $authenticatedUser,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ProgrammePlanificationOutput {
        if (!$data instanceof ProgrammePlanificationInput
            || null === $data->dateProgrammee
            || null === $data->salle
            || null === $data->chirurgienId
        ) {
            throw new \InvalidArgumentException('Un programme opératoire valide est attendu.');
        }

        $programme = $this->planificationService->plan(
            date: $data->dateProgrammee,
            room: $data->salle,
            surgeonId: $data->chirurgienId,
            modelIds: $data->chirurgieModeleIds,
            actor: $this->authenticatedUser->getUser()->getUserIdentifier(),
        );

        return ProgrammePlanificationOutput::fromProgramme($programme);
    }
}
