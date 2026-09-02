<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ChirurgiePreparation;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\PreparationMaterielInitializer;
use DateTimeInterface;
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

        $preparations = [];
        $coches = 0;
        $absents = 0;
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            $materiel = $preparation->getMateriel();
            $coches += $preparation->isCoche() ? 1 : 0;
            $absents += $preparation->isAbsent() ? 1 : 0;
            $preparations[] = [
                'id' => $preparation->getId(),
                'coche' => $preparation->isCoche(),
                'absent' => $preparation->isAbsent(),
                'cocheLe' => $preparation->getCocheLe()?->format(DateTimeInterface::ATOM),
                'materiel' => [
                    'id' => $materiel?->getId(),
                    'intitule' => $materiel?->getIntitule(),
                    'adresse' => $materiel?->getAdresse(),
                    'typeMateriel' => $materiel?->getTypeMateriel(),
                ],
            ];
        }

        $total = count($preparations);
        $traites = $coches + $absents;
        $chirurgien = $chirurgie->getChirurgien();
        $modele = $chirurgie->getChirurgieModele();

        return new ChirurgiePreparation(
            id: $chirurgie->getId() ?? 0,
            dateProgrammee: $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            salle: $chirurgie->getSalle() ?? '',
            ordre: $chirurgie->getOrdre(),
            valide: $chirurgie->isValide(),
            etatValidation: $chirurgie->isValide() ? 'VALIDEE' : ($total > 0 && $traites === $total && $absents > 0 ? 'VALIDATION_PARTIELLE' : 'EN_PREPARATION'),
            chirurgien: ['id' => $chirurgien?->getId(), 'prenom' => $chirurgien?->getPrenom(), 'nom' => $chirurgien?->getNom()],
            chirurgieModele: ['id' => $modele?->getId(), 'intitule' => $modele?->getIntitule()],
            preparationsMateriel: $preparations,
            progressionPreparation: ['total' => $total, 'coches' => $coches, 'absents' => $absents, 'traites' => $traites, 'complete' => $total > 0 && $total === $traites],
        );
    }
}
