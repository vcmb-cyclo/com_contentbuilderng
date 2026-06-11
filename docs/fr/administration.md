# Administration

L'administration principale est accessible par
**Composants > ContentBuilder NG**.

## Écran Stockages de données

Cet écran liste les structures de données connues.

Actions disponibles :

- créer ;
- éditer ;
- supprimer ;
- publier ou dépublier ;
- rechercher, filtrer et trier ;
- ouvrir l'aide.

La suppression d'un stockage interne peut supprimer sa table de données. Une table
externe n'est pas supprimée par la suppression de sa définition, selon le message de
confirmation présent dans l'interface.

Bonnes pratiques :

- sauvegardez avant une suppression ;
- donnez un titre lisible et un nom technique stable ;
- utilisez un stockage par structure métier cohérente ;
- ne considérez pas l'ordre d'affichage comme une règle fonctionnelle.

## Écran d'édition d'un stockage

### Onglet Stockage

Paramètres principaux :

- **Nom** : nom technique de la table interne ;
- **Titre** : libellé d'administration ;
- **Publié** : active ou désactive le stockage ;
- **Ordre** ;
- **Mode interne** ou **table externe**.

En mode interne, l'enregistrement peut créer ou renommer la table. En mode externe,
la table doit exister. Le composant peut lire sa structure, mais l'ajout ou le
renommage de colonnes est limité.

### Champs

L'écran permet d'ajouter, modifier, ordonner, publier et supprimer des champs.

Après un changement de structure, utilisez **Datatable Sync** pour aligner la
définition et la table.

### Import de fichier

Formats confirmés :

- CSV ;
- XLSX ;
- XLS.

Options visibles :

- choix du délimiteur CSV ;
- réparation d'encodage si `iconv` est disponible ;
- création de champs depuis les en-têtes ;
- suppression préalable des enregistrements existants ;
- aperçu des colonnes.

L'import est refusé pour certaines opérations sur une table externe.

Checklist avant import :

- [ ] sauvegarde effectuée
- [ ] petit échantillon testé
- [ ] noms de colonnes uniques
- [ ] nombre de colonnes cohérent
- [ ] encodage vérifié
- [ ] option de suppression des données comprise

> 📷 *Capture à ajouter : onglets Stockage, Informations et import de fichier — `docs/fr/img/admin-stockage-edition.png`*

## Écran Vues

La liste des vues permet :

- créer ;
- copier ;
- éditer ;
- publier ou dépublier ;
- supprimer ;
- filtrer par texte, état ou mot-clé ;
- prévisualiser.

Une vue doit être publiée et associée à une source valide pour fonctionner hors
prévisualisation administrateur.

## Édition d'une vue

### Onglet Vue

Configure :

- nom ;
- mot-clé ;
- thème ;
- publication ;
- mode Debug ;
- source et type ;
- contexte frontend, backend ou les deux ;
- champs et colonnes.

Pour chaque champ, les colonnes principales sont :

- inclusion en liste ;
- inclusion dans la recherche ;
- lien vers le détail ;
- autorisation API/Stats ;
- éditable ;
- wordwrap ;
- publication ;
- ordre.

Le mode Debug est propre à la vue. Il ne dépend pas du Debug global de Joomla.

### Onglet Options avancées

Options observées :

- publication automatique des nouveaux enregistrements ;
- affichage limité aux enregistrements publiés ;
- colonnes techniques et métadonnées ;
- boutons Nouveau, Éditer, Export, Imprimer et Retour ;
- barre de boutons ou en-tête de liste fixe ;
- prévisualisation ;
- affichage du nom et des filtres dans le titre ;
- filtre et sélecteur de pagination ;
- limite initiale ;
- filtre externe ;
- correspondance exacte ;
- jusqu'à trois critères de tri ;
- évaluation et nombre de niveaux ;
- libellés alternatifs des boutons Enregistrer et Valider.

Les options de menu Joomla peuvent surcharger certaines valeurs de la vue.

### Onglet Article

Configure la création d'articles Joomla :

- activer la création ;
- supprimer l'article avec l'enregistrement ;
- champ titre ;
- catégorie ;
- niveau d'accès ;
- vedette ;
- langue ;
- impact de la langue de l'article sur l'enregistrement ;
- délai avant publication ;
- délai avant dépublication ;
- impact de la publication de l'article sur l'enregistrement.

Testez la catégorie et les droits Joomla avant d'activer cette fonction en
production.

### Onglet Texte d'introduction de liste

Contenu affiché au-dessus de la liste frontend.

### Onglet États de liste

Permet de définir des états personnalisés avec :

- titre ;
- couleur ;
- publication ;
- action optionnelle fournie par un plugin.

Les plugins fournis comprennent des actions de mise à la corbeille et de restauration.

### Onglet Détail

Contient :

- le template de détail ;
- les barres supérieure et inférieure ;
- le bouton Retour ;
- le code PHP de préparation ;
- la génération d'un exemple depuis le thème.

