<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use CB\Component\Contentbuilderng\Administrator\Helper\ListLimitHelper;

/** @var array $displayData */
$storageId   = (int) ($displayData['storageId'] ?? 0);
$item        = $displayData['item'] ?? null;
$records     = is_iterable($displayData['records'] ?? null) ? $displayData['records'] : [];
$labels      = is_array($displayData['columnLabels'] ?? null) ? $displayData['columnLabels'] : [];
$hasPk       = (bool) ($displayData['hasPrimaryKey'] ?? false);
$pagination  = $displayData['pagination'] ?? null;
$listLimit   = max(0, (int) ($displayData['listLimit'] ?? 20));
$listStart   = max(0, (int) ($displayData['listStart'] ?? 0));
$search      = (string) ($displayData['search'] ?? '');
$ordering    = (string) ($displayData['ordering'] ?? '');
$direction   = strtolower((string) ($displayData['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$editBaseUrl = (string) ($displayData['editBaseUrl'] ?? '');

$columns = array_keys($labels);
$isReadOnly = (int) ($item->bytable ?? 0) === 2;
$canMutate = $hasPk && !$isReadOnly && $editBaseUrl !== '';

$records = is_array($records) ? $records : iterator_to_array($records);
$rowCount = count($records);

$total   = $pagination ? (int) $pagination->total : $rowCount;
$pages   = ($listLimit > 0) ? (int) ceil(max(1, $total) / $listLimit) : 1;
$current = ($listLimit > 0) ? (int) floor($listStart / $listLimit) + 1 : 1;

$baseParams = [
    'option'         => 'com_contentbuilderng',
    'view'           => 'storage',
    'layout'         => 'edit',
    'id'             => $storageId,
    'tabStartOffset' => 'tabData',
    'data_limit'     => $listLimit,
    'data_search'    => $search,
    'data_ordering'  => $ordering,
    'data_direction' => $direction,
    'data_start'     => $listStart,
];
$dataUrl = static fn (array $overrides = []): string =>
    Route::_('index.php?' . http_build_query(array_merge($baseParams, $overrides)), false) . '#tabData';

$pageLink = static fn (int $start): string => $dataUrl(['data_start' => max(0, $start)]);

// Lien de tri d'une colonne : bascule asc/desc si déjà triée, sinon asc.
$sortHeader = static function (string $column, string $label) use ($dataUrl, $ordering, $direction): string {
    $active = $ordering === $column;
    $nextDir = ($active && $direction === 'asc') ? 'desc' : 'asc';
    $icon = $active
        ? ($direction === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down')
        : 'fa-solid fa-sort';
    return '<a href="' . htmlspecialchars($dataUrl(['data_ordering' => $column, 'data_direction' => $nextDir, 'data_start' => 0]), ENT_QUOTES, 'UTF-8')
        . '" class="cb-storage-data-sort' . ($active ? ' active' : '') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . ' <span class="' . $icon . '" aria-hidden="true"></span></a>';
};

$truncate = static function ($value): string {
    $value = (string) ($value ?? '');
    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120) . '…';
    }
    return $value;
};
?>
<div class="card border rounded-3 mb-3 cb-storage-data-editor">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 class="h5 mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_TAB'); ?></h2>
            <small class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_TAB_TOOLTIP'); ?></small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php /* Pas de <form> ici : le contenu de l'onglet est rendu dans
                     #adminForm et un <form> imbriqué le fermerait prématurément
                     (perte de boxchecked, cf. joomla-toolbar-button). */ ?>
            <div class="d-flex align-items-center gap-1 cb-storage-data-search" data-cb-search-base="<?php echo htmlspecialchars($dataUrl(['data_search' => '', 'data_start' => 0]), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="search"
                    class="form-control form-control-sm cb-storage-data-search-input"
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="<?php echo htmlspecialchars(Text::_('JSEARCH_FILTER'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(Text::_('JSEARCH_FILTER'), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" class="btn btn-primary btn-sm cb-storage-data-search-submit">
                    <span class="icon-search" aria-hidden="true"></span>
                </button>
            </div>
            <?php if ($canMutate) : ?>
                <div class="btn-group btn-group-sm" role="group">
                    <a class="btn btn-success group-add"
                        href="<?php echo htmlspecialchars($editBaseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        target="_blank"
                        rel="noopener"
                        title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_ADD_RECORD'), ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_ADD_RECORD'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="icon-plus" aria-hidden="true"></span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($isReadOnly) : ?>
            <div class="alert alert-info m-3 mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_READONLY_EXTERNAL_STORAGE_MSG'); ?></div>
        <?php elseif (!$hasPk) : ?>
            <div class="alert alert-warning m-3 mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_NO_PRIMARY_KEY'); ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-striped mb-0 cb-storage-data-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column) : ?>
                            <th class="text-nowrap"><?php echo $sortHeader((string) $column, (string) ($labels[$column] ?? $column)); ?></th>
                        <?php endforeach; ?>
                        <?php if ($canMutate) : ?>
                            <th width="90" class="text-center" aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_EDIT_RECORD'), ENT_QUOTES, 'UTF-8'); ?>"></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rowCount === 0) : ?>
                    <tr>
                        <td colspan="<?php echo count($columns) + ($canMutate ? 1 : 0); ?>" class="text-center text-muted py-3">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_EMPTY'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($records as $record) : ?>
                        <?php
                        $record = (array) $record;
                        $recordId = (int) ($record['id'] ?? 0);
                        $rowLabel = '';
                        foreach ($columns as $column) {
                            if ($column !== 'id' && trim((string) ($record[$column] ?? '')) !== '') {
                                $rowLabel = (string) $record[$column];
                                break;
                            }
                        }
                        if ($rowLabel === '') {
                            $rowLabel = '#' . $recordId;
                        }
                        ?>
                        <tr>
                            <?php foreach ($columns as $column) : ?>
                                <td><?php echo htmlspecialchars($truncate($record[$column] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endforeach; ?>
                            <?php if ($canMutate) : ?>
                                <td class="text-center text-nowrap">
                                    <div class="btn-group btn-group-sm cb-storage-data-actions" role="group">
                                        <a class="btn btn-primary"
                                            href="<?php echo htmlspecialchars($editBaseUrl . '&record_id=' . $recordId, ENT_QUOTES, 'UTF-8'); ?>"
                                            target="_blank"
                                            rel="noopener"
                                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_EDIT_RECORD'), ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_EDIT_RECORD'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="icon-pencil" aria-hidden="true"></span>
                                        </a>
                                        <button type="button"
                                            class="btn btn-danger group-remove"
                                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_DELETE_RECORD'), ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_DELETE_RECORD'), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="return cbDeleteStorageRecord(<?php echo $recordId; ?>, <?php echo htmlspecialchars(json_encode($rowLabel, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>);">
                                            <span class="icon-minus" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    $limitChoices = ListLimitHelper::getPaginationChoices();
    if (!in_array($listLimit, $limitChoices, true)) {
        $limitChoices[] = $listLimit;
        sort($limitChoices);
    }
    ?>
        <div class="card-footer">
            <div class="cb-storage-pagination">
                <div class="cbPagesCounter d-flex flex-wrap align-items-center gap-2">
                    <span><?php echo Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_DATA_PAGE_COUNTER', $current, max(1, $pages), $total); ?></span>
                    <label class="d-flex align-items-center gap-1 mb-0">
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM'); ?></span>
                        <select class="form-select form-select-sm w-auto cb-storage-data-limit"
                            data-cb-limit-base="<?php echo htmlspecialchars($dataUrl(['data_limit' => '__CBLIMIT__', 'data_start' => 0]), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php foreach ($limitChoices as $choice) : ?>
                                <option value="<?php echo (int) $choice; ?>"<?php echo (int) $choice === $listLimit ? ' selected' : ''; ?>>
                                    <?php echo $choice === 0 ? htmlspecialchars(Text::_('JALL'), ENT_QUOTES, 'UTF-8') : (int) $choice; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <?php if ($pages > 1) : ?>
                    <nav class="cb-storage-pages">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $current <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $current <= 1 ? '#' : htmlspecialchars($pageLink(($current - 2) * $listLimit), ENT_QUOTES, 'UTF-8'); ?>">&laquo;</a>
                            </li>
                            <?php for ($p = 1; $p <= $pages; $p++) : ?>
                                <?php if ($p === 1 || $p === $pages || abs($p - $current) <= 2) : ?>
                                    <li class="page-item <?php echo $p === $current ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($pageLink(($p - 1) * $listLimit), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php elseif (abs($p - $current) === 3) : ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $current >= $pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $current >= $pages ? '#' : htmlspecialchars($pageLink($current * $listLimit), ENT_QUOTES, 'UTF-8'); ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
</div>
