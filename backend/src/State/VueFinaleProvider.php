<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ChirurgieVueFinale;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\ChirurgieReadModelFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Produit la synthèse finale d'une chirurgie validée, avec les matériels prêts
 * et les fiches techniques de son modèle.
 */
final readonly class VueFinaleProvider implements ProviderInterface
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private ChirurgieReadModelFactory $readModelFactory,
    ) {
    }

    /**
     * Refuse l'accès avant validation puis projette les données nécessaires à la
     * vue finale en lecture seule.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChirurgieVueFinale
    {
        $chirurgie = $this->repository->findVueFinaleData((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Chirurgie planifiée introuvable.');

        if (!$chirurgie->isValide()) {
            throw new ApiProblemException('CHIRURGIE_NON_VALIDEE', 'La vue finale est disponible uniquement après validation.');
        }

        return $this->readModelFactory->createFinalView($chirurgie);
    }
}
