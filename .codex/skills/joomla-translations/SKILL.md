---
name: joomla-translations
description: >
  Use this skill whenever creating, editing, or reviewing any user-facing
  string in a Joomla 6 extension (component, module, plugin, template): admin
  field labels and descriptions, form/XML labels, toolbar buttons, error and
  success messages, tooltips, JS-exposed strings (Text::script), email
  templates. Triggers on any edit to a language .ini file, any new or changed
  Text::_() / Text::sprintf() / Text::plural() call, any <field> label/
  description/hint in XML manifests or forms, or any request to add/update a
  translation. Always update en-GB, fr-FR, and de-DE together, keep wording
  aligned across the three, and apply correct French typography (fine
  non-breaking spaces, French quotation marks, capitalization rules).
---

# Traductions Joomla 6 (en-GB / fr-FR / de-DE)

## Quand s'applique cette skill
Dès qu'une chaîne destinée à l'utilisateur est créée ou modifiée :
libellés de champs, descriptions, infobulles, messages d'erreur/succès,
boutons de la toolbar admin, libellés XML (`<field label="..." description="...">`),
chaînes exposées au JS via `Text::script()`.

## Règle d'or
**Une clé de langue ne doit jamais être ajoutée ou modifiée dans une seule
langue.** Les trois fichiers `en-GB`, `fr-FR`, `de-DE` sont édités dans le
même tour de modification, avec un sens strictement équivalent.

## 1. Emplacement des fichiers

```
admin/language/en-GB/en-GB.com_xxx.ini
admin/language/en-GB/en-GB.com_xxx.sys.ini   (nom, description du manifeste)
admin/language/fr-FR/fr-FR.com_xxx.ini
admin/language/fr-FR/fr-FR.com_xxx.sys.ini
admin/language/de-DE/de-DE.com_xxx.ini
admin/language/de-DE/de-DE.com_xxx.sys.ini

site/language/en-GB/en-GB.com_xxx.ini   (chaînes front-end, si différentes)
site/language/fr-FR/fr-FR.com_xxx.ini
site/language/de-DE/de-DE.com_xxx.ini
```

- `*.sys.ini` : uniquement le nom et la description visibles dans le
  gestionnaire d'extensions (chargé même quand l'extension est désactivée).
- `*.ini` (sans `.sys`) : toutes les autres chaînes, chargées à l'exécution.

## 2. Convention de nommage des clés

Format : `COM_<EXTENSION>_<CONTEXTE>_<ELEMENT>`, toujours en MAJUSCULES,
underscores, pas d'espaces ni d'accents dans la clé elle-même.

```ini
COM_CONTENTBUILDERNG_FIELD_API_KEY_LABEL="API Key"
COM_CONTENTBUILDERNG_FIELD_API_KEY_DESC="Your Anthropic API key, used for completions."
COM_CONTENTBUILDERNG_ERROR_INVALID_KEY="The provided API key is invalid."
COM_CONTENTBUILDERNG_TOOLBAR_REINDEX="Reindex"
COM_CONTENTBUILDERNG_N_ITEMS_INDEXED_1="%d item indexed"
COM_CONTENTBUILDERNG_N_ITEMS_INDEXED_MORE="%d items indexed"
```

Suffixes courants à respecter pour la cohérence avec le cœur Joomla :
- `_LABEL` pour le libellé d'un champ
- `_DESC` pour la description/infobulle d'un champ
- `_HINT` pour le placeholder
- `_N_ITEMS_..._1` / `_..._MORE` pour le pluriel (cf. `Text::plural()`)

## 3. Alignement du sens entre les langues

Pour chaque clé, les trois traductions doivent :
- exprimer **exactement** la même information (pas de paraphrase libre ni
  d'ajout/suppression de nuance) ;
- avoir un registre cohérent (vouvoiement en français, formel en allemand —
  `Sie`, jamais `du`, dans une interface d'administration) ;
- conserver les mêmes espaces réservés (`%s`, `%d`, `%1$s`...) **dans le même
  ordre logique** — utiliser les index positionnels (`%1$s`, `%2$d`) dès que
  l'ordre des arguments diffère entre langues, ce qui est fréquent en
  allemand (ordre des mots différent).

Exemple correct (ordre des arguments figé par index, pas par position) :

```ini
; en-GB
COM_CONTENTBUILDERNG_MSG_REINDEXED="%1$s items reindexed in %2$s seconds"
; fr-FR
COM_CONTENTBUILDERNG_MSG_REINDEXED="%1$s éléments réindexés en %2$s secondes"
; de-DE
COM_CONTENTBUILDERNG_MSG_REINDEXED="%1$s Elemente in %2$s Sekunden neu indiziert"
```

## 4. Typographie française (fr-FR)

À appliquer systématiquement dans les fichiers `fr-FR` :

- **Espace fine insécable** avant `:`, `?`, `!`, `;` lorsque le rendu final
  est du HTML/texte affiché à l'utilisateur (utiliser `&#8239;` ou une espace
  insécable normale `&nbsp;` selon ce que le moteur de template restitue
  correctement — à défaut de certitude sur le rendu, préférer une espace
  insécable simple plutôt que rien).
- **Guillemets français** « comme ceci » avec espace insécable interne,
  jamais de guillemets droits `"..."` dans le texte affiché (les guillemets
  droits restent les délimiteurs INI, ce n'est pas le sujet ici).
- **Majuscules** : seule la première lettre d'un libellé de champ est
  capitalisée (« Clé API », pas « Clé API » → correct ; éviter
  « Clé Api » ou « CLÉ API » sauf si l'anglais lui-même est tout en
  capitales pour un acronyme volontaire).
- **Accents obligatoires**, y compris sur les majuscules (« Écrire »,
  pas « Ecrire »).
- Pas d'anglicisme évitable (« télécharger » plutôt que « uploader » quand
  un équivalent existe nativement dans Joomla FR).

## 5. Workflow de modification

1. Identifier ou créer la clé dans `en-GB` (source de vérité sémantique).
2. Ajouter immédiatement la même clé dans `fr-FR` et `de-DE`, dans le
   fichier correspondant (`.ini` ou `.sys.ini` selon le contexte, cf. §1).
3. Vérifier que les trois fichiers gardent le même **ordre de clés** (facilite
   la relecture en diff).
4. Si la chaîne est utilisée en JS, vérifier qu'elle est bien déclarée via
   `Text::script('COM_XXX_KEY')` côté PHP avant d'être consommée par
   `Joomla.Text._('COM_XXX_KEY')` côté JS — sinon elle n'existera que côté
   serveur.
5. Ne jamais laisser une clé `TODO`/non traduite : si la traduction définitive
   n'est pas connue, le signaler explicitement au lieu de dupliquer le texte
   anglais comme valeur fr-FR/de-DE.

## 6. Ce que cette skill ne couvre pas
- La création de nouvelles langues au-delà de en-GB/fr-FR/de-DE (hors
  périmètre, cf. `AGENTS.md`/`CLAUDE.md`).
- La logique de fallback de langue Joomla (gérée nativement par le cœur,
  pas de workaround à coder).
