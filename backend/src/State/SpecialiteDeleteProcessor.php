<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Specialite;
use App\Exception\ApiProblemException;
use App\Repository\SpecialiteRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Supprime une spécialité en réaffectant ses références à « Sans spécialité »
 * afin de préserver l'intégrité des référentiels.
 */
final readonly class SpecialiteDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private SpecialiteRepository $repository,
    ) {
    }

    /**
     * Protège la spécialité par défaut, migre les dépendances puis supprime la
     * spécialité demandée.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Specialite) {
            throw new \InvalidArgumentException('Une spécialité est attendue.');
        }

        if (Specialite::SANS_SPECIALITE === $data->getIntitule()) {
            throw new ApiProblemException(
                'DEFAULT_SPECIALITE_PROTECTED',
                'La spécialité « Sans spécialité » ne peut pas être supprimée.',
            );
        }

        $specialiteParDefaut = $this->repository->findDefault()
            ?? throw new \LogicException('La spécialité « Sans spécialité » est absente.');

        foreach ($data->getChirurgiens()->toArray() as $chirurgien) {
            $chirurgien->setSpecialite($specialiteParDefaut);
        }
        foreach ($data->getMateriels()->toArray() as $materiel) {
            $materiel->setSpecialite($specialiteParDefaut);
        }
        foreach ($data->getChirurgiesModeles()->toArray() as $chirurgieModele) {
            $chirurgieModele->setSpecialite($specialiteParDefaut);
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