### Onglet Édition

Contient :

- le template éditable ;
- les barres supérieure et inférieure ;
- le répertoire d'upload ;
- la protection du répertoire ;
- le code PHP de préparation ;
- l'option **Éditer par type**.

Avec une source BreezingForms, **Éditer par type** délègue le formulaire à
BreezingForms et remplace le template éditable natif.

### Onglet Debug

Visible lorsque le mode Debug de la vue est activé. Options :

- afficher la colonne ID BreezingForms ;
- afficher l'ID interne CBNG ;
- activer les logs CBNG ;
- afficher les logs de la requête ;
- afficher les permissions calculées ;
- afficher les filtres, le tri et la pagination.

Désactivez ce mode après le diagnostic : les informations sont visibles par les
utilisateurs ayant accès à la vue.

### Onglet API

Présente les endpoints et exemples propres à la vue. Les liens de prévisualisation
administrateur contiennent une signature temporaire et ne doivent pas être copiés
comme identifiants permanents d'une application externe.

### Onglet Emails

Configure les notifications de création et de mise à jour :

- activation ;
- sujet ;
- destinataires ;
- expéditeur alternatif ;
- nom d'expéditeur ;
- pièces jointes issues des uploads ;
- HTML ou texte ;
- template utilisateur ;
- template administrateur.

Des variables de champs peuvent être utilisées dans certains paramètres, par exemple
`{email}`. Testez chaque template avec des données réelles non sensibles.

### Onglet Permissions

Sépare :

- les droits frontend ;
- les droits backend ;
- les options par utilisateur ;
- les matrices par groupe Joomla ;
- les limites de création et d'édition ;
- les vérifications ;
- la gestion de profil ou d'inscription.

Voir [Permissions et ACL](permissions-acl.md).

## Écran Utilisateurs

Cet écran est lié à une vue et gère les paramètres individuels :

- vérifications ;
- limites propres à un utilisateur ;
- publication de l'accès utilisateur.

À vérifier : l'usage exact de chaque colonne dépend du workflow de vérification
configuré dans la vue.

<a id="plugins"></a>

## Gestion des plugins Joomla

Le paquet installe plusieurs groupes de plugins. Leur activation se contrôle dans
**Système > Gestion > Plugins**. Ne les activez que lorsqu'une vue ou un contenu en a
besoin.

Plugins de validation livrés :

- valeur non vide ;
- adresse e-mail ;
- égalité entre valeurs ;
- date valide ;
- date non antérieure à une référence.

Autres familles livrées :

- thèmes `Joomla 6`, `Dark`, `Blank` et `Khepri` ;
- actions de liste Corbeille et Restaurer ;
- vérifications Pass-Through et PayPal ;
- exemple de plugin de soumission ;
- plugins de contenu pour les téléchargements, images, évaluations, vérifications,
  permissions et statistiques.

Le plugin système expose notamment :

- le nombre d'éléments synchronisés par passage ;
- la désactivation de la soumission native d'articles Joomla ;
- la désactivation du cache de `com_content` pour préserver l'exécution de certains
  plugins de contenu ;
- l'affectation automatique de groupes Joomla après vérification ;
- les groupes concernés ;
- une liste d'identifiants de vues limitant cette affectation.

L'affectation automatique de groupes modifie directement les droits des utilisateurs.
Testez-la avec un compte non administrateur et limitez-la aux vues nécessaires.

Le plugin de mise à l'échelle des images permet de fixer une taille maximale en Mo.
Le plugin de vérification PayPal propose des identifiants de production, des
identifiants de test et un mode Sandbox. La validité du workflow PayPal avec votre
compte et les API PayPal actuelles est **À vérifier** avant toute utilisation réelle.

> 📷 *Capture à ajouter : filtrage des plugins Joomla sur « ContentBuilder NG » — `docs/fr/img/admin-plugins.png`*

## Écran À propos

Il affiche :

- version, date et type de build ;
- bibliothèques détectées ;
- audit de la base ;
- journal du composant ;
- transfert de configuration ;
- workflow **REPAIR DB**.

L'audit recherche notamment :

- index dupliqués ;
- tables historiques ;
- entrées de menus historiques ;
- problèmes d'encodage ou de collation ;
- anciennes données compactées ;
- colonnes d'audit manquantes ;
- doublons de plugins ;
- écarts de champs BreezingForms ;
- menus pointant vers une vue invalide ;
- incohérences de permissions frontend ;
- références d'éléments invalides ;
- catégories d'articles générés invalides ;
- anciens fichiers de langue.

Exécutez les réparations hors période de charge et après sauvegarde.

## Transfert de configuration

L'écran permet l'export et l'import JSON des sections sélectionnées :

- vues ;
- stockages ;
- contenus de stockage si demandé.

Les modes d'import comprennent au moins la fusion et le remplacement. Préférez la
fusion pour un transfert ordinaire. Utilisez le remplacement uniquement sur une copie
testée du site.
