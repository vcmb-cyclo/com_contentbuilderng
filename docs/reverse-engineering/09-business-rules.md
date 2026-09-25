# 09 — Règles métier

> Rétro-analyse (reverse engineering) de `com_contentbuilderng` — Joomla 6 / PHP 8.3+
> / MySQL-MariaDB. Ce document documente les **règles métier implicites**,
> codées en dur dans le code du composant : conditions de publication, états
> et transitions, obligation/optionnalité des champs, valeurs par défaut et
> leur logique de déclenchement, dépendances entre champs, règles de
> visibilité au-delà de l'ACL Joomla, règles de calcul, comportements
> conditionnés par la configuration, priorités d'application et héritage.
>
> Documentation uniquement — **aucun fichier de code n'a été modifié** pour
> produire ce document. Le code est la source de vérité ; le présent document
> n'en est qu'une lecture organisée.
>
> Méthode de qualification des affirmations (identique aux autres livrables
> du dossier) :
> - **Fait observé** : lu directement dans le code, référence `fichier:ligne`.
> - **Comportement déduit** : assemblé à partir de plusieurs faits observés.
> - **Hypothèse** : interprétation plausible, non entièrement vérifiée.
> - **Zone inconnue** : point non tranché, incohérence potentielle, ou dette
>   technique à signaler.
>
> Pour chaque règle, une ligne **Conséquence** explique ce qui se passe
> concrètement si elle est violée ou ignorée (message d'erreur, blocage,
> comportement silencieux). Ce document renvoie à `03-data-model.md` pour le
> détail des colonnes/tables, à `08-configuration.md` pour la liste exhaustive
> des paramètres `#__extensions.params`, et à `07-security.md` pour l'ACL
> Joomla native et les contrôles CSRF/upload déjà traités là-bas — il ne les
> reproduit pas, sauf quand la règle métier qu'ils déclenchent doit être
> explicitée ici.

## Sommaire

