# Migration depuis l'ancien ContentBuilder

ContentBuilder NG reprend des structures historiques de ContentBuilder/Crosstec et les
modernise pour Joomla 6. La migration principale est exécutée par l'installateur.

## Principe essentiel

Ne désinstallez pas l'ancien ContentBuilder avant l'installation de ContentBuilder NG.
Ses tables constituent la source de migration. Une désinstallation historique peut
supprimer les données nécessaires.

## Ce que l'installateur sait reprendre

Le dépôt documente et implémente notamment :

- le renommage des tables `#__contentbuilder_*` vers
  `#__contentbuilderng_*` ;
- la normalisation des entrées d'extension et de menus ;
- la normalisation des types de source historiques ;
- la mise à jour du schéma, des dates, index et colonnes attendues ;
- l'installation et l'activation des plugins NG ;
- la désactivation et le nettoyage prudent des plugins historiques ;
- la normalisation des thèmes anciens vers un thème pris en charge ;
- la conservation des vues, éléments, enregistrements, stockages et associations
  d'articles lorsque la migration se déroule sans collision.

L'installateur ne fusionne pas deux tables non vides concurrentes. Ce cas nécessite
une analyse manuelle.

## Sauvegardes obligatoires

Avant toute migration :

- sauvegarde complète de la base ;
- sauvegarde complète des fichiers Joomla ;
- copie des répertoires d'upload ;
- export de la configuration ContentBuilder si l'ancien site le permet ;
- relevé des versions Joomla, PHP, ContentBuilder et BreezingForms ;
- liste des plugins et templates personnalisés ;
- export des menus utilisant ContentBuilder ;
- inventaire des scripts externes qui appellent ses anciennes URLs.

Testez la restauration de la sauvegarde sur un environnement séparé.

## Ordre recommandé

1. Clonez le site.
2. Bloquez les nouvelles saisies.
3. Désactivez les anciens plugins ContentBuilder, particulièrement le plugin système,
   avant la migration du cœur Joomla.
4. Migrez Joomla vers Joomla 6.
5. Migrez BreezingForms si vos vues en dépendent.
6. Conservez les tables historiques ContentBuilder.
7. Installez le paquet complet ContentBuilder NG.
8. Consultez le journal d'installation.
9. Lancez **Audit**, puis **REPAIR DB** si nécessaire.
10. Testez les parcours avec plusieurs comptes.

## Changements d'URL

Remplacez :

```text
option=com_contentbuilder
```

par :

```text
option=com_contentbuilderng
```

Les menus Joomla connus sont normalement adaptés automatiquement, mais les URLs
stockées dans des articles, modules, templates, scripts JavaScript ou applications
externes doivent être vérifiées manuellement.

## Ancien endpoint AJAX supprimé

L'ancien endpoint n'est plus pris en charge :

```text
index.php?option=com_contentbuilder&task=ajax.display
```

Les actions migrées utilisent :

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=...
```

Actions confirmées :

- `rating`
- `get-unique-values`
- `stats`

Exemple :

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=stats&id=25
```

Consultez [API JSON](api-json.md) pour les permissions et paramètres.

## Points de vigilance

### Collisions de tables

Si une table historique et sa cible NG contiennent toutes les deux des données,
l'installateur n'effectue pas de fusion automatique.

Ne renommez pas une table isolément sans vérifier les relations d'identifiants entre
toutes les tables ContentBuilder.

### BreezingForms

Une vue liée à un formulaire BreezingForms supprimé affiche une erreur de source ou de
vue BF introuvable. Le bouton **REPAIR DB** sait synchroniser certains champs manquants
dans la vue, mais la source BF doit exister.

### Plugins et templates personnalisés

Les anciens plugins personnalisés ne sont pas automatiquement portés vers Joomla 6.
Les templates contenant du PHP doivent être relus et testés.

### Articles Joomla

Vérifiez les catégories, langues, publications et associations entre articles et
enregistrements.

### API et champs

L'API exige des permissions par vue et une autorisation explicite sur chaque champ.
Une ancienne intégration qui lisait tous les champs doit être adaptée.

## Checklist avant migration

- [ ] clone de recette disponible
- [ ] sauvegarde base et fichiers restaurable
- [ ] anciennes tables conservées
- [ ] plugins historiques inventoriés
- [ ] BreezingForms compatible Joomla 6
- [ ] personnalisations et anciennes URLs recensées
- [ ] fenêtre de maintenance planifiée

## Checklist après migration

- [ ] journal sans erreur critique
- [ ] audit exécuté
- [ ] collisions de tables absentes ou résolues
- [ ] vues et stockages présents
- [ ] menus Joomla corrigés
- [ ] liste, détail, création et édition testés
- [ ] publication et suppression testées
- [ ] permissions testées avec plusieurs rôles
- [ ] articles liés contrôlés
- [ ] imports, exports et uploads contrôlés
- [ ] API et scripts externes adaptés
- [ ] mode Debug désactivé après diagnostic

Pour la procédure de reprise et de rollback détaillée, le dépôt contient également le
guide administrateur racine `MIGRATION_GUIDE.md`.
