# Contrat API ChirOrg

Base locale : `http://localhost:8080/api`. Toutes les routes métier utilisent `Authorization: Bearer <token>`. Le refresh token est géré par un cookie HttpOnly.

## Authentification

### `POST /login` — public

Body : `{"email":"user@chirorg.test","password":"password"}`. Retour : `{"token":"<jwt>"}` et cookie `refresh_token`. Erreurs : `401` identifiants invalides.

### `POST /token/refresh` — public

Envoie automatiquement le cookie HttpOnly. Retour : un nouveau JWT et un nouveau cookie après rotation. Erreurs : `401` token absent, expiré ou révoqué.

### `POST /logout` — public

Révoque le refresh token fourni par cookie et expire ce cookie. Retour : `{"message":"Déconnexion réussie."}`.

### `GET /me` — `ROLE_USER`

Retour : `{"id":1,"email":"user@chirorg.test","roles":["ROLE_USER"]}`. Le mot de passe n’est jamais exposé. Erreurs : `401` sans JWT.

## Programme opératoire

### `GET /programmes-operatoires` — `ROLE_USER`

Paramètres : `date=YYYY-MM-DD`, ou `dateDebut` / `dateFin`, et `salle`. `date` est prioritaire ; sans date, le jour courant est utilisé.

Retour : liste plate triée par date, salle, ordre et identifiant :

```json
[{"id":1,"date":"2026-07-20","heure":"08:00","dateProgrammee":"2026-07-20T08:00:00+00:00","salle":"Salle A","ordre":1,"valide":false,"chirurgien":{"id":1,"prenom":"Jean","nom":"Dupont"},"chirurgieModele":{"id":1,"intitule":"Prothèse de genou"},"progressionPreparation":{"total":6,"coches":3,"complete":false}}]
```

`GET /programmes-operatoires/{date}` applique directement la date du chemin. Erreurs : `400` date invalide.

## Préparation et validation

### `GET /chirurgies-planifiees/{id}/preparation` — `ROLE_USER`

Retourne la chirurgie, le chirurgien, le modèle, la checklist et sa progression. Initialise la checklist si nécessaire. Ne retourne jamais la fiche technique. Erreurs : `404`, `409` si aucune liste de matériel ne correspond.

### `PATCH /preparations-materiel/{id}/cocher` — `ROLE_USER`

Body : `{"coche":true}`. Met à jour `cocheLe` et `cochePar`; un décochage remet ces valeurs à `null`. Erreurs : `400` body invalide, `404`, `409` chirurgie déjà validée.

### `POST /chirurgies-planifiees/{id}/validation` — `ROLE_USER`

Valide uniquement une checklist non vide et entièrement cochée. Retourne la chirurgie avec `valide`, `valideLe` et `validePar`. Erreurs : `404`, `409` matériel absent ou incomplet.

### `GET /chirurgies-planifiees/{id}/vue-finale` — `ROLE_USER`

Retourne la chirurgie validée, l’utilisateur validateur, le matériel présent et la fiche technique. Lecture seule. Erreurs : `404`, `409` chirurgie non validée.

## Administration

Les routes `/users`, `/chirurgiens`, `/chirurgie-modeles`, `/fiches-techniques`, `/materiels` et `/listes-materiel` sont modifiables par `ROLE_ADMIN`. Les lectures des référentiels sont accessibles à `ROLE_USER`. Une suppression d’une ressource utilisée retourne `409` :

```json
{"code":"RESOURCE_ALREADY_USED","message":"Cette ressource ne peut pas être supprimée car elle est déjà utilisée."}
```

Les erreurs métier utilisent toujours la forme `{"code":"...","message":"..."}`. Les validations API Platform retournent `422`.
