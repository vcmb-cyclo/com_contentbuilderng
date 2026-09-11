# Menu Joomla — List View

Les boutons frontend suivent la [charte graphique commune](frontend-graphic-charter.md),
avec Éditer/Imprimer comme référence de taille, graisse et marges.

## Priorité des actions d’affichage — 6.1.16-RC1

Pour Imprimer, Export XLS et Évaluation, Oui et Non dans le menu remplacent
le réglage de la vue. Paramètre par défaut conserve la valeur de la vue.
La résolution s’applique aux écrans Liste et Détail avant le chargement des
données d’évaluation. Elle ne donne aucun droit supplémentaire et ne modifie
pas la syntaxe CBList. Imprimer conserve sa place dans la barre supérieure de
Détail : cette barre doit être affichée. Le mode stockage direct en lecture
seule conserve ses restrictions.

Les tests doivent couvrir chaque action, les deux valeurs de la vue et les
trois choix du menu. La navigation Liste → Détail doit conserver le contexte
du menu Joomla.

## 1. Statut du document

Cette spécification décrit le nouveau type de menu ContentBuilder NG
« List View », type par défaut à partir du cycle 6.1.10-RC09.

- Projet : ContentBuilder NG
- Statut : RC09-B20 validée et publiée sous 6.1.10-RC09
- Version du document : 2.1
- Dernière mise à jour : 2026-08-14
- Plateforme : Joomla 6 uniquement
- PHP : 8.3 ou version ultérieure
- Base de données : MySQL ou MariaDB uniquement
- Layout Joomla : `list/default`
- Fichier de paramètres : `site/tmpl/list/default.xml`
- Rendu frontend : présentation tabulaire actuelle du List View Classic

Les layouts Cards, Compact Table et Tiles restent des types de menu séparés,
mais utilisent le même contrat de paramètres, le même ordre et les mêmes
fonctionnalités que List View. Seul leur rendu visuel spécialisé diffère.

## 2. Objectif

Le nouveau List View permet de créer plusieurs listes spécialisées à partir
d'une même Vue ContentBuilder NG, par exemple :

- liste de gestion ;
- liste publique ;
- liste d'adhérents ;
- liste de statistiques.

Il reprend uniquement les fonctions utiles de CBList dans une interface
Joomla native. Il ne reproduit ni l'intégration iframe, ni `loading`, ni
`height`, et ne remplace pas le plugin CBList.

Le rendu initial reste le tableau ContentBuilder NG actuel. Le thème peut être
hérité de la Vue ou surchargé par le menu.

## 3. Sélection de la Vue et thème

### 3.1 Vue

Le paramètre `form_id` est obligatoire.

- La liste commence par « Choisir une vue ».
- La première Vue ne doit jamais être sélectionnée silencieusement.
- L'ordre des Vues est celui de ContentBuilder NG.
- Les Vues non publiées restent visibles dans le sélecteur d'administration
  afin de permettre la préparation d'un menu avant publication.
- Une sauvegarde sans Vue sélectionnée produit l'erreur Joomla traduite.
- La Vue sélectionnée est conservée par Reset.

### 3.2 Thème

Le thème utilise le contrôle commun validé en RC08 :

- `Use Default (<theme de la Vue>)` ;
- choix explicite parmi les thèmes disponibles.

Les layouts Cards, Compact Table et Tiles ne deviennent pas des thèmes du
nouveau List View. Ils réutilisent néanmoins automatiquement son constructeur
et toutes ses règles fonctionnelles.

## 4. Titre visible de la liste

Le paramètre ne concerne que le titre ContentBuilder NG rendu au-dessus de la
liste. Il ne modifie ni le titre de l'élément de menu Joomla, ni Page Heading,
ni les métadonnées.

Modes :

- `Default (Vue)` ;
- `Custom` ;
- `Hidden`.

Règles du titre personnalisé :

- maximum 255 points de code Unicode ;
- suppression des espaces Unicode en début et fin ;
- texte Unicode et emoji autorisés ;
- HTML interdit ;
- caractères de contrôle invisibles rejetés ;
- sortie échappée ;
- une valeur vide après normalisation est invalide ;
- masquer le titre exige le mode explicite `Hidden`.

Ce contrôle termine le bloc principal List View, avant le premier séparateur
de sous-catégorie. Le libellé et le sélecteur utilisent le même alignement
horizontal que les autres réglages placés en haut de page. Lorsque le mode
Custom est sélectionné, Custom introduction apparaît sur la ligne suivante.

