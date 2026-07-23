# Maintenance et dépannage

## Routine de maintenance

Après une mise à jour de Joomla, PHP, BreezingForms ou ContentBuilder NG :

1. sauvegardez ;
2. installez la mise à jour sur une copie ;
3. lancez **À propos > Audit** ;
4. consultez le journal ;
5. testez les parcours critiques ;
6. vérifiez les plugins ;
7. contrôlez les tâches planifiées et intégrations API.

## Journaux

Le composant utilise `com_contentbuilderng.log`.

Le fichier est recherché dans le chemin de logs configuré par Joomla, avec un repli
vers le répertoire `logs` du site. L'écran **À propos > Afficher le journal** facilite
sa consultation.

Consultez aussi :

- les logs PHP du serveur ;
- les logs du serveur web ;
- les messages système Joomla ;
- la console du navigateur pour les problèmes d'interface.

## Mode Debug de vue

Utilisez-le pour une vue précise lorsque vous devez inspecter :

- les identifiants ;
- les permissions ;
- les filtres, tris et pagination ;
- les logs générés pendant la requête.

Le panneau est visible par les utilisateurs autorisés à voir la vue. Ne le laissez pas
actif en production.

## Audit et REPAIR DB

L'audit est diagnostique. **REPAIR DB** exécute un workflow de corrections proposées,
notamment sur :

- index dupliqués ;
- tables et menus historiques ;
- encodages et collations ;
- données compactées ;
- colonnes d'audit ;
- doublons de plugins ;
- synchronisation de champs BreezingForms ;
- références et catégories.

Certaines vérifications restent manuelles. Lisez chaque étape avant de continuer.

La conversion de collation peut verrouiller ou reconstruire une table.

## Problèmes de migration

### Tables historiques encore présentes

- lancez l'audit ;
- vérifiez si la table cible existe ;
- comparez le nombre de lignes ;
- ne fusionnez pas manuellement deux tables non vides ;
- conservez les journaux et la sauvegarde.

### Champs BreezingForms manquants

Le workflow de réparation peut ajouter dans la vue les champs source manquants.
Si le formulaire BF lui-même n'existe plus, sélectionnez une autre source ou restaurez
le formulaire.

### Menus incorrects

Recherchez les liens contenant :

```text
option=com_contentbuilder
```

et remplacez-les par `option=com_contentbuilderng` si l'installateur ne les a pas
normalisés.

## Problèmes de stockage

### Table interne absente

Enregistrez d'abord le stockage pour créer la table, puis lancez **Datatable Sync**.

### Table externe introuvable

Le bouton Enregistrer ne crée pas nécessairement la table externe. Créez-la dans la
base ou corrigez son nom.

### Import incomplet

- vérifiez le format CSV/XLSX/XLS ;
- vérifiez les en-têtes uniques ;
- contrôlez le délimiteur ;
- essayez la réparation d'encodage ;
- augmentez prudemment la mémoire PHP si nécessaire ;
- testez un échantillon réduit.

## Problèmes de permissions

Si un bouton est visible mais l'action refusée :

- contrôlez les permissions frontend et backend séparément ;
- contrôlez l'héritage des groupes ;
- vérifiez les limites ;
- vérifiez la propriété ;
- vérifiez la publication de la vue ;
- vérifiez les validations ou vérifications requises.

Voir la [checklist ACL](permissions-acl.md).

## Problèmes API

### Réponse 403

Vérifiez :

- permission API ;
- permission de l'action ;
- permission Stats pour `action=stats` ;
- champ publié et API autorisée ;
- identité Joomla de la requête.

### Réponse vide après sparse fieldset

Les ressources non nommées dans `fields[...]` sont supprimées. Ajoutez chaque
ressource nécessaire.

### Champ « non autorisé »

Utilisez le nom réel du champ source ou son label reconnu et activez **API autorisée**
sur l'élément de la vue.

## Problèmes d'upload

- extension autorisée ;
- taille maximale ;
- dossier existant et modifiable ;
- configuration PHP `upload_max_filesize` et `post_max_size` ;
- protection `.htaccess` compatible avec le serveur.

La protection du répertoire documentée par le composant ne fonctionne pas de la même
manière sur Windows : **À vérifier** sur ce type d'hébergement.

## Problèmes d'e-mail

- configuration globale d'envoi Joomla ;
- destinataires résolus ;
- variables de champs valides ;
- fichiers joints existants ;
- taille totale acceptable ;
- format HTML cohérent ;
- erreur détaillée dans les logs.

## Compatibilité

Le périmètre de ce dépôt est :

- Joomla 6 uniquement ;
- PHP 8.3 minimum.

Le manifeste SQL est MySQL. MariaDB est mentionné dans le guide de migration comme
environnement attendu, mais la matrice exacte de versions n'est pas documentée :
**À vérifier** avant production.

## Procédure de diagnostic

1. reproduire avec une URL et un utilisateur identifiés ;
2. noter l'heure exacte ;
3. désactiver temporairement les caches concernés ;
4. activer le Debug de la vue si nécessaire ;
5. consulter `com_contentbuilderng.log` et les logs PHP ;
6. vérifier la source, l'ID de vue et l'ID d'enregistrement ;
7. vérifier les permissions ;
8. vérifier la publication, la langue et les filtres ;
9. relancer l'audit ;
10. reproduire sur une copie avant toute réparation SQL.
