<?php

namespace App\Service;

use App\Entity\ChirurgiePlanifiee;
use App\Entity\ListeMateriel;
use App\Entity\PreparationMateriel;
use App\Exception\ApiProblemException;
use App\Repository\ListeMaterielRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PreparationMaterielInitializer
{
    public function __construct(
        private ListeMaterielRepository $listeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findListe(ChirurgiePlanifiee $chirurgie): ListeMateriel
    {
        $chirurgien = $chirurgie->getChirurgien();
        $modele = $chirurgie->getChirurgieModele();

        if (null === $chirurgien || null === $modele) {
            throw new ApiProblemException('INVALID_CHIRURGIE', 'Le chirurgien et la chirurgie modèle sont requis pour initialiser le matériel.');
        }

        return $this->listeRepository->findOneForChirurgienAndChirurgieModele($chirurgien, $modele)
            ?? throw new ApiProblemException('LISTE_MATERIEL_INTROUVABLE',
            'Aucune liste de matériel ne correspond à ce chirurgien et à cette chirurgie modèle.',
        );
    }

    public function initializeForChirurgie(ChirurgiePlanifiee $chirurgie): void
    {
        if ($chirurgie->isValide()) {
            throw new ApiProblemException('PREPARATION_VERROUILLEE', 'Le matériel d’une chirurgie validée ne peut plus être initialisé.');
        }

        $liste = $this->findListe($chirurgie);
        $materielsExistants = [];
        foreach ($chirurgie->getPreparationsMateriel() as $preparationExistante) {
            $materielId = $preparationExistante->getMateriel()?->getId();
            if (null !== $materielId) {
                $materielsExistants[$materielId] = true;
            }
        }

        foreach ($liste->getMateriels() as $materiel) {
            if (null !== $materiel->getId() && isset($materielsExistants[$materiel->getId()])) {
                continue;
            }

            $preparation = (new PreparationMateriel())
                ->setChirurgiePlanifiee($chirurgie)
                ->setMateriel($materiel);
            $chirurgie->addPreparationMateriel($preparation);
            $this->entityManager->persist($preparation);
        }

        $this->entityManager->flush();
    }
}
