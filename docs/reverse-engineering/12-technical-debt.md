# 12. Dette technique

> Document de rétro-analyse. Sources exploitées : `phpstan-baseline.neon`,
> `phpstan.neon.dist`, `phpcs.xml.dist`, `REFACTORING_PLAN.md`, `MIGRATION.md`,
> `MIGRATION_GUIDE.md`, `SQL_MIGRATION_GUIDE.md`, `STORAGE_WIZARD_PLAN.md`,
> `CHANGELOG.md`, `admin/sql/updates/mysql/`, l'historique `git log`, et un
> échantillonnage du code source (`grep`, comptages de lignes).
>
> Convention de lecture : **Fait observé** (élément vérifiable, fichier:ligne
> ou référence de commit/document), **Comportement déduit** (conséquence
> raisonnablement certaine du fait observé), **Hypothèse** (interprétation non
> vérifiée), **Zone inconnue** (ce que cette analyse ne permet pas de trancher).
> Seul le code présent dans le dépôt fait foi pour le comportement actuel ;
> l'historique git et les documents de suivi ne sont mobilisés ici que pour
> documenter la dette et son évolution, jamais comme description du
> comportement runtime.
>
> Périmètre : documentation uniquement. Aucun fichier de code n'a été modifié
> pour produire ce document.

---

## 0. Résumé exécutif

L'équipe a produit un audit interne daté du 2026-07-31 et un plan de refonte
(`REFACTORING_PLAN.md`) qui donne des indicateurs historiques : **86 000 lignes
de source**, **1 524 méthodes**, une baseline PHPStan de **1 639 lignes** au
niveau 2 sur 10, et à l'origine **2 377 erreurs PSR-12** sur 218 fichiers
(désormais résorbées en gate globale — voir §2). Ces chiffres et l'estimation
de **90–126 jours-homme** sont des données de planification ; ils ne mesurent
pas directement le nombre ou la gravité de défauts runtime.

Trois foyers de dette dominent, par impact décroissant :

1. **`site/src/Model/EditModel.php::store()`** — une méthode unique d'environ
   1 300 lignes, point de passage de la soumission d'enregistrement, sans test
   comportemental direct et exclue de la gate PSR-12. Le même modèle contient
   deux `eval()` de scripts de validation et d'action configurables ; ils
   permettent l'exécution de PHP selon les ACL d'écriture retenues.
2. **`script.php`** — 2 469 lignes / 96 méthodes dans une seule classe
   d'installation. Il reste une façade très large, bien qu'il délègue déjà une
   partie de ses responsabilités à quatre services dédiés.
3. **La dette PHPStan/PSR-12 elle-même est trackée et pilotée** (baseline
   décroissante visée, exclusions PSR-12 nommées et justifiées) — c'est la
   partie la mieux gérée de la dette du projet, au contraire de la dette
   « silencieuse » (duplication de code, `eval()`, méthodes god-object) qui
   n'a pas d'outil de mesure automatique.

---

## 1. Dette trackée : `phpstan-baseline.neon`

**Fait observé.** `phpstan-baseline.neon` compte 1 639 lignes
(confirmé par `wc -l`, cohérent avec le chiffre cité par
`REFACTORING_PLAN.md`). `phpstan.neon.dist` fixe le niveau d'analyse à **2 sur
10** et couvre `admin/src`, `admin/layouts`, `site/src`, `site/layouts`,
`plugins`, `script.php` (exclut `admin/vendor`).

Le nombre de lignes de la baseline est un indicateur de taille de fichier, pas
un décompte de diagnostics ni une mesure directe de dette fonctionnelle : une
entrée PHPStan s'étend sur plusieurs lignes.

### 1.1 Répartition par type d'erreur

| Identifiant PHPStan | Occurrences | Nature |
|---|---:|---|
| `property.notFound` | 149 | Accès à une propriété non déclarée |
| `method.notFound` | 36 | Appel de méthode absente du type statique |
| `variable.undefined` | 24 | Variable potentiellement non définie |
| `parameter.notFound` | 18 | `@param` PHPDoc référençant un paramètre inexistant |
| `closure.unusedUse` | 9 | Capture `use (&$x)` inutilisée dans une closure |
| `return.missing` | 7 | Chemin de retour manquant |
| `phpDoc.parseError` | 6 | PHPDoc malformé |
| `arguments.count` | 6 | Appel avec un nombre d'arguments incorrect |
| `class.notFound` | 5 | Référence à une classe introuvable pour l'analyseur |
| `return.phpDocType` | 3 | Type de retour PHPDoc incohérent |
| `constructor.unusedParameter` | 3 | Paramètre de constructeur non utilisé |
| `nullCoalesce.variable` / `isset.variable` / `empty.variable` | 6 | Variables jamais définies utilisées avec `??`/`isset`/`empty` |
| `binaryOp.invalid` | 1 | Opération binaire sur types incompatibles |

**Comportement déduit.** Plusieurs entrées `property.notFound` concernent les
classes `HtmlView` qui assignent des propriétés non déclarées (`$this->tags`,
`$this->pagination`, `$this->show_permissions`…). C'est une fragilité de
typage, pas une preuve de défaut fonctionnel. Les appels signalés par
`method.notFound` peuvent provenir de stubs Joomla incomplets, mais cette cause
doit être vérifiée entrée par entrée : elle ne permet pas d'écarter des erreurs
applicatives dans le même lot.

### 1.2 Répartition par fichier

| Fichier | Lignes de baseline |
|---|---:|
| `site/src/View/List/HtmlView.php` | 55 |
| `site/src/View/Details/HtmlView.php` | 28 |
| `admin/src/View/Edit/HtmlView.php` | 22 |
| `admin/src/View/Form/HtmlView.php` | 16 |
| `site/src/View/Publicforms/HtmlView.php` | 13 |
| `admin/src/View/Forms/HtmlView.php` | 11 |
| `admin/src/Service/TemplateRenderService.php` | 9 |
| `plugins/contentbuilderng_listaction/{untrash,trash}/.../Extension/*.php` | 7 chacun |
| `plugins/content/contentbuilderng_image_scale/.../ContentbuilderngImageScale.php` | 6 |
| `plugins/contentbuilderng_verify/passthrough/.../Passthrough.php` | 5 |

