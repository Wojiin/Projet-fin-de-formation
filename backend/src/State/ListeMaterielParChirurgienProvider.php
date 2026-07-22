<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ListeMaterielRepository;

final readonly class ListeMaterielParChirurgienProvider implements ProviderInterface
{
    public function __construct(private ListeMaterielRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->findBy(['chirurgien' => $uriVariables['id']], ['intitule' => 'ASC']);
    }
}