1. [Cycle de vie et publication d'un enregistrement](#1-cycle-de-vie-et-publication-dun-enregistrement)
2. [États de liste (`list_states`) et routage d'action (trash/untrash)](#2-états-de-liste-list_states-et-routage-daction-trashuntrash)
3. [Champs obligatoires vs optionnels — validation des Storage Fields](#3-champs-obligatoires-vs-optionnels--validation-des-storage-fields)
4. [Valeurs par défaut et logique de déclenchement](#4-valeurs-par-défaut-et-logique-de-déclenchement)
5. [Dépendances entre champs](#5-dépendances-entre-champs)
6. [Règles de visibilité (au-delà de l'ACL Joomla)](#6-règles-de-visibilité-au-delà-de-lacl-joomla)
7. [Règles de calcul](#7-règles-de-calcul)
8. [Comportements conditionnés par la configuration](#8-comportements-conditionnés-par-la-configuration)
9. [Priorités et ordres d'application](#9-priorités-et-ordres-dapplication)
10. [Héritage de configuration](#10-héritage-de-configuration)
11. [Règles spécifiques formalisées (list_states.action, audit/repair, vérification/captcha)](#11-règles-spécifiques-formalisées-list_statesaction-auditrepair-vérificationcaptcha)
12. [Tableau récapitulatif — violation → conséquence](#12-tableau-récapitulatif--violation--conséquence)

---

## 1. Cycle de vie et publication d'un enregistrement

### 1.1 Deux systèmes d'état orthogonaux, jamais fusionnés

**Fait observé/Comportement déduit** — ContentBuilder NG ne connaît **pas**
un état de publication unique par enregistrement, mais **trois** signaux
distincts, gérés par trois acteurs différents :

| Signal | Table.colonne | Qui l'écrit | Rôle |
|---|---|---|---|
| Publication CBNG | `#__contentbuilderng_records.published` | `EditModel::store()` (soumission), plugin système (fenêtre programmée) | « Cet enregistrement est-il visible côté vue CBNG ? » |
| État de liste | `#__contentbuilderng_list_records.state_id` | `EditModel` (changement d'état de liste, toujours, quel que soit le plugin routé) | Badge de workflow libre (« Validé », « En attente », etc.) |
| État de l'article Joomla lié | `#__content.state` | `ArticleService::createArticle()` (génération), puis le plugin `contentbuilderng_listaction` routé par `list_states.action` (`trash`/`untrash`) | Publication/corbeille de l'article Joomla généré, indépendante de CBNG |

**Conséquence** : changer l'état de liste d'un enregistrement (front ou
admin) **ne dépublie jamais** automatiquement l'enregistrement CBNG
lui-même (`records.published` n'est pas touché par ce changement) ; seul un
plugin `listaction` explicitement routé (§2) peut agir sur l'article Joomla,
et seule une action front/admin dédiée ou la fenêtre programmée (§1.2) agit
sur `records.published`. Un enregistrement peut donc être « publié CBNG »
tout en ayant un article Joomla « à la corbeille », ou l'inverse — c'est un
état cohérent, pas une anomalie.

### 1.2 Fenêtre de publication programmée (`publish_up`/`publish_down`)

**Fait observé** (`plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php`,
`onAfterRoute()`) : à chaque requête de **mutation** détectée
(`isSyncMutationRequest()`), le plugin système exécute un `UPDATE` qui
republie/dépublie `records.published` selon que `now()` est dans la fenêtre
`[publish_up, publish_down]`, et positionne/retire `is_future`.

**Comportement déduit** : la publication programmée n'est **pas** un
mécanisme cron/planifié — elle dépend entièrement du trafic réel du site
(prochaine requête de mutation qui passera par ce plugin). Sur un site à
trafic très faible, un enregistrement dont `publish_up` est atteint peut
rester invisible plusieurs heures/jours après l'heure programmée, jusqu'à ce
qu'une requête de mutation quelconque (pas nécessairement liée à cet
enregistrement) relance le contrôle.

**Conséquence** : aucun message d'erreur — comportement silencieux. Un
enregistrement « programmé » qui n'apparaît pas à l'heure prévue n'est pas
un bug applicatif au sens strict, mais une conséquence de l'absence de tâche
planifiée dédiée (`com_scheduler` non utilisé, confirmé absent des fichiers
lus).

### 1.3 `published_only` — visibilité restreinte même pour le propriétaire

**Fait observé** (`site/src/Helper/PublishedRecordVisibilityHelper.php`) :

```php
public static function shouldRestrictToPublishedOnly(object $data, bool $isAdminPreview): bool
{
    return !$isAdminPreview && (bool) ($data->published_only ?? false);
}
```

**Comportement déduit** : quand `forms.published_only = 1`, **tous** les
enregistrements non publiés sont masqués des listes/détails/exports —
**y compris pour leur propriétaire** et pour un administrateur naviguant
normalement (pas en mode preview signé). Ce filtre est indépendant de
`own_only`/`own_only_fe` (§6.1) : les deux filtres se cumulent quand ils
sont actifs simultanément. Seul le lien de **prévisualisation admin signé**
(HMAC, `07-security.md` §1.3.2) contourne ce filtre, puisque c'est le seul
cas où `$isAdminPreview` vaut `true`.

**Conséquence** : un enregistrement dépublié reste invisible à son auteur
tant que `published_only` est actif sur la vue — pas de message d'erreur
distinct, l'enregistrement disparaît simplement de la liste/du détail
(comportement silencieux, potentiellement déroutant pour l'utilisateur qui
vient de soumettre son propre enregistrement).

### 1.4 Retro-création de `records` pour un storage `bytable=1` déjà peuplé

**Fait observé** (`admin/src/Model/StorageModel.php:962-1001`,
`syncStorageDataTableOrBytable()`) : quand un storage existant est rattaché
en écriture (`bytable=1`) et que la table cible contient déjà des lignes,
CBNG **rétro-crée** une ligne `#__contentbuilderng_records` par ligne
physique préexistante, avec `published=1` **fixe** (pas de valeur
paramétrable, pas de reprise d'un éventuel état de publication déjà présent
dans la table source).

**Conséquence** : toute donnée préexistante rattachée à CBNG via
`bytable=1` devient **immédiatement visible** côté vues publiées de ce
storage, sans étape de relecture/validation intermédiaire — à anticiper
avant de rattacher une table contenant des données sensibles ou incomplètes.

### 1.5 Synchronisation publication ↔ blocage de compte (`act_as_registration`)

**Fait observé** (`plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php`,
`onAfterRoute()` et `onAfterInitialise()`/`onAfterInitialize()`) : quatre
`UPDATE` multi-tables MySQL bruts (syntaxe `UPDATE t1, t2, t3, t4 SET ...`)
synchronisent, pour les enregistrements issus d'une vue
`act_as_registration=1` :
- `records.published = 0` quand l'utilisateur Joomla auteur est bloqué
  (`#__users.block = 1`), `records.published = forms.auto_publish` quand il
  est débloqué (dans `onAfterRoute()`) ;
- `#__content.state` de l'article lié, symétriquement (dans
  `onAfterInitialise()`/`onAfterInitialize()`, côté site uniquement).

**Comportement déduit** : bloquer un compte Joomla créé via une vue
d'inscription CBNG **dépublie automatiquement** son enregistrement CBNG et
son article Joomla lié, à la prochaine requête de mutation qui traverse le
plugin système — sans action explicite sur l'enregistrement ou l'article
eux-mêmes. Débloquer le compte les republie, mais uniquement jusqu'à la
valeur `forms.auto_publish` de la vue (donc pas nécessairement republié si
`auto_publish=0`).

**Conséquence** : un administrateur qui bloque un utilisateur pour des
raisons de modération de compte fait disparaître, en cascade et sans
notification distincte, tout le contenu qu'il a soumis via des vues
d'inscription — comportement volontaire (modération globale d'un compte
suspect) mais à connaître avant de bloquer un compte pour un motif sans
rapport avec son contenu soumis.

---

## 2. États de liste (`list_states`) et routage d'action (trash/untrash)

### 2.1 Dix états par défaut, quatre publiés, aucune action assignée

**Fait observé** (`admin/src/Model/FormModel.php`, `buildDefaultListStates()`) :
à la création d'une vue (ou dès qu'elle a moins d'états que le standard), CBNG
provisionne exactement **10** états par défaut, avec des couleurs fixes et
**seuls les 4 premiers publiés** :

| # | Titre | Couleur | Publié par défaut | `action` par défaut |
|---|---|---|---|---|
| 1 | `COM_CONTENTBUILDERNG_LIST_STATE_DEFAULT_TITLE` (1) | `60E309` (vert) | oui | *(vide)* |
| 2 | idem (2) | `FF9800` (orange) | oui | *(vide)* |
| 3 | idem (3) | `FCFC00` (jaune) | oui | *(vide)* |
| 4 | idem (4) | `FC0000` (rouge) | oui | *(vide)* |
| 5–10 | idem (5..10) | `FFFFFF` (blanc) | non | *(vide)* |

**Comportement déduit** — conséquence critique pour §11.1 : **aucun état
n'a d'action (`action`) préremplie**. Le routage `trash`/`untrash`
(§2.2/§11.1), qui dépend entièrement de la colonne `list_states.action`,
n'a donc **jamais d'effet tant qu'un administrateur n'a pas explicitement
saisi `action = 'trash'` (ou `'untrash'`, ou le nom de tout autre plugin du
groupe `contentbuilderng_listaction`) sur un état donné**, dans l'onglet
« États de liste » de la vue. La fonctionnalité « corbeille » n'est donc pas
activée par défaut — c'est une configuration explicite, pas un
comportement natif.

**Conséquence si non configuré** : basculer un enregistrement vers un état
sans `action` associée met à jour `list_records.state_id` normalement
(aucune erreur), mais **aucun effet de bord** ne se produit sur l'article
Joomla lié — comportement entièrement silencieux (pas de message d'erreur,
pas de log signalant l'absence de plugin routé).

### 2.2 Complétion des états manquants — par index fixe, pas par correspondance de titre

**Fait observé** (`admin/src/Model/FormModel.php:1553-1601`) : quand une
vue a moins d'états que les 10 par défaut (`existingCount < defaultCount`),
CBNG **complète le delta** en insérant les états manquants
`$this->_default_list_states[$existingCount + $i]` — c'est-à-dire par
**position dans le tableau des valeurs par défaut**, pas par correspondance
d'identifiant ou de titre avec les états déjà présents.

**Comportement déduit** : si un administrateur a supprimé (indirectement,
en pratique impossible — voir `03-data-model.md` §7, aucune suppression
individuelle d'état trouvée) ou si l'installation initiale n'a créé que
certains états, une complétion ultérieure ajoutera les états `#(existingCount+1)`
à `#10` de la séquence par défaut — jamais un état « manquant » spécifique
identifié par titre. **Zone inconnue** : ce cas ne se produit en pratique
que si le nombre d'états d'une vue a été réduit par une voie non identifiée
dans le code lu (import de configuration avec un jeu partiel, par exemple),
la mécanique de suppression individuelle d'état n'existant pas côté UI.

### 2.3 Édition d'un état existant — mise à jour ciblée par `id`

**Fait observé** (`admin/src/Model/FormModel.php:1536-1551`) : les états
dont l'`id` posté est `> 0` sont mis à jour par `UPDATE ... WHERE form_id =
:formId AND id = :sid` — `title`, `color` (normalisée en hexadécimal
majuscule sans `#`, `normalizeListStateColor()`), `action` et `published`
sont réécrits inconditionnellement à chaque sauvegarde de la vue, même si
seule une autre section du formulaire (ex. onglet Article) a été modifiée.

**Conséquence** : soumettre le formulaire d'édition de vue avec un onglet
« États de liste » resté à l'état par défaut du navigateur (valeurs non
rechargées, cas d'un post partiel/scripté) réécrirait silencieusement les
états avec ces valeurs — comportement standard de formulaire HTML complet,
pas de garde applicative spécifique observée contre une soumission
partielle de cet onglet.

### 2.4 Deux colonnes d'état, deux acteurs — voir aussi §11.1

Voir §11.1 pour le détail complet du contrat `onBeforeAction`/`onAfterAction`/
`onAfterArticleCreation` et la garde anti-résurrection de `trash`.

---

## 3. Champs obligatoires vs optionnels — validation des Storage Fields

### 3.1 Le caractère obligatoire est une propriété du **storage**, pas de la vue

**Fait observé** (`admin/src/types/com_contentbuilderng.php:939-953`,
`getRequiredElementIds()`) : la liste des champs obligatoires d'un
enregistrement est calculée en lisant **directement**
`#__contentbuilderng_storage_fields.required` (via `$this->elements`,
peuplé dans le constructeur `:66-74` par une requête sur `storage_fields`,
filtrée `COALESCE(published, 1) = 1`), en excluant les champs système
(`StorageSystemFieldHelper::isSystemFieldName()`). **La table `elements`
(la configuration par vue) ne porte aucune colonne `required`** — confirmé
par l'inventaire complet des colonnes `elements` en `03-data-model.md` §5 :
aucune colonne de ce nom.

**Comportement déduit — conséquence majeure** : le caractère obligatoire
d'un champ est **partagé par toutes les vues** qui exposent ce storage.
Il est **impossible**, par la configuration d'une vue (onglet Éléments/
Options d'élément), de rendre optionnel pour cette vue un champ marqué
`required` au niveau du storage, ni de rendre obligatoire pour une seule
vue un champ qui ne l'est pas au niveau du storage. Seule l'édition du
champ dans l'écran **Storage** (§4 de `docs/reverse-engineering`
`04-features.md`/brouillon admin) change ce comportement, pour toutes les
vues à la fois.

**Conséquence si ignoré** : un intégrateur qui pense pouvoir « assouplir »
la validation d'un champ requis en le rendant simplement non-éditable pour
une vue particulière ne fait, en réalité, que masquer le champ — voir §3.2
pour la conséquence concrète (souvent pire : blocage total de la création).

### 3.2 Cas limite contre-intuitif — champ requis non éditable dans la vue

**Fait observé** (`site/src/Model/EditModel.php:1467-1502`, croisé avec
`site/src/Model/EditModel.php:804-834` et
`admin/src/Service/ListSupportService.php:158-169`) :

1. `getRequiredElementIds()` retourne **tous** les champs `required=1` du
   storage, indépendamment de `elements.editable`/`elements.published` pour
   la vue courante.
2. `$values` (le tableau des valeurs traitées) n'est peuplé que pour les
   champs réellement **éditables dans cette vue** : `elements.editable = 1
   AND elements.published = 1`, **puis** filtré une seconde fois par les
   restrictions de menu (`cb_menu_published_fields`/`cb_menu_edit_fields`,
   mode « nouvelle liste »/`{CBList fields=}`).
3. La boucle de contrôle des champs requis (`:1473-1502`) ne tolère
   l'absence d'un champ requis dans `$values` **que pour un enregistrement
   existant** (`$isExistingRecord`, édition partielle « sparse » tolérée,
   `:1480-1482`). **Pour une création**, l'absence est traitée comme une
   valeur vide → échec de validation.

**Comportement déduit — contre-intuitif** : si un champ est marqué
`required=1` au niveau du storage mais que la vue utilisée pour **créer**
un nouvel enregistrement ne le rend pas éditable (`elements.editable=0`,
champ dépublié pour cette vue, ou exclu par une restriction de menu/mode
embarqué), **toute création via cette vue échoue systématiquement**, avec
le message `COM_CONTENTBUILDERNG_STORAGE_REQUIRED_VALUE` pointant un champ
que l'utilisateur ne peut, par construction, jamais renseigner depuis cet
écran. L'édition d'un enregistrement **existant** via la même vue restreinte
n'est, elle, jamais bloquée par ce même champ (branche `$isExistingRecord`).

**Conséquence** : création de vue systématiquement cassée pour toute vue
dont la configuration exclut un champ requis du storage — sans message
d'erreur explicite au moment de la configuration (l'écran Formulaire ne
prévient pas d'une telle incohérence ; seul `FormAuditService`, via les
paramètres `audit_field_missing_in_edit`/`audit_details_template_empty`,
pourrait signaler un champ absent d'un template, mais ne vérifie pas
spécifiquement ce croisement `required` × `editable`). **Recommandation
pour l'équipe** : à considérer comme un contrôle d'audit potentiel à
ajouter (`FormAuditService` couvre déjà un terrain proche).

### 3.3 Validation post-hoc par type SQL — indépendante des règles « métier »

**Fait observé** (`site/src/Model/EditModel.php:1504-1647`, s'appuyant sur
`admin/src/Helper/StorageColumnTypeHelper.php`) : après la boucle de
validation « métier » (`FieldValidationService`, règles configurées par
champ — §3.5), une **seconde** passe de contrôle, indépendante et non
désactivable par `enable_validations` (§8.1), vérifie que chaque valeur
postée est compatible avec le **type SQL réel de la colonne** :

| Type storage | Contrôle | Échec → message |
|---|---|---|
| `varchar` | Longueur UTF-8 (`mb_strlen`) ≤ taille de colonne (`getVarcharElementSizes()`) | `COM_CONTENTBUILDERNG_STORAGE_VARCHAR_LENGTH` |
| `int` | `StorageColumnTypeHelper::isValidIntegerValue()` — entier signé dans les bornes `INT_MIN`/`INT_MAX` (`-2147483648`/`2147483647`) | `COM_CONTENTBUILDERNG_STORAGE_INTEGER_VALUE` |
| `decimal` | Regex `^[+-]?(\d{1,11}(\.\d{1,4})?|\.\d{1,4})$` (cohérent avec `DECIMAL(15,4)`, §4.1) | `COM_CONTENTBUILDERNG_STORAGE_DECIMAL_VALUE` |
| `boolean` | Valeur ∈ `{null, '', 0, 1, '0', '1'}` uniquement | `COM_CONTENTBUILDERNG_STORAGE_BOOLEAN_VALUE` |
| `date`/`datetime` | `StorageColumnTypeHelper::isValidTemporalValue()` — format strict (`Y-m-d`/`Y-m-d H:i:s`), plage `1000-01-01`…`9999-12-31` | `COM_CONTENTBUILDERNG_STORAGE_DATE_VALUE`/`_DATETIME_VALUE` |

**Comportement déduit** : cette passe est **toujours active**, même quand
le paramètre composant `enable_validations` (§8.1) est désactivé — c'est un
garde-fou d'intégrité de données (compatibilité avec le type SQL réel), pas
une « validation métier » configurable par champ. Un formulaire dont toutes
les règles de validation par champ sont désactivées reste donc protégé
contre une valeur qui casserait un `INSERT`/`UPDATE` SQL typé.

**Conséquence** : blocage de la soumission (`cb_submission_failed=1`),
message ciblé sur le champ concerné, formulaire réaffiché avec les valeurs
déjà saisies restaurées (session `cb_failed_values`) — aucune perte de
saisie pour l'utilisateur.

### 3.4 Rendre un champ requis a posteriori — purge des valeurs vides existantes

**Fait observé** (`admin/src/Helper/StorageColumnTypeHelper.php:224-243`,
`enforceRequired()`) : quand un champ passe de facultatif à obligatoire
(colonne physique `NULL` → `NOT NULL`), CBNG **ne bloque jamais** l'opération
même si des lignes existantes ont une valeur `NULL` sur cette colonne : il
exécute d'abord un `UPDATE ... SET <col> = <littéral vide selon le type>
WHERE <col> IS NULL` (`''`/`0`/`'1970-01-01'`/`'1970-01-01 00:00:00'` selon
le type, `emptyValueLiteral()`), **puis** l'`ALTER TABLE ... MODIFY ... NOT
NULL`.

**Comportement déduit** : rendre un champ obligatoire réécrit
silencieusement les valeurs `NULL` préexistantes par une valeur « vide »
typée (ex. `0` pour un entier, `1970-01-01` pour une date) — ce n'est
**pas** une erreur bloquante côté DDL, mais une perte d'information
sémantique (`NULL` ≠ « valeur vide connue ») sur les lignes déjà présentes,
sans confirmation explicite demandée à l'administrateur au-delà de l'action
« rendre requis » elle-même.

**Conséquence** : aucune erreur visible ; l'administrateur qui consulte
ensuite les anciennes lignes verra des valeurs par défaut typées là où il y
avait auparavant une absence de valeur, ce qui peut fausser un filtre/tri
ultérieur (« aucune date » devient indiscernable de « 1er janvier 1970 »).

### 3.5 Validations configurables par champ — nature « facultative » par construction

**Fait observé** (`admin/src/Service/FieldValidationService.php`) : les
règles `notempty`, `equal`, `email`, `date_not_before`, `date_is_valid`
(plus les validations externes déclarées par des plugins
`contentbuilderng_validation` via `onValidate`) ne s'appliquent **que si**
elles sont explicitement cochées dans `elements.validations` (CSV) pour ce
champ. Une valeur vide sur un champ **non** requis (`storage_fields.required=0`)
et sans règle `notempty` cochée n'est **jamais** rejetée par cette couche —
c'est la définition même de « champ optionnel ».

**Conséquence** : un champ peut être facultatif au niveau du storage
(§3.1) **et** porter des règles de validation « format » (ex. `email`) qui
ne s'appliquent qu'à une valeur non vide — un champ e-mail facultatif laissé
vide passe la validation `email` (la règle ne s'exécute que sur la valeur
fournie ; voir `validateEmail()`, qui traite un tableau à un seul élément
`['']` — **Zone inconnue** : `ContentbuilderngHelper::isEmail('')` n'a pas
été relu ligne à ligne dans ce document pour confirmer si une chaîne vide
est traitée comme valide par cette fonction ; à vérifier avant toute
évolution de cette règle si le comportement exact sur chaîne vide devient
critique).

---

## 4. Valeurs par défaut et logique de déclenchement

### 4.1 Taille par défaut d'un champ storage — maximum du type, pas une valeur réduite

**Fait observé** (`admin/src/Helper/StorageColumnTypeHelper.php:93-105`) :
`defaultSize()` délègue à `maxSize()` — la taille **pré-remplie** à la
sélection d'un type `varchar`/`text` est déjà la taille **maximale**
autorisée pour ce type (`255` pour `varchar`, `65535` pour `text`), pas une
valeur intermédiaire prudente. L'administrateur doit **réduire**
explicitement la taille s'il veut une contrainte plus stricte ;
`normalizeSize()` (`:111-126`) borne toute saisie `< 1` à ce même maximum
par défaut, et plafonne toute saisie excessive au maximum du type (jamais
d'erreur, silencieusement tronqué).

**Conséquence** : un administrateur qui laisse la taille « par défaut »
obtient la contrainte la plus permissive possible pour le type choisi — la
validation de longueur (§3.3) ne devient donc réellement stricte que si la
taille est explicitement réduite.

### 4.2 États de liste par défaut — voir §2.1

Renvoi à §2.1 pour la table complète (10 états, couleurs, publication,
`action` toujours vide par défaut).

### 4.3 Réinitialisation des réglages « Article » — valeurs codées en dur

**Fait observé** (`admin/layouts/form/article_tab.php:50-64,389-424`,
fonction JS `cbResetArticleOptions()`) : le bouton « Réinitialiser » de
l'onglet Article restaure des valeurs **codées en dur** côté client
(`$articleDefaults`), en particulier `create_articles = 0`,
`delete_articles = 1`, `default_access = 0`, puis soumet automatiquement en
tâche `form.apply`.

**Comportement déduit** : la réinitialisation désactive la génération
d'articles (`create_articles=0`) mais **conserve** `delete_articles=1` —
cohérent avec une politique par défaut « ne crée pas d'article, mais si
un article existe/est créé plus tard, le supprimer suit la suppression de
l'enregistrement » (postulat de sécurité côté nettoyage).

**Conséquence** : cliquer « Réinitialiser » sur une vue qui avait
`create_articles=1` avec une configuration avancée désactive silencieusement
la génération d'articles pour les soumissions **futures** — les articles
**déjà créés** ne sont ni supprimés ni modifiés par cette action seule
(effet différé à la prochaine sauvegarde/soumission, cf. §7 du brouillon
admin, « Aucun effet de bord » à la sauvegarde de la configuration
elle-même).

### 4.4 Décalage de publication d'article — appliqué uniquement s'il y a déjà un article

**Fait observé** (`admin/src/Service/ArticleService.php:226-238`) : le
décalage `default_publish_up_days`/`default_publish_down_days` n'est
appliqué **que si `$article` (l'article Joomla déjà lié) existe** —
`is_array($article) && isset($article['article_id'])`. Pour la **toute
première** génération d'article d'un enregistrement (aucun article encore
lié), ce décalage n'a — dans la portion de code lue — **aucun effet
observé sur les dates initiales** ; c'est la date de soumission
(`$createdUp`/`$createdDown` initialisés depuis `records.publish_up`/`publish_down`,
sinon `$_now`) qui s'applique sans décalage supplémentaire tant qu'aucun
article n'existe déjà. **Zone inconnue** : ce point mériterait une relecture
plus large de `createArticle()` (au-delà de l'extrait lu, `:62-724`) pour
confirmer s'il existe un autre chemin qui applique le décalage dès la
création initiale — signalé ici comme risque d'incohérence entre « valeur
saisie dans l'onglet Article » et « effet réellement observé à la première
création d'article » plutôt qu'affirmé comme un fait clos.

**Conséquence potentielle si l'hypothèse se confirme** : un administrateur
configurant `default_publish_up_days=7` s'attendant à ce que **tout**
article généré soit différé de 7 jours pourrait constater que seule une
**resynchronisation** ultérieure d'un article déjà existant applique ce
décalage, pas la création initiale — à vérifier en environnement Joomla
réel avant toute communication utilisateur sur ce paramètre.

### 4.5 Cascade de pagination — voir §9.1 (priorités)

La logique de valeur par défaut de pagination (`default_list_limit`,
sentinelle `INHERIT = -1`) est un cas de **priorité en cascade** plutôt
qu'une simple valeur fixe — détaillée en §9.1 pour éviter la duplication.

### 4.6 Thème visuel — repli systématique sur `thoth`

**Fait observé** (`plugins/contentbuilderng_themes/*`,
`admin/src/Service/TemplateSampleService.php:33,42`,
`site/src/View/List/HtmlView.php:119-124,122`,
`admin/src/View/Edit/HtmlView.php`, `site/src/View/Details/HtmlView.php:466-472`,
`site/src/View/Edit/HtmlView.php:816-822`) : chaque site de résolution de
thème retombe sur la valeur **codée en dur** `'thoth'` si le plugin
configuré (`forms.theme_plugin` ou surcharge de menu) est introuvable/
désactivé.

**Comportement déduit** : désactiver ou désinstaller un plugin de thème
actif (`blank`/`dark`/`khepri`) ne casse **jamais** le rendu d'une vue —
elle bascule silencieusement sur `thoth`, sans message d'avertissement
visible côté administrateur (aucune chaîne `Text::_()` observée à ce
sujet dans les 4 fichiers d'extension de thème).

**Conséquence** : changement visuel silencieux d'une vue en production si
son thème configuré devient indisponible — à surveiller via l'audit
(§11.2) plutôt que via un message applicatif dédié (aucun vérificateur
d'audit spécifique au thème n'a été identifié dans la liste des 17
vérificateurs, §11.2 — **Zone inconnue**, à confirmer).

### 4.7 Métadonnées de champs système — toujours créées dépubliées

**Fait observé** (`admin/src/Model/StorageModel.php`,
`ensureSystemFieldMetadata()`, `:641-761`, commentaire explicite `:641-646`) :
quand une colonne physique système (`id`, `user_id`, `created`, etc.) est
détectée sans ligne `storage_fields` correspondante, CBNG crée cette ligne
avec `published = 0` — **délibérément**, pas par omission.

**Comportement déduit** : ces champs système restent utilisables pour le
tri/l'ordonnancement interne mais **invisibles par défaut** dans les
écrans qui filtrent sur `published=1` (liste des champs disponibles dans
l'écran Options d'élément, par exemple) — un administrateur doit les
publier explicitement pour les exposer, ce qui est la valeur par défaut la
plus sûre (évite d'exposer accidentellement `user_id`/`created_by` bruts
comme colonnes de formulaire).

**Conséquence si l'administrateur les publie sans réflexion** : ces champs
deviennent sélectionnables comme n'importe quel autre champ storage,
**sans** garde applicative additionnelle repérée qui les traiterait
différemment (au-delà du filtre `StoragefieldsModel::delete()` qui, lui,
protège spécifiquement ces noms contre la **suppression**, §3 de
`03-data-model.md`).

### 4.8 Quotas — 0 = illimité, pas « aucun droit »

**Fait observé** (`admin/src/Service/PermissionService.php:369-383`) : les
comparaisons de quota (`limit_add`/`limit_edit`, vue et utilisateur) ne
s'activent que si la limite est `> 0`. Une valeur `0` (défaut colonne
`install.sql`) signifie **« pas de limite »**, pas « zéro autorisation ».

**Conséquence** : un administrateur qui souhaite bloquer totalement les
soumissions d'un utilisateur via un quota ne peut pas le faire en posant
`limit_add=0` (interprété comme illimité) — il doit retirer le droit `new`
lui-même de la matrice de permissions (`config`), ou publier=0 la ligne
`#__contentbuilderng_users` correspondante (§10 de `03-data-model.md`).
Poser `limit_add=0` en pensant bloquer l'utilisateur est donc un **piège de
configuration silencieux** : aucune erreur, mais l'effet obtenu est
l'inverse de celui recherché.

---

## 5. Dépendances entre champs

### 5.1 Confirmation de valeur — convention de nommage `_repeat`

**Fait observé** (`admin/src/Service/FieldValidationService.php:154-173`,
`validateEqual()`) : la règle `equal` compare la valeur du champ courant à
celle du champ dont le **nom** est `<nom_du_champ>_repeat` — une
convention de nommage pure, pas une référence explicite par identifiant
dans la configuration du champ.

**Conséquence** : renommer le champ de confirmation sans respecter
exactement le suffixe `_repeat` (ou renommer le champ principal sans
renommer son homologue `_repeat`) **casse silencieusement** la règle
`equal` — elle ne trouve simplement aucun champ correspondant et retourne
une chaîne vide (`:169-172`), donc **aucune erreur de validation n'est
levée**, y compris quand les deux valeurs diffèrent réellement.

### 5.2 Chronologie de dates — convention de nommage `_later`

**Fait observé** (`admin/src/Service/FieldValidationService.php:214-255`,
`validateDateNotBefore()`) : même mécanisme que §5.1, avec le suffixe
`_later` — le champ `_later` doit contenir une date **postérieure ou
égale** au champ courant, sinon message
`COM_CONTENTBUILDERNG_VALIDATION_DATE_NOT_BEFORE`. Cette règle est
explicitement **incompatible avec les champs groupés** (répétés) — toute
valeur de type tableau retourne directement
`COM_CONTENTBUILDERNG_VALIDATION_DATE_NOT_BEFORE_GROUPS`.

**Conséquence** : appliquer `date_not_before` à un champ configuré en
« groupe » (répétable) bloque **systématiquement** toute soumission
comportant ce champ, quelle que soit la valeur saisie — configuration
incompatible à éviter, sans garde-fou à la configuration (l'écran Options
d'élément ne semble pas empêcher de cocher `date_not_before` sur un champ
`is_group=1` — **Zone inconnue**, non confirmé par lecture du template
correspondant).

### 5.3 Champ upload — « requis » évalué contre le fichier déjà en base

**Fait observé** (`admin/src/Service/FieldValidationService.php:117-152`,
`validateNotEmpty()`) : pour un champ de type `upload`, la règle
`notempty` ne considère **pas** uniquement la valeur postée : elle relit
l'enregistrement existant (`$form->getRecord($recordId, ...)`) et considère
le champ « rempli » si un fichier y est **déjà** attaché, même si aucun
nouveau fichier n'a été posté dans cette soumission précise.

**Comportement déduit** : ceci est ce qui permet l'édition d'un
enregistrement sans re-uploader un fichier obligatoire à chaque
modification — cohérent avec la tolérance « édition partielle » de §3.2.
Le contrôle échoue uniquement si **ni** l'enregistrement existant **ni** la
soumission courante ne porte de fichier.

### 5.4 Bypass de captcha — dépendance à la configuration d'affichage du champ

**Fait observé** (`site/src/Model/EditModel.php:804-834,912-936`, croisé
avec `admin/src/Service/ListSupportService.php:158-169`) : le test
Securimage n'est exécuté **que si** le champ `captcha` n'appartient pas à
`$noneditable_fields` — cet ensemble est construit à partir de :
`elements.editable = 0 OR elements.published = 0` (`ListSupportService::getListNonEditableElements()`),
**puis étendu** par toute exclusion issue des restrictions de menu
(`cb_menu_published_fields`/`cb_menu_edit_fields`, mode « nouvelle
liste »/`{CBList fields=}`).

**Comportement déduit — contre-intuitif, implication sécurité** : dépublier
l'élément `captcha` d'une vue (`elements.published=0`), le rendre non
éditable (`elements.editable=0`), ou simplement l'exclure d'un menu «
nouvelle liste »/d'une intégration `{CBList fields=}` **désactive
totalement la vérification captcha** pour toute soumission passant par ce
contexte — sans avertissement, sans message d'audit spécifique identifié
(le champ n'est simplement jamais évalué comme « présent » dans la vue, la
condition `$the_captcha_field !== null && !in_array(...)` échoue
silencieusement à la seconde partie).

**Conséquence** : un administrateur qui restreint les champs affichés
d'un formulaire d'inscription public via `{CBList fields=...}` (ou une
configuration de menu « nouvelle liste ») **sans lister explicitement le
champ captcha** parmi les champs autorisés désactive de facto la protection
anti-bot de ce point d'entrée — risque de sécurité opérationnel à
documenter/auditer, indépendamment de toute action malveillante (simple
erreur de configuration).

### 5.5 Mode inscription (`act_as_registration`) — dépendance « tout ou rien » sur 6 champs

**Fait observé** (`site/src/Model/EditModel.php:940`) : le bloc de
traitement d'inscription (validation nom/e-mail/mot de passe/nom
d'utilisateur, création du compte Joomla) ne s'exécute que si **les six**
mappings de champ (`registration_name_field`, `_username_field`,
`_password_field`, `_password_repeat_field`, `_email_field`,
`_email_repeat_field`) sont configurés **et** résolvent chacun vers un
élément réellement présent/éditable dans la soumission courante
(`$the_name_field !== null && $the_email_field !== null && ...`).

**Comportement déduit** : `forms.act_as_registration = 1` avec un seul des
six mappings manquant (élément supprimé côté source, champ dépublié, champ
exclu par restriction de menu) **désactive silencieusement toute
l'inscription** pour cette soumission — l'enregistrement CBNG est tout de
même créé (le reste du traitement continue normalement), mais **aucun**
compte Joomla n'est créé, sans message d'erreur dédié signalant
l'incohérence de configuration (le bloc entier est simplement sauté par la
condition composée).

**Conséquence** : un formulaire d'inscription apparemment fonctionnel (le
message de succès générique `COM_CONTENTBUILDERNG_SAVED` s'affiche) peut ne
créer **aucun** compte utilisateur si un seul des six champs mappés devient
indisponible — bug de configuration silencieux, particulièrement risqué
après une réorganisation de storage (renommage/suppression de colonne) qui
casserait un des six mappings sans le signaler.

### 5.6 Listes déroulantes dépendantes — API `get-unique-values`

**Fait observé** (`site/src/Controller/ApiController.php`, endpoint
`action=get-unique-values`, paramètres `field_reference_id`, `where_field`,
`where`) : ce point d'entrée retourne les valeurs distinctes d'un champ,
**filtrées** par la valeur d'un autre champ (`where_field`/`where`) — le
mécanisme explicite de « listes déroulantes en cascade » du composant
(ex. Pays → Ville). **Fait observé — double gate** : la requête est
refusée (`403`) si **l'un ou l'autre** des deux champs référencés
(`field_reference_id` et `where_field`) n'est pas marqué
`elements.api_allowed = 1` sur la vue concernée — indépendamment des droits
d'affichage/édition classiques.

**Conséquence** : un champ « source » pour une liste dépendante doit être
explicitement autorisé côté API (`api_allowed=1`), **même s'il n'est
jamais destiné à être exposé via l'endpoint CRUD `api.display` lui-même**
— oubli fréquent probable lors de la mise en place d'un formulaire à
listes en cascade, qui se traduirait par un échec `403`
(`COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED`/erreur générique côté client
JS) plutôt qu'une liste vide silencieuse.

### 5.7 `create_articles=1` sans catégorie — validation client uniquement

**Fait observé** (`admin/layouts/form/article_tab.php:262-334`) : si
`create_articles=1`, le champ `default_category` devient obligatoire
**côté navigateur uniquement** (`required`, `setCustomValidity()`). Aucune
revalidation serveur de cette contrainte n'a été localisée dans
`FormController::save()`/`FormModel::save()` (délégation complète au core
Joomla `AdminModel::save()`).

**Comportement déduit** : un enregistrement `jform[default_category]=0`
(valeur par défaut de la colonne, `install.sql:316`) peut être persisté en
contournant le JS (formulaire soumis par un client HTTP direct, ou
`default_category` laissé à `0` par une importation `ConfigImportService`,
§11 du brouillon admin). `ArticleService::createArticle()` utilise ensuite
`$categoryId = (int) $form['default_category']` (`:255`) **sans contrôle
d'existence de catégorie** dans la portion de code lue.

**Conséquence potentielle** : génération d'un article avec `catid = 0`
(catégorie invalide au sens Joomla) si cette chaîne de dépendance n'est
jamais revalidée côté serveur — **Zone inconnue**, non confirmée par
exécution réelle (pas d'environnement Joomla exécutable dans cette
analyse) ; signalée ici comme hypothèse de risque à vérifier en priorité
si `create_articles` est utilisé en production avec des formulaires
exposés à des clients non-navigateur.

---

## 6. Règles de visibilité (au-delà de l'ACL déjà couverte en `07-security.md`)

### 6.1 `own_only`/`own_only_fe` — filtre de **données**, distinct de la matrice « own » d'ACL

**Fait observé** (`site/src/Model/ListModel.php:1173-1174`, `DetailsModel.php:803-804`,
`ExportModel.php:407`, `EditModel.php:555,1070,1932`,
`admin/src/Helper/Audit/FrontendPermissionAuditHelper.php:33,101-102`) :
`forms.own_only` (contexte admin) et `forms.own_only_fe` (contexte front)
pilotent un filtre SQL `WHERE r.user_id = <utilisateur courant>` appliqué
**directement dans la requête de liste/détail/export**, distinct de la
matrice « own » consommée par `PermissionService` (§1.3 de
`07-security.md`, permissions par **action**).

**Comportement déduit — deux mécanismes non interchangeables** :
- `own_only(_fe)` : filtre de **visibilité des données** — un
  enregistrement qui n'appartient pas à l'utilisateur courant
  **disparaît purement et simplement** des résultats, quel que soit son
  droit `view` par ailleurs (même un administrateur avec tous les droits
  de groupe sur `view` ne verra, dans une liste front, que ses propres
  lignes si `own_only_fe=1`).
- Matrice « own » de `PermissionService` (permissions par groupe/`own`) :
  contrôle d'**action** — détermine si une action (view/edit/delete/…) est
  **autorisée**, lève une exception `403` en cas de refus (§6.2).

**Fait observé — audit dédié** :
`FrontendPermissionAuditHelper.php:101-102` signale explicitement
(`'own_only_fe is enabled, but no owner-specific frontend action is
active.'`) une configuration où `own_only_fe=1` est actif mais où
**aucune** action `view`/`listaccess`/`edit`/`delete`/`new` n'est accordée
en mode « own » dans `config` — un signe que l'administrateur a
probablement voulu restreindre la liste à « mes enregistrements » sans se
rendre compte que le droit d'action lui-même doit être accordé séparément.

**Conséquence** : activer `own_only_fe` sans accorder les actions « own »
correspondantes aboutit à une liste vide/action refusée pour tout
utilisateur non couvert par un droit de groupe — silencieux côté données
(liste vide, pas d'erreur), bloquant côté action (`403`) si l'utilisateur
tente d'agir sur un enregistrement qui n'apparaît même pas dans sa propre
liste.

### 6.2 Priorité de résolution des permissions — groupe avant « own », premier match gagnant

**Fait observé** (`admin/src/Service/PermissionService.php:417-570`,
`checkPermissions()`) : l'ordre de résolution est :
1. `published` (vue publiée, sauf preview) — sinon refus immédiat.
2. Quotas `limit_edit`/`limit_add` (uniquement pour `edit`/`new`) — refus
   immédiat si dépassé.
3. Gate de vérification (`verify_view`/`new`/`edit`, §6.4) pour les actions
   `edit`/`new`/`view`/`delete` — refus, ou redirection si une URL de
   vérification est configurée.
4. **Droits de groupe** (boucle sur `$permissions[$groupId][$action]`,
   `:467-472`) — premier groupe effectif qui accorde l'action **suffit**,
   la boucle s'arrête (`break`).
5. **Seulement si aucun groupe n'a accordé l'action** : évaluation de la
   matrice « own » (`:474-542`), avec vérification de propriété réelle en
   base (`session_id` pour l'anonyme, `$form->isOwner()` pour
   l'authentifié).

**Comportement déduit** : un droit de groupe **explicitement accordé**
prime toujours sur la restriction « own » — si un groupe a le droit
`edit`, tous ses membres peuvent éditer **tous** les enregistrements de la
vue, même ceux configurés en mode « own » pour ce groupe (le mode « own »
n'est évalué qu'en absence de tout droit de groupe direct pour cette
action). Il n'existe pas de mécanisme pour dire « ce groupe peut éditer
uniquement ses propres enregistrements » via la clé `permissions<suffix>`
seule — ce cas passe nécessairement par la matrice `own<suffix>`
(configuration distincte, pas un raffinement du droit de groupe).

**Conséquence** : une configuration ACL qui coche à la fois `permissions.<groupId>.edit`
**et** `own.edit` pour le même groupe rend la restriction « own » **inopérante**
pour ce groupe (le droit de groupe l'emporte systématiquement, la matrice
« own » n'étant jamais atteinte) — piège de configuration silencieux.

### 6.3 Quota individuel — remplace, n'additionne pas, le quota de la vue

**Fait observé** (`admin/src/Service/PermissionService.php:369-383`) :

```php
if ((int)$limit_edit > 0 && (int)$user_limit_edit > 0 && (int)$edited >= (int)$user_limit_edit) { … refus }
elseif ((int)$limit_edit > 0 && (int)$user_limit_edit <= 0 && (int)$edited >= (int)$limit_edit) { … refus }
```

**Comportement déduit** : si `#__contentbuilderng_users.limit_edit`
(surcharge individuelle) est **positif**, il **remplace intégralement** le
quota de la vue (`forms.limit_edit`) pour cet utilisateur — ce n'est ni un
plafond supplémentaire, ni une addition, c'est une substitution complète du
seuil de comparaison. Un utilisateur avec `limit_edit=100` sur une vue dont
`forms.limit_edit=5` peut éditer jusqu'à 100 enregistrements, pas 5+100.
Symétrique pour `limit_add`/`amount_records`.

**Conséquence** : poser une surcharge individuelle « généreuse » sur un
utilisateur neutralise totalement le quota global de la vue pour lui,
même si l'intention était de lui accorder « un peu plus » que les autres —
à documenter clairement pour l'administrateur configurant des quotas
individuels.

### 6.4 Expiration de vérification — `verification_days ≤ 0` signifie « définitivement valide »

**Fait observé** (`admin/src/Service/PermissionService.php:710-730`,
`resolveVerificationPermission()`) :

```php
$days = (float) $verification_days_<action> * 86400;
$validUntil = strtotime($verification_date_<action>) + $days;
if ($verified && ($now < $validUntil || $verification_days_<action> <= 0)) {
    return true; // accès autorisé
}
return $verification_url_<action> !== '' ? $verification_url_<action> : false;
```

**Comportement déduit** : `verification_days_<action> ≤ 0` (défaut colonne
`0`, `install.sql`) rend une vérification **permanente** dès qu'elle a été
effectuée une fois — le facteur temps n'entre jamais en jeu tant que cette
colonne reste à `0`. Ce n'est qu'en posant une valeur strictement positive
(nombre de **jours**, valeur `float` donc fractions de jour possibles) que
la vérification expire et doit être renouvelée.

**Conséquence si mal compris** : un administrateur pensant que
`verification_days_view=0` signifie « vérification requise à chaque
visite » obtient en réalité l'inverse — « vérifiée une fois, valable pour
toujours ». Pour exiger une revérification périodique, la valeur doit être
strictement positive (ex. `30` pour un mois).

**Conséquence côté utilisateur non vérifié/expiré** : si
`verification_url_<action>` est configuré, redirection automatique vers
cette URL (`checkPermissions()` appelle `$app->redirect()`) ; sinon,
refus `403` classique (`NotAllowed`).

### 6.5 Signed admin preview — droits larges mais jamais `delete`/`publish`/`state`

**Fait observé** (`admin/src/Service/PermissionService.php:628-674`,
`setStoragePreviewPermissions()`) : le mode « prévisualisation storage
direct » accorde, pour **tous** les enregistrements du storage (portée
« record-agnostic », `record_ids: null`, §CONTEXT_KEY) : `view`, `new`,
`edit`, `listaccess` (et `stats` pour les groupes), mais **jamais**
`delete`, `publish`, `state`, `rating`, `api`, `fullarticle`, `language` —
ces actions ne sont pas accordées par cette méthode. Elle fixe explicitement
la clé `published` à `true` (`PermissionService.php:641`) : le contrôle
initial de `checkPermissions()` sur cette clé ne refuse donc pas, à lui seul,
les actions `view`/`new`/`edit`/`listaccess` accordées par la prévisualisation.

### 6.6 `{CBList actions=}` (mode embarqué) — restriction stricte, jamais d'extension de droits

**Fait observé** (`site/src/Service/EmbeddedListActionFilterService.php:9-20,93-97`,
commentaire de code explicite) : `isAllowed()` retourne toujours
`$aclAllows && $embeddedAllows` — une intersection logique. Le paramètre
`actions=` d'une balise `{CBList}` ou `cblist_actions=` en requête ne peut
**jamais** accorder un droit que l'ACL/la configuration de vue
n'accordait pas déjà.

**Conséquence** : c'est une garantie de sécurité **positive** (pas un
risque) — un rédacteur d'article qui compose une balise `{CBList
actions=delete}` sur une vue où `delete` n'est pas accordé au visiteur ne
crée **pas** de faille : `ListController::assertConstrainedAction()`/
`EditController::assertConstrainedAction()`/`DetailsController::display()`
appellent ce filtre **avant** le contrôle ACL normal, et le résultat le
plus restrictif des deux prévaut toujours.

### 6.7 Storage direct auto-provisionné — profils de droits par défaut selon l'origine

**Fait observé** (`admin/src/Service/DirectStorageFormProvisioningService.php`,
commentaire `:36-43`, repris dans le brouillon site §2.1) : une vue
auto-créée pour exposer un storage sans configuration manuelle (« storage
direct ») reçoit des droits par défaut différents selon qui a déclenché la
création :
- **provisionnement admin délibéré** (Storage Wizard) : droits
  lecture/écriture « raisonnables », immédiatement utilisables ;
- **provisionnement déclenché par une requête front anonyme** : **lecture
  seule pour Invité, rien d'autre** — commentaire du code : « puisque
  personne ne l'a relue ».

**Comportement déduit** : c'est un principe de moindre privilège appliqué
automatiquement — exposer un storage interne « brut » sans jamais être
passé par l'assistant admin (ex. un lien direct `storage_id=` deviné/partagé
avant toute configuration volontaire) ne donne **jamais** de droit
d'écriture par défaut à un visiteur anonyme, même si le storage lui-même
ne porte aucune restriction.

**Conséquence** : un administrateur qui découvre une vue « auto-provisionnée
» avec des droits très restrictifs ne doit pas y voir un bug — c'est le
comportement de sécurité par défaut attendu ; il doit explicitement ouvrir
et ajuster les droits (onglet Permissions) pour élargir l'accès si
souhaité.

### 6.8 Storage `bytable=2` (lecture seule) — boutons d'action masqués, pas seulement refusés

**Fait observé** (`site/src/Model/ListModel.php`, mode storage direct,
`:326-331` du brouillon site) : en mode storage direct sur un storage
`bytable=2`, `edit_button`/`new_button` sont forcés à `0` **au niveau des
données transmises au gabarit**, pas seulement refusés au clic — la
suppression est par ailleurs bloquée explicitement côté contrôleur Storage
admin (`COM_CONTENTBUILDERNG_READONLY_EXTERNAL_STORAGE_MSG`, §2/§4 du
brouillon admin).

**Comportement déduit** : l'UI ne propose **jamais** de bouton d'action
d'écriture pour ce mode, plutôt que d'afficher un bouton qui échouerait au
clic — cohérence UX/sécurité, l'utilisateur n'est jamais confronté à un
refus après action (sauf accès direct à une URL de tâche `edit.save`/
`edit.delete` construite manuellement, qui serait alors refusée par le
contrôle ACL/DDL sous-jacent plutôt que par l'UI).

---

## 7. Règles de calcul

### 7.1 Notation (`rating`) — barème dépendant de `rating_slots`

**Fait observé** (`site/src/Controller/ApiController.php::ratePayload()`,
détaillé dans le brouillon site §9.1) : la note effective enregistrée
dépend du nombre de « slots » configuré sur la vue (`forms.rating_slots`,
défaut `5`, `install.sql:391`) :

| `rating_slots` | Interprétation de `rate` posté | Note effective |
|---|---|---|
| `1` | binaire (« j'aime ») | `1` si `rate` présent, sinon pas de note |
| `2` | pouce haut/bas | `1` si `rate ≥ 4`, sinon `0` |
| `3`/`4`/`5` | échelle bornée | `rate` tel quel, borné à l'échelle |

**Conséquence** : changer `rating_slots` sur une vue **après** que des
notes ont déjà été enregistrées ne convertit **jamais** rétroactivement
`records.rating_sum`/`rating_count` déjà accumulés — un changement
d'échelle en cours de vie d'une vue rend la moyenne affichée
(`rating_sum / rating_count`) potentiellement incohérente entre notes
anciennes (échelle précédente) et nouvelles (échelle actuelle), sans
recalcul ni avertissement.

### 7.2 Anti-doublon de notation — deux fenêtres temporelles différentes

**Fait observé** (`03-data-model.md` §13, confirmé par
`ApiController::ratePayload()`) : le doublon est empêché par **deux**
mécanismes de durée de vie différente, combinés (le premier qui bloque
suffit) :
- table `rating_cache` : fenêtre glissante d'environ **1 jour**
  (`DATEDIFF(:now, date) >= 1` purge les entrées plus anciennes à chaque
  appel — pas de tâche planifiée) ;
- clé de **session** Joomla (`com_contentbuilderng.rating.rated<formId><recordId>`) :
  valable toute la **durée de la session** du navigateur.

**Comportement déduit** : après 1 jour, la **même IP** peut renoter le
même enregistrement **si sa session a expiré entre-temps** (les deux
protections sont combinées par un OU logique côté contrôle, mais chacune a
sa propre durée de vie) — ce n'est donc pas un verrou permanent par IP,
seulement une fenêtre anti-spam à court terme complétée par une protection
de session.

### 7.3 Quota d'ajout (`amount_records`) — calcul extensible par type de source

**Fait observé** (`admin/src/Service/PermissionService.php:251-286`,
commentaire de code explicite) : le nombre d'enregistrements déjà soumis
par l'utilisateur (utilisé pour comparer au quota `limit_add`, §6.3) est
calculé par une méthode statique `getNumRecordsQuery($referenceId, $userId)`
**propre à chaque implémentation de source** (`com_contentbuilderng`,
`com_breezingformsng`, ou tout type custom déposé dans
`JPATH_SITE/media/contentbuilderng/types/`), dont la chaîne SQL brute
retournée est injectée **telle quelle** comme sous-requête scalaire dans
la requête de permissions.

**Comportement déduit — piège documenté dans le code lui-même** : toute
colonne référencée dans cette sous-requête (notamment `id`) **doit** être
qualifiée par son nom de table — sinon MySQL peut la résoudre de façon
ambiguë contre les tables de la requête englobante (`forms`,
`contentbuilderng_users`, `contentbuilderng_records`) plutôt que celle de
la sous-requête, provoquant un calcul de quota **silencieusement faux**
(pas d'erreur SQL si la colonne existe dans plusieurs tables jointes).

**Conséquence pour un type de source personnalisé** : un intégrateur qui
implémente `getNumRecordsQuery()` sans qualifier ses colonnes reproduit
exactement le bug déjà corrigé dans les deux implémentations natives —
piège à documenter pour toute extension tierce du composant.

### 7.4 Compteur de téléchargements (`resource_access.hits`) — déduplication par session, pas par accès réel

**Fait observé** (`plugins/content/contentbuilderng_download/src/Extension/ContentbuilderngDownload.php`,
brouillon plugins §1.3) : le compteur `hits` n'est incrémenté qu'une seule
fois par session Joomla pour la combinaison (type, élément, ressource),
via une clé de session dédiée — un utilisateur qui télécharge 10 fois le
même fichier dans la même session ne fait progresser le compteur que de 1.

**Conséquence** : `hits` mesure le nombre de **sessions distinctes**
ayant accédé à la ressource au moins une fois, pas le nombre réel de
téléchargements HTTP — à ne pas confondre avec un compteur brut de
requêtes si ce chiffre est présenté aux visiteurs (`hide-downloads`
permet de le masquer, cf. `04-features.md`/brouillon plugins).

### 7.5 Statistiques `{CBStats}` — total « réel » indépendant de la somme des groupes

**Fait observé** (brouillon plugins §1.2, `groups=`) : quand des
intervalles (`groups=`) sont définis sur un champ numérique, le total
affiché reste le total **réel** de tous les enregistrements correspondant
au filtre — **pas** la somme des comptages par groupe. Des intervalles
qui se chevauchent (ou qui ne couvrent pas 100% de la plage de valeurs)
peuvent donc faire apparaître un total supérieur (ou inférieur) à la
somme visible des barres/lignes du graphique.

**Conséquence** : ce n'est pas une incohérence de calcul, mais une
conséquence directe de la définition des groupes par l'auteur du contenu
— à documenter côté utilisateur final de la balise `{CBStats}` plutôt que
côté code (déjà noté comme « cas limite » dans le brouillon plugins,
repris ici comme règle de calcul formelle).

---

## 8. Comportements conditionnés par la configuration

> Pour la liste exhaustive des paramètres et leurs valeurs par défaut, voir
> `08-configuration.md`. Cette section documente uniquement **la règle
> métier** que chaque paramètre active/désactive.

### 8.1 `enable_validations` — interrupteur global « tout ou rien »

**Fait observé** (`admin/src/Service/FieldValidationService.php:32-35,86-88`,
`08-configuration.md` §1) : quand ce paramètre composant est désactivé
(`0`), **toutes** les règles de validation configurées champ par champ
(natives **et** externes via plugins `contentbuilderng_validation`) sont
neutralisées d'un coup, quel que soit leur paramétrage individuel — la
méthode `validate()` retourne `[]` sans même parcourir les règles
sélectionnées.

**Conséquence** : ce paramètre agit comme un **kill-switch** global, utile
en diagnostic (isoler un bug de validation) mais dangereux s'il est laissé
désactivé en production — un formulaire d'inscription avec des règles
`email`/`equal`/`notempty` configurées deviendrait **totalement non
validé** sur ces aspects (seules les validations de type SQL, §3.3, restent
actives, elles ne sont jamais désactivables par ce paramètre).

### 8.2 `disable_new_articles` — blocage de la création d'article Joomla natif

**Fait observé** (`08-configuration.md` §14, `plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php:412`) :
quand actif (défaut `1` = JYES), toute tentative de création d'un article
`com_content` **natif** Joomla (hors passage par CBNG) est redirigée avec
message d'erreur `COM_CONTENTBUILDERNG_PERMISSIONS_NEW_NOT_ALLOWED`.

**Conséquence** : les administrateurs Joomla natifs (même avec tous les
droits `com_content`) ne peuvent plus créer d'article « brut » via *Contenu
› Articles › Nouveau* tant que ce paramètre est actif — politique
volontaire pour forcer le passage par le pipeline CBNG (templates,
synchronisation), mais surprenante pour un administrateur qui ne connaît
pas ce paramètre du plugin système (documentation/formation nécessaire).

### 8.3 `nocache` — désactivation ciblée du cache de page Joomla

**Fait observé** (`08-configuration.md` §14) : actif par défaut (`1`),
désactive temporairement `config.caching` pour les requêtes `com_content`/
mutations CBNG détectées, restauré ensuite dans `onBeforeRender()`.

**Conséquence si désactivé par un administrateur pensant gagner en
performance** : des pages contenant des balises CBNG (`{CBList}`,
`{CBStats}`, listes/détails) peuvent servir du contenu **obsolète** depuis
le cache de page Joomla après une soumission/modification — comportement
silencieux (pas d'erreur, juste des données périmées affichées) jusqu'à
expiration naturelle du cache.

### 8.4 `is_auto_groups`/`auto_groups`/`auto_groups_limit_views` — appartenance de groupe conditionnée à la vérification

**Fait observé** (`08-configuration.md` §14,
`plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php`,
brouillon plugins §7) : quand `is_auto_groups=1`, les utilisateurs dont
`#__contentbuilderng_users.verified_view = 1` sont ajoutés aux groupes
Joomla listés dans `auto_groups` — **et retirés automatiquement** dès
qu'ils n'ont plus aucune vérification `verified_view` valide sur aucune
vue concernée (agrégat `HAVING SUM(verified_view) = 0`).

**Comportement déduit** : c'est un mécanisme **bidirectionnel et
automatique** — un administrateur qui invalide manuellement une
vérification (dépublie une ligne `#__contentbuilderng_users`, ou révoque
`verified_view`) **retire** silencieusement l'utilisateur du groupe
Joomla associé à la prochaine requête de mutation qui traverse le plugin
système, sans action explicite sur les groupes eux-mêmes.

**Conséquence** : gérer les groupes Joomla d'un utilisateur « auto-groupé »
directement depuis *Utilisateurs › Gérer* (ajout manuel) est **instable**
si ce mécanisme reste actif — le plugin système peut retirer ce même
utilisateur du groupe à la prochaine mutation si sa vérification CBNG
n'est plus valide, écrasant l'action manuelle sans avertissement.

### 8.5 `protect_upload_directory` — bascule dynamique de la protection `.htaccess`

**Fait observé** (brouillon plugins §1.4, `image_scale`) : le fichier
`.htaccess deny from all` du répertoire de cache/upload d'une vue est
**posé ou retiré dynamiquement** selon `forms.protect_upload_directory`,
à chaque exécution pertinente — pas une configuration figée à
l'installation.

**Conséquence** : désactiver `protect_upload_directory` sur une vue dont
les fichiers uploadés étaient jusque-là protégés les rend **immédiatement
accessibles par URL directe** dans `media/`, dès la prochaine requête qui
retire le `.htaccess` — effet immédiat et silencieux, à traiter comme un
changement de posture de sécurité, pas un simple réglage d'affichage.

### 8.6 `limited_article_options`/`limited_article_options_fe` — gate des réglages avancés d'article

**Fait observé** (`admin/src/Service/ArticleService.php:257-350`,
paramètre `$limitedOptions`) : quand `false` (droit `fullarticle` accordé,
§6.2), le soumetteur peut fournir `config['catid']`/`access`/`featured`/
`language`/`metakey`/`metadesc`/`attribs`/`metadata`, et (uniquement côté
admin, `$app->isClient('administrator')`) `created`/`created_by`. Quand
`true` (valeur par défaut, `install.sql:359-360` : `1`), **seules** les
valeurs par défaut de la vue (`default_category`/`default_access`/
`default_featured`/…) s'appliquent, toute tentative de les surcharger côté
soumission est ignorée.

**Conséquence** : accorder le droit `fullarticle` à un groupe front sans
en avoir conscience permet à ses membres de choisir **la catégorie, le
niveau d'accès et la mise en avant** de l'article généré, potentiellement
en dehors de ce que l'administrateur avait prévu via les valeurs par
défaut de la vue — à traiter comme un droit sensible, distinct des droits
`new`/`edit` classiques.

### 8.7 Export sans gate `listaccess` — renvoi croisé

Voir `07-security.md` §1.3 (« Zone à signaler ») : l'accès **direct** à
`view=export` ne revérifie jamais `listaccess`, seulement l'état publié et
`own_only_fe`. **Conséquence métier** : un lien d'export deviné/partagé
directement peut contourner une restriction `listaccess` par groupe,
tant que la vue reste publiée — repris ici car c'est une **règle de
visibilité** au sens de cette section 6/8, pas seulement un défaut de
contrôle technique.

---

## 9. Priorités et ordres d'application

### 9.1 Cascade de pagination — quatre niveaux, du plus spécifique au plus général

**Fait observé** (`08-configuration.md` §1,
`admin/src/Helper/ListLimitHelper.php`, `site/src/Dispatcher/Dispatcher.php:106-186`) :
l'ordre de résolution de la limite de pagination effective d'une liste est :

1. **Requête explicite de l'utilisateur** (`list[limit]` déjà posé en
   session/URL par une action de pagination volontaire) — toujours
   prioritaire, détecté par `MenuParamHelper::hasExplicitListLimitRequest()`.
2. **Paramètre de menu Joomla** (`cb_list_limit`), appliqué par le
   `Dispatcher` **seulement si** aucune limite explicite n'a été demandée.
3. **Réglage de la vue** (`forms.initial_list_limit`), sauf valeur
   sentinelle `INHERIT = -1` (`ListLimitHelper::INHERIT`).
4. **Paramètre composant** `default_list_limit` (`ListLimitHelper::getGlobalDefault()`),
   lui-même replié sur la constante `FACTORY_DEFAULT = 20` si absent/invalide.

**Conséquence** : changer `default_list_limit` au niveau composant n'a
**aucun effet visible** sur une vue dont `initial_list_limit` est
explicitement posé à une valeur ≠ `-1`, ni sur un menu qui définit
`cb_list_limit`, ni sur un utilisateur qui a déjà changé manuellement sa
pagination — c'est le niveau de repli le **plus bas** de la cascade, pas
un réglage global au sens strict.

### 9.2 Résolution du thème visuel — menu, puis preview, puis vue, puis repli `thoth`

**Fait observé** (brouillon site §2.4, brouillon plugins §5) :
`MenuThemeHelper::resolve()` (surcharge de menu) est appliqué **avant**
`PreviewThemeHelper::apply()` (surcharge de prévisualisation admin), tous
deux au-dessus de `forms.theme_plugin` (valeur de base de la vue), avec
repli final sur `'thoth'` si le plugin résolu par cette chaîne est
indisponible (§4.6).

**Conséquence** : un administrateur qui prévisualise une vue avec un
thème différent (`PreviewThemeHelper`) ne voit **pas** nécessairement le
même thème qu'un visiteur naviguant via un item de menu qui impose son
propre thème (`MenuThemeHelper`) — le mode preview ne surclasse un menu
que si celui-ci n'impose rien explicitement (ordre exact des deux
surcharges à confirmer par relecture de `MenuThemeHelper`/`PreviewThemeHelper`
si un cas de conflit réel est signalé — **Zone inconnue** sur l'ordre
précis entre ces deux surcharges spécifiquement, distinct du repli final
`thoth` qui, lui, est confirmé `Fait observé`).

### 9.3 Config Transfer — sections dépendantes ajoutées automatiquement, jamais retirées

**Fait observé** (brouillon admin §11,
`ConfigExportService::resolveEffectiveSections()`,
`FORM_DEPENDENT_SECTIONS`/`STORAGE_DEPENDENT_SECTIONS`) : sélectionner
`forms` à l'export ajoute automatiquement `elements`/`list_states`/
`resource_access` ; sélectionner `storages` ajoute automatiquement
`storage_fields` — ces ajouts sont **unidirectionnels** (le sens inverse
n'entraîne aucune inclusion automatique de `forms`/`storages`).

**Conséquence** : un export ciblant uniquement des `storage_fields` sans
sélectionner `storages` n'ajoute **pas** automatiquement le storage
parent — un import ultérieur de ce fichier partiel échouerait
probablement à rattacher ces champs (`storage_id` orphelin côté cible),
puisque la dépendance n'est vérifiée que dans un sens.

### 9.4 Config Transfer — import `merge` (défaut) vs `replace`

**Fait observé** (brouillon admin §11, `ConfigImportService::applyPayload()`) :
- **`MODE_MERGE`** (défaut) : les lignes existantes (par `id`) sont mises à
  jour, sans purge préalable — un champ non présent dans le payload importé
  reste inchangé en base (pas remis à une valeur par défaut).
- **`MODE_REPLACE`** : pour les `form_id`/`storage_id` explicitement
  ciblés, **supprime d'abord** les lignes existantes correspondantes avant
  réinsertion — remplacement complet, potentiellement destructif si le
  fichier importé est incomplet par rapport à l'état cible actuel (ex. des
  éléments ajoutés manuellement depuis l'export ne seraient jamais
  réinsérés en mode replace).

**Conséquence** : choisir `replace` sur une vue qui a évolué depuis
l'export source (nouveaux champs, nouveaux états de liste ajoutés
manuellement en production) **efface ces ajouts** sans confirmation
distincte au-delà du choix du mode lui-même — aucune sauvegarde
automatique préalable identifiée (§11 du brouillon admin, « Effets de
bord »).

### 9.5 Complétion des états de liste — mise à jour d'abord, complétion ensuite

Renvoi à §2.2/§2.3 : les états déjà présents (`id > 0` posté) sont
toujours réécrits avec les valeurs du formulaire **avant** que le nombre
total d'états ne soit comparé au standard pour une éventuelle complétion —
l'ordre garantit que la complétion ne fait jamais que **compléter**
(jamais écraser un état déjà présent).

### 9.6 `{CBStats config=...}` — le fichier `.ini` cède toujours la priorité aux attributs explicites du tag

**Fait observé** (brouillon plugins §1.2,
`CbStatsConfigService::merge()`) : les valeurs par défaut chargées depuis
un fichier de configuration réutilisable (`config=<fichier>.ini`) sont
fusionnées **avant** les attributs explicitement présents dans la balise
`{CBStats}` — un attribut de tag écrase toujours la valeur du fichier de
config, jamais l'inverse.

**Conséquence** : un fichier de config partagé entre plusieurs balises
`{CBStats}` sert de **socle** de valeurs par défaut réutilisables, jamais
de contrainte imposée — chaque balise individuelle garde la capacité de
surcharger n'importe quel réglage du fichier, ce qui peut surprendre un
administrateur pensant « verrouiller » une présentation via un fichier de
config partagé.

### 9.7 PayPal — bascule production/sandbox, un seul jeu de champs actif à la fois

**Fait observé** (`08-configuration.md` §13,
`plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php:52-60`) :
le paramètre `test` détermine **exclusivement** lequel des deux jeux de
champs (`business`/`token` vs `test_business`/`test_token`) est utilisé —
pas de fusion ni de repli entre les deux jeux si l'un des deux est
incomplet.

**Conséquence** : laisser `test=1` (sandbox) en configuration alors que
seuls `business`/`token` (production) ont été renseignés aboutit à un
plugin PayPal qui utilise des champs **vides** (`test_business`/`test_token`
non renseignés, défaut `''`) — échec de configuration côté PayPal
(`onSetup` bloquant, §6 du brouillon plugins), pas un repli silencieux
vers les valeurs de production.

---

## 10. Héritage de configuration

### 10.1 Champs — héritage strict du storage, aucune surcharge par vue

Renvoi à §3.1 : `required` (et, plus largement, le type SQL/la taille de
colonne, `storage_fields.sql_type`/`field_size`) sont des propriétés **du
storage**, jamais surchargeables au niveau `elements` (aucune colonne
équivalente sur cette table). Une vue **configure l'affichage/l'édition/la
validation « métier » facultative** (§3.5) d'un champ, mais **hérite**
intégralement de sa nature structurelle (obligatoire, type, taille) depuis
le storage sous-jacent.

### 10.2 Éléments — auto-provisionnement puis overlay manuel

**Fait observé** (`admin/src/Service/FormSupportService.php:448,475,489`,
brouillon admin §8) : à la synchronisation d'une vue avec sa source, les
nouveaux champs détectés côté storage/BreezingForms génèrent
automatiquement une ligne `elements` (valeurs par défaut de colonne,
`install.sql:65-96` — ex. `list_include=1`, `editable=0`,
`detail_include=1`, `api_allowed=0`, `published=1`), puis les champs
disparus côté source sont désynchronisés (suppression de la ligne
`elements` orpheline).

**Comportement déduit** : une vue « hérite » de la liste de champs de son
storage/BreezingForms à chaque synchronisation, mais ses réglages
d'affichage/validation manuels **persistent** tant que le champ source
existe encore — seule la disparition du champ côté source fait perdre ces
réglages (suppression en cascade de la ligne `elements`).

**Conséquence** : renommer un champ côté storage (qui, selon §4 du
brouillon admin, renomme la colonne physique via `syncEditedFields()`
plutôt que supprimer/recréer) **préserve** en principe la ligne `elements`
correspondante (même `reference_id` = `storage_fields.id`, qui ne change
pas au renommage) ; c'est la **suppression** du champ (pas son
renommage) qui déclenche la perte de configuration côté `elements`.

### 10.3 Menu vs vue — surcharge ponctuelle limitée à une liste blanche de paramètres

**Fait observé** (`site/src/Dispatcher/Dispatcher.php:21-30,41-47,153-160`,
`REQUEST_OVERRIDABLE_MENU_PARAMS`) : sur les **huit** paramètres listés
(`cb_show_author`, `cb_show_top_bar`, `cb_show_details_top_bar`,
`cb_show_bottom_bar`, `cb_show_details_bottom_bar`,
`cb_show_details_back_button`, `cb_filter_in_title`, `cb_prefix_in_title`),
une valeur explicitement présente dans la requête HTTP courante **survit**
au reset systématique des autres paramètres de menu effectué en tête de
`dispatch()` — c'est le **seul** mécanisme d'héritage/surcharge ponctuelle
identifié qui fonctionne au niveau requête, sans passer par l'écran
d'édition du menu Joomla.

**Conséquence** : tout autre paramètre de menu (`cb_list_limit`, filtres,
etc.) **ne peut pas** être surchargé de cette façon — seule l'édition du
menu Joomla (ou les champs `Menuinherit`/`Menuoverridereset` côté écran de
menu, §10.4) permet de changer un réglage hors de cette liste blanche des
huit.

### 10.4 Champ `Menuinherit`/`Menuoverridereset` — retour explicite à la valeur héritée de la vue

**Fait observé** (`site/src/Field/MenuinheritField.php`,
`MenuoverrideresetField.php`, brouillon site §5, table des 11 champs
`JForm`) : l'écran d'édition d'un item de menu Joomla propose un mécanisme
de bascule « hériter de la vue » et un bouton de réinitialisation dédié
pour **annuler** une surcharge de menu et revenir explicitement à la
valeur portée par la vue CBNG ciblée.

**Comportement déduit** : l'héritage menu → vue est donc **bidirectionnel
et réversible** par construction — contrairement à l'héritage storage →
élément (§10.1, irréversible sans modifier le storage lui-même), un
administrateur peut à tout moment ramener un réglage de menu à « suit la
vue » sans avoir à ressaisir manuellement la valeur de la vue.

### 10.5 Templates verrouillés (`*_template_locked`) — resynchronisation automatique, pas héritage figé

**Fait observé** (`admin/src/Controller/ElementoptionsController.php:49-76`,
brouillon admin §8) : quand `details_template_locked`/`editable_template_locked = 1`
sur une vue, toute modification d'un élément (changement de type, ajout,
suppression) déclenche une **régénération automatique** du template
verrouillé correspondant via `FormSupportService::resyncLockedTemplates()`
— capturée par `\Throwable`, dégradée en avertissement
(`COM_CONTENTBUILDERNG_TEMPLATE_LOCKED_RESYNC_UNAVAILABLE`) sans jamais
bloquer la sauvegarde de l'élément lui-même en cas d'échec.

**Comportement déduit** : « verrouillé » ne signifie donc **pas** « figé
définitivement » — c'est un mode où le template **suit automatiquement**
la configuration des éléments plutôt que d'être édité manuellement
(inverse d'un déverrouillage qui permettrait une édition manuelle libre,
potentiellement désynchronisée de la configuration des éléments).

**Conséquence en cas d'échec de resynchronisation** : le template
verrouillé peut rester **temporairement désynchronisé** de la
configuration réelle des éléments (l'élément est sauvegardé, mais le
template n'a pas pu être régénéré) — avertissement affiché, pas de
blocage, à surveiller via une nouvelle tentative de sauvegarde ou via
l'audit par vue (`FormAuditService`, badges d'anomalies, tab12).

---

## 11. Règles spécifiques formalisées (`list_states.action`, audit/repair, vérification/captcha)

### 11.1 Routage `list_states.action` — contrat complet

**Fait observé** (`site/src/Model/EditModel.php:2727-2859`,
`plugins/contentbuilderng_listaction/trash/src/Extension/Trash.php`,
`.../untrash/src/Extension/Untrash.php`, brouillon plugins §2/§3) :

1. `EditModel` (composant) lit `list_states.action` pour l'état cible,
   importe **le seul** plugin `contentbuilderng_listaction` dont le nom de
   dossier correspond **exactement** à cette valeur
   (`PluginHelper::importPlugin('contentbuilderng_listaction', $action)`).
2. Dispatch `onBeforeAction($form_id, $items)` — **avant** toute écriture
   de `list_records.state_id`.
3. `EditModel` met à jour `list_records.state_id` (upsert, avec repli
   anti-collision `DuplicateKeyViolationHelper`) **inconditionnellement**,
   que l'action corresponde ou non à un plugin réellement installé.
4. Dispatch `onAfterAction($form_id, $items, $error)` — **après**
   l'écriture.
5. Séparément, à **chaque** création/synchronisation d'article Joomla
   (`ArticleService::createArticle()`), dispatch
   `onAfterArticleCreation($form_id, $record_id, $article)` vers **tout**
   le groupe `contentbuilderng_listaction` (pas d'import ciblé identifié à
   ce point précis — **Zone inconnue** sur le mécanisme d'import exact,
   cf. brouillon plugins §9).

**Comportement des deux plugins livrés** :
- `trash` : `onBeforeAction` force `#__content.state = -2` (corbeille
  Joomla) pour les articles liés aux enregistrements concernés ;
  `onAfterArticleCreation` **supprime** (`DELETE`, pas dépublication)
  tout article qui vient d'être recréé pour un enregistrement dont l'état
  de liste courant a toujours `action='trash'` — garde-fou anti-résurrection.
- `untrash` : `onBeforeAction` restaure `#__content.state` depuis
  `records.published` (pas une valeur fixe) ; `onAfterArticleCreation` est
  un no-op délibéré (la recréation normale d'un article restauré est le
  comportement désiré, rien à contrer).

**Conséquence si `action` ne correspond à aucun plugin installé/activé** :
`list_records.state_id` est mis à jour normalement (aucune erreur), mais
**aucun effet de bord** ne se produit — silencieux, pas de log d'échec
d'import de plugin identifié dans le code lu.

**Conséquence si deux états de vues différentes portent la même `action`
mais un comportement souhaité différent** : impossible à distinguer côté
plugin — le routage se fait uniquement sur la **valeur de `action`**, pas
sur `form_id`/`state_id` ; tout état de n'importe quelle vue avec
`action='trash'` déclenche exactement le même comportement `Trash.php`.

### 11.2 Cycle Audit/Repair — 17 vérificateurs, pré-check avant action

**Fait observé** (`admin/src/Helper/DatabaseAuditHelper.php`,
`admin/src/Service/RepairWorkflowService.php`, brouillon admin §14) :

- **Audit à la demande** (`about.runAudit`) : agrège ~17 vérificateurs
  spécialisés (index dupliqués, tables historiques orphelines, entrées de
  menu historiques, encodage/collation, colonnes manquantes storage/forms,
  extensions plugin dupliquées, synchronisation BreezingForms, cohérence
  des références d'éléments, doublons de `records`, enregistrements
  BreezingForms orphelins, catégories d'articles incohérentes, fichiers de
  langue obsolètes, répertoires temporaires d'installeur périmés, noms
  d'index non conformes, types de colonne suspects, tri sur colonnes
  date/heure invalides, mode debug actif en production, incohérences de
  permissions frontend, protection du répertoire d'upload) — résultat
  journalisé et stocké en session, jamais en base.
- **Réparation en un clic** (`about.repairAuditIssue`, whitelist
  `DIRECT_AUDIT_REPAIR_ISSUES`) : ré-exécute l'audit après coup pour
  rafraîchir l'état — supporte l'AJAX, seule action « audit » de ce
  contrôleur à le faire explicitement.
- **Repair Workflow guidé** (`RepairWorkflowService::createWorkflowState()`) :
  checklist séquentielle des mêmes 17 étapes (moins deux jugées
  « diagnostic uniquement », `frontend_permission_consistency`/
  `menu_view_consistency`), avec un **pré-check par étape**
  (`buildPrechecks()`) qui marque d'emblée une étape `not_required` si
  aucun problème n'est détecté — **sans action utilisateur** —, et
  `advanceToNextPendingStep()` qui saute automatiquement les étapes déjà
  `not_required`/`done`.

**Comportement déduit — pas de garde-fou contre une ré-exécution
manuelle** : `executeStep()` exécute la réparation même si le pré-check
avait marqué l'étape `not_required`/`done`, si elle est appelée
directement par son identifiant (pas de re-vérification interne avant
exécution).

**Conséquence** : une réparation peut être déclenchée « pour rien » sur
une étape déjà saine (aucune donnée à corriger trouvée) — comportement
généralement inoffensif par construction (les helpers de réparation sont
censés être idempotents), mais non garanti pour les 17 helpers
individuellement (non relus tous en détail dans ce document, cf. §12 de
`03-data-model.md` sur l'absence de transaction couvrant les opérations
DDL en général).

**Conséquence d'un échec critique lors du self-heal automatique** (§1 du
brouillon admin, `script.php::postflight()`) : si `hasCriticalFailure()`
est vrai en fin de `postflight()`, une `\RuntimeException` est levée —
l'installation/mise à jour **échoue visiblement** dans l'installeur
Joomla, plutôt que de se terminer silencieusement en mode dégradé
(politique délibérée, distincte du comportement « silencieux » de la
plupart des autres règles de ce document).

### 11.3 Vérification/captcha — bypass et activation admin

**Règles déjà formalisées ailleurs dans ce document, regroupées ici pour
la synthèse (renvois)** :

- **Bypass captcha par exclusion de champ** : §5.4 — dépublier/rendre non
  éditable/exclure par restriction de menu le champ `captcha` d'une vue
  désactive silencieusement la vérification anti-bot pour cette vue/ce
  contexte.
- **Expiration de vérification** : §6.4 — `verification_days_<action> ≤ 0`
  rend une vérification définitive ; une valeur positive impose un
  renouvellement périodique en jours (valeur `float`, fractions de jour
  possibles).
- **Activation admin d'un compte** (`VerifyModel::activate_by_admin()`,
  `admin/src/Model/VerifyModel.php:469-559`) : protégée par l'ACL
  **`com_users`** (`core.create`), **pas** par une permission propre à
  `com_contentbuilderng` — ce choix traite l'activation comme une
  activation de compte Joomla standard, réutilisant le vocabulaire de
  messages `COM_USERS_*` natif plutôt que des clés CBNG dédiées. Un
  administrateur avec des droits complets sur `com_contentbuilderng` mais
  **sans** `core.create` sur `com_users` **ne peut pas** activer un compte
  via ce flux — restriction potentiellement surprenante pour un profil
  d'administration délégué au seul composant CBNG.
- **Vérification « bypass » depuis une inscription** (§1.7 du brouillon
  site/plugins) : une inscription CBNG peut créer directement une ligne
  `#__contentbuilderng_verifications` (mode `registration_bypass_plugin`)
  sans jamais passer par la balise de contenu `{CBVerify}` classique — un
  second chemin d'entrée vers le même mécanisme de vérification, à
  connaître pour tout audit de sécurité de ce flux (déjà signalé
  `07-security.md`, repris ici comme variante fonctionnelle du même
  contrat de vérification).
- **`onSetup` bloquant vs non bloquant** : PayPal retourne un message
  d'erreur qui **bloque** la suite du flux de vérification si `amount`
  (paramètre requis) est absent (§9.7) ; `passthrough` ne bloque jamais
  (`onSetup` retourne toujours une chaîne vide) — deux comportements
  volontairement différents pour deux profils de plugin (paiement
  vs passe-plat sans exigence de configuration).

---

## 12. Tableau récapitulatif — violation → conséquence

| # | Règle | Violation/oubli typique | Conséquence concrète |
|---|---|---|---|
| §1.5 | Blocage compte `act_as_registration` | Bloquer un utilisateur Joomla pour un motif sans rapport avec son contenu | Dépublication en cascade de tous ses enregistrements/articles liés, sans notification |
| §2.1 | `list_states.action` non configurée | Compter sur « corbeille » par défaut | Aucun effet de bord sur `#__content.state`, silencieux |
| §3.2 | Champ `required` du storage exclu de l'édition d'une vue | Restreindre l'affichage d'un champ requis pour une vue | Création systématiquement bloquée via cette vue (`COM_CONTENTBUILDERNG_STORAGE_REQUIRED_VALUE`) |
| §4.8 | `limit_add`/`limit_edit = 0` pensé comme « bloquer » | Poser `0` pour interdire | Interprété comme illimité — effet inverse de l'intention |
| §5.1/§5.2 | Renommage d'un champ sans respecter `_repeat`/`_later` | Renommer un champ principal sans son homologue | Règle `equal`/`date_not_before` silencieusement inopérante |
| §5.4 | Champ captcha exclu par menu/mode embarqué | Restreindre les champs affichés d'un formulaire public | Vérification captcha totalement désactivée, sans avertissement |
| §5.5 | Un des 6 champs de mapping d'inscription indisponible | Suppression/renommage d'un champ source mappé | Aucun compte Joomla créé, message de succès générique affiché quand même |
| §5.6 | Champ « source » d'une liste dépendante non `api_allowed` | Oubli de cocher `api_allowed` sur un champ utilisé en filtre | `403` sur `get-unique-values`, liste dépendante cassée |
| §6.1/§6.2 | `own_only_fe=1` sans droit d'action « own » associé | Cocher `own_only_fe` seul, sans matrice `own` | Liste vide/action refusée pour les utilisateurs hors groupe |
| §6.2 | Droit de groupe + « own » cochés simultanément | Configuration ACL trop permissive par erreur | La restriction « own » devient inopérante pour ce groupe |
| §6.4 | `verification_days = 0` pensé comme « à chaque fois » | Confusion sur la sémantique de la colonne | Vérification définitive dès la première fois, jamais renouvelée |
| §8.1 | `enable_validations = 0` laissé en production | Diagnostic oublié réactivé | Toutes les validations « métier » par champ neutralisées (sauf validations de type SQL) |
| §8.4 | Gestion manuelle des groupes Joomla d'un utilisateur auto-groupé | Ajout manuel de groupe concurrent au mécanisme automatique | Le plugin système peut retirer l'utilisateur du groupe à la prochaine mutation |
| §8.5 | `protect_upload_directory` désactivé sur une vue sensible | Modification de configuration sans mesurer l'impact | Fichiers uploadés immédiatement accessibles par URL directe |
| §8.6 | `fullarticle` accordé sans en mesurer la portée | Extension de droits front trop large | Le soumetteur choisit catégorie/accès/mise en avant de l'article généré |
| §9.4 | Import Config Transfer en mode `replace` sur une vue modifiée depuis l'export | Choix du mode sans vérifier les divergences | Perte des ajouts effectués depuis l'export source, sans sauvegarde automatique |
| §11.3 | Activation admin d'un compte sans `core.create` sur `com_users` | Administrateur délégué au seul composant CBNG | Impossible d'activer le compte via ce flux malgré des droits complets sur CBNG |

---

**Règles les plus contre-intuitives identifiées dans cette analyse** (à
signaler en priorité à Gilles/l'équipe produit) :

1. **§3.2** — un champ `storage_fields.required=1` non rendu éditable dans
   une vue **bloque toute création** via cette vue, avec un message
   pointant un champ que l'utilisateur ne voit jamais.
2. **§5.4** — dépublier/exclure le champ captcha d'une vue **désactive
   totalement** la protection anti-bot, sans avertissement d'aucune sorte.
3. **§4.8/§6.3** — un quota (`limit_add`/`limit_edit`) à `0` signifie
   **illimité**, pas « interdit » ; une surcharge individuelle positive
   **remplace** (n'additionne pas) le quota de la vue.
4. **§6.4** — `verification_days ≤ 0` rend une vérification **permanente**,
   à l'opposé de l'intuition « 0 jour = expire immédiatement ».
5. **§8.4** — l'appartenance à un groupe Joomla « auto-groupé » est
   **retirée automatiquement** dès la perte de vérification CBNG, en
   concurrence silencieuse avec toute gestion manuelle du groupe.