**Comportement déduit.** Le tableau montre une présence notable de la couche
**View**, mais ses cinq premières lignes totalisent 134 lignes, soit environ
8 % de la taille brute de la baseline. Il ne permet donc pas de conclure à une
concentration « massive », ni à la stabilité fonctionnelle des vues : l'absence
de mention dans le changelog n'est pas une mesure de régression en production.

### 1.3 Plan de résorption documenté

**Fait observé** (`REFACTORING_PLAN.md`, chantier A, étape 3). L'équipe prévoit
de monter le niveau PHPStan un cran à la fois (3→6), en régénérant puis
résorbant la baseline à chaque cran, avec un arrêt volontaire au niveau 6 :
« Les niveaux 7-8 exigent une couverture de typage que le code legacy
(`types/`, `EditModel`) ne pourra pas offrir avant D et F. » Chiffrage : 1 j
(niveau 3) + 2 j (niveau 4, détection de code mort) + 3 j (niveau 5) + 5–8 j
(niveau 6) = 11–16 jours-homme restants. Une étape 4 — garde CI qui échoue si
`phpstan-baseline.neon` grandit — est également prévue mais **pas encore
implémentée** au moment de cet audit.

**Zone inconnue.** Rien dans le dépôt ne montre de garde anti-régression de
baseline actif aujourd'hui (pas de job CI dédié trouvé référençant explicitement
la longueur du fichier) ; seule `phpstan.neon.dist` tolère les baseline entries
qui ne correspondent plus (`reportUnmatchedIgnoredErrors: false`), ce qui
peut laisser croître silencieusement une baseline sans qu'un désaccord entre
environnements (local vs CI) ne soit détecté.

---

## 2. Chantiers de refactoring auto-identifiés (`REFACTORING_PLAN.md`)

**Fait observé.** Le document date l'audit source au 2026-07-31 et précise
l'état d'avancement au 2026-08-01. C'est un aveu de dette direct, écrit par
l'équipe pour elle-même. Résumé chantier par chantier :

| # | Chantier | Statut au 2026-08-01 | Charge restante | Risque |
|---|---|---|---:|---|
| A | Outillage qualité (PHPStan 2→6, PSR-12) | PSR-12 en gate global ✅ ; PHPStan niveau 2 toujours, pas de garde de baseline | 11–16 j | Faible |
| B | Échappement des sorties front (XSS) | ✅ Fait — 2 XSS stockées publiques trouvées et corrigées en cours de route | — | Faible |
| C | Cache et requêtes N+1 | N+1 principaux résorbés ; **mise en cache de `ListModel::getData()` reportée par décision explicite** | 4–7 j | Moyen |
| D | Décomposition d'`EditModel::store()` | Caractérisation *structurelle* seulement (pas comportementale) ; découpage non commencé | 25–30 j | **Élevé** |
| E | Décomposition des autres god-methods | Non commencé | 15–20 j | Moyen |
| F | `FormSourceInterface` (types/ dynamiques) | Non commencé | 8–12 j | Moyen |
| G | Refonte du modèle de permission | Non commencé | 10–15 j | Moyen |
| H | Moteur de gabarits sans `eval()` | **Non planifié — `eval()` conservé par décision de direction** | 12–18 j (si repris) | **Élevé** |

**Total annoncé : 90–126 jours-homme restants.**

### 2.1 Points saillants à retenir

- **Chantier B (échappement) — fait observé notable.** La passe a trouvé *deux
  XSS stockées non citées par l'audit initial* : une boucle `foreach ($row as
  $key => $value) { echo nl2br((string) $value); }` dans
  `site/tmpl/list/default.php` et `$cardTitle` dans la vue « carte », toutes
  deux alimentées par des valeurs de champ soumises côté public sans aucun
  échappement (seul `nl2br()` était appliqué). Les deux sont dites corrigées
  au moment de la rédaction du plan — ce document ne revérifie pas l'état
  actuel du code, cohérent avec la consigne de ne pas traiter le code comme
  sujet d'audit ici.
- **Chantier C — décision de report documentée et argumentée.** Le cache de
  `ListModel::getData()` (501 lignes, chemin le plus chaud du composant) a été
  écarté après lecture complète : un effet de bord en écriture
  (`UPDATE #__contentbuilderng_records.rand_date` pour un tri aléatoire
  périodique) s'exécute *dans* la méthode de lecture, et la clé de cache
  envisagée par le plan initial omettait plusieurs dimensions (filtres de
  session par utilisateur, restriction « mes enregistrements » par
  utilisateur) — un cache mal indexé aurait pu fuiter des données entre
  utilisateurs ou servir des résultats obsolètes. C'est un exemple rare de
  dette *évitée consciemment* plutôt que simplement accumulée.
- **Chantier D — `EditModel::store()`.** Constat du plan : « 1 327 lignes à
  ce jour (1 305 au 2026-07-31) [...] validation CSRF, résolution du
  formulaire, traitement des téléversements, validation de champs, appels de
  plugins, insertion en base, génération d'articles, notifications e-mail,
  gestion d'état de liste. **Aucun test ne la couvre.** » La caractérisation
  comportementale complète est explicitement décrite comme non réalisable en
  l'état : le bouchon `Database` de test (`admin/tests/bootstrap.php`) ne sait
  répondre qu'à une seule requête (`#__usergroups`), alors que `store()` en
  émet « une douzaine de différentes sur quatre tables », en plus d'appeler
  `PluginHelper::importPlugin()`, un mailer et le système de fichiers, sans
  bouchon pour aucun des trois. Deux points de vigilance sont néanmoins figés
  par des tests structurels ajoutés : l'ordre CSRF-en-premier et
  écriture-disque-avant-validation-avec-suppression-sur-échec
  (`admin/tests/Unit/Model/EditModelStoreInvariantsTest.php`).
