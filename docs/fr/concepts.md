# Concepts essentiels

## Vue

Une vue est la configuration fonctionnelle centrale. Elle associe une source de
données à :

- une liste ;
- une fiche détail ;
- un formulaire d'édition ;
- des permissions ;
- des règles de publication ;
- des templates ;
- des notifications ;
- éventuellement des articles Joomla et une API.

Une même source peut être utilisée par plusieurs vues avec des usages et droits
différents.

## Formulaire et source

Dans l'interface historique, le mot « formulaire » peut désigner la configuration
d'une vue. La source réelle peut être :

- un stockage ContentBuilder NG (`com_contentbuilderng`) ;
- un formulaire BreezingForms (`com_breezingforms`).

L'option « Éditer par type » peut déléguer l'édition au composant source, notamment
BreezingForms.

## Enregistrement ou record

Un enregistrement est une ligne de données métier. ContentBuilder NG maintient aussi
des métadonnées : publication, langue, dates, état, évaluations et liens d'article.

Le `record_id` utilisé par l'API correspond à l'identifiant métier résolu pour la
source.

## Stockage

Un stockage définit la structure des données :

- **interne** : ContentBuilder NG crée et gère une table préfixée Joomla ;
- **externe** : la configuration pointe vers une table existante.

Le mode externe impose davantage de prudence : certaines opérations de structure sont
limitées et la table doit déjà exister.

## Article Joomla

Une vue peut créer un article Joomla pour chaque enregistrement. Elle configure
notamment :

- le champ servant de titre ;
- la catégorie ;
- le niveau d'accès ;
- la langue ;
- la mise en vedette ;
- les dates de publication ;
- la synchronisation de publication ou de langue ;
- la suppression éventuelle de l'article avec l'enregistrement.

## Champ ou élément

Chaque champ possède plusieurs propriétés indépendantes :

- publié ;
- inclus dans la liste ;
- inclus dans la recherche ;
- cliquable vers le détail ;
- autorisé par l'API et les statistiques ;
- éditable ;
- ordre ;
- label ;
- type de tri ;
- wrapper ou transformation d'affichage.

Un champ non autorisé par l'API reste absent des réponses, même s'il est visible dans
la liste.

## Permissions

Les permissions sont configurées séparément pour le frontend et le backend, par
groupe Joomla. Elles peuvent aussi s'appliquer uniquement aux enregistrements dont
l'utilisateur est propriétaire.

Actions présentes :

- voir ;
- créer ;
- éditer ;
- supprimer ;
- changer l'état ;
- publier ;
- modifier les paramètres d'article ;
- changer la langue ;
- évaluer ;
- utiliser l'API ;
- consulter les statistiques ;
- accéder à la liste.

## Liste

La liste affiche des enregistrements avec, selon la configuration :

- recherche ;
- filtres ;
- tri ;
- pagination ;
- sélection multiple ;
- publication ;
- états personnalisés ;
- création, édition et suppression ;
- export XLSX ;
- liens de prévisualisation.

Plusieurs layouts de liste sont livrés et sélectionnables sur l'élément de menu :
`default` (tableau standard), `listcompact` (compact), `listcard` (cartes),
`listtiles` (tuiles), ainsi que `listone`, `listtwo` et `listthree`. Ils sont
surchargeables (voir [Templates et personnalisation](templates-personnalisation.md)).

### États de liste et sélection

Lorsque les options correspondantes sont activées sur la vue, ContentBuilder NG
mémorise des états par enregistrement et par utilisateur dans
`#__contentbuilderng_list_states` et `#__contentbuilderng_list_records`. Ils
permettent par exemple des actions de liste (corbeille / restauration) et la
sélection multiple.

## Templates

Les templates déterminent le HTML des détails, de l'édition, des articles et de
certaines listes. Des zones de préparation PHP peuvent modifier les données avant le
rendu.

Ils offrent une grande souplesse mais exécutent du code : réservez leur modification
à des personnes compétentes et versionnez les changements.

## Plugins

Le paquet contient plusieurs familles :

- thèmes ;
- validations ;
- vérifications ;
- actions de liste ;
- soumission ;
- plugins de contenu pour téléchargements, images, évaluations et statistiques ;
- plugin système.

L'activation et la configuration dépendent du cas d'usage.

## API

L'API JSON réutilise la configuration de la vue :

- permissions par action ;
- champs explicitement autorisés ;
- règles de visibilité ;
- lecture liste/détail ;
- modification d'un enregistrement ;
- statistiques, évaluation et valeurs uniques.

Elle n'est pas une API indépendante contournant l'ACL Joomla.

## Vérification (verify)

Certaines actions (voir, créer, éditer) peuvent exiger une étape de vérification
préalable, avec une durée de validité en jours et une URL de redirection. Les plugins
de la famille `contentbuilderng_verify` (par exemple `passthrough` et `paypal`)
traitent ce flux. Les vérifications sont enregistrées dans
`#__contentbuilderng_verifications` et l'état par utilisateur dans
`#__contentbuilderng_users`. *À vérifier selon votre configuration.*

## Évaluation (rating)

Les vues peuvent autoriser une note par enregistrement (action `rating`). Les valeurs
agrégées sont mises en cache dans `#__contentbuilderng_rating_cache` et exposées par
l'API (`action=rating`) et le plugin de contenu d'évaluation.

