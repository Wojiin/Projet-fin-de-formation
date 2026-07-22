<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeOperatoireResume;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\PreparationMateriel;
use App\Repository\ChirurgiePlanifieeRepository;
use DateTimeInterface;

final readonly class ProgrammeOperatoireService
{
    public function __construct(private ChirurgiePlanifieeRepository $repository)
    {
    }

    /** @return list<ProgrammeOperatoireResume> */
    public function getProgrammes(?DateTimeInterface $date = null, ?DateTimeInterface $dateDebut = null, ?DateTimeInterface $dateFin = null, ?string $salle = null, ?int $chirurgienId = null): array
    {
        $programmes = [];
        foreach ($this->repository->findForProgrammeOperatoire($date, $dateDebut, $dateFin, $salle, $chirurgienId) as $chirurgie) {
            $key = $this->programmeKey($chirurgie);
            $programmes[$key] ??= $this->newProgrammeData($chirurgie);
            $this->addChirurgieToProgramme($programmes[$key], $chirurgie);
        }

        return array_values(array_map(
            static fn (array $programme): ProgrammeOperatoireResume => new ProgrammeOperatoireResume(
                date: $programme['date'],
                salle: $programme['salle'],
                chirurgien: $programme['chirurgien'],
                nombreChirurgies: $programme['nombreChirurgies'],
                nombreChirurgiesValidees: $programme['nombreChirurgiesValidees'],
                progressionPreparation: $programme['progressionPreparation'],
            ),
            $programmes,
        ));
    }

    public function getProgramme(DateTimeInterface $date, string $salle, int $chirurgienId, bool $vueFinale = false): ?ProgrammeOperatoire
    {
        $chirurgies = $this->repository->findForProgrammeOperatoire(
            date: $date,
            salle: $salle,
            chirurgienId: $chirurgienId,
            valide: $vueFinale ? true : null,
            withFichesTechniques: $vueFinale,
        );

        if ([] === $chirurgies) {
            return null;
        }

        $programme = $this->newProgrammeData($chirurgies[0]);
        $items = [];
        foreach ($chirurgies as $chirurgie) {
            $this->addChirurgieToProgramme($programme, $chirurgie);
            $items[] = $this->chirurgieData($chirurgie, $vueFinale);
        }

        return new ProgrammeOperatoire(
            id: $this->programmeId($programme),
            date: $programme['date'],
            salle: $programme['salle'],
            chirurgien: $programme['chirurgien'],
            nombreChirurgies: $programme['nombreChirurgies'],
            nombreChirurgiesValidees: $programme['nombreChirurgiesValidees'],
            chirurgies: $items,
        );
    }

    /** @return array<string, mixed> */
    private function newProgrammeData(ChirurgiePlanifiee $chirurgie): array
    {
        $chirurgien = $chirurgie->getChirurgien();

        return [
            'date' => $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            'salle' => $chirurgie->getSalle() ?? '',
            'chirurgien' => ['id' => $chirurgien?->getId(), 'prenom' => $chirurgien?->getPrenom(), 'nom' => $chirurgien?->getNom()],
            'nombreChirurgies' => 0,
            'nombreChirurgiesValidees' => 0,
            'progressionPreparation' => ['total' => 0, 'coches' => 0, 'complete' => false],
        ];
    }

    /** @param array<string, mixed> $programme */
    private function addChirurgieToProgramme(array &$programme, ChirurgiePlanifiee $chirurgie): void
    {
        ++$programme['nombreChirurgies'];
        if ($chirurgie->isValide()) {
            ++$programme['nombreChirurgiesValidees'];
        }

        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            ++$programme['progressionPreparation']['total'];
            if ($preparation->isCoche()) {
                ++$programme['progressionPreparation']['coches'];
            }
        }
        $progression = $programme['progressionPreparation'];
        $programme['progressionPreparation']['complete'] = $progression['total'] > 0 && $progression['total'] === $progression['coches'];
    }

    /** @return array<string, mixed> */
    private function chirurgieData(ChirurgiePlanifiee $chirurgie, bool $includeFichesTechniques): array
    {
        $preparations = [];
        $coches = 0;
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            $preparations[] = $this->preparationData($preparation);
            $coches += $preparation->isCoche() ? 1 : 0;
        }
        $modele = $chirurgie->getChirurgieModele();
        $data = [
            'id' => $chirurgie->getId() ?? 0,
            'heure' => $chirurgie->getDateProgrammee()?->format('H:i') ?? '',
            'dateProgrammee' => $chirurgie->getDateProgrammee()?->format(DateTimeInterface::ATOM) ?? '',
            'ordre' => $chirurgie->getOrdre(),
            'valide' => $chirurgie->isValide(),
            'valideLe' => $chirurgie->getValideLe()?->format(DateTimeInterface::ATOM),
            'chirurgieModele' => ['id' => $modele?->getId(), 'intitule' => $modele?->getIntitule()],
            'progressionPreparation' => ['total' => count($preparations), 'coches' => $coches, 'complete' => count($preparations) > 0 && count($preparations) === $coches],
            'preparationsMateriel' => $preparations,
        ];

        if ($includeFichesTechniques) {
            $data['fichesTechniques'] = array_map(static fn ($fiche): array => [
                'id' => $fiche->getId(), 'titre' => $fiche->getTitre(), 'description' => $fiche->getDescription(), 'lienImage' => $fiche->getLienImage(), 'ordre' => $fiche->getOrdre(),
            ], $modele?->getFichesTechniques()->toArray() ?? []);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function preparationData(PreparationMateriel $preparation): array
    {
        $materiel = $preparation->getMateriel();

        return [
            'id' => $preparation->getId(),
            'coche' => $preparation->isCoche(),
            'cocheLe' => $preparation->getCocheLe()?->format(DateTimeInterface::ATOM),
            'materiel' => ['id' => $materiel?->getId(), 'intitule' => $materiel?->getIntitule(), 'adresse' => $materiel?->getAdresse(), 'typeMateriel' => $materiel?->getTypeMateriel()],
        ];
    }

    private function programmeKey(ChirurgiePlanifiee $chirurgie): string
    {
        return implode('|', [$chirurgie->getDateProgrammee()?->format('Y-m-d'), $chirurgie->getSalle(), $chirurgie->getChirurgien()?->getId()]);
    }

    /** @param array<string, mixed> $programme */
    private function programmeId(array $programme): string
    {
        return sprintf('%s-%s-%s', $programme['date'], $programme['salle'], $programme['chirurgien']['id']);
    }
}