- **Chantier E — inventaire des « god-methods ».** Constat chiffré : **108
  méthodes dépassent 100 lignes, 301 dépassent 50 lignes** sur l'ensemble du
  code analysé. Cibles nommées par ordre de valeur :
  `ContentbuilderngImageScale::onContentPrepare()` (738 lignes, installe aussi
  un `set_error_handler()` global à supprimer — voir chantier I),
  `ArticleService::createArticle()` (662), `FormModel::save()` (661),
  `ListModel::getData()` (501), `types/*::getListRecords()` (461/373),
  `TemplateRenderService::getEditableTemplate()` (436, contient `eval()`),
  `StorageController::save()` (397), `VerifyModel::__construct()` (367 —
  « un constructeur ne doit pas faire ça »).
- **Chantier F — `admin/src/types/*.php`.** Constat : 3 149 lignes utilisant
  un namespace en minuscules hors convention PSR
  (`CB\Component\Contentbuilderng\Administrator\types`, confirmé par
  `grep` — voir §5.1). Certaines voies, notamment `PermissionService`,
  construisent encore un `require_once` et un `call_user_func` à partir du type
  de formulaire lu en base. Le risque LFI est conditionnel : il dépend du
  contrôle effectif de cette valeur et de l'absence de normalisation de chemin
  dans cette voie. Il ne doit pas être généralisé à `FormSourceFactory`, qui
  applique une liste blanche aux types intégrés. Le contrat
  `getNumRecordsQuery()` renvoie bien du SQL brut inséré dans un `SELECT` ;
  c'est surtout une dette de contrat et de typage.
- **Chantier G — modèle de permission.** Constat : l'autorisation dépend
  aujourd'hui d'un état de session (clé de session portée par formulaire +
  marqueur de contexte + filtre propriétaire en SQL) ; la sécurité repose sur
  une discipline d'appel (« armer avant de vérifier ») plutôt que sur un
  contrat de type. Le plan cible un `PermissionEvaluatorInterface` sans état.
- **Chantier H — `eval()` dans les gabarits — risque accepté et documenté.**
  Les champs `details_prepare` et `editable_prepare`
  (`admin/forms/form.xml:127,147`, `filter="raw"`) sont exécutés par `eval()`
  au rendu frontend. Une personne à qui les ACL permettent de modifier ces
  champs peut donc exécuter du PHP arbitraire sur le serveur. La gravité dépend
  de la délégation effective de `core.edit` et du modèle d'administration
  retenu ; ce n'est une escalade de privilège que si ce droit est confié à un
  acteur qui ne devrait pas pouvoir exécuter du code. La décision de direction
  documentée est de conserver `eval()`. Une atténuation proposée consiste à
  restreindre l'**écriture** de ces deux champs à `core.admin` sans changer le
  comportement des gabarits existants.
- **Chantier I — dette courte hors refonte**, dont : un `set_error_handler()`
  vide et global dans le plugin Image Scale, qui masque les erreurs prises en
  charge pendant les requêtes où ce plugin est chargé ; génération de classeur
  Excel dans un gabarit de vue (`site/tmpl/export/default.php`, violation MVC,
  2 j) ; 19 événements `GenericEvent` par chaîne de caractères à typer. La
  requête d'état de l'export n'est pas retenue : elle filtre déjà
  `records.form_id` et `states.form_id`.
- **Chantier J — cohérence d'opérations multi-étapes.** Constat : plusieurs
  opérations multi-étapes n'ont pas de mécanisme explicite de compensation :
  sauvegarde d'un stockage (`RENAME` + `ALTER` + métadonnées,
  `StorageModel.php:634-903`), sauvegarde d'un formulaire
  (`FormModel.php:944`), copie de formulaire (`FormController.php:899-1034`),
  synchronisation de colonnes (`DatatableService.php:447-620`). Le plan note
  une contrainte MySQL/MariaDB réelle : le DDL n'étant pas transactionnel,
  un chemin de compensation explicite est nécessaire ; une transaction seule
  ne résoudrait pas ces cas.

**Hypothèse.** Le chiffrage en jalons (M1 « Filet » à 15 j cumulés jusqu'à M5
« Finition » à 115 j) suggère un séquencement délibéré où le chantier D
(`EditModel::store()`) ne peut démarrer qu'après le chantier A (outillage) et
F (`FormSourceInterface`) — cohérent avec le principe affiché « sans filet
statique et sans style vérifié, D et E sont des refontes à l'aveugle ».

---

## 3. Marqueurs explicites dans le code (TODO/FIXME/HACK/workaround/@deprecated)

**Fait observé.** Un balayage `grep -rniE "TODO|FIXME|XXX|HACK|workaround|
@deprecated"` sur `admin/`, `site/`, `plugins/`, `script.php` (hors
`vendor/`) ne remonte que **cinq occurrences significatives**, aucune
`@deprecated` :

| Fichier:ligne | Contexte |
|---|---|
| `admin/src/types/com_contentbuilderng.php:510` | `// TODO: how to deal with terms in this?` |
| `site/src/Model/ListModel.php:932` | `// "buddy quaid hack", should be an option in future versions` |
| `site/src/Model/ListModel.php:1267` | `$table->text .= "<!-- workaround for J! pagebreak bug: class=\"system-pagebreak\" -->\n";` |

(Les deux occurrences dans `admin/tests/Unit/Helper/*Test.php` sont des noms
de méthode de test — `testRepairHasNothingToDoWithoutDuplicateGroups` — pas
des marqueurs de dette.)

