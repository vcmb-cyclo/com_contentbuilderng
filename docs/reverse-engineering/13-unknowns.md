# 13 — Zones d'incertitude (synthèse consolidée)

> Rétro-analyse (reverse engineering) de `com_contentbuilderng` — Joomla 6 /
> PHP 8.3+ / MySQL-MariaDB. Ce document est une **compilation transversale et
> dédupliquée** de tous les points signalés comme **Hypothèse**, **Zone
> inconnue**, **contradiction non tranchée** ou **anomalie à vérifier
> manuellement** dans les 12 documents `01-overview.md` à `12-technical-debt.md`
> de `docs/reverse-engineering/`. Il ne réanalyse pas le code : chaque point
> est repris de sa source, avec sa référence, et reformulé pour être lisible
> indépendamment du document d'origine.
>
> **Documentation uniquement — aucun fichier de code n'a été modifié pour
> produire ce document.**
>
> Niveaux utilisés (repris de la légende commune aux 12 documents source) :
> - **Hypothèse non vérifiée** : interprétation plausible, non confirmée par
>   lecture exhaustive du code.
> - **Zone inconnue** : point non tranché, faute d'avoir pu lire l'intégralité
>   du code concerné dans le budget de la mission, ou faute d'environnement
>   Joomla exécutable pour observer le comportement réel.
> - **Contradiction non tranchée entre deux documents** : deux documents de la
>   rétro-analyse (ou un document et une source du dépôt, ex. `AGENTS.md`)
>   énoncent des faits qui ne se recoupent pas sans que cette fusion tranche
>   laquelle est actuelle.
> - **Anomalie nécessitant vérification manuelle prioritaire** : indice fort
>   dans le code statique (import d'une classe absente, requête sans `WHERE`,
>   contrôle d'accès manquant) qui, si confirmé en exécution réelle,
>   constitue une régression ou une faille — pas une simple incertitude de
>   lecture.
>
> Gravité perçue indiquée par point (élevé / moyen / faible) — une
> appréciation qualitative de cette rétro-analyse, pas une notation de
> sécurité formelle (CVSS ou équivalent), à confirmer cas par cas par
> l'équipe produit.

---

## Priorités de vérification humaine

Les points ci-dessous reviennent dans plusieurs documents et/ou portent sur
un comportement visible en production (écran, bouton, export, contrôle
d'accès). Ils sont classés par gravité perçue décroissante, pas par ordre de
citation. Le numéro 5, invalidé par lecture du code, reste vacant pour
préserver les renvois vers les autres points.

| # | Sujet | Gravité | Documents source | Action recommandée |
|---|---|---|---|---|
| 1 | **`Administrator\Model\EditModel` introuvable** — l'écran admin `view=edit` (lien « Preview » de l'onglet Data Storage) importe une classe absente du dépôt (`admin/src/View/Edit/HtmlView.php:269-270` importe `Administrator\Model\EditModel`, seule `site/src/Model/EditModel.php` existe). Le lien « Preview » généré par `admin/src/View/Storage/HtmlView.php:426` échouerait donc probablement à l'exécution. | **Élevé** | `02-architecture.md` §2 ; `04-features.md` #36 ; `14-traceability.md` #36 | Tester manuellement en environnement Joomla réel le clic sur « Preview » depuis l'onglet Data d'un storage ; si l'erreur se confirme, créer/rétablir la classe manquante ou retirer le lien. |
| 2 | **`DELETE FROM #__contentbuilderng_articles` sans clause `WHERE`** dans `FormModel::deleteByIds()` (`admin/src/Model/FormModel.php:1785-1788`), déclenché quand la liste des `form_id` restants après une suppression de vue(s) est vide. | **Élevé** | `03-data-model.md` §19 ; `04-features.md`, Contradictions #4 ; `05-user-flows.md` §6.4 | Relire l'intégralité des appelants de `deleteByIds()` pour confirmer que ce cas ne se produit que lors de la suppression de **toutes** les vues à la fois (purge totale légitime) ; ajouter un test de non-régression ciblé avant toute modification de cette zone. |
| 3 | **`DatatableController::create()`/`sync()` : `core.manage` sans contrôle `core.edit`** — `ComponentAccessTrait::execute()` exige `core.manage` avant ces deux tâches DDL, puis chacune vérifie le jeton CSRF. Aucun contrôle spécifique `core.edit` n'est présent dans le contrôleur. | **Élevé (à confirmer selon la politique ACL)** | `06-api-contracts.md` §2.1 et §8 (point 1) ; `05-user-flows.md` §15.4 ; `07-security.md` §1.2 ; `14-traceability.md` #9 | Déterminer si `core.manage` suffit pour créer et synchroniser les tables. Si `core.edit` est requis pour modifier un storage, ajouter ce contrôle aux deux tâches et couvrir un appel direct par un test ciblé. |
| 4 | **`ExportController`/`ExportModel` (export Excel front, `view=export`) ne rejoue pas le contrôle `listaccess`** appliqué par `ListController::display()` pour la même donnée en liste — l'accès **direct** à l'URL d'export d'une vue publiée semble contourner une restriction `List Access` par groupe. | **Élevé** | `07-security.md` §1.3 et §11 (point 2) ; `04-features.md`, Contradictions #7 ; `09-business-rules.md` §8.7 | Tester en environnement Joomla réel l'accès direct à `view=export&id=<id>` sur une vue dont `listaccess` est restreint par groupe, avec un utilisateur non autorisé. Si confirmé, ajouter l'appel `PermissionService::checkPermissions('listaccess', ...)`/`authorizeFe()` manquant dans `ExportController`/`ExportModel`. |
| 6 | **Double fichier `Dispatcher.php` sous `site/src/`** — `site/src/Dispatcher/Dispatcher.php` (le vrai dispatcher PSR-4, chargé par Joomla) coexiste avec `site/src/Controller/Dispatcher.php` (corps plus simple/ancien, aucune référence trouvée ailleurs dans le dépôt) — code mort probable. | **Moyen** | `02-architecture.md` §2 ; `04-features.md` #35 ; `06-api-contracts.md` §3 ; `14-traceability.md` #35 | Confirmer avec Gilles qu'il s'agit d'un oubli de nettoyage (réorganisation antérieure vers `Dispatcher/`) plutôt que d'un filet de sécurité volontaire ; si confirmé mort, supprimer le fichier. |
| 7 | **Incohérence de version entre `AGENTS.md` (branche `gil_6.1.16`, `6.1.15` publiée) et l'historique Git** — celui-ci montre des versions déjà publiées jusqu'à `6.1.20` et un cycle `6.2.0` entamé (`095346d Start 6.2.0 development...`). | **Moyen** (dette documentaire de méta-niveau, pas de code) | `12-technical-debt.md` §7 ; `AGENTS.md` (racine du dépôt) | Demander confirmation à Gilles : soit `AGENTS.md` doit être mis à jour avec la version de développement réelle, soit l'environnement d'analyse expose un état différent de celui attendu — à trancher avant toute nouvelle instruction de branche donnée à un agent. |
| 8 | **`default_category` obligatoire uniquement côté client** (JS, `admin/layouts/form/article_tab.php:262-334`) quand `create_articles=1` — aucune revalidation serveur confirmée dans `FormController::save()`/`FormModel::save()`, ce qui pourrait permettre un article généré avec `catid=0` (catégorie Joomla invalide) via un client HTTP direct ou un import de configuration incomplet. | **Moyen** | `09-business-rules.md` §5.7 ; `06-api-contracts.md` §8 (point 6) ; `05-user-flows.md` §12.5 ; `04-features.md`, Contradictions (renvoi #11) | Vérifier en environnement réel la conséquence d'un `default_category=0` posté directement ; ajouter une revalidation serveur dans `FormModel::save()`/`ArticleService::createArticle()` si le risque se confirme en production. |
| 9 | **`admin/src/types/*.php` — chargement dynamique via `require_once` construit depuis une valeur stockée en base**, qualifié par les développeurs eux-mêmes de « gadget LFI » potentiel (`REFACTORING_PLAN.md`), namespace hors convention PSR (`types` en minuscule), `call_user_func` pour l'instanciation. | **Moyen-Élevé** | `12-technical-debt.md` §2 (chantier F) et §5.1 ; `02-architecture.md` §6 | Prioriser le chantier F du plan de refactoring existant (`FormSourceInterface`) ou, à défaut, auditer précisément les chemins d'entrée pour confirmer/exclure l'exploitabilité réelle de ce mécanisme. |
| 10 | **`eval()` sur texte configurable en base** (`details_prepare`/`editable_prepare` de `admin/forms/form.xml:127,147`, plus `TemplateRenderService.php:1004,1335` et `EditModel.php:756,763`) — RCE assumée pour tout titulaire de `core.edit` sur le composant, décision de direction documentée de conserver ce comportement. | **Élevé** (risque accepté, pas une incertitude à lever mais un point à reconfirmer périodiquement) | `12-technical-debt.md` résumé exécutif, §2 (chantier H), §4.2, §9 | Reconfirmer périodiquement avec Gilles que la décision de conserver `eval()` reste valide ; envisager l'atténuation à coût quasi nul déjà proposée par le plan (restreindre l'écriture de ces deux champs à `core.admin`). |

---

## Modèle de données

**11. Absence de toute contrainte `FOREIGN KEY` SQL déclarée.**
Toutes les relations entre les 13 tables `#__contentbuilderng_*` (et vers
`#__content`/`#__users`) sont logiques, imposées par le code applicatif
(cascades manuelles, jointures), jamais par InnoDB.
Document(s) : `03-data-model.md` §18, §19.
Référence code : `admin/sql/install.sql` (aucun `FOREIGN KEY`).
Niveau : **Zone inconnue** (conséquence pratique non quantifiée).
Gravité : faible à moyenne (risque d'incohérence référentielle en cas de
manipulation directe de la base ou de bug applicatif).
Action : pas d'action immédiate ; à garder en tête pour tout script de
maintenance SQL direct.

**12. `#__contentbuilderng_list_states` sans index sur `form_id`**, alors que
la table est quasi systématiquement filtrée par `form_id` dans le code
(`FormModel.php`, `ListStateAndRatingTrait.php`).
Document(s) : `03-data-model.md` §7, §19.
Référence code : `admin/sql/install.sql:452-461`.
Niveau : Zone inconnue (peut-être un oubli mineur — la table reste petite
par nature).
Gravité : faible.
Action : vérifier en environnement réel s'il existe un index non documenté ;
sinon, évaluer l'ajout d'un index si le volume de vues/états grandit.

**13. `#__contentbuilderng_registered_users.record_id` — cible exacte non
confirmée** (`records.id` supposé, par hypothèse, alors que dans
`records`/`list_records` eux-mêmes `record_id` désigne la ligne source, pas
`records.id`).
Document(s) : `03-data-model.md` §11, §19.
Référence code : `site/src/Model/EditModel.php:2012` (écriture), `register()`
(`:2295-2557`).
Niveau : Hypothèse non vérifiée.
Gravité : faible (table d'audit/traçabilité, pas de logique métier active
dessus).
Action : relire précisément le point d'écriture pour confirmer la cible
exacte si un développement futur doit joindre cette table.

**14. Lien `verifications` ↔ `#__contentbuilderng_users.verified_*` non tracé
ligne à ligne** — le mécanisme précis par lequel une vérification aboutie
met à jour les drapeaux `verified_view/new/edit` n'a pas été confirmé au-delà
d'une présomption raisonnable (`VerifyModel::activate()`/
`activate_by_admin()`).
Document(s) : `03-data-model.md` §12, §19 ; `04-features.md` §23 (zone
inconnue « verification_days »).
Référence code : `admin/src/Model/VerifyModel.php:109-559` (portion lue),
`:561+` (non entièrement relu).
Niveau : Zone inconnue.
Gravité : moyenne (impacte la compréhension complète du flux de
vérification/paiement).
Action : relire intégralement `VerifyModel.php` (admin et site) pour
confirmer la chaîne complète.

