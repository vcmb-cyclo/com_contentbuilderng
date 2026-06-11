# Documentation utilisateur de ContentBuilder NG

[English documentation](../en/index.md)

ContentBuilder NG est un composant Joomla 6 permettant de construire des applications
de données configurables : formulaires, listes, fiches détail, édition, permissions,
articles Joomla, notifications, exports et API JSON.

Le projet est issu de la migration et de la modernisation de l'ancien ContentBuilder
de Crosstec. Il s'agit d'un projet communautaire, distinct du produit historique et
fourni sans garantie.

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

- Joomla 6.0 ou supérieur ;
- PHP 8.1 ou supérieur ;
- MySQL ou MariaDB compatible avec Joomla 6 ;
- droits Joomla permettant d'installer et d'administrer des extensions ;
- droits SQL suffisants pour créer et modifier les tables lors de l'installation ;
- BreezingForms compatible Joomla 6 uniquement si des vues utilisent cette source.

## Quand utiliser ContentBuilder NG ?

Utilisez-le pour :

- publier une liste de données avec recherche, filtres, tri et pagination ;
- proposer un formulaire de création ou d'édition ;
- appliquer des droits différents selon les groupes Joomla ;
- limiter les utilisateurs à leurs propres enregistrements ;
- relier des enregistrements à des articles Joomla ;
- construire un stockage interne ou exploiter une table existante ;
- importer des données CSV, XLSX ou XLS ;
- exposer une sélection de champs au moyen d'une API JSON ;
- migrer une installation historique de ContentBuilder vers Joomla 6.

## Quand éviter de l'utiliser ?

Évitez de le choisir sans étude complémentaire si :

- le projet nécessite des transactions métier complexes ou un workflow sur mesure ;
- la base externe ne doit jamais être modifiée alors que sa structure ne correspond
  pas aux attentes du composant ;
- aucune personne ne peut maintenir les templates PHP personnalisés ;
- l'application exige une API publique sans authentification ni contrôle ACL ;
- vous attendez une compatibilité avec Joomla 5 ou un ancien PHP : elle n'est pas
  prise en charge par le périmètre actuel du projet ;
- vous avez besoin d'une garantie éditeur ou d'un support contractuel.

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

> **TODO capture d'écran :** page d'accueil de ContentBuilder NG dans
> l'administration Joomla 6.
