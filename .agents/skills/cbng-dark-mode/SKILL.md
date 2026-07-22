---
name: cbng-dark-mode
description: Audite et optimise l'affichage en mode sombre des écrans frontend du composant Joomla 6 ContentBuilderNG. À utiliser pour corriger les problèmes CSS dark mode, contrastes, tableaux, formulaires, détails, listes et toolbars frontend.
argument-hint: "[objectif-optionnel]"
disable-model-invocation: true
allowed-tools: Read Grep Glob Edit MultiEdit Bash
---

# Skill : optimisation dark mode frontend ContentBuilderNG

Tu es un développeur expert Joomla 6, PHP 8.3, Bootstrap 5, HTML/CSS responsive et accessibilité WCAG.

## Contexte

Le projet est le composant Joomla 6 `com_contentbuilderng`.

L'objectif est d'optimiser l'affichage en mode sombre des écrans frontend du composant, sans casser :

- le mode clair ;
- les vues backend ;
- les templates Joomla modernes ;
- les surcharges template ;
- l'affichage mobile ;
- l'accessibilité clavier ;
- la compatibilité Bootstrap 5.

Arguments éventuels fournis par l'utilisateur :

`$ARGUMENTS`

## Périmètre strict

Intervenir uniquement sur le frontend du composant ContentBuilderNG, notamment :

- vues liste frontend ;
- vues détail frontend ;
- formulaires publics ;
- derniers enregistrements ;
- tableaux ;
- filtres ;
- recherche ;
- pagination ;
- boutons d'action ;
- toolbar frontend ;
- badges ;
- messages ;
- champs de formulaire ;
- liens ;
- icônes ;
- blocs d'audit trail ;
- boutons précédent / suivant ;
- templates frontend propres au composant.

Ne pas modifier les vues backend.

## Contraintes impératives

- Joomla 6 uniquement.
- PHP 8.3.
- Bootstrap 5.
- Ne pas modifier la logique métier PHP sauf nécessité démontrée.
- Ne pas introduire de dépendance JavaScript inutile.
- Ne pas forcer globalement le mode sombre.
- Ne pas appliquer de hack spécifique à un navigateur, un écran ou un téléphone.
- Ne pas utiliser `!important` sauf justification précise.
- Ne pas coder massivement des couleurs en dur.
- Préférer les variables CSS Bootstrap 5 et Joomla.
- Respecter `data-bs-theme="dark"` lorsqu'il est utilisé par le template.
- Limiter les styles au composant avec une classe racine claire.
- Préserver les classes Bootstrap existantes.
- Préserver les surcharges template Joomla.

## Méthode obligatoire

### 1. Audit initial

Commence par identifier les fichiers frontend pertinents :

- `site/tmpl/**`
- `site/src/**`
- `media/**`
- fichiers CSS/SCSS du composant
- layouts frontend éventuels
- thèmes ou templates ContentBuilderNG éventuels

Liste les fichiers candidats avant modification.

### 2. Diagnostic dark mode

Cherche en priorité :

- fonds blancs forcés ;
- textes noirs forcés ;
- bordures trop claires ou trop sombres ;
- liens peu lisibles ;
- champs de formulaire illisibles ;
- tableaux Bootstrap mal adaptés ;
- badges agressifs ;
- icônes invisibles ;
- styles inline hérités ;
- classes Joomla 3/4 obsolètes ;
- règles CSS trop globales ;
- conflits avec Bootstrap 5 ;
- absence de classe racine frontend.

### 3. Stratégie CSS attendue

Préférer une approche de ce type, en adaptant les noms exacts au code existant :

```css
.com-contentbuilderng,
.cbng-frontend {
  color: var(--bs-body-color);
  background-color: var(--bs-body-bg);
}

[data-bs-theme="dark"] .com-contentbuilderng,
[data-bs-theme="dark"] .cbng-frontend {
  --cbng-surface-bg: var(--bs-tertiary-bg);
  --cbng-border-color: var(--bs-border-color);
  --cbng-muted-color: var(--bs-secondary-color);
}
