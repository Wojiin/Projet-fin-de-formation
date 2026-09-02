<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PreparationMaterielInput;
use App\Entity\PreparationMateriel;
use App\Repository\PreparationMaterielRepository;
use App\Service\AuthenticatedUserProvider;
use App\Service\PreparationMaterielService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Change l'état d'une ligne de préparation en conservant sa traçabilité et en
 * interdisant toute modification après validation de la chirurgie.
 */
final readonly class PreparationMaterielCocherProcessor implements ProcessorInterface
{
    public function __construct(
        private PreparationMaterielRepository $repository,
        private PreparationMaterielService $preparationService,
        private AuthenticatedUserProvider $authenticatedUser,
    ) {
    }

    /**
     * Coche ou décoche une préparation, renseigne ou efface son auteur et sa date,
     * puis enregistre la transition.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PreparationMateriel
    {
        if (!$data instanceof PreparationMaterielInput || (null === $data->coche && null === $data->absent)) {
            throw new \InvalidArgumentException('Un état de préparation valide est attendu.');
        }

        $preparation = $this->repository->find((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Préparation de matériel introuvable.');

        return $this->preparationService->updateState(
            $preparation,
            $data->coche ?? false,
            $data->absent ?? false,
            $this->authenticatedUser->getUser(),
        );
    }
}
