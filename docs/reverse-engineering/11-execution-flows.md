# 11 — Flux d'exécution (diagrammes)

> Légende : **Fait observé** / **Comportement déduit** / **Hypothèse** /
> **Zone inconnue** — voir `01-overview.md`. Sources : `06-api-contracts.md`
> (signatures d'événements), `03-data-model.md`, `09-business-rules.md`,
> `07-security.md`, `10-dependencies.md`, `02-architecture.md`.
>
> Ces diagrammes simplifient l'ordre réel des appels pour la lisibilité ;
> les numéros de ligne exacts de chaque étape sont dans les documents
> référencés ci-dessus, pas répétés ici.

## 1. Soumission d'un enregistrement front (création/édition), avec vérification

**Fait observé/Comportement déduit**, assemblé depuis `EditController`,
`EditModel::store()` (767-2294), et le contrat `onBeforeSubmit`/`onAfterSubmit`/
`onSetup`/`onForward`/`onVerify` (`06-api-contracts.md` §5.1-5.2).

```mermaid
sequenceDiagram
    actor User as Visiteur
    participant Disp as Dispatcher (site)
    participant EC as EditController
    participant EM as EditModel::store()
    participant FV as FieldValidationService /\nvalidateField()
    participant Captcha as securimage
    participant Submit as Plugins\ncontentbuilderng_submit
    participant DB as MySQL\n(records, list_records,\nusers, registered_users)
    participant Article as ArticleService
    participant Verify as VerifyModel /\nplugins contentbuilderng_verify

    User->>Disp: POST index.php?option=com_contentbuilderng\n&task=edit.save
    Disp->>EC: route vers EditController
    EC->>EM: store()
    EM->>Submit: onBeforeSubmit($form, $data, ...)
    alt plugin bloque
        Submit-->>EM: résultat négatif (effet de bord, non contractuel)
        EM-->>User: erreur / arrêt (comportement déduit, non tracé exhaustivement)
    else suite normale
        EM->>FV: validation champ par champ\n(required, types, dépendances)
        alt validation KO
            FV-->>EM: message(s) d'erreur en file
            EM-->>EC: échec
            EC-->>User: formulaire réaffiché + messages
        else validation OK
            EM->>Captcha: vérification captcha\n(sauf si champ captcha absent de la vue —\nvérification alors silencieusement désactivée,\nvoir 09-business-rules.md §5.2)
            EM->>DB: INSERT/UPDATE records\n(+ list_records, users si inscription)
            EM->>EM: register() — création éventuelle\nd'un compte Joomla + #__contentbuilderng_registered_users
            EM->>Article: génération/synchro article Joomla\n(#__content, #__assets)
            EM->>Verify: onSetup → onForward\n(si vérification requise par la vue)
            alt vérification requise (ex. paiement)
                Verify-->>User: redirection externe\n(ex. formulaire PayPal auto-soumis)
                User->>Verify: retour (verify=1&verification_id=...)
                Verify->>Verify: onVerify → succès/échec
                Verify->>DB: UPDATE verifications /\nusers.verified_*
            end
            EM->>Submit: onAfterSubmit($form, $data, $record_id, ...)
            EM-->>EC: succès
            EC-->>User: redirection / message de succès
        end
    end
```

**Zone inconnue** (`06-api-contracts.md` §7) : le point d'import exact de
`onAfterArticleCreation` (groupe `contentbuilderng_listaction`) dans cette
séquence n'a pas été localisé avec certitude — il s'articule très
probablement entre la génération d'article et la fin de `store()`, sans
confirmation ligne à ligne.

## 2. Rendu `{CBList}` dans un article Joomla tiers

**Fait observé/Comportement déduit**, assemblé depuis
`plugins/content/contentbuilderng_cblist/`, `docs/specifications/cblist.md`
(vérifié contre le code), `03-data-model.md`, `04-features.md`.

```mermaid
sequenceDiagram
    actor Visitor as Visiteur
    participant Joomla as Joomla (com_content ou autre)
    participant Event as onContentPrepare
    participant Plg as contentbuilderng_cblist
    participant Parse as Parseur de tag {CBList id=... ...}
    participant Model as ListModel / ContentCardService
    participant DB as #__contentbuilderng_records\n+ list_records + storage physique

    Visitor->>Joomla: affichage d'un article contenant {CBList id=15 ...}
    Joomla->>Event: dispatch onContentPrepare(article)
    Event->>Plg: contenu de l'article
    Plg->>Parse: détection et extraction des tags {CBList ...}
    Parse->>Plg: paramètres (id, fields, sort, limit,\ncard, w, labels, actions...)
    Plg->>Model: résolution de la vue (form_id=15) + requête
    Model->>DB: SELECT filtré/trié/paginé
    DB-->>Model: lignes
    Model-->>Plg: enregistrements + métadonnées de pagination
    Plg->>Plg: rendu HTML (liste, cartes h1-h6/v1-v6\nselon card=, ou output=value)
    Plg-->>Event: contenu article substitué (tag → HTML)
    Event-->>Joomla: article final
    Joomla-->>Visitor: page rendue
```

**Fait observé** (`06-api-contracts.md` §7) : `{CBStats}` suit un chemin
distinct mais parallèle — il existe aussi un point d'entrée HTTP direct
(`ApiController::display()` avec `action=cbstats`) qui partage le même
moteur de calcul et les mêmes fichiers `.ini` de titleset/groupset que le
plugin de contenu, mais reste un point d'entrée séparé (pas de dispatch via
`onContentPrepare` pour cet appel direct).

