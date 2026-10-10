# Charte graphique frontend — ContentBuilder NG

## Référence validée pour 6.1.16-RC1

Le format des boutons **Éditer / Imprimer** dans Détail est la référence pour
les barres d’actions des écrans Liste, Détail et Éditer. L’harmonisation porte
sur la typographie et les marges, pas uniquement sur la couleur au survol.

## Typographie et dimensions

- Police héritée du template Joomla ; aucune police imposée par CB.
- Taille : `--cb-font-size-compact`, soit `0.85rem`.
- Graisse : `600`.
- Marges intérieures : `--cb-button-padding-block` (`0.34rem`) et
  `--cb-button-padding-inline` (`0.85rem`).
- Boutons autonomes arrondis ; conserver les jonctions natives du groupe
  Recherche / Réinitialiser, sans arrondir séparément ses boutons.
- Ces règles sont partagées dans `media/css/frontend.css`, sans copie de
  valeurs différente pour Nouveau, Export XLS ou les actions de Détail.

Les boutons concernés sont Nouveau, Supprimer, Export XLS, Rechercher,
Réinitialiser, Imprimer, Éditer, Retour, Enregistrer, Appliquer et les paramètres
d’article, lorsqu’ils sont rendus dans les barres d’actions. La pagination, les
liens de navigation ordinaires et les contrôles internes des champs ne sont pas
transformés en boutons de barre d’actions.

## Couleurs et interaction

| Actions | Style |
| --- | --- |
| Nouveau, Imprimer, Éditer, Retour, Réinitialiser | Fond transparent au repos, texte et contour gris, survol gris natif |
| Enregistrer, Appliquer, Paramètres de l’article | Même style neutre ; aucun fond rouge, bleu ou bleu-vert ajouté |
| Supprimer | Conserver le contour rouge et le comportement destructif |
| Export XLS | Conserver le style vert d’export ; typographie et marges communes |
| Rechercher | Conserver la couleur d’action existante ; typographie et marges communes |

Le style neutre utilise `btn btn-sm btn-outline-secondary`. Ne pas le remplacer
par `btn-primary` : certains templates définissent cette classe en rouge.
Ne pas ajouter de couleur spécifique basée sur `--link-color` pour Éditer.
Les couleurs exactes, transitions, états actifs, désactivés et focus restent
ceux du template et du thème CB. Le focus clavier doit rester visible.

## Mise en page à préserver

- Le champ où l’on saisit la recherche conserve une largeur de **180 px**.
- Sur écran assez large, conserver les contrôles de la barre sur la même ligne.
- Sur petit écran, autoriser le retour à la ligne sans débordement horizontal.
- Dans les options administrateur, Imprimer reste à côté d’Export XLS.
- Le bloc Tri précède les menus référents ; ses listes utilisent
  `form-select form-select-sm`. Son cadre conserve un padding de 8 px (`p-2`)
  et les lignes un espacement de 4 px (`gap-1`, `mb-1`).

## Titres de liste et introduction — référence validée en 6.1.22.3

- Le titre de page et le titre Custom utilisent `<h1 class="h3 cb-list-title">` :
  la sémantique reste celle du titre principal, avec la taille H3 native du template.
- Khepri, Thoth, Dark et Blank ne réduisent pas cette taille sur mobile.
  Avec Cassiopeia et une base de 16 px, H3 vaut `calc(1.3rem + .6vw)`,
  puis `1.75rem` à partir de 1 200 px (environ 23 px à 390 px et 28 px sur grand écran).
- Le conteneur `.cb-list-titlebar` et le bloc `.cb-list-intro` ont un retrait
  horizontal de `1rem` de chaque côté, y compris sur mobile et tablette.
- Aucun trait ni fond décoratif n'est ajouté sous le titre. Les titres longs
  peuvent revenir à la ligne sans troncature ni débordement.
- View / Intro reste du HTML administrable : CB conserve sa mise en forme et
  ses niveaux de titres. Le retrait commun ne transforme pas automatiquement
  les titres enregistrés en H3 et ne change pas le texte de l'introduction.
- La taille peut être surchargée via `.cb-list-title`. Sa couleur utilise
  `--cb-list-title-color`, puis les variables du template `--heading-color`,
  `--body-color`, `--bs-body-color`, et enfin l'héritage CSS.

## Mode sombre : respecter le template Joomla

Le thème CB ne doit modifier ni le `body` global, ni les fonds des conteneurs
Joomla `.site-grid`, `.container-component` et `.component-content`.
Le titre et l'introduction suivent la palette et le fond du template, sans
imposer un fond noir ou blanc. La préférence sombre du téléphone ne signifie
pas que le template Joomla a lui-même adopté une palette sombre.

Les blocs CB (tableau de données, filtres, panneaux) peuvent conserver leurs
styles sombres locaux. Le fond et le texte des cellules doivent rester lisibles
ensemble, ainsi que les liens, lignes alternées et états au survol. Préserver
les couleurs d'état des enregistrements définies explicitement. Respecter le
mode explicite `data-bs-theme="light"` ou `data-bs-theme="dark"` du template.

Cas de régression : sur Cyclo, le template Cassiopeia personnalisé conserve
un dégradé blanc sur son image de fond. L'ancienne règle Thoth rendait le texte
global presque blanc en mode sombre mobile ; le titre devenait illisible.
Khepri seul ne reproduisait pas ce défaut. Depuis 6.1.22.3, Thoth laisse le
template gérer la page et le titre reste lisible sans surcharge propre à Cyclo.

Vérifier le CSS effectivement chargé : une surcharge de thème dans le menu peut
prendre le pas sur le thème de la Vue. Comparer les modes clair, sombre système
et sombre explicite, avec fond personnalisé, à 390, 768 et 1 280 px. Contrôler
le titre, l'Intro et les données séparément ; conserver la possibilité d'une
surcharge CSS. La reproduction HTML/CSS en navigateur complète le test sur le
site réel sans le remplacer ; la RC1 a été validée par Gilles avant publication.

## Vérification avant validation

Comparer les styles calculés des boutons à Éditer/Imprimer : famille de police,
taille, graisse, hauteur de ligne et padding. Vérifier le repos, le survol, le
focus clavier et l’état désactivé, ainsi que les écrans Liste, Détail et Éditer.
Contrôler les couleurs fonctionnelles de Supprimer et Export XLS, la largeur de
recherche et l’absence de débordement. Un test sur un fragment HTML ou sur un
seul template ne vaut pas validation de tous les templates et thèmes CB.

Les captures et scripts intermédiaires restent dans `qa-artifacts/` ; `build/`
ne contient que les ZIP locaux. Cette charte est interne au dépôt et n’entre
pas dans les paquets installables.
