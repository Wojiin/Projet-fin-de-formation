<?php

namespace App\Exception;

use ApiPlatform\Metadata\ErrorResource;
use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

#[ErrorResource]
final class ApiProblemException extends \RuntimeException implements ProblemExceptionInterface
{
    public function __construct(
        public readonly string $errorCode,
        private readonly string $detail,
        private readonly int $status = Response::HTTP_CONFLICT,
    ) {
        parent::__construct($detail);
    }

    public function getType(): string
    {
        return '/api/errors/'.strtolower(str_replace('_', '-', $this->errorCode));
    }

    public function getTitle(): ?string
    {
        return Response::$statusTexts[$this->status] ?? 'API Error';
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
