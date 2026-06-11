# Premiers pas

Ce tutoriel crée une petite liste de contacts à partir d'un stockage interne.

## 1. Créer le stockage

1. Ouvrez **Composants > ContentBuilder NG > Stockages de données**.
2. Cliquez sur **Nouveau**.
3. Saisissez un nom technique stable, par exemple `contacts_cbng`.
4. Saisissez le titre `Contacts`.
5. Choisissez le mode **Table interne**.
6. Enregistrez.
7. Ajoutez les champs `nom`, `email` et `message`.
8. Enregistrez puis utilisez **Datatable Sync** si l'interface le propose.

Le nom technique devient une table préfixée par Joomla. Évitez de le modifier après
la mise en production.

> 📷 *Capture à ajouter : création d'un stockage interne et ajout de champs — `docs/fr/img/premiers-pas-stockage.png`*

## 2. Créer une vue

1. Ouvrez **Vues**.
2. Cliquez sur **Nouveau**.
3. Donnez un nom explicite, par exemple `Contacts publics`.
4. Sélectionnez la source ContentBuilder NG.
5. Sélectionnez le stockage `Contacts`.
6. Choisissez le contexte d'affichage frontend ou les deux.
7. Enregistrez une première fois pour charger les champs.

## 3. Configurer les champs

Dans l'onglet **Vue** :

- publiez les champs utiles ;
- activez **Liste** pour `nom` et `email` ;
- activez **Recherche** pour `nom` et `email` ;
- rendez `nom` cliquable pour ouvrir le détail ;
- rendez les champs nécessaires éditables ;
- n'activez **API autorisée** que si l'API doit réellement exposer le champ.

Définissez ensuite l'ordre des champs.

## 4. Configurer l'affichage

Dans **Options avancées** :

- activez le filtre ;
- choisissez la pagination initiale ;
- configurez le tri ;
- activez les boutons Nouveau et Éditer si le parcours le nécessite ;
- choisissez si seuls les enregistrements publiés sont visibles.

Dans **Détail** et **Édition** :

- utilisez les exemples générés par le thème comme point de départ ;
- vérifiez que le template d'édition existe ;
- conservez une version simple avant toute personnalisation.

## 5. Configurer les permissions

Pour un premier test avec des utilisateurs enregistrés :

- accordez **Liste**, **Voir**, **Nouveau** et **Éditer** au groupe concerné ;
- accordez **Supprimer** et **Publier** uniquement si nécessaire ;
- utilisez les permissions frontend, pas seulement backend ;
- activez « propres enregistrements » si chacun ne doit gérer que ses données.

Consultez [Permissions et ACL](permissions-acl.md) avant une ouverture publique.

## 6. Publier un menu frontend

1. Ouvrez **Menus** dans Joomla.
2. Créez un élément de menu.
3. Choisissez un type ContentBuilder NG :
   **Vue liste**, **Détails de l'enregistrement**, **Créer un élément de menu**
   (création/édition), **Dernier** ou **Liste de vues publiques**.
4. Sélectionnez la vue.
5. Conservez les options de menu sur « Utiliser la valeur par défaut » au départ.
6. Publiez le menu.

## 7. Créer et modifier un enregistrement

Depuis le frontend :

1. ouvrez la liste ;
2. cliquez sur **Nouveau** ;
3. renseignez les champs ;
4. enregistrez ;
5. ouvrez le détail ;
6. testez **Éditer** ;
7. vérifiez le retour dans la liste.

Si le nouvel enregistrement n'apparaît pas, contrôlez :

- l'option de publication automatique ;
- l'option « publiés uniquement » ;
- la langue ;
- la permission de voir ;
- la propriété de l'enregistrement ;
- les filtres actifs.

## Vérification finale

- [ ] stockage publié et table existante
- [ ] vue publiée
- [ ] source valide
- [ ] champs publiés
- [ ] template détail présent
- [ ] template édition présent
- [ ] permissions frontend attribuées
- [ ] menu publié
- [ ] création testée
- [ ] modification testée
- [ ] résultat vérifié avec un autre compte

