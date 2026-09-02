<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeOperatoireResume;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\PreparationMateriel;
use App\Repository\ChirurgiePlanifieeRepository;
use DateTimeInterface;

/**
 * Agrège les chirurgies planifiées sous la forme de programmes opératoires
 * consommables par l'API, avec leurs compteurs et leur progression matériel.
 */
final readonly class ProgrammeOperatoireService
{
    public function __construct(private ChirurgiePlanifieeRepository $repository)
    {
    }

    /**
     * Construit les résumés de programmes selon les filtres de date, salle et chirurgien.
     *
     * @return list<ProgrammeOperatoireResume>
     */
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
                creePar: $programme['creePar'],
            ),
            $programmes,
        ));
    }

    /**
     * Construit le détail ordonné d'un programme unique.
     * Le mode vue finale limite la lecture aux chirurgies validées et à leurs fiches.
     */
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

        usort($chirurgies, static function (ChirurgiePlanifiee $left, ChirurgiePlanifiee $right): int {
            $orderComparison = ($left->getOrdre() ?? PHP_INT_MAX) <=> ($right->getOrdre() ?? PHP_INT_MAX);

            return 0 !== $orderComparison
                ? $orderComparison
                : ($left->getId() ?? PHP_INT_MAX) <=> ($right->getId() ?? PHP_INT_MAX);
        });

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

    /**
     * Initialise l'agrégat technique utilisé pour compter les chirurgies et préparations.
     *
     * @return array<string, mixed>
     */
    private function newProgrammeData(ChirurgiePlanifiee $chirurgie): array
    {
        $chirurgien = $chirurgie->getChirurgien();

        return [
            'date' => $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            'salle' => $chirurgie->getSalle() ?? '',
            'chirurgien' => ['id' => $chirurgien?->getId(), 'prenom' => $chirurgien?->getPrenom(), 'nom' => $chirurgien?->getNom()],
            'nombreChirurgies' => 0,
            'nombreChirurgiesValidees' => 0,
            'progressionPreparation' => ['total' => 0, 'coches' => 0, 'absents' => 0, 'traites' => 0, 'complete' => false],
            'creeLe' => $chirurgie->getCreeLe(),
            'creePar' => $chirurgie->getCreePar(),
        ];
    }

    /**
     * Ajoute une chirurgie aux compteurs d'un programme sans construire son détail.
     *
     * @param array<string, mixed> $programme
     */
    private function addChirurgieToProgramme(array &$programme, ChirurgiePlanifiee $chirurgie): void
    {
        if (null !== $chirurgie->getCreeLe()
            && (null === $programme['creeLe'] || $chirurgie->getCreeLe() < $programme['creeLe'])
        ) {
            $programme['creeLe'] = $chirurgie->getCreeLe();
            $programme['creePar'] = $chirurgie->getCreePar();
        }

        ++$programme['nombreChirurgies'];
        if ($chirurgie->isValide()) {
            ++$programme['nombreChirurgiesValidees'];
        }

        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            ++$programme['progressionPreparation']['total'];
            if ($preparation->isCoche()) {
                ++$programme['progressionPreparation']['coches'];
            }
            if ($preparation->isAbsent()) {
                ++$programme['progressionPreparation']['absents'];
            }
        }
        $progression = $programme['progressionPreparation'];
        $programme['progressionPreparation']['traites'] = $progression['coches'] + $progression['absents'];
        $programme['progressionPreparation']['complete'] = $progression['total'] > 0 && $progression['total'] === $programme['progressionPreparation']['traites'];
    }

    /**
     * Transforme une chirurgie en représentation API et, si demandé, joint ses fiches.
     *
     * @return array<string, mixed>
     */
    private function chirurgieData(ChirurgiePlanifiee $chirurgie, bool $includeFichesTechniques): array
    {
        $preparations = [];
        $coches = 0;
        $absents = 0;
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            $preparations[] = $this->preparationData($preparation);
            $coches += $preparation->isCoche() ? 1 : 0;
            $absents += $preparation->isAbsent() ? 1 : 0;
        }
        $traites = $coches + $absents;
        $modele = $chirurgie->getChirurgieModele();
        $data = [
            'id' => $chirurgie->getId() ?? 0,
            'dateProgrammee' => $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            'ordre' => $chirurgie->getOrdre(),
            'valide' => $chirurgie->isValide(),
            'etatValidation' => $chirurgie->isValide() ? 'VALIDEE' : (count($preparations) > 0 && $traites === count($preparations) && $absents > 0 ? 'VALIDATION_PARTIELLE' : 'EN_PREPARATION'),
            'valideLe' => $chirurgie->getValideLe()?->format(DateTimeInterface::ATOM),
            'creeLe' => $chirurgie->getCreeLe()?->format(DateTimeInterface::ATOM),
            'creePar' => $chirurgie->getCreePar(),
            'modifieLe' => $chirurgie->getModifieLe()?->format(DateTimeInterface::ATOM),
            'modifiePar' => $chirurgie->getModifiePar(),
            'chirurgieModele' => ['id' => $modele?->getId(), 'intitule' => $modele?->getIntitule()],
            'progressionPreparation' => ['total' => count($preparations), 'coches' => $coches, 'absents' => $absents, 'traites' => $traites, 'complete' => count($preparations) > 0 && count($preparations) === $traites],
            'preparationsMateriel' => $preparations,
        ];

        if ($includeFichesTechniques) {
            $data['fichesTechniques'] = array_map(static fn ($fiche): array => [
                'id' => $fiche->getId(), 'titre' => $fiche->getTitre(), 'description' => $fiche->getDescription(), 'lienImage' => $fiche->getLienImage(), 'ordre' => $fiche->getOrdre(),
            ], $modele?->getFichesTechniques()->toArray() ?? []);
        }

        return $data;
    }

    /**
     * Transforme une ligne de préparation et son matériel pour les sorties API.
     *
     * @return array<string, mixed>
     */
    private function preparationData(PreparationMateriel $preparation): array
    {
        $materiel = $preparation->getMateriel();

        return [
            'id' => $preparation->getId(),
            'coche' => $preparation->isCoche(),
            'absent' => $preparation->isAbsent(),
            'cocheLe' => $preparation->getCocheLe()?->format(DateTimeInterface::ATOM),
            'materiel' => ['id' => $materiel?->getId(), 'intitule' => $materiel?->getIntitule(), 'adresse' => $materiel?->getAdresse(), 'typeMateriel' => $materiel?->getTypeMateriel()],
        ];
    }

    /** Construit la clé de regroupement stable date / salle / chirurgien. */
    private function programmeKey(ChirurgiePlanifiee $chirurgie): string
    {
        return implode('|', [$chirurgie->getDateProgrammee()?->format('Y-m-d'), $chirurgie->getSalle(), $chirurgie->getChirurgien()?->getId()]);
    }

    /**
     * Produit l'identifiant fonctionnel exposé pour un programme agrégé.
     *
     * @param array<string, mixed> $programme
     */
    private function programmeId(array $programme): string
    {
        return sprintf('%s-%s-%s', $programme['date'], $programme['salle'], $programme['chirurgien']['id']);
    }
}
