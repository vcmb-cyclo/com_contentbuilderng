# Templates et personnalisation

ContentBuilder NG utilise des templates configurés dans chaque vue pour produire les
détails, les formulaires d'édition, les articles et certaines présentations de liste.

## Commencer par un exemple généré

Les plugins de thème peuvent générer :

- un exemple de template détail ;
- un exemple de template éditable ;
- du CSS et du JavaScript associés.

Thèmes livrés :

- Thoth ;
- Dark ;
- Blank ;
- Khepri, hérité de ContentBuilder.

Le thème Thoth est utilisé comme repli lorsque le thème demandé n'est pas
disponible.

Procédure conseillée :

1. sélectionnez un thème ;
2. enregistrez la vue ;
3. générez l'exemple ;
4. testez-le sans modification ;
5. dupliquez le contenu avant une personnalisation importante ;
6. versionnez votre template.

## Variables de champs

Les templates générés utilisent des noms de champs. Les templates d'e-mail emploient
notamment :

```text
{nom:label}     le libellé du champ
{nom:value}     la valeur du champ
{nom:item}      le contrôle de saisie du champ dans un template d'édition
{value}         la valeur brute dans un wrapper de colonne
{value_inline}  la valeur brute dans un wrapper d'article
{webpath nom}   le chemin web absolu d'un fichier envoyé
{CBSite} / {cbsite}   l'URL racine du site
{hide-if-empty nom} ... {/hide}   masque un bloc si le champ est vide
{hide-if-matches nom valeur} ... {/hide-if-matches}   masque un bloc si le champ vaut exactement cette valeur
```

Ces remplacements sont effectués par le service de rendu (`TemplateRenderService`).
Utilisez les exemples générés pour votre vue comme référence prioritaire, car les
champs disponibles dépendent de la source.

## Conditions d'affichage

`{hide-if-empty nom} ... {/hide}` masque le bloc lorsque la valeur du champ est vide.
`{hide-if-matches nom valeur} ... {/hide-if-matches}` masque le bloc lorsque la
valeur courante du champ correspond exactement à `valeur`.

Dans les templates Détail, ces conditions s'appliquent aux valeurs affichées.
Dans les templates Édition, elles s'appliquent aussi aux blocs en lecture seule
utilisant `{nom:value}`. En revanche, un bloc contenant `{nom:item}` reste affiché
même si la valeur est vide ou correspond à `hide-if-matches`, afin de permettre la
saisie ou la correction du champ.

## Exemple simple d'e-mail

```html
<p>Nouvelle demande :</p>
<p><strong>{nom:label}</strong> : {nom:value}</p>
{hide-if-empty message}
<p><strong>{message:label}</strong> : {message:value}</p>
{/hide}
```

## Préparation PHP

Les onglets Détail et Édition comportent une zone de préparation exécutée avant le
rendu. L'interface fournit des exemples et des sélecteurs de snippets.

Risques :

- erreur PHP rendant la vue inaccessible ;
- exposition de données sensibles ;
- incompatibilité après modification d'un champ ;
- ralentissement si le code effectue des requêtes répétées ;
- contournement involontaire de l'échappement HTML.

Réservez cette fonction aux administrateurs techniques. Testez sur une copie du site.

## Wrappers de colonnes

Une colonne de liste peut appliquer un wrapper autour de sa valeur. Les fichiers de
langue donnent trois catégories d'usage :

- HTML avec `{value}` ;
- code PHP transformant `$value` ;
- balise de plugin de contenu.

Exemple HTML :

```html
<strong>{value}</strong>
```

N'insérez jamais directement une valeur non fiable dans un attribut HTML ou du
JavaScript sans échappement adapté.

## Plugins de contenu

Balises détectées :

```text
{CBDownload ...}
{CBImageScale ...}
{CBRating ...}
{CBVerify ...}
{CBStats ...}
```

CBStats insère dans les contenus Joomla des statistiques dynamiques provenant
d'une vue ContentBuilder NG. Sa syntaxe générale est :

```text
{CBStats id=IdVue ...}
```

Exemples :

