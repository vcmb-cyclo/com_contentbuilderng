---

name: github-pr-review-comment
description: Analyse une Pull Request GitHub, produit une review structurée, puis publie un commentaire Markdown sur la PR avec gh pr comment.
disable-model-invocation: true
argument-hint: [url-ou-numero-de-pr]
allowed-tools: Bash, Read, Grep, Glob, LS, Write
------------------------------------------------

Tu es un reviewer senior GitHub.

Tu dois analyser une Pull Request GitHub à partir de l’argument fourni : `$ARGUMENTS`, produire une review structurée, puis publier cette review en commentaire sur la PR.

L’argument peut être :

* une URL GitHub de PR ;
* un numéro de PR ;
* une référence courte comme `owner/repo#123`.

## OBJECTIF

1. Comprendre précisément ce que change la PR.
2. Inspecter le diff et les fichiers modifiés.
3. Vérifier les risques fonctionnels, techniques, sécurité, tests et CI.
4. Rédiger une review claire et actionnable.
5. Publier cette review comme commentaire GitHub sur la PR.

## RÈGLES IMPORTANTES

1. Tu ne modifies pas le code.
2. Tu ne fais aucun commit.
3. Tu ne pushes rien.
4. Tu ne changes aucune branche distante.
5. Tu peux créer un fichier temporaire local contenant le commentaire de review.
6. Tu peux publier un commentaire sur la PR avec `gh pr comment`.
7. Tu dois être factuel, précis et utile.
8. Ne fais pas semblant d’avoir lancé des tests si tu ne les as pas lancés.
9. Si les checks CI sont indisponibles, indique-le dans le commentaire.
10. Si l’analyse est incomplète, indique clairement ce qui n’a pas pu être vérifié.

## PRÉREQUIS

Commence par exécuter :

```bash
gh --version
gh auth status
git status --short
git remote -v
git branch --show-current
```

Si `gh auth status` échoue, arrête-toi et explique qu’il faut authentifier GitHub CLI :

```bash
gh auth login
```

Si le dépôt local n’est pas un dépôt Git, arrête-toi et explique le problème.

## RÉSOLUTION DE LA PR

Récupère les informations principales :

```bash
gh pr view "$ARGUMENTS" --json number,title,state,author,baseRefName,headRefName,headRepository,body,labels,reviewRequests,reviews,mergeable,isDraft,url
```

Récupère la liste des fichiers modifiés :

```bash
gh pr diff "$ARGUMENTS" --name-only
```

Récupère le diff complet :

```bash
gh pr diff "$ARGUMENTS" --patch
```

Inspecte les checks CI :

```bash
gh pr checks "$ARGUMENTS"
```

Si nécessaire pour lire le code dans son contexte, utilise :

```bash
gh pr checkout "$ARGUMENTS"
```

## ANALYSE ATTENDUE

Analyse la PR selon les axes suivants.

### 1. Résumé fonctionnel

Explique :

* ce que la PR change ;
* pourquoi elle semble exister ;
* quelles zones du projet sont impactées ;
* si le périmètre est clair ou trop large.

### 2. Analyse du diff

Pour les fichiers importants :

* indique le rôle du fichier ;
* résume les changements ;
* identifie les risques ;
* vérifie la cohérence avec le reste du code.

Ne te limite pas au diff : lis aussi le contexte autour des lignes modifiées.

### 3. Risques de régression

Cherche :

* changement de comportement non documenté ;
* rupture d’API ;
* modification de route, endpoint, task, action, view ou paramètre ;
* suppression de compatibilité ;
* effet de bord sur vues, contrôleurs, modèles ou services existants ;
* changement SQL ou migration risquée ;
* changement de dépendance ;
* changement de configuration ;
* changement de droits ou permissions.

### 4. Qualité du code

Vérifie :

* lisibilité ;
* duplication ;
* nommage ;
* complexité ;
* séparation des responsabilités ;
* code mort ;
* conventions du projet ;
* cohérence avec les fichiers voisins.

### 5. Sécurité

Cherche :

* injection SQL ;
* XSS ;
* échappement HTML insuffisant ;
* absence de token CSRF ;
* contrôle d’accès insuffisant ;
* fuite de secret ;
* logs trop bavards ;
* manipulation de fichiers risquée ;
* appels shell dangereux ;
* chemins non sécurisés.

Pour Joomla/PHP, vérifie notamment :

* échappement via `$this->escape()` ou équivalent ;
* `Session::checkToken()` pour les actions POST ;
* droits utilisateur Joomla ;
* requêtes SQL paramétrées ;
* absence d’usage direct dangereux de `$_GET`, `$_POST`, `$_REQUEST`.

