# Interface partagée et Cards CSS

Pour les boutons des écrans Liste, Détail et Éditer, appliquer la
[charte graphique frontend](frontend-graphic-charter.md). Les palettes des
Cards ci-dessous ne remplacent pas le style neutre des boutons d’action.

L'asset Joomla commun `com_contentbuilderng.cards` charge
`media/css/cards.css`. CBList, CBStats et les futures extensions utilisent cet
asset sans dupliquer les styles. Toutes les classes partagées sont préfixées
`cb-`. Le rendu historique reste le défaut et la Card ne fixe jamais la grille
ou le nombre de colonnes de l'article.

La syntaxe commune est `card=h1` à `card=h6`, ou `card=v1` à `card=v6`. Les
classes sont `cb-card`, `cb-card-header`, `cb-card-body`, `cb-card-h1` à
`cb-card-h6` et `cb-card-v1` à `cb-card-v6`. Un header est créé uniquement si
une clé `title` explicite dans `labels=` contient du texte et `hide="title"` est absent. Pour toutes les variantes H et V, le
titre reste horizontal et placé au-dessus du contenu. Les variantes H occupent
la largeur disponible. Les variantes V sont compactes, se juxtaposent lorsque
l'espace le permet et repassent en pleine largeur sous 768 px.
Les titres des Cards sont centrés par défaut.

CBList et CBStats partagent le même contrat : `labels="title=..."` fournit le
titre normal ou le titre de Card, et `hide="title"` le masque. `labels` ne
contient aucune valeur spéciale de masquage.

Le titre est rendu en `h4` par défaut. Le dernier `|` de la valeur `title` dans `labels=` peut
indiquer un niveau `h1` à `h6`, sans distinction de casse, ou une taille
visuelle positive `remX` / `remX.X`. Les espaces autour du séparateur sont
ignorés. Avec `rem`, le niveau sémantique reste `h4`. Un suffixe inconnu reste
dans le titre complet, qui utilise alors le rendu `h4` par défaut.

```text
labels="title=Départements | h4"
labels="title=Départements | rem1.25"
```

L'option publique facultative `w=` règle la largeur de la Card dans la grille :
`w=33` occupe une colonne, `w=66` deux colonnes et `w=100` toute la ligne.
Seules ces trois valeurs numériques sans guillemets sont admises et `w=` exige
la présence de `card=`. Sans `w=`, une variante V vaut 33 et une variante H
vaut 100. Si l'espace restant est insuffisant, CSS Grid place la Card sur la
ligne suivante. Sur petit écran, toutes les largeurs passent à 100.

`w=` règle la largeur de la Card ; l'option CBStats `width=` règle uniquement
la largeur du graphique à l'intérieur de cette Card.

Le conteneur commun facultatif `.cb-cards` organise trois variantes V par ligne
sur PC. Une variante H placée directement dans ce conteneur occupe la ligne
complète. Sous 768 px, le conteneur passe à une colonne et toutes les largeurs
`w=33`, `w=66` et `w=100` occupent la ligne complète. Toutes les Cards à
juxtaposer doivent être dans le même conteneur, sans élément `<br>` entre elles.

Lorsqu'un éditeur tel que JCE enveloppe une balise CBStats ou CBList dans un
élément `span` directement sous `.cb-cards`, ce wrapper doit rester transparent
pour la grille. Les largeurs `w=33`, `w=66` et `w=100`, le comportement pleine
largeur des variantes H et le passage à une colonne sous 768 px doivent être
identiques à ceux d'une Card enfant directe. Cette tolérance vise uniquement un
`span` contenant une seule Card et ne modifie pas la syntaxe publique.

```html
<div class="cb-cards">
{CBStats id=15 field=Groupe output=pie labels="title=Groupes" card=v1 w=33}
{CBStats id=15 field=Prenom output=bar labels="title=Prénoms" card=v2 w=66}
{CBList id=15 fields="Nom|Prenom|Email" labels="title=Derniers inscrits" card=h1 w=100}
</div>
```

Pour présenter des chiffres clés dans n'importe quelle Card H1 à H6 ou V1 à
V6, utiliser une liste de définitions `.cb-kpi-list`. Chaque ligne contient un
`dt` pour le libellé et un `dd` pour la valeur. Les styles partagés alignent les
valeurs, renforcent leur graisse et ajoutent un séparateur discret :

```html
<dl class="cb-kpi-list">
  <div>
    <dt>Inscriptions</dt>
    <dd>{CBStats id=15 output=total}</dd>
  </div>
  <div>
    <dt>Places restantes</dt>
    <dd>{CBStats id=15 output=remaining target=200}</dd>
  </div>
</dl>
```

Les classes éditoriales facultatives `.cb-brm-heading`, `.cb-brm-lead` et
`.cb-brm-latest` structurent respectivement l'en-tête centré, son texte
d'introduction et l'ancre vers la liste. Elles n'ajoutent aucune syntaxe à
CBStats ou CBList.

Les propriétés publiques sont `--cb-card-accent`, `--cb-card-header-bg`, `--cb-card-header-color`,
`--cb-card-bg`, `--cb-card-color` et `--cb-card-border-color` :

```css
.cb-card-h1 {
    --cb-card-header-bg: #005a9c;
    --cb-card-header-color: #fff;
}
```

Palette par défaut : H1/V1 bleu `#2878b5`, H2/V2 vert `#5a9b55`, H3/V3
orange `#e09335`, H4/V4 violet `#8b63a8`, H5/V5 rouge `#c75b5b` et H6/V6
ardoise `#607d8b`.
