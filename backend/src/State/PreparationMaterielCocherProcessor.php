<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PreparationMaterielInput;
use App\Entity\PreparationMateriel;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\PreparationMaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Change l'état d'une ligne de préparation en conservant sa traçabilité et en
 * interdisant toute modification après validation de la chirurgie.
 */
final readonly class PreparationMaterielCocherProcessor implements ProcessorInterface
{
    public function __construct(
        private PreparationMaterielRepository $repository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * Coche ou décoche une préparation, renseigne ou efface son auteur et sa date,
     * puis enregistre la transition.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PreparationMateriel
    {
        if (!$data instanceof PreparationMaterielInput || (null === $data->coche && null === $data->absent)) {
            throw new \InvalidArgumentException('Un état de préparation valide est attendu.');
        }

        $coche = $data->coche ?? false;
        $absent = $data->absent ?? false;
        if ($coche && $absent) {
            throw new \InvalidArgumentException('Un matériel ne peut pas être à la fois prêt et absent.');
        }

        $preparation = $this->repository->find((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Préparation de matériel introuvable.');

        $chirurgie = $preparation->getChirurgiePlanifiee();
        if ($chirurgie?->isValide()) {
            throw new ApiProblemException('PREPARATION_VERROUILLEE', 'Le matériel d’une chirurgie validée ne peut plus être modifié.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        $now = new \DateTimeImmutable();
        $preparation->setCoche($coche)->setAbsent($absent);
        if ($coche) {
            $preparation->setCocheLe($now)->setCochePar($user);
        } else {
            $preparation->setCocheLe(null)->setCochePar(null);
        }
        $chirurgie
            ?->setModifieLe($now)
            ->setModifiePar($user->getUserIdentifier());

        $this->entityManager->flush();

        return $preparation;
    }
}
