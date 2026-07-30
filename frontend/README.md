# Frontend ChirOrg

Application monopage Vue 3 de planification et de préparation des programmes opératoires.

## Architecture

- `src/router` : routes nommées, layout authentifié imbriqué et gardes de navigation ;
- `src/services` : unique couche HTTP Axios et adaptation des réponses API ;
- `src/stores` : état global et logique métier Pinia ;
- `src/views` : orchestration des écrans à partir des props de route et des stores ;
- `src/components` : composants de présentation et champs de formulaire réutilisables ;
- `src/config` : description des référentiels et des formulaires d’administration ;
- `src/utils` : fonctions pures partagées.

Les vues n’appellent pas Axios directement. Les services renvoient les données utiles, puis les actions Pinia mettent à jour l’état réactif.

## Authentification

Le JWT d’accès est conservé uniquement en mémoire dans le store Pinia. Le refresh token est géré par l’API dans un cookie `HttpOnly` :

1. Axios envoie les cookies avec `withCredentials`;
2. le store tente un renouvellement silencieux au démarrage de la SPA ;
3. l’intercepteur ajoute le JWT courant dans `Authorization: Bearer`;
4. après un `401`, une seule requête de renouvellement est partagée par les appels concurrents ;
5. la requête initiale est rejouée, ou la session Pinia est nettoyée si le renouvellement échoue.

Aucun token n’est écrit dans `localStorage` ou `sessionStorage`.

## Installation et développement

```sh
npm install
npm run dev
```

L’URL de l’API se configure avec `VITE_API_URL`. La valeur de développement par défaut est `http://localhost:8080/api`.

Avec Docker, le service `frontend` est déclaré dans `backend/compose.yaml` et expose Vite sur `http://localhost:5173`.

## Validation

```sh
npm run build
npm run test:unit -- --run
npm run test:e2e
```

La suite end-to-end utilise l’API réelle et couvre Chromium, Firefox et WebKit. Elle vérifie notamment la navigation sans rechargement complet et la restauration de session après actualisation.