**Comportement déduit.** Le volume de marqueurs explicites est très faible
pour un projet de cette taille (86 000 lignes selon `REFACTORING_PLAN.md`).
Ce n'est **pas** un signe d'absence de dette : la dette réelle du projet est
documentée ailleurs, de façon plus structurée, dans `phpstan-baseline.neon`
(1 639 lignes trackées mécaniquement) et `REFACTORING_PLAN.md` (10 chantiers
chiffrés), plutôt que laissée en commentaires inline. Les deux occurrences de
`ListModel.php` révèlent chacune une dette ponctuelle mais assumée : un
comportement figé faute de configuration exposée (« should be an option in
future versions »), et un contournement d'un bug tiers connu (moteur de
pagination Joomla `system-pagebreak`) maintenu tel quel plutôt que retiré.

**Zone inconnue.** L'absence de `@deprecated` ne signifie pas l'absence d'API
interne obsolète : `MIGRATION.md` (Phase 6, cochée faite) mentionne des
« compatibilités runtime legacy » déjà supprimées, ce qui suggère que du code
marqué obsolète a pu exister puis être retiré plutôt que laissé annoté.

---

## 4. Duplication de code et candidats à du code mort (échantillon)

> Avertissement : échantillonnage, pas un audit exhaustif. Les candidats
> ci-dessous sont vérifiés individuellement ; d'autres existent probablement
> ailleurs dans les 86 000 lignes du dépôt.

### 4.1 Duplication confirmée — parseur `eval()` de gabarit PHP

**Fait observé.** `admin/src/Helper/PhpTemplateHelper.php:23-77`
(`PhpTemplateHelper::evaluate()`) implémente un parseur qui découpe une chaîne
sur les marqueurs `<?php`/`?>` et `eval()` chaque segment PHP trouvé, avec une
branche `mb_*` et une branche `strlen`/`substr` de repli. **Le même algorithme,
caractère pour caractère identique dans sa logique**, est ré-implémenté inline
dans `admin/src/Service/TemplateRenderService.php` (lignes ~728-770, à
l'intérieur d'un traitement de `item_wrapper`), plutôt que d'appeler
`PhpTemplateHelper::evaluate()` — alors que ce même service *appelle déjà*
`PhpTemplateHelper::evaluate()` ailleurs
(`admin/src/Service/RuntimeUtilityService.php:164` fait de même, et
`admin/src/types/com_breezingformsng.php:1665` /
`admin/src/types/com_contentbuilderng.php:1116` aussi).

