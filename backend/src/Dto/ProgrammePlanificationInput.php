<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/** Commande de planification groupée : date, salle, chirurgien et modèles dans leur ordre initial. */
final class ProgrammePlanificationInput
{
    /**
     * @param list<int> $chirurgieModeleIds
     */
    public function __construct(
        #[ApiProperty(
            description: 'Date du programme au format YYYY-MM-DD.',
            openapiContext: ['type' => 'string', 'format' => 'date', 'example' => '2030-01-15'],
        )]
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        #[Assert\NotNull]
        #[Assert\GreaterThanOrEqual(
            value: 'tomorrow Europe/Paris',
            message: 'La date du programme doit être au minimum celle de demain.',
        )]
        public readonly ?\DateTimeImmutable $dateProgrammee = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public readonly ?string $salle = null,
        #[Assert\NotNull]
        #[Assert\Positive]
        public readonly ?int $chirurgienId = null,
        #[Assert\Count(min: 1, max: 50)]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive(),
        ])]
        public readonly array $chirurgieModeleIds = [],
    ) {
    }
}
