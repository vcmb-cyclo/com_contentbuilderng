# API JSON

L'API est exposée par :

```text
index.php?option=com_contentbuilderng&task=api.display&id=VIEW_ID
```

Ajoutez `format=json` si votre intégration ou votre routage Joomla l'exige.

## Principes de sécurité

- la vue doit exister ;
- les permissions de la vue sont appliquées ;
- les champs doivent être publiés ;
- chaque champ exposé doit être marqué **API autorisée** ;
- les permissions diffèrent selon l'opération ;
- les liens de prévisualisation signés de l'administration sont temporaires.

## Format général des réponses

Succès :

```json
{
  "success": true,
  "messages": [],
  "data": {}
}
```

Erreur :

```json
{
  "success": false,
  "messages": ["Message d'erreur"],
  "data": null
}
```

Le code HTTP est positionné pour les erreurs comprises entre 400 et 599.

## Lire une liste

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&list[limit]=20&list[start]=0
```

Permissions : **API + Voir + List Access**.

Réponse déduite du contrôleur :

```json
{
  "success": true,
  "messages": [],
  "data": {
    "items": [
      {
        "record_id": 123,
        "values": {
          "Nom": "Exemple"
        }
      }
    ],
    "pagination": {
      "total": 1,
      "limit": 20,
      "start": 0
    }
  }
}
```

Seuls les champs autorisés par l'API apparaissent dans `values`.

## Lire un détail

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&record_id=123
```

Permissions : **API + Voir**.

Format par défaut :

```json
{
  "success": true,
  "messages": [],
  "data": {
    "record_id": 123,
    "form_id": 3,
    "fields": {
      "Nom": "Exemple"
    },
    "navigation": {
      "previous": 122,
      "next": 124
    }
  }
}
```

Avec `verbose=1`, chaque champ contient :

```json
{
  "reference_id": "17",
  "label": "Nom",
  "value": "Exemple"
}
```

## Mettre à jour un enregistrement

Méthodes acceptées : `PUT`, `PATCH` et `POST`.

```text
/index.php?option=com_contentbuilderng&task=api.display&id=3&record_id=123
```

Payload :

```json
{
  "fields": {
    "Nom": "Nouveau nom",
    "Email": "contact@example.test"
  }
}
```

Permissions : **API + Éditer**.

`record_id` est obligatoire. Les clés peuvent être des noms de champs ou, pour les
références numériques reconnues, des identifiants de champs. Les champs non autorisés
sont ignorés ; si aucun champ autorisé ne reste, la requête est refusée.

La création d'un nouvel enregistrement par API n'est pas démontrée par le contrôleur :
**À vérifier**. Le code exige actuellement un `record_id` pour `POST`.

## Valeurs uniques

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&action=get-unique-values&field_reference_id=17
```

Paramètres :

- `field_reference_id` : référence du champ ;
- `where_field` : champ de condition optionnel ;
- `where` : valeur de condition optionnelle.

Permissions : **API + List Access**.

Les deux champs de référence doivent être autorisés par l'API.

Réponse :

```json
{
  "success": true,
  "messages": [],
  "data": {
    "code": 0,
    "field_reference_id": "17",
    "msg": ["Valeur A", "Valeur B"]
  }
}
```

## Évaluation

```text
POST /index.php?option=com_contentbuilderng&task=api.display&id=3&action=rating&record_id=123&rate=5
```

Permissions : **API + Évaluation**.

L'action refuse les méthodes autres que `POST`. Le nombre de niveaux dépend du
paramètre d'évaluation de la vue (`rating_slots`). Le contrôleur utilise la session et
l'adresse IP pour limiter les votes répétés.

> ⚠️ **Attention :** l'action `rating` exige un **jeton CSRF Joomla** valide. Le
> contrôleur appelle `Session::checkToken` (en `post` ou `get`) et renvoie une erreur
> `JINVALID_TOKEN` (403) si le jeton est absent ou invalide. Un appel externe doit donc
> disposer d'une session Joomla authentifiée et transmettre le jeton de formulaire.

## Statistiques

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&action=stats
```

Permission : **Stats uniquement**.

Réponse :

```json
{
  "success": true,
  "messages": [],
  "data": {
    "form": {
      "id": 3,
      "name": "Contacts",
      "title": "Contacts publics"
    },
    "records": {
      "total": 31,
      "published": 9,
      "unpublished": 22,
      "future": 0,
      "edited": 5,
      "scheduled": 0,
      "expired": 0,
      "last_update": "2026-06-04 19:01:43"
    },
    "ratings": {
      "rated_records": 0,
      "rating_count": 0,
      "rating_sum": 0,
      "average": 0
    },
    "languages": {
      "*": 31
    }
  }
}
```

### Grouper par champ

```text
&action=stats&field=Parcours
```

Le champ peut être recherché par référence, nom ou label, mais il doit être publié et
autorisé par l'API.

Lorsque toutes les valeurs distinctes du champ sont numériques, la charge utile
`field` renvoie aussi les agrégats `sum` (pondéré par le nombre d'enregistrements),
`min` et `max`. Lorsque toutes les valeurs distinctes sont des dates ISO
(`AAAA-MM-JJ`, avec une heure optionnelle `HH:MM` ou `HH:MM:SS`), `min` et `max`
renvoient la date la plus ancienne et la plus récente, `sum` restant `null`.
Sinon, les trois clés valent `null`.

### Filtrer

```text
&action=stats&filter[field]=Parcours&filter[value]=200%20km*
```

Règles :

- espaces de début et fin ignorés ;
- `*` représente une suite quelconque de caractères ;
- `|` sépare les alternatives.

Exemple :

```text
filter[value]=200 km* | 300 km*
```

## Sparse fieldsets

Sur les requêtes `GET` :

```text
&fields[items]=record_id,Nom,Email
&fields[fields]=Nom,Email
&fields[records]=total,published
&fields[ratings]=average
```

Les ressources non citées sont supprimées de la réponse. Pour conserver plusieurs
ressources, utilisez plusieurs paramètres `fields[...]`.

Exemple statistiques :

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&action=stats&fields[records]=total&fields[ratings]=average
```

## Erreurs courantes

| Message | Cause probable |
| --- | --- |
| Vue introuvable | mauvais ID ou vue absente |
| Vue BF introuvable | source BreezingForms absente |
| Accès API refusé | permission API manquante |
| Accès statistiques refusé | permission Stats manquante |
| Champ non autorisé pour API/Stats | champ non publié ou case API non activée |
| `record_id` obligatoire | mise à jour sans identifiant |
| Aucun champ fourni | payload absent ou invalide |

## Authentification

L'API utilise l'identité et la session Joomla de la requête. Le dépôt ne fournit pas
dans ces fichiers un mécanisme autonome documenté de jeton API permanent :
**À vérifier** selon l'authentification mise en place sur votre site.

