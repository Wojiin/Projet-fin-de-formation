<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ChirurgiePlanifiee;
use App\Service\PreparationMaterielInitializer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ChirurgiePlanifieeWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private PreparationMaterielInitializer $initializer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePlanifiee
    {
        if (!$data instanceof ChirurgiePlanifiee) {
            throw new \InvalidArgumentException('Une chirurgie planifiée est attendue.');
        }

        $this->initializer->findListe($data);
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        $this->initializer->initializeForChirurgie($data);

        return $result;
    }
}
