<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 20]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $status = $exception->getStatusCode();
        $message = $exception->getMessage();
        $event->setResponse(new JsonResponse([
            'code' => $this->businessCode($status, $message),
            'message' => '' !== $message ? $message : $this->defaultMessage($status),
        ], $status, $exception->getHeaders()));
    }

    private function businessCode(int $status, string $message): string
    {
        return match (true) {
            str_contains($message, 'déjà utilisée') => 'RESOURCE_ALREADY_USED',
            str_contains($message, 'liste de matériel') => 'LISTE_MATERIEL_INTROUVABLE',
            str_contains($message, 'Tout le matériel'), str_contains($message, 'aucune préparation') => 'MATERIEL_PREPARATION_INCOMPLETE',
            str_contains($message, 'validée ne peut plus') => 'PREPARATION_VERROUILLEE',
            str_contains($message, 'vue finale') => 'CHIRURGIE_NON_VALIDEE',
            str_contains($message, 'Date invalide') => 'INVALID_PROGRAMME_DATE',
            400 === $status => 'INVALID_REQUEST',
            404 === $status => 'RESOURCE_NOT_FOUND',
            409 === $status => 'BUSINESS_CONFLICT',
            default => 'HTTP_ERROR',
        };
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'La requête est invalide.',
            404 => 'La ressource demandée est introuvable.',
            409 => 'La requête entre en conflit avec l’état actuel de la ressource.',
            default => 'Une erreur est survenue.',
        };
    }
}