## 3. Installation / mise à jour / désinstallation

**Fait observé** (`script.php`, `com_contentbuilderng.xml`,
`12-technical-debt.md`).

```mermaid
flowchart TD
    A[Installeur Joomla] -->|extension.installer=install| B[script.php\npreflight]
    B --> C{SQL install.sql\nexécuté par Joomla}
    C --> D[script.php::install]
    D --> E[Création menu admin\nCOM_CONTENTBUILDERNG /\nforms, storages, about]
    D --> F[Vérification/réparation\nde schéma ensure*]
    D --> G[Enregistrement des 17 plugins\n+ activation]

    H[Installeur Joomla] -->|extension.installer=update| I[SQL updates/mysql/*.sql\nversion par version]
    I --> J[script.php::update]
    J --> F

    K[Installeur Joomla] -->|extension.installer=uninstall| L[SQL uninstall.sql]
    L --> M[script.php::uninstall]
    M --> N[Suppression tables\n#__contentbuilderng_*]
    N -.non supprimé.-> O[Tables dynamiques #__&lt;storage&gt;\net articles/#__content/#__assets\ngénérés restent en base\n— désinstallation non purgative]
```

**Fait observé** (`03-data-model.md` §"Zones d'incertitude") : la
désinstallation ne purge ni les tables dynamiques par storage, ni les
articles Joomla générés — conception assumée, pas un oubli documenté comme
tel dans le code lu.

## 4. Audit & Réparation (écran About)

**Fait observé** (`04-features.md`, `06-api-contracts.md` §4.5) : 17
vérificateurs d'intégrité, réparation guidée en 17 étapes, exposée via des
tâches AJAX `about.repair*` (indicateur `cb_ajax=1`, pas de `com_ajax`
Joomla).

```mermaid
flowchart LR
    A[Admin ouvre view=about] --> B[AboutController::display]
    B --> C[Exécution des 17 vérificateurs\nAudit*Helper]
    C --> D{Anomalie détectée ?}
    D -- non --> E[Statut OK affiché]
    D -- oui --> F[Item de la liste d'audit\navec action 'Réparer']
    F --> G[Admin clique Réparer]
    G --> H[task=about.repairAuditIssue\n+ 12 autres tâches repair* dédiées\ncb_ajax=1]
    H --> I[Application du correctif ciblé\nen base / fichier]
    I --> J[Réponse JSON\nstatut + message]
    J --> C
```

## 5. Vérification par paiement PayPal

**Fait observé** (`10-dependencies.md` §3, `07-security.md` §10,
`06-api-contracts.md` §5.2) : protocole legacy Website Payments Standard.

```mermaid
sequenceDiagram
    actor User as Visiteur
    participant VM as VerifyModel
    participant Plg as plugin paypal\n(onSetup/onForward/onVerify)
    participant PP as PayPal\n(cgi-bin/webscr)

    User->>VM: soumission déclenchant une vérification\n(verification requise par la vue)
    VM->>Plg: onSetup()
    Plg-->>VM: '' (config valide) ou message d'erreur bloquant
    VM->>Plg: onForward()
    Plg-->>User: formulaire HTML auto-soumis\n(_xclick, business, amount...)
    User->>PP: redirection navigateur vers PayPal
    PP-->>User: paiement + retour vers le site\n(verify=1&verification_id=...)
    User->>VM: retour avec verification_id
    VM->>Plg: onVerify()
    Plg->>PP: requête synchrone (curl, SSL_VERIFYPEER=false)\n_notify-synch (PDT) puis repli fsockopen\n_notify-validate (IPN) en clair
    PP-->>Plg: réponse SUCCESS/FAIL
    Plg-->>VM: {msg, is_test, data} ou échec
    VM->>VM: UPDATE #__contentbuilderng_verifications\n+ users.verified_*
    VM-->>User: page de confirmation / échec
```

**Fait observé** : `CURLOPT_SSL_VERIFYPEER=false` sur les deux appels
sortants — documenté comme observation de sécurité dans `07-security.md`,
non corrigé (hors périmètre de cette rétro-analyse).

## 6. Export / Import de configuration (Config Transfer)

**Fait observé** (`06-api-contracts.md` §6.1, `04-features.md`).

```mermaid
flowchart LR
    subgraph Export
        A[Admin : view=configtransfer\ntask=export] --> B[ConfigExportService::buildPayload]
        B --> C[JSON cbng-config-export-v1\nstorages+fields+forms+elements+titlesets]
        C --> D[Téléchargement fichier]
    end
    subgraph Import
        E[Admin : upload JSON] --> F[ConfigImportService::applyPayload]
        F --> G{merge ou replace}
        G -- merge --> H[Upsert sélectif]
        G -- replace --> I[Remplacement des entités importées]
        H --> J[(#__contentbuilderng_storages/\nstorage_fields/forms/elements)]
        I --> J
    end
```

**Zone inconnue** (`06-api-contracts.md` §8) : l'atomicité de
`ConfigImportService::applyPayload()` (comportement en cas d'échec partiel)
n'a pas été tracée avec certitude.
