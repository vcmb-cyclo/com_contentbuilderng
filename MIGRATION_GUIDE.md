# Guide de migration administrateur

Ce guide décrit la migration d'un ancien site ContentBuilder vers ContentBuilder NG
sur Joomla 6.

La migration principale est automatique : l'installation ou la mise à jour du paquet
ContentBuilder NG détecte les anciennes structures, renomme les tables, adapte le
schéma et normalise les références connues. Les commandes SQL de ce document servent
uniquement au diagnostic ou à la reprise après échec.

## Périmètre supporté

- Joomla 6.0 ou supérieur.
- PHP 8.3 ou supérieur.
- MySQL ou MariaDB, comme prévu par le manifeste du composant.
- Migration depuis le nom historique `contentbuilder`.
- Migration d'une version antérieure de `com_contentbuilderng`.

Une migration directe sur le site de production est déconseillée. Effectuez d'abord
la procédure sur une copie complète du site.

## Ordre de migration du site

ContentBuilder NG migre ContentBuilder, mais ne migre pas le cœur Joomla.

Si le site source utilise une version antérieure à Joomla 6 :

1. clonez le site et sa base de données ;
2. désactivez les anciens plugins ContentBuilder, en particulier le plugin système,
   avant la mise à niveau de Joomla ;
3. effectuez la migration Joomla vers la version 6 selon la procédure Joomla ;
4. conservez les anciennes tables ContentBuilder, même si l'ancien composant ne
   fonctionne plus sous Joomla 6 ;
5. migrez BreezingForms vers une version compatible Joomla 6 si des vues y sont liées ;
6. installez ContentBuilder NG : son installateur reprend alors les tables et
   configurations historiques.

Il n'est pas nécessaire que l'ancien ContentBuilder puisse s'exécuter sous Joomla 6.
En revanche, ses tables ne doivent pas avoir été supprimées avant l'installation de
ContentBuilder NG.

## Ce qui est migré automatiquement

Pendant l'installation ou la mise à jour, ContentBuilder NG :

1. vérifie les versions de Joomla et PHP ainsi que l'écriture des fichiers existants ;
2. désactive les anciens plugins ContentBuilder avant de charger le nouveau code ;
3. détecte et renomme les tables `#__contentbuilder_*` en
   `#__contentbuilderng_*` ;
4. migre les entrées du composant dans `#__extensions`, les assets et les liens de
   menus vers `com_contentbuilderng` ;
5. normalise les anciennes valeurs de type stockées dans les tables ContentBuilder ;
6. adapte les colonnes, dates, valeurs par défaut, index et colonnes d'audit attendus ;
7. installe ou actualise les plugins livrés avec le paquet et désactive leurs
   équivalents historiques ;
8. remplace les thèmes non pris en charge par le thème Thoth ;
9. nettoie les doublons connus, anciens fichiers de langue, menus obsolètes et caches ;
10. conserve les données, vues, éléments, enregistrements, stockages et associations
    d'articles présents dans les tables migrées.

L'installateur ne fusionne jamais automatiquement deux tables contenant toutes les
deux des données. Ce cas est signalé dans le journal et doit être analysé manuellement.

## Avant la migration

### 1. Préparer une fenêtre de maintenance

- Prévenez les utilisateurs et empêchez les nouvelles saisies.
- Désactivez les tâches planifiées, imports et automatisations qui écrivent dans
  ContentBuilder ou BreezingForms.
- Notez la version actuelle de Joomla, PHP, ContentBuilder et BreezingForms.
- Téléchargez uniquement un paquet ContentBuilder NG complet correspondant à Joomla 6.

### 2. Vérifier les prérequis

- Joomla est déjà migré en version 6.
- PHP est en version 8.1 ou supérieure.
- L'utilisateur SQL peut exécuter `ALTER`, `CREATE`, `DROP`, `INDEX`, `UPDATE` et
  `RENAME TABLE`.
- Les répertoires du composant, des médias et des plugins sont modifiables par Joomla.
- L'espace disque permet de conserver une sauvegarde complète et de reconstruire les
  tables lors d'une conversion de collation.

### 3. Sauvegarder la base et les fichiers

Utilisez de préférence l'outil de sauvegarde habituel du site. À défaut, les commandes
suivantes donnent un exemple à adapter :

```bash
mysqldump --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 \
  -h DB_HOST -u DB_USER -p DB_NAME > before-contentbuilderng.sql

tar -czf /chemin/sur/before-contentbuilderng-files.tar.gz \
  -C /chemin/vers/joomla .
```

