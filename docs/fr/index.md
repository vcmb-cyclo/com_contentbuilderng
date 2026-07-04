# Documentation utilisateur de ContentBuilder NG

[English documentation](../en/index.md)

ContentBuilder NG est un composant Joomla 6 permettant de construire des applications
de données configurables : formulaires, listes, fiches détail, édition, permissions,
articles Joomla, notifications, exports et API JSON.

Le projet est issu de la migration et de la modernisation de l'ancien ContentBuilder
de Crosstec. Il s'agit d'un projet communautaire, distinct du produit historique et
fourni sans garantie.

> ℹ️ **Note :** cette documentation décrit la version `6.1.7-RC73` du composant
> (champ `<version>` du fichier `com_contentbuilderng.xml`). Les écrans et options
> peuvent évoluer d'une version à l'autre.

## Public concerné

Cette documentation s'adresse principalement :

- aux administrateurs de sites Joomla 6 ;
- aux intégrateurs qui configurent des vues et des menus ;
- aux responsables de migration depuis ContentBuilder ;
- aux utilisateurs avancés qui personnalisent les templates ;
- aux équipes qui consomment l'API JSON.

La documentation n'exige pas de connaître PHP pour les opérations courantes.
La personnalisation des templates et le diagnostic avancé demandent toutefois des
compétences Joomla, HTML et PHP.

## Prérequis

- Joomla 6.0 ou supérieur (le composant est testé sous Joomla 6.x, avec ou sans le
  plugin de compatibilité ascendante). Joomla 5.4.x devrait fonctionner mais n'est
  pas testé — *à vérifier* selon votre environnement ;
- PHP 8.3 (version testée par le projet). PHP 8.1 reste le minimum théorique de
  Joomla 6 — *à vérifier* ;
- MySQL ou MariaDB compatible avec Joomla 6 ;
- droits Joomla permettant d'installer et d'administrer des extensions ;
- droits SQL suffisants pour créer et modifier les tables lors de l'installation
  (le composant crée ses propres tables `#__contentbuilderng_*`) ;
- BreezingForms compatible Joomla 6 uniquement si des vues utilisent cette source.

## Quand utiliser ContentBuilder NG ?

Utilisez-le pour :

- publier une liste de données avec recherche, filtres, tri et pagination ;
- proposer un formulaire de création ou d'édition en frontend ;
- appliquer des droits différents selon les groupes Joomla ;
- limiter les utilisateurs à leurs propres enregistrements ;
- relier des enregistrements à des articles Joomla ;
- construire un stockage interne ou exploiter une table existante ;
- importer des données CSV, XLSX ou XLS ;
- exposer une sélection de champs au moyen d'une API JSON (lecture et mise à jour) ;
- afficher des statistiques et des notes (rating) ;
- migrer une installation historique de ContentBuilder vers Joomla 6.

## Quand éviter de l'utiliser ?

Évitez de le choisir sans étude complémentaire si :

- le projet nécessite des transactions métier complexes ou un workflow sur mesure ;
- la base externe ne doit jamais être modifiée alors que sa structure ne correspond
  pas aux attentes du composant ;
- aucune personne ne peut maintenir les templates PHP personnalisés ;
- l'application exige une API publique sans authentification ni contrôle ACL
  (l'API applique toujours le modèle de permissions du composant) ;
- vous attendez une compatibilité avec Joomla 5 ou un ancien PHP : elle n'est pas
  garantie par le périmètre actuel du projet ;
- vous avez besoin d'une garantie éditeur ou d'un support contractuel.

## Vue d'ensemble des entités

| Entité | Rôle |
| --- | --- |
| **Vue** (View / Form) | Unité de configuration : source, colonnes, templates, permissions, options. Apparaît dans le menu **Vues**. |
| **Stockage** (Storage) | Table de données gérée par le composant, avec ses champs. |
| **Élément** | Champ d'une vue (issu du stockage ou d'un formulaire BreezingForms). |
| **Enregistrement** (Record) | Une ligne de données affichée, créée ou éditée. |
| **Article** | Lien optionnel entre un enregistrement et un article Joomla. |
| **Plugin** | Thème, validation, action de liste, soumission, vérification ou plugin de contenu. |
| **API JSON** | Point d'accès `task=api.display` pour lire et mettre à jour des enregistrements. |

Voir [Concepts](concepts.md) pour le détail complet.

## Parcours conseillé

1. [Installer ContentBuilder NG](installation.md).
2. En cas d'ancien site, suivre la
   [migration depuis ContentBuilder](migration-contentbuilder.md).
3. Lire les [concepts essentiels](concepts.md).
4. Réaliser le tutoriel [Premiers pas](premiers-pas.md).
5. Configurer les [permissions et l'ACL](permissions-acl.md).
6. Consulter le guide [Administration](administration.md).
7. Tester les parcours [Frontend](frontend.md).

## Tous les chapitres

- [Installation](installation.md)
- [Migration depuis ContentBuilder](migration-contentbuilder.md)
- [Premiers pas](premiers-pas.md)
- [Concepts](concepts.md)
- [Administration](administration.md)
- [Frontend](frontend.md)
- [Permissions et ACL](permissions-acl.md)
- [API JSON](api-json.md)
- [Templates et personnalisation](templates-personnalisation.md)
- [Maintenance et dépannage](maintenance-depannage.md)
- [FAQ](faq.md)
- [Glossaire](glossaire.md)

## Avertissement

ContentBuilder NG est un projet communautaire, fourni « en l'état », sans garantie
d'aucune sorte. Ce n'est **pas** un produit officiel Crosstec. Utilisez-le à vos
risques. Réalisez toujours une sauvegarde complète (fichiers et base de données)
avant installation ou migration.

> 📷 *Capture à ajouter : page d'accueil de ContentBuilder NG dans l'administration Joomla 6 (menu Composants) — `docs/fr/img/index-accueil.png`*
