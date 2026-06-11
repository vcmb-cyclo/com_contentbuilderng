# FAQ

## ContentBuilder NG est-il l'ancien produit officiel Crosstec ?

Non. Il s'agit d'une modernisation communautaire issue de ContentBuilder. Le projet
n'est pas un produit officiel Crosstec.

## Quelles versions sont prises en charge ?

Joomla 6.0 minimum et PHP 8.1 minimum.

## Puis-je installer le ZIP « Source code » de GitHub ?

Ce n'est pas recommandé. Utilisez le paquet
`com_contentbuilderng-<version>.zip` publié dans une release, car il est assemblé avec
les dépendances de production nécessaires.

## Dois-je désinstaller l'ancien ContentBuilder avant la migration ?

Non. Conservez ses tables et installez ContentBuilder NG par-dessus après sauvegarde.

## La migration modifie-t-elle automatiquement les anciennes tables ?

Oui pour les noms historiques reconnus, sous réserve qu'il n'existe pas de collision
entre deux tables contenant des données.

## Que signifie « Vue BF introuvable » ?

La vue ContentBuilder NG pointe vers un formulaire BreezingForms absent ou invalide.

## Quelle différence entre une vue et un stockage ?

Le stockage définit la structure physique des données. La vue définit l'affichage,
l'édition, les permissions et les comportements appliqués à une source.

## Puis-je utiliser une table SQL existante ?

Oui avec un stockage externe. Sauvegardez la table et vérifiez sa structure. Certaines
opérations de création ou renommage de champs sont limitées.

## Pourquoi un enregistrement créé n'apparaît-il pas ?

Vérifiez la publication automatique, « publiés uniquement », la langue, les filtres,
la propriété et les permissions de voir.

## Pourquoi un utilisateur voit-il la liste mais pas le détail ?

**List Access** autorise la liste, tandis que **Voir** contrôle le détail. Vérifiez les
deux permissions.

## Pourquoi le bouton Éditer n'apparaît-il pas ?

Le champ doit être éditable, le bouton doit être activé, le template doit exister et
l'utilisateur doit avoir la permission Éditer.

## Puis-je limiter un utilisateur à ses propres données ?

Oui, avec les options « propres enregistrements » et les permissions propres. Testez
le comportement pour les utilisateurs connectés et les soumissions anonymes.

## ContentBuilder NG peut-il créer des articles Joomla ?

Oui. Une vue peut créer et synchroniser des articles avec catégorie, langue, accès,
publication et titre configurables.

## Quels imports sont disponibles ?

CSV, XLSX et XLS pour les stockages. Testez toujours un petit fichier avant un import
complet.

## Quel format d'export est disponible ?

La liste frontend peut proposer un export XLSX lorsque l'option est activée.

## Comment exposer un champ dans l'API ?

Publiez le champ et activez **API autorisée** dans la table des éléments de la vue.
Attribuez aussi les permissions API nécessaires au groupe.

## Pourquoi `fields[records]=total` supprime-t-il `ratings` ?

Les sparse fieldsets suppriment les ressources non demandées. Ajoutez par exemple
`fields[ratings]=average` pour conserver cette ressource.

## La permission API suffit-elle pour les statistiques ?

Non. `action=stats` utilise la permission **Stats** uniquement. Les champs de
regroupement ou filtre doivent aussi être autorisés par l'API.

## Que signifie « Field is not allowed for API/Stats » ?

Le champ demandé n'est pas publié, n'est pas marqué API autorisée ou son nom/label ne
correspond pas à un champ de la vue.

## L'API peut-elle créer un nouvel enregistrement ?

Le contrôleur actuel exige un `record_id` pour `POST`, `PUT` et `PATCH`. La création
sans `record_id` est donc **À vérifier** et ne doit pas être supposée disponible.

## À quoi sert le mode Debug de la vue ?

Il affiche un badge et, selon les options, les identifiants, permissions, filtres et
logs de la requête frontend. Désactivez-le après diagnostic.

## Où trouver les logs ?

Dans `com_contentbuilderng.log`, dans le chemin de logs Joomla ou le dossier `logs` du
site. L'écran **À propos** peut l'afficher.

## Puis-je désinstaller sans perdre les données ?

Non. Le SQL de désinstallation supprime les tables ContentBuilder NG. Exportez et
sauvegardez avant toute désinstallation.

## REPAIR DB est-il sans risque ?

Non. Il peut modifier le schéma et les données. Sauvegardez, exécutez-le hors charge et
lisez chaque étape.