## 5. Colonnes affichées, capacités et filtres fixes

### 5.1 Source des champs

La Vue reste la source d'autorité.

- Tout champ publié de la Vue peut devenir une colonne du menu.
- Le mode Default reprend les réglages List, Search, Link, Detail, Edit et
  Published de la Vue. Le mode Custom peut uniquement les restreindre pour ce
  menu sans modifier la Vue.
- Un champ non publié reste indisponible et ne peut jamais être réactivé par le
  menu.
- Tout champ actif et techniquement filtrable peut servir de filtre fixe, même
  s'il n'est pas affiché comme colonne.
- Les titres de colonnes restent ceux définis dans la Vue. Le menu ne propose
  pas d'alias de titre par colonne.

### 5.2 Mode d'affichage

Deux modes sont disponibles :

- `Default (Vue)` : reprend exactement les colonnes visibles et leur ordre ;
- `Custom` : permet de choisir un sous-ensemble autorisé et son ordre propre au
  menu.

### 5.3 Interface RC09-B12

Un tableau Joomla unifié intitulé « Colonnes affichées » est placé en bas de
l'onglet. Il reprend les indicateurs de la Vue afin que l'administrateur voie
immédiatement le plafond applicable au menu :

| Ordre | Champ / Libellé | Liste | Recherche | Lien | Detail | Modifier | Export | Publié | Filtre de données |
|---|---|---|---|---|---|---|---|---|---|
| déplacement | libellé de la Vue | choix restrictif | choix restrictif | choix restrictif | choix restrictif | choix restrictif | choix restrictif | choix restrictif | valeur facultative |

Comportement attendu :

- en mode `Default (Vue)`, l'affichage et l'ordre sont visibles mais
  verrouillés ;
- en mode `Custom`, les colonnes autorisées sont activables et réordonnables
  par glisser-déposer ;
- un champ non affiché peut recevoir un filtre fixe ;
- une recherche interne permet de retrouver un champ dans une Vue volumineuse ;
- Liste, Recherche, Lien, Detail, Modifier, Export et Publié reprennent le plafond
  défini par la Vue mère ;
- si une capacité vaut Yes dans la Vue, le menu peut la désactiver puis la
  réactiver dans la limite autorisée par la Vue ;
- si une capacité vaut No dans la Vue, sa case est décochée, désactivée et ne
  peut jamais être activée par le menu ;
- les libellés et l'ordre Champ / Libellé, Liste, Recherche, Lien, Detail,
  Modifier, Export et Publié correspondent à ceux de `CB → View` ;
- chaque libellé possède une explication accessible par infobulle ;
- l'infobulle apparaît au survol ou au focus du libellé lui-même, sans ajouter
  de pictogramme visible `i`, `?` ou équivalent ;
- le champ de filtre utilise le message « Add a data filter » ;
- une commande permet de n'afficher dans l'éditeur que les colonnes
  sélectionnées ;
- aucun numéro `Order` n'est saisi manuellement ;
- l'ordre des lignes de filtres n'a aucun effet sur les données.

Cette présentation reste ajustable visuellement sans modifier le contrat
fonctionnel validé.

### 5.4 Syntaxe des filtres fixes

Les filtres sont définis par l'administrateur du menu, invisibles et non
modifiables sur le frontend.

La syntaxe RC08 est conservée :

- valeur simple : `Validé` ;
- commence par : `Route 1*` ;
- finit par : `*Gravel` ;
- contient : `*100*` ;
- alternatives : `Route 100 km|Route 150 km` ;
- combinaison : `Route 1*| *Gravel`.

La normalisation supprime les espaces placés autour de `|`. La dernière valeur
d'exemple devient donc `Route 1*|*Gravel`.

Règles de combinaison :

- `OU` entre les alternatives d'un même champ ;
- `ET` entre les filtres de champs différents.

Les filtres sont appliqués après les ACL et avant le tri, la limite totale et
la pagination.

## 6. Recherche frontend

La recherche frontend est distincte des filtres fixes.

### 6.1 Affichage

`Show Search` propose :

- `Use Default (Vue)` ;
- `Yes` ;
- `No`.

### 6.2 Portée

Le sélecteur séparé `Search fields` est supprimé. Lorsque les colonnes sont en
mode Custom, la colonne Recherche du tableau permet de choisir directement les
champs publiés recherchables pour ce menu. Ce choix peut surcharger le réglage
Search de la Vue, sans pouvoir utiliser un champ non publié.

