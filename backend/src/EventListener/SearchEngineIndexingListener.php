<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Empêche l'indexation des réponses de cet intranet, quelle que soit la route servie. */
final class SearchEngineIndexingListener
{
    #[AsEventListener(event: KernelEvents::RESPONSE)]
    /** Ajoute l'en-tête robots à toute réponse HTTP sortante. */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
}
