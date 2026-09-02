# Frontend ChirOrg

Application monopage Vue 3 de planification et de préparation des programmes opératoires.

## Architecture

- `src/router` : routes nommées, layout authentifié imbriqué et gardes de navigation ;
- `src/api` : configuration HTTP, client Axios, erreurs et formats de réponse ;
- `src/services` : appels réseau sans état réactif ni logique d’affichage ;
- `src/mappers` : adaptation des payloads API vers les modèles du frontend ;
- `src/stores` : état métier partagé et mutations Pinia ;
- `src/composables` : orchestration fonctionnelle des vues, du routeur et des stores ;
- `src/views` : assemblage déclaratif des composants et logique d’affichage uniquement ;
- `src/components` : composants de présentation et champs de formulaire réutilisables ;
- `src/config` : descriptions déclaratives des écrans, formulaires et navigations ;
- `src/domain` : règles métier pures, comme les filtres administratifs ;
- `src/presenters` : libellés et textes dérivés destinés à l’affichage ;
- `src/utils` : fonctions pures partagées.

Chaque vue fonctionnelle possède un composable `use<NomDeLaVue>.js`. Les vues ne dépendent directement ni d’Axios, ni des services, ni de Pinia, ni du routeur. Les services HTTP renvoient les données utiles, les mappers les adaptent, puis les actions Pinia mettent à jour l’état partagé. Des tests d’architecture verrouillent ces frontières.

```text
Vue.vue → useVue.js → store Pinia → service API → client Axios
                         ↓
                       mapper
```

Une vue peut appeler un service depuis son composable lorsqu’une commande ne produit aucun état partagé, par exemple le changement de mot de passe ou le téléversement temporaire d’une image.

Les services restent ciblés par domaine : `authApi` gère le cycle de session, `accountApi` le compte courant, `technicalSheetApi` les fiches techniques, `programmeApi` les programmes et `preparationApi` la checklist. Le CRUD générique des référentiels reste isolé dans `adminApi`.

Les structures visuelles répétées sont également mutualisées : `PageHeading` porte les en-têtes et leurs actions, `SurgeryOverview` le résumé commun aux validations, et `AdminItemActions` les commandes CRUD des listes responsive.

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

L’URL de l’API se configure avec `VITE_API_BASE_URL`. La valeur de développement par défaut est `http://localhost:8080/api`.

Avec Docker, le service `frontend` est déclaré dans `../docker-compose.dev.yaml` et expose Vite sur `http://localhost:5173`.

## Validation

```sh
npm run build
npm run test:unit
npm run test:e2e
```

Les tests sont regroupés dans `tests/unit` et `tests/e2e`. La suite end-to-end utilise l’API réelle et couvre Chromium, Firefox et WebKit.