Un champ peut participer à la recherche sans être affiché comme colonne. La
surcharge reste propre au menu et ne modifie jamais la configuration de la Vue.

## 6.3 Lien, champ Detail et template

La colonne `Lien` décide uniquement si une colonne de liste ouvre la fiche
Detail. La nouvelle capacité `Detail` décide si la valeur d'un champ peut être
injectée dans cette fiche.

Dans `CB → View`, `Detail` est placé juste avant `Edit` et constitue une
autorisation de la Vue mère :

- tout champ publié existant ou nouvellement créé utilise `Detail = Yes` par
  défaut afin de conserver le comportement historique ;
- `Detail = No` interdit effectivement l'affichage de la valeur, même si un
  ancien template contient encore son marqueur ;
- un template Detail verrouillé est généré uniquement avec les champs
  `Published = Yes` et `Detail = Yes` ;
- un template manuel non verrouillé n'est pas réécrit automatiquement, mais
  `Detail = No` empêche quand même l'injection de la donnée ;
- le template reste responsable de la structure, de l'ordre et de la mise en
  page des champs autorisés.

Le menu peut désactiver `Detail` pour sa liste spécialisée, mais ne peut pas
l'activer lorsque la Vue mère l'interdit. Cette restriction doit être appliquée
côté serveur au rendu de la fiche Detail.

Infobulle anglaise du libellé `Detail` :

> Detail displays selected fields of a record. Fields are selected in the
> View tab and require a template configured in the Detail tab. A menu item
> may hide a field enabled by the parent View, but cannot enable a field
> disabled by that View.

### 6.4 Relation Published, Detail et Edit

`Published` est l'autorisation principale d'un champ :

- lorsque `Published = No`, `Detail` et `Edit` sont sans effet et présentés
  comme indisponibles ;
- les valeurs Detail et Edit enregistrées sont conservées afin d'être
  restaurées si le champ est republié ;
- dans un menu, désactiver Published neutralise pour ce menu Liste, Recherche,
  Lien, Detail, Modifier et le filtre de données du champ ;
- cette restriction locale ne modifie jamais la Vue mère.

La valeur effective d'une capacité suit toujours la règle : autorisation de
la Vue ET autorisation du menu.

## 7. Tri initial

Le tri initial est séparé de l'ordre horizontal des colonnes et des filtres.

Modes :

- `Default (Vue)` ;
- `Custom`.

Le mode personnalisé reprend le principe de
`CB → View → Options → Sorting → Initial Sort Order` :

- jusqu'à trois critères ordonnés ;
- `ID` toujours disponible, même s'il n'est pas affiché ;
- champs actifs techniquement triables, visibles ou non ;
- aucun champ dupliqué ;
- direction `ASC` ou `DESC` propre à chaque critère.

Le visiteur peut ensuite retrier la liste en cliquant sur un titre de colonne
compatible. Ce tri interactif ne modifie pas le paramètre du menu.

## 8. Limite totale et pagination

### 8.1 Limite totale

La limite totale est distincte du nombre de lignes par page.

La Vue ContentBuilder NG définit `Maximum records` immédiatement sous
`Pagination size`. Sa valeur usine est `All` : aucune limite totale.

Le menu utilise le même contrôle Joomla :

- `Use Default (<valeur de la Vue>)` ;
- valeurs standard de pagination ;
- `All` ;
- saisie Custom.

Le menu peut réduire ou supprimer cette limite pour sa liste spécialisée sans
modifier la Vue. `All` est stocké comme `0` et l'héritage du menu comme `-1`.

Ordre d'application :

1. ACL et permissions ;
2. recherche ;
3. filtres ;
4. tri ;
5. limite totale ;
6. pagination.

L'export et la pagination travaillent sur le sous-ensemble limité.

### 8.2 Nombre de lignes par page

Le menu Joomla présente ce réglage sous le libellé `Lines per page`.

`Lines per page` conserve le contrôle partagé RC06 :

- héritage de la Vue puis de la configuration globale ;
- `0` = All ;
- entier positif = valeur explicite ou Custom ;
- valeurs issues de `pagination_choices`.

`List limit selector` utilise `Use Default (Yes/No) / Yes / No`.

Le contrôle `Maximum lines` utilise le même composant Joomla à liste
déroulante et saisie Custom que `Lines per page`.

Le libellé reste court. Son aide inline explique précisément qu'il s'agit du
nombre d'enregistrements affichés par page à l'ouverture de la liste, que la
valeur peut être héritée de la configuration globale puis surchargée par un
menu Joomla, et que `All` affiche la liste complète sans pagination.

