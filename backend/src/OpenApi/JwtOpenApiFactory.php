<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;

/** Enrichit la documentation OpenAPI avec le schéma d'authentification Bearer JWT. */
final readonly class JwtOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    /** Décore le document généré afin que Swagger propose l'envoi du JWT. */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $components = $openApi->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();
        $securitySchemes['bearerAuth'] = new SecurityScheme(
            type: 'http',
            description: 'Jeton JWT obtenu avec POST /login.',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        return $openApi
            ->withComponents($components->withSecuritySchemes($securitySchemes))
            ->withSecurity([['bearerAuth' => []]]);
    }
}
