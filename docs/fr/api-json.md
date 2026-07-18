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

### Plugin de contenu et API URL CBStats

Dans un contenu Joomla, `export=manual` peut être ajouté aux balises Pie, Bar et Table. Il affiche les valeurs finales normalisées et une balise `source=manual` visible et copiable. Cette option de présentation ne fait pas partie du contrat des sorties URL/API.

Le plugin de contenu CBStats utilise une source normalisée unique pour ses sorties
Table, JSON, Pie et Bar. Son contrat JSON est un tableau brut contenant des
libellés sous forme de chaînes et des valeurs entières :

```text
{CBStats id=3 field=NomDuChamp output=json sort=title dir=asc}
```

```json
[
  {"label":"Valeur A","value":12},
  {"label":"Valeur B","value":7}
]
```

Le même moteur est disponible via ce point d'entrée ContentBuilder NG existant :

```text
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=NomDuChamp&output=json
```

#### Sorties URL prises en charge

| `output` | Réponse | `field` obligatoire |
| --- | --- | --- |
| `json` | Tableau normalisé brut | Oui |
| `total` | Nombre d'enregistrements correspondants | Non |
| `sum` | Somme numérique pondérée | Oui |
| `min`, `max` | Minimum/maximum numérique, ou borne chronologique d'une date ISO | Oui |
| `form_name` | Titre ou nom de la vue | Non |

En l'absence de `output`, le point d'accès utilise `json` par défaut ; `field` est
donc obligatoire. `table`, `pie` et `bar` restent réservés au contenu et sont
refusés par l'API URL. JSON réutilise le traitement commun de `add` signé et de
`titles`.

#### Paramètres

- `id` : identifiant positif obligatoire de la vue ContentBuilder NG ;
- `field` : obligatoire pour `json`, `sum`, `min` et `max` ;
- `filter[field]` et `filter[value]` : facultatifs, mais obligatoirement fournis ensemble ;
- `sort=none|title|value` : facultatif et lu uniquement pour `json`, défaut `none` ;
- `dir=asc|desc` : facultatif et lu uniquement pour `json`, défaut `asc`.
- `add=Libellé=EntierSigné;...` : facultatif et lu uniquement pour `json` ;
- `titles=Original=Titre affiché;...` : facultatif et lu uniquement pour `json`.

Les sorties scalaires ignorent `sort` et `dir`.

Les valeurs de filtre sont nettoyées de leurs espaces de début et de fin. `*`
représente une suite quelconque de caractères et `|` sépare les alternatives. Un
filtre fourni doit contenir au moins une alternative non vide.
`sort=none` conserve l'ordre naturel du moteur, `sort=title` applique un ordre
naturel des titres affichés finaux selon la langue active et `sort=value` compare
les nombres finaux. Si un résultat de `add` est négatif, CBStats utilise
temporairement `0` pour ce libellé avant les titres, le tri, les pourcentages et
l'output. Les données sources restent inchangées et un résultat ultérieur nul ou
positif est utilisé normalement. Cette règle s'applique aussi à un libellé absent
recevant un delta négatif. Les mappings de titres ne modifient que l'affichage et
ne fusionnent jamais les catégories.

Exemples complets :

```text
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&output=total
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&output=form_name
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=sum
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=min
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=max
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Catégorie&output=json&filter[field]=Statut&filter[value]=Ouvert*%20%7C%20En%20attente&sort=value&dir=desc
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Catégorie&output=json&add=1%3D-2%3B2%3D3&titles=1%3DGroupe%201%3B2%3DGroupe%202
```

#### Réponses, permissions et DEBUG

`output=json` retourne le tableau brut présenté ci-dessus, directement comparable
à la sortie JSON d'une balise d'article. Les sorties scalaires utilisent
l'enveloppe de succès API standard :


```json
{"success":true,"messages":[],"data":31}
```

`action=cbstats` exige la permission **Stats** de la vue. Il n'ajoute volontairement
pas la permission API générale utilisée par les points d'accès aux listes et aux
détails d'enregistrements. Un champ demandé doit néanmoins être publié et autorisé
pour API/Stats. La requête utilise l'identité et la session Joomla courantes ;
DEBUG ne modifie jamais ces permissions.

Lorsque DEBUG est désactivé sur la vue, les erreurs utilisent l'enveloppe API sobre
et n'énumèrent ni les sorties prises en charge, ni les vues ou champs inaccessibles.
Lorsque DEBUG est activé, les diagnostics 4xx sûrs peuvent être plus précis. Les
erreurs serveur restent génériques. L'API n'exige et n'utilise aucun paramètre de
requête `debug=1` supplémentaire.

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