**15. Aucune purge/rétention identifiée pour `#__contentbuilderng_verifications`**
— accumulation potentiellement non bornée dans le temps.
Document(s) : `03-data-model.md` §12, §19 ; `04-features.md` §23.
Référence code : recherche exhaustive sans résultat de `DELETE` sur cette
table hors cascade de suppression de vue.
Niveau : Zone inconnue.
Gravité : faible (sauf considération RGPD/volumétrie explicite).
Action : vérifier auprès de Gilles si une politique de rétention est
attendue ; sinon documenter comme comportement assumé.

**16. `storage_fields.published = 0` — sort physiquement de la colonne
sous-jacente ou masque seulement les métadonnées ?** Non confirmé par la
lecture d'un `DROP COLUMN` correspondant à une désactivation simple de champ.
Document(s) : `03-data-model.md` §3, §19.
Référence code : `admin/src/Model/StoragefieldsModel.php:415`,
`StorageModel.php:1228` (suppriment la ligne de métadonnées, pas la colonne
physique dans le chemin lu).
Niveau : Hypothèse non vérifiée.
Gravité : faible.
Action : relire le code de désactivation d'un champ storage pour confirmer
si la colonne physique est conservée ou supprimée.

**17. Tables dynamiques `#__<storage.name>` non désinstallées** — confirmées
comme absentes de `uninstall.sql`, mais conséquences opérationnelles exactes
(accumulation disque, collision de nom après réinstallations répétées) non
explorées.
Document(s) : `03-data-model.md` §15, §17, §19 ; `11-execution-flows.md` §3.
Référence code : `admin/sql/uninstall.sql` (13 `DROP TABLE` seulement).
Niveau : Fait observé (le comportement) + Zone inconnue (les conséquences
pratiques en environnement de test/production répétée).
Gravité : faible (comportement assumé pour préserver le contenu généré).
Action : documenter explicitement ce comportement pour toute procédure
RGPD/désinstallation complète ; tester une collision de nom en environnement
de test si le cas est jugé plausible.

