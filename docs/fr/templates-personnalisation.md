# Templates et personnalisation

ContentBuilder NG utilise des templates configurés dans chaque vue pour produire les
détails, les formulaires d'édition, les articles et certaines présentations de liste.

## Commencer par un exemple généré

Les plugins de thème peuvent générer :

- un exemple de template détail ;
- un exemple de template éditable ;
- du CSS et du JavaScript associés.

Thèmes livrés :

- Joomla 6 ;
- Dark ;
- Blank ;
- Khepri, hérité de ContentBuilder.

Le thème Joomla 6 est utilisé comme repli lorsque le thème demandé n'est pas
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
{value}         la valeur brute dans un wrapper de colonne
{value_inline}  la valeur brute dans un wrapper d'article
{webpath nom}   le chemin web absolu d'un fichier envoyé
{CBSite} / {cbsite}   l'URL racine du site
{hide-if-empty nom} ... {/hide}   masque un bloc si le champ est vide
```

Ces remplacements sont effectués par le service de rendu (`TemplateRenderService`).
Utilisez les exemples générés pour votre vue comme référence prioritaire, car les
champs disponibles dépendent de la source.

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

Exemple de statistiques :

```text
{CBStats id=25 output=total}
{CBStats id=25 field=Parcours output=table}
{CBStats id=25 filter[field]=Parcours filter[value]="200 km*" output=total}
```

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

