<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Specialite;
use App\Service\SpecialiteDeletionService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Supprime une spécialité en réaffectant ses références à « Sans spécialité »
 * afin de préserver l'intégrité des référentiels.
 */
final readonly class SpecialiteDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private SpecialiteDeletionService $deletionService,
    ) {
    }

    /**
     * Protège la spécialité par défaut, migre les dépendances puis supprime la
     * spécialité demandée.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Specialite) {
            throw new \InvalidArgumentException('Une spécialité est attendue.');
        }

        $this->deletionService->reassignReferences($data);

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