**18. Rapprochement migrations ↔ `install.sql` limité aux 17 fichiers présents
dans le dépôt** — la cohérence n'est garantie que pour ces versions ; les
mises à jour depuis une version antérieure à `6.1.7` sont hors périmètre de
vérification.
Document(s) : `03-data-model.md` §1.2, §19.
Référence code : `admin/sql/updates/mysql/*.sql`.
Niveau : Zone inconnue (limite méthodologique assumée).
Gravité : faible.
Action : aucune, sauf si une migration depuis une version très ancienne
(< 6.1.7) est un scénario réel à couvrir.

---

## Sécurité

**19. `contentbuilderng.manage` / `contentbuilderng.admin` déclarées et
documentées mais jamais invoquées par `->authorise()` dans le code lu.**
Document(s) : `07-security.md` §1.1, §11 (point 1).
Référence code : `admin/access.xml:54-64` ; `docs/fr/permissions-acl.md:12-13`,
`docs/en/permissions-acl.md:11-12`.
Niveau : Anomalie à vérifier (deux cases ACL sans effet observable).
Gravité : moyenne (peut induire un administrateur en erreur sur la portée
réelle de ces droits).
Action : soit brancher ces actions sur un contrôle réel si elles ont un rôle
prévu, soit les retirer de `access.xml` et de la documentation utilisateur.

**20. Champs `allow_html = true` réaffichés sans `htmlspecialchars()`**,
protégés uniquement par `cleanString()` (qui échappe seulement la syntaxe de
jetons `{champ}` propre au composant, pas un purificateur HTML).
Document(s) : `07-security.md` §5, §11 (point 3).
Référence code : `admin/src/Service/TemplateRenderService.php:707-719,
900-995, 1098, 1157-1158` ; `admin/src/Service/TextUtilityService.php:20-31`.
Niveau : Hypothèse non vérifiée (probablement intentionnel — champ « éditeur
riche »), à valider contre la matrice ACL réelle de qui peut remplir/lire un
tel champ.
Gravité : moyenne (XSS stocké possible selon la configuration ACL du
formulaire).
Action : confirmer avec Gilles si `allow_html` doit rester un choix
assumé par le concepteur de formulaire ; documenter clairement le risque
dans la documentation utilisateur de cette option.

