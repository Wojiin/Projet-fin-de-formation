<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\ReferenceDeletionGuard;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Protège les suppressions de référentiels et d'objets métier encore reliés à
 * des données opérationnelles ou à une trace de sécurité.
 */
final readonly class ReferenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private ReferenceDeletionGuard $deletionGuard,
    ) {
    }

    /**
     * Refuse la suppression lorsque l'objet est utilisé, sinon délègue au
     * processeur Doctrine standard.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->deletionGuard->assertCanDelete($data);

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
