# Installation

## Avant de commencer

Vérifiez les prérequis :

- Joomla 6.0 ou supérieur (vérifié par l'installateur) ;
- PHP 8.3 minimum (vérifié par l'installateur) ;
- accès Super Utilisateur ;
- sauvegarde récente du site et de la base ;
- espace disque suffisant ;
- répertoires Joomla modifiables par le serveur web.

> ℹ️ **Note :** l'installateur (`script.php`) refuse l'installation si PHP est
> inférieur à 8.3 ou si Joomla est inférieur à 6.0. Un message d'erreur est alors
> affiché et l'opération est annulée.

Pour une migration depuis l'ancien ContentBuilder, ne désinstallez pas l'ancien
composant avant d'avoir lu le
[guide de migration](migration-contentbuilder.md).

> ⚠️ **Attention :** ne désinstallez jamais l'ancien composant ContentBuilder /
> BreezingForms avant d'installer ContentBuilder NG. La migration des données est
> réalisée automatiquement pendant l'installation.

## Installer depuis une release ZIP

La méthode recommandée est d'utiliser le paquet d'installation publié dans une
release du projet.

1. Téléchargez le fichier nommé `com_contentbuilderng-<version>.zip`.
2. Dans Joomla, ouvrez **Système > Installation > Extensions**.
3. Sélectionnez l'onglet d'envoi d'un fichier paquet (« Téléverser le fichier
   du paquet »).
4. Déposez le ZIP d'installation.
5. Attendez la fin complète du processus.
6. Lisez les messages de l'installateur, notamment les avertissements de migration.

N'utilisez pas automatiquement l'archive **Source code (zip)** générée par GitHub.
Elle représente le dépôt de développement et peut ne pas contenir les dépendances de
production assemblées comme le paquet de release.

> 📷 *Capture à ajouter : installation du paquet dans le gestionnaire d'extensions Joomla 6 — `docs/fr/img/installation-upload.png`*

## Ce que l'installation met en place

- Le composant et son menu d'administration **Composants > ContentBuilder NG**,
  avec les sous-menus **Stockages de données**, **Vues** et **À propos**.
- Les tables de base de données suivantes (préfixe Joomla `#__`) :
  `#__contentbuilderng_forms`, `#__contentbuilderng_elements`,
  `#__contentbuilderng_storages`, `#__contentbuilderng_storage_fields`,
  `#__contentbuilderng_records`, `#__contentbuilderng_articles`,
  `#__contentbuilderng_users`, `#__contentbuilderng_registered_users`,
  `#__contentbuilderng_list_records`, `#__contentbuilderng_list_states`,
  `#__contentbuilderng_resource_access`, `#__contentbuilderng_verifications`,
  `#__contentbuilderng_rating_cache`.
- Les plugins livrés (thèmes, validations, actions de liste, soumission,
  vérification, plugins de contenu et plugin système). Voir
  [Administration](administration.md#plugins) pour la liste détaillée.

## Vérifications après installation

Dans **Composants > ContentBuilder NG**, vérifiez :

- la présence des entrées **Stockages de données**, **Vues** et **À propos** ;
- la version et le type de build affichés dans **À propos** ;
- l'absence d'erreur critique dans le journal ;
- la présence des plugins ContentBuilder NG dans **Système > Gestion > Extensions** ;
- l'activation des plugins nécessaires à votre usage ;
- la présence des tables `#__contentbuilderng_*` dans la base.

Lancez ensuite :

1. **À propos > Audit** ;
2. examinez les avertissements ;
3. utilisez **REPAIR DB** uniquement après sauvegarde si des réparations sont
   proposées ;
4. relancez l'audit.

- [ ] entrées de menu présentes
- [ ] tables `#__contentbuilderng_*` créées
- [ ] plugins activés selon le besoin
- [ ] audit exécuté sans avertissement bloquant

## Mise à jour

Une mise à jour utilise le même paquet complet :

1. sauvegardez les fichiers et la base ;
2. installez le nouveau ZIP par-dessus la version existante ;
3. consultez le message de mise à jour ;
4. lancez l'audit ;
5. testez une liste, un détail, une création et une modification.

L'installateur peut adapter le schéma, normaliser des références, mettre à jour les
plugins livrés et nettoyer des éléments historiques.

> ℹ️ **Note :** un serveur de mise à jour est déclaré dans le manifeste. Joomla peut
> donc proposer les nouvelles versions via **Système > Mise à jour > Extensions**.
> Seules les releases publiées sont considérées comme stables.

## Erreurs fréquentes

### Version PHP ou Joomla refusée

Le composant vérifie Joomla 6.0 minimum et PHP 8.3 minimum. Changez la version du
serveur avant de relancer l'installation.

### Fichiers non modifiables

Vérifiez les propriétaires et permissions des répertoires Joomla. Évitez de rendre
globalement les fichiers accessibles en écriture.

### Échec SQL

- conservez le site hors ligne ;
- consultez le journal Joomla et `com_contentbuilderng.log` ;
- vérifiez les droits `CREATE`, `ALTER`, `INDEX`, `UPDATE` et `RENAME TABLE` ;
- recherchez une collision entre tables historiques et tables NG ;
- restaurez la sauvegarde avant toute correction manuelle non maîtrisée.

Voir aussi le guide `SQL_MIGRATION_GUIDE.md` à la racine du projet pour les cas de
récupération avancée.

### Plugins manquants

Le paquet complet installe les plugins livrés. Si le journal indique que la source
d'installation des plugins n'a pas été trouvée, vérifiez que vous avez utilisé le ZIP
de release complet.

## Désinstallation

> ⚠️ **Attention :** le manifeste de désinstallation supprime les tables
> `#__contentbuilderng_*`, notamment les vues, enregistrements, stockages,
> permissions, articles liés et vérifications. Cette suppression est irréversible.

Avant toute désinstallation :

- exportez la configuration utile (**À propos > Exporter la configuration**) ;
- exportez les données métier ;
- sauvegardez la base complète ;
- sauvegardez les fichiers envoyés ;
- identifiez les articles Joomla liés.

La désinstallation ne doit pas être utilisée comme méthode de mise à jour ou de
migration.

## Release ZIP ou code source ?

| Usage | Release ZIP | Code source du dépôt |
| --- | --- | --- |
| Installation Joomla | Oui, recommandé | Non, sans assemblage |
| Dépendances PHP de production | Incluses dans le paquet construit | À installer |
| Tests et outils de développement | Exclus | Présents ou configurables |
| Usage administrateur | Adapté | Déconseillé |
| Contribution au projet | Non | Oui |

Checklist finale :

- [ ] ZIP de release utilisé
- [ ] installation terminée sans erreur critique
- [ ] audit exécuté
- [ ] plugins nécessaires présents
- [ ] sauvegarde conservée
- [ ] test frontend effectué