```text
{CBStats id=25 output=total}
{CBStats id=25 output=form_name}
{CBStats id=25 field=Parcours output=table}
{CBStats id=25 field=Parcours output=json sort=title dir=asc}
{CBStats id=25 field=Parcours output=pie sort=value dir=desc}
{CBStats id=25 field=Parcours output=bar sort=value dir=desc}
{CBStats id=25 field=Parcours output=pie title="👥 Total des inscrits" export=manual}
{CBStats id=25 field=Catégorie output=pie add="Existant=-2;Externe=3"}
{CBStats id=25 field=Catégorie output=table titles="1=Groupe 1;2=Groupe 2"}
{CBStats id=25 field=Catégorie output=bar add="1=-2;2=3" titles="1=Groupe 1;2=Groupe 2" sort=value dir=desc}
{CBStats id=25 field=Parcours output=sum}
{CBStats id=25 field=Parcours output=min}
{CBStats id=25 field=Parcours output=max}
{CBStats id=25 filter[field]=Statut filter[value]="Ouvert" output=total}
{CBStats id=25 filter[field]=Statut filter[value]="Ouvert*" output=total}
{CBStats id=25 filter[field]=Statut filter[value]="Ouvert* | En attente" output=total}
```

### Export manuel figé

Ajoutez `export=manual` à une balise Pie, Bar ou Table pour afficher les libellés, valeurs et total finaux ainsi qu’une balise `source=manual` visible. Les filtres, ajouts, renommages et tris sont déjà intégrés aux données figées. Le bouton centré copie exactement la syntaxe affichée, prête à être collée dans un autre article sans dépendre de la vue d’origine.

| Sortie | Résultat | `field` obligatoire |
| --- | --- | --- |
| `total` | Nombre d'enregistrements correspondants | Non |
| `form_name` | Titre de la vue, ou son nom si le titre est vide | Non |
| `table` | Tableau HTML statique valeur/nombre | Oui |
| `json` | Tableau JSON brut d'objets `{label,value}` | Oui |
| `pie` | Graphique Pie responsive | Oui |
| `bar` | Graphique à barres horizontal responsive | Oui |
| `sum` | Somme pondérée des valeurs numériques | Oui |
| `min`, `max` | Plus petite et plus grande valeur numérique | Oui |

`table`, `json`, `pie` et `bar` consomment les mêmes données PHP normalisées. Un
tableau vide affiche `0` ; un graphique vide affiche un message localisé. JSON ne
possède aucune enveloppe HTML ou JavaScript :

```json
[
  {"label":"Valeur A","value":12},
  {"label":"Valeur B","value":7}
]
```

Utilisez ensemble `filter[field]=NomDuChamp` et `filter[value]="Valeur"`. Sans
joker, `filter[value]="Ouvert"` correspond uniquement à la valeur exacte. Avec
`filter[value]="Ouvert*"`, des valeurs comme `Ouvert` et `Ouvert (externe)`
peuvent correspondre. Le caractère `|` sépare les alternatives et les espaces
de début et de fin sont supprimés. Dans une balise d'article,
`field=NomDuChamp value="Valeur"` sert aussi de raccourci de filtre lorsque
`filter[field]` est absent.

Le champ regroupé et le champ filtré peuvent être différents :

```text
{CBStats id=15 field=Element-1 filter[field]=Element-2 filter[value]="Dét* | 3 | 4" output=bar}
```

Ici, `field=Element-1` est regroupé et affiché, tandis que
`filter[field]=Element-2` sert uniquement à sélectionner les enregistrements.
`*` est un joker, `|` sépare les alternatives et les espaces autour des valeurs
sont ignorés. Sans joker, la comparaison est exacte.

Lorsque le filtre porte sur le champ affiché, le raccourci suivant est strictement
équivalent au filtre complet sur `Element-2` :

```text
{CBStats id=15 field=Element-2 value="Dét* | 3 | 4" output=bar}
```

`value=` est réservé à ce raccourci sur le même champ. Ne le confondez pas avec
`values=`, utilisé exclusivement par `source=manual`.

Les sorties de statistiques de champ acceptent `sort=none|title|value` et
`dir=asc|desc`. Les valeurs par défaut sont `sort=none` et `dir=asc`.
`sort=none` conserve l'ordre naturel du moteur ; `sort=title` applique un ordre
naturel des libellés selon la langue active ; `sort=value` compare les nombres.
`dir` modifie la direction du tri choisi.

