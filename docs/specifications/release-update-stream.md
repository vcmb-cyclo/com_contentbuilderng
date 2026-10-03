# ContentBuilder NG — publication et flux Joomla Update

## Périmètre

- Joomla 6 uniquement ;
- paquets RC locaux ou installés manuellement ;
- branches et pull requests fusionnées dans `main` ;
- releases GitHub et manifeste `com_contentbuilderng_update.xml`.

## Principe

### Release 6.1.22.1

Exception CI autorisée par Gilles le 3 octobre 2026 :
`GHSA-vfj7-8cjw-p6xm` (`braces`, sans version corrigée disponible).
Le contrôle npm accepte uniquement cet avis et ses dépendances transitives,
uniquement lorsque tous les chemins concernés sont des dépendances de
développement. Tout autre avis de sévérité moderate/high/critical et toute
erreur d'audit restent bloquants. Le lint CSS reste obligatoire.
Ces dépendances sont absentes du ZIP Joomla. Retirer l'exception dès qu'une
version corrigée ou un remplacement validé de l'outillage est disponible.
L'exception ne constitue pas une correction de la vulnérabilité upstream.

- Les panneaux Edit ne commandent plus les outils List View : recherche,
  filtres, actions, export et lignes par page gardent leurs réglages propres.
- En Détail, le préfixe du titre hérite de la Vue lorsque le menu utilise
  la valeur par défaut ; les valeurs explicites Yes/No gardent leur priorité.
- Le correctif est validé par Gilles en production sous Joomla 6.1.4 / PHP 8.4.
  PHP 8.3 reste supporté ; aucune contrainte minimale n'est modifiée.
- Les 46 tests ciblés passent sous PHP 8.3 et 8.4 (896 assertions par version).
- Le composant, les assets et les 17 plugins portent la version stable 6.1.22.1.
  Le marqueur SQL n'effectue aucune modification structurelle.
- Le flux Joomla Update est publié par le workflow après disponibilité du ZIP
  officiel et validation des contrôles de release.

### Release 6.1.21

- Les Cards CBStats et CBList conservent leurs largeurs de grille lorsqu'un
  éditeur tel que JCE les enveloppe dans un élément `span`.
- Dans les options du menu Joomla List View, Excel export, le mode du nom de
  fichier XLS et le nom personnalisé conditionnel occupent une même ligne
  responsive.
- Le provisionnement administrateur d'un formulaire depuis un Storage attribue
  List, View et Create aux groupes non-Guest, conserve Guest en lecture seule,
  laisse Edit désactivé et complète uniquement les groupes absents sans écraser
  les permissions existantes.
- L'ajout inline d'un champ Storage exige le droit d'édition et transmet
  explicitement les valeurs de la ligne courante afin d'éviter la réutilisation
  de données obsolètes.
- PhpSpreadsheet est mis à jour de 5.9.0 à 5.10.0 et les spécifications internes
  de rétro-ingénierie couvrent désormais l'architecture, les données, les flux,
  la sécurité, les règles métier, les dépendances et la traçabilité.
- Les versions du composant, des assets et des 17 plugins livrés sont
  synchronisées sur `6.1.21` pour la publication finale.

### Release 6.1.19

- La release finale reprend la RC7 validée : nom personnalisable et sécurisé
  des exports XLSX avec l'heure historique `HHMM`, durcissement de l'API JSON et
  de son contrat OpenAPI, contrôles Joomla Database Maintenance, suppression du
  plugin Ping retiré et correction du tableau **About > Plugins**.
- Les versions du composant, des assets et des 17 plugins livrés sont
  synchronisées sur `6.1.19` pour la publication finale.
- Le manifeste Joomla Update reste sur `6.1.18` jusqu'à la création et à la
  validation du ZIP GitHub par le workflow de publication.

### Développement 6.1.19-RC4

- Le dernier fichier de `admin/sql/updates/mysql` porte exactement la version
  du manifeste du composant, même lorsqu'aucune modification structurelle de la
  base n'est nécessaire.
- Après installation ou mise à jour, la **Version de la base de données** affichée
  par Joomla doit correspondre à la **Version du manifeste**.
- Le validateur du ZIP et le smoke test Joomla refusent désormais tout paquet
  dont ces deux versions diffèrent.
- Les versions des plugins restent en `6.1.18`, leurs fichiers fonctionnels
  n'étant pas modifiés pour cette RC.

### Développement 6.1.19-RC1

- La RC1 installe et actualise le manifeste du composant sous le nom canonique
  `administrator/components/com_contentbuilderng/contentbuilderng.xml` attendu
  par Joomla 6.1.3.
- L'écran **Système > Maintenance > Base de données** doit résoudre la version
  installée sans avertissement PHP et déclarer les structures à jour.
- Le smoke test utilise Joomla 6.1.3 et exécute explicitement la maintenance de
  la base après le parcours installation puis mise à jour.
