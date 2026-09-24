# 07 — Sécurité

> Rétro-analyse (reverse engineering) de `com_contentbuilderng` — Joomla 6 / PHP 8.3+.
> Ce document est **uniquement descriptif** : il documente les mécanismes de
> sécurité réellement présents dans le code (ACL, authentification, CSRF,
> validation des entrées, échappement, SQL, upload, accès direct aux fichiers,
> secrets, appels externes), ainsi que les zones où aucune protection
> équivalente n'a été trouvée. Le code est la source de vérité : aucune
> modification, correction ou refactorisation n'a été apportée au dépôt pour
> produire ce document, y compris lorsqu'une faiblesse est constatée.
>
> Méthode de qualification des affirmations :
> - **Fait observé** : lu directement dans le code, avec référence `fichier:ligne`.
> - **Comportement déduit** : logique assemblée à partir de plusieurs faits observés.
> - **Hypothèse** : interprétation plausible non totalement vérifiée dans le code lu.
> - **Zone inconnue** : point non tranché, incohérence potentielle, ou zone à
>   signaler pour investigation ou couverture de test ultérieure — pas une
>   invitation à corriger dans ce document.

## Sommaire

1. [ACL Joomla](#1-acl-joomla)
2. [Authentification](#2-authentification)
3. [CSRF](#3-csrf)
4. [Validation des entrées / filtrage](#4-validation-des-entrées--filtrage)
5. [Échappement HTML / XSS](#5-échappement-html--xss)
6. [SQL / requêtes préparées](#6-sql--requêtes-préparées)
7. [Upload de fichiers](#7-upload-de-fichiers)
8. [Accès direct aux fichiers PHP](#8-accès-direct-aux-fichiers-php)
9. [Secrets / configuration](#9-secrets--configuration)
10. [Appels externes (plugin PayPal)](#10-appels-externes-plugin-paypal)
11. [Synthèse des zones à signaler](#11-synthèse-des-zones-à-signaler)

---

## 1. ACL Joomla

### 1.1 Déclaration (`admin/access.xml`)

**Fait observé** : `admin/access.xml:1-92` déclare, dans la section
`component`, les actions Joomla standard `core.admin`, `core.options`,
`core.manage`, `core.create`, `core.delete`, `core.edit`, `core.edit.own`,
`core.edit.state`, puis deux actions propres au composant :
`contentbuilderng.manage` (`admin/access.xml:54-58`) et
`contentbuilderng.admin` (`admin/access.xml:60-64`). Une seconde section
`form` (`admin/access.xml:70-91`) déclare `core.delete`, `core.edit`,
`core.edit.state`, avec un commentaire du dépôt (`admin/access.xml:66-69`)
expliquant que `FormController` autorise `core.edit` sur l'actif
`com_contentbuilderng.form.<id>` et que la section rend ces règles
configurables au lieu d'hériter silencieusement de l'actif racine.

**Zone à signaler** : `contentbuilderng.manage` et `contentbuilderng.admin`
sont documentées dans `docs/fr/permissions-acl.md:12-13` et
`docs/en/permissions-acl.md:11-12` comme déterminant « qui peut administrer
le composant », mais aucune occurrence de
`->authorise('contentbuilderng.manage', ...)` ou
`->authorise('contentbuilderng.admin', ...)` n'a été trouvée dans
`admin/src`, `site/src` ou `plugins` (recherche exhaustive). Tout le contrôle
d'accès back-office observé s'appuie exclusivement sur les actions Joomla
natives `core.manage` / `core.edit` / `core.edit.state` (voir §1.2). Ces deux
cases à cocher existent donc dans l'écran Permissions de Joomla sans effet
observable dans le code lu.

### 1.2 Contrôle d'accès back-office

**Fait observé — garde-fou commun** : `admin/src/Controller/Traits/ComponentAccessTrait.php:34-59`
définit `ComponentAccessTrait`, qui redéfinit `execute($task)` pour appeler
`assertComponentAccess()` avant `parent::execute($task)` — c'est-à-dire
**avant chaque tâche**, quel que soit le contrôleur back-office qui l'utilise.
`assertComponentAccess()` (lignes 47-58) vérifie
`$app->getIdentity()->authorise('core.manage', 'com_contentbuilderng')` et
lève `Joomla\CMS\Access\Exception\NotAllowed` (HTTP 403) sinon. Le
commentaire de tête (lignes 21-33) explicite l'intention : empêcher qu'une
tâche d'écriture (DDL, état d'enregistrement, indicateurs de vérification)
reste atteignable via un contrôleur non gardé individuellement.

**Fait observé — usage du trait** : le trait est utilisé par tous les
contrôleurs back-office sauf un :
`AboutController.php:41-43`, `ConfigtransferController.php:24-26`,
`DatatableController.php:28-30`, `DisplayController.php:25-27`,
`ElementoptionsController.php:28-30`, `FormController.php:45-47`,
`FormsController.php:42-44`, `StorageController.php:43-45`,
`StoragefieldController.php:33-35`, `StoragesController.php:36-38`,
`StoragewizardController.php:45-47`, `UsersController.php:30-32`.
`TitlesetController.php` (`admin/src/Controller/TitlesetController.php:16`)
n'utilise **pas** le trait, mais chaque méthode publique appelle
manuellement `assertAuthorized()` (`admin/src/Controller/TitlesetController.php:291-296`,
même contrôle `core.manage`) avant toute opération — `save()`/`apply()`
(lignes 27-35 via `saveAndRedirect()` ligne 269-289), `save2copy()` (ligne 40),
`deleteSelected()` (ligne 60), `exportSelected()` (ligne 81),
`importFiles()` (ligne 137), `validateFile()` (ligne 241),
`deleteFile()` (ligne 257). **Comportement déduit** : le résultat pratique
(gate `core.manage` sur toute action) est équivalent à celui du trait, mais
par une voie différente (pas de garantie automatique en cas d'ajout futur
d'une méthode sans appel explicite, contrairement au trait qui protège
`execute()` globalement).

**Fait observé — contrôles plus fins par action** : au-dessus du gate
`core.manage`, certaines actions ajoutent un contrôle `core.edit` /
`core.edit.state` par formulaire :
- `admin/src/Controller/FormController.php:491-495, 538-542, 579-583, 620-624, 662-666` :
  avant de modifier un formulaire donné, vérifie
  `core.manage` **OU** `core.edit` sur l'actif `com_contentbuilderng.form.<id>`
  **OU** `core.edit` global sur le composant.
- `admin/src/Controller/StorageController.php:80` (`core.edit`),
  `:590` (`core.manage`), `:650` (`core.edit`), `:1259` (`core.edit.state`).
- `admin/src/Controller/StoragewizardController.php:81` (`core.manage`).
- `admin/src/Controller/TitlesetController.php:293` (`core.manage`).
- `admin/src/Controller/ConfigtransferController.php:61, 74` (`core.manage`,
  import/export de configuration).
- `admin/src/Controller/AboutController.php:124, 766, 812, 843, 923`
  (`core.manage` avant audit, log, export, import).

**Fait observé — CSRF systématique côté back-office** : chaque tâche de
mutation appelle `$this->checkToken()` avant tout traitement — repéré dans
`FormController.php` (25 occurrences), `StorageController.php` (16),
`StoragewizardController.php` (16), `AboutController.php` (13),
`TitlesetController.php` (8), `DatatableController.php`,
`ElementoptionsController.php`, `StoragefieldController.php`,
`UsersController.php`, `FormsController.php` (via `setDebugMode()`,
`admin/src/Controller/FormsController.php:230-232`, appelée par
`debug_on()`/`debug_off()`), `StoragesController.php` (`copy()`).

### 1.3 Contrôle d'accès front-office — moteur applicatif propre

**Fait observé** : le front-office **n'utilise pas** `$user->authorise()`
pour les permissions fonctionnelles (voir/éditer/supprimer un enregistrement,
accès à une liste). Il s'appuie sur un moteur applicatif propre,
`PermissionService` (`admin/src/Service/PermissionService.php`, 741 lignes,
namespace `Administrator\Service` bien qu'utilisé par le front — pas une
anomalie : c'est le service partagé back/front du composant), qui :

- calcule et met en session (`setPermissions()`, lignes 225-415) un jeu de
  permissions par formulaire/enregistrement, dérivé de la table
  `#__contentbuilderng_forms.config` (matrice de permissions par groupe
  Joomla + section « propre » `own`/`own_fe`) croisée avec
  `#__contentbuilderng_users` (compteurs, vérification, limites par
  utilisateur) ;
- résout les groupes Joomla effectifs de l'utilisateur courant, **y compris
  les groupes parents** (`getEffectiveGroupIds()`, lignes 74-115, remontée
  de `parent_id` via `#__usergroups`) ;
- vérifie l'action demandée (`checkPermissions()`, lignes 417-570) contre :
  état publié du formulaire, limites d'ajout/édition, gate de vérification
  (paiement/e-mail, voir §1.4), permission de groupe, et permission
  « propre » avec vérification de propriété réelle en base
  (`$form->isOwner(...)`, lignes 512, 534) ou de correspondance de session
  pour les soumissions anonymes (`session_id`, lignes 504-527).
- **Fait observé — anti-rejeu de contexte** : `contextMatchesRequest()`
  (lignes 194-223) refuse un jeu de permissions qui n'a pas été calculé pour
  le formulaire/les enregistrements réellement ciblés par la requête
  courante — le commentaire (lignes 183-193) documente explicitement le bug
  visé : sans ce contrôle, un jeu calculé pour l'enregistrement A pouvait
  autoriser une action sur l'enregistrement B lors d'une requête ultérieure.

`authorize()` / `authorizeFe()` (lignes 618-626) sont les points d'entrée
utilisés par les contrôleurs front (`EditController`, `ListController`,
`DetailsController`, `ApiController`, ainsi que le plugin de contenu
`contentbuilderng_download`, voir §7/§1.3.1) et exposent une variante
booléenne de `checkPermissions()` sans lever d'exception.

**Fait observé — `ListController`** : `site/src/Controller/ListController.php:66-86`
(`armPermissionsForSelection()`) recalcule explicitement les permissions pour
**la sélection courante** avant chaque action de masse, avec un commentaire
(lignes 58-64) documentant le même type de bug historique (« ces actions
autorisaient auparavant directement depuis la session, càd contre le
formulaire/enregistrement armé par la page précédente »). L'affichage de
liste vérifie `listaccess` en session (`ListController.php:439-445`) sauf en
prévisualisation admin signée (§1.3.2).

**Zone à signaler — `ExportController` (export Excel front) sans gate
`listaccess`** : `site/src/Controller/ExportController.php:19-73` délègue
entièrement à la vue `export` sans jamais appeler
`PermissionService::checkPermissions()` / `authorizeFe()`. `ExportModel`
(`site/src/Model/ExportModel.php`) filtre bien les enregistrements par état
publié (`PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly()`,
ligne 404) et par propriétaire si `own_only_fe` est actif (lignes 405-407),
mais ne recherche à aucun moment la chaîne `listaccess` ni n'appelle
`PermissionService`. À comparer avec `ListController::display()`
(`site/src/Controller/ListController.php:439-445`), qui appelle
explicitement `checkPermissions('listaccess', ..., $suffix)` avant
d'afficher la même liste. **Comportement déduit** : un formulaire dont la
matrice de permissions restreint `List Access` à certains groupes reste
listable normalement via `view=list`, mais son export (`view=export&id=<id>`)
paraît accessible à quiconque peut atteindre l'URL, tant que le formulaire
est publié et que `export_xls` (contrôle de conception, pas d'ACL par
utilisateur) contient au moins un élément — sous réserve que rien d'autre
dans la chaîne d'appel (menu Joomla, filtre d'accès de niveau Joomla sur le
menu) ne referme cet accès. Ce point n'a pas été vérifié en conditions
réelles (pas d'environnement Joomla exécutable dans cette analyse) ; il est
présenté comme une divergence de code entre deux chemins qui rendent la même
donnée.

**Fait observé — export via `DetailsController`/`ListController`
(bouton export intégré à la liste)** : contrairement au point ci-dessus,
lorsque l'export est déclenché depuis l'écran de liste (lien généré par
`ListController`/`site/tmpl/list/default.php`), la session a déjà été armée
par le passage préalable par `ListController::display()`, qui applique le
gate `listaccess`. La zone signalée ci-dessus concerne l'accès **direct** à
l'URL `view=export`.

#### 1.3.1 `ApiController` — permissions par méthode/action

**Fait observé** : `site/src/Controller/ApiController.php:132`
(`assertApiPermissions((new ApiPermissionRequirementService())->getRequiredPermissions($method, $action, $recordId))`)
calcule les permissions requises selon le verbe HTTP et l'action
(`admin/src/Service/ApiPermissionRequirementService.php:24, 38` : ex.
`GET` liste ⇒ `['api', 'view', 'listaccess']`) puis les vérifie via
`PermissionService`. `admin/src/Service/ApiFieldPermissionService.php` filtre
en plus, champ par champ, les données exposées selon un flag « API
autorisée » par élément (`getAllowedReferenceMap()`,
`site/src/Controller/ApiController.php:762, 824`). Les mutations (POST/PUT/PATCH)
exigent en plus un jeton CSRF (§3).

#### 1.3.2 Liens de prévisualisation admin signés (HMAC)

**Fait observé** : plusieurs contrôleurs front (`ExportController.php:37-72`,
`EditController.php:770-800` environ, `ListController.php:510-545` environ,
`DetailsController.php:360-380` environ, `ApiController.php:1160-1175`
environ) et vues admin (`admin/src/View/Form/HtmlView.php:405-413`,
`admin/src/View/Forms/HtmlView.php:145-179`,
`admin/src/View/Storage/HtmlView.php:390-405`,
`admin/src/View/Storages/HtmlView.php:120-155`,
`admin/src/Helper/FormSourceFactory.php:35-66`,
`admin/src/Service/PermissionService.php:676-708`) partagent un mécanisme
de prévisualisation signé : `CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper::buildPayload()`
concatène `cible|expiration|acteur_id|acteur_nom|utilisateur_id`
(`site/src/Helper/PreviewLinkHelper.php:18-21`), signé par
`hash_hmac('sha256', $payload, $app->get('secret'))` où `secret` est le
secret d'application Joomla (`configuration.php`), et vérifié côté
destinataire par `hash_equals(...)` (comparaison à temps constant — bon
réflexe contre le timing attack). Le lien expire (`cb_preview_until` comparé
à `time()`) et exige un `cb_preview_user_id` positif. **Comportement
déduit** : ce mécanisme permet à un administrateur authentifié de générer un
lien de prévisualisation d'un formulaire non publié ou restreint, sans
authentification supplémentaire du visiteur du lien — sécurité reposant
entièrement sur le secret d'application Joomla et sur la confidentialité du
lien signé transmis (pas de garde côté génération autre que le contrôle
`core.manage`/`core.edit` qui protège les écrans où le lien est affiché).

---

## 2. Authentification

**Fait observé** : aucun système d'authentification propre au composant.
L'identité de l'utilisateur courant est systématiquement obtenue via
`Joomla\CMS\Application\CMSApplicationInterface::getIdentity()` (Joomla
natif) — plus de 50 fichiers, ex. `site/src/Model/Edit/OwnershipTrait.php:59`
(`(int) ($this->app->getIdentity()->id ?? 0)`),
`admin/src/Service/PermissionService.php:68`
(`getCurrentUserId()`), `admin/src/Controller/DisplayController.php:48`.

**Fait observé — table `#__contentbuilderng_users`** : cette table
(`admin/sql/install.sql:661-696`) ne stocke **aucun identifiant ni mot de
passe**. Sa colonne `userid` référence explicitement `#__users.id` (Joomla) —
commentaire du script d'installation (`admin/sql/install.sql:666`). Elle
sert uniquement de compteur/état par (utilisateur Joomla, formulaire CB) :
nombre d'enregistrements soumis, drapeaux `verified_view/new/edit` +
horodatage, limites individuelles `limit_add`/`limit_edit`, et
`published` (blocage d'un utilisateur pour une vue donnée). Ce n'est donc
pas un système d'authentification alternatif, mais une table de compteurs et
de statut applicatif adossée à l'identité Joomla.

**Fait observé — fonctionnalité « agir comme inscription » (`act_as_registration`)** :
lorsqu'un formulaire CB est configuré pour créer des comptes Joomla à la
soumission, `site/src/Model/EditModel.php` délègue entièrement à l'API
utilisateur Joomla native :
- hachage du mot de passe via `Joomla\CMS\User\UserHelper::hashPassword()`
  (`site/src/Model/EditModel.php:2313`), pas d'implémentation de hachage
  maison ;
- génération d'un jeton d'activation via
  `ApplicationHelper::getHash(UserHelper::genRandomPassword())`
  (`site/src/Model/EditModel.php:2355`), soit le générateur de mot de passe
  aléatoire natif de Joomla, pas un `mt_rand()`/`uniqid()` maison.
- **Comportement déduit** : le compte créé est un compte Joomla standard
  (table `#__users`), soumis ensuite aux mêmes règles ACL/authentification
  Joomla que tout autre utilisateur — pas de « second système d'utilisateurs »
  parallèle à celui de Joomla.

**Fait observé — soumissions anonymes** : pour les formulaires accessibles
sans compte, la propriété d'un enregistrement peut être déterminée par
correspondance de `session_id` Joomla plutôt que par `userid`
(`admin/src/Service/PermissionService.php:504-527`, comparaison
`$sessionId == $currentSessionId`). **Comportement déduit** : ce mode
n'authentifie personne — il permet seulement à l'auteur anonyme d'une
soumission de continuer à agir dessus tant que sa session serveur (cookie de
session Joomla) reste valide.

**Zone inconnue** : le comportement précis du filtre d'entrée Joomla
`'html'` (`Joomla\Input\Input::get($name, $default, 'html')`, utilisé par
exemple en `site/src/Model/EditModel.php:1091`) — assainissement effectif
appliqué en amont de l'enregistrement — n'a pas été ré-audité ici : c'est du
code du noyau Joomla, hors du dépôt analysé.

---

## 3. CSRF

**Fait observé — back-office** : `checkToken()` (méthode standard de
`Joomla\CMS\MVC\Controller\BaseController`, qui vérifie le jeton de
formulaire Joomla) est appelé en tête de chaque tâche de mutation dans tous
les contrôleurs admin (détail exhaustif au §1.2).

**Fait observé — front-office** :
- `site/src/Controller/ListController.php:91, 185`
  (`$this->checkToken('post', false)`, retour booléen plutôt qu'exception,
  avec levée manuelle de `\RuntimeException(Text::_('JINVALID_TOKEN'), 403)`
  juste après si le jeton est invalide).
- `site/src/Controller/EditController.php` et `DetailsController.php` :
  jetons vérifiés dans le flux d'enregistrement (via `PermissionService`/
  contrôleur — le champ `HTMLHelper::_('form.token')` est émis dans les
  templates, voir ci-dessous, et vérifié côté serveur par le contrôleur
  `Edit`).
- **API REST-like** (`site/src/Controller/ApiController.php:950-961`,
  `assertStateChangingRequestToken()`) : accepte un jeton soit en paramètre
  de formulaire (session), soit dans l'en-tête `X-CSRF-Token`
  (`$_SERVER['HTTP_X_CSRF_TOKEN']`), comparé avec `hash_equals()` au jeton de
  session (`Session::getFormToken()`), puis validé via
  `Session::checkToken('post')` ou `Session::checkToken('get')`
  (lignes 959-961). Appelée pour les mutations, ex. lignes 173, 518.

**Fait observé — jeton présent dans les formulaires HTML rendus** :
`HTMLHelper::_('form.token')` est émis dans au moins 18 gabarits (recherche
exhaustive dans `site/tmpl` et `admin/tmpl`), notamment
`site/tmpl/edit/default.php:500, 1219, 1269, 1314`,
`site/tmpl/list/default.php:1764`, `site/tmpl/details/default.php:314`,
`site/tmpl/publicforms/default.php:320`. Tous les formulaires HTML repérés
dans `site/tmpl` (`<form ...>`) possèdent un jeton correspondant dans le même
fichier ; aucun formulaire de mutation sans jeton visible n'a été identifié.
Le seul formulaire en méthode `GET`
(`site/tmpl/publicforms/default.php:49`, `method="get"`) sert de formulaire
de filtrage/recherche, pas de mutation — un jeton CSRF y est présent
mais son utilité est réduite pour une requête `GET` en dehors du contrôle
`checkToken('get')` déjà mentionné côté API.

---

## 4. Validation des entrées / filtrage

**Fait observé — filtrage typé systématique** : usage massif des accesseurs
typés de `Joomla\Input\Input` : `getInt` (338 occurrences dans
`admin/src`+`site/src`+`plugins`), `getCmd` (160), `getString` (105),
`getBool` (87), contre **une seule** occurrence de `getRaw()`
(`admin/src/Model/FormModel.php:966`,
`$jformRaw = (array) $input->post->getRaw('jform')`, tableau de
configuration de formulaire ensuite validé champ par champ en aval, pas
affiché tel quel).

**Fait observé — validation JForm / Field / Rule custom** :
- `admin/src/Field/ListlimitField.php`, `PaginationchoicesField.php` :
  champs JForm custom pour les listes/paginations.
- `admin/src/Rule/PaginationchoicesRule.php:12-24` : règle JForm qui délègue
  la validation à `ListLimitHelper::parsePaginationChoices()` et retourne
  `false` en cas de `UnexpectedValueException`.
- `site/src/Field/*.php` (`CategoriesField`, `MenucategoryField`,
  `FormsField`, etc.) : champs JForm custom pour la configuration des menus
  Joomla liés au composant.
- **Fait observé — bascule globale de validation** (déjà documentée dans
  `docs/reverse-engineering/08-configuration.md` — reprise ici pour le
  contexte sécurité) : le paramètre `enable_validations` du composant
  (`admin/services/provider.php:100-106`, `admin/src/Service/FieldValidationService.php:65-88`)
  désactive, s'il est à `0`, **l'ensemble** des règles de validation de champ
  configurées (`notempty`, `equal`, `email`, `date_not_before`,
  `date_is_valid`, plus les validations externes déclarées par des plugins
  tiers) côté soumission de formulaire — un interrupteur global qui,
  désactivé, neutralise silencieusement toute validation métier configurée
  par le concepteur de formulaire. C'est un comportement de configuration
  documenté, pas une faille : le code fait précisément ce que le paramètre
  annonce.

**Fait observé — captcha serveur** : `site/src/Model/EditModel.php:912-936`
vérifie le champ « captcha » côté serveur avec la bibliothèque tierce
`bgli100/securimage` (`new \Securimage(); $securimage->check($cap_value)`,
ligne 928-930), chargée depuis
`JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/bgli100/securimage/securimage.php'`
(ligne 917) ; en cas d'échec, la soumission est marquée
`cb_submission_failed` (ligne 931) — le rejet est donc appliqué côté
serveur, pas seulement côté client.

**Fait observé — upload : liste blanche d'extensions par défaut** : voir
détail complet au §7.

---

## 5. Échappement HTML / XSS

**Fait observé — discipline générale des gabarits** : recherche exhaustive
des `echo $variable` / `<?= $variable` dans `site/tmpl` et `admin/tmpl` (hors
`Text::_()`, `HTMLHelper`, casts `(int)`, `number_format`) : un seul résultat
brut hors échappement explicite (`admin/tmpl/forms/default.php:460`), qui
affiche une icône de tri Joomla native (`$this->pagination->orderDownIcon(...)`),
pas une donnée utilisateur. Le reste des templates examinés utilise
systématiquement `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` ou
`$this->escape()` (ex. `site/tmpl/details/default.php:203, 299, 300, 408,
411, 414, 420, 429, 536, 546, 559, 566`).

**Fait observé — moteur de rendu des valeurs de champ**
(`admin/src/Service/TemplateRenderService.php`) : c'est ici que les valeurs
soumises par les utilisateurs (enregistrements CB) sont injectées dans les
gabarits HTML configurés par le concepteur de formulaire (remplacement de
jetons `{nom}`/`{nom:value}`/`{nom:label}`). Deux comportements distincts
selon la configuration du champ :
- **Cas par défaut (`allow_html` absent ou faux)** : la valeur passe par
  `TextUtilityService::allhtmlspecialchars()`
  (`admin/src/Service/TemplateRenderService.php:707, 719`;
  implémentation dans `admin/src/Service/TextUtilityService.php:20-23`), qui
  applique `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')` puis
  `cleanString()`.
- **Cas `allow_html = true`** (champ explicitement configuré par le
  concepteur de formulaire pour accepter du HTML — ex. éditeur riche) :
  la valeur passe uniquement par `TextUtilityService::cleanString()`
  (`admin/src/Service/TemplateRenderService.php:717-718`, branche
  `$allowHtml ? $textUtilityService->cleanString(...) : ...`), **sans**
  `htmlspecialchars()`. Or `cleanString()`
  (`admin/src/Service/TextUtilityService.php:25-31`) ne fait
  qu'échapper les caractères `[ ] { } ( ) |` (pour protéger la syntaxe du
  moteur de jetons `{champ}` du composant lui-même) — ce n'est **pas** un
  filtre anti-XSS ni un purificateur HTML (pas de suppression de
  `<script>`, d'attributs `on*`, ni d'URI `javascript:`).
  Même schéma en `admin/src/Service/TemplateRenderService.php:900-995`
  (`getTemplate()`, rendu détail) et `:1098, :1157-1158`
  (`getEmailTemplate()`, rendu e-mail — la branche `allow_html && $html`
  copie la valeur brute, ligne 1158).

  **Comportement déduit** : lorsqu'un champ de formulaire CB est configuré
  avec `allow_html = true`, la valeur soumise par l'utilisateur qui a rempli
  ce champ est réaffichée dans les pages de détail/liste/e-mail sans
  échappement HTML — seul un auto-purificateur minimal de la propre syntaxe
  de gabarit du composant est appliqué. Il s'agit très probablement d'une
  fonctionnalité intentionnelle (permettre un champ « éditeur riche »
  affichant du HTML formaté), et non d'un oubli d'échappement générique : le
  chemin par défaut (`allow_html` non coché) échappe correctement. L'impact
  concret dépend entièrement de qui peut soumettre une valeur dans un champ
  marqué `allow_html` (concepteur de formulaire lui-même, utilisateur
  enregistré, ou visiteur anonyme selon la configuration ACL du formulaire —
  §1.3) et de qui peut ensuite consulter l'enregistrement — points de
  configuration par formulaire, non fixés dans le code.

**Fait observé — saisie ct̂é « input » pour ces mêmes champs** :
`site/src/Model/EditModel.php:1090-1091` lit la valeur postée avec le filtre
Joomla `'html'` quand `allow_html` est vrai
(`$this->app->getInput()->post->get('cb_' . $id, null, 'html')`), contre
`'raw'` sinon (ligne 1093). **Zone inconnue** : le filtre `'html'` de
`Joomla\Input\Input` applique un assainissement du noyau Joomla (liste noire
de balises/attributs) non ré-audité ici (hors dépôt) — il s'agit donc d'une
couche de filtrage en amont dont l'efficacité précise contre le XSS n'a pas
été vérifiée dans le cadre de cette analyse, à mettre en regard de l'absence
d'échappement en sortie documentée ci-dessus.

---

## 6. SQL / requêtes préparées

**Fait observé — usage massif du Query Builder** : 698 appels à
`$db->setQuery(` et 641 usages de `getQuery(true)`/`createQuery` dans
`admin/src`+`site/src`. L'immense majorité des requêtes observées lors de
cette analyse utilisent `$db->quoteName()` pour les identifiants et
`$db->quote()` pour les valeurs, y compris pour les DDL dynamiques
(`ALTER/DROP/TRUNCATE TABLE` de tables de stockage générées par
l'utilisateur), ex. `admin/src/Model/StorageModel.php:310, 330, 350, 470,
503, 855, 957, 1084, 1181, 1252, 1470`.

**Fait observé — test de non-régression SQL dédié** :
`admin/tests/Unit/Security/SqlHardeningTest.php` scanne le code source de
quatre fichiers (`plugins/content/contentbuilderng_download/src/Extension/ContentbuilderngDownload.php`,
`site/src/Model/EditModel.php`, `admin/src/Model/StorageModel.php`,
`admin/src/types/com_contentbuilderng.php`) pour vérifier l'absence de
motifs d'interpolation SQL non sûrs déjà corrigés par le passé (concaténation
directe de `$file_id`, `$session->getId()`, noms de table dans des
`DROP/ALTER/TRUNCATE TABLE`). **Comportement déduit** : ce test documente
des incidents de production réels déjà corrigés et sert de garde de
non-régression, sans couvrir l'intégralité du code source.

**Fait observé — `GROUP_CONCAT ... SEPARATOR` en syntaxe MySQL correcte** :
`admin/tests/Unit/Sql/GroupConcatSeparatorSyntaxTest.php` scanne
`admin/src`, `site/src` et `plugins` pour rejeter tout `SEPARATOR` suivi d'un
appel de fonction plutôt que d'un littéral chaîne (rappel de la règle
MySQL/MariaDB de `AGENTS.md`). Le cas corrigé est visible dans
`admin/src/types/com_breezingformsng.php:840`
(`$rawSeparator = $db->quote(chr(31));`), avec un commentaire explicite
(lignes 838-839 : « SEPARATOR requires a string literal, not an expression
like CHAR(31) »), utilisé lignes 843 et 845.

**Fait observé — interpolation SQL brute sans `$db->quote()` (élément
`GROUP_CONCAT`)** : dans le même fichier,
`admin/src/types/com_breezingformsng.php:829, 831, 843, 845, 1022, 1105`,
l'expression `GROUP_CONCAT` insère `{$element['name']}` (ligne 829/843/1022)
ou `$name` (ligne 1105) directement dans le fragment SQL construit en
chaîne, sans passer par `$db->quote()`/`$db->escape()` :
```
"GROUP_CONCAT( DISTINCT ( Case When s.`name` = '{$element['name']}' Then s.`value` End ) Order By s.`id` SEPARATOR ', ' ) As `col{$element['id']}Value`,"
```
`$element['name']` provient de la colonne `#__facileforms_elements.name`
(chargée juste avant, `admin/src/types/com_breezingformsng.php:809-816`) —
c'est-à-dire le nom d'un champ de formulaire défini par un concepteur de
formulaire back-office (accès protégé par `core.manage`/`core.edit`, §1.2),
pas une entrée soumise par un visiteur du site. `$element['id']` est un
entier chargé depuis la même table. **Fait observé — contraste avec les
lignes voisines** : la même méthode utilise `intval($id)` à la ligne 1106
pour le cas symétrique côté `element` (identifiant numérique), ce qui montre
que le motif d'échappement correct est connu et appliqué ailleurs dans le
même fichier, mais pas de façon uniforme pour `name`. **Zone à signaler** :
ce fichier ne fait pas partie des quatre fichiers couverts par
`SqlHardeningTest.php` — cette interpolation n'est donc pas gardée par un
test de non-régression SQL, à la différence des motifs déjà corrigés
ailleurs. L'exploitabilité dépend de qui peut créer/renommer un élément de
formulaire BreezingForms importé (rôle back-office `core.manage`/`core.edit`
sur le composant).

**Fait observé — requête SQL en chaîne littérale, sécurisée par `intval()`
seul** : `site/src/Model/ExportModel.php:154`
(`buildQuery()`) : `'Select * From #__contentbuilderng_forms Where id = ' . intval($this->_id) . ' And published = 1';`
— pas de Query Builder ici, mais l'unique variable interpolée est
explicitement castée en entier, ce qui neutralise l'injection sur ce point
précis.

**Fait observé — protection contre la lecture de fichier arbitraire via un
champ enregistré** : `admin/src/Helper/ContentbuilderngHelper.php:111-147`
(`is_internal_path()`) résout le chemin réel (`realpath`) d'une valeur de
champ « fichier » stockée en base et vérifie qu'il reste sous `JPATH_SITE`
avant tout accès disque (lecture, suppression, téléchargement). Utilisé
notamment en upload (§7,
`site/src/Model/EditModel.php:1138, 1288, 1295`) et dans le plugin de
téléchargement (`plugins/content/contentbuilderng_download/src/Extension/ContentbuilderngDownload.php:424-425`,
avec un commentaire explicite : « sans ce contrôle de confinement, un champ
manipulé transforme ce plugin en primitive de lecture de fichier
arbitraire »).

---

## 7. Upload de fichiers

**Fait observé — point d'entrée** : `site/src/Model/EditModel.php:1148`
(`$file = $this->app->getInput()->files->get('cb_' . $id, null, 'array');`),
dans le traitement de soumission d'un champ de type upload, ainsi que
`admin/src/Controller/StorageController.php:163, 566`
(`csv_file`, import CSV/XLS back-office, réservé aux utilisateurs
`core.manage`/`core.edit`).

**Fait observé — liste blanche d'extensions avec refus par défaut** :
`site/src/Model/EditModel.php:81-89` déclare
`DEFAULT_ALLOWED_UPLOAD_EXTENSIONS` (`pdf, txt, csv, rtf, doc, docx, odt,
xls, xlsx, ods, ppt, pptx, odp, png, jpg, jpeg, gif, webp, bmp, tif, tiff,
zip, gz, rar, 7z, mp3, mp4, ogg, webm, wav` — **`svg` volontairement
exclu**, commentaire ligne 84-85 : « peut transporter du script et est
servi inline lorsqu'il est lié directement »). Le commentaire de tête
(lignes 75-77) documente le changement de comportement : « historiquement,
un `allowed_file_extensions` vide signifiait "tout accepter", ce qui
autorisait l'upload de `.php` dans le répertoire de destination configuré
(souvent accessible par le web) ». Le champ peut toujours élargir cette
liste par sa propre configuration `allowed_file_extensions`
(`site/src/Model/EditModel.php:1209-1221`).

**Fait observé — double contrôle d'extension exécutable** :
`hasExecutableExtension()` (`site/src/Model/EditModel.php:91-109`) découpe
le nom de fichier sur **chaque** point (`explode('.', $filename)`, pas
seulement l'extension finale) et rejette toute correspondance avec
`MediaHelper::EXECUTABLES` ∪ `InputFilter::FORBIDDEN_FILE_EXTENSIONS`
(listes Joomla natives) — commentaire ligne 91-96 : contre les
configurations serveur qui exécutent `shell.php.pdf`. Ce contrôle
s'applique **en plus** de la liste blanche (ligne 1234 :
`if ($msg === '' && self::hasExecutableExtension($filename))`), et
délibérément pas via `MediaHelper::canUpload()` (commentaire lignes
1229-1233 : rejetterait des uploads légitimes comme `.docx`/`.zip`/`.svg`
en appliquant en plus la politique étroite de `com_media`).

**Fait observé — contrôle de taille** : `site/src/Model/EditModel.php:1185-1203`,
comparaison de `$file['size']` à `max_filesize` converti en octets
(`k`/`m`/`g`).

**Fait observé — assainissement du nom de fichier** :
`File::makeSafe($file['name'])` (ligne 1150/1152, API Joomla),
puis remplacement de tous les espaces et points internes par `_`
(ligne 1248 : `str_replace(array(' ', '.'), '_', $stripped) . '.' . $ext`,
avec commentaire lignes 1246-1247 sur le risque d'extensions inconnues côté
Apache), troncature à 100 caractères (lignes 1250-1259), et
déduplication par hachage aléatoire si un fichier du même nom existe déjà
(ligne 1263 : `md5(mt_rand(...) . time()) . '_' . $filename` — collision
extrêmement improbable, pas un objectif de sécurité mais d'unicité).

**Fait observé — confinement du répertoire de destination** :
avant l'écriture effective, `ContentbuilderngHelper::is_internal_path($dest)`
est vérifié (ligne 1295) — un chemin de destination configuré hors de
`JPATH_SITE` fait échouer l'upload (`COM_CONTENTBUILDERNG_UPLOAD_FAILED`,
lignes 1296-1297) plutôt que d'écrire hors du site.

**Fait observé — `index.html` de protection créé à la volée** : si le
répertoire de destination ne contient pas déjà `index.html`, il est créé
avant l'écriture du fichier uploadé (`site/src/Model/EditModel.php:1267-1269`,
`File::write($dest . '/index.html', '')`) — protection classique contre le
listage de répertoire sur un serveur mal configuré, appliquée uniquement aux
répertoires d'upload créés dynamiquement (pas à toute l'arborescence du
composant, voir §8).

**Fait observé — upload dans l'écran d'import de « title sets » (back-office)** :
`admin/src/Controller/TitlesetController.php:138-163`
(`importFiles()`) valide `UPLOAD_ERR_OK`, une taille max de `1048576` octets
(1 Mo, ligne 150), `is_uploaded_file()` (ligne 151, protection contre
l'injection de chemin via un faux upload), délègue le contenu à
`CbStatsTitleSetManagerService::validateImportFile()` (ligne 155) — écran
protégé par `core.manage` (§1.2).

**Zone inconnue** : aucun test unitaire dédié n'a été trouvé référençant
directement `DEFAULT_ALLOWED_UPLOAD_EXTENSIONS`, `hasExecutableExtension()`
ou `is_internal_path()` (recherche dans `admin/tests`) — ces mécanismes de
durcissement de l'upload ne semblent pas couverts par une garde de
non-régression automatisée dédiée, contrairement aux motifs SQL (§6).

---

## 8. Accès direct aux fichiers PHP

**Fait observé — garde `_JEXEC`** : recherche exhaustive de tous les
fichiers `.php` sous `admin/src`, `site/src`, `plugins`, `admin/tmpl`,
`site/tmpl`, `admin/sql` : un seul fichier sans occurrence de `_JEXEC`
(`site/tmpl/latest/latest.php`), qui est un fichier **vide** (0 octet) —
pas une absence de garde exploitable. Tous les autres fichiers PHP du
périmètre analysé portent `\defined('_JEXEC') or die(...)` (formulations
variables : `die`, `die('Restricted access')`,
`die('Direct Access to this location is not allowed.')`).

**Fait observé — exception volontaire et auto-documentée : le générateur de
captcha** : `media/images/securimage_show.php:1-25` est un script PHP
**directement accessible par le web** (appelé en `<img src="...">` depuis
le HTML du formulaire, `admin/src/Service/TemplateRenderService.php:1581`),
et le définit lui-même explicitement :
```php
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
```
puis rétablit un environnement Joomla minimal
(`require_once dirname(__FILE__) . '/../../../../includes/app.php';`,
ligne 19 ; `Factory::getApplication('administrator')`, ligne 24) avant
d'inclure la bibliothèque tierce `securimage.php`
(`media/images/securimage/securimage.php`) et de générer l'image. C'est un
comportement attendu pour un générateur de CAPTCHA (doit être atteignable
en dehors du routeur Joomla habituel), pas une faille — mais il constitue,
avec `PreviewLinkHelper` (§1.3.2), l'une des rares surfaces du composant qui
contournent volontairement le cycle de requête MVC standard de Joomla.

**Fait observé — `index.html` présents dans la quasi-totalité des dossiers
« classiques »** (gabarits `tmpl`, contrôleurs, modèles historiques) mais
**absents des dossiers PSR-4 `src/`** (`admin/src/Service`, `Extension`,
`Helper`, `Field`, `Rule`, `Contract`, `Dto`, ainsi que tous les
`*/src/Extension` et `*/services` des 17 plugins). **Comportement déduit** :
c'est le motif habituel de Joomla 4+ pour les répertoires autoloadés en
PSR-4 — chaque fichier PHP y porte de toute façon la garde `_JEXEC` (voir
ci-dessus), la protection contre le listage de répertoire reposant alors sur
la configuration du serveur web plutôt que sur `index.html`, contrairement à
la convention Joomla 1.5–3.

**Zone à signaler (mineure)** : trois dossiers de gabarits back-office
récents n'ont **pas** d'`index.html` alors que leurs dossiers voisins en ont
un : `admin/tmpl/titleset/`, `admin/tmpl/titlesets/`, `admin/tmpl/storagewizard/`
(à comparer à `admin/tmpl/forms/` et `admin/tmpl/about/`, qui en ont un).
Chaque fichier `default.php` de ces dossiers porte néanmoins la garde
`_JEXEC` (vérifié ci-dessus) — l'impact pratique dépend donc uniquement de
la configuration du serveur web (listage de répertoire activé ou non), pas
d'un accès direct exécutable aux scripts eux-mêmes.

---

## 9. Secrets / configuration

**Fait observé — pas de chiffrement applicatif des secrets** : aucune
occurrence de `Joomla\CMS\Crypt`, `Crypt::`, ni `openssl_encrypt` dans tout
le dépôt (recherche exhaustive). Les secrets de configuration du composant
sont stockés en clair dans la colonne `params` (JSON) de `#__extensions`,
mécanisme standard Joomla pour tout plugin — protection reposant sur les
permissions Joomla d'accès à l'écran Gérer les plugins et sur la sécurité de
la base de données, pas sur un chiffrement propre au composant.

**Fait observé — jeton PayPal en champ texte, pas mot de passe** :
`plugins/contentbuilderng_verify/paypal/paypal.xml:22-27` déclare
`business`, `token`, `test_business`, `test_token` avec `type="text"` (pas
`type="password"`) — ces valeurs apparaissent donc en clair dans le
formulaire de configuration du plugin lorsqu'il est réédité par un
administrateur ayant accès à l'écran des plugins (contrôle d'accès Joomla
standard sur `com_plugins`, pas propre à ContentBuilder NG).
`plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php:49-60`
lit ces valeurs via `Joomla\Registry\Registry` depuis les paramètres du
plugin (`PluginHelper::getPlugin(...)->params`), sans aucun traitement de
chiffrement/déchiffrement supplémentaire.

**Fait observé — secret d'application Joomla réutilisé pour les liens
signés** : le secret HMAC des liens de prévisualisation (§1.3.2) est
`$app->get('secret')`, c'est-à-dire le secret global d'application Joomla
(`configuration.php`) partagé par tout le site — pas un secret dédié au
composant. **Comportement déduit** : une rotation de ce secret (par exemple
après une fuite) invaliderait immédiatement tous les liens de
prévisualisation en circulation (effet de bord bénin) mais affecte aussi
d'autres mécanismes Joomla natifs qui s'appuient sur le même secret
(cookies, jetons — hors périmètre du composant).

**Fait observé — jeton de vérification/paiement généré sans CSPRNG** :
`admin/src/Model/VerifyModel.php:248`
(`$verification_id = md5(uniqid("", true) . mt_rand(0, mt_getrandmax()) . $user_id);`)
génère l'identifiant unique (`verification_hash`, colonne
`#__contentbuilderng_verifications.verification_hash`,
`admin/sql/install.sql`) qui sert de jeton d'accès à un flux de vérification
(confirmation d'e-mail, complétion de paiement — voir §10) via
`admin/src/Model/VerifyModel.php:148`. La construction combine `uniqid()`
(horodatage haute résolution) et `mt_rand()` (générateur pseudo-aléatoire
non cryptographique) plutôt qu'une source cryptographiquement sûre comme
`random_bytes()`/`bin2hex(random_bytes())`. **Comportement déduit** : la
prévisibilité pratique de ce jeton dépend de la difficulté à deviner à la
fois l'horodatage précis de génération et la valeur `mt_rand()` associée —
non nulle en théorie pour un `mt_rand()` non réensemencé de façon
imprévisible, mais ce point n'a pas été creusé plus avant (pas d'analyse de
l'entropie effective de l'implémentation `mt_rand()` de l'environnement
cible) ; il est documenté ici comme fait de code, sans estimation de
gravité.

**Fait observé — jeton de téléchargement de fichier déterministe** :
`plugins/content/contentbuilderng_download/src/Extension/ContentbuilderngDownload.php:437, 512`
utilise `sha1($field . $the_value)` comme identifiant du lien de
téléchargement — déterministe (même champ + même chemin de fichier
produisent toujours le même identifiant), mais ce n'est **pas** le mécanisme
de contrôle d'accès : l'autorisation réelle (`authorizeFe('view')` /
`authorize('view')`, lignes 315, 324, via `PermissionService`) est vérifiée
séparément avant que le lien ne soit même généré ou honoré (lignes 310-333).
**Comportement déduit** : la prévisibilité du hachage n'ouvre pas d'accès
supplémentaire par elle-même, tant que le contrôle `PermissionService`
en amont reste correctement appliqué à chaque requête (ce qui est le cas
observé : le contrôle est refait à chaque appel, pas mis en cache côté
client).

**Fait observé — aucun identifiant/mot de passe en dur trouvé** : recherche
de motifs courants (`password.*=.*['"]`, clés API génériques) dans
`admin/src`, `site/src`, `plugins` sans résultat significatif au-delà des
champs de configuration déclarés ci-dessus (qui sont des emplacements de
saisie, pas des valeurs codées en dur).

---

## 10. Appels externes (plugin PayPal)

**Fait observé — deux flux d'appel sortant vers PayPal**,
`plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php` :

1. **Redirection du visiteur vers PayPal** (`onForward()`, lignes 126-169) :
   génère un formulaire HTML auto-soumis vers
   `https://www.paypal.com/cgi-bin/webscr` (ou
   `https://www.sandbox.paypal.com/...` en mode test, ligne 56) avec
   `cmd=_xclick` — ce n'est pas un appel serveur-à-serveur, mais une
   redirection du navigateur du visiteur portant les paramètres de paiement
   (`business`, `item_name`, `amount`, `tax`, etc., lignes 140-159), y
   compris `notify_url` pointant vers l'URL de retour du site avec
   `&paypal_ipn=true` si l'IPN est activée (ligne 150).

2. **Vérification serveur-à-serveur du paiement**, deux variantes :
   - `onVerify()` / PDT (« Payment Data Transfer »), lignes 178-260 :
     requête `cmd=_notify-synch` vers `/cgi-bin/webscr` avec le jeton `tx`
     fourni par PayPal au retour et le `token` PDT configuré (lignes
     190-200).
   - `verifyIpn()` (IPN, « Instant Payment Notification »), lignes 268-356 :
     renvoie à PayPal (`cmd=_notify-validate`) l'intégralité des paramètres
     reçus dans la notification (`$_REQUEST`, ligne 285-290) pour
     confirmation, motif standard de validation IPN.

**Fait observé — validation de la réponse** : dans les deux cas, la réponse
est jugée valide seulement si la première ligne vaut littéralement
`SUCCESS` (PDT, ligne 239) ou `VERIFIED` (IPN, ligne 328), **et** si les
champs `mc_gross`/`mc_currency` renvoyés par PayPal correspondent exactement
au montant/devise attendus par le composant (lignes 247, 333 :
`$keyarray['mc_gross'] != (floatval($this->amount) + floatval($this->tax)) || $keyarray['mc_currency'] != strtoupper($this->curreny)` —
comparaison faible `!=`, pas `!==`, mais sur des valeurs numériques/chaînes
normalisées). Pas de vérification de signature cryptographique au-delà de
cet aller-retour serveur-à-serveur (c'est le mécanisme PDT/IPN historique de
PayPal lui-même, qui ne repose pas sur une signature mais sur une
revalidation active de chaque champ auprès de PayPal).

**Fait observé — désactivation de la vérification TLS du pair** :
`curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false)` est présent dans les deux
méthodes d'appel sortant (`onVerify()` ligne 209, `verifyIpn()` ligne 302).
La connexion reste chiffrée (URL `https://`), mais le certificat du serveur
distant (PayPal) n'est pas validé — code vulnérable en théorie à une
interception homme-du-milieu sur le chemin réseau du serveur vers PayPal,
ce qui compromettrait la validation du paiement elle-même. Aucun
commentaire dans le code n'explique ce choix.

**Fait observé — repli `fsockopen` sur le port 80** : si `curl_init` n'est
pas disponible, les deux méthodes retombent sur
`fsockopen($paypal, 80, $errno, $errstr, 30)` (lignes 221, 312) où `$paypal`
vaut la chaîne `'https://www.paypal.com'` (avec le schéma inclus) — c'est-à-
dire un appel `fsockopen()` avec un nom d'hôte contenant `https://` sur le
port `80` (HTTP en clair), ce qui n'établit pas la connexion attendue avec
l'API PHP standard (`fsockopen()` attend un nom d'hôte nu, ou un préfixe de
transport `ssl://`/`tls://`, pas un schéma `https://` complet). **Zone
inconnue** : ce chemin de repli n'a pas été testé à l'exécution dans le
cadre de cette analyse (pas d'environnement PHP exécutable disponible ici) ;
il est documenté tel quel, sans certitude sur son comportement runtime
exact (échec silencieux probable plutôt que dégradation vers du HTTP en
clair, étant donné la construction du nom d'hôte, mais ceci reste une
déduction et non une observation d'exécution).

**Fait observé — pas d'appel externe dans le plugin « passthrough »** :
`plugins/contentbuilderng_verify/passthrough/src/Extension/Passthrough.php`
n'effectue aucun appel réseau — c'est un plugin de vérification vide
(commentaire du manifeste, `plugins/contentbuilderng_verify/passthrough/passthrough.xml:16-18` :
« utile pour forcer une vue requise ou désactiver l'inscription Joomla au
profit de l'inscription ContentBuilder »), hors périmètre pour ce point.

---

## 11. Synthèse des zones à signaler

Liste consolidée des points relevés ci-dessus qui méritent une attention
particulière (investigation, décision produit, ou couverture de test),
sans jugement de gravité ni action corrective proposée ici :

1. **§1.1** — `contentbuilderng.manage` / `contentbuilderng.admin`
   (`admin/access.xml:54-64`) déclarées et documentées, mais aucun
   `->authorise()` ne les invoque dans le code lu.
2. **§1.3** — `ExportController`/`ExportModel` (export Excel front,
   `view=export`) ne rejoue pas le contrôle `listaccess` de
   `PermissionService` que `ListController::display()` applique pour la
   même donnée en liste (`ListController.php:439-445` vs absence
   équivalente dans `ExportModel.php`).
3. **§5** — Champs de formulaire marqués `allow_html = true` : la valeur
   soumise est réaffichée sans `htmlspecialchars()` (seule la syntaxe de
   jetons du composant est protégée par `cleanString()`,
   `admin/src/Service/TextUtilityService.php:25-31`) — comportement
   probablement intentionnel, à valider contre la matrice ACL réelle de
   qui peut remplir/consulter un tel champ.
4. **§6** — `admin/src/types/com_breezingformsng.php:829, 831, 843, 845,
   1022, 1105` : interpolation SQL directe de `$element['name']`/`$name`
   sans `$db->quote()`, hors périmètre de
   `admin/tests/Unit/Security/SqlHardeningTest.php`.
5. **§7** — Pas de test unitaire dédié à `DEFAULT_ALLOWED_UPLOAD_EXTENSIONS`,
   `hasExecutableExtension()` ou `is_internal_path()`.
6. **§8** — `admin/tmpl/titleset/`, `admin/tmpl/titlesets/`,
   `admin/tmpl/storagewizard/` sans `index.html`, à la différence de leurs
   dossiers voisins (impact limité par la garde `_JEXEC` déjà présente dans
   chaque fichier).
7. **§9** — `verification_hash` (`admin/src/Model/VerifyModel.php:248`)
   généré via `md5(uniqid() . mt_rand() . $user_id)` plutôt qu'un CSPRNG.
8. **§10** — `CURLOPT_SSL_VERIFYPEER => false` dans les deux appels
   sortants du plugin PayPal (`Paypal.php:209, 302`) ; repli `fsockopen`
   construit avec un nom d'hôte incluant le schéma `https://` sur le port
   80 (`Paypal.php:221, 312`), comportement runtime non vérifié dans cette
   analyse.
