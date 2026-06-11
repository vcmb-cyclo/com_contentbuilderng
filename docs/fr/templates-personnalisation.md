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
{nom:label}
{nom:value}
{hide-if-empty nom}
{/hide}
```

La syntaxe exacte disponible dans les templates détail et édition dépend du moteur de
rendu et du type source. Utilisez les exemples générés pour votre vue comme référence
prioritaire.

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

Les layouts frontend se trouvent dans `site/tmpl` dans le dépôt source. Pour une
personnalisation de site, préférez le mécanisme d'override du template Joomla lorsque
le layout s'y prête, au lieu de modifier les fichiers installés du composant.

À vérifier : le chemin exact proposé par l'écran Joomla **Créer des substitutions**
selon le layout ContentBuilder NG choisi.

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

> **TODO capture d'écran :** génération d'un template exemple et éditeur de préparation.

