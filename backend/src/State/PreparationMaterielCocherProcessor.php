<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PreparationMateriel;
use App\Entity\User;
use App\Repository\PreparationMaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PreparationMaterielCocherProcessor implements ProcessorInterface
{
    public function __construct(
        private PreparationMaterielRepository $repository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PreparationMateriel
    {
        $request = $this->requestStack->getCurrentRequest();

        try {
            $payload = json_decode($request?->getContent() ?? '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Le body doit être un objet JSON valide contenant le booléen "coche".');
        }

        if (!is_array($payload) || !array_key_exists('coche', $payload) || !is_bool($payload['coche'])) {
            throw new BadRequestHttpException('Le champ "coche" est obligatoire et doit être un booléen.');
        }

        $preparation = $this->repository->find((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Préparation de matériel introuvable.');

        if ($preparation->getChirurgiePlanifiee()?->isValide()) {
            throw new ConflictHttpException('Le matériel d’une chirurgie validée ne peut plus être modifié.');
        }

        $preparation->setCoche($payload['coche']);
        if ($payload['coche']) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedHttpException('Utilisateur non authentifié.');
            }
            $preparation->setCocheLe(new \DateTimeImmutable())->setCochePar($user);
        } else {
            $preparation->setCocheLe(null)->setCochePar(null);
        }

        $this->entityManager->flush();

        return $preparation;
    }
}
