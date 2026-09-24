# 08 — Configuration

> Rétro-analyse (reverse engineering) de `com_contentbuilderng` — Joomla 6 / PHP 8.3+.
> Ce document couvre exclusivement la **configuration système** du composant et
> de ses 17 plugins : options `<config><fields>` des manifestes XML, réglages
> globaux Joomla (`#__extensions.params`), constantes PHP jouant un rôle de
> configuration, et vérification de l'absence de table de configuration dédiée
> en base.
>
> Méthode de qualification des affirmations :
> - **Fait observé** : lu directement dans le code, avec référence `fichier:ligne`.
> - **Comportement déduit** : logique assemblée à partir de plusieurs faits observés.
> - **Hypothèse** : interprétation plausible non totalement vérifiée dans le code lu.
> - **Zone inconnue** : point non tranché, incohérence potentielle, ou dette technique
>   à signaler.
>
> Les entités "configurables par l'utilisateur en base" (Storages, Storage
> Fields, Forms) ne sont **pas** traitées ici comme de la configuration système :
> voir `03-data-model.md` et `04-features.md` pour leur détail. Seule la
> frontière est rappelée en fin de document (§7).

## Sommaire

1. [Composant global — `admin/config.xml`](#1-composant-global--adminconfigxml)
2. [Plugin content — `contentbuilderng_cblist`](#2-plugin-content--contentbuilderng_cblist)
3. [Plugin content — `contentbuilderng_cbstats`](#3-plugin-content--contentbuilderng_cbstats)
4. [Plugin content — `contentbuilderng_download`](#4-plugin-content--contentbuilderng_download)
5. [Plugin content — `contentbuilderng_image_scale`](#5-plugin-content--contentbuilderng_image_scale)
6. [Plugin content — `contentbuilderng_permission_observer`](#6-plugin-content--contentbuilderng_permission_observer)
7. [Plugin content — `contentbuilderng_rating`](#7-plugin-content--contentbuilderng_rating)
8. [Plugin content — `contentbuilderng_verify` (content)](#8-plugin-content--contentbuilderng_verify)
9. [Plugin listaction — `trash` / `untrash`](#9-plugins-listaction--trash--untrash)
10. [Plugin submit — `submit_sample`](#10-plugin-submit--submit_sample)
11. [Plugins themes — `blank` / `dark` / `khepri` / `thoth`](#11-plugins-themes--blank--dark--khepri--thoth)
12. [Plugin verify — `passthrough`](#12-plugin-verify--passthrough)
13. [Plugin verify — `paypal`](#13-plugin-verify--paypal)
14. [Plugin system — `contentbuilderng_system`](#14-plugin-system--contentbuilderng_system)
15. [Constantes PHP et valeurs codées en dur jouant un rôle de configuration](#15-constantes-php-et-valeurs-codées-en-dur-jouant-un-rôle-de-configuration)
16. [Configuration stockée en base (recherche et conclusion)](#16-configuration-stockée-en-base-recherche-et-conclusion)
17. [Frontière avec Storages / Storage Fields / Forms](#17-frontière-avec-storages--storage-fields--forms)
18. [Synthèse des paramètres morts / suspects](#18-synthèse-des-paramètres-morts--suspects)

---

## 1. Composant global — `admin/config.xml`

**Fait observé** : `admin/config.xml:1-84` déclare deux `<fieldset>` : `general`
(paramètres métier) et `permissions` (ACL standard Joomla, champ `rules`).
`addfieldprefix="CB\Component\Contentbuilderng\Administrator\Field"` et
`addruleprefix="CB\Component\Contentbuilderng\Administrator\Rule"` indiquent
que les types de champ/règle non standards (`listlimit`, `paginationchoices`)
sont résolus dans ces deux namespaces.

Ces paramètres sont stockés dans la colonne `params` (JSON) de
`#__extensions` pour l'extension `com_contentbuilderng`, lus via
`Joomla\CMS\Component\ComponentHelper::getParams('com_contentbuilderng')`
(mécanisme standard Joomla — pas de table de config dédiée, voir §16).

| Paramètre | Type | Défaut XML | Signification | Code qui le lit |
|---|---|---|---|---|
| `default_list_limit` | `listlimit` (champ custom, `admin/src/Field/ListlimitField.php`) | `20` | Nombre d'enregistrements par page utilisé comme valeur globale de repli pour toutes les listes du composant (vues de liste front, tableau des éléments en admin) quand aucune valeur plus spécifique (menu, vue) n'est définie. | **Fait observé** : `admin/src/Helper/ListLimitHelper.php:18-24` (`getGlobalDefault()`), qui `ComponentHelper::getParams('com_contentbuilderng')->get('default_list_limit', self::FACTORY_DEFAULT)`. Consommé en cascade par `resolveViewValue()` (ligne 112-119) pour toute vue dont le paramètre local est à `INHERIT` (`-1`). |
| `pagination_choices` | `paginationchoices` (champ custom, `admin/src/Field/PaginationchoicesField.php`, validé par la règle custom `admin/src/Rule/PaginationchoicesRule.php`) | *(aucun défaut XML — géré par le code)* | Liste CSV des choix de pagination proposés dans les sélecteurs "Nombre à afficher" (ex. `5,10,20,25,50,100,All`). | **Fait observé** : `admin/src/Helper/ListLimitHelper.php:27-37` (`getPaginationChoices()`), qui lit `pagination_choices` avec le défaut `FACTORY_CHOICES = '5,10,20,25,50,100,All'` (ligne 16) et retombe sur ce défaut en cas de valeur invalide (`UnexpectedValueException` capturée). Publié côté JS via `registerFieldAssets()` (ligne 137-147, `addScriptOptions('com_contentbuilderng.listLimitField', ...)`). |
| `view_elements_limit` | `listlimit` | `20` | Limite de pagination locale à l'onglet "Éléments" (Elements) d'une vue en back-office, indépendante de `default_list_limit` mais avec le même défaut factory. | **Fait observé** : `admin/src/Model/ElementsModel.php:178-181`, `(int) ComponentHelper::getParams('com_contentbuilderng')->get('view_elements_limit', 20)`, utilisé comme limite par défaut appliquée quand on arrive depuis une autre vue sans limite explicite en requête (lignes 165-189). |
| `enable_validations` | `radio` (switcher) | `1` (JYES) | Interrupteur global qui active/désactive **toutes** les validations de champs personnalisées (règles `notempty`, `equal`, `email`, `date_not_before`, `date_is_valid` + validations externes déclarées par des plugins tiers) côté back-office (options d'élément) — un OFF global neutralise silencieusement toute validation configurée sur chaque champ. | **Fait observé (gating réel)** : `admin/services/provider.php:100-106`, injecté comme flag `bool` dans le constructeur de `FieldValidationService` (`(bool) ComponentHelper::getParams('com_contentbuilderng')->get('enable_validations', 1)`). Utilisé dans `admin/src/Service/FieldValidationService.php:65-67` et `:86-88` (`validate()` retourne `[]` sans exécuter aucune règle si `!areValidationsEnabled()`). **Fait observé (affichage debug seulement)** : `site/tmpl/details/default.php:354`, `site/tmpl/list/default.php:578`, `site/tmpl/edit/default.php:895` relisent le même paramètre côté front uniquement pour peupler la clé `validationsEnabled` du panneau de debug JS (`:393`, `:608`, `:927`) — cette seconde lecture n'influence pas le comportement, elle ne fait que l'afficher. |
| `audit_details_template_empty` | `radio` (switcher) | `0` (JNO) | Active, dans l'audit de formulaire (`FormAuditService`), l'avertissement signalant qu'un template de détails (`details_template`) est vide alors que des éléments publiés en "detail_include" existent. | **Fait observé** : `admin/src/Service/FormAuditService.php:50` puis passé en paramètre `checkTemplates(..., bool $auditDetailsTemplateEmpty, ...)` (ligne 763-773) ; contrôle l'émission des checks `CBNG-AUDIT-TEMPLATES-EMPTY` / `CBNG-AUDIT-DETAILS-TEMPLATE-EMPTY` (lignes 784-807) et `CBNG-AUDIT-FIELD-MISSING-IN-DETAILS` (ligne 824-833). |
| `audit_field_missing_in_edit` | `radio` (switcher) | `0` (JNO) | Active l'avertissement d'audit signalant qu'un champ éditable n'apparaît pas dans le template d'édition (`editable_template`). | **Fait observé** : `admin/src/Service/FormAuditService.php:51`, consommé ligne 835 (`CBNG-AUDIT-*-MISSING-IN-EDIT`). |
| `rules` (fieldset `permissions`) | `rules` (type Joomla natif) | *(aucun)* | ACL du composant (droits Joomla standard : Admin/Configure/Access/etc. sur `component`). | **Comportement déduit** : consommé nativement par le moteur d'autorisation Joomla (`JAccess`/`Access::check`) lors de tout appel `$user->authorise('core.*', 'com_contentbuilderng')`, sans lecture explicite via `params->get()` dans le code du composant — mécanisme standard Joomla, pas propre à ContentBuilder NG. `admin/src/Service/PermissionService.php` implémente la logique d'ACL applicative du composant (permissions par formulaire/enregistrement) mais ne relit pas ce champ `rules` directement ; il s'appuie sur les vérifications Joomla natives en amont. |

**Note tests** : `admin/tests/Unit/View/ListLimitConfigurationTest.php:23-29` vérifie
par introspection XPath la présence des champs `default_list_limit` et
`pagination_choices` dans `admin/config.xml`, confirmant que ces deux noms de
champ sont contractuels (changement de nom = rupture de test).

---

## 2. Plugin content — `contentbuilderng_cblist`

**Fait observé** : `plugins/content/contentbuilderng_cblist/contentbuilderng_cblist.xml:24-63`.
Le `<fieldset name="description">` ne contient que des champs `type="note"`
(`cblist_syntax_note`, `cblist_fields_note`, `cblist_value_note`,
`cblist_actions_note`, `cblist_none_note`) : ce sont des blocs d'aide
statiques (alertes Bootstrap) affichés dans l'écran de configuration du
plugin, **sans aucune valeur stockée ni lue** — ils ne pilotent aucun
comportement runtime.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun paramètre fonctionnel)* | — | — | Le plugin ne déclare que des notes d'aide (syntaxe de la balise `{cblist ...}`). Sa configuration réelle est portée par les paramètres de la balise elle-même dans le contenu de l'article, pas par les paramètres du plugin. | Sans objet — voir `04-features.md` pour la syntaxe `{cblist}`. |

---

## 3. Plugin content — `contentbuilderng_cbstats`

**Fait observé** : `plugins/content/contentbuilderng_cbstats/contentbuilderng_cbstats.xml:24-126`.
Comme `cblist`, le seul `<fieldset name="description">` ne contient que des
champs `type="note"` (13 blocs d'aide : syntaxe, carte éditoriale, filtres,
titleset, groupset, pourcentage, progression, debug, mode manuel, export,
présentation, en-têtes, options d'affichage, masquage).

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun paramètre fonctionnel)* | — | — | Uniquement des notes d'aide sur la syntaxe `{cbstats ...}`. La configuration réelle des statistiques est portée par les attributs de la balise `{cbstats}` dans le contenu, traités par `site/src/Service/CbStatsConfigService.php` / `StatsService.php` etc. | Sans objet ici — voir `04-features.md`. |

---

## 4. Plugin content — `contentbuilderng_download`

**Fait observé** : `plugins/content/contentbuilderng_download/contentbuilderng_download.xml:19-31`.
Un seul champ `type="note"` (`cbdownload_syntax_note`), aide sur la syntaxe
de la balise de téléchargement.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun paramètre fonctionnel)* | — | — | Note d'aide uniquement. | Sans objet. |

---

## 5. Plugin content — `contentbuilderng_image_scale`

**Fait observé** : `plugins/content/contentbuilderng_image_scale/contentbuilderng_image_scale.xml:19-35`.

| Paramètre | Type | Défaut XML | Signification | Code qui le lit |
|---|---|---|---|---|
| `max_filesize` | `text` | `4` | Taille maximale (en unités de 4 "Mo Joomla" arrondis, voir calcul ci-dessous) acceptée pour le fichier image que le plugin va redimensionner à la volée lors du rendu du contenu (`onContentPrepare`). | **Fait observé** : `plugins/content/contentbuilderng_image_scale/src/Extension/ContentbuilderngImageScale.php:107-111` — `$plugin = PluginHelper::getPlugin('content', 'contentbuilderng_image_scale'); $pluginParams = (new Registry)->loadString($plugin->params); $max_filesize = (8 * 8 * 8 * 1024 * 2) * intval($pluginParams->def('max_filesize', 4));` (commentaire code : "4M default"). Comparé à `filesize($the_value)` ligne 584 pour rejeter les fichiers trop volumineux avant redimensionnement. |

**Comportement déduit** : le facteur `8*8*8*1024*2 = 1 048 576` correspond à
1 Mio ; `max_filesize` (en Mio) est donc bien multiplié par 1 048 576 pour
obtenir une limite en octets — cohérent avec le commentaire "4M default".

**Zone inconnue / homonymie à ne pas confondre** : il existe un **second**
`max_filesize`, sans rapport avec ce paramètre de plugin : c'est une **option
par champ de type upload**, stockée dans `#__contentbuilderng_elements.options`
(colonne JSON) et éditée via `admin/tmpl/elementoptions/default.php:351-358`
/ `admin/src/Model/ElementoptionsModel.php:537`, puis relue côté front dans
`site/src/Model/EditModel.php:1185-1201` pour valider la taille d'un fichier
uploadé par un champ de formulaire. Ce second `max_filesize` est une
configuration **par champ/formulaire** (donc hors périmètre "config système"
au sens de ce document, cf. §17), à ne pas confondre avec le paramètre de
plugin `contentbuilderng_image_scale` documenté ci-dessus qui, lui, régit
uniquement le service de redimensionnement à l'affichage d'un article.

---

## 6. Plugin content — `contentbuilderng_permission_observer`

**Fait observé** : `plugins/content/contentbuilderng_permission_observer/contentbuilderng_permission_observer.xml:19-22`
— `<fields name="params"></fields>` est **vide** : aucun champ de
configuration n'est déclaré pour ce plugin.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun)* | — | — | Plugin sans configuration ; son comportement (observation/application des permissions sur le contenu) est entièrement piloté par la donnée métier (permissions des formulaires/enregistrements), pas par des paramètres de plugin. | Sans objet. |

---

## 7. Plugin content — `contentbuilderng_rating`

**Fait observé** : `plugins/content/contentbuilderng_rating/contentbuilderng_rating.xml:19-31`.
Un seul champ `type="note"` (`cbrating_syntax_note`).

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun paramètre fonctionnel)* | — | — | Note d'aide sur la syntaxe de la balise de notation. | Sans objet. |

---

## 8. Plugin content — `contentbuilderng_verify`

**Fait observé** : `plugins/content/contentbuilderng_verify/contentbuilderng_verify.xml:19-31`
(groupe **content**, distinct du groupe **contentbuilderng_verify** des
sous-plugins `passthrough`/`paypal` — voir §12-13). Un seul champ
`type="note"` (`cbverify_syntax_note`).

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun paramètre fonctionnel)* | — | — | Note d'aide. Ce plugin de contenu expose la syntaxe permettant d'insérer un contrôle de vérification dans un article ; les sous-plugins de vérification réels (`passthrough`, `paypal`) sont un groupe de plugin séparé (`contentbuilderng_verify`), voir §12-13. | Sans objet. |

---

## 9. Plugins listaction — `trash` / `untrash`

**Fait observé** : `plugins/contentbuilderng_listaction/trash/trash.xml:24-27` et
`plugins/contentbuilderng_listaction/untrash/untrash.xml:24-27` : dans les
deux cas `<fields name="params"></fields>` est vide.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun)* | — | — | Actions de liste "Mettre à la corbeille" / "Restaurer" sur les articles Joomla liés aux enregistrements sélectionnés ; aucune option n'est configurable, le comportement est fixe. | Sans objet. |

---

## 10. Plugin submit — `submit_sample`

**Fait observé** : `plugins/contentbuilderng_submit/submit_sample/submit_sample.xml:23-26`
— `<fields name="params"></fields>` vide.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun)* | — | — | Plugin d'exemple ("Sample on how to use submit plugins") destiné à illustrer l'API des plugins `submit`, sans configuration. | Sans objet. |

---

## 11. Plugins themes — `blank` / `dark` / `khepri` / `thoth`

**Fait observé** : aucun des 4 manifestes
(`plugins/contentbuilderng_themes/{blank,dark,khepri,thoth}/*.xml`) ne
comporte de balise `<config>` du tout (contrairement aux autres groupes de
plugins où `<config><fields name="params">` existe, même vide). Ces plugins
n'ont donc **structurellement aucun écran de paramètres** dans l'administration
Joomla.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun — pas de `<config>` déclaré)* | — | — | Chaque thème (Blank, Dark, Khepri, Thoth) fournit un jeu de CSS/layouts sélectionné par formulaire (colonne `theme_plugin` de `#__contentbuilderng_forms`, cf. `03-data-model.md`), pas par des paramètres de plugin Joomla. | Sans objet ici — la sélection du thème est une donnée de formulaire, pas une configuration de plugin. |

**Comportement déduit** : la personnalisation visuelle par thème se fait donc
exclusivement via le choix du plugin de thème actif par formulaire (champ
`theme_plugin`) et via les fichiers CSS/layout embarqués dans chaque plugin,
jamais via un panneau de configuration Joomla dédié à ces plugins.

---

## 12. Plugin verify — `passthrough`

**Fait observé** : `plugins/contentbuilderng_verify/passthrough/passthrough.xml:23-26`
— `<fields name="params"></fields>` vide.

| Paramètre | Type | Défaut | Signification | Code qui le lit |
|---|---|---|---|---|
| *(aucun)* | — | — | Plugin de vérification "passe-plat" (description XML : "Empty verification plugin that helps if you need to force a required view or disabled the Joomla! registration and using the ContenBuilder registration") ; comportement fixe, aucune option. | Sans objet. |

---

## 13. Plugin verify — `paypal`

**Fait observé** : `plugins/contentbuilderng_verify/paypal/paypal.xml:23-36`.

| Paramètre | Type | Défaut XML | Signification | Code qui le lit |
|---|---|---|---|---|
| `business` | `text` | `""` | Identifiant marchand PayPal (adresse e-mail ou Merchant ID) utilisé en **mode production** pour construire le formulaire de paiement PayPal. | **Fait observé** : `plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php:58` (`$this->business = $pluginParams->def('business', '');`, branche `else` du test sandbox) puis injecté dans le HTML du formulaire de paiement ligne 142 (`<input type="hidden" name="business" ...>`). |
| `token` | `text` | `""` | Jeton/identifiant complémentaire pour l'intégration PayPal en production (usage précis interne au flux de paiement du plugin, non détaillé par du code IPN visible dans l'extrait lu). | **Fait observé** : ligne 59 (`$this->token = $pluginParams->def('token', '');`), branche production. |
| `test` | `radio` | `0` (No) | Bascule "Sandbox" : active l'environnement de test PayPal (`https://www.sandbox.paypal.com`) au lieu de l'URL de production. | **Fait observé** : `Paypal.php:52-60` — `if ($pluginParams->def('test', 0)) { $this->test = true; ...; $this->url = 'https://www.sandbox.paypal.com'; } else { ... }`. |
| `test_business` | `text` | `""` | Identifiant marchand PayPal utilisé en **mode sandbox** (quand `test` = Yes), à la place de `business`. | **Fait observé** : ligne 54 (`$this->business = $pluginParams->def('test_business', '');`, branche `if ($pluginParams->def('test', 0))`). |
| `test_token` | `text` | `""` | Jeton utilisé en **mode sandbox**, à la place de `token`. | **Fait observé** : ligne 55 (`$this->token = $pluginParams->def('test_token', '');`). |

**Comportement déduit** : les 5 champs forment un couple production/sandbox
piloté par l'interrupteur `test` ; aucun champ n'est de type `password` malgré
la sensibilité potentielle de `token`/`test_token` — voir remarque ci-dessous.

**Zone inconnue / dette potentielle** : les champs `token` et `test_token`
sont déclarés en `type="text"` (texte en clair, visible à l'écran et dans
l'export de configuration) plutôt qu'en `type="password"`. Selon le rôle réel
de ce "Token" dans l'intégration PayPal (simple identifiant de bouton ou
secret d'API), cela pourrait mériter un traitement plus protecteur ; le code
lu ne permet pas de confirmer si cette valeur est un secret sensible côté
PayPal ou un simple identifiant public de configuration de bouton — à
vérifier avec Gilles avant toute évolution du champ.

---

## 14. Plugin system — `contentbuilderng_system`

**Fait observé** : `plugins/system/contentbuilderng_system/contentbuilderng_system.xml:19-40`.

| Paramètre | Type | Défaut XML | Signification | Code qui le lit |
|---|---|---|---|---|
| `limit_per_turn` | `text` | `50` | Nombre maximum d'enregistrements traités par "tour" (batch) lors d'une opération de traitement en masse pilotée par le plugin système (throttling d'un job interne). | **Fait observé** : `plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php:676` — `->setLimit((int) $pluginParams->def('limit_per_turn', 50));`. |
| `disable_new_articles` | `radio` | `1` (JYES) | Quand activé, bloque/redirige les tentatives de création d'un nouvel article Joomla natif (`com_content`) depuis le back-office pour forcer le passage par ContentBuilder NG (empêche la création d'articles "hors CB" pour les types de contenu gérés par CB). | **Fait observé** : `ContentbuilderngSystem.php:412` — condition combinant `$pluginParams->def('disable_new_articles', 0)` avec la détection de `option=com_content` et des tâches `new`/`article.add`/vue `article`+layout `form`/vue `form`+layout `edit`, sur un article sans id (`$a_id <= 0`). |
| `nocache` | `radio` | `1` (JYES) | Désactive la mise en cache Joomla pour les pages/contextes gérés par le composant (évite de servir du contenu ContentBuilder obsolète depuis le cache de page). | **Fait observé** : deux lectures identiques, `ContentbuilderngSystem.php:158` et `:511` — `if ($pluginParams->def('nocache', 1)) { ... }` (désactivation du cache dans deux points d'entrée du cycle de vie de l'événement système, probablement `onAfterInitialise`/`onBeforeRender` ou équivalents). |
| `is_auto_groups` | `radio` | `0` (JNO) | Active l'attribution automatique de groupes d'utilisateurs Joomla lors de l'inscription/validation d'un enregistrement CB (fonctionnalité "auto-groupes"). | **Fait observé** : `ContentbuilderngSystem.php:223` — `if (intval($pluginParams->get('is_auto_groups', 0)) == 1 && count($pluginParams->get('auto_groups', array()))) { ... }`. |
| `auto_groups` | `usergroup` (multiple) | `""` | Liste des groupes d'utilisateurs Joomla à assigner automatiquement quand `is_auto_groups` est actif. | **Fait observé** : lectures multiples — `ContentbuilderngSystem.php:240` (`implode(',', array_map('intval', $pluginParams->get('auto_groups', [])))`), `:241` (calcul dupliqué, potentiellement redondant — voir remarque ci-dessous), `:271` (`$groups = $pluginParams->get('auto_groups', array());`), `:290` (nouveau calcul de la même liste). |
| `auto_groups_limit_views` | `text` | `""` | Liste CSV d'identifiants de vues limitant le périmètre où l'attribution automatique de groupes s'applique (si vide, s'applique à toutes les vues). | **Fait observé** : `ContentbuilderngSystem.php:225-226` — `if ($pluginParams->get('auto_groups_limit_views', '') != '') { $operateViews = explode(',', $pluginParams->get('auto_groups_limit_views', '')); }`. |

**Zone inconnue / dette potentielle** : `ContentbuilderngSystem.php:240-241`
calcule deux variables (`$autoGroupsIntList` et `$autoGroupsRawList`) à
partir de **la même expression** (`implode(',', array_map('intval',
$pluginParams->get('auto_groups', [])))`), et la ligne `:290` recalcule une
troisième fois `$autoGroupsIntList2` de façon identique. Sans lecture plus
approfondie du corps complet de la méthode (hors périmètre "configuration" de
cette tâche), on ne peut pas exclure un calcul redondant/mort ; à signaler
comme piste de nettoyage potentiel plutôt que bug confirmé.

---

## 15. Constantes PHP et valeurs codées en dur jouant un rôle de configuration

**Fait observé** — recherche `define(...)` sur tout le dépôt (hors
`vendor`/`node_modules`) : aucune constante globale de configuration métier
via `define()` n'existe dans le code applicatif. Les seules occurrences
concernent l'amorçage technique Joomla/PHPStan/tests
(`\defined('_JEXEC') or die`, `phpstan-bootstrap.php:8,31,34`,
`admin/tests/bootstrap.php:17`, `media/images/securimage/securimage_show.php:7`
et `media/images/securimage_show.php:9`) — ce ne sont pas des paramètres de
configuration fonctionnels.

En revanche, plusieurs **constantes de classe (`const`)** jouent un rôle de
configuration statique (valeurs par défaut ou limites codées en dur, non
exposées dans l'UI) :

| Constante | Fichier:ligne | Valeur | Rôle |
|---|---|---|---|
| `ListLimitHelper::FACTORY_DEFAULT` | `admin/src/Helper/ListLimitHelper.php:15` | `20` | Valeur de repli finale si `default_list_limit` (config composant) est absent ou invalide. |
| `ListLimitHelper::FACTORY_CHOICES` | `admin/src/Helper/ListLimitHelper.php:16` | `'5,10,20,25,50,100,All'` | Liste de choix de pagination de repli si `pagination_choices` est absent ou invalide. |
| `ListLimitHelper::INHERIT` | `admin/src/Helper/ListLimitHelper.php:13` | `-1` | Valeur sentinelle signifiant "hériter de la valeur parente" dans la cascade de pagination (vue → menu → composant). |
| `ListLimitHelper::ALL` | `admin/src/Helper/ListLimitHelper.php:14` | `0` | Valeur sentinelle signifiant "Tout afficher" (pas de limite). |
| `Logger::LOG_FILE` | `admin/src/Helper/Logger.php:28` | `'com_contentbuilderng.log'` | Nom de fichier de log applicatif, non configurable via l'UI (chemin de base résolu dynamiquement via `$app->get('log_path')`, cf. `Logger.php:58-76`). |
| `Logger::MAX_ROTATED_FILES` | `admin/src/Helper/Logger.php:29` | `10` | Nombre maximum de fichiers de log conservés lors de la rotation (`rotateIfNeeded()`, ligne 78+) — limite fixe, non exposée en configuration. |
| `FieldValidationService::BUILT_IN_VALIDATIONS` | `admin/src/Service/FieldValidationService.php:17-23` | `['notempty','equal','email','date_not_before','date_is_valid']` | Liste fixe des règles de validation natives disponibles par champ, complétée dynamiquement par des validations externes déclarées par d'autres plugins (`getAvailableValidationNames()`, lignes ~40-58). |
| `FormAuditService::STATUS_OK` / `STATUS_WARNING` / `STATUS_ERROR` | `admin/src/Service/FormAuditService.php:28-30` | `'ok'` / `'warning'` / `'error'` | Vocabulaire fixe des niveaux de sévérité de l'audit de formulaire — configuration structurelle, non paramétrable. |
| `PermissionService::CONTEXT_KEY` / `SCOPE_INPUT_KEY` | `admin/src/Service/PermissionService.php:24,30` | `'__cb_context'` / `'cb_permission_scope_form_id'` | Clés internes fixes utilisées pour propager le contexte de permission ; techniques, non fonctionnelles au sens "réglage utilisateur". |

**Comportement déduit** : le composant ne recourt pas à un fichier
`Constants`/`Config` central unique — les constantes de configuration
statique sont distribuées par classe de service (`Logger`, `ListLimitHelper`,
`FieldValidationService`, `FormAuditService`), ce qui est cohérent avec la
séparation MVC/services imposée par `AGENTS.md`.

---

## 16. Configuration stockée en base (recherche et conclusion)

**Fait observé** — inventaire complet des tables créées par
`admin/sql/install.sql` :

```
#__contentbuilderng_articles
#__contentbuilderng_elements
#__contentbuilderng_forms
#__contentbuilderng_list_records
#__contentbuilderng_list_states
#__contentbuilderng_rating_cache
#__contentbuilderng_records
#__contentbuilderng_registered_users
#__contentbuilderng_resource_access
#__contentbuilderng_storages
#__contentbuilderng_storage_fields
#__contentbuilderng_users
#__contentbuilderng_verifications
```

**Conclusion (fait observé)** : **aucune table `#__contentbuilderng_config`
ou `#__contentbuilderng_settings` n'existe**. Il n'y a donc pas de
configuration système stockée en base en dehors du mécanisme Joomla standard
(`#__extensions.params` pour le composant et pour chaque plugin, lu via
`ComponentHelper::getParams()` / `PluginHelper::getPlugin()->params` +
`Registry`). Toute donnée en base relative à `storages`/`storage_fields`/
`forms` est une donnée **métier configurée par l'utilisateur final** (au sens
"contenu créé dans l'outil"), pas une configuration système au sens Joomla du
terme — voir §17.

---

## 17. Frontière avec Storages / Storage Fields / Forms

Les tables `#__contentbuilderng_storages`, `#__contentbuilderng_storage_fields`
et `#__contentbuilderng_forms` (et les tables associées `elements`,
`records`, etc.) constituent le **modèle de données dynamique** du
constructeur de formulaires : chaque installation de ContentBuilder NG y
définit ses propres formulaires, champs et stockages, ce qui est fonctionnellement
plus proche d'un "contenu structuré configuré par l'utilisateur métier" que
d'une configuration système au sens classique (Joomla `params`). Ces entités
ne sont donc **pas détaillées dans ce document** ; voir :

- `03-data-model.md` pour le schéma de ces tables (colonnes, clés, relations).
- `04-features.md` pour le fonctionnement du Storage Wizard, des formulaires
  et de la construction dynamique de stockage.

La frontière retenue dans ce document : est traité comme "configuration
système" (§1-15) tout paramètre stocké dans `#__extensions.params` (composant
ou plugin) via le mécanisme standard Joomla, plus les constantes PHP
statiques ; est laissé hors périmètre tout ce qui est une donnée métier créée
et modifiée par l'utilisateur final via les écrans de gestion de formulaires
(Storages, Forms, Elements).

---

## 18. Synthèse des paramètres morts / suspects

**Paramètres XML fonctionnels totaux documentés** : 18
(7 composant, dont 1 ACL native + 5 image_scale/paypal/system répartis comme
suit : `max_filesize` ×1, `paypal` ×5, `contentbuilderng_system` ×6, plus 6
paramètres composant réels). Décompte détaillé :

- Composant (`admin/config.xml`) : 6 paramètres métier + 1 champ ACL natif (`rules`) = **7**.
- `contentbuilderng_image_scale` : **1** (`max_filesize`).
- `contentbuilderng_verify/paypal` : **5** (`business`, `token`, `test`, `test_business`, `test_token`).
- `contentbuilderng_system` : **6** (`limit_per_turn`, `disable_new_articles`, `nocache`, `is_auto_groups`, `auto_groups`, `auto_groups_limit_views`).

**Total paramètres fonctionnels documentés : 19** (en comptant `rules`).
S'y ajoutent une vingtaine de champs `type="note"` purement informatifs
(cblist ×5, cbstats ×13, download ×1, rating ×1, verify/content ×1) qui ne
stockent ni ne pilotent aucune valeur — ils sont listés par exhaustivité mais
ne comptent pas comme "paramètres de configuration".

**Paramètres déclarés mais jamais lus dans le code (morts) : aucun trouvé.**
Tous les paramètres fonctionnels identifiés (composant + `image_scale` +
`paypal` + `contentbuilderng_system`) ont été retracés jusqu'à au moins un
point de lecture (`params->get()`/`->def()`) dans le code PHP correspondant.

**Zones d'incertitude / dette potentielle signalées** :

1. **`enable_validations`** : double lecture — une lecture réellement gating
   (`admin/services/provider.php` → `FieldValidationService`) et une lecture
   purement cosmétique côté front (`site/tmpl/{details,list,edit}/default.php`)
   qui n'affecte que l'affichage du panneau de debug. Cette duplication de
   lecture (3 fichiers front + 1 fichier admin, 4 appels identiques à
   `ComponentHelper::getParams('com_contentbuilderng')->get('enable_validations', 1)`)
   pourrait être mutualisée mais ne constitue pas un bug fonctionnel.
2. **`token` / `test_token` (plugin `paypal`)** : champs `type="text"` en clair
   plutôt que `type="password"` pour des valeurs potentiellement sensibles —
   à clarifier avec Gilles avant toute modification du champ.
3. **`auto_groups` (plugin système)** : dans
   `ContentbuilderngSystem.php`, la même expression
   `implode(',', array_map('intval', $pluginParams->get('auto_groups', [])))`
   est recalculée à trois reprises (lignes 240, 241, 290) sous des noms de
   variables différents (`$autoGroupsIntList`, `$autoGroupsRawList`,
   `$autoGroupsIntList2`) — piste de redondance à vérifier, non confirmée
   comme bug faute d'avoir lu l'intégralité de la méthode dans le cadre de
   cette tâche de documentation de configuration.
4. **Homonymie `max_filesize`** : le paramètre de plugin
   `contentbuilderng_image_scale.max_filesize` (config système, global) et
   l'option de champ `max_filesize` par élément de formulaire (donnée
   métier, en base dans `#__contentbuilderng_elements.options`) portent le
   même nom mais n'ont aucun lien de code entre eux — source de confusion
   documentaire potentielle si non distinguée (voir §5).