**21. Interpolation SQL directe sans `$db->quote()` dans `com_breezingformsng.php`**
(`GROUP_CONCAT` avec `$element['name']`/`$name` injectés en chaîne), hors du
périmètre des 4 fichiers couverts par `SqlHardeningTest.php`.
Document(s) : `07-security.md` §6, §11 (point 4).
Référence code : `admin/src/types/com_breezingformsng.php:829, 831, 843, 845,
1022, 1105`.
Niveau : Anomalie à vérifier (pattern déjà corrigé ailleurs dans le même
fichier avec `intval()`, mais pas uniformément).
Gravité : moyenne (exploitabilité limitée aux utilisateurs `core.manage`/
`core.edit` pouvant nommer un champ BreezingForms importé).
Action : étendre `SqlHardeningTest.php` à ce fichier, ou corriger
l'interpolation par `$db->quote()`.

**22. Aucun test unitaire dédié aux mécanismes de durcissement de l'upload**
(`DEFAULT_ALLOWED_UPLOAD_EXTENSIONS`, `hasExecutableExtension()`,
`is_internal_path()`).
Document(s) : `07-security.md` §7, §11 (point 5).
Niveau : Zone inconnue (absence de couverture, pas un bug confirmé).
Gravité : faible.
Action : ajouter une couverture de test ciblée, cohérente avec la politique
« traiter toute régression manuelle comme couverture de test manquante »
d'`AGENTS.md`.

**23. Trois dossiers `admin/tmpl/` sans `index.html`** (`titleset/`,
`titlesets/`, `storagewizard/`), à la différence de leurs voisins.
Document(s) : `07-security.md` §8, §11 (point 6).
Niveau : Anomalie mineure confirmée (fait observé), impact limité par la
garde `_JEXEC` déjà présente dans chaque fichier.
Gravité : faible.
Action : ajouter les `index.html` manquants par cohérence, sans urgence.

