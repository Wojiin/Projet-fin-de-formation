<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ChirurgiePreparation;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\PreparationMaterielInitializer;
use DateTimeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ChirurgiePreparationProvider implements ProviderInterface
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private PreparationMaterielInitializer $initializer,
    ) {
    }

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
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            $materiel = $preparation->getMateriel();
            $coches += $preparation->isCoche() ? 1 : 0;
            $preparations[] = [
                'id' => $preparation->getId(),
                'coche' => $preparation->isCoche(),
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
        $chirurgien = $chirurgie->getChirurgien();
        $modele = $chirurgie->getChirurgieModele();

        return new ChirurgiePreparation(
            id: $chirurgie->getId() ?? 0,
            dateProgrammee: $chirurgie->getDateProgrammee()?->format(DateTimeInterface::ATOM) ?? '',
            salle: $chirurgie->getSalle() ?? '',
            ordre: $chirurgie->getOrdre(),
            valide: $chirurgie->isValide(),
            chirurgien: ['id' => $chirurgien?->getId(), 'prenom' => $chirurgien?->getPrenom(), 'nom' => $chirurgien?->getNom()],
            chirurgieModele: ['id' => $modele?->getId(), 'intitule' => $modele?->getIntitule()],
            preparationsMateriel: $preparations,
            progressionPreparation: ['total' => $total, 'coches' => $coches, 'complete' => $total > 0 && $total === $coches],
        );
    }
}