**Comportement déduit.** Trois sites d'appel utilisent le helper factorisé
(`RuntimeUtilityService`, les deux fichiers `types/`), un quatrième
(`TemplateRenderService`, dans le traitement des wrappers d'éléments) porte
une copie locale de la même logique. Une correction future du parseur (gestion
d'un cas limite, sécurisation) appliquée à `PhpTemplateHelper::evaluate()`
sans toucher à la copie de `TemplateRenderService.php` laisserait ce site en
désaccord silencieux avec les trois autres.

### 4.2 `eval()` — inventaire complet

**Fait observé.** Huit appels à `eval()` en dehors des tests, répartis dans
quatre fichiers :

| Fichier:ligne | Contexte |
|---|---|
| `admin/src/Helper/PhpTemplateHelper.php:48,71` | Parseur factorisé `<?php…?>` inline |
| `admin/src/Service/TemplateRenderService.php:745,766` | Copie dupliquée du même parseur (§4.1) |
| `admin/src/Service/TemplateRenderService.php:1004,1335` | `eval($prepareCode)` sur `details_prepare`/`editable_prepare` — le point RCE documenté par le chantier H |
| `site/src/Model/EditModel.php:756,763` | `eval($code)` pour les scripts de validation et d'action configurés sur les champs |

**Comportement déduit.** Le risque n'est pas un point isolé : il couvre le
parseur de wrapper, les `*_prepare` de gabarits et les scripts de validation et
d'action des champs. Les deux derniers usages ne relèvent pas du moteur de
gabarits, même s'ils exécutent également du texte configurable en base comme
PHP.

### 4.3 Candidats de code mort

**Fait observé.** `STORAGE_WIZARD_PLAN.md:38-40` documente lui-même un cas de
code mort trouvé et corrigé en cours de développement : « le task
`storage.listDelete` n'existait dans aucun contrôleur (bouton mort) » — un
bouton d'interface appelant une action serveur absente, réparé par l'ajout de
`StorageController::listDelete()`. C'est un aveu direct que ce type de dérive
(UI vivante, action morte) existe dans ce projet et n'est pas détecté
automatiquement.

**Hypothèse.** `PhpTemplateHelper` (§4.1) est un candidat inverse : la classe
elle-même n'est pas morte (3 appelants confirmés), mais sa duplication dans
`TemplateRenderService.php` suggère qu'elle a été écrite ou copiée avant que
le helper ne soit extrait, sans que l'occurrence in situ soit ensuite
remplacée par un appel — un reliquat de refactoring partiel plutôt qu'un code
mort au sens strict.

**Zone inconnue.** Un inventaire complet de code mort (classes/méthodes sans
appelant) demanderait un outil dédié (PHPStan niveau 4 « code mort » n'est pas
encore atteint — voir §1.3 ; `REFACTORING_PLAN.md` chantier A situe
explicitement la détection de code mort au niveau PHPStan 4, non franchi).

---

## 5. Incohérences structurelles

### 5.1 Convention de namespace : `admin/src/types/` hors convention

**Fait observé.** `grep -rh "^namespace" admin/src/types` retourne uniquement
`namespace CB\Component\Contentbuilderng\Administrator\types;` — le seul
segment de namespace en minuscules de tout le dépôt, alors que tous les autres
répertoires (`Controller`, `Model`, `View`, `Service`, `Helper`, `Table`,
`Extension`, `Contract`, `Dto`, `Rule` en admin ; `Controller`, `Dispatcher`,
`Model`, `View`, `Element`, `Field`, `Helper`, `Service`, `Table` en site)
suivent la convention PSR avec majuscule initiale. C'est exactement le constat
du chantier F de `REFACTORING_PLAN.md` (§2).

### 5.2 `admin/src/` vs `site/src/` — arborescences non symétriques

**Fait observé.** `admin/src/` contient `Contract/`, `Dto/`, `Rule/` et
`types/` en plus des répertoires communs ; `site/src/` contient `Dispatcher/`
et `Element/` que `admin/src/` n'a pas. Ce n'est pas nécessairement une
incohérence — les deux façades Joomla (admin/site) ont des responsabilités
différentes (le site a un dispatcher de composant, l'admin gère un modèle de
données plus riche via Table/Dto) — mais l'absence de tout répertoire
`Contract`/`Dto` côté site indique que la couche site n'a pas reçu la même
passe de typage/abstraction que l'admin.

### 5.3 Style PSR-12 à deux vitesses dans les mêmes répertoires

**Fait observé.** `phpcs.xml.dist:28-35` exclut explicitement de la gate
PSR-12 : `admin/src/types/*`, `site/src/Model/EditModel.php`,
`site/src/Model/ListModel.php`, `site/src/View/Details/HtmlView.php`,
`admin/src/Model/VerifyModel.php`, et les plugins `download`, `image_scale`,
`contentbuilderng_themes` — liste qui correspond très exactement à celle citée
par `REFACTORING_PLAN.md` chantier A comme « dette réelle, pas traitée ».

Vérification concrète sur les méthodes sans modificateur de visibilité
explicite (`function foo()` plutôt que `public function foo()`, un motif que
`phpcbf` corrige mécaniquement partout ailleurs selon le plan) :

| Fichier | Méthodes sans visibilité explicite | Exclu de `phpcs.xml.dist` |
|---|---:|---|
| `site/src/Model/EditModel.php` | 6 (dont `store()`, `EditModel.php:767`) | Oui |
| `site/src/Model/ListModel.php` | 1 | Oui |
| `admin/src/Model/FormModel.php` | 0 | Non |
| `admin/src/Model/StorageModel.php` | 0 | Non |

**Comportement déduit.** Ce n'est pas une différence de convention entre
admin et site en général (`FormModel.php`/`StorageModel.php`, côté admin, sont
alignés à 0 occurrence), mais une différence entre les fichiers passés au
crible du chantier A et ceux volontairement laissés de côté — dont
précisément `EditModel::store()`, la méthode elle-même définie sans
`public`/`private`/`protected` (`site/src/Model/EditModel.php:767`,
`function store()`), cohérent avec la décision documentée de ne pas y
appliquer `phpcbf` avant caractérisation comportementale (§2, chantier D,
étape 1).

### 5.4 Top 15 des plus gros fichiers PHP (hors `vendor`, hors tests)

**Fait observé.**

| Rang | Lignes | Fichier |
|---:|---:|---|
| 1 | 3 111 | `site/src/Model/EditModel.php` |
| 2 | 2 128 | `admin/tmpl/about/audit_report.php` |
| 3 | 2 037 | `admin/tmpl/storage/default.php` |
| 4 | 1 963 | `admin/src/types/com_breezingformsng.php` |
| 5 | 1 891 | `admin/src/Model/FormModel.php` |
| 6 | 1 845 | `admin/src/Model/StorageModel.php` |
| 7 | 1 765 | `site/tmpl/list/default.php` |
| 8 | 1 701 | `admin/src/Service/TemplateRenderService.php` |
| 9 | 1 560 | `admin/src/Controller/StorageController.php` |
| 10 | 1 536 | `plugins/content/contentbuilderng_cbstats/src/Extension/ContentbuilderngStats.php` |
| 11 | 1 488 | `admin/src/types/com_contentbuilderng.php` |
| 12 | 1 461 | `admin/src/Service/RepairWorkflowService.php` |
| 13 | 1 405 | `admin/src/Controller/FormController.php` |
| 14 | 1 364 | `site/src/Model/ListModel.php` |
| 15 | 1 328 | `site/tmpl/edit/default.php` |

**Comportement déduit.** Le fichier n°1 (`EditModel.php`) porte à lui seul
environ 1 300 lignes dans sa seule méthode `store()` (§2, chantier D) — soit
plus de 40 % du fichier dans une méthode unique. Les rangs 2, 3, 7 et 15 sont
des gabarits de vue (`tmpl/*.php`), pas du code applicatif : une taille de
1 300 à 2 100 lignes pour un template Joomla est un signal de logique
métier échappée dans la couche présentation (cohérent avec le constat du
chantier I sur `site/tmpl/export/default.php`, §2). Les rangs 4 et 11
(`admin/src/types/*.php`) confirment la taille du périmètre visé par le
chantier F (3 149 lignes cumulées pour les deux fichiers `types/`, cohérent
avec le chiffre du plan).

---

## 6. Évolutions de schéma et mécanismes de rattrapage (`admin/sql/updates/mysql/`)

**Fait observé.** 18 fichiers de migration versionnée, de `6.1.7.sql` à
`6.1.21-RC1.sql`. Le premier (`6.1.7.sql`) est explicitement un marqueur de
baseline : « Ce fichier enregistre la version 6.1.7 dans `#__schemas` sans
modifier le schéma : les installations existantes sont déjà à ce niveau via
`sql/install.sql` et `script.php`. » — confirmé par `MIGRATION.md` Phase 2,
qui précise que le versionnage SQL n'existait pas avant cette date et que les
migrations historiques `ContentBuilder → NG` de `script.php` restent hors de
ce système (elles renomment des tables étrangères au schéma NG).

### 6.1 Corrections et évolutions de schéma a posteriori

| Migration | Correction apportée |
|---|---|
| `6.1.10-RC06.sql` | `MODIFY initial_list_limit TINYINT` — type revu après coup |
| `6.1.10-RC09-B9.sql` puis `-B10.sql` | Ajout de deux colonnes `initial_order_dir2/3` (`-B9`), puis correction de leur défaut `'desc'` → `'asc'` et rattrapage des lignes existantes deux migrations plus tard (`-B10`) — un défaut mal choisi corrigé après coup, avec `UPDATE` de compensation ciblé sur les lignes dont `initial_sort_order2/3 = -1` |
| `6.1.10-RC11-B6.sql` | Ajout de trois colonnes `export_*` **et** `UPDATE ... SET export_id_column = show_id_column, export_state_column = list_state, export_publish_column = list_publish` — une notion (« colonnes visibles en export ») absente du modèle initial, rétro-alimentée depuis des colonnes existantes proches mais sémantiquement différentes |
| `6.1.15-RC4.sql` | Ajout de `list_state_bulk`, initialisé par copie de `list_state` — même motif : un réglage distinct extrait a posteriori d'un réglage existant |
| `6.1.7.104.sql` | Ajout de `required` sur `#__contentbuilderng_storage_fields`, avec un commentaire long expliquant explicitement pourquoi ce fichier ne peut pas placer la colonne `AFTER field_size` (risque de casser une mise à niveau depuis une version antérieure à `field_size`), renvoyant vers un « self-heal » runtime dans `script.php` (`ensureStorageFieldSizeColumn()`) qui complète la colonne après coup pour les installations qui ne l'ont pas encore |

**Interprétation.** Ces migrations montrent que le modèle de données a évolué,
notamment par séparation de réglages auparavant communs. Cette évolution ne
constitue pas, à elle seule, une dette technique : elle peut résulter d'une
évolution fonctionnelle normale. Seules les corrections de défauts documentés,
comme le défaut initial de `initial_order_dir2/3`, établissent un problème
historique précis.

**Fait observé — auto-réparation runtime en doublon du versionnage SQL.** Le
commentaire de `6.1.7.104.sql` révèle un mécanisme parallèle au système de
migrations versionnées : `script.php::ensureStorageFieldSizeColumn()` (et,
par nommage similaire, une famille entière `ensureForms*/ensureElements*` —
voir §8) rattrape des colonnes manquantes au runtime, indépendamment de
`#__schemas`. C'est une dette de robustesse assumée (documentée en commentaire)
plutôt que cachée, mais elle signifie que deux mécanismes de correction de
schéma coexistent pour des raisons de compatibilité ascendante entre anciennes
versions.

### 6.2 Fichiers récents sans changement de schéma

**Fait observé.** `6.1.19-RC4` à `6.1.20.sql` (7 fichiers) ne contiennent que
des commentaires « No structural database change is required » — de purs
marqueurs de version. `6.1.19-RC4.sql` correspond, d'après le `CHANGELOG.md`
correspondant, à l'ajout d'un contrôle qui « Align[s] Joomla's recorded
database schema version with the installed ContentBuilder NG manifest
version » et « reject[s] a stale schema version » — un correctif défensif visant
justement à empêcher la classe de désynchronisation illustrée par
`6.1.7.104.sql`/`6.1.8.sql` de se reproduire silencieusement.

**Zone inconnue.** Aucun index ajouté a posteriori n'apparaît dans ces 18
fichiers (aucune instruction `ADD INDEX`/`ADD KEY`) : soit le schéma initial
(`sql/install.sql`, non audité ici) couvre déjà les besoins d'indexation, soit
un manque d'index n'a simplement pas encore été détecté/documenté par
l'équipe. Cette analyse ne peut pas trancher entre les deux sans lire
`sql/install.sql` et le plan de requêtes réel, hors périmètre demandé.

---

## 7. Historique git — évolutions et corrections (derniers ~80-300 commits visibles)

> L'historique local visible couvre 124 commits sur la branche courante. Il
> ne remonte donc pas jusqu'à l'origine du projet ; les observations
> ci-dessous portent sur cette fenêtre récente (approximativement 6.1.7 →
> 6.1.20 + début de cycle 6.2.0).

**Fait observé.** Aucun commit `revert` trouvé sur l'historique visible
(`git log --oneline --all | grep -i revert` → vide). Séquence de versions
publiées, de la plus ancienne à la plus récente visible :
6.1.14 → 6.1.15 → 6.1.16 → 6.1.17 → 6.1.18 → 6.1.19 → 6.1.20, puis
`095346d Start 6.2.0 development and remove PHP 8.5 test deprecations`.

**Comportement déduit — cadence de correctifs resserrée après chaque
promotion en production.** Le motif récurrent est : une release candidate
« RCx » promue, suivie rapidement d'un ou plusieurs correctifs ciblés en
production. Exemples concrets tirés du `CHANGELOG.md` et de `git log` :

- **6.1.20** : `9692d16 Fix duplicated BF group values for 6.1.20-RC1` —
  une régression de RC1 (valeurs dupliquées de champs radio/case à
  cocher/liste BreezingForms après édition frontend) corrigée avant la
  promotion en version stable, confirmée par le `CHANGELOG.md`
  (« Fix a regression that duplicated BreezingForms radio, checkbox and
  select-list values after frontend editing »).
- **6.1.19** : cycle de 7 release candidates (RC1, RC3–RC7) avant la version
  stable, avec des corrections resserrées et ciblées à chaque étape (filename
  d'export XLSX, layout de tableau, alignement du schéma `#__schemas` avec le
  manifeste — cf. §6.2).
- **6.1.18** : `645da3c Fix Joomla 6.1.3 database schema checks` — un
  correctif déclenché par une évolution de Joomla lui-même (Database
  Maintenance de Joomla 6.1.3), pas par un bug interne — signe de dépendance
  étroite au comportement exact d'outils de diagnostic du cœur Joomla.
- **6.1.15** : série de correctifs de mise en page frontend rapprochés dans
  le temps (`6415914 Fix search group width overridden by Cassiopeia`,
  `cec4945 Restore responsive filter wrapping`, `2ac5f05 Target list filter
  flex container correctly`, `354aeda Keep list state controls on one desktop
  row`, `1cd0364 Remove excess gap before state filter`, `126d9af Restore
  compact list toolbar layout`, `155387d Fix search input and bulk state
  control sizing`) — plusieurs itérations successives sur la même zone
  d'interface (barre d'outils/filtres de liste frontend), signe d'une mise au
  point empirique plutôt que d'une spécification stable dès le départ.
- **`fa2d4ed`/`be495f2` : deux commits « Fix error controls » consécutifs** —
  un correctif suivi immédiatement d'un second portant le même intitulé,
  suggérant une première correction incomplète.

**Hypothèse.** La densité de commits « Fix » sur la mise en page frontend en
6.1.15-6.1.17 (barre d'outils de liste, alignement de colonnes, largeur de
recherche) suggère que cette zone d'UI n'était pas couverte par des tests
visuels/de régression automatisés au moment de ces changements — chaque
ajustement révélant un effet de bord sur le suivant. Ceci est cohérent avec
`AGENTS.md` qui, pour la baseline UX de menu, demande explicitement de
« distinguer les vérifications automatisées » des « tests manuels Joomla » et
de traiter toute régression manuelle comme couverture de test manquante — une
règle de processus qui semble être une réponse directe à ce type d'historique.

**Zone inconnue.** L'AGENTS.md du dépôt affirme que la branche de
développement courante est `gil_6.1.16` et que `6.1.15` est la version
publiée ; l'historique git observé montre au contraire des versions déjà
publiées jusqu'à `6.1.20` et un cycle `6.2.0` entamé. Cette analyse ne peut
pas déterminer si `AGENTS.md` est simplement resté non mis à jour après ces
sorties, ou si l'environnement d'analyse expose un état de dépôt différent de
celui que `AGENTS.md` décrit ; c'est signalé ici comme une dette
documentaire de méta-niveau (le fichier qui gouverne le workflow des agents
peut lui-même dériver du dépôt), pas comme une dette de code.

---

## 8. `script.php` — aperçu structurel

**Fait observé.** 2 469 lignes, 95,7 Ko, **une seule classe**
(`com_contentbuilderngInstallerScript`, ligne 64), **96 méthodes** : le
constructeur et les cinq points d'entrée Joomla sont publics, les 90 autres
sont privés.

### 8.1 Groupes de responsabilités identifiés par nommage de méthode

| Groupe | Exemples de méthodes | Volume approx. |
|---|---|---:|
| Cycle de vie Joomla (points d'entrée) | `preflight()`, `install()`, `update()`, `uninstall()`, `postflight()` | 5 méthodes |
| Journalisation / logs | `bootLogger()`, `log()`, `writeInstallLogEntry()`, `rotateSharedLogIfNeeded()`, `resolveSharedLogPath()`, `cleanupRotatedSharedLogs()`, `priorityToString()`, `formatInstallMessageForDisplay()` | 8 méthodes |
| Permissions et chemins fichiers | `checkInstalledFilePermissionsBeforeCopy()`, `getInstalledWritableCheckPaths()`, `collectNonWritablePaths()`, `ensureUploadDirectoryExists()` | 4 méthodes |
| Nettoyage de fichiers/langues obsolètes | `removeOldDirectories()`, `removeObsoleteFiles()`, `removeLegacyPluginLanguageFiles()`, `purgeStaleLanguageFiles()`, `removeObsoleteLanguageFiles()` | 5 méthodes |
| Auto-réparation de schéma runtime (`ensure*`) | `ensureFormsDisplayColumns()`, `ensureFormsFilterExactMatchDefault()`, `ensureElementsLinkableDefault()`, `ensureElementsDetailIncludeColumn()`, `ensureElementsApiAllowedColumn()`, `ensureElementsListIncludeDefault()`, `ensureElementsSearchIncludeDefault()`, `ensureStorageFieldSqlTypeColumn()`, `ensureStorageFieldSizeColumn()`, `ensureAdminMenuRootNodeExists()`, `ensureAdministrationMainMenuEntry()`, `ensureSubmenuQuickTasks()`, `ensureCanonicalComponentManifest()`, `ensurePluginsInstalled()` | 14 méthodes |
| Réparation de menus legacy | `buildMenuLinkOptionWhereClauses()`, `updateMenuLinks()`, `normalizeBrokenTargetMenuLinks()`, `repairLegacyMenuTitleKeys()`, `migratePackedPayloadsToModernFormat()`, `migrateLegacyMenuBackButtonParams()` (≈93 lignes), `migrateLegacyNestedMenuSettingsToRootParams()` (≈96 lignes), `removeLegacyAdminMenuBranchByAlias()` | 8 méthodes |
| Gestion de plugins (installation/désactivation/dédoublonnage) | `activatePlugins()`, `removeDeprecatedThemePlugins()`, `removeCoreValidationPlugins()`, `removeRetiredPlugins()`, `normalizeFormThemePlugins()`, `disableLegacyPluginsInPriorityOrder()`, `removeLegacySystemPluginFolderEarly()`, `removeLegacyPluginsDisableOnly()`, `removeLegacyPluginFoldersBestEffort()`, `deduplicateTargetPluginExtensions()` | 10 méthodes |
| Rapport de mise à jour (paquets composer, highlights) | `captureUpdatePackageSnapshot()`, `readComposerLockLibraries()`, `addUpdateHighlight()`, `addLibraryUpdateHighlight()`, `reportUpdatedPackageAssets()` | 5 méthodes |
| Cohérence de version/manifeste | `getCurrentInstalledVersion()`, `verifyInstalledExtensionConsistency()`, `getIncomingPackageVersion()`, `getIncomingPackageBuildType()`, `getIncomingPackageBuildTimestamp()`, `getBuildTypeLabel()`, `formatPackageBuildTimestamp()`, `formatIncomingBuildTypeMessage()` | 8 méthodes |
| Purge de caches | `purgeCaches()` | 1 méthode (~68 lignes) |
| Migration `ContentBuilder → NG` (renommage historique de composant) | `normalizeLegacyComponentTypes()`, `normalizeLegacyBreezingFormsTypes()`, `migrateClassicListMenusToModernListView()`, `normalizeLegacyBreezingFormsRecordTypes()`, `migrateLegacyContentbuilderName()`, `removeLegacyComponent()`, `deduplicateTargetComponentExtensions()` | 7 méthodes (dont `removeLegacyComponent()` ≈89 lignes) |
| Divers / infrastructure | `db()`, `safe()`, `checkRequirements()`, `installAndUpdate()`, `resolveJoomlaTimezoneName()`, `applyJoomlaTimezoneForLogging()`, `tableExists()`, `logDatabaseRuntimeInfo()`, `resetCriticalFailures()`, `hasCriticalFailure()`, `getCriticalFailureSummary()` | ~11 méthodes |

**Comportement déduit.** Le fichier reste une façade d'installation large,
mais il ne concentre pas toutes ces responsabilités dans son implémentation :
son constructeur instancie déjà `InstallerService`, `MigrationService`,
`PluginInstallerService` et `SchemaService`, auxquels plusieurs méthodes
privées délèguent. Le point d'entrée Joomla impose une classe de script ; la
dette résiduelle est donc la taille de cette façade et les responsabilités qui
y restent, non l'absence de toute séparation en services.

**Comportement déduit — famille `ensure*` comme mécanisme de dette
récurrent.** Les 14 méthodes `ensure*` (auto-réparation de schéma au runtime,
indépendante du système `#__schemas`/`sql/updates/mysql/`) confirment à
l'échelle du fichier entier le motif ponctuel déjà repéré en §6.1
(`ensureStorageFieldSizeColumn()`) : une bonne partie des évolutions de schéma
n'est pas seulement gérée par les migrations versionnées, mais aussi par un
filet de rattrapage runtime qui vérifie et corrige la structure des tables à
chaque `postflight()`. C'est une robustesse défensive (utile face à des
mises à niveau depuis des états de base hétérogènes, cf. `MIGRATION_GUIDE.md`)
mais aussi une duplication de la source de vérité du schéma entre deux
mécanismes distincts.

**Hypothèse.** La taille résiduelle de `script.php` s'explique en partie par
l'historique de migration du composant. Le document `MIGRATION_GUIDE.md` décrit
une migration automatique depuis l'ancien `contentbuilder` (tables, extensions,
assets, menus, thèmes et dédoublonnage). Cette hypothèse n'invalide pas les
services déjà extraits : elle explique seulement pourquoi la façade Joomla
reste substantielle.

