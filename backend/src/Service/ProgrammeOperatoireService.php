<?php

namespace App\Service;

use App\Dto\ProgrammeOperatoire;
use App\Repository\ChirurgiePlanifieeRepository;
use DateTimeInterface;

final readonly class ProgrammeOperatoireService
{
    public function __construct(private ChirurgiePlanifieeRepository $repository)
    {
    }

    /** @return list<ProgrammeOperatoire> */
    public function getProgramme(
        ?DateTimeInterface $date = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
        ?string $salle = null,
    ): array {
        $items = [];

        foreach ($this->repository->findForProgrammeOperatoire($date, $dateDebut, $dateFin, $salle) as $chirurgie) {
            $total = $chirurgie->getPreparationsMateriel()->count();
            $coches = $chirurgie->getPreparationsMateriel()
                ->filter(static fn ($preparation): bool => $preparation->isCoche())
                ->count();
            $dateProgrammee = $chirurgie->getDateProgrammee();
            $chirurgien = $chirurgie->getChirurgien();
            $modele = $chirurgie->getChirurgieModele();

            $items[] = new ProgrammeOperatoire(
                id: $chirurgie->getId() ?? 0,
                date: $dateProgrammee?->format('Y-m-d') ?? '',
                heure: $dateProgrammee?->format('H:i') ?? '',
                dateProgrammee: $dateProgrammee?->format(DateTimeInterface::ATOM) ?? '',
                salle: $chirurgie->getSalle() ?? '',
                ordre: $chirurgie->getOrdre(),
                valide: $chirurgie->isValide(),
                chirurgien: [
                    'id' => $chirurgien?->getId(),
                    'prenom' => $chirurgien?->getPrenom(),
                    'nom' => $chirurgien?->getNom(),
                ],
                chirurgieModele: [
                    'id' => $modele?->getId(),
                    'intitule' => $modele?->getIntitule(),
                ],
                progressionPreparation: [
                    'total' => $total,
                    'coches' => $coches,
                    'complete' => $total > 0 && $total === $coches,
                ],
            );
        }

        return $items;
    }
}
