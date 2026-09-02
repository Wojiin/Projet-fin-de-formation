<?php

namespace App\Service;

use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\ListeMateriel;
use App\Entity\Materiel;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgiePlanifieeRepository;

/** Refuse la suppression des référentiels encore reliés à des données métier. */
final readonly class ReferenceDeletionGuard
{
    public function __construct(private ChirurgiePlanifieeRepository $chirurgieRepository)
    {
    }

    public function assertCanDelete(mixed $resource): void
    {
        $used = match (true) {
            $resource instanceof Chirurgien => !$resource->getListesMateriel()->isEmpty() || !$resource->getChirurgiesPlanifiees()->isEmpty(),
            $resource instanceof ChirurgieModele => !$resource->getFichesTechniques()->isEmpty() || !$resource->getListesMateriel()->isEmpty() || !$resource->getChirurgiesPlanifiees()->isEmpty(),
            $resource instanceof Materiel => !$resource->getListesMateriel()->isEmpty() || !$resource->getPreparationsMateriel()->isEmpty(),
            $resource instanceof ListeMateriel => null !== $this->chirurgieRepository->findOneBy([
                'chirurgien' => $resource->getChirurgien(),
                'chirurgieModele' => $resource->getChirurgieModele(),
            ]),
            $resource instanceof ChirurgiePlanifiee => $resource->isValide(),
            $resource instanceof User => !$resource->getRefreshTokens()->isEmpty() || !$resource->getChirurgiesValidees()->isEmpty() || !$resource->getPreparationsCochees()->isEmpty(),
            default => false,
        };

        if ($used) {
            throw new ApiProblemException(
                'RESOURCE_ALREADY_USED',
                'Cette ressource ne peut pas être supprimée car elle est déjà utilisée.',
            );
        }
    }
}