Pour `table`, `json`, `pie` et `bar`, `add="Libellé=EntierSigné"` applique des
deltas cumulatifs : une valeur positive ajoute, zéro ne modifie rien et une
valeur négative retire des occurrences. Si le résultat final calculé devient
négatif, CBStats utilise temporairement `0` pour ce libellé avant le tri, le
calcul des pourcentages et le rendu ; les données sources restent inchangées et
un résultat ultérieur nul ou positif est utilisé normalement.
`titles="Original=Titre affiché"` modifie uniquement l'affichage, sans changer
les données sources ni fusionner les catégories. Les libellés non indiqués
restent inchangés. L'ordre est données, filtres, regroupement, `add`, `titles`,
tri, puis output ; `sort=title` utilise les titres affichés finaux. Les
points-virgules séparent les entrées et le premier signe égal sépare chaque paire.

Pie et Bar affichent des pourcentages localisés avec une décimale, des infobulles,
une légende compacte détaillée et un total. Les graphiques sont responsives,
peuvent coexister dans toute combinaison Pie/Bar sur une page et partagent les
mêmes ressources graphiques locales.

`sum`, `min` et `max` retournent `0` lorsque les valeurs correspondantes sont
vides ou ne sont pas toutes numériques. Les champs de date peuvent fournir un
`min` et un `max` chronologiques, tandis que `sum` reste à `0`. Toutes les sorties
basées sur un champ vérifient sa disponibilité API/Stats.

CBStats applique toujours la permission STATS de la vue. Pour l'URL/API, vérifiez
les réglages **API + Droits**, la disponibilité API/Stats des champs et l'onglet
**API** de la vue. Les outputs URL disponibles sont `json`, `total`, `sum`,
`min`, `max` et `form_name` ; JSON accepte aussi `add`, `titles`, `sort` et `dir`,
tandis que Table, Pie et Bar restent réservés aux contenus. Les erreurs publiques
restent génériques. `debug=1` demande un
diagnostic uniquement lorsque DEBUG est activé sur la vue ContentBuilder NG
ciblée ; il n'accorde aucun accès et ne modifie jamais les permissions de vue, de
champ ou STATS.

La syntaxe complète des plugins Download, ImageScale et Verify n'est pas documentée
de façon exhaustive dans les guides du dépôt : **À vérifier** à partir des templates
historiques utilisés sur votre site.

## Overrides Joomla

Les layouts frontend se trouvent dans `site/tmpl/<vue>/` dans le dépôt source
(installés sous `components/com_contentbuilderng/tmpl/`). Pour une personnalisation de
site, préférez le mécanisme d'override du template Joomla lorsque le layout s'y prête,
au lieu de modifier les fichiers installés du composant.

Layouts de liste livrés (vue `list`) :

- `default` (tableau) ;
- `listcompact` ;
- `listcard` ;
- `listtiles` ;
- `listone`, `listtwo`, `listthree`.

Le chemin d'override Joomla standard est :

```text
templates/<votre_template>/html/com_contentbuilderng/list/default.php
```

> ℹ️ **Note :** l'écran Joomla **Système > Templates de site > [votre template] >
> Créer des substitutions** liste les vues du composant et copie le layout choisi au
> bon emplacement. Le chemin précis dépend du nom de la vue (`list`, `details`,
> `edit`, `latest`, `publicforms`) et du layout — *à vérifier* dans votre installation.

## Ce qu'il ne faut pas modifier directement

Évitez de modifier :

- les fichiers sous `components/com_contentbuilderng` ;
- les fichiers sous `administrator/components/com_contentbuilderng` ;
- les plugins livrés ;
- les dépendances sous `vendor` ;
- les tables SQL à la main sans diagnostic.

Une mise à jour peut remplacer ces fichiers.

## Bonnes pratiques

- gardez une copie du template avant modification ;
- utilisez des noms de champs stables ;
- échappez les valeurs affichées ;
- limitez le PHP ;
- testez les champs vides ;
- testez les uploads ;
- testez avec un utilisateur non administrateur ;
- vérifiez le rendu mobile ;
- contrôlez le mode sombre si le thème Dark est utilisé ;
- désactivez le Debug après validation.

> 📷 *Capture à ajouter : génération d'un template exemple et éditeur de préparation PHP — `docs/fr/img/templates-preparation.png`*
