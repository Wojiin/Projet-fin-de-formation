<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ChirurgiePlanifiee;
use App\Entity\User;
use App\Service\PreparationMaterielInitializer;
use App\Service\ProgrammeOrderAllocator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Encadre l'écriture d'une chirurgie planifiée : calcule sa position initiale
 * et initialise obligatoirement sa préparation de matériel.
 */
final readonly class ChirurgiePlanifieeWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private PreparationMaterielInitializer $initializer,
        private ProgrammeOrderAllocator $orderAllocator,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * Persiste la chirurgie après contrôle de sa liste de matériel et attribution
     * du prochain ordre disponible lors d'une création.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChirurgiePlanifiee
    {
        if (!$data instanceof ChirurgiePlanifiee) {
            throw new \InvalidArgumentException('Une chirurgie planifiée est attendue.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        $isNew = null === $data->getId();
        $now = new \DateTimeImmutable();
        $identifier = $user->getUserIdentifier();
        if ($isNew) {
            $data
                ->setCreeLe($now)
                ->setCreePar($identifier)
                ->setModifieLe($now)
                ->setModifiePar($identifier);
        } else {
            $data->setModifieLe($now)->setModifiePar($identifier);
        }

        if (!$isNew) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        return $this->entityManager->wrapInTransaction(function () use ($data, $operation, $uriVariables, $context): ChirurgiePlanifiee {
            if (null !== $data->getDateProgrammee()
                && null !== $data->getSalle()
                && null !== $data->getChirurgien()
            ) {
                $data->setOrdre($this->orderAllocator->reserveNextOrder(
                    $data->getDateProgrammee(),
                    $data->getSalle(),
                    $data->getChirurgien(),
                ));
            }

            $this->initializer->findListe($data);
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            $this->initializer->initializeForChirurgie($data);

            return $result;
        });
    }
}
