<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$mode = in_array((string) ($this->mode ?? 'export'), ['export', 'import'], true) ? (string) $this->mode : 'export';
$isExportMode = $mode === 'export';
$isImportMode = $mode === 'import';
$importReport = is_array($this->importReport ?? null) ? $this->importReport : [];
$importSummary = is_array($importReport['summary'] ?? null) ? $importReport['summary'] : [];
$importDetails = array_values(array_filter(array_map('strval', (array) ($importSummary['details'] ?? [])), static fn(string $v): bool => trim($v) !== ''));
$importGeneratedAt = (string) ($importReport['generated_at'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$importTablesCount = (int) ($importSummary['tables'] ?? 0);
$importRowsCount = (int) ($importSummary['rows'] ?? 0);
$selectedSections = array_fill_keys((array) ($this->selectedSections ?? []), true);
$selectedFormIds = array_fill_keys(array_map('intval', (array) ($this->selectedFormIds ?? [])), true);
$selectedStorageIds = array_fill_keys(array_map('intval', (array) ($this->selectedStorageIds ?? [])), true);
?>

<form
    action="<?php echo Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=' . ($isExportMode ? 'export' : 'import')); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
    enctype="multipart/form-data"
>
    <div class="container-fluid">
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_TITLE'); ?></h2>
                        <p class="text-muted mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_DESC'); ?></p>

                        <ul class="nav nav-tabs mb-3" role="tablist" aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_TITLE'); ?>">
                            <li class="nav-item" role="presentation">
                                <a
                                    class="nav-link <?php echo $isExportMode ? 'active' : ''; ?>"
                                    href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=export'); ?>"
                                    role="tab"
                                    aria-selected="<?php echo $isExportMode ? 'true' : 'false'; ?>"
                                >
                                    <span class="fa fa-download me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION'); ?>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a
                                    class="nav-link <?php echo $isImportMode ? 'active' : ''; ?>"
                                    href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=import'); ?>"
                                    role="tab"
                                    aria-selected="<?php echo $isImportMode ? 'true' : 'false'; ?>"
                                >
                                    <span class="fa fa-upload me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION'); ?>
                                </a>
                            </li>
                        </ul>

                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTIONS_LABEL'); ?></label>
                                    <span class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-sections-check-all">
                                            <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-sections-uncheck-all">
                                            <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                        </button>
                                    </span>
                                </div>
                                <div class="border rounded p-3" style="max-height: 340px; overflow-y: auto;">
                                    <?php foreach ((array) ($this->configSections ?? []) as $sectionKey => $sectionMeta) : ?>
                                        <?php
                                        $sectionLabel = trim((string) ($sectionMeta['label'] ?? ''));
                                        $sectionDescription = trim((string) ($sectionMeta['description'] ?? ''));
                                        if ((string) $sectionKey === 'forms') {
                                            $sectionDescription .= ($sectionDescription !== '' ? ' ' : '') . 'Exporte aussi les elements, list states et resource access lies aux formulaires selectionnes.';
                                        }
                                        if ((string) $sectionKey === 'storages') {
                                            $sectionDescription .= ($sectionDescription !== '' ? ' ' : '') . 'Exporte aussi les storage fields lies aux storages selectionnes.';
                                        }
                                        ?>
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input cb-config-section-toggle"
                                                type="checkbox"
                                                name="cb_config_sections[]"
                                                id="cb_config_section_<?php echo htmlspecialchars((string) $sectionKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                value="<?php echo htmlspecialchars((string) $sectionKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo isset($selectedSections[(string) $sectionKey]) ? 'checked="checked"' : ''; ?>
                                            >
                                            <label class="form-check-label" for="cb_config_section_<?php echo htmlspecialchars((string) $sectionKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </label>
                                            <?php if ($sectionDescription !== '') : ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($sectionDescription, ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <?php if ($isExportMode) : ?>
                                    <div class="mb-3" id="cb-config-forms-box">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SELECT_FORMS'); ?></label>
                                            <span class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-forms-check-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-forms-uncheck-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                                </button>
                                            </span>
                                        </div>
                                        <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                                            <?php if (empty($this->forms)) : ?>
                                                <div class="alert alert-info mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>
                                            <?php else : ?>
                                                <?php foreach ((array) $this->forms as $formRow) : ?>
                                                    <?php
                                                    $formId = (int) ($formRow['id'] ?? 0);
                                                    $formName = trim((string) ($formRow['name'] ?? ''));
                                                    if ($formName === '') {
                                                        $formName = '#' . $formId;
                                                    }
                                                    $formType = trim((string) ($formRow['type'] ?? ''));
                                                    $formReferenceId = (string) ($formRow['reference_id'] ?? '');
                                                    $isPublished = (int) ($formRow['published'] ?? 0) === 1;
                                                    $meta = [];
                                                    if ($formType !== '') {
                                                        $meta[] = $formType;
                                                    }
                                                    if ($formReferenceId !== '') {
                                                        $meta[] = '#' . $formReferenceId;
                                                    }
                                                    $meta[] = $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
                                                    ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input cb-config-form-item"
                                                            type="checkbox"
                                                            name="cb_config_form_ids[]"
                                                            id="cb_config_form_<?php echo $formId; ?>"
                                                            value="<?php echo $formId; ?>"
                                                            <?php echo isset($selectedFormIds[$formId]) ? 'checked="checked"' : ''; ?>
                                                        >
                                                        <label class="form-check-label" for="cb_config_form_<?php echo $formId; ?>">
                                                            <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars(implode(' / ', $meta), ENT_QUOTES, 'UTF-8'); ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-2" id="cb-config-storages-box">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SELECT_STORAGES'); ?></label>
                                            <span class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-storages-check-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-storages-uncheck-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                                </button>
                                            </span>
                                        </div>
                                        <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                                            <?php if (empty($this->storages)) : ?>
                                                <div class="alert alert-info mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>
                                            <?php else : ?>
                                                <?php foreach ((array) $this->storages as $storageRow) : ?>
                                                    <?php
                                                    $storageId = (int) ($storageRow['id'] ?? 0);
                                                    $storageTitle = trim((string) ($storageRow['title'] ?? ''));
                                                    $storageName = trim((string) ($storageRow['name'] ?? ''));
                                                    $storageLabel = $storageTitle !== '' ? $storageTitle : ($storageName !== '' ? $storageName : ('#' . $storageId));
                                                    $storageMeta = $storageName !== '' ? $storageName : ('#' . $storageId);
                                                    if ((int) ($storageRow['bytable'] ?? 0) === 1) {
                                                        $storageMeta .= ' / bytable';
                                                    }
                                                    ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input cb-config-storage-item"
                                                            type="checkbox"
                                                            name="cb_config_storage_ids[]"
                                                            id="cb_config_storage_<?php echo $storageId; ?>"
                                                            value="<?php echo $storageId; ?>"
                                                            <?php echo isset($selectedStorageIds[$storageId]) ? 'checked="checked"' : ''; ?>
                                                        >
                                                        <label class="form-check-label" for="cb_config_storage_<?php echo $storageId; ?>">
                                                            <?php echo htmlspecialchars($storageLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($storageMeta, ENT_QUOTES, 'UTF-8'); ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isImportMode) : ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_MODE_LABEL'); ?></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="cb_config_import_mode" id="cb_config_import_mode_merge" value="merge" checked="checked">
                                            <label class="form-check-label" for="cb_config_import_mode_merge">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_MODE_MERGE'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="cb_config_import_mode" id="cb_config_import_mode_replace" value="replace">
                                            <label class="form-check-label" for="cb_config_import_mode_replace">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_MODE_REPLACE'); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cb_config_import_file" class="form-label fw-semibold"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FILE_LABEL'); ?></label>
                                        <input
                                            type="file"
                                            class="form-control"
                                            id="cb_config_import_file"
                                            name="cb_config_import_file"
                                            accept=".json,application/json"
                                        >
                                        <small class="text-muted d-block mt-1"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FILE_HELP'); ?></small>
                                    </div>
                                    <div class="border rounded p-3 d-none" id="cb-config-import-preview">
                                        <div class="alert alert-info py-2 mb-3" id="cb-config-import-preview-summary"></div>
                                        <div class="row g-3">
                                            <div class="col-lg-6" id="cb-config-import-forms-box">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SELECT_FORMS'); ?></label>
                                                    <span class="d-inline-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-import-forms-check-all">
                                                            <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-import-forms-uncheck-all">
                                                            <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                                        </button>
                                                    </span>
                                                </div>
                                                <div class="border rounded p-3" id="cb-config-import-forms-list" style="max-height: 220px; overflow-y: auto;"></div>
                                            </div>
                                            <div class="col-lg-6" id="cb-config-import-storages-box">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SELECT_STORAGES'); ?></label>
                                                    <span class="d-inline-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-import-storages-check-all">
                                                            <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-import-storages-uncheck-all">
                                                            <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                                        </button>
                                                    </span>
                                                </div>
                                                <div class="border rounded p-3" id="cb-config-import-storages-list" style="max-height: 220px; overflow-y: auto;"></div>
                                            </div>
                                        </div>
                                        <div id="cb-config-import-hidden-inputs"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="submit" id="cb-config-submit-button" class="btn <?php echo $isExportMode ? 'btn-success cb-config-export-btn' : 'btn-primary'; ?>">
                                <span class="fa <?php echo $isExportMode ? 'fa-download' : 'fa-upload'; ?> me-1" aria-hidden="true"></span>
                                <?php echo $isExportMode ? Text::_('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION') : Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isImportMode) : ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="h6 card-title mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_LOG_TITLE'); ?></h3>
                            <?php if ($importReport === []) : ?>
                                <div class="alert alert-info mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_LOG_EMPTY'); ?></div>
                            <?php else : ?>
                                <p class="text-muted small mb-2">
                                    <?php echo Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_LOG_LAST_RUN', $importGeneratedAt, $importTablesCount, $importRowsCount); ?>
                                </p>
                                <?php if ($importDetails === []) : ?>
                                    <div class="alert alert-secondary mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>
                                <?php else : ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($importDetails as $importDetail) : ?>
                                            <li class="list-group-item px-2 py-1"><?php echo htmlspecialchars($importDetail, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" id="cb_config_task" value="<?php echo $isExportMode ? 'about.exportConfiguration' : 'about.importConfiguration'; ?>">
    <input type="hidden" name="return_view" value="configtransfer">
    <input type="hidden" name="return_mode" value="<?php echo $isExportMode ? 'export' : 'import'; ?>">
    <?php if ($isExportMode) : ?>
        <input type="hidden" name="cb_require_form_filter" value="1">
        <input type="hidden" name="cb_require_storage_filter" value="1">
    <?php endif; ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
    (function () {
        function setChecked(selector, checked) {
            var nodes = document.querySelectorAll(selector);
            if (!nodes.length) {
                return;
            }

            for (var i = 0; i < nodes.length; i++) {
                nodes[i].checked = checked;
            }
        }

        function isSectionChecked(sectionKey) {
            var node = document.getElementById('cb_config_section_' + sectionKey);
            return !!(node && node.checked);
        }

        function setGroupState(containerId, itemSelector, enabled) {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            container.style.opacity = enabled ? '1' : '.55';

            var items = container.querySelectorAll(itemSelector);
            for (var i = 0; i < items.length; i++) {
                items[i].disabled = !enabled;
            }
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setImportPreviewGroupState(containerId, itemSelector, enabled) {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            container.style.opacity = enabled ? '1' : '.55';

            var items = container.querySelectorAll(itemSelector);
            for (var i = 0; i < items.length; i++) {
                items[i].disabled = !enabled;
            }
        }

        function syncImportSubmitState() {
            var submitButton = document.getElementById('cb-config-submit-button');
            if (!submitButton) {
                return;
            }

            if (<?php echo $isExportMode ? 'true' : 'false'; ?>) {
                submitButton.disabled = false;
                return;
            }

            var importFormsEnabled = isSectionChecked('forms');
            var importStoragesEnabled = isSectionChecked('storages');
            var hasSelectedForms = importFormsEnabled && document.querySelectorAll('.cb-config-import-form-item:checked').length > 0;
            var hasSelectedStorages = importStoragesEnabled && document.querySelectorAll('.cb-config-import-storage-item:checked').length > 0;

            submitButton.disabled = !(hasSelectedForms || hasSelectedStorages);
        }

        function syncImportHiddenInputs() {
            var hiddenContainer = document.getElementById('cb-config-import-hidden-inputs');
            if (!hiddenContainer) {
                return;
            }

            var markup = '';
            var importFormsEnabled = isSectionChecked('forms');
            var importStoragesEnabled = isSectionChecked('storages');
            var formItems = document.querySelectorAll('.cb-config-import-form-item:checked');
            var storageItems = document.querySelectorAll('.cb-config-import-storage-item:checked');

            if (importFormsEnabled) {
                for (var i = 0; i < formItems.length; i++) {
                    markup += '<input type="hidden" name="cb_config_import_form_names[]" value="' + escapeHtml(formItems[i].value) + '">';
                }
            }

            if (importStoragesEnabled) {
                for (var j = 0; j < storageItems.length; j++) {
                    markup += '<input type="hidden" name="cb_config_import_storage_names[]" value="' + escapeHtml(storageItems[j].value) + '">';
                }
            }

            hiddenContainer.innerHTML = markup;
            syncImportSubmitState();
        }

        function syncSectionFilters() {
            var formFiltersEnabled = isSectionChecked('forms');
            var storageFiltersEnabled = isSectionChecked('storages');

            setGroupState('cb-config-forms-box', '.cb-config-form-item', formFiltersEnabled);
            setGroupState('cb-config-storages-box', '.cb-config-storage-item', storageFiltersEnabled);
            setImportPreviewGroupState('cb-config-import-forms-box', '.cb-config-import-form-item', formFiltersEnabled);
            setImportPreviewGroupState('cb-config-import-storages-box', '.cb-config-import-storage-item', storageFiltersEnabled);
            syncImportHiddenInputs();
            syncImportSubmitState();
        }

        function bindCheckButtons(checkButtonId, uncheckButtonId, selector, onChange) {
            var checkButton = document.getElementById(checkButtonId);
            if (checkButton) {
                checkButton.addEventListener('click', function () {
                    setChecked(selector, true);
                    if (typeof onChange === 'function') {
                        onChange();
                    }
                });
            }

            var uncheckButton = document.getElementById(uncheckButtonId);
            if (uncheckButton) {
                uncheckButton.addEventListener('click', function () {
                    setChecked(selector, false);
                    if (typeof onChange === 'function') {
                        onChange();
                    }
                });
            }
        }

        bindCheckButtons('cb-config-sections-check-all', 'cb-config-sections-uncheck-all', '.cb-config-section-toggle', syncSectionFilters);
        bindCheckButtons('cb-config-forms-check-all', 'cb-config-forms-uncheck-all', '.cb-config-form-item');
        bindCheckButtons('cb-config-storages-check-all', 'cb-config-storages-uncheck-all', '.cb-config-storage-item');
        bindCheckButtons('cb-config-import-forms-check-all', 'cb-config-import-forms-uncheck-all', '.cb-config-import-form-item', syncImportHiddenInputs);
        bindCheckButtons('cb-config-import-storages-check-all', 'cb-config-import-storages-uncheck-all', '.cb-config-import-storage-item', syncImportHiddenInputs);

        var sectionToggles = document.querySelectorAll('.cb-config-section-toggle');
        for (var i = 0; i < sectionToggles.length; i++) {
            sectionToggles[i].addEventListener('change', syncSectionFilters);
        }

        function extractPreviewRows(payload, sectionKey) {
            if (!payload || typeof payload !== 'object') {
                return [];
            }

            var data = payload.data;
            if (data && typeof data === 'object' && data[sectionKey] && Array.isArray(data[sectionKey].rows)) {
                return data[sectionKey].rows;
            }

            if (Array.isArray(payload.tables)) {
                var legacyTables = {
                    forms: '#__contentbuilderng_forms',
                    storages: '#__contentbuilderng_storages'
                };
                for (var i = 0; i < payload.tables.length; i++) {
                    var entry = payload.tables[i];
                    if (entry && entry.table === legacyTables[sectionKey] && Array.isArray(entry.rows)) {
                        return entry.rows;
                    }
                }
            }

            return [];
        }

        function renderImportPreviewList(listId, itemClass, inputName, rows, getValue, getLabel) {
            var list = document.getElementById(listId);
            if (!list) {
                return;
            }

            if (!rows.length) {
                list.innerHTML = '<div class="alert alert-secondary mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>';
                return;
            }

            var markup = '';
            for (var i = 0; i < rows.length; i++) {
                var value = getValue(rows[i]);
                if (!value) {
                    continue;
                }
                var label = getLabel(rows[i]);
                var id = inputName + '_' + i;
                markup += ''
                    + '<div class="form-check mb-1">'
                    + '<input class="form-check-input ' + itemClass + '" type="checkbox" id="' + escapeHtml(id) + '" value="' + escapeHtml(value) + '" checked="checked">'
                    + '<label class="form-check-label" for="' + escapeHtml(id) + '">' + escapeHtml(label) + '</label>'
                    + '</div>';
            }

            list.innerHTML = markup !== '' ? markup : '<div class="alert alert-secondary mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>';
        }

        function bindImportPreviewCheckboxes() {
            var previewItems = document.querySelectorAll('.cb-config-import-form-item, .cb-config-import-storage-item');
            for (var i = 0; i < previewItems.length; i++) {
                previewItems[i].addEventListener('change', syncImportHiddenInputs);
            }
        }

        function renderImportPreview(payload) {
            var preview = document.getElementById('cb-config-import-preview');
            var summary = document.getElementById('cb-config-import-preview-summary');
            if (!preview || !summary) {
                return;
            }

            var formRows = extractPreviewRows(payload, 'forms');
            var storageRows = extractPreviewRows(payload, 'storages');

            renderImportPreviewList(
                'cb-config-import-forms-list',
                'cb-config-import-form-item',
                'cb_config_import_form',
                formRows,
                function (row) {
                    return row && row.name ? String(row.name) : '';
                },
                function (row) {
                    var name = row && row.name ? String(row.name) : '';
                    var type = row && row.type ? String(row.type) : '';
                    return type ? (name + ' (' + type + ')') : name;
                }
            );

            renderImportPreviewList(
                'cb-config-import-storages-list',
                'cb-config-import-storage-item',
                'cb_config_import_storage',
                storageRows,
                function (row) {
                    return row && row.name ? String(row.name) : '';
                },
                function (row) {
                    var title = row && row.title ? String(row.title) : '';
                    var name = row && row.name ? String(row.name) : '';
                    return title && title !== name ? (title + ' (' + name + ')') : name;
                }
            );

            summary.textContent = 'Fichier analyse : ' + formRows.length + ' form(s), ' + storageRows.length + ' storage(s).';
            preview.classList.remove('d-none');
            bindImportPreviewCheckboxes();
            syncSectionFilters();
            syncImportHiddenInputs();
        }

        function resetImportPreview(message) {
            var preview = document.getElementById('cb-config-import-preview');
            var summary = document.getElementById('cb-config-import-preview-summary');
            var formsList = document.getElementById('cb-config-import-forms-list');
            var storagesList = document.getElementById('cb-config-import-storages-list');
            var hiddenContainer = document.getElementById('cb-config-import-hidden-inputs');

            if (preview) {
                preview.classList.add('d-none');
            }
            if (summary) {
                summary.textContent = message || '';
            }
            if (formsList) {
                formsList.innerHTML = '';
            }
            if (storagesList) {
                storagesList.innerHTML = '';
            }
            if (hiddenContainer) {
                hiddenContainer.innerHTML = '';
            }
            syncImportSubmitState();
        }

        var importFileInput = document.getElementById('cb_config_import_file');
        if (importFileInput && typeof window.FileReader === 'function') {
            importFileInput.addEventListener('change', function () {
                if (!importFileInput.files || !importFileInput.files.length) {
                    resetImportPreview('');
                    return;
                }

                var reader = new FileReader();
                reader.onload = function () {
                    try {
                        var payload = JSON.parse(String(reader.result || ''));
                        renderImportPreview(payload);
                    } catch (error) {
                        resetImportPreview('Impossible de lire le contenu du fichier pour afficher un apercu.');
                    }
                };
                reader.onerror = function () {
                    resetImportPreview('Impossible de lire le contenu du fichier pour afficher un apercu.');
                };
                reader.readAsText(importFileInput.files[0]);
            });
        }

        syncSectionFilters();
        syncImportSubmitState();
    }());
</script>
