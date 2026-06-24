---

name: qa-audit
description: Audite un site web en live avec playwright-cli et produit un rapport HTML autonome.
disable-model-invocation: true
argument-hint: url-du-site
allowed-tools: Bash, Read, Write
--------------------------------

Tu es un QA engineer senior. Tu vas auditer en live le site suivant avec `playwright-cli` :

```text
$ARGUMENTS
```

Personne ne t'a dit où sont les problèmes : ton travail est de les trouver, de les reproduire, de les documenter et de produire un rapport HTML autonome.

Avant toute action, lance obligatoirement :

```bash
playwright-cli --help
```

Utilise cette aide pour vérifier la syntaxe réelle disponible dans l'environnement courant. N'invente pas de commande `playwright-cli` si la syntaxe n'est pas confirmée par `--help`.

---

# Objectif

Auditer le site cible sans crawler agressivement, identifier les anomalies réelles visibles côté utilisateur, produire un fichier `qa-report.html` dans le dossier courant, puis l'ouvrir dans le navigateur.

Ne présume rien du contenu du site.

---

# Plateforme

Détecte l'OS avant de lancer les commandes auxiliaires :

```bash
uname
```

Si `uname` échoue ou si l'environnement est Windows, utilise PowerShell.

Adapte toutes les commandes selon l'OS.

## macOS

Ouvrir le rapport :

```bash
open qa-report.html
```

Encoder un screenshot en base64 sans retour ligne :

```bash
base64 < screenshot.png | tr -d '\n'
```

## Linux

Ouvrir le rapport :

```bash
xdg-open qa-report.html
```

Encoder un screenshot en base64 sans retour ligne :

```bash
base64 -w 0 screenshot.png
```

Si `base64 -w 0` n'est pas disponible :

```bash
base64 < screenshot.png | tr -d '\n'
```

## Windows PowerShell

Ouvrir le rapport :

```powershell
Start-Process .\qa-report.html
```

Encoder un screenshot en base64 :

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("screenshot.png"))
```

---

# Règles anti-429 / audit non agressif

L'audit doit être poli, limité et défendable. Ne transforme pas `playwright-cli` en crawler massif.

Paramètres par défaut :

```text
MAX_PAGES = 8
MAX_LINKS_PER_PAGE = 15
MAX_EXTERNAL_LINKS_TOTAL = 5
DELAY_BETWEEN_LINK_CHECKS_MS = 800 à 1500
DELAY_BETWEEN_PAGE_NAVIGATIONS_MS = 1500 à 3000
MAX_RETRY_AFTER_SECONDS = 60
MAX_RETRIES_AFTER_429 = 1
```

Règles obligatoires :

* Déduplique toutes les URLs avant vérification.
* Vérifie les liens séquentiellement, jamais en parallèle.
* Attends entre chaque vérification HTTP.
* Attends entre chaque navigation de page.
* Ne vérifie pas tous les assets du site.
* Ne crawle pas récursivement toutes les pages.
* Ne teste pas les actions destructrices.
* N'utilise pas de proxy, rotation d'IP, contournement de rate-limit ou user-agent trompeur.
* Ne spamme pas les formulaires.
* Ne tente pas de bypasser une protection anti-bot.
* Ne clique pas sur logout, delete, remove, unsubscribe, checkout final, paiement, confirmation, suppression ou action irréversible.

Ignore ou limite fortement :

* `mailto:`
* `tel:`
* `javascript:`
* ancres seules comme `#section`
* fichiers médias lourds
* PDF volumineux
* tracking
* analytics
* CDN d'assets
* réseaux sociaux
* logout
* panier
* checkout
* paiement
* actions destructrices

## Gestion des 429

Si une réponse HTTP vaut `429` :

1. Lis le header `Retry-After` si disponible.
2. Si `Retry-After` est présent et inférieur ou égal à 60 secondes, attends puis fais une seule nouvelle tentative.
3. Si `Retry-After` est absent ou supérieur à 60 secondes, n'insiste pas.
4. Dès qu'un domaine renvoie `429`, arrête toutes les vérifications restantes vers ce domaine.
5. Note l'événement dans le rapport comme `Info` ou `Warning` : `Rate limiting rencontré pendant l'audit`.
6. Ne classe un `429` comme bug applicatif que si un utilisateur normal le rencontre lors d'une navigation manuelle unique, sans crawling.

Si des liens ou pages sont ignorés pour éviter le rate-limit, ajoute-les dans la section `Limites de l'audit`.