Vérifiez que les archives ne sont pas vides et qu'elles peuvent être lues. Une
sauvegarde non testée ne constitue pas un rollback fiable.

### 4. Relever l'état initial

Remplacez `<prefix>` par le préfixe Joomla réel, par exemple `abc_`.

```sql
SELECT TABLE_NAME, TABLE_ROWS, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE '<prefix>contentbuilder%'
ORDER BY TABLE_NAME;
```

Vérifiez également les entrées de composant :

```sql
SELECT extension_id, name, element, enabled
FROM <prefix>extensions
WHERE type = 'component'
  AND element IN (
    'contentbuilder',
    'com_contentbuilder',
    'com_contentbuilderng'
  )
ORDER BY extension_id;
```

Ces requêtes sont en lecture seule. Conservez leur résultat avec la sauvegarde.

## Exécuter la migration

1. Activez le mode hors ligne du site.
2. Ouvrez **Système > Installation > Extensions** dans Joomla.
3. Installez le paquet complet ContentBuilder NG par-dessus l'installation existante.
4. Ne désinstallez pas l'ancien composant avant cette opération : la désinstallation
   peut exécuter du SQL destructif et supprimer le contexte nécessaire à la migration.
5. Attendez le message final de l'installateur. Ne rechargez pas la page pendant les
   renommages ou modifications de tables.
6. Ouvrez **Composants > ContentBuilder NG > À propos**.
7. Consultez le journal `com_contentbuilderng.log`.

Le journal se trouve dans le chemin des logs configuré par Joomla. À défaut, il est
écrit dans le répertoire `logs` à la racine du site. Les lignes `[WARNING]` doivent
être examinées ; une ligne `[ERROR]` impose de suspendre la remise en production.

## Audit et réparation après installation

Dans **Composants > ContentBuilder NG > À propos** :

1. lancez **Audit** ;
2. enregistrez ou capturez le rapport ;
3. si des problèmes réparables sont détectés, lancez **REPAIR DB** ;
4. examinez chaque étape et appliquez uniquement les corrections proposées ;
5. relancez **Audit** jusqu'à obtenir un rapport sans erreur bloquante.

REPAIR DB peut notamment :

- supprimer les index dupliqués ;
- renommer les tables historiques restantes ;
- convertir les collations ;
- migrer les anciennes données compactées vers leur format actuel ;
- ajouter les colonnes d'audit manquantes ;
- supprimer les doublons de plugins ;
- normaliser les anciens titres de menus ;
- synchroniser les champs des vues liées à BreezingForms ;
- réparer certaines références d'éléments et associations d'articles ;
- supprimer les anciens fichiers de langue.

Certaines vérifications restent volontairement diagnostiques, notamment les menus
pointant vers une vue invalide et les permissions frontend incohérentes. Elles doivent
être corrigées dans l'administration après lecture du rapport.

La conversion de collation peut verrouiller ou reconstruire des tables. Exécutez
REPAIR DB hors période de charge et uniquement après sauvegarde.

## Checklist fonctionnelle

Avant de rouvrir le site, contrôlez au minimum :

- la liste des vues et des stockages dans l'administration ;
- une liste frontend avec pagination, tri, recherche et filtres ;
- l'ouverture d'une fiche détail ;
- la création et la modification d'un enregistrement ;
- la publication, dépublication et suppression selon les rôles ;
- les vues liées à BreezingForms et leurs champs ;
- les menus Joomla pointant vers ContentBuilder NG ;
- la création ou mise à jour d'articles Joomla, si utilisée ;
- les exports, imports et pièces jointes ;
- les plugins de validation, vérification, contenu et actions de liste ;
- les appels API et intégrations externes ;
- les journaux PHP, Joomla et `com_contentbuilderng.log`.

Testez avec au moins un administrateur, un utilisateur authentifié standard et un
visiteur non authentifié lorsque la vue est publique.

## Adaptations manuelles attendues

La base et les menus Joomla connus sont migrés automatiquement. En revanche,
l'installateur ne peut pas réécrire du code personnalisé stocké dans des articles,
modules, templates, scripts JavaScript ou applications externes.

### Anciennes URLs du composant

Recherchez les références suivantes dans vos personnalisations :

```text
option=com_contentbuilder
```

La cible actuelle est :

```text
option=com_contentbuilderng
```

### Ancien endpoint AJAX

L'ancien endpoint n'existe plus :

```text
index.php?option=com_contentbuilder&task=ajax.display
```

