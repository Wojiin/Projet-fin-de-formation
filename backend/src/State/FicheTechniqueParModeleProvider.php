<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\FicheTechniqueRepository;

final readonly class FicheTechniqueParModeleProvider implements ProviderInterface
{
    public function __construct(private FicheTechniqueRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->findBy(
            ['chirurgieModele' => $uriVariables['id']],
            ['ordre' => 'ASC'],
        );
    }
}