## 9. Actions d'interface

Les options d'interface utilisent individuellement :

- `Use Default (Yes/No)` ;
- `Yes` ;
- `No`.

Options concernées :

- Excel export ;
- Detail - Print, qui conserve strictement l'impression historique de la fiche
  Detail et n'ajoute aucune impression de liste ;
- Rating, uniquement lorsque la Vue fournit déjà cette fonction.

`Back button` et `Lines per page` ne sont pas dupliqués dans ce groupe :
ils conservent les contrôles communs déjà validés en RC08/RC06.

Toute option `Use Default` affiche la valeur réellement héritée de la Vue,
par exemple `Use Default (Yes)` ou `Use Default (No)`.

Les listes Oui/Non utilisent l'état couleur Joomla validé en RC08 : Oui vert,
Non rouge, héritage neutre.

Les options `Show State`, `Show bulk state changer` et `Show State filter` proposent séparément
`Use Default (Yes/No) / Yes / No`.

`Show Search` hérite de `show_filter`, `Show State` hérite de `list_state` et
`Show State` pilote l’affichage individuel de l’état et les contrôles par ligne.
`Show bulk state changer` est une option de la Vue, placée dans le panneau
`Panneau d’affichage des boutons`. Elle pilote uniquement l’outil groupé de
changement d’état, est indépendante de `Choisir` et de la colonne `État`, et
hérite de `list_state_bulk`. L’outil est rendu dans la barre d’actions de la
liste, jamais dans la zone des filtres. Une surcharge de menu peut encore
forcer cette option indépendamment. `Show State filter` hérite exclusivement de
`show_state_filter` et correspond au filtrage réel par état. Un menu peut
donc masquer ou afficher le filtre d'état sans modifier l'affichage des états ni
le filtre de recherche. La valeur par défaut de Vue pour le filtre d'état est No.
Le filtre d'état reste absent si aucun état n'est publié.

Une option d'affichage ne contourne jamais une permission applicable et ne
crée pas une fonctionnalité absente de la Vue.

## 10. Actions frontend du menu

La Vue et les ACL Joomla constituent toujours le plafond des droits. Le menu
peut ajouter une interdiction, mais ne peut jamais accorder un droit.

Chaque opération protégée propose uniquement :

- `Permissions de la vue`, qui conserve les droits frontend définis dans la
  Vue pour les groupes et le propriétaire du record ;
- `Disabled`.

`Disabled` utilise l'état rouge des listes Joomla. L'héritage reste neutre.
Il n'affiche aucun résumé Oui/Non, car l'absence d'une autorisation globale ne
constitue pas un refus explicite pour chaque utilisateur.

Restrictions individuelles :

- Create ;
- Detail ;
- Edit ;
- Delete ;
- Publish/Unpublish ;
- State actions.

Une restriction `Disabled` doit être contrôlée côté serveur. Masquer seulement
le bouton est insuffisant. Un administrateur autorisé à modifier un menu ne
peut pas s'attribuer une permission ContentBuilder NG par ce menu.

Lorsque Detail est désactivé, aucun lien vers la fiche détaillée n'est
généré dans la liste.

Le libellé visible de l'ACL historique `View` devient le mot unique `Detail`.
Sa clé technique reste `view` afin de conserver toutes les permissions
existantes. `List Access` contrôle l'accès à la liste ; `Detail` contrôle
l'ouverture et l'affichage d'un enregistrement individuel.

Dans le tableau des colonnes, une case héritée non modifiable reçoit un fond
gris et une infobulle explicative. Une capacité absente de la Vue reste
verrouillée et ne peut jamais être activée dans le menu.

## 11. Export

L'export conserve le mécanisme actuel et respecte le contrat du nouveau menu :

- uniquement les champs publiés et autorisés pour Export par la Vue ;
- en mode Custom, la sélection Export propre au menu, indépendamment des
  colonnes affichées dans la liste ;
- valeurs des états lorsque les états sont présents ;
- ACL ;
- filtres fixes ;
- recherche frontend ;
- tri courant ou initial ;
- limite totale ;
- toutes les lignes du résultat autorisé, pas seulement la page visible ;
- aucune donnée provenant d'un champ non autorisé pour Export.

Le lien XLSX transporte explicitement le contexte effectif du menu vers le modèle
d'export : filtres fixes, champs de recherche, sélection Export, publication locale,
tri, directions et limite. Les filtres frontend ou externes actifs complètent les
filtres fixes du menu ; ils ne les remplacent pas. La sélection Export est appliquée
dans l'ordre configuré dans le menu, sans revenir à l'ordre de la Vue mère.