---

## 9. Synthèse priorisée

| Impact | Élément de dette | Référence | État |
|---|---|---|---|
| **Élevé** | `EditModel::store()` (~1 300 lignes), non testée comportementalement, hors gate PSR-12 | `site/src/Model/EditModel.php:767` ; `REFACTORING_PLAN.md` chantier D | Caractérisation structurelle seulement ; découpage non commencé |
| **Élevé** | Exécution de PHP configurable en base (`details_prepare`/`editable_prepare`, wrapper d'éléments, scripts de validation/action) — risque conditionné par les ACL d'écriture | `admin/forms/form.xml:127,147` ; `TemplateRenderService.php:1004,1335` ; `EditModel.php:756,763` ; `REFACTORING_PLAN.md` chantier H | Risque accepté par décision de direction documentée ; non planifié |
| **Élevé** | `admin/src/types/*.php` : namespace hors convention, chargements et appels dynamiques ; SQL brut renvoyé par contrat | `REFACTORING_PLAN.md` chantier F ; confirmé §5.1 | Risque de chemin dynamique à qualifier par une revue ACL/normalisation ; découpage non commencé |
| Moyen | 108 méthodes > 100 lignes / 301 > 50 lignes (god-methods) hors `EditModel::store()` | `REFACTORING_PLAN.md` chantier E ; §5.4 | Non commencé |
| Moyen | Cache absent sur `ListModel::getData()` (chemin le plus chaud), report volontairement documenté | `REFACTORING_PLAN.md` chantier C | Reporté, risque identifié et évité consciemment |
| Moyen | Modèle de permission dépendant d'un état de session | `REFACTORING_PLAN.md` chantier G | Non commencé |
| Moyen | Opérations DDL/multi-tables sans mécanisme explicite de compensation | `REFACTORING_PLAN.md` chantier J | À traiter par compensation ; une transaction seule ne couvre pas le DDL MySQL/MariaDB |
| Moyen | `script.php` : façade de 96 méthodes malgré une délégation partielle à quatre services | §8 | Structurel, propre au format script Joomla |
| Moyen | Duplication du parseur `eval()` de gabarit entre `PhpTemplateHelper` et `TemplateRenderService` | §4.1 | Non signalée par les documents internes consultés |
| Faible | `phpstan-baseline.neon` : 1 639 lignes, niveau 2/10, pas encore de garde anti-régression | §1 ; `REFACTORING_PLAN.md` chantier A | Plan chiffré (11–16 j), en cours |
| Faible | `set_error_handler()` vide global dans le plugin Image Scale | `ContentbuilderngImageScale.php:30-31` ; `REFACTORING_PLAN.md` chantier I | Masque les erreurs prises en charge dans les requêtes où le plugin est chargé |
| Faible | Marqueurs `TODO`/`hack`/`workaround` inline | §3 | Ponctuel, peu nombreux |
| Faible | Évolutions et rattrapages de schéma historiques | §6 | À distinguer des défauts avérés ; une évolution fonctionnelle n'est pas une dette par elle-même |

---

## 10. Zones hors périmètre de cette analyse

- Contenu de `sql/install.sql` (schéma de référence) — non lu, seules les
  migrations incrémentales de `admin/sql/updates/mysql/` ont été examinées.
- Historique git antérieur au commit le plus ancien visible localement
  (124 commits) — le dépôt peut avoir un historique plus long non accessible
  dans cet environnement.
- Détection exhaustive de code mort (nécessiterait un outil d'analyse d'appel
  statique complet ou PHPStan niveau 4, non encore atteint par le projet —
  voir §1.3).
- Couverture de test réelle par fichier (le nombre de tests — 689 à 694 selon
  les étapes du plan — est cité par `REFACTORING_PLAN.md` mais n'a pas été
  recoupé ici avec une exécution de couverture).