---

# Méthode d'exploration

## 1. Initialisation

* Ouvre le site cible.
* Démarre la vidéo.
* Prends un premier snapshot de la home.
* Prends un screenshot de la home.
* Note le navigateur utilisé.
* Note la date et l'heure.
* Note l'URL finale après redirection éventuelle.

Démarre la vidéo avant les actions principales :

```text
video-start
```

Ajoute un chapitre vidéo pour la home :

```text
video-chapter Home
```

---

## 2. Découverte contrôlée des pages

Depuis la home :

* Liste les liens visibles.
* Normalise les URLs.
* Supprime les doublons.
* Sépare liens internes et liens externes.
* Identifie les pages importantes.

Construis un plan de test de 3 à 8 pages maximum.

Priorise :

1. Home
2. Page contact
3. Page pricing / offres / tarifs si présente
4. Page produit / service principale
5. Page documentation / blog / contenu si présente
6. Page login / signup si présente, sans soumettre de données sensibles
7. Mentions légales / politique si pertinente
8. Une page profonde importante trouvée depuis la navigation

Justifie brièvement les pages choisies dans tes notes de travail et dans le rapport.

Ne visite pas plus de 8 pages sauf instruction explicite contraire.

---

## 3. Batterie de contrôles par page

Pour chaque page visitée :

* Ajoute un chapitre vidéo :

```text
video-chapter Nom de la page
```

* Ouvre la page.
* Attends le chargement raisonnable.
* **Prends un snapshot complet** — développe toutes les sections (`snapshot --depth=0` ou appels récursifs sur chaque nœud racine) pour ne manquer aucun lien d'action caché dans une branche non développée.
* Prends un screenshot.
* Observe les erreurs console.
* Observe les warnings console.
* Vérifie les images visibles cassées.
* **Enumère tous les liens de la page** (pas seulement les liens de navigation visibles) — collecte les `href` et les `onclick` à la recherche de patterns d'action (`edit`, `delete`, `save`, `state`, `admin`, `manage`…).
* Vérifie un échantillon limité de liens visibles importants.
* Vérifie les éléments interactifs non destructifs.
* **Applique les contrôles de sécurité** décrits dans la section dédiée ci-dessous.
* Teste le rendu mobile.
* Note les anomalies, sélecteurs et preuves.

---

# Contrôles systématiques

## Console

Collecte :

* erreurs JavaScript
* warnings importants
* erreurs réseau visibles côté console
* erreurs de ressources critiques

Ne remonte pas comme bug majeur un warning tiers non bloquant, sauf impact utilisateur visible.

---

## Images cassées

Utilise une évaluation DOM équivalente à :

```javascript
Array.from(document.images)
  .filter(img => {
    const rect = img.getBoundingClientRect();
    const visible = rect.width > 0 && rect.height > 0;

    return visible && img.complete && img.naturalWidth === 0;
  })
  .map(img => ({
    src: img.currentSrc || img.src,
    alt: img.alt,
    selector: img.id
      ? `#${img.id}`
      : img.className
        ? `.${String(img.className).split(' ').join('.')}`
        : img.tagName.toLowerCase()
  }));
