<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Dto\ProgrammeOperatoireResume;
use App\Repository\ChirurgiePlanifieeRepository;
use DateTimeInterface;

/** Orchestre les lectures de programmes entre le dépôt et leurs projections API. */
final readonly class ProgrammeOperatoireService
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private ProgrammeReadModelFactory $readModelFactory,
    ) {
    }

    /**
     * @return list<ProgrammeOperatoireResume>
     */
    public function getProgrammes(
        ?DateTimeInterface $date = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
        ?string $salle = null,
        ?int $chirurgienId = null,
    ): array {
        return $this->readModelFactory->createSummaries(
            $this->repository->findForProgrammeOperatoire($date, $dateDebut, $dateFin, $salle, $chirurgienId),
        );
    }

    public function getProgramme(
        DateTimeInterface $date,
        string $salle,
        int $chirurgienId,
        bool $vueFinale = false,
    ): ?ProgrammeOperatoire {
        $chirurgies = $this->repository->findForProgrammeOperatoire(
            date: $date,
            salle: $salle,
            chirurgienId: $chirurgienId,
            valide: $vueFinale ? true : null,
            withFichesTechniques: $vueFinale,
        );

        return $this->readModelFactory->createProgramme($chirurgies, $vueFinale);
    }
}
