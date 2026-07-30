<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ChirurgiePlanifiee;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\PreparationMaterielInitializer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encadre l'écriture d'une chirurgie planifiée : calcule sa position initiale
 * et initialise obligatoirement sa préparation de matériel.
 */
final readonly class ChirurgiePlanifieeWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private PreparationMaterielInitializer $initializer,
        private ChirurgiePlanifieeRepository $repository,
    ) {
    }

    /**
     * Persiste la chirurgie après contrôle de sa liste de matériel et attribution
     * du prochain ordre disponible lors d'une création.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePlanifiee
    {
        if (!$data instanceof ChirurgiePlanifiee) {
            throw new \InvalidArgumentException('Une chirurgie planifiée est attendue.');
        }

        if (null === $data->getId()
            && null !== $data->getDateProgrammee()
            && null !== $data->getSalle()
            && null !== $data->getChirurgien()?->getId()
        ) {
            $maximum = array_reduce(
                $this->repository->findForProgrammeOperatoire(
                    date: $data->getDateProgrammee(),
                    salle: $data->getSalle(),
                    chirurgienId: $data->getChirurgien()->getId(),
                ),
                static fn (int $order, ChirurgiePlanifiee $chirurgie): int => max($order, $chirurgie->getOrdre() ?? 0),
                0,
            );
            $data->setOrdre($maximum + 1);
        }

        $this->initializer->findListe($data);
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        $this->initializer->initializeForChirurgie($data);

        return $result;
    }
}
