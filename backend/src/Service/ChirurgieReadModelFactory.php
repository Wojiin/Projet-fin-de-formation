<?php

namespace App\Service;

use App\Dto\ChirurgiePreparation;
use App\Dto\ChirurgieVueFinale;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\FicheTechnique;
use App\Entity\PreparationMateriel;
use DateTimeInterface;

/** Projette les entités chirurgie vers les contrats de lecture exposés par l'API. */
final readonly class ChirurgieReadModelFactory
{
    public function __construct(private PreparationProgressCalculator $progressCalculator)
    {
    }

    public function createPreparation(ChirurgiePlanifiee $chirurgie): ChirurgiePreparation
    {
        $progress = $this->progressCalculator->calculate($chirurgie->getPreparationsMateriel());

        return new ChirurgiePreparation(
            id: $chirurgie->getId() ?? 0,
            dateProgrammee: $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            salle: $chirurgie->getSalle() ?? '',
            ordre: $chirurgie->getOrdre(),
            valide: $chirurgie->isValide(),
            etatValidation: $this->progressCalculator->validationState($chirurgie->isValide(), $progress),
            chirurgien: $this->surgeonData($chirurgie),
            chirurgieModele: $this->modelData($chirurgie),
            preparationsMateriel: $this->preparationRows($chirurgie),
            progressionPreparation: $progress,
        );
    }

    public function createFinalView(ChirurgiePlanifiee $chirurgie): ChirurgieVueFinale
    {
        $validatedBy = $chirurgie->getValidePar();

        return new ChirurgieVueFinale(
            id: $chirurgie->getId() ?? 0,
            dateProgrammee: $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            salle: $chirurgie->getSalle() ?? '',
            ordre: $chirurgie->getOrdre(),
            valide: $chirurgie->isValide(),
            valideLe: $chirurgie->getValideLe()?->format(DateTimeInterface::ATOM),
            validePar: null === $validatedBy ? null : [
                'id' => $validatedBy->getId(),
                'email' => $validatedBy->getEmail(),
            ],
            chirurgien: $this->surgeonData($chirurgie),
            chirurgieModele: $this->modelData($chirurgie),
            materielsValides: $this->validatedMaterialRows($chirurgie),
            ficheTechnique: $this->technicalSheetRows($chirurgie),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createProgrammeItem(ChirurgiePlanifiee $chirurgie, bool $includeTechnicalSheets): array
    {
        $progress = $this->progressCalculator->calculate($chirurgie->getPreparationsMateriel());
        $data = [
            'id' => $chirurgie->getId() ?? 0,
            'dateProgrammee' => $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            'ordre' => $chirurgie->getOrdre(),
            'valide' => $chirurgie->isValide(),
            'etatValidation' => $this->progressCalculator->validationState($chirurgie->isValide(), $progress),
            'valideLe' => $chirurgie->getValideLe()?->format(DateTimeInterface::ATOM),
            'creeLe' => $chirurgie->getCreeLe()?->format(DateTimeInterface::ATOM),
            'creePar' => $chirurgie->getCreePar(),
            'modifieLe' => $chirurgie->getModifieLe()?->format(DateTimeInterface::ATOM),
            'modifiePar' => $chirurgie->getModifiePar(),
            'chirurgieModele' => $this->modelData($chirurgie),
            'progressionPreparation' => $progress,
            'preparationsMateriel' => $this->preparationRows($chirurgie),
        ];

        if ($includeTechnicalSheets) {
            $data['fichesTechniques'] = $this->technicalSheetRows($chirurgie);
        }

        return $data;
    }

    /**
     * @return array{id: int|null, prenom: string|null, nom: string|null}
     */
    public function surgeonData(ChirurgiePlanifiee $chirurgie): array
    {
        $surgeon = $chirurgie->getChirurgien();

        return [
            'id' => $surgeon?->getId(),
            'prenom' => $surgeon?->getPrenom(),
            'nom' => $surgeon?->getNom(),
        ];
    }

    /**
     * @return array{id: int|null, intitule: string|null}
     */
    private function modelData(ChirurgiePlanifiee $chirurgie): array
    {
        $model = $chirurgie->getChirurgieModele();

        return ['id' => $model?->getId(), 'intitule' => $model?->getIntitule()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function preparationRows(ChirurgiePlanifiee $chirurgie): array
    {
        $rows = [];
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            $rows[] = $this->preparationData($preparation);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function preparationData(PreparationMateriel $preparation): array
    {
        $material = $preparation->getMateriel();

        return [
            'id' => $preparation->getId(),
            'coche' => $preparation->isCoche(),
            'absent' => $preparation->isAbsent(),
            'cocheLe' => $preparation->getCocheLe()?->format(DateTimeInterface::ATOM),
            'materiel' => [
                'id' => $material?->getId(),
                'intitule' => $material?->getIntitule(),
                'adresse' => $material?->getAdresse(),
                'typeMateriel' => $material?->getTypeMateriel(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validatedMaterialRows(ChirurgiePlanifiee $chirurgie): array
    {
        $rows = [];
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            if (!$preparation->isCoche()) {
                continue;
            }

            $material = $preparation->getMateriel();
            $rows[] = [
                'id' => $material?->getId(),
                'intitule' => $material?->getIntitule(),
                'adresse' => $material?->getAdresse(),
                'typeMateriel' => $material?->getTypeMateriel(),
                'cocheLe' => $preparation->getCocheLe()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function technicalSheetRows(ChirurgiePlanifiee $chirurgie): array
    {
        $sheets = $chirurgie->getChirurgieModele()?->getFichesTechniques()->toArray() ?? [];
        usort($sheets, static fn (FicheTechnique $left, FicheTechnique $right): int => [
            $left->getOrdre() ?? PHP_INT_MAX,
            $left->getId() ?? PHP_INT_MAX,
        ] <=> [
            $right->getOrdre() ?? PHP_INT_MAX,
            $right->getId() ?? PHP_INT_MAX,
        ]);

        return array_map(static fn (FicheTechnique $sheet): array => [
            'id' => $sheet->getId(),
            'titre' => $sheet->getTitre(),
            'description' => $sheet->getDescription(),
            'lienImage' => $sheet->getLienImage(),
            'ordre' => $sheet->getOrdre(),
        ], $sheets);
    }
}