- Les versions des plugins restent en `6.1.18`, leurs fichiers fonctionnels
  n'étant pas modifiés pour cette RC.

### Release 6.1.18

- La release finale reprend la RC1 validée et corrige l'export XLSX des menus Joomla
  List View filtrés et réorganisés.
- L'export conserve simultanément les filtres fixes du menu et les filtres frontend
  ou externes actifs, le tri effectif, la limite totale et l'ordre des colonnes Export
  propre au menu.
- Les migrations historiques de modification de colonnes utilisent la forme SQL
  comprise par le vérificateur de schéma Joomla 6.1.3 et une instruction `ALTER`
  distincte par colonne. L'écran **Système > Maintenance > Base de données** ne
  doit plus produire de requête de contrôle invalide sur MariaDB.
- Les versions du composant, des assets et de tous les plugins livrés sont
  synchronisées sur `6.1.18` pour la publication finale.
- Le manifeste Joomla Update reste sur `6.1.17` jusqu'à la création et à la
  validation du ZIP GitHub par le workflow de publication.

### Développement 6.1.18-RC1

- La correction est développée sur `gil_6.1.18` à partir du `main` publié en
  `6.1.17`.
- La RC1 fait exporter la List View effective du menu au lieu de reconstruire le
  fichier selon la seule Vue mère.
- Les versions des plugins restent en `6.1.17` pendant la RC, leurs fichiers
  fonctionnels n'ayant pas été modifiés.

### Release 6.1.17

- La release finale reprend la RC2 validée : le réglage **Type de tri/export**
  dispose de sa propre colonne, masquée par défaut, et le Label retrouve une
  présentation compacte sans roue ni panneau extensible.
- L'alignement de la colonne Export est homogène avec les autres capacités.
- PHP 8.3 et PHP 8.4 sont tous deux contrôlés avant publication par la syntaxe
  et la suite PHPUnit. Le packaging et le smoke test Joomla 6 s'exécutent sous
  PHP 8.4. PHP 8.5 reste une cible expérimentale de compatibilité CI.
- Les versions du composant, des assets et de tous les plugins livrés sont
  synchronisées sur `6.1.17` pour la publication finale.

### Développement 6.1.17-RC2

- La RC2 remplace la roue intégrée au Label par une colonne indépendante
  **Type de tri/export**, masquée par défaut comme **Retour**.
- Le Label retrouve sa mise en page normale ; afficher la colonne avancée donne
  directement accès au sélecteur de type sans pictogramme intermédiaire.
- Les versions des plugins restent en `6.1.16`, leurs fichiers n'étant pas
  modifiés dans cette RC.

### Développement 6.1.17-RC1

- La correction repart du `main` publié en `6.1.16` sur la branche
  `gil_6.1.17`.
- Elle compacte la roue du type de tri avancé, maintient le libellé et son
  contrôle fermé sur une seule ligne et aligne la colonne Export comme les
  autres capacités.
- Les versions des plugins restent en `6.1.16`, leurs fichiers n'étant pas
  modifiés dans cette RC.

### État validé au 8 septembre 2026

- La version stable `6.1.15` est publiée sous le tag `v6.1.15`, avec son ZIP
  officiel et le manifeste Joomla Update correspondant. La PR #132 est fusionnée.
- La version stable `6.1.16` reprend la RC04 validée. Sa publication, son tag et
  son annonce Joomla Update sont réalisés exclusivement par le workflow de
  release après fusion de `gil_6.1.16` dans `main`.
- `6.1.16` regroupe les réglages du menu Joomla List View, la sélection
  Export indépendante, les infobulles sans pictogramme, les actions frontend
  neutres, l'export XLSX typé par colonne et la matrice PHP de préparation de
  release.
- Les trois appels inutiles à `ReflectionMethod::setAccessible()` ont été
  supprimés des tests pour PHP 8.5 : 988 tests et 5 445 assertions passent sans
  dépréciation sur la base reprise pour 6.1.16.
- PHP 8.3 et PHP 8.4 sont les cibles de production supportées. PHP 8.4 est la
  cible principale de développement, de couverture, de packaging et du smoke
  test Joomla. PHP 8.5 est testé par la syntaxe et PHPUnit mais reste
  expérimental tant que le smoke test complet sur une image Joomla épinglée
  PHP 8.5 n'est pas disponible.
- La sortie des fichiers de langue FR et DE du paquet principal est prévue
  pour 6.2.0. Le conditionnement, l’installation et les mises à jour des langues
  restent à spécifier avant toute modification des paquets.

Après publication réussie, une branche de release peut être supprimée localement
et sur GitHub uniquement après vérification de son intégration complète dans
`origin/main`. Le tag et la release sont conservés. Les développements suivants
restent sur leur branche `gil_<version>` ; la branche locale `main` peut être
synchronisée sans y effectuer de développement.

