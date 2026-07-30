<?php

namespace App\Service;

use App\Entity\ChirurgiePlanifiee;
use App\Entity\ListeMateriel;
use App\Entity\PreparationMateriel;
use App\Exception\ApiProblemException;
use App\Repository\ListeMaterielRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Résout la liste de matériel adaptée à une chirurgie et crée les lignes de
 * préparation manquantes sans dupliquer celles déjà créées.
 */
final readonly class PreparationMaterielInitializer
{
    public function __construct(
        private ListeMaterielRepository $listeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Recherche la liste définie pour le couple chirurgien / chirurgie modèle.
     * Ce couple est obligatoire pour garantir une préparation reproductible.
     */
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

    /**
     * Initialise les préparations d'une chirurgie non validée à partir de sa liste.
     * Le flush est optionnel pour permettre une création atomique d'un programme.
     */
    public function initializeForChirurgie(ChirurgiePlanifiee $chirurgie, bool $flush = true): void
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

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