Le menu peut également choisir le nom du fichier XLSX. Le mode `default`, utilisé
pour les anciens menus et les valeurs absentes, conserve strictement le nom actuel
`CB_export_<Vue>_YYYY-MM-DD_HHMM.xlsx`. Le mode `custom` transmet un nom de base
sans extension ; après suppression des contrôles, chemins et caractères interdits,
les espaces sont remplacés par `_` et le résultat devient
`<Nom>_YYYY-MM-DD_HHMM.xlsx`. Un nom vide après nettoyage revient au mode par défaut.
Cette option ne s'applique pas aux exports lancés hors d'un menu CB List View.

Un champ peut donc être absent des colonnes affichées et rester présent dans le
fichier XLSX. Le menu peut retirer un champ exportable autorisé par la Vue, mais
ne peut jamais ajouter un champ dont la capacité Export est désactivée dans la
Vue ou dont la publication est désactivée.

Le classeur XLSX utilise les types de tri explicites de la Vue lorsqu'ils sont
définis. Sinon, il détecte prudemment les colonnes entièrement numériques ou
temporelles, y compris les champs de choix BreezingForms. Les colonnes mixtes,
les nombres avec zéro initial et les formules restent du texte. Cette conversion
est interne et n'ajoute aucun réglage dans la Vue ou le menu.

## 12. Groupes de paramètres

Ordre fonctionnel validé dans RC09-B13 :

1. Le message d'information général, placé en haut de l'onglet.
2. Select View, Theme et Reset to Default, sans sous-titre « List View » redondant.
3. Lines per page.
4. Maximum lines, hérité de la Vue.
5. Initial Sort Order ; en mode Custom, jusqu'à trois couples champ/direction
   sont affichés immédiatement sous le sélecteur.
6. View introduction, dernier réglage du bloc principal List View ; Custom
   introduction apparaît sur la ligne suivante lorsqu'il est activé.
7. **Display - Detail - Edit**
   - première ligne : Excel export, Back button et Rating ;
   - deuxième ligne : Detail - Top panel, Detail - Bottom panel et
     Detail - Print ;
   - troisième ligne : Edit - Top panel, Edit - Bottom panel et Edit - List button.
   - quatrième ligne : mode du nom de fichier XLS et nom personnalisé conditionnel.
8. **Search and State**
   - Show Search ;
   - Show State ;
   - Show bulk state changer ;
   - Show State filter.
9. **Frontend actions**
   - Create ;
   - Detail ;
   - Edit ;
   - Delete ;
   - Publish ;
   - States.
10. **Displayed columns**, avec les filtres de données.
11. **Article**, groupe peu utilisé placé en fin d'onglet.

Les intitulés d'organisation « New » et « Classic » ne sont pas affichés dans
les groupes de réglages. Chaque sous-catégorie utilise le séparateur Joomla
validé sur le List View historique : trait fin, titre centré, trait fin.

Le groupe **Display** fusionne les réglages d'affichage et les anciennes
« Actions ». Le terme Action n'est pas utilisé comme titre de groupe, car il
ne décrit pas correctement ces contrôles visuels. Le groupe
**Frontend actions** contient les contrôles qui peuvent interdire, pour ce
menu, des opérations autorisées par la Vue ; il ne s'agit pas d'un éditeur
d'ACL et ces contrôles ne peuvent jamais accorder une autorisation. Le choix
`View permissions` reste neutre et n'affiche aucun résumé Oui/Non. Son
infobulle rappelle que les droits effectifs dépendent des groupes, des règles
de propriété et des privilèges Joomla.

**Edit - List button** est un réglage d'affichage distinct. Il hérite de
`View > Options > Edit button` ou masque uniquement le crayon de modification
dans la liste. Il ne modifie ni les ACL, ni l'accès à l'écran Edit depuis un
autre emplacement.

## 13. Reset to Default

Le Reset du nouveau List View :

- conserve la Vue sélectionnée ;
- supprime réellement toutes les surcharges propres au nouveau menu ;
- rétablit l'héritage dynamique de la Vue ;
- supprime les colonnes personnalisées ;
- supprime les filtres fixes du nouveau menu ;
- supprime tout ancien paramètre de filtre Classic encore présent ;
- déclenche l'état modifié du formulaire Joomla.

