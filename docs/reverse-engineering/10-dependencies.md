# 10. Dépendances et intégrations externes

> Document de rétro-analyse (reverse engineering). Aucune modification de code
> n'a été effectuée pour produire ce document. Le code est la source de
> vérité ; chaque affirmation est classée **Fait observé** (avec fichier et,
> si utile, ligne), **Comportement déduit**, **Hypothèse** ou **Zone
> inconnue**.

## Sommaire

1. [Dépendances Composer](#1-dépendances-composer)
2. [APIs Joomla 6 core](#2-apis-joomla-6-core)
3. [Intégrations externes — PayPal en détail](#3-intégrations-externes--paypal-en-détail)
4. [Bibliothèques JavaScript](#4-bibliothèques-javascript)
5. [Autres dépendances et extensions Joomla](#5-autres-dépendances-et-extensions-joomla)
6. [Synthèse](#6-synthèse)

---

## 1. Dépendances Composer

**Fait observé** — `admin/composer.json` déclare :

```json
{
  "require": {
    "php": ">=8.3",
    "bgli100/securimage": "^4.0",
    "phpoffice/phpspreadsheet": "^5.8"
  },
  "require-dev": {
    "phpstan/phpstan": "^2.2.11",
    "phpunit/phpunit": "^12.5.34",
    "squizlabs/php_codesniffer": "^4.0.4"
  }
}
```

Il n'existe pas de `composer.json` à la racine du dépôt, ni dans `site/`. Seul
`admin/composer.json` déclare des dépendances PHP. Le paquet Composer publié
côté administrateur est installé dans
`administrator/components/com_contentbuilderng/vendor/`.

### 1.1 `bgli100/securimage` (^4.0) — captcha graphique

**Fait observé** — Chargement défensif à trois niveaux, dupliqué dans deux
fichiers avec une logique quasiment identique :

- `admin/src/Model/VerifyModel.php` n'y fait pas directement appel, c'est
  `site/src/Model/EditModel.php:915-930` qui charge la classe au moment de la
  validation d'un formulaire comportant un champ captcha :
  ```php
  if (!class_exists('Securimage')) {
      $vendorSecurimage = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/bgli100/securimage/securimage.php';
      $composerAutoload  = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/autoload.php';
      if (is_file($composerAutoload)) {
          require_once $composerAutoload;
      }
      if (!class_exists('Securimage') && is_file($vendorSecurimage)) {
          require_once $vendorSecurimage;
      }
  }
  $securimage = new \Securimage();
  $cap_value = $this->app->getInput()->post->get('cb_' . $the_captcha_field['reference_id'], null, 'raw');
  if ($securimage->check($cap_value) == false) { /* … message d'erreur … */ }
  ```
- Le même chargement défensif (autoload Composer puis repli sur le chemin
  direct du paquet vendored) est répété dans
  `admin/src/Model/StorageModel.php:13-31` — mais là, ce n'est **pas** pour
  le captcha : c'est un garde-fou générique en tête de fichier qui échoue
  bruyamment (`RuntimeException`) si `Securimage` n'est disponible ni via
  Composer ni via le chemin vendored. Ce bloc semble être un reliquat/garde
  partagé plutôt qu'un usage réel de Securimage dans `StorageModel`
  (`StorageModel` n'appelle `new Securimage()` nulle part ailleurs dans le
  fichier).
- `admin/scripts/prune-securimage-audio.php` : script Composer
  (`post-install-cmd` / `post-update-cmd`, voir `admin/composer.json`) qui
  supprime le répertoire `vendor/bgli100/securimage/audio` après
  installation — purge des fichiers audio du captcha (probablement pour
  réduire la taille du paquet, l'audio-captcha n'étant pas utilisé par le
  composant).

**Fait observé** — Rendu de l'image captcha : deux scripts CGI-like
autonomes, **hors** de la classe Composer, qui incluent une **copie
vendored séparée et minimale** de la bibliothèque Securimage :
- `media/images/securimage_show.php` et `media/images/securimage/securimage_show.php`
  incluent `.../images/securimage/securimage.php` (chemin `media/images/`),
  instancient `new securimage()` (classe globale, minuscule) et appellent
  `$img->show(...)`.
- `media/images/securimage/securimage.php` (872 octets) n'est **pas** la
  bibliothèque Securimage complète : c'est un shim qui recharge l'autoload
  Composer puis, à défaut, le fichier vendored
  `vendor/bgli100/securimage/securimage.php`, exactement comme le shim de
  `StorageModel.php`.
- Le répertoire `media/images/securimage/` contient aussi les ressources
  graphiques nécessaires à l'affichage (police `AHGBold.ttf`, images de fond
  `backgrounds/`, dictionnaire de mots `words/`), ce qui confirme que ces
  scripts sont le point d'entrée HTTP public du captcha (appelé en `<img
  src="…securimage_show.php">` depuis les formulaires front-end), séparé du
  flux de validation applicatif (`EditModel.php`).

**Comportement déduit** — Le captcha ContentBuilder NG est entièrement
géré par `bgli100/securimage` (fork/paquet Composer de la bibliothèque
historique Securimage), avec :
- un point d'entrée de **génération d'image** accessible en HTTP direct
  (`media/images/securimage_show.php`), indépendant du cycle MVC Joomla ;
- un point de **vérification** intégré au modèle `EditModel` lors de la
  soumission d'un formulaire ContentBuilder NG comportant un champ de type
  captcha.

**Hypothèse** — La duplication du chemin `media/images/securimage/` (une
copie légère avec son propre `securimage.php`/`securimage_show.php`) en plus
du vendor Composer laisse penser qu'il s'agit d'un héritage de l'ancienne
architecture Joomla (avant migration Composer), conservé pour compatibilité
d'URL publique (`media/images/...` étant servable directement sans passer
par le routeur Joomla), plutôt qu'une seconde dépendance distincte.

**Zone inconnue** — Aucun `use Securimage\...` (namespace PSR-4) n'a été
trouvé dans le code : toutes les références utilisent la classe globale
`\Securimage` (API historique non-namespacée). Le nom exact du paquet
(`bgli100/securimage`) suggère un fork maintenu par un tiers (GitHub user
`bgli100`) de la bibliothique historique `dapphp/securimage`, mais le
`composer.lock`/vendor n'étant pas présent dans le dépôt analysé, l'origine
précise et le contenu exact du paquet publié n'ont pas pu être vérifiés
depuis le code seul.

### 1.2 `phpoffice/phpspreadsheet` (^5.8) — export/import Excel

**Fait observé** — Deux usages distincts et complémentaires :

- **Export XLSX (front-end)** — `site/tmpl/export/default.php:21-34` importe
  `PhpOffice\PhpSpreadsheet\Cell\Coordinate`, `Cell\DataType`,
  `Shared\Date as SpreadsheetDate`, `Style\Alignment`, `Style\Fill`,
  `Writer\Xlsx` et `Spreadsheet`. Le chargement de l'autoload Composer se
  fait via `CB\Component\Contentbuilderng\Administrator\Helper\VendorHelper::load()`
  (`site/tmpl/export/default.php:30-32`), avant les `use` PhpSpreadsheet
  proprement dits. Cette vue construit une feuille de calcul
  (`new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet(...)`, mise en page
  A4 via `PageSetup::PAPERSIZE_A4`, remplissage de cellules, styles
  d'alignement/couleur) à partir des données d'un formulaire/liste
  ContentBuilder NG, pour l'export "Excel" côté visiteur.
- **Import CSV/XLS/XLSX → CSV (back-office)** —
  `admin/src/Model/StorageModel.php` :
  - `convertSpreadsheetFileToCsv()` (ligne ~1480) : charge un fichier
    `.xls`/`.xlsx` uploadé via `\PhpOffice\PhpSpreadsheet\IOFactory::load()`
    puis le réécrit en CSV avec `\PhpOffice\PhpSpreadsheet\Writer\Csv` (ligne
    1491-1500), afin de réutiliser le même pipeline d'import CSV pour tous
    les formats tableur.
  - `extractHeaderColumnsFromUpload()` (ligne ~1519) : pour les fichiers
    `.xls`/`.xlsx` (le CSV est traité nativement avec `fgetcsv`), charge la
    feuille active et lit la première ligne via `rangeToArray()` (ligne
    1559-1565) pour proposer le mapping des colonnes d'import.

**Comportement déduit** — PhpSpreadsheet sert à la fois de moteur d'**export**
(génération de classeurs `.xlsx` mis en forme pour les vues "export" du
front-end) et d'outil d'**ingestion** (normalisation de fichiers tableur
uploadés en CSV interne, pour l'import de données dans les tables de
stockage ContentBuilder NG). Dans les deux cas, le chargement est paresseux
(`VendorHelper::load()`), pour éviter de charger l'autoload Composer sur
chaque requête Joomla.

### 1.3 `VendorHelper` — point d'entrée commun de l'autoload Composer

**Fait observé** — `admin/src/Helper/VendorHelper.php` est une classe
utilitaire statique unique (`final class VendorHelper`) qui charge
paresseusement `administrator/components/com_contentbuilderng/vendor/autoload.php`
et lève une `\RuntimeException` explicite si ce fichier est absent. Elle est
utilisée par `StorageModel.php` (import tableur) et par
`site/tmpl/export/default.php` (export XLSX) — c'est le point d'entrée
Composer canonique du composant, alors que le captcha Securimage a son
propre repli redondant (voir §1.1) plutôt que de systématiquement passer par
`VendorHelper`.

### 1.4 Dépendances de développement

**Fait observé** — `phpstan/phpstan` (^2.2.11), `phpunit/phpunit`
(^12.5.34) et `squizlabs/php_codesniffer` (^4.0.4) sont déclarées en
`require-dev`. Configurations présentes à la racine et sous `admin/` :
`phpstan.neon.dist`, `phpcs.xml.dist`, `admin/phpunit.xml.dist`. Les scripts
Composer `lint:php` (`phpcs`) et `lint:php:fix` (`phpcbf`) sont définis dans
`admin/composer.json`. `admin/tests/` contient 120 fichiers PHP de tests
unitaires (dossier `Unit/`, `bootstrap.php`).

**Fait observé** — `.github/workflows/build-package.yml` et
`.github/workflows/codeql.yml` existent, confirmant un pipeline CI GitHub
Actions (build de paquet + analyse CodeQL), cohérent avec l'usage de
`squizlabs/php_codesniffer`, `phpstan` et `phpunit` en développement.

### 1.5 `package.json` racine — outillage CSS uniquement

**Fait observé** — `package.json` (racine) :
```json
{
  "scripts": { "lint:css": "stylelint \"media/css/**/*.css\" \"plugins/**/*.css\"" },
  "devDependencies": { "stylelint": "17.14.1", "stylelint-config-standard": "40.0.0" }
}
```
Aucune dépendance de build (pas de bundler, pas de transpileur). Ce fichier
ne sert qu'au lint CSS en CI/local ; il ne participe à aucun build du
JavaScript ou du CSS livré (les fichiers de `media/js/` et `media/css/` sont
livrés tels quels, non minifiés/transpilés).

---

## 2. APIs Joomla 6 core

Fréquence d'usage (`use Joomla\...`) mesurée par grep sur l'ensemble du
dépôt (composant + plugins). Cette section liste les APIs qui structurent
réellement l'architecture, pas l'exhaustivité des `use`.

### 2.1 Cycle de vie de l'extension (Extension/DI/Dispatcher)

**Fait observé** — `admin/services/provider.php` déclare le
`ServiceProviderInterface` du composant et enregistre, via le conteneur
Joomla (`Joomla\DI\Container`) :
- `Joomla\CMS\Extension\Service\Provider\MVCFactory` (namespace
  `\CB\Component\Contentbuilderng`),
- `Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory`,
- `Joomla\CMS\Extension\Service\Provider\RouterFactory`,

puis une longue série de services applicatifs propres au composant
(`DatatableService`, `StorageFieldService`, `PathService`,
`FormSupportService`, `PermissionService`, `ArticleService`,
`MenuService`, etc.) injectés dans le conteneur. C'est le mécanisme central
Joomla 6 (namespaced extensions, PSR-4, DI container) qui structure
**toute** l'architecture MVC du composant : `Joomla\CMS\MVC\Factory\MVCFactoryInterface`
(21 usages), `Joomla\CMS\Dispatcher\ComponentDispatcher` (2),
`Joomla\CMS\Extension\PluginInterface` (17, un par plugin) suivent le même
schéma dans chaque `services/provider.php` de plugin.

**Comportement déduit** — Le composant et chacun de ses 17 plugins/paquets
(`contentbuilderng_verify/paypal`, `contentbuilderng_themes/*`, etc.)
suivent strictement le pattern Joomla 6 "extension as a service" : un
`services/provider.php` retournant une classe anonyme implémentant
`ServiceProviderInterface`, un `Extension` implémentant `PluginInterface` /
`SubscriberInterface`, aucune classe procédurale historique
(`plgXxxYyy`) — conforme à AGENTS.md ("Use native Joomla 6 admin patterns").

### 2.2 Base de données (`Joomla\Database`)

**Fait observé** — `Joomla\Database\DatabaseInterface` est le type le plus
utilisé après `Text` (94 occurrences), toujours obtenu via injection ou via
`RuntimeContextHelper::getDatabase()` (jamais `Factory::getDbo()` en usage
direct dans les classes vues, cf. `CategoriesField.php:29`). `Joomla\Database\ParameterType`
(16) et `Joomla\Database\QueryInterface`/`DatabaseQuery` sont utilisés pour
des requêtes préparées/paramétrées via le Query Builder Joomla — cohérent
avec la contrainte AGENTS.md "Raw SQL … outside the Joomla Query Builder".

**Comportement déduit** — L'accès base de données est entièrement
médié par l'API `Joomla\Database` (Query Builder + `ParameterType` pour le
binding), jamais par une couche PDO/mysqli directe.

### 2.3 MVC / Table / Form (structure des vues et formulaires)

**Fait observé** — `Joomla\CMS\MVC\Model\AdminModel` / `ListModel` /
`BaseDatabaseModel`, `Joomla\CMS\MVC\Controller\BaseController` /
`AdminController`, `Joomla\CMS\Table\Table` (6), `Joomla\CMS\Form\Form` (6)
et `Joomla\CMS\Form\FormField` (7, base de tous les champs personnalisés
sous `site/src/Field/*Field.php` et `admin/src/Field/*Field.php` — ex.
`CategoriesField`, `MenuformsField`, `ListlimitField`) forment le socle MVC
classique Joomla. Les modèles de gestion des "Storage" (tables dynamiques
générées par l'utilisateur, cœur métier de ContentBuilder NG) étendent
`AdminModel`/`ListModel` plutôt que de réinventer une couche CRUD.

### 2.4 Evènements (`Joomla\Event`)

**Fait observé** — `Joomla\Event\SubscriberInterface` (17) et
`Joomla\Event\DispatcherInterface` (17) : chaque plugin implémente
`getSubscribedEvents()`. Deux familles d'évènements structurantes :
- **`onContentPrepare`** (core, hérité de com_content) : souscrit par
  `contentbuilderng_cblist`, `contentbuilderng_cbstats`,
  `contentbuilderng_download`, `contentbuilderng_image_scale`,
  `contentbuilderng_permission_observer`, `contentbuilderng_rating` et
  `contentbuilderng_verify` (groupe `content`) — c'est le mécanisme par
  lequel les tags `{cblist …}`, `{cbstats …}`, etc. insérés dans un article
  Joomla (ou tout contenu passant par le pipeline de préparation de
  contenu) sont détectés et remplacés.
- **Evènements propres au composant**, définis et déclenchés par
  ContentBuilder NG lui-même pour ses propres points d'extension : `onSetup`,
  `onForward`, `onVerify`, `onViewport` (groupe de plugins
  `contentbuilderng_verify`, ex. PayPal — voir §3), `onBeforeAction`/
  `onAfterAction`/`onAfterArticleCreation` (groupe `contentbuilderng_listaction`,
  ex. `trash`/`untrash`), `onBeforeSubmit`/`onAfterSubmit` (groupe
  `contentbuilderng_submit`), et `onContentTemplateJavascript`/
  `onContentTemplateCss`/`onListViewJavascript`/`onListViewCss`/
  `onContentTemplateSample`/`onEditableTemplateSample`/`onEditableTemplateJavascript`/
  `onEditableTemplateCss` (groupe `contentbuilderng_themes`, ex. `blank`,
  `dark`, `khepri`, `thoth`).
- **`onAfterDispatch`/`onAfterInitialise`/`onAfterRoute`/`onBeforeRender`**
  souscrits par le plugin système `plugins/system/contentbuilderng_system`.

**Comportement déduit** — ContentBuilder NG définit son propre système de
plugins internes (groupes `contentbuilderng_verify`, `contentbuilderng_themes`,
`contentbuilderng_listaction`, `contentbuilderng_submit`) construit
au-dessus de l'API standard `Joomla\Event` : ce ne sont pas des évènements
Joomla core, mais des points d'extension propres au composant, exposés via
le même mécanisme `getSubscribedEvents()`/`PluginHelper::isEnabled()` que
les plugins Joomla natifs — une architecture de plugins imbriquée dans le
système de plugins Joomla.

### 2.5 Rendu, assets et éditeur

**Fait observé** — `Joomla\CMS\HTML\HTMLHelper` (38), `Joomla\CMS\Layout\LayoutHelper`
(9), `Joomla\CMS\WebAsset\WebAssetManager` (3 imports directs, mais `$wa =
$document->getWebAssetManager()` est utilisé dans la quasi-totalité des vues,
voir §4) et `Joomla\CMS\Editor\Editor` (3 : `TemplateRenderService.php`,
`admin/layouts/form/prepare_editor.php`, `admin/tmpl/elementoptions/default.php`)
structurent le rendu. `Editor::getInstance($app->get('editor'))` utilise
l'éditeur WYSIWYG configuré globalement dans Joomla (ex. TinyMCE), tandis que
`Editor::getInstance('codemirror')` force explicitement l'éditeur de code
Joomla (`plg_editors_codemirror`, fourni en standard avec Joomla) pour les
champs de type code (PHP/JS/CSS des templates ContentBuilder NG) — dépendance
logicielle sur ce plugin d'éditeur étant activé.

### 2.6 Autres APIs structurantes

**Fait observé** — `Joomla\CMS\Router\Route` (59, génération d'URL SEF),
`Joomla\CMS\Uri\Uri` (36), `Joomla\CMS\Plugin\PluginHelper` (34, découverte
et activation des plugins internes ContentBuilder NG cités en §2.4),
`Joomla\CMS\Date\Date` (29), `Joomla\Filesystem\File`/`Folder` (16+13,
opérations sur fichiers uploadés/exportés — API moderne `joomla/filesystem`,
pas de `JFile`/`JFolder` legacy), `Joomla\CMS\Log\Log` (17, journalisation),
`Joomla\CMS\Toolbar\ToolbarHelper`/`Toolbar` (barre d'outils admin),
`Joomla\Registry\Registry` (13, params XML), `Joomla\CMS\Component\ComponentHelper`
(10), `Joomla\CMS\Session\Session`, `Joomla\CMS\Access\Access`/`NotAllowed`
(ACL).

**Fait observé** — `Joomla\CMS\Mail\MailerFactoryInterface` est utilisé
dans `admin/src/Model/VerifyModel.php` et `site/src/Model/EditModel.php`
(hors tests) — envoi d'e-mails d'activation de compte (voir §5.2) et
probablement de notifications de soumission de formulaire.

**Zone inconnue** — Aucun usage direct de `Joomla\CMS\Mail\Mail` (classe
concrète) trouvé hors `MailerFactoryInterface` ; le detail des templates
d'e-mail envoyés par ContentBuilder NG lui-même (hors activation com_users)
n'a pas été examiné dans ce document (hors périmètre "dépendances").

### 2.7 UCM, Tags, Workflow, Associations

**Fait observé** — Aucune occurrence de `Joomla\CMS\Tag`, `Joomla\CMS\Workflow`,
`Joomla\CMS\UCM`, ni `Joomla\CMS\Association` n'a été trouvée dans
l'ensemble du code PHP du dépôt (composant + plugins).

**Comportement déduit** — ContentBuilder NG n'intègre **pas** le système de
tags Joomla, ni le workflow de publication (com_workflow / états ACL de
workflow), ni les associations multilingues, ni l'UCM (Unified Content
Model). Les enregistrements créés dans les tables de "storage" dynamiques du
composant restent en dehors de ces sous-systèmes Joomla natifs.

---

## 3. Intégrations externes — PayPal en détail

**Fait observé** — `plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php`
implémente un plugin du groupe interne `contentbuilderng_verify`
(`getSubscribedEvents()` : `onViewport`, `onSetup`, `onForward`, `onVerify`
— voir §2.4), avec deux mécanismes de vérification de paiement PayPal,
**tous deux basés sur l'ancienne API "Website Payments Standard" / PDT /
IPN de PayPal**, et non sur l'API REST moderne PayPal Checkout / Orders v2.

### 3.1 Protocole : bouton de paiement classique (`_xclick`)

**Fait observé** — `onForward()` (lignes 126-169) génère un formulaire HTML
auto-soumis vers `https://www.paypal.com/cgi-bin/webscr` (ou
`https://www.sandbox.paypal.com/cgi-bin/webscr` en mode test, ligne 56), en
`POST`, avec `cmd=_xclick` et les champs classiques du "Website Payments
Standard" historique : `business`, `item_name`, `item_number`, `amount`,
`tax`, `no_shipping`, `no_note`, `return` (URL de retour), `cancel_return`,
`rm` (return method = 2, POST), `lc` (locale), `currency_code`, et, si l'IPN
est activé, `notify_url` (l'URL de retour suffixée de `&paypal_ipn=true`) et
`test_ipn=1` en mode sandbox.

**Comportement déduit** — Cette redirection `cgi-bin/webscr` correspond à
l'ancienne interface "Website Payments Standard" de PayPal, **officiellement
retirée/dépréciée par PayPal** depuis plusieurs années au profit de l'API
REST Checkout. Le code fonctionne toujours tant que PayPal maintient une
rétrocompatibilité de fait sur cette route historique, mais ce protocole
n'est plus la voie recommandée par PayPal.

### 3.2 Vérification synchrone (PDT-like via `_notify-synch`)

**Fait observé** — `onVerify()` (lignes 178-260), en l'absence d'IPN actif :
- lit le paramètre GET `tx` (transaction id) de la requête de retour,
  construit `cmd=_notify-synch&tx=<tx>&at=<auth_token>` (le `token` étant le
  jeton d'authentification PDT saisi dans la config du plugin),
- envoie ce corps en `POST` vers `https://www.paypal.com/cgi-bin/webscr`
  (ou sandbox) — via `curl_init()`/`curl_exec()` si disponible
  (`CURLOPT_SSL_VERIFYPEER` **désactivé**, ligne 209), sinon via
  `fsockopen($paypal, 80, …)` (**port 80, HTTP non chiffré**, lignes
  220-234) en repli,
- parse la réponse texte ligne par ligne (`SUCCESS`/`FAIL` en première
  ligne, puis paires `clé=valeur` urlencodées),
- si `SUCCESS`, compare `mc_gross` (montant brut) et `mc_currency` aux
  valeurs attendues (`amount + tax`, `currency-code`) avant de retourner les
  données de paiement (`tx`, `is_test`, `data`).

**Fait observé — problème de sécurité notable** :
`CURLOPT_SSL_VERIFYPEER => false` (ligne 209) désactive la vérification du
certificat TLS du serveur PayPal lors de l'appel curl, et le chemin de repli
`fsockopen` (ligne 221) se connecte en clair sur le port 80. C'est un défaut
de sécurité du code existant, à signaler indépendamment de ce document
(reverse engineering uniquement, aucune correction appliquée ici).

### 3.3 Vérification asynchrone (IPN via `_notify-validate`)

**Fait observé** — `verifyIpn()` (lignes 268-356), appelée depuis
`onVerify()` quand `$this->ipn` est vrai et que la requête de retour porte
`paypal_ipn=true` (paramètre ajouté par `onForward()` à `notify_url`) :
- reconstruit le corps `cmd=_notify-validate` en **renvoyant tel quel** à
  PayPal l'intégralité de `$_REQUEST` reçu (ré-urlencodé après
  `stripslashes`), conformément au protocole IPN classique (« echo-back »),
- POST vers le même endpoint `cgi-bin/webscr` (curl, avec la même
  désactivation `CURLOPT_SSL_VERIFYPEER`, ou repli `fsockopen` port 80),
- attend la réponse `VERIFIED` en première ligne, revalide `mc_gross`/`mc_currency`,
- retourne un tableau contenant `exit => true` et `header => 'Status: 200
  OK'` — signal explicite à l'appelant (le modèle `EditModel`/`VerifyModel`
  côté ContentBuilder NG, hors périmètre PayPal) de répondre HTTP 200 à
  PayPal sans autre contenu, comportement attendu d'un endpoint IPN.

### 3.4 Authentification et données envoyées/reçues

**Fait observé** — Paramètres de configuration du plugin (champs XML,
`paypal.xml:24-35`), stockés dans les `params` du plugin Joomla (table
`#__extensions`, non chiffrés) :

| Champ            | Rôle                                                      |
|-------------------|------------------------------------------------------------|
| `business`         | Adresse e-mail ou identifiant marchand PayPal (production) |
| `token`             | Jeton d'authentification PDT (production)                  |
| `test`              | Bascule Sandbox (0/1)                                       |
| `test_business`     | Adresse marchand pour le mode Sandbox                       |
| `test_token`        | Jeton PDT pour le mode Sandbox                               |

Aucune clé API REST (Client ID / Secret OAuth2) n'est utilisée : cette
intégration ne s'appuie pas sur l'API REST moderne de PayPal (qui utiliserait
OAuth2 + `api-m.paypal.com` / `api-m.sandbox.paypal.com`).

**Données envoyées à PayPal** : identité marchand (`business`), détails de
l'article/montant/devise/taxe, jeton d'auth (`token`), URLs de retour et
d'annulation, locale.
**Données reçues de PayPal** : en retour synchrone, paires clé/valeur dont
`mc_gross`, `mc_currency` (contrôlées) ; en IPN, l'intégralité du POST PayPal
(`txn_id`, `mc_gross`, `mc_currency`, etc., non typé/validé au-delà des deux
champs contrôlés).

**Hypothèse** — Le fait que `verifyIpn()` renvoie **l'intégralité** de
`$_REQUEST` à PayPal pour validation (plutôt qu'un jeu de champs blancs)
est conforme au protocole IPN historique de PayPal (qui exige le renvoi
identique des données reçues), mais expose le composant à relayer tout
paramètre injecté par un tiers dans la requête si celle-ci n'est pas
authentifiée en amont par ailleurs.

### 3.5 Plugin `passthrough`

**Fait observé** — `plugins/contentbuilderng_verify/passthrough/src/Extension/Passthrough.php`
implémente le même contrat d'évènements (`onViewport`, `onSetup`,
`onForward`, `onVerify`) sans appel réseau : plugin "vide" utilisé pour
forcer une vue requise ou remplacer le système d'enregistrement Joomla par
celui de ContentBuilder NG sans paiement (cf. description XML). Il ne
constitue pas une intégration externe, mais confirme que le groupe de
plugins `contentbuilderng_verify` est un point d'extension générique dont
PayPal n'est qu'une implémentation parmi d'autres possibles.

---

## 4. Bibliothèques JavaScript

### 4.1 `media/js/` (10 fichiers, composant principal) — vanilla JS, aucune lib tierce

**Fait observé** — Aucun de ces fichiers n'importe ni ne référence de
bibliothèque tierce (pas de jQuery, pas de framework). Rôle probable de
chacun (une ligne) :

| Fichier | Rôle probable |
|---|---|
| `admin-about.js` | Interactions AJAX de l'onglet "About" (audit, easter egg) en admin. |
| `admin-ui.js` | Utilitaires UI transverses de l'admin (onglets, `ContentBuilderNgAdmin` namespace). |
| `contentbuilderng.js` | Utilitaires génériques partagés (ex. `is_numeric`, animation de fondu `fade`). |
| `form-audit.js` | Gestion AJAX de l'audit/réparation de formulaire (onglet Audit de l'édition de formulaire). |
| `form-edit-init.js` | Initialisation de la vue d'édition de formulaire (le plus volumineux, 3486 lignes), pilotée par `window.cbFormEditConfig`. |
| `list-init.js` | Initialisation de la vue liste front-end, pilotée par `window.cbListConfig`. |
| `list-limit-field.js` | Comportement du champ "limite de résultats" (choix de pagination). |
| `menu-list-options.js` | Options de menu Joomla pour les vues de type liste ContentBuilder NG. |
| `menu-options.js` | Options de menu Joomla générales (formulaires/valeurs par défaut). |
| `index.html` | Fichier vide de protection de répertoire (convention Joomla), pas du code applicatif. |

**Comportement déduit** — Style homogène : IIFE `'use strict'`, lecture de
configuration injectée côté serveur via `Joomla.getOptions('com_contentbuilderng.*', …)`
ou variables globales (`window.cbFormEditConfig`, `window.cbListConfig`),
traductions via `Joomla.Text._()`. Conforme à AGENTS.md ("Keep custom CSS and
JavaScript minimal", pas de build JS).

### 4.2 Bibliothèque tierce chargée via CDN — Coloris

**Fait observé** — `media/joomla.asset.json` déclare deux assets pointant
directement vers un CDN externe (jsdelivr), sans copie locale :
```json
{ "name": "com_contentbuilderng.coloris.css", "type": "style",
  "uri": "https://cdn.jsdelivr.net/npm/@melloware/coloris@0.25.0/dist/coloris.min.css" },
{ "name": "com_contentbuilderng.coloris.js", "type": "script",
  "uri": "https://cdn.jsdelivr.net/npm/@melloware/coloris@0.25.0/dist/umd/coloris.min.js",
  "dependencies": ["core"], "attributes": {"defer": true} }
```
Utilisée par `admin/src/View/Form/HtmlView.php:87-88`
(`$wa->useStyle('com_contentbuilderng.coloris.css'); $wa->useScript('com_contentbuilderng.coloris.js');`)
— sélecteur de couleur (color picker) `@melloware/coloris` v0.25.0, pour un
champ de couleur dans l'éditeur de formulaire admin.

**Comportement déduit** — C'est la **seule** ressource JS/CSS du dépôt
chargée depuis un CDN public plutôt que servie localement depuis `media/`.
Cela introduit une dépendance réseau externe (jsdelivr) pour le
back-office : si le CDN est inaccessible (réseau restreint, pare-feu), le
sélecteur de couleur ne se chargera pas.

### 4.3 Bibliothèque tierce vendored localement — Chart.js

**Fait observé** — `plugins/content/contentbuilderng_cbstats/media/js/vendor/chart.umd.min.js`
(en-tête : *"Chart.js v4.5.1 … (c) 2025 Chart.js Contributors … MIT
License"*), accompagné de `vendor/LICENSE.md`. Déclaré dans
`plugins/content/contentbuilderng_cbstats/media/joomla.asset.json` comme
asset `plg_content_contentbuilderng_cbstats.chartjs` (version `4.5.1`), dont
dépendent les scripts `cbstats-pie.js`, `cbstats-bar.js` et
`cbstats-charts.js` du même plugin.

**Comportement déduit** — Contrairement à Coloris, Chart.js est **vendored
en local** (livré dans le paquet du plugin, pas de dépendance CDN), utilisé
pour le rendu de graphiques (camembert/barres) du plugin de contenu
`contentbuilderng_cbstats` (statistiques affichées dans les articles via le
tag `{cbstats …}`, cf. `onContentPrepare`, §2.4).

### 4.4 CSS (`media/css/`, 22 fichiers)

**Fait observé** — Aucune référence CDN ni bibliothèque tierce trouvée dans
les CSS (grep `cdn.`/`googleapis`/`jsdelivr`/`cdnjs`/`unpkg` négatif sur
`*.css`). Ils sont tous déclarés en assets natifs Joomla dans
`media/joomla.asset.json` avec des dépendances internes en chaîne (ex.
`com_contentbuilderng.list` dépend de `com_contentbuilderng.frontend`
dépend de `com_contentbuilderng.cards`) — aucun bundler, chaque fichier est
servi tel quel.

---

## 5. Autres dépendances et extensions Joomla

### 5.1 Appels réseau sortants — récapitulatif

**Fait observé** — `curl_init`/`curl_exec` n'apparaît que dans
`plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php` (§3).
`fsockopen` n'apparaît également que dans ce même fichier (repli HTTP sur
port 80 des appels PayPal). Aucun usage de `Joomla\Http\HttpFactory` (ou
`Joomla\CMS\Http\HttpFactory`) n'a été trouvé nulle part dans le dépôt —
c'est-à-dire que même le seul appel HTTP sortant du composant (PayPal)
n'utilise **pas** l'API HTTP standard de Joomla, mais du cURL/`fsockopen`
brut. Aucun `file_get_contents('http...')` n'a été trouvé.

**Comportement déduit** — En dehors de l'intégration PayPal, ContentBuilder
NG n'effectue aucun appel réseau sortant applicatif (pas de SMS, pas
d'autre API tierce, pas de télémétrie détectée dans le code).

### 5.2 Dépendance sur `com_users` (core Joomla, non "tierce")

**Fait observé** — `admin/src/Model/VerifyModel.php:474-658` utilise
massivement l'API et les chaînes de langue de `com_users` (ACL
`core.create` sur `com_users`, `ComponentHelper::getParams('com_users')`,
chaînes `COM_USERS_ACTIVATION_TOKEN_NOT_FOUND`,
`COM_USERS_REGISTRATION_ACTIVATION_SAVE_FAILED`,
`COM_USERS_EMAIL_ACCOUNT_DETAILS`, `COM_USERS_EMAIL_REGISTERED_BODY[_NOPW]`,
`COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_[SUBJECT|BODY]`, etc.),
avec envoi d'e-mail via `MailerFactoryInterface` (§2.6).

**Comportement déduit** — ContentBuilder NG réimplémente/relie son propre
flux d'inscription (`act_as_registration` sur un formulaire, §1.1 pour le
lien avec le captcha) au **flux d'activation de compte standard de
com_users** (jetons d'activation, e-mails d'activation avec ou sans mot de
passe, activation par l'administrateur), plutôt que d'inventer son propre
système de comptes. `com_users` est un composant core toujours présent dans
une installation Joomla — il ne s'agit donc pas d'une extension tierce au
sens "à installer séparément", mais d'une dépendance fonctionnelle forte
vis-à-vis de ce composant core.

### 5.3 Dépendance sur `com_content` (core Joomla) — catégories et plugins de contenu

**Fait observé** — `site/src/Field/CategoriesField.php:43,67` interroge la
table partagée `#__categories` en filtrant
`a.extension = com_content` (et vérifie l'ACL `core.create` sur
`com_content.category.<id>`) pour peupler un champ de sélection de
catégorie. Par ailleurs, tout le groupe de plugins `content`
(`contentbuilderng_cblist`, `contentbuilderng_cbstats`,
`contentbuilderng_download`, `contentbuilderng_image_scale`,
`contentbuilderng_permission_observer`, `contentbuilderng_rating`,
`contentbuilderng_verify`) s'accroche à `onContentPrepare`, l'évènement du
pipeline de rendu de contenu **partagé** avec `com_content` (et tout autre
composant qui déclenche cet évènement) — voir §2.4.

**Comportement déduit** — Le composant dépend structurellement du modèle de
données `#__categories` de `com_content` (composant core toujours présent)
pour son champ "Catégorie", et son mécanisme d'intégration dans les
articles (`{cblist}`, `{cbstats}`, etc.) repose sur le pipeline de contenu
que `com_content` déclenche à l'affichage d'un article — sans que
`com_content` soit une dépendance "tierce" installable séparément
(composant core Joomla toujours disponible).

### 5.4 Dépendance sur les plugins d'éditeur Joomla (`plg_editors_*`)

**Fait observé** — Voir §2.5 : `Editor::getInstance('codemirror')` est
appelé en dur à trois endroits (`TemplateRenderService.php`,
`prepare_editor.php`, `elementoptions/default.php`) pour les champs de code
des templates ContentBuilder NG.

**Comportement déduit** — Ces trois points supposent que le plugin
`plg_editors_codemirror` (fourni et activé par défaut dans une installation
Joomla standard) est installé et activé ; en son absence, `Editor::getInstance('codemirror')`
retournera un éditeur non fonctionnel ou une exception selon l'implémentation
core de Joomla (non ré-vérifié ici, hors périmètre). Aucun `ComponentHelper::isEnabled('com_x')`
ni équivalent `PluginHelper::isEnabled()` défensif n'a été trouvé encadrant
cet appel — pas de garde-fou explicite dans le code si ce plugin d'éditeur
est désactivé.

### 5.5 Absence de `<uses>` dans les manifestes et de vérifications défensives d'extensions

**Fait observé** — Aucun manifeste XML du dépôt (`com_contentbuilderng.xml`,
`admin/config.xml`, `admin/access.xml`, les manifestes de plugins) ne
contient de balise `<uses>`. `com_contentbuilderng.xml` déclare seulement
`<php_minimum>8.3</php_minimum>`, aucune dépendance sur une autre extension
Joomla n'est déclarée au niveau installeur.

**Fait observé** — `ComponentHelper::isEnabled('com_x')` n'apparaît nulle
part dans le code (recherche exhaustive). Les seules vérifications
défensives d'activation d'extension trouvées sont
`PluginHelper::isEnabled('contentbuilderng_themes', $activePlugin)`
(`admin/src/Service/TemplateSampleService.php:35,134`,
`admin/src/Service/FormAuditService.php:700`) — c'est-à-dire des
vérifications sur les **propres plugins internes** de ContentBuilder NG
(groupe `contentbuilderng_themes`), pas sur des extensions tierces
externes.

**Comportement déduit** — ContentBuilder NG ne déclare et ne vérifie
formellement aucune dépendance "molle" envers une extension Joomla tierce
autre que les composants core toujours présents (`com_users`, `com_content`)
et ses propres sous-plugins. Il n'y a pas de détection défensive du type
« si com_fields est installé, alors… » ni d'intégration avec le système de
champs personnalisés Joomla (`com_fields`/`FieldsHelper`) — recherche
négative sur `com_fields`/`FieldsHelper` dans l'ensemble du dépôt.

### 5.6 Hébergement externe pour les mises à jour Joomla

**Fait observé** — `com_contentbuilderng_update.xml` pointe
`<changelogurl>` et `<downloads><downloadurl>` vers
`https://raw.githubusercontent.com/vcmb-cyclo/com_contentbuilderng/...`
et `https://github.com/vcmb-cyclo/com_contentbuilderng/releases/download/...`,
avec un `<sha256>` de vérification d'intégrité. `com_contentbuilderng.xml`
référence la même URL de changelog GitHub brut.

**Comportement déduit** — Le mécanisme natif "mises à jour" de Joomla
(installeur core, pas du code du composant) va chercher les métadonnées de
version et le paquet ZIP sur GitHub (`raw.githubusercontent.com`,
`github.com/releases`). C'est une dépendance d'infrastructure (hébergement
des releases), pas une dépendance de code, mais elle constitue un appel
réseau sortant effectué par l'environnement Joomla hôte au nom du
composant lors d'une vérification de mise à jour.

---

## 6. Synthèse

| Catégorie | Dépendance | Nature | Où |
|---|---|---|---|
| Composer (prod) | `bgli100/securimage` ^4.0 | Captcha graphique | `EditModel.php` (validation), `media/images/securimage*` (rendu image), `StorageModel.php` (garde autoload) |
| Composer (prod) | `phpoffice/phpspreadsheet` ^5.8 | Export XLSX front + import XLS/XLSX back | `site/tmpl/export/default.php`, `admin/src/Model/StorageModel.php` |
| Composer (dev) | `phpstan`, `phpunit`, `phpcs` | Qualité/tests, pas d'exécution en prod | `phpstan.neon.dist`, `phpcs.xml.dist`, `admin/phpunit.xml.dist`, `admin/tests/` |
| npm (racine) | `stylelint` | Lint CSS uniquement, aucun build | `package.json` |
| CDN externe | `@melloware/coloris` 0.25.0 | Sélecteur de couleur admin | `media/joomla.asset.json`, `admin/src/View/Form/HtmlView.php` |
| JS vendored local | `chart.js` 4.5.1 (MIT) | Graphiques du plugin `cbstats` | `plugins/content/contentbuilderng_cbstats/media/js/vendor/` |
| API externe | PayPal (Website Payments Standard, PDT/IPN — legacy) | Paiement/vérification pour la vérification de formulaire | `plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php` |
| Extension Joomla core | `com_users` | Inscription/activation de compte | `admin/src/Model/VerifyModel.php` |
| Extension Joomla core | `com_content` | Table `#__categories`, pipeline `onContentPrepare` | `site/src/Field/CategoriesField.php`, plugins du groupe `content` |
| Plugin Joomla core | `plg_editors_codemirror` | Éditeur de code des templates | `TemplateRenderService.php`, `prepare_editor.php`, `elementoptions/default.php` |
| Infrastructure | GitHub (`vcmb-cyclo/com_contentbuilderng`) | Hébergement du flux de mise à jour Joomla | `com_contentbuilderng_update.xml`, `com_contentbuilderng.xml` |

**Zones inconnues restantes** :
- Contenu exact et provenance précise du paquet `bgli100/securimage` (pas de
  `vendor/`/`composer.lock` livré dans ce dépôt pour inspection directe).
- Comportement de Joomla core si `plg_editors_codemirror` est désactivé (non
  vérifié, car hors du code de ce dépôt).
- Contenu complet des e-mails envoyés par ContentBuilder NG lui-même
  (au-delà du flux d'activation `com_users`), non examiné dans ce document
  centré sur les dépendances.
