# ChirOrg

ChirOrg est une application full stack de planification, préparation et validation des programmes opératoires. Le backend repose sur Symfony et API Platform ; le frontend utilise Vue 3, Pinia et Vite.

## Arborescence

```text
ChirOrg/
├── backend/                  API Symfony et tests PHP
├── conception/               modèles et diagrammes de conception
├── docker/nginx/             configuration du serveur HTTP
├── docs/                     documentation fonctionnelle et technique
├── frontend/                 application Vue et tests JavaScript
└── docker-compose.dev.yaml   environnement de développement
```

Dans le frontend, les conventions suivent celles de Netflux :

- `src/api` contient le client HTTP commun ;
- `src/components` contient les composants métier réutilisables ;
- `src/components/ui` contient les composants d’interface génériques ;
- `src/services`, `src/stores`, `src/views` et `src/utils` ont chacun une responsabilité unique ;
- `tests/unit` et `tests/e2e` sont séparés du code applicatif.

Les classes du domaine médical conservent leur nomenclature française afin de rester alignées avec le vocabulaire fonctionnel de ChirOrg.

## Lancement avec Docker

Prérequis : Docker et Docker Compose.

```bash
docker compose -f docker-compose.dev.yaml up -d --build
```

Services exposés :

- frontend : `http://localhost:5173` ;
- API : `http://localhost:8080/api` ;
- MySQL : `127.0.0.1:3306`.

## Développement local

Backend, depuis `backend/` :

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

Frontend, depuis `frontend/` :

```bash
npm install
npm run dev
```

## Vérification

```bash
cd backend
php bin/phpunit

cd ../frontend
npm run test:unit
npm run build
```

## Audit Lighthouse

Lighthouse doit mesurer les ressources minifiées, sans le client de développement Vite ni Vue DevTools :

```bash
docker compose -f docker-compose.dev.yaml -f docker-compose.lighthouse.yaml up -d --build
```

Lancez ensuite l'audit global depuis le dossier `frontend` avec un compte administrateur :

```powershell
cd frontend
$env:LH_EMAIL = "admin@chirorg.test"
$env:LH_PASSWORD = "votre-mot-de-passe"
npm.cmd run lighthouse:ci
```

Le script audite chaque vue trois fois, conserve les scores médians et écrit les rapports HTML, JSON et le résumé global dans `frontend/lighthouse-reports`.

Pour auditer ponctuellement une route en un seul passage :

```powershell
$env:LH_RUNS = "1"
$env:LH_ROUTES = "/programme"
npm.cmd run lighthouse:ci
```

Pour revenir au développement courant :

```bash
docker compose -f docker-compose.dev.yaml up -d
```

Vue DevTools est désactivé par défaut. Pour l’activer ponctuellement, définissez `VITE_ENABLE_DEVTOOLS=true`.
