<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use App\Repository\ChirurgiePlanifieeRepository;
use App\Service\ChirurgieValidationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ChirurgieValidationProcessor implements ProcessorInterface
{
    public function __construct(
        private ChirurgiePlanifieeRepository $repository,
        private ChirurgieValidationService $validationService,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePlanifiee
    {
        $chirurgie = $this->repository->findPreparationData((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Chirurgie planifiée introuvable.');
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        return $this->validationService->validate($chirurgie, $user);
    }
}
