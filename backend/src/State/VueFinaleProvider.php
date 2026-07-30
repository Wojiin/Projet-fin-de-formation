<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ChirurgieVueFinale;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgiePlanifieeRepository;
use DateTimeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Produit la synthèse finale d'une chirurgie validée, avec les matériels prêts
 * et les fiches techniques de son modèle.
 */
final readonly class VueFinaleProvider implements ProviderInterface
{
    public function __construct(private ChirurgiePlanifieeRepository $repository)
    {
    }

    /**
     * Refuse l'accès avant validation puis projette les données nécessaires à la
     * vue finale en lecture seule.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChirurgieVueFinale
    {
        $chirurgie = $this->repository->findVueFinaleData((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Chirurgie planifiée introuvable.');

        if (!$chirurgie->isValide()) {
            throw new ApiProblemException('CHIRURGIE_NON_VALIDEE', 'La vue finale est disponible uniquement après validation.');
        }

        $materiels = [];
        foreach ($chirurgie->getPreparationsMateriel() as $preparation) {
            if (!$preparation->isCoche()) {
                continue;
            }
            $materiel = $preparation->getMateriel();
            $materiels[] = [
                'id' => $materiel?->getId(),
                'intitule' => $materiel?->getIntitule(),
                'adresse' => $materiel?->getAdresse(),
                'typeMateriel' => $materiel?->getTypeMateriel(),
                'cocheLe' => $preparation->getCocheLe()?->format(DateTimeInterface::ATOM),
            ];
        }

        $fiches = [];
        $modele = $chirurgie->getChirurgieModele();
        foreach ($modele?->getFichesTechniques() ?? [] as $fiche) {
            $fiches[] = [
                'id' => $fiche->getId(),
                'titre' => $fiche->getTitre(),
                'description' => $fiche->getDescription(),
                'lienImage' => $fiche->getLienImage(),
                'ordre' => $fiche->getOrdre(),
            ];
        }

        $chirurgien = $chirurgie->getChirurgien();
        $validePar = $chirurgie->getValidePar();

        return new ChirurgieVueFinale(
            id: $chirurgie->getId() ?? 0,
            dateProgrammee: $chirurgie->getDateProgrammee()?->format('Y-m-d') ?? '',
            salle: $chirurgie->getSalle() ?? '',
            ordre: $chirurgie->getOrdre(),
            valide: true,
            valideLe: $chirurgie->getValideLe()?->format(DateTimeInterface::ATOM),
            validePar: null === $validePar ? null : ['id' => $validePar->getId(), 'email' => $validePar->getEmail()],
            chirurgien: ['id' => $chirurgien?->getId(), 'prenom' => $chirurgien?->getPrenom(), 'nom' => $chirurgien?->getNom()],
            chirurgieModele: ['id' => $modele?->getId(), 'intitule' => $modele?->getIntitule()],
            materielsValides: $materiels,
            ficheTechnique: $fiches,
        );
    }
}