Il restaure aussi visuellement et fonctionnellement chaque famille de champs :
introduction, actions d'affichage, recherche et états, restrictions, colonnes,
recherche par colonne, liens, Detail/Edit/Published, filtres de données, ordre
personnalisé, directions de tri, nombre de lignes et limite maximale. L'ordre
des colonnes revient à celui de la Vue mère.

Il ne copie jamais les valeurs courantes de la Vue dans le menu.

## 13.1 Persistance Joomla

Le constructeur enregistre toute sa configuration dans `cb_new_config`.

- la synchronisation vers le champ Joomla est assurée par l'asset JavaScript
  externe déclaré dans `joomla.asset.json` ;
- aucun script inline n'est requis ;
- la valeur est resynchronisée avant la soumission du formulaire ;
- après Save ou Save & Close, les valeurs personnalisées sont restaurées à
  l'identique ;
- chaque modification déclenche l'état modifié natif du formulaire Joomla.

## 14. Migration définitive des anciens menus Classic

La migration RC09 supprime entièrement le type de menu, le XML, le wrapper PHP
et les trois anciens FormFields de List View Classic.

Pendant l'installation ou la mise à jour :

- chaque lien `layout=listclassic` devient `layout=default` ;
- les paramètres `cb_list_filterhidden`, `cb_list_orderhidden` et
  `cb_list_filter` sont supprimés à la racine et dans l'ancien groupe
  `params.settings` ;
- les autres réglages du menu sont conservés ;
- un menu sans ancienne configuration est migré silencieusement ;
- si un filtre ou un ordre historique non vide est supprimé, Joomla affiche un
  warning traduit contenant le titre et l'identifiant du menu ;
- le warning demande de recréer les filtres dans
  `Displayed columns → Data filter` et le tri dans
  `Initial sort order → Custom` ;
- les cinq anciens fichiers déjà installés sont supprimés physiquement par le
  script de mise à jour.

À partir de B15, les nouveaux Data filters utilisent exclusivement le stockage
JSON `cb_menu_data_filters`, commun aux listes, détails, formulaires Edit et
exports. L'ancien stockage tabulé `cb_list_filterhidden`, son ordre parallèle
`cb_list_orderhidden` et leur parser frontend sont supprimés. Les anciennes clés
ne subsistent que dans le code de migration chargé de nettoyer la base.

À partir de B19, le nom du paramètre `cb_menu_data_filters` est défini une seule
fois par `MenuDataFilterService::INPUT_NAME`. Le code actif emploie uniquement
le vocabulaire Data filter (`sanitizeDataFilterTerm`, `recordFilters`) ; les
anciens noms « hidden filter » ne sont conservés que comme littéraux dans la
migration et ses tests afin de reconnaître puis supprimer les anciennes données.
Cette réorganisation interne ne modifie ni la syntaxe `|` / `*`, ni le stockage
JSON, ni l'application commune des filtres aux listes, détails, éditions et
exports.

L'ordre de toutes les lignes du constructeur est enregistré séparément dans
`columnOrder`. Il ne dépend jamais de la liste des colonnes visibles. Un champ
masqué peut donc rester à la position choisie et participer à la recherche, au
lien, au détail ou à un Data filter sans redevenir visible après sauvegarde.

## 14.1 Layouts spécialisés

`List View (Cards)`, `List View (Compact Table)` et `List View (Tiles)` :

- exposent exactement les mêmes champs XML que `List View` ;
- utilisent le même constructeur `menulistbuilder` ;
- appliquent les mêmes colonnes, recherche, filtres de données, tri,
  pagination, affichages et restrictions ;
- conservent leur wrapper PHP et leur rendu visuel spécialisé ;
- ne dupliquent aucune liste d'options ni logique de configuration.

## 15. Interface, langues et aide

- Utiliser les FormFields, layouts, tableaux, boutons et comportements Joomla
  natifs.
- Aucun CSS ne doit imiter un contrôle Joomla.
- Le JavaScript personnalisé doit rester minimal.
- Tous les textes passent par des clés de langue alignées en `en-GB`, `fr-FR`
  et `de-DE`.
- Les options de valeur par défaut utilisent `Use Default (%s)` en anglais,
  `Paramètre par défaut (%s)` en français et `Standardeinstellung (%s)` en
  allemand. Les mentions dans les descriptions suivent cette terminologie.
- Sans valeur affichée, préciser la source entre parenthèses : `View`, `Vue`
  ou `Ansicht`. Avec une valeur affichée, conserver la valeur réellement héritée.
