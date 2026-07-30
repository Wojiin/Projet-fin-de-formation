<?php

namespace App\Exception;

use ApiPlatform\Metadata\ErrorResource;
use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

#[ErrorResource]
/** Représente une violation métier sous la forme normalisée RFC 7807 de l'API. */
final class ApiProblemException extends \RuntimeException implements ProblemExceptionInterface
{
    public function __construct(
        public readonly string $errorCode,
        private readonly string $detail,
        private readonly int $status = Response::HTTP_CONFLICT,
    ) {
        parent::__construct($detail);
    }

    /** Produit l'URI stable qui identifie le code métier de l'erreur. */
    public function getType(): string
    {
        return '/api/errors/'.strtolower(str_replace('_', '-', $this->errorCode));
    }

    /** Retourne le libellé HTTP correspondant au statut de l'erreur. */
    public function getTitle(): ?string
    {
        return Response::$statusTexts[$this->status] ?? 'API Error';
    }

    /** Expose le statut HTTP à retourner au client. */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /** Expose le message métier lisible par le client. */
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /** N'associe pas l'erreur à une instance métier supplémentaire. */
    public function getInstance(): ?string
    {
        return null;
    }
}
