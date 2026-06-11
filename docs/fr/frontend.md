# Utilisation côté site

Le frontend est construit à partir d'une vue publiée et d'un élément de menu Joomla,
ou d'une URL routée vers un contrôleur ContentBuilder NG.

## Types de menus disponibles

- **Vue liste** ;
- listes en cartes, tuiles, compactes ou layouts personnalisés ;
- **Créer** un enregistrement ;
- afficher un **Enregistrement** précis ;
- afficher le **Dernier** enregistrement de l'utilisateur ;
- afficher une **Liste de vues publiques**.

Les options de menu peuvent surcharger la catégorie, la pagination, les barres
d'actions, l'auteur, le bouton Retour et les filtres.

## Consultation d'une liste

Selon la configuration, l'utilisateur peut :

- rechercher dans les champs autorisés ;
- appliquer des filtres ;
- trier les colonnes ;
- choisir le nombre de lignes ;
- suivre un lien vers le détail ;
- sélectionner plusieurs enregistrements ;
- exporter la liste en XLSX ;
- créer, éditer, supprimer, publier ou changer l'état.

Les actions réellement visibles dépendent à la fois de la configuration d'affichage et
des permissions calculées.

## Recherche et filtres

Un champ doit être marqué **Inclus dans la recherche** pour être interrogé.

Les menus peuvent imposer des filtres de champs. Le séparateur `|` représente des
alternatives dans les filtres configurés. Le filtre exact modifie le mode de
correspondance.

Le titre de page peut afficher le filtre actif, mais le code de langue précise que la
recherche libre n'est pas incluse dans ce titre.

## Fiche détail

Le détail peut afficher :

- les champs prévus par le template ;
- les informations de création et modification ;
- les barres d'action ;
- le bouton Retour ;
- le bouton Imprimer ;
- les liens précédent et suivant ;
- les plugins de contenu intégrés au template.

Un champ peut être rendu cliquable depuis la liste pour ouvrir cette fiche.

## Création

Pour créer un enregistrement, l'utilisateur doit :

- accéder à une vue publiée ;
- posséder la permission **Nouveau** ;
- ne pas avoir atteint sa limite de création ;
- satisfaire une éventuelle vérification ;
- réussir les validations et le captcha s'ils sont configurés.

Après création, l'enregistrement peut être publié automatiquement ou rester
dépublié.

## Édition

La permission **Éditer** est requise. En mode « propres enregistrements »,
l'utilisateur doit aussi être reconnu comme propriétaire ou utiliser la même session
dans certains parcours anonymes.

L'édition peut utiliser :

- le template natif ContentBuilder NG ;
- l'éditeur du type source, notamment BreezingForms.

## Suppression

La suppression est disponible dans les contrôleurs de liste et d'édition. Elle exige
les droits correspondants. Selon la vue, l'article Joomla associé peut également être
supprimé.

Avant d'activer la suppression pour un groupe non administrateur, testez :

- suppression depuis la liste ;
- suppression depuis l'éditeur ;
- effet sur l'article lié ;
- effet sur les fichiers envoyés : **À vérifier** selon le type de source et le
  template utilisé.

## Publication, état et langue

Des permissions distinctes contrôlent :

- la publication ;
- l'état personnalisé ;
- la langue ;
- les paramètres complets d'article.

Un enregistrement peut être masqué par :

- sa publication ;
- sa date de publication future ;
- sa date de fin ;
- sa langue ;
- un filtre ;
- l'option « publiés uniquement » ;
- la restriction aux propres enregistrements.

## Dernier enregistrement

Le type de menu **Dernier** ouvre la dernière saisie de l'utilisateur. Si aucune
saisie n'existe, le libellé de langue indique une redirection vers la création.

## Liste de vues publiques

Ce menu affiche plusieurs vues sélectionnées avec, selon les options :

- identifiant ;
- mot-clé ;
- permissions de voir, créer ou éditer ;
- texte d'introduction.

## Mode Debug

Lorsqu'il est activé sur la vue, un badge DEBUG est affiché. Un panneau repliable peut
présenter les identifiants, permissions, filtres et logs de la requête.

Il s'agit d'un outil de diagnostic, pas d'une fonction destinée au public.

## Messages courants

### « Vue introuvable »

La vue n'existe pas, est dépubliée ou l'ID du menu est incorrect.

### « Vue BF introuvable »

La vue est liée à une source BreezingForms absente ou dont l'identifiant n'est plus
valide.

### « Enregistrement introuvable »

L'enregistrement n'existe pas ou est masqué par publication, langue, propriété ou
filtre.

### « Accès à la liste non autorisé »

Le groupe ne possède pas **List Access** dans les permissions frontend.

### « Vous n'êtes pas autorisé à éditer »

Vérifiez le droit Éditer, la propriété, la limite d'édition et les vérifications.

### « Cette vue n'est pas exportable »

L'export XLSX n'est pas activé ou le contexte ne l'autorise pas.

Checklist de test frontend :

- [ ] visiteur
- [ ] utilisateur enregistré
- [ ] propriétaire d'un enregistrement
- [ ] non-propriétaire
- [ ] éditeur ou modérateur
- [ ] langue différente
- [ ] enregistrement publié et dépublié
- [ ] filtres actifs et réinitialisés