- `Use Global` / `Paramètres généraux` / `Globale Einstellungen` est réservé
  à l’héritage des paramètres généraux, pas à celui d’une vue.
- Appliquer la règle à toutes les copies administrateur et site (`.ini`,
  `.sys.ini`, `.menu.ini`), conformément à la spécification de traduction
  [joomla-translations](../../.agents/skills/joomla-translations/SKILL.md).
- Chaque option possède une aide inline courte.
- Les aides courtes utilisent le mécanisme Joomla `Toggle Inline Help` et sont
  masquées par défaut. L'aide de syntaxe des filtres de données reste visible
  en permanence.
- Les sélecteurs utilisent une largeur limitée à 80 % de la largeur RC08
  précédente, tout en revenant à 100 % de la largeur disponible sur petit
  écran.
- Les exemples de filtres utilisent du code visuellement identifiable.
- Aucun texte technique de stockage n'est exposé à l'utilisateur.

## 16. Critères d'acceptation

Tous les critères ci-dessous décrivent le comportement RC09-B20 validé et
publié sous 6.1.10-RC09.

1. List View est l'unique type de menu tabulaire classique ; aucun type Classic n'est découvrable.
2. La sélection de Vue est obligatoire et le thème est héritable.
3. Default reprend les colonnes et leur ordre depuis la Vue.
4. Custom sélectionne et réordonne uniquement les colonnes autorisées.
5. Un champ masqué peut filtrer ou participer à la recherche sans être affiché.
6. Les filtres supportent `|` et `*` conformément à RC08.
7. La recherche Default/Custom respecte la portée choisie.
8. Le tri accepte ID et jusqu'à trois champs avec directions indépendantes.
9. La limite totale intervient après filtrage et tri.
10. Pagination, All et Custom ne régressent pas.
11. Les restrictions du menu réduisent les ACL et ne les étendent jamais.
12. L'export ne divulgue aucune colonne masquée et conserve les états présents.
13. Le Reset conserve la Vue et supprime toutes les surcharges du List View.
14. La mise à jour migre automatiquement les liens Classic et nettoie leurs paramètres obsolètes.
15. L'interface est utilisable sur une Vue comportant de nombreux champs.
16. Le rendu frontend reste celui du tableau actuel avec tri par en-tête.
17. Save et Save & Close conservent toutes les options du constructeur.
18. Le constructeur est aligné sur la largeur des autres groupes Joomla.
19. Les valeurs héritées Oui/Non restent visibles dans les options Use Default,
    sauf pour les actions frontend qui affichent seulement View permissions.
20. Back button et Lines per page ne sont présents qu'une seule fois.
21. Export No masque l'export et Detail Disabled bloque les liens et l'accès direct.
22. Les cases Liste, Recherche, Lien, Detail, Modifier, Export et Publié du mode
    Custom sont modifiables uniquement dans la limite autorisée par la Vue mère.
23. Les sélecteurs sont limités à une largeur lisible et les valeurs Oui/Non utilisent les couleurs Joomla verte/rouge.
24. Search, State et State filter proposent séparément Use Default/Yes/No et
    héritent chacun de leur propre option de Vue.
25. Maximum lines utilise le contrôle commun à liste déroulante, All et saisie Custom.
26. Les nouvelles options exposent une aide courte via Toggle Inline Help.
27. Detail No empêche toute injection de la valeur dans la fiche, quel que soit
    le contenu historique du template.
28. Un champ Published No ne peut pas être activé dans Detail ou Edit par un
    menu Joomla.
29. L'ACL visible Detail conserve la clé technique historique `view`.
30. Le message d'information est placé en haut et Article en fin d'onglet.
31. Display - Detail - Edit regroupe les contrôles Export, Print et Rating,
    les barres Detail/Edit et le bouton Retour.
32. Frontend actions remplace le titre Security sans modifier le plafond
    d'autorisation fourni par la Vue.
33. Chaque sous-catégorie utilise un séparateur fin avec son titre centré.
34. View introduction et Custom introduction sont conservés, alignés avec les
    autres contrôles horizontaux et terminent le bloc principal List View.
35. L'aide permanente des filtres se termine par l'exemple
    `data1 | data2* | da*ta3 | *data4`, sans point final.
36. Les sélecteurs de Display et Search and State font 300 px au maximum et
    restent responsives.
37. Chaque critère de tri de la Vue possède sa propre direction ASC/DESC.
38. Publier ou dépublier un champ met immédiatement à jour les cadenas Detail
    et Edit sans sauvegarde intermédiaire.