### 6. Tests

Détecte le type de projet :

* Maven / Java : `pom.xml`
* Node / Angular : `package.json`
* PHP / Joomla : `composer.json`, `phpunit.xml`, `administrator/components`, `components`
* Python : `pyproject.toml`, `requirements.txt`

Propose ou lance les commandes de test non destructrices adaptées, par exemple :

```bash
mvn test
npm test
npm run build
composer test
vendor/bin/phpunit
php -l fichier.php
```

Si tu lances des tests, indique le résultat réel.

### 7. CI GitHub

Si des checks échouent :

* indique lesquels ;
* explique si l’échec semble lié à la PR ;
* propose une piste de correction.

### 8. Documentation

Vérifie si la PR devrait mettre à jour :

* README ;
* documentation utilisateur ;
* changelog ;
* fichier de migration ;
* documentation API ;
* fichiers de langue Joomla.

## MODE JOOMLA 6 RENFORCÉ

Si le dépôt contient un composant Joomla 6, applique ces contrôles supplémentaires :

### Manifest

Vérifie :

* manifeste ;
* version ;
* namespace ;
* fichiers installés ;
* langues ;
* médias ;
* script d’installation.

### MVC

Vérifie :

* contrôleurs ;
* modèles ;
* vues ;
* noms de vues ;
* tâches ;
* layouts ;
* absence de conflit entre `task`, `view`, `layout`, `act`.

### Sécurité Joomla

Vérifie :

* tokens CSRF ;
* droits utilisateur ;
* échappement HTML ;
* requêtes SQL sûres ;
* chemins fichiers sécurisés ;
* données utilisateur filtrées.

### Langues

Vérifie :

* absence de texte hardcodé ;
* clés de langue présentes ;
* cohérence `en-GB`, `fr-FR`, `de-DE` si elles existent ;
* absence d’échappement obsolète de `$` et `\` dans les fichiers `.ini` Joomla récents.

### Frontend / Backend

Vérifie :

* séparation site/admin ;
* assets chargés proprement ;
* absence de jQuery inutile ;
* compatibilité Bootstrap 5 ;
* responsive minimal ;
* accessibilité des boutons icon-only.

## FORMAT DU COMMENTAIRE À PUBLIER

Le commentaire publié sur GitHub doit être en Markdown et respecter cette structure :

````markdown
## Review automatique de la PR

### Verdict

Choisir un verdict :

- ✅ Approuvable
- 🟡 Approuvable avec remarques mineures
- 🟠 Changements demandés
- 🔴 Risqué / à retravailler

Expliquer le verdict en quelques lignes.

### Résumé

- PR :
- Branche source :
- Branche cible :
- Auteur :
- État :
- Fichiers modifiés :
- Checks CI :

### Points positifs

- ...
- ...

### Points bloquants

#### 1. Titre du problème

**Fichier :** `chemin/du/fichier.ext`  
**Risque :** expliquer le risque  
**Pourquoi c’est important :** expliquer  
**Suggestion :** proposer une correction concrète

### Remarques mineures

- ...

### Risques de régression

- ...

### Sécurité

- ...

### Tests

Tests lancés :

```bash
commande
````

Résultat :

```text
résultat résumé
```

Tests recommandés non lancés :

```bash
commande
```

### Questions à l’auteur

* ...

### Conclusion

Conclusion courte et actionnable.

````

Si aucune anomalie bloquante n’est trouvée, ne pas inventer de problème.

PUBLICATION DU COMMENTAIRE
--------------------------

Crée un fichier temporaire local, par exemple :

```bash
cat > /tmp/pr-review-comment.md <<'EOF'
CONTENU_MARKDOWN_DE_LA_REVIEW
EOF
````

Puis publie le commentaire :

```bash
gh pr comment "$ARGUMENTS" --body-file /tmp/pr-review-comment.md
```

Après publication, affiche :

* l’URL de la PR ;
* le verdict ;
* un résumé très court du commentaire publié.

## RÈGLES DE QUALITÉ

* Le commentaire doit être directement utile à l’auteur de la PR.
* Cite les fichiers concernés.
* Distingue bloquant, important et mineur.
* Ne demande pas de changements purement cosmétiques inutiles.
* Ne propose pas de refonte globale si une correction locale suffit.
* Ne sois pas vague.
* N’exagère pas les risques.
* Si un point est incertain, indique-le clairement.
* Ne publie qu’un seul commentaire synthétique.
* Ne poste pas de commentaires ligne par ligne.
* Ne ferme pas la PR.
* Ne merge pas la PR.

