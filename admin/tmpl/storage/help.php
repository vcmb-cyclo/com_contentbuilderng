<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$input = Factory::getApplication()->getInput();
$storageId = (int) $input->getInt('id', 0);
$backToEdit = Route::_('index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $storageId);
$backToList = Route::_('index.php?option=com_contentbuilderng&view=storages');
?>
<div class="container-fluid p-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_TITLE'); ?></h1>
    <p class="text-muted mb-4">
        Cet écran permet de configurer une source de données (storage), ses champs et la synchronisation avec la table SQL.
        Utilisez cette aide comme check-list rapide pour éviter les erreurs de structure.
    </p>

    <div class="alert alert-info mb-4">
        <strong>Résumé :</strong> un storage décrit la table, les colonnes exposées et les opérations de maintenance (création / synchro / import CSV).
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">1) Paramètres principaux</h2>
                    <ul class="mb-0">
                        <li><strong>Name / table:</strong> nom technique de la table SQL.</li>
                        <li><strong>Title:</strong> libellé fonctionnel affiché dans l'administration.</li>
                        <li><strong>Mode interne/externe:</strong> interne = table gérée par CB NG, externe = table existante.</li>
                        <li><strong>Published:</strong> active ou désactive le storage.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">2) Barre d'outils</h2>
                    <ul class="mb-0">
                        <li><strong>Save / Save &amp; New:</strong> enregistre la configuration.</li>
                        <li><strong>Save:</strong> crée/renomme automatiquement la table SQL en mode interne.</li>
                        <li><strong>Datatable Sync:</strong> aligne la structure SQL sur les champs déclarés.</li>
                        <li><strong>Delete fields:</strong> supprime les champs sélectionnés dans la liste.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">3) Gestion des champs</h2>
                    <ul class="mb-0">
                        <li>Ajoutez un champ avec un <strong>nom SQL stable</strong> (évitez les changements fréquents).</li>
                        <li>Définissez un <strong>label clair</strong> pour l'administration.</li>
                        <li>Utilisez les options de groupe uniquement si nécessaire.</li>
                        <li>Avant production, validez que les types de champs correspondent aux données réelles.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">4) Import CSV</h2>
                    <ul class="mb-0">
                        <li>Contrôlez le séparateur et l'encodage avant import.</li>
                        <li><strong>Drop records</strong> vide les données existantes avant import.</li>
                        <li><strong>Published</strong> applique l'état publié/non publié aux lignes importées.</li>
                        <li>Testez d'abord avec un petit échantillon.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-warning mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Erreurs fréquentes et prévention</h2>
            <ul class="mb-0">
                <li><strong>Renommage de table:</strong> faire une sauvegarde SQL avant modification.</li>
                <li><strong>Incohérence champs/table:</strong> exécuter Datatable Sync après tout changement de structure.</li>
                <li><strong>Import incomplet:</strong> vérifier délimiteur CSV, encodage et colonnes attendues.</li>
                <li><strong>Conflits de nom:</strong> ne pas réutiliser un nom de colonne déjà présent avec un autre type.</li>
            </ul>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?php if ($storageId > 0): ?>
            <a class="btn btn-success btn-sm" href="<?php echo $backToEdit; ?>">
                Retour au stockage courant
            </a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="<?php echo $backToList; ?>">
            <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_BACK_TO_STORAGES'); ?>
        </a>
    </div>
</div>