39. Un critère de tri inutilisé (`None`) utilise `Ascending` comme direction
    par défaut dans la Vue et dans le menu.
40. Lines per page, Maximum lines et Initial Sort Order sont regroupés en haut
    de page, sans sous-catégorie Pagination and Sorting.
41. Custom introduction affiche un compteur Unicode `N/255` et refuse toute
    saisie au-delà de 255 points de code sans couper un caractère Unicode.
42. Reset to Default restaure toutes les familles de champs dynamiques, les
    trois critères de tri et l'ordre de la Vue, tout en conservant la Vue.
43. Les deux chemins AJAX de publication, dont le gestionnaire délégué utilisé
    par l'interface, mettent immédiatement à jour les cadenas Detail/Edit.
44. Custom introduction est une zone de texte de deux lignes qui grandit
    automatiquement jusqu'à cinq lignes, puis utilise un ascenseur vertical.
45. Son compteur affiche `N/255 · X ligne(s)` ; seuls les retours volontaires
    sont comptés et ils sont conservés dans le rendu frontend.
46. Print conserve strictement son fonctionnement Detail historique, porte le
    libellé Detail - Print et se trouve immédiatement sous Detail - Bottom panel.
    Dans CB → Vue → Options, sa case est regroupée avec Export XLS sur la même
    ligne. Cette disposition n'ajoute aucun bouton d'impression de liste.
47. Rating termine le groupe Display - Detail - Edit et les deux contrôles
    déplacés utilisent le même alignement horizontal que les panneaux.
48. Tous les contrôles de Display - Detail - Edit partagent la même grille
    horizontale ; leurs libellés sont préfixés par Detail - ou Edit - afin de
    rendre explicite l'écran concerné.
49. Le groupe est ordonné en trois lignes compactes : Excel export, Back button
    et Rating ; Detail - Top panel, Detail - Bottom panel et Detail - Print ;
    puis Edit - Top panel et Edit - Bottom panel.
50. Cards, Compact Table et Tiles utilisent exactement le contrat de champs du
    nouveau List View tout en conservant leur rendu spécialisé.
51. Les menus Classic existants deviennent des List View sans perdre leurs
    paramètres modernes compatibles.
52. Un warning traduit et ciblé est affiché uniquement lorsqu'un filtre ou un
    ordre historique non vide est supprimé.
53. Dans la Vue mère, un champ dépublié affiche un cadenas dans Detail ou Edit
    uniquement lorsque la capacité correspondante était activée. Une capacité
    déjà désactivée reste représentée par une croix grise non interactive. La
    republication restaure exactement les valeurs Detail/Edit conservées.
54. L'ordre du constructeur est conservé pour toutes les lignes, y compris une
    colonne List désactivée utilisée uniquement par Search, Link, Detail ou Data filter.
55. Les Data filters utilisent le format JSON interne `cb_menu_data_filters` ;
    aucun parser ou FormField Classic n'est utilisé pendant l'affichage frontend.
56. Les valeurs héritées Oui/Activé utilisent un vert léger, les valeurs
    héritées Non/Désactivé un rouge léger, les héritages non booléens restent gris
    et les surcharges explicites conservent les couleurs Joomla fortes.
57. Chaque action frontend possède une infobulle sans icône précisant qu'elle peut
    conserver ou réduire l'ACL de la Vue, sans jamais l'étendre. View permissions
    reste neutre et n'affiche pas de résumé Oui/Non.
58. Une Vue non publiée sélectionnable dans un menu fournit elle aussi ses
    valeurs héritées réelles au constructeur.
59. Le bouton Close preview ferme l'onglet ouvert par Preview ; si le navigateur
    interdit la fermeture, il utilise le retour administration comme repli.
60. Preview utilise les réglages et le thème de la Vue, sans surcharge d'un
    élément de menu Joomla et sans Data filter propre à un menu.
61. Les champs Edit en lecture seule utilisent une présentation uniforme quel
    que soit l'origine de la restriction (Vue ou menu) : badge Read-only,
    curseur interdit, valeur lisible en italique et valeur sélectionnée visible
    pour les groupes radio.

## 17. Hors périmètre RC09

- fusion de Cards, Compact Table ou Tiles dans un seul type de menu ;
- modification de leur rendu frontend spécialisé ;
- iframe, `height`, `loading=lazy` ou `loading=eager` ;
- modification du plugin CBList ;
- ajout de permissions depuis un menu ;
- migration automatique des filtres Classic vers le nouveau format.
