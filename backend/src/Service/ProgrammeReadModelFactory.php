<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeOperatoireResume;
use App\Entity\ChirurgiePlanifiee;

/** Assemble les projections de chirurgies en programmes opératoires agrégés. */
final readonly class ProgrammeReadModelFactory
{
    public function __construct(
        private ChirurgieReadModelFactory $chirurgieFactory,
        private PreparationProgressCalculator $progressCalculator,
    ) {
    }

    /**
     * @param list<ChirurgiePlanifiee> $chirurgies
     *
     * @return list<ProgrammeOperatoireResume>
     */
    public function createSummaries(array $chirurgies): array
    {
        $programmes = [];
        foreach ($chirurgies as $chirurgie) {
            $key = $this->programmeKey($chirurgie);
            $programmes[$key] ??= $this->newProgrammeData($chirurgie);
            $this->addChirurgie($programmes[$key], $chirurgie);
        }

        return array_values(array_map(
            static fn (array $programme): ProgrammeOperatoireResume => new ProgrammeOperatoireResume(
                date: $programme['date'],
                salle: $programme['salle'],
                chirurgien: $programme['chirurgien'],
                nombreChirurgies: $programme['nombreChirurgies'],
                nombreChirurgiesValidees: $programme['nombreChirurgiesValidees'],
                progressionPreparation: $programme['progressionPreparation'],
                creePar: $programme['creePar'],
            ),
            $programmes,
        ));
    }

    /**
     * @param list<ChirurgiePlanifiee> $chirurgies
     */
    public function createProgramme(array $chirurgies, bool $includeTechnicalSheets = false): ?ProgrammeOperatoire
    {
        if ([] === $chirurgies) {
            return null;
        }

        usort($chirurgies, static fn (ChirurgiePlanifiee $left, ChirurgiePlanifiee $right): int => [
            $left->getOrdre() ?? PHP_INT_MAX,
            $left->getId() ?? PHP_INT_MAX,
        ] <=> [
            $right->getOrdre() ?? PHP_INT_MAX,
            $right->getId() ?? PHP_INT_MAX,
        ]);

        $programme = $this->newProgrammeData($chirurgies[0]);
        $items = [];
        foreach ($chirurgies as $chirurgie) {
            $this->addChirurgie($programme, $chirurgie);
            $items[] = $this->chirurgieFactory->createProgrammeItem($chirurgie, $includeTechnicalSheets);
        }

        return new ProgrammeOperatoire(
            id: sprintf('%s-%s-%s', $programme['date'], $programme['salle'], $programme['chirurgien']['id']),
            date: $programme['date'],
            salle: $programme['salle'],
            chirurgien: $programme['chirurgien'],
            nombreChirurgies: $programme['nombreChirurgies'],
            nombreChirurgiesValidees: $programme['nombreChirurgiesValidees'],
            chirurgies: $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function newProgrammeData(ChirurgiePlanifiee $chirurgie): array
    {
        return [
            'date' => $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            'salle' => $chirurgie->getSalle() ?? '',
            'chirurgien' => $this->chirurgieFactory->surgeonData($chirurgie),
            'nombreChirurgies' => 0,
            'nombreChirurgiesValidees' => 0,
            'progressionPreparation' => $this->progressCalculator->emptyProgress(),
            'creeLe' => $chirurgie->getCreeLe(),
            'creePar' => $chirurgie->getCreePar(),
        ];
    }

    /**
     * @param array<string, mixed> $programme
     */
    private function addChirurgie(array &$programme, ChirurgiePlanifiee $chirurgie): void
    {
        if (null !== $chirurgie->getCreeLe()
            && (null === $programme['creeLe'] || $chirurgie->getCreeLe() < $programme['creeLe'])
        ) {
            $programme['creeLe'] = $chirurgie->getCreeLe();
            $programme['creePar'] = $chirurgie->getCreePar();
        }

        ++$programme['nombreChirurgies'];
        $programme['nombreChirurgiesValidees'] += $chirurgie->isValide() ? 1 : 0;
        $programme['progressionPreparation'] = $this->progressCalculator->merge(
            $programme['progressionPreparation'],
            $this->progressCalculator->calculate($chirurgie->getPreparationsMateriel()),
        );
    }

    private function programmeKey(ChirurgiePlanifiee $chirurgie): string
    {
        return implode('|', [
            $chirurgie->getDateProgrammee()?->format('Y-m-d'),
            $chirurgie->getSalle(),
            $chirurgie->getChirurgien()?->getId(),
        ]);
    }
}
