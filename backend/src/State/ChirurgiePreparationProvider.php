<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ChirurgiePreparation;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\ChirurgieReadModelFactory;
use App\Service\PreparationMaterielInitializer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fournit la checklist de préparation d'une chirurgie et la crée à la première
 * consultation si elle n'a pas encore été matérialisée.
 */
final readonly class ChirurgiePreparationProvider implements ProviderInterface
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private PreparationMaterielInitializer $initializer,
        private ChirurgieReadModelFactory $readModelFactory,
    ) {
    }

    /**
     * Charge la chirurgie, garantit ses lignes de préparation puis calcule la progression.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePreparation
    {
        $id = (int) ($uriVariables['id'] ?? 0);
        $chirurgie = $this->repository->findPreparationData($id)
            ?? throw new NotFoundHttpException('Chirurgie planifiée introuvable.');

        if ($chirurgie->getPreparationsMateriel()->isEmpty()) {
            $this->initializer->initializeForChirurgie($chirurgie);
            $chirurgie = $this->repository->findPreparationData($id) ?? $chirurgie;
        }

        return $this->readModelFactory->createPreparation($chirurgie);
    }
}
