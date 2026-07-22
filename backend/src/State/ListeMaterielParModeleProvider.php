<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ListeMaterielRepository;

final readonly class ListeMaterielParModeleProvider implements ProviderInterface
{
    public function __construct(private ListeMaterielRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->findBy(['chirurgieModele' => $uriVariables['id']], ['intitule' => 'ASC']);
    }
}
