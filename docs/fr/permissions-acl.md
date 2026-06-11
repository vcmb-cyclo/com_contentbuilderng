# Permissions et ACL

ContentBuilder NG combine les groupes Joomla et une matrice de permissions propre à
chaque vue.

## Deux contextes distincts

Chaque vue possède des permissions :

- **frontend** : site public ;
- **backend** : administration.

Attribuer un droit backend ne donne pas automatiquement le même droit frontend.

## Héritage des groupes Joomla

Le service de permissions prend en compte le groupe direct de l'utilisateur et ses
groupes parents. Une autorisation attribuée à un groupe parent peut donc être héritée.

Les tests unitaires du dépôt vérifient ce comportement pour le frontend et le backend.

## Actions disponibles

| Permission | Effet |
| --- | --- |
| Voir | Ouvrir un détail |
| Nouveau | Créer un enregistrement |
| Éditer | Modifier un enregistrement |
| Supprimer | Supprimer un enregistrement |
| État | Changer un état personnalisé |
| Publier | Publier ou dépublier |
| Article complet | Modifier les paramètres d'article |
| Langue | Changer la langue |
| Évaluation | Évaluer un enregistrement |
| API | Utiliser les endpoints API qui l'exigent |
| Stats | Consulter les statistiques |
| List Access | Accéder à la liste |

## Permissions sur les propres enregistrements

La section **Propre** permet d'accorder certaines actions uniquement sur les
enregistrements appartenant à l'utilisateur.

La propriété dépend du type source. Pour certaines soumissions anonymes, la session
peut aussi intervenir.

L'option « propres uniquement » filtre la vue. Elle est différente d'une permission
« propre » : l'une limite les données affichées, l'autre autorise une action sur les
données possédées.

## Limites et vérifications

Une permission cochée peut encore être refusée si :

- la vue est dépubliée ;
- la limite de créations est atteinte ;
- la limite d'éditions est atteinte ;
- une vérification requise n'est pas valide ;
- l'enregistrement n'appartient pas à l'utilisateur ;
- la source n'existe plus.

Des limites globales de vue peuvent être remplacées par des limites individuelles.

## Exemples de rôles

### Visiteur

Configuration prudente :

- Liste : oui, seulement si la liste est publique ;
- Voir : oui pour les données publiques ;
- Nouveau : éventuellement ;
- Éditer/Supprimer/Publier/API/Stats : non ;
- utiliser captcha et validation pour une soumission publique.

### Utilisateur enregistré

Exemple :

- Liste et Voir : oui ;
- Nouveau : oui ;
- Éditer et Supprimer : propres enregistrements uniquement ;
- Publier : non ;
- API : non sauf besoin explicite.

### Éditeur

Exemple :

- Liste, Voir, Nouveau, Éditer : oui ;
- État et Publier : oui si le rôle modère les contenus ;
- Supprimer : selon la politique ;
- Article complet : seulement si l'éditeur gère catégories, accès et dates.

### Administrateur

Peut recevoir toutes les permissions backend. Ne supposez pas que le statut Super
Utilisateur contourne chaque règle de vue : testez la matrice réellement configurée.

## Permissions API

Les exigences confirmées sont :

| Appel | Permissions |
| --- | --- |
| GET détail | API + Voir |
| GET liste | API + Voir + List Access |
| PUT/PATCH/POST mise à jour | API + Éditer |
| `action=get-unique-values` | API + List Access |
| `action=rating` | API + Évaluation |
| `action=stats` | Stats uniquement |

En plus, chaque champ doit être publié et marqué **API autorisée**.

## Diagnostic : un utilisateur ne voit pas la liste

- [ ] vue publiée
- [ ] source valide
- [ ] contexte frontend activé
- [ ] permission List Access
- [ ] permission Voir
- [ ] groupe direct ou parent correctement coché
- [ ] menu lié à la bonne vue
- [ ] langue et filtres vérifiés
- [ ] restriction « propres uniquement » comprise

## Diagnostic : un utilisateur ne peut pas modifier

- [ ] permission Éditer frontend
- [ ] champ marqué Éditable
- [ ] template éditable configuré
- [ ] propriété de l'enregistrement
- [ ] limite d'édition non atteinte
- [ ] vérification valide
- [ ] enregistrement et source existants
- [ ] bouton Éditer affiché

## Diagnostic avec le mode Debug

Activez temporairement :

- **Afficher les permissions** ;
- **Afficher les filtres actifs** ;
- **Afficher les logs de la requête**.

Comparez ensuite le résultat pour un compte autorisé et un compte refusé. Désactivez
le Debug après le test.