Utilisez l'API JSON :

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=rating
index.php?option=com_contentbuilderng&task=api.display&format=json&action=get-unique-values
index.php?option=com_contentbuilderng&task=api.display&format=json&action=stats&id=25
```

Les permissions API de chaque vue et les champs autorisés doivent être configurés.

### Plugins et templates personnalisés

Les anciens plugins fournis par ContentBuilder sont désactivés. Tout plugin ou template
personnalisé doit être porté vers les namespaces et APIs natives de Joomla 6. Ne
réactivez pas un ancien plugin uniquement pour faire disparaître une erreur.

## Cas particulier : collision de tables

Une collision existe lorsqu'une table historique et sa table NG cible sont présentes.
L'installateur applique les règles suivantes :

- source historique vide : elle peut être supprimée ;
- cible NG vide : elle peut être remplacée par la source historique ;
- source et cible contenant des données : aucune fusion, aucun écrasement.

Exemple de diagnostic exact :

```sql
SELECT COUNT(*) AS legacy_rows FROM <prefix>contentbuilder_forms;
SELECT COUNT(*) AS ng_rows FROM <prefix>contentbuilderng_forms;
```

Répétez le contrôle pour la paire signalée dans le journal. Si les deux résultats sont
supérieurs à zéro, n'exécutez pas de `RENAME TABLE` et ne concaténez pas les lignes :
les identifiants et relations entre tables doivent être rapprochés ensemble.

Si la table NG est confirmée vide, qu'une sauvegarde vient d'être validée et que
l'installateur n'a pas pu terminer l'opération, la reprise manuelle minimale est :

```sql
DROP TABLE <prefix>contentbuilderng_forms;
RENAME TABLE <prefix>contentbuilder_forms
  TO <prefix>contentbuilderng_forms;
```

Adaptez uniquement les deux noms à la paire signalée. Les commandes DDL provoquent un
commit implicite sous MySQL/MariaDB : un `ROLLBACK` SQL ne les annulera pas. Après une
intervention manuelle, réinstallez le paquet, puis relancez Audit et REPAIR DB.

## Rollback

Le rollback fiable consiste à restaurer la base **et** les fichiers pris au même
instant. Désinstaller ContentBuilder NG n'est pas un rollback.

1. Maintenez le site hors ligne.
2. Conservez une copie des logs de migration pour l'analyse.
3. Restaurez la base sauvegardée.
4. Restaurez les fichiers sauvegardés.
5. Purgez les caches Joomla, PHP OPcache et les caches externes éventuels.
6. Vérifiez l'ancienne version avant de rouvrir le site.

Exemple de restauration à adapter :

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < before-contentbuilderng.sql
tar -xzf before-contentbuilderng-files.tar.gz -C /chemin/vers/joomla
```

Ne restaurez jamais uniquement les anciennes tables ContentBuilder dans un site dont
les fichiers et plugins sont déjà en version NG : les schémas et le code doivent rester
cohérents.

## Pièges connus

- **Tables anciennes et NG toutes deux remplies** : migration volontairement bloquée
  pour éviter une perte de données.
- **Code personnalisé** : les URLs et appels AJAX écrits hors des menus Joomla ne sont
  pas réécrits automatiquement.
- **Plugins historiques** : ils sont désactivés, pas convertis en plugins Joomla 6.
- **Thèmes historiques** : une référence non prise en charge peut être remplacée par
  le thème `thoth`.
- **Permissions de fichiers ou SQL insuffisantes** : l'installation peut copier une
  partie des fichiers sans terminer toutes les adaptations.
- **BreezingForms absent ou formulaire source supprimé** : les vues liées ne peuvent
  pas être synchronisées correctement.
- **Collations volumineuses** : REPAIR DB peut verrouiller les tables pendant leur
  reconstruction.
- **Menus ou permissions incohérents** : l'audit les signale mais ne choisit pas à la
  place de l'administrateur la vue ou la règle métier correcte.
- **Caches externes** : CDN, reverse proxy et cache serveur peuvent conserver des URLs
  ou du JavaScript antérieurs à la migration.

## Critères de validation

La migration peut être considérée comme terminée lorsque :

- l'installation ne contient aucune erreur critique ;
- aucune collision de tables remplies n'est en attente ;
- Audit ne signale plus d'erreur structurelle bloquante ;
- les étapes utiles de REPAIR DB ont été appliquées ;
- les contrôles fonctionnels réussissent avec les profils utilisateurs attendus ;
- les intégrations personnalisées utilisent `com_contentbuilderng` et la nouvelle API ;
- une nouvelle sauvegarde complète du site migré a été créée.
