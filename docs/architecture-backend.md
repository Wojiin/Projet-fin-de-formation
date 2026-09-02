# Architecture du backend

Ce document fixe les conventions retenues après revue des versions réellement verrouillées dans `composer.lock` : Symfony 8.1.1, API Platform 4.3.17, Doctrine ORM 3.6.7, LexikJWTAuthenticationBundle 3.2.0 et JWTRefreshTokenBundle 2.0.0.

## Principes

- Les entités Doctrine portent le CRUD standard. Les providers et processors personnalisés sont réservés aux vues agrégées et aux règles métier.
- Les sous-ressources simples utilisent `ApiPlatform\Metadata\Link`; le provider Doctrine construit leur requête.
- Les filtres sont déclarés avec `QueryParameter` et les filtres unitaires (`ExactFilter`, `PartialSearchFilter`, `SortFilter`). L’ancien attribut `ApiFilter` n’est plus utilisé.
- Un body propre à une action est représenté par un DTO avec contraintes Symfony. API Platform assure désérialisation, validation et documentation OpenAPI.
- Les conflits métier utilisent une `ErrorResource` conforme RFC 7807. Les erreurs de transport, de sécurité et de validation restent gérées par Symfony et API Platform.
- L’authentification, la création du JWT, sa rotation, le cookie de refresh et sa révocation à la déconnexion restent confiés aux bundles Lexik et Gesdinet.

## Répartition du code

- `Entity/` : modèle Doctrine, opérations API et contrat de sérialisation.
- `Dto/` : entrées d’actions et sorties agrégées.
- `State/` : adaptateurs API Platform minces, seulement lorsqu’un CRUD ou une sous-ressource Doctrine ne suffit pas.
- `Service/` : cas d’usage, règles métier, projections et intégrations techniques, sans lecture manuelle de la requête HTTP.
- `Exception/` : erreurs métier sérialisées par API Platform.
- `Repository/` : requêtes Doctrine propres au métier; aucun assemblage de réponse HTTP.

## Centralisations issues de l’audit

- `AuthenticatedUserProvider` fournit l’utilisateur courant sans dépendance directe à `Security` dans les processors/providers.
- `ChirurgieAuditTrail` applique les dates et auteurs à partir de l’horloge applicative.
- `PreparationProgressCalculator`, `ChirurgieReadModelFactory` et `ProgrammeReadModelFactory` constituent l’unique source des compteurs et projections API.
- Les services de planification, d’ordre, de préparation, de validation, de suppression et de mot de passe portent les règles d’écriture.
- `TechnicalSheetImageStorage` isole la validation MIME et le système de fichiers du contrôleur HTTP.

Les tests d’architecture empêchent de réintroduire la sécurité ou le mapping des réponses dans `State`, les opérations de fichiers dans les contrôleurs et les dépendances API Platform dans les services.

Les métadonnées API Platform restent sur les entités et DTO : leur extraction ajouterait de la configuration sans supprimer de duplication. Les dépôts Doctrine simples restent explicites afin de conserver leurs types et leurs futurs critères métier.

## Documentation de référence

- [Symfony 8.1](https://symfony.com/doc/8.1/index.html)
- [API Platform : DTO](https://api-platform.com/docs/core/dto/)
- [API Platform : paramètres et filtres](https://api-platform.com/docs/core/filters/)
- [API Platform : sous-ressources](https://api-platform.com/docs/core/subresources/)
- [API Platform : gestion des erreurs](https://api-platform.com/docs/core/errors/)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/index.html)
- [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle/tree/3.x/Resources/doc)
- [JWTRefreshTokenBundle](https://github.com/markitosgv/JWTRefreshTokenBundle)

## Vérifications

```bash
composer check
php bin/phpunit
php bin/console api:openapi:export --output=var/openapi.json
```

Les tests fonctionnels nécessitent que le schéma de la base de test existe. Leur classe purge ensuite uniquement cette base et recharge automatiquement les fixtures au démarrage, afin que chaque exécution parte du même état.