```

Vérifie surtout les images visibles et significatives.

---

## Liens

Pour chaque page :

* Prends au maximum 15 liens visibles importants.
* Déduplique.
* Ignore les liens exclus par les règles anti-429.
* Vérifie les statuts séquentiellement.
* Respecte les délais.
* Respecte `Retry-After`.
* Arrête les checks d'un domaine dès qu'il renvoie 429.

Catégorisation :

* `2xx` : OK
* `3xx` : OK si redirection cohérente
* `4xx` hors 429 : bug potentiel
* `5xx` : bug potentiel
* `429` : rate-limit rencontré, à classer selon contexte
* timeout : warning, à confirmer si reproductible

---

## Interactions

Teste uniquement les interactions non destructrices :

* menus
* accordéons
* onglets
* filtres visuels
* boutons ouvrant une modale
* navigation principale
* burger menu mobile
* champs de formulaire sans soumission finale
* validations côté client si sans effet externe

Pour chaque interaction suspecte :

* reproduis le comportement
* prends un snapshot
* prends un screenshot ciblé si possible
* note le sélecteur concerné
* note le résultat attendu
* note le résultat observé

Ne soumets pas de formulaire réel sauf si c'est clairement un formulaire de test sans effet externe.

---

## Sécurité

### Détection des URLs d'action

Sur chaque page visitée, collecte tous les liens et handlers qui correspondent à des opérations privilégiées. Utilise une évaluation DOM pour extraire l'ensemble des URLs d'action :

```javascript
Array.from(document.querySelectorAll('a[href], [onclick]')).flatMap(el => {
  const href = el.getAttribute('href') || '';
  const onclick = el.getAttribute('onclick') || '';
  const combined = href + ' ' + onclick;
  if (/edit|delete|save|state|admin|manage|remove|update|create|new/i.test(combined)) {
    return [{ tag: el.tagName, text: el.textContent.trim().substring(0, 40), href, onclick: onclick.substring(0, 100) }];
  }
  return [];
});
```

Complète en inspectant les scripts inline pour les fonctions de redirection (`location.href`, `window.location`) pointant vers des URLs d'action.

### Test d'accès sans authentification

Pour chaque URL d'action trouvée (patterns `task=edit`, `task=delete`, `task=state`, `action=edit`, `action=delete`, `/admin/`, `/manage/`, etc.) :

1. **Navigue vers l'URL** dans la même session anonyme (sans cookies de session, sans authentification).
2. **Vérifie ce qui est retourné** :
   - HTTP 401 ou 403 ou redirection vers login → protégé, OK
   - HTTP 200 avec formulaire ou données → **Bloquant** : accès non autorisé
   - HTTP 200 avec message d'erreur applicatif → documenter selon impact
3. **Prends un screenshot** si la page est accessible sans auth.
4. **Compte les champs exposés** si un formulaire est présent.
5. **Note les données sensibles visibles** (données personnelles, IDs internes, tokens).

> **Important :** naviguer vers une URL pour vérifier son accessibilité n'est pas une action destructrice. Ne pas soumettre de formulaire, ne pas confirmer de suppression, ne pas finaliser d'action irréversible.

### Détection des panneaux de debug / admin

Sur chaque page, cherche les éléments révélant des informations internes visibles à un visiteur non authentifié :

```javascript
document.querySelectorAll('[class*=debug], [id*=debug], [class*=admin], details, [class*=permission]')
```

Documente comme **Warning** tout panneau exposant : IDs internes, permissions, comptes utilisateurs, configuration, données de session.

### Résumé sécurité par page

Pour chaque page, note dans le rapport :
- Nombre d'URLs d'action trouvées
- Nombre testées en session anonyme
- Résultat : protégées / accessibles / non testées (avec raison)

---

## Mobile

Pour les pages clés, teste au minimum un viewport mobile :

```text
375x812
```

Vérifie :

* menu mobile
* débordements horizontaux
* textes coupés
* CTA visibles
* formulaires utilisables
* images adaptées
* éléments cliquables accessibles

---

# Gravités

Classe chaque anomalie avec une gravité.

## Bloquant

Impact utilisateur majeur :

* page inaccessible
* erreur JS empêchant l'usage principal
* CTA critique inutilisable
* formulaire clé impossible à utiliser
* navigation principale cassée
* contenu principal absent
* bug mobile empêchant l'usage
* formulaire d'édition ou de suppression accessible sans authentification (HTTP 200 en session anonyme)
* données personnelles ou sensibles exposées sans authentification
* panneau d'administration accessible sans authentification

## Warning

Impact réel mais non bloquant :

* lien important cassé
* image importante cassée
* warning console lié à une fonctionnalité
* élément interactif secondaire défaillant
* problème mobile gênant mais contournable
* performance ou chargement anormal visible
* rate-limit rencontré sur une vérification raisonnable

## Info

Observation utile :

* rate-limit rencontré pendant échantillonnage
* lien externe non vérifié pour éviter 429
* warning tiers non bloquant
* amélioration UX mineure
* contenu ambigu
* limite de l'audit

---

# Preuves

Pour chaque anomalie réelle, documente :

* URL de la page
* description
* résultat attendu
* résultat observé
* sélecteur concerné si applicable
* gravité
* preuve : snapshot et/ou screenshot
* étapes de reproduction
* statut HTTP si pertinent
* console error/warning si pertinent

N'invente rien.

Si une page est saine, dis-le explicitement dans `Ce qui fonctionne`.

---

# Vidéo

Enregistre une vidéo de l'audit :

* `video-start` au début
* `video-chapter` pour chaque page
* `video-stop` à la fin

Dans le rapport, indique :

* chemin du fichier `.webm`
* liste des chapitres
* pages correspondantes

Si la vidéo échoue, documente l'échec dans la section `Limites de l'audit` sans inventer de chemin.

