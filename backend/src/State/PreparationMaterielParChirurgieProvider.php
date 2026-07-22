<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\PreparationMaterielRepository;

final readonly class PreparationMaterielParChirurgieProvider implements ProviderInterface
{
    public function __construct(private PreparationMaterielRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->findBy(['chirurgiePlanifiee' => $uriVariables['id']], ['id' => 'ASC']);
    }
}
