<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/** Noyau Symfony de ChirOrg, limité aux environnements explicitement supportés par le projet. */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /** Retourne la liste fermée des environnements autorisés pour éviter une configuration ambiguë. */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