---

# Screenshots

Crée un dossier local pour les preuves, par exemple :

```text
qa-artifacts/
```

Nomme les screenshots clairement :

```text
qa-artifacts/01-home-desktop.png
qa-artifacts/02-home-mobile.png
qa-artifacts/03-contact-bug-form.png
```

Encode les screenshots en base64 selon l'OS détecté.

Le rapport HTML doit embarquer les screenshots en base64 :

```html
<img src="data:image/png;base64,..." alt="Description de la capture">
```

Ne référence pas seulement des fichiers locaux pour les screenshots : ils doivent être intégrés au HTML.

---

# Rapport HTML

Crée un fichier :

```text
qa-report.html
```

dans le dossier courant.

Le rapport doit être autonome, lisible et structuré.

Il doit contenir les sections suivantes.

## Header

* Titre : `Rapport QA`
* URL testée
* Date et heure
* Navigateur utilisé
* OS détecté
* URL finale après redirection éventuelle

## Métriques en cartes

4 cartes minimum :

* Pages testées
* Erreurs console
* Bugs trouvés
* Warnings

Ajoute si utile :

* Liens vérifiés
* URLs ignorées pour éviter rate-limit
* 429 rencontrés

## Plan de test

Liste les pages sélectionnées et explique pourquoi elles ont été choisies.

## Section vidéo

* Chemin du `.webm`
* Liste des chapitres
* Mention claire si la vidéo n'a pas pu être produite

## Section sécurité

Un tableau par page listant les URLs d'action trouvées et leur résultat en session anonyme :

* URL testée
* HTTP status obtenu en session anonyme
* Résultat : Protégé / Accessible / Non testé
* Gravité si accessible

## Section bugs

Un bloc par bug, trié par gravité :

* badge : `Bloquant`, `Warning` ou `Info`
* page concernée
* description
* attendu
* observé
* sélecteur concerné
* étapes de reproduction
* preuve associée
* statut HTTP si pertinent

## Section console

* erreurs console
* warnings console
* page concernée
* message résumé
* impact estimé

## Section liens

* liens testés
* statut HTTP
* liens ignorés
* domaines stoppés à cause de 429
* note sur le respect du rate-limit

## Section ce qui fonctionne

Liste les points OK :

* pages sans anomalie critique
* navigation fonctionnelle
* formulaires ou interactions OK
* rendu mobile OK si confirmé
* images OK si confirmé

## Section limites de l'audit

Mentionne explicitement :

* pages non testées
* liens ignorés
* domaines arrêtés après 429
* fonctionnalités non testées car destructrices ou sensibles
* éventuelles limites de `playwright-cli`
* éventuelles limites réseau

## Section screenshots

Affiche les screenshots embarqués en base64 sur 2 colonnes.

Chaque capture doit avoir :

* titre
* page
* contexte
* image intégrée

## Footer

Le footer doit contenir exactement :

```text
Généré par Claude Code + playwright-cli · <date>
```

---

# Squelette HTML recommandé

Le rapport est autonome, lisible sans serveur, sans dépendance externe. Style sobre : lisibilité avant tout, pas de décoration superflue.

