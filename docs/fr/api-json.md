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

L'API utilise l'identité déjà établie par une session administrateur/site Joomla.
Une vue peut aussi autoriser les visiteurs anonymes ; ses permissions restent alors
applicables. Ce point d'accès site ne fournit pas de mécanisme autonome de jeton API permanent.

Les réponses déclarent `Cache-Control: private, no-store`, `Pragma: no-cache`,
`X-Content-Type-Options: nosniff` et `Vary: Authorization, Cookie`.

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
`list[limit]` vaut 20 par défaut et est plafonné à 100 par le serveur. La valeur 0
ne permet pas de demander tous les enregistrements par l'API.

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

Les requêtes d'écriture exigent une session Joomla et l'en-tête
`X-CSRF-Token`. Sa valeur est le nom du jeton de formulaire Joomla courant,
obtenu côté serveur avec `Session::getFormToken()`.

`record_id` est obligatoire. Les clés peuvent être des noms de champs ou, pour les
références numériques reconnues, des identifiants de champs. Les champs non autorisés
sont ignorés ; si aucun champ autorisé ne reste, la requête est refusée.

Cette API ne crée pas d'enregistrement : `record_id` est aussi obligatoire avec `POST`.
Un corps déclaré `application/json` invalide est refusé avec une erreur 400.

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
La réponse est plafonnée à 100 valeurs.

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
> contrôleur vérifie `X-CSRF-Token` ou le jeton de formulaire Joomla et renvoie une
> erreur `JINVALID_TOKEN` (403) si le jeton est absent ou invalide.

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
Table, JSON, Pie, Bar, Histogram, Line et Radar. Son contrat JSON est un tableau brut contenant des
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
| `table`, `pie`, `bar`, `histogram`, `line`, `radar` | Statistiques normalisées | Oui |
| `distinct` | Nombre de valeurs distinctes non vides du champ filtré | Oui |
| `total` | Nombre d'enregistrements correspondants | Non |
| `sum` | Somme numérique pondérée | Oui |
| `min`, `max` | Minimum/maximum numérique, ou borne chronologique d'une date ISO | Oui |
| `avg` | Moyenne arithmétique des valeurs numériques individuelles retenues | Oui |
| `view_name` | Nom de la vue ContentBuilder NG | Non |

En l'absence de `output`, le point d'accès utilise `json` par défaut ; `field` est
donc obligatoire. Les requêtes URL pour Table et les graphiques retournent les
mêmes statistiques normalisées que les renderers de contenu. JSON réutilise le
traitement commun de `add` signé et de `titles`.

#### Paramètres

- `id` : identifiant positif obligatoire de la vue ContentBuilder NG ;
- `field` : obligatoire pour `json`, toutes les sorties de liste/graphiques, `distinct`, `sum`, `min`, `max` et `avg` ;
- `filter[field]` et `filter[value]` : facultatifs, mais obligatoirement fournis ensemble ;
- `sort=none|title|value` : facultatif pour les sorties de liste/graphiques, défaut `none` ;
- `dir=asc|desc` : facultatif pour les sorties de liste/graphiques, défaut `asc`.
- `add=Libellé=EntierSigné;...` : facultatif pour les sorties de liste/graphiques ;
- `titles=Original=Titre affiché;...` : facultatif pour les sorties de liste/graphiques.
- `hide=title|total|values|graph` : sélection de présentation facultative. Les balises
  d’article et les requêtes URL utilisent le même parser et les mêmes contrôles.

Les sorties scalaires ignorent `sort` et `dir`.

`hide="title"` masque le titre du bloc ou de la Card défini par `labels="title=..."`.
`hide="total"` masque uniquement le Total affiché, `hide="values"` masque
uniquement la liste textuelle des libellés et valeurs sous le graphique sans
modifier le graphique, et `hide="graph"` masque le dessin tout en conservant
cette liste textuelle légère. Ces valeurs peuvent
être combinées avec `|` dans n’importe quel ordre. Les options non applicables
et les combinaisons masquant tout le résultat sont refusées. L’ancienne syntaxe
`total=hide` est refusée.

```text
{CBStats id=3 field=NomDuChamp output=bar hide="total"}
{CBStats id=3 field=NomDuChamp output=radar hide="graph|total"}
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=NomDuChamp&output=bar&hide=total
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=NomDuChamp&output=bar&hide=graph%7Ctotal
```

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
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&output=view_name
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=sum
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=min
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=max
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Montant&output=avg
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Age&output=histogram&groups=18-29%3B30-39%3B40-49%3B50%2B
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=DateInscription&output=line&sort=title&dir=asc&limit=30
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Age&output=radar&groups=18-29%3B30-39%3B40-49%3B50-59%3B60%2B
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

### Groupes de valeurs et outputs visuels CBStats

L’URL `action=cbstats` accepte `avg`, `histogram`, `line` et `radar`, en plus
des sorties de liste, scalaires et graphiques déjà disponibles. `avg` calcule
la moyenne arithmétique des valeurs numériques individuelles après les ACL et
les filtres ; les valeurs vides ou non numériques sont ignorées. Exemple :

```text
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=3&field=Age&output=avg
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=15&field=Civilite&value=H&output=percentage
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=15&output=progress&target=200
GET /index.php?option=com_contentbuilderng&task=api.display&action=cbstats&id=15&field=Age&output=histogram&groupset=ages-fr-FR.ini
```

`groups=18-29;30-39;40-49;50-59;60+` crée des groupes par intervalles inclusifs,
dans l’ordre déclaré. `groups=1,2,7,9=Groupe%201;3,4,8=Groupe%202` crée des
groupes explicites de valeurs non contiguës. Les chevauchements sont autorisés
et chaque groupe est compté indépendamment. Les graphiques retournent les
données normalisées `total` et `items`, sans HTML. Histogram est vertical, Line
conserve l’ordre final des catégories et Radar est recommandé avec 4 à 6 axes
(minimum 3, maximum 8).