**24. `verification_hash` généré via `md5(uniqid() . mt_rand() . $user_id)`**
plutôt qu'un générateur cryptographiquement sûr (`random_bytes()`).
Document(s) : `07-security.md` §9, §11 (point 7).
Référence code : `admin/src/Model/VerifyModel.php:248`.
Niveau : Fait observé + Zone inconnue sur l'entropie effective exploitable.
Gravité : moyenne (jeton d'accès à un flux de vérification/paiement).
Action : évaluer le remplacement par `bin2hex(random_bytes(16))` ou
équivalent lors d'une prochaine évolution de `VerifyModel`.

**25. Champs PayPal `token`/`test_token` déclarés en `type="text"` plutôt
que `type="password"`.** Signalé indépendamment par deux documents sous deux
angles différents (secret potentiel vs cohérence de configuration).
Document(s) : `07-security.md` §9 ; `08-configuration.md` §13, §18 (point 2).
Référence code : `plugins/contentbuilderng_verify/paypal/paypal.xml:22-27`.
Niveau : Zone inconnue (le code ne permet pas de confirmer si `token` est un
secret sensible côté PayPal ou un simple identifiant public de bouton).
Gravité : faible à moyenne.
Action : demander confirmation à Gilles sur la sensibilité réelle de ce
champ avant toute évolution (passage en `type="password"` ou non).

**26. `CURLOPT_SSL_VERIFYPEER => false`** sur les deux appels sortants du
plugin PayPal, et repli `fsockopen` construit avec un nom d'hôte incluant le
schéma `https://` sur le port 80 (comportement runtime non testé faute
d'environnement PHP exécutable).
Document(s) : `07-security.md` §10, §11 (point 8) ; `10-dependencies.md`
§3.2, §3.3 ; `11-execution-flows.md` §5.
Référence code : `plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php:209,
221, 302, 312`.
Niveau : Fait observé (le code) + Zone inconnue (comportement runtime exact
du repli `fsockopen`).
Gravité : moyenne à élevée (validation de paiement vulnérable en théorie à
une interception MITM sur le trajet serveur → PayPal).
Action : tester en environnement réel le comportement du chemin de repli
`fsockopen` ; envisager de réactiver `CURLOPT_SSL_VERIFYPEER` et de corriger
la construction de l'hôte `fsockopen`, sous réserve de validation produit
(risque déjà connu, non corrigé par choix explicite hors périmètre de cette
rétro-analyse).

**27. Comportement exact du filtre d'entrée Joomla `'html'`** utilisé pour
les champs `allow_html`, non ré-audité (code du noyau Joomla, hors dépôt).
Document(s) : `07-security.md` §2, §5.
Référence code : `site/src/Model/EditModel.php:1091, 1093`.
Niveau : Zone inconnue (limite de périmètre assumée).
Gravité : faible, à mettre en regard du point 20 ci-dessus.
Action : aucune action de ce dépôt ; dépend du comportement du noyau Joomla.

---

## Architecture et code mort

**28. `admin/src/View/Edit/HtmlView.php` importe `Administrator\Model\EditModel`,
classe absente du dépôt.** Voir priorité #1 ci-dessus (point le plus
critique).
Document(s) : `02-architecture.md` §2 ; `04-features.md` #36 ;
`09-business-rules.md` §6.5 ; `14-traceability.md` #36.
Niveau : **Anomalie nécessitant vérification manuelle prioritaire.**

**29. Double fichier `Dispatcher.php`** sous `site/src/`. Voir priorité #6
ci-dessus.
Document(s) : `02-architecture.md` §2 ; `04-features.md` #35 ;
`06-api-contracts.md` §3 ; `14-traceability.md` #35.
Niveau : **Anomalie nécessitant vérification manuelle prioritaire**
(vérification de non-usage, pas de test fonctionnel).

**30. `MenuService::createBackendMenuItem15()`/`createBackendMenuItem16()`/
`createBackendMenuItem3()` — aucun appelant identifié.**
Document(s) : `02-architecture.md` §3 ; `04-features.md` #37 ;
`14-traceability.md` #37.
Référence code : `admin/src/Service/MenuService.php`.
Niveau : Hypothèse forte (code legacy Joomla 1.5/1.6/3, contraire à la règle
« Joomla 6 only » d'`AGENTS.md`), non confirmée par une recherche exhaustive
d'appelants dans le cadre de cette mission.
Gravité : faible (dette, pas un risque fonctionnel).
Action : confirmer l'absence totale d'appelant (recherche exhaustive) avant
suppression.

**31. `admin/src/types/*.php` — namespace hors convention PSR, chargement
dynamique par `require_once`/`call_user_func`.** Voir priorité #9 ci-dessus.
Document(s) : `02-architecture.md` §6 ; `12-technical-debt.md` §2 (chantier
F), §5.1.
Niveau : Fait observé (le mécanisme) + dette technique majeure identifiée
par l'équipe elle-même.

**32. Duplication du parseur `eval()` de gabarit** entre
`PhpTemplateHelper::evaluate()` (factorisé, 3 appelants) et une copie inline
dans `TemplateRenderService.php` (non factorisée).
Document(s) : `12-technical-debt.md` §4.1, §9.
Référence code : `admin/src/Helper/PhpTemplateHelper.php:23-77` vs
`admin/src/Service/TemplateRenderService.php:728-770`.
Niveau : Hypothèse (reliquat de refactoring partiel plutôt que code mort au
sens strict).
Gravité : faible à moyenne (une correction future du parseur appliquée à un
seul des deux endroits créerait une désynchronisation silencieuse).
Action : remplacer la copie inline par un appel à `PhpTemplateHelper::evaluate()`.

**33. Inventaire complet de code mort non réalisable dans le cadre de cette
mission** — nécessiterait un outil d'analyse d'appel statique complet ou
PHPStan niveau 4 (non encore atteint par le projet).
Document(s) : `12-technical-debt.md` §4.3, §1.3, §10.
Niveau : Zone inconnue (limite méthodologique assumée).
Gravité : faible.
Action : prioriser la montée en niveau PHPStan (chantier A du plan de
refactoring existant) qui permettra une détection de code mort outillée.

**34. `admin/src/` vs `site/src/` — arborescences non symétriques**
(`Contract`/`Dto`/`Rule`/`types` côté admin seulement, `Dispatcher`/`Element`
côté site seulement).
Document(s) : `12-technical-debt.md` §5.2.
Niveau : Fait observé, pas nécessairement une incohérence — signalé pour
mémoire, la couche site n'ayant pas reçu la même passe de typage/abstraction
que l'admin.
Gravité : faible.
Action : aucune action requise, à garder en tête pour un futur effort
d'harmonisation.

---

## Événements et plugins internes

**35. Point d'import exact du groupe `contentbuilderng_listaction` avant
`onAfterArticleCreation`** — dispatché depuis `ArticleService.php:711-712`
vers le groupe entier, sans import ciblé visible juste avant dans ce fichier.
Document(s) : `06-api-contracts.md` §5.3, §8 (point 2) ; `04-features.md`,
Contradictions #2 ; `11-execution-flows.md` §1 ; `09-business-rules.md` §11.1.
Niveau : Zone inconnue.
Gravité : faible à moyenne (comprendre exactement quels plugins reçoivent
cet événement importe pour tout nouveau plugin `listaction` tiers).
Action : relire `ArticleService.php` en amont de la ligne 711 pour localiser
l'éventuel `PluginHelper::importPlugin('contentbuilderng_listaction')` sans
argument.

**36. Retour de `onAfterSubmit` (`$submit_after_result`) capturé mais jamais
lu dans la portion de code observée.**
Document(s) : `06-api-contracts.md` §5.1, §8 (point 3) ; `04-features.md`,
Contradictions #11.
Référence code : `site/src/Model/EditModel.php:2044-2045` (dispatch), fichier
de 2870+ lignes non intégralement relu.
Niveau : Zone inconnue.
Gravité : faible.
Action : relire l'intégralité d'`EditModel.php` (au-delà des portions
`767-2294`/`2295-2557`/`2727-2859` déjà couvertes) pour confirmer si ce
retour est exploité ailleurs.

**37. Signature exacte et contrat de `onViewport`** (dispatché par le plugin
de contenu `contentbuilderng_verify`, pas par `VerifyModel`) jamais exercé en
pratique — les deux plugins livrés (`Paypal`, `Passthrough`) retournent
systématiquement une chaîne vide.
Document(s) : `06-api-contracts.md` §5.2, §8 (point 4).
Niveau : Zone inconnue.
Gravité : faible (point d'extension défini mais inexploité).
Action : documenter le contrat attendu pour tout développeur tiers souhaitant
personnaliser le rendu de `{CBVerify}` ; aucune urgence corrective.

**38. Signification exacte d'`edit_by_type`**, qui conditionne le dispatch
d'`onAfterSubmit` (`!$data->edit_by_type`).
Document(s) : `06-api-contracts.md` §5.1.
Référence code : `site/src/Model/EditModel.php:2044-2045`.
Niveau : Zone inconnue.
Gravité : faible.
Action : relire le point de définition de `$data->edit_by_type` dans
`EditModel::getData()`/`store()` pour documenter précisément ce drapeau.

**39. Sentinelle exacte de l'échec d'`onVerify`** (`false`/tableau vide) non
tracée en détail au-delà du comportement observé de `Passthrough` (toujours
réputé réussi).
Document(s) : `06-api-contracts.md` §5.2.
Référence code : `admin/src/Model/VerifyModel.php:323`.
Niveau : Hypothèse non vérifiée.
Gravité : faible.
Action : relire `VerifyModel.php:323+` pour confirmer la forme exacte
attendue en cas d'échec.

**40. Ordre exact entre `onBeforeSubmit` et la résolution des champs
spéciaux d'inscription** dans `EditModel::store()`, décrit comme proche dans
la séquence mais non confirmé ligne à ligne sur l'intégralité du fichier.
Document(s) : `05-user-flows.md` §18 (point 1).
Niveau : Zone inconnue.
Gravité : faible.
Action : relecture ciblée si un plugin `contentbuilderng_submit` tiers doit
interagir avec les champs d'inscription.

---

## Règles métier

**41. Décalage de publication d'article (`default_publish_up_days`/
`_down_days`) appliqué uniquement s'il existe déjà un article lié** — pour la
toute première génération d'article, aucun effet observé dans la portion de
code lue ; un administrateur pourrait s'attendre à ce que ce décalage
s'applique dès la création initiale.
Document(s) : `09-business-rules.md` §4.4.
Référence code : `admin/src/Service/ArticleService.php:226-238`, portion
`:62-724` non intégralement relue.
Niveau : Zone inconnue (formulée comme risque d'incohérence, pas un fait
clos).
Gravité : moyenne (peut surprendre un administrateur configurant ce
paramètre en attendant un effet immédiat).
Action : relire l'intégralité de `createArticle()` pour confirmer/infirmer ;
tester en environnement Joomla réel si le paramètre est utilisé en
production.

**42. Aucun vérificateur d'audit spécifique au thème visuel identifié** parmi
les 17 vérificateurs de l'écran About — le repli silencieux sur `thoth`
quand un thème configuré est indisponible n'est donc signalé nulle part à
l'administrateur.
Document(s) : `09-business-rules.md` §4.6.
Niveau : Zone inconnue, à confirmer.
Gravité : faible.
Action : vérifier la liste complète des 17 vérificateurs (`04-features.md`,
`14-traceability.md` #4) pour confirmer l'absence, et évaluer l'ajout d'un
vérificateur dédié si jugé utile.

**43. Aucune garde applicative confirmée empêchant de cocher `date_not_before`
sur un champ `is_group=1`** (configuration incompatible qui bloque
systématiquement toute soumission comportant ce champ).
Document(s) : `09-business-rules.md` §5.2.
Niveau : Zone inconnue (template de l'écran Options d'élément non relu en
détail sur ce point précis).
Gravité : faible à moyenne (piège de configuration silencieux).
Action : vérifier le template `admin/tmpl/elementoptions/default.php` pour
confirmer l'absence de garde, et en ajouter une si absente.

**44. Comportement de `ContentbuilderngHelper::isEmail('')` sur une chaîne
vide non confirmé** — pourrait faire qu'un champ e-mail facultatif laissé
vide « passe » silencieusement la validation `email` même quand elle est
cochée.
Document(s) : `09-business-rules.md` §3.5.
Niveau : Zone inconnue.
Gravité : faible (comportement probablement correct par construction, mais
non vérifié ligne à ligne).
Action : relire `ContentbuilderngHelper::isEmail()` pour confirmer.

**45. Ordre exact de résolution entre `MenuThemeHelper::resolve()` et
`PreviewThemeHelper::apply()`** en cas de conflit réel entre une
prévisualisation admin et une surcharge de thème de menu.
Document(s) : `09-business-rules.md` §9.2.
Niveau : Zone inconnue (le repli final sur `thoth`, lui, est confirmé — seul
l'ordre entre ces deux surcharges spécifiques reste à vérifier).
Gravité : faible.
Action : relecture ciblée de `MenuThemeHelper`/`PreviewThemeHelper` si un cas
de conflit réel est signalé en production.

**46. Déclenchement exact du lien d'activation manuelle d'un compte**
(`verify_by_admin=1&token=`) — hypothèse qu'il provient toujours d'une
notification e-mail à l'administrateur ; le gabarit d'e-mail correspondant
n'a pas été tracé.
Document(s) : `04-features.md` §23 (ligne ~1987) ; `05-user-flows.md` §18
(point 2).
Niveau : Hypothèse non vérifiée.
Gravité : faible.
Action : localiser le gabarit d'e-mail de notification d'inscription en
attente pour confirmer l'unique point d'origine du lien.

**47. Détail exact du rendu HTML/JS de `RatingHelper::getRating()`** au-delà
du HTML inline déjà exposé dans la documentation existante.
Document(s) : `04-features.md` §24.1.
Niveau : Zone inconnue.
Gravité : faible.
Action : relecture ciblée si une évolution du rendu de notation est prévue.

**48. Contenu CSS exact des 4 thèmes visuels**, en particulier si le thème
`dark` implémente réellement `prefers-color-scheme`/`data-bs-theme="dark"` —
seule l'architecture PHP de chargement a été vérifiée, pas le contenu CSS
ligne à ligne. Ce point est explicitement **orthogonal** au mécanisme de
sélection de thème CBNG par vue/menu (à ne pas confondre avec un éventuel
mode sombre global du site).
Document(s) : `04-features.md` #33 (ligne ~2946), Contradictions #12.
Niveau : Zone inconnue.
Gravité : faible.
Action : lire le contenu de `plugins/contentbuilderng_themes/dark/css/*.css`
si une cohérence avec un mode sombre global du site est requise.

**49. Absence de tâche planifiée (`com_scheduler`) pour la synchronisation
articles ↔ enregistrements et pour la fenêtre de publication programmée** —
mécanisme purement « à la demande », déclenché par le trafic réel du site
(`isSyncMutationRequest()`), jamais par un cron ; absence totale d'un
mécanisme alternatif non formellement prouvée (recherche non exhaustive
au-delà des fichiers déjà lus).
Document(s) : `09-business-rules.md` §1.2 ; `04-features.md` #34 (ligne
~3096) ; `05-user-flows.md` §18 (point 3).
Niveau : Zone inconnue (le comportement lui-même est un Fait observé ;
l'absence *totale* d'alternative est la part Zone inconnue).
Gravité : moyenne (impact opérationnel sur les sites à faible trafic —
publication programmée pouvant rester invisible plusieurs heures/jours).
Action : confirmer avec Gilles si ce comportement est acceptable en l'état
ou si une tâche planifiée devrait être ajoutée (`com_scheduler`).

**50. Rollback applicatif de `EditModel::store()` en cas d'échec d'inscription
après écriture du record** — pas de transaction SQL globale identifiée,
seulement un rollback ciblé (`clearDirtyRecordUserData()`) ; un état partiel
(enregistrement CBNG sans compte Joomla associé) n'est pas totalement exclu
sans relecture plus poussée de `admin/src/types/*`.
Document(s) : `04-features.md`, Contradictions #5 ; `05-user-flows.md` §16.1.
Niveau : Zone inconnue.
Gravité : moyenne (risque d'incohérence de données en cas d'échec partiel).
Action : relire `admin/src/types/*` et le chemin complet de `register()`
pour confirmer/infirmer l'existence d'un état partiel possible ; envisager
un test de non-régression ciblé sur ce scénario d'échec.

**51. `PublicformsModel::buildOrderBy()` — tri figé à `ORDER BY ordering`**,
indépendamment de l'état `filter_order`/`filter_order_Dir` calculé et
stocké : garde-fou SQL volontaire (colonne de tri non whitelistée en chaîne
littérale) ou dette de code non nettoyée, non tranché.
Document(s) : `04-features.md`, Contradictions #6 ; `05-user-flows.md` §7
(renvoi), `06-api-contracts.md` §3 (Zone inconnue relayée du brouillon).
Référence code : `site/src/Model/PublicformsModel.php:168-183`.
Niveau : Zone inconnue.
Gravité : faible.
Action : relire `buildOrderBy()` en détail pour déterminer si le tri
dynamique était prévu et jamais branché, ou si c'est un garde-fou
intentionnel ; documenter l'intention dans le code (commentaire) une fois
tranché.

**52. Degré de duplication de logique entre `DatatableService`
(`createForStorage()`/`syncColumnsFromFields()`) et
`StorageModel::syncStorageDataTableOrBytable()`** — proximité fonctionnelle
observée, non recoupée ligne à ligne (`DatatableService.php`, 749 lignes,
non lu intégralement).
Document(s) : `04-features.md`, Contradictions #1.
Niveau : Zone inconnue.
Gravité : faible à moyenne (duplication potentielle de logique DDL
sensible).
Action : lire intégralement `DatatableService.php` et le comparer point par
point à `StorageModel::syncStorageDataTableOrBytable()` pour déterminer s'il
s'agit d'une délégation interne ou d'une vraie duplication à factoriser.

---

## API et contrats externes

**53. `DatatableController::create()`/`sync()` sans contrôle `core.edit` spécifique.**
Voir priorité #3 ci-dessus (repris ici pour la cohérence thématique).
Document(s) : `06-api-contracts.md` §2.1, §8 (point 1) ;
`07-security.md` §1.2.
Niveau : **Fait observé** (`core.manage` via `ComponentAccessTrait` et
`checkToken()` dans les méthodes) + **Zone inconnue** (nécessité d'un
contrôle `core.edit` supplémentaire selon la politique ACL souhaitée).

**54. `StoragesController::copy()` — comportement exact vis-à-vis de la
table physique non tracé en détail.**
Document(s) : `06-api-contracts.md` §2.1, §8 (point 7).
Référence code : `admin/src/Controller/StoragesController.php:96`.
Niveau : Zone inconnue.
Gravité : faible à moyenne (une copie de storage mal comprise pourrait
dupliquer ou omettre des champs/données physiques).
Action : relire `StoragesController::copy()` et le service sous-jacent pour
documenter précisément le comportement de duplication.

**55. Atomicité de `ConfigImportService::applyPayload()` non confirmée** —
aucun rollback transactionnel global identifié pour un import en cours
d'application ; en cas d'échec à mi-chemin, l'état partiellement importé
n'est pas confirmé comme atomique.
Document(s) : `06-api-contracts.md` §6.1, §8 (point 5) ; `04-features.md`,
Contradictions #3 ; `11-execution-flows.md` §6.
Référence code : `admin/src/Service/ConfigImportService.php` (937 lignes,
non lu intégralement).
Niveau : Zone inconnue.
Gravité : moyenne (Config Transfer manipule potentiellement plusieurs vues/
storages en une seule opération).
Action : lire intégralement `ConfigImportService::applyPayload()` pour
confirmer l'atomicité ou son absence ; si absente, envisager une transaction
englobante ou une sauvegarde préalable automatique avant un import
`MODE_REPLACE`.

**56. `default_category` non revalidé côté serveur.** Voir priorité #8
ci-dessus (repris ici pour la cohérence thématique).
Document(s) : `06-api-contracts.md` §8 (point 6) ; `09-business-rules.md`
§5.7 ; `05-user-flows.md` §12.5.

---

## Dette technique

**57. Incohérence de version `AGENTS.md` vs historique Git.** Voir priorité
#7 ci-dessus.
Document(s) : `12-technical-debt.md` §7.
Niveau : **Contradiction non tranchée** entre les instructions du projet et
l'état observé du dépôt.

**58. Aucune garde CI active contre la croissance de `phpstan-baseline.neon`**
— seule `reportUnmatchedIgnoredErrors: false` tolère les entrées obsolètes,
ce qui peut laisser croître silencieusement la baseline sans désaccord
détecté entre environnements local/CI.
Document(s) : `12-technical-debt.md` §1.3, §9.
Niveau : Zone inconnue (absence de garde confirmée ; effet pratique non
mesuré).
Gravité : faible.
Action : implémenter l'étape 4 du chantier A du plan de refactoring existant
(garde CI qui échoue si la baseline grandit), déjà prévue mais non faite.

**59. L'absence de `@deprecated` dans le code ne signifie pas l'absence
d'API interne obsolète** — `MIGRATION.md` mentionne des « compatibilités
runtime legacy » déjà supprimées, suggérant que du code marqué obsolète a pu
exister puis être retiré plutôt que laissé annoté.
Document(s) : `12-technical-debt.md` §3.
Niveau : Zone inconnue (limite méthodologique de la recherche de marqueurs
textuels).
Gravité : faible.
Action : aucune action immédiate ; à garder en tête lors d'un futur audit de
compatibilité ascendante.

**60. Aucun index ajouté a posteriori dans les 17 fichiers de migration
versionnée** — impossible de déterminer si `install.sql` couvre déjà tous
les besoins d'indexation ou si un manque d'index n'a simplement pas encore
été détecté par l'équipe (nécessiterait de lire `install.sql` et le plan de
requêtes réel, hors périmètre de `12-technical-debt.md`).
Document(s) : `12-technical-debt.md` §6.2.
Niveau : Zone inconnue.
Gravité : faible.
Action : croiser avec le point 12 ci-dessus (`list_states` sans index sur
`form_id`) lors d'un futur audit de performance.

**61. Couverture de test réelle par fichier non recoupée avec une exécution
de couverture** — le nombre de tests (689 à 694 selon les étapes du plan)
est cité par `REFACTORING_PLAN.md` mais non vérifié par exécution dans le
cadre de cette rétro-analyse (pas d'environnement PHP exécutable).
Document(s) : `12-technical-debt.md` §10.
Niveau : Zone inconnue (limite méthodologique assumée de toute la
rétro-analyse — voir aussi `01-overview.md` §7).
Gravité : faible.
Action : exécuter la suite de tests avec couverture dans un environnement CI
dédié pour objectiver ce chiffre.

---

## Dépendances externes

**62. Contenu exact et provenance précise du paquet `bgli100/securimage`**
— aucun `vendor/`/`composer.lock` livré dans ce dépôt pour inspection
directe ; le nom suggère un fork tiers de `dapphp/securimage` mais cela
n'est pas confirmé.
Document(s) : `10-dependencies.md` §1.1, §6.
Niveau : Zone inconnue.
Gravité : faible (dépendance de captcha, pas de code métier sensible).
Action : consulter Packagist/GitHub pour confirmer la provenance exacte du
paquet si une revue de sécurité des dépendances tierces est prévue.

**63. Comportement de Joomla core si `plg_editors_codemirror` est
désactivé** — non vérifié, aucun garde-fou défensif (`PluginHelper::isEnabled()`)
trouvé autour des trois appels en dur à `Editor::getInstance('codemirror')`.
Document(s) : `10-dependencies.md` §5.4, §6.
Référence code : `admin/src/Service/TemplateRenderService.php`,
`admin/layouts/form/prepare_editor.php`,
`admin/tmpl/elementoptions/default.php`.
Niveau : Zone inconnue.
Gravité : faible à moyenne (les champs de code des templates pourraient
devenir non fonctionnels si ce plugin d'éditeur Joomla est désactivé).
Action : ajouter un garde défensif (`PluginHelper::isEnabled('editors',
'codemirror')`) avec message d'erreur explicite si absent, ou documenter la
dépendance comme requise.

**64. Contenu complet des e-mails envoyés par ContentBuilder NG lui-même**
au-delà du flux d'activation `com_users` — non examiné dans le document
dépendances (hors périmètre déclaré).
Document(s) : `10-dependencies.md` §2.6, §6.
Niveau : Zone inconnue (hors périmètre assumé du document source).
Gravité : faible.
Action : couvrir ce point dans une future revue centrée sur les
notifications/templates e-mail du composant si nécessaire.

---

## Notes méthodologiques transversales

- Cette synthèse ne réintroduit pas les niveaux de gravité formels (type
  CVSS) : chaque appréciation « élevé/moyen/faible » est qualitative, issue
  de la lecture croisée des 12 documents, et doit être reconfirmée par
  l'équipe produit avant toute priorisation d'un plan d'action correctif.
- Plusieurs points listés ici sont volontairement **regroupés** quand deux
  ou trois documents source décrivaient la même incertitude sous des angles
  différents (ex. points #1/#5, #3/#53, #4, #7/#57, #8/#56) — le tableau de
  priorités en tête de document reste la référence unique pour ces cas.
- Aucun des points ci-dessus n'a été vérifié par exécution réelle du code :
  cette rétro-analyse entière repose sur une lecture statique, sans
  environnement PHP/MySQL/Joomla disponible pour ce travail
  (`01-overview.md` §7). Tout point marqué « à tester en environnement réel »
  reste, à ce stade, une hypothèse de lecture statique, aussi forte soit
  l'indice.