```html
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rapport QA</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; color: #111; line-height: 1.6; }
    h1, h2, h3 { margin-top: 2rem; }
    h1 { border-bottom: 2px solid #111; padding-bottom: .4rem; }
    h2 { border-bottom: 1px solid #ccc; padding-bottom: .2rem; }
    table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .9rem; }
    th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; vertical-align: top; }
    th { background: #f4f4f4; }
    code { background: #f4f4f4; padding: 1px 4px; border-radius: 3px; font-size: .9em; }
    .badge { font-weight: 700; font-size: .8rem; padding: 2px 8px; border-radius: 3px; color: #fff; }
    .badge.bloquant { background: #b42318; }
    .badge.warning  { background: #b54708; }
    .badge.info     { background: #175cd3; }
    .badge.ok       { background: #067647; }
    .bug { border-left: 3px solid #ccc; padding: .5rem 1rem; margin: 1rem 0; }
    .bug.bloquant { border-color: #b42318; }
    .bug.warning  { border-color: #b54708; }
    .bug.info     { border-color: #175cd3; }
    .screenshots { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .screenshots img { width: 100%; height: auto; border: 1px solid #ccc; }
    footer { margin-top: 3rem; border-top: 1px solid #ccc; padding-top: 1rem; font-size: .85rem; color: #555; }
  </style>
</head>
<body>
  <h1>Rapport QA</h1>
  <p>URL testée : <strong><!-- URL --></strong><br>
  Date : <!-- DATE --> · Navigateur : <!-- BROWSER --> · OS : <!-- OS --></p>

  <table>
    <tr><th>Pages testées</th><th>Erreurs console</th><th>Bugs</th><th>Warnings</th></tr>
    <tr><td><!-- pages --></td><td><!-- console errors --></td><td><!-- bugs --></td><td><!-- warnings --></td></tr>
  </table>

  <h2>Plan de test</h2>
  <!-- Pages choisies + justification -->

  <h2>Vidéo</h2>
  <!-- Chemin webm + chapitres -->

  <h2>Sécurité</h2>
  <!-- Tableau URLs d'action testées en session anonyme -->

  <h2>Bugs</h2>
  <!-- Blocs bugs triés par gravité -->

  <h2>Console</h2>
  <!-- Erreurs et warnings console -->

  <h2>Liens</h2>
  <!-- Statuts HTTP, liens ignorés, 429 -->

  <h2>Ce qui fonctionne</h2>
  <!-- Points OK -->

  <h2>Limites de l'audit</h2>
  <!-- Limites, rate-limit, pages non testées -->

  <h2>Screenshots</h2>
  <div class="screenshots">
    <!-- <figure><img src="data:image/png;base64,..." alt="..."><figcaption>Titre · page · contexte</figcaption></figure> -->
  </div>

  <footer>Généré par Claude Code + playwright-cli · <!-- DATE --></footer>
</body>
</html>
```

---

# Critères de qualité

Avant d'écrire le rapport final, vérifie que :

* Le fichier `qa-report.html` existe.
* Il contient les screenshots en base64.
* Il ne dépend pas d'un CDN externe.
* Les bugs ne sont pas inventés.
* Les 429 sont traités comme rate-limit sauf preuve d'impact utilisateur normal.
* Les pages saines sont mentionnées.
* Les limites de l'audit sont explicites.
* Le rapport contient bien les 4 métriques minimales.
* Le rapport contient bien le footer demandé.
* La vidéo est arrêtée proprement.
* Le navigateur est fermé proprement.
* Le snapshot de chaque page a bien été développé intégralement pour ne manquer aucun lien d'action.
* Les URLs d'action trouvées ont bien été testées en session anonyme.
* La section sécurité est présente dans le rapport avec le résultat de chaque URL d'action.

---

# Fin de mission

À la fin :

1. Arrête la vidéo :

```text
video-stop
```

2. Écris `qa-report.html`.

3. Confirme le chemin complet du rapport.

Sur macOS/Linux :

```bash
pwd
```

Sur Windows PowerShell :

```powershell
Get-Location
```

4. Ouvre le rapport dans le navigateur avec la commande adaptée.

macOS :

```bash
open qa-report.html
```

Linux :

```bash
xdg-open qa-report.html
```

Windows PowerShell :

```powershell
Start-Process .\qa-report.html
```

5. Termine par :

```text
playwright-cli close
```

---

# Règle finale

Sois factuel. N'invente aucune anomalie.

Si tout fonctionne, le rapport doit le dire clairement.

Si le site déclenche des 429, documente-les proprement comme une limite ou un signal de rate-limit, et réduis immédiatement l'intensité de l'audit.
