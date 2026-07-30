# Contrat API ChirOrg

Base locale : `http://localhost:8080/api`. Toutes les routes métier utilisent `Authorization: Bearer <token>`. Le refresh token est géré par un cookie HttpOnly.

## Authentification

### `POST /login` — public

Body : `{"email":"user@chirorg.test","password":"password"}`. Retour : `{"token":"<jwt>"}` et cookie `refresh_token`. Erreurs : `401` identifiants invalides.

### `POST /token/refresh` — public

Envoie automatiquement le cookie HttpOnly. Retour : un nouveau JWT et un nouveau cookie après rotation. Erreurs : `401` token absent, expiré ou révoqué.

### `POST /logout` — public

Le listener de déconnexion de JWTRefreshTokenBundle révoque le refresh token fourni par cookie et expire ce cookie. La réponse confirme que le token a été invalidé (ou l’était déjà).

### `GET /me` — `ROLE_USER`

Retour : `{"id":1,"email":"user@chirorg.test","roles":["ROLE_USER"]}`. Le mot de passe n’est jamais exposé. Erreurs : `401` sans JWT.

## Programme opératoire

### `GET /programmes-operatoires` — `ROLE_USER`

Paramètres déclarés et validés par API Platform : `date=YYYY-MM-DD`, ou `dateDebut` / `dateFin`, ainsi que `salle` et `chirurgien` (entier positif). `date` est prioritaire ; sans date, le jour courant est utilisé. Un paramètre inconnu retourne `400`.

Retour : liste regroupée par jour, salle et chirurgien, destinée à la page d’accueil :

```json
[{"date":"2026-07-20","salle":"Salle A","chirurgien":{"id":1,"prenom":"Jean","nom":"Dupont"},"nombreChirurgies":2,"nombreChirurgiesValidees":1,"progressionPreparation":{"total":12,"coches":9,"complete":false}}]
```

### `GET /programmes-operatoires/{date}/{salle}/{chirurgien}` — `ROLE_USER`

Retourne le détail d’un programme avec les chirurgies planifiées et leurs lignes de préparation. Le front coche une ligne via `PATCH /preparations-materiel/{id}/cocher`, puis valide la chirurgie via `POST /chirurgies-planifiees/{id}/validation`.

### `POST /programmes-operatoires` — `ROLE_USER`

Planifie en une seule transaction plusieurs modèles de chirurgie pour un jour, une salle et un chirurgien communs. Le tableau `chirurgieModeleIds` définit l’ordre initial et chaque préparation matériel est initialisée.

### `PATCH /programmes-operatoires/{date}/{salle}/{chirurgien}/ordre` — `ROLE_USER`

Reçoit `{"chirurgieIds":[3,1,2]}`. La liste doit contenir exactement chaque chirurgie du programme une fois ; les ordres sont recalculés de `1` à `n` dans une transaction unique.

### `GET /programmes-operatoires/{date}/{salle}/{chirurgien}/vue-finale` — `ROLE_USER`

Retourne uniquement les chirurgies validées du programme, en lecture seule, avec les fiches techniques de leur chirurgie modèle. Erreurs : `400` date ou chirurgien invalide, `404` programme absent.

## Préparation et validation

### `GET /chirurgies-planifiees/{id}/preparation` — `ROLE_USER`

Retourne la chirurgie, le chirurgien, le modèle, la checklist et sa progression. Initialise la checklist si nécessaire. Ne retourne jamais la fiche technique. Erreurs : `404`, `409` si aucune liste de matériel ne correspond.

### `PATCH /preparations-materiel/{id}/cocher` — `ROLE_USER`

Body : `{"coche":true}`. Le DTO d’entrée est désérialisé et validé par API Platform. La route met à jour `cocheLe` et `cochePar`; un décochage remet ces valeurs à `null`. Erreurs : `400` JSON invalide, `422` donnée invalide, `404`, `409` chirurgie déjà validée.

### `POST /chirurgies-planifiees/{id}/validation` — `ROLE_USER`

Valide uniquement une checklist non vide et entièrement cochée. Retourne la chirurgie avec `valide`, `valideLe` et `validePar`. Erreurs : `404`, `409` matériel absent ou incomplet.

### `GET /chirurgies-planifiees/{id}/vue-finale` — `ROLE_USER`

Retourne la chirurgie validée, l’utilisateur validateur, le matériel présent et la fiche technique. Lecture seule. Erreurs : `404`, `409` chirurgie non validée.

## Administration

Les routes `/users`, `/specialites`, `/chirurgiens`, `/chirurgie-modeles`, `/fiches-techniques`, `/materiels` et `/listes-materiel` sont modifiables par `ROLE_ADMIN`. Les lectures des référentiels sont accessibles à `ROLE_USER`. Les chirurgiens, modèles de chirurgie et matériels référencent une spécialité chirurgicale.

La spécialité système `Sans spécialité` sert de valeur de repli et ne peut pas être supprimée. Lorsqu’un administrateur supprime une autre spécialité, ses chirurgiens, matériels et modèles de chirurgie sont automatiquement réaffectés à cette spécialité système avant la suppression.

Une suppression d’une autre ressource utilisée retourne `409` :

```json
{
  "type": "/api/errors/resource-already-used",
  "title": "Conflict",
  "status": 409,
  "detail": "Cette ressource ne peut pas être supprimée car elle est déjà utilisée.",
  "errorCode": "RESOURCE_ALREADY_USED"
}
```

Les erreurs métier sont des ressources d’erreur API Platform au format RFC 7807. Les validations API Platform retournent `422` avec la liste des violations.
