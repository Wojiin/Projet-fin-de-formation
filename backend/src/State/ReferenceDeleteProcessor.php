<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Chirurgien;
use App\Entity\ChirurgieModele;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\ListeMateriel;
use App\Entity\Materiel;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\ChirurgiePlanifieeRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ReferenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private ChirurgiePlanifieeRepository $chirurgieRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $used = match (true) {
            $data instanceof Chirurgien => !$data->getListesMateriel()->isEmpty() || !$data->getChirurgiesPlanifiees()->isEmpty(),
            $data instanceof ChirurgieModele => !$data->getFichesTechniques()->isEmpty() || !$data->getListesMateriel()->isEmpty() || !$data->getChirurgiesPlanifiees()->isEmpty(),
            $data instanceof Materiel => !$data->getListesMateriel()->isEmpty() || !$data->getPreparationsMateriel()->isEmpty(),
            $data instanceof ListeMateriel => null !== $this->chirurgieRepository->findOneBy([
                'chirurgien' => $data->getChirurgien(),
                'chirurgieModele' => $data->getChirurgieModele(),
            ]),
            $data instanceof ChirurgiePlanifiee => $data->isValide(),
            $data instanceof User => !$data->getRefreshTokens()->isEmpty() || !$data->getChirurgiesValidees()->isEmpty() || !$data->getPreparationsCochees()->isEmpty(),
            default => false,
        };

        if ($used) {
            throw new ApiProblemException('RESOURCE_ALREADY_USED', 'Cette ressource ne peut pas être supprimée car elle est déjà utilisée.');
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