La version du code et la version proposée par Joomla Update sont deux états
distincts. Une RC peut être préparée, testée, installée manuellement et fusionnée
dans `main` sans être publiée comme release GitHub.

`com_contentbuilderng_update.xml` doit toujours annoncer la dernière version
réellement publiée, dont le tag, la release GitHub et le ZIP installable existent.
Il ne doit jamais pointer vers une RC locale, une branche, une pull request ou un
artefact qui n'est pas disponible dans les releases GitHub.

## Préparation d'une RC non publiée

Les fichiers suivants portent la version RC :

- `com_contentbuilderng.xml` ;
- `media/joomla.asset.json` ;
- `CHANGELOG.md` ;
- `com_contentbuilderng_changelog.xml`.

La version de `media/joomla.asset.json` doit correspondre à celle du manifeste
du composant, notamment lorsque le CSS change. Elle permet aux URL des
ressources de changer de version pour éviter la réutilisation du CSS précédent
depuis le cache du navigateur.

Le fichier `com_contentbuilderng_update.xml` reste inchangé et continue de
référencer la dernière release publiée. Le ZIP local n'inclut pas ce manifeste
de mise à jour.

Cette règle s'applique aussi lorsqu'une RC est installée manuellement sur un site
de production : cette installation ne doit pas annoncer la RC aux autres sites.

## Publication

### Contenu du changelog final

Le changelog d'une version stable est rédigé à partir du diff complet entre le
tag stable précédent et le commit candidat, et non à partir du seul commit de
promotion de la dernière RC. Il agrège les effets fonctionnels de toutes les RC,
ainsi que les corrections fusionnées directement pendant le cycle.

Chaque entrée finale doit :

- s'adresser d'abord à l'utilisateur ou à l'administrateur Joomla et décrire en
  langage courant le problème corrigé ou l'amélioration observable ;
- utiliser des catégories explicites telles que **Fixed**, **Changed** ou
  **Security**, sans imposer au lecteur de connaître les RC, commits, branches,
  classes PHP ou noms de dépendances internes ;
- couvrir séparément les corrections, changements fonctionnels et incidences de
  sécurité présents dans le diff ; une dépendance de production n'est citée que
  par son bénéfice concret lorsqu'il est utile à l'administrateur ;
- exclure des notes publiques la promotion d'une RC, la synchronisation des
  numéros de version, les changements purement CI, les dépendances de
  développement et la documentation interne ; ces opérations restent contrôlées
  dans la PR et la spécification de release ;
- rester sémantiquement identique dans `CHANGELOG.md`,
  `com_contentbuilderng_changelog.xml`, la PR de release et les notes GitHub ;
- être vérifié pendant la revue par comparaison avec le journal des commits et
  le diff du tag stable précédent.

Une formulation générique telle que « Promote the validated release candidate
fixes » n'est pas une entrée finale suffisante : les corrections validées doivent
être nommées explicitement. « Synchronize all shipped plugin versions » n'est pas
non plus une amélioration destinée à l'utilisateur et reste hors du changelog
public. Les changements internes sont identifiés pendant la revue afin que leur
omission des notes publiques soit volontaire.

Avant publication, la revue doit pouvoir répondre oui aux quatre questions
suivantes :

1. Un administrateur comprend-il immédiatement ce qui a été corrigé ?
2. Chaque correction fonctionnelle du cycle apparaît-elle une seule fois ?
3. Les détails de fabrication de la release ont-ils été retirés ?
4. Les textes Markdown, XML Joomla et GitHub donnent-ils la même information ?

Avant l'ouverture de la PR, une revue locale vérifie le diff complet par rapport
à `origin/main`, la cohérence des versions, les traductions, la documentation,
les fichiers distribués et l'absence de régression identifiable. Après
l'ouverture de la PR, la revue est répétée sur le diff GitHub et complétée par
les contrôles automatiques. La fusion et la publication ne sont autorisées que
si la revue ne contient aucun constat bloquant et si tous les contrôles requis
sont verts.

Le manifeste de mise à jour peut être modifié seulement après que la release
GitHub et son ZIP installable sont disponibles. Le workflow publie d'abord la
release, vérifie son succès, puis met automatiquement à jour et commit le
manifeste. Celui-ci doit alors contenir :

- la version publiée exacte ;
- l'URL du ZIP attaché à cette release ;
- le checksum SHA-256 produit et validé par le workflow de publication.

La préparation de la release ne modifie donc pas manuellement le manifeste en
avance.

Un merge dans `main` ne constitue pas une publication. Aucun tag, aucune release
et aucune modification du flux Joomla Update ne sont créés pour une simple PR.

## Critères d'acceptation

1. Une URL de téléchargement du manifeste correspond toujours à un ZIP GitHub
   existant et installable.
2. Une RC non publiée ne déclenche aucune proposition Joomla Update.
3. Une installation manuelle en production n'altère pas le flux de mise à jour.
4. La publication officielle met à jour le manifeste uniquement après la
   création et la validation de la release.
