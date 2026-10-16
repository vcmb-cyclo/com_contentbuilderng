<?php

/**
 * @package     ContentBuilderNG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$item = $displayData['item'] ?? null;
$themePlugins = is_array($displayData['themePlugins'] ?? null) ? $displayData['themePlugins'] : [];
$formatTypeDisplay = $displayData['formatTypeDisplay'] ?? null;
$elementsTableHtml = (string) ($displayData['elementsTableHtml'] ?? '');
$allBfSystemFields = is_array($displayData['allBfSystemFields'] ?? null) ? $displayData['allBfSystemFields'] : [];
$isBreezingFormsType = (bool) ($displayData['isBreezingFormsType'] ?? false);

if (!is_object($item) || !is_callable($formatTypeDisplay)) {
    return;
}

?>
<fieldset id="cb-form-view-general" class="mb-3">
    <div class="row g-3 align-items-end mb-2">
        <div class="col-12 col-lg-4 d-flex flex-wrap flex-lg-nowrap align-items-center gap-2">
            <label for="name" class="mb-0 text-nowrap">
                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_VIEW_NAME_TIP'); ?>"><b><?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>:</b></span>
            </label>
            <input class="form-control form-control-sm flex-grow-1 cb-form-view-inline-input" type="text" name="jform[name]" id="name" size="32"
                maxlength="255"
                value="<?php echo htmlspecialchars($item->name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="col-12 col-lg-4 d-flex flex-wrap flex-lg-nowrap align-items-center gap-2">
            <label for="tag" class="mb-0 text-nowrap">
                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_VIEW_TAG_TIP'); ?>"><b><?php echo Text::_('COM_CONTENTBUILDERNG_VIEW_CATEGORY_LABEL'); ?></b></span>
            </label>
            <input class="form-control form-control-sm flex-grow-1 cb-form-view-inline-input" type="text" name="jform[tag]" id="tag" size="32"
                maxlength="255"
                value="<?php echo htmlspecialchars($item->tag ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="col-12 col-lg-4">
            <div class="d-flex align-items-center gap-2 flex-nowrap">
                <label for="theme_plugin" class="mb-0">
                    <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_THEME_PLUGIN_TIP'); ?>"><b><?php echo Text::_('COM_CONTENTBUILDERNG_THEME_PLUGIN'); ?>:</b></span>
                </label>
                <select class="form-select-sm w-auto" name="jform[theme_plugin]" id="theme_plugin">
                    <?php foreach ($themePlugins as $themePlugin) : ?>
                        <option value="<?php echo htmlspecialchars((string) $themePlugin, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $themePlugin == $item->theme_plugin ? ' selected="selected"' : ''; ?>>
                            <?php echo htmlspecialchars((string) $themePlugin, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if ((int) ($item->id ?? 0) < 1) : ?>
        <label for="cb_form_type_select">
            <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_TIP'); ?>"><b><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE'); ?>:</b></span>
        </label>
        <select class="form-select-sm" name="jform[type]" id="cb_form_type_select">
            <?php foreach ((array) ($item->types ?? []) as $type) : ?>
                <?php if (trim((string) $type) === '') {
                    continue;
                } ?>
                <?php $typeValue = (string) $type;
                $typeDisplay = $formatTypeDisplay($typeValue); ?>
                <option value="<?php echo htmlspecialchars($typeValue, ENT_QUOTES, 'UTF-8'); ?>"
                    data-full="<?php echo htmlspecialchars((string) ($typeDisplay['full'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    title="<?php echo htmlspecialchars((string) ($typeDisplay['full'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($typeDisplay['short'] ?? $typeValue), ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php else : ?>
        <div></div>
        <div class="alert py-2 mb-2 d-flex flex-wrap align-items-center gap-3" id="cb-form-view-source">
            <div class="d-flex flex-grow-1 flex-wrap align-items-center gap-2 cb-form-view-source-details">
                <label<?php echo !$item->reference_id ? ' for="cb_form_reference_select"' : ''; ?>>
                    <b><?php echo Text::_('COM_CONTENTBUILDERNG_FORM_SOURCE'); ?>:</b>
                </label>
                <?php if (!$item->reference_id) : ?>
                    <select class="form-select-sm" name="jform[reference_id]" id="cb_form_reference_select" style="max-width: 200px;">
                        <option value="0" selected="selected"><?php echo Text::_('COM_CONTENTBUILDERNG_CHOOSE'); ?></option>
                        <?php foreach ((array) ($item->forms ?? []) as $referenceId => $title) : ?>
                            <option value="<?php echo $referenceId; ?>"><?php echo htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <?php
                    $sourceTitle = (string) ($item->form->getTitle() ?? '');
                    $sourceReferenceId = (int) $item->form->getReferenceId();
                    $sourceType = (string) ($item->type ?? '');
                    $sourceTypeName = trim((string) ($item->type_name ?? ''));
                    $sourceEditLink = '';

                    if ($sourceType === 'com_breezingformsng' && $sourceReferenceId > 0 && $sourceTypeName !== '') {
                        $bfOption = null;
                        foreach (['com_breezingformsng'] as $_opt) {
                            if (is_dir(JPATH_ADMINISTRATOR . '/components/' . $_opt)) {
                                $bfOption = $_opt;
                                break;
                            }
                        }
                        $sourceEditLink = $bfOption !== null ? Route::_('index.php?option=' . $bfOption . '&act=quickmode&formName=' . rawurlencode($sourceTypeName) . '&form=' . $sourceReferenceId, false) : '';
                    } elseif ($sourceType === 'com_contentbuilderng' && $sourceReferenceId > 0) {
                        $sourceEditLink = Route::_('index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $sourceReferenceId, false);
                    }
                    ?>
                    <?php if ($sourceEditLink !== '') : ?>
                        <a href="<?php echo htmlspecialchars($sourceEditLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sourceTitle, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php else : ?>
                        <?php echo htmlspecialchars($sourceTitle, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                    <input type="hidden" name="jform[reference_id]" value="<?php echo $sourceReferenceId; ?>" />
                <?php endif; ?>

                <label>
                    <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_TIP'); ?>"><b><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE'); ?>:</b></span>
                </label>
                <?php $typeDisplay = $formatTypeDisplay((string) ($item->type ?? '')); ?>
                <span class="editlinktip hasTip" title="<?php echo htmlspecialchars((string) ($typeDisplay['full'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($typeDisplay['short'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <input type="hidden" name="jform[type]" value="<?php echo $item->type; ?>" />
                <input type="hidden" name="jform[type_name]" value="<?php echo isset($item->type_name) ? $item->type_name : ''; ?>" />
            </div>
            <?php if ($isBreezingFormsType && $allBfSystemFields !== []) : ?>
                <?php echo LayoutHelper::render(
                    'form.bf_system_fields_modal',
                    ['item' => $item, 'allBfSystemFields' => $allBfSystemFields],
                    dirname(__DIR__)
                ); ?>
            <?php endif; ?>
        </div>
        <div></div>
    <?php endif; ?>
</fieldset>

<div id="cb-form-view-elements">
    <?php echo $elementsTableHtml; ?>
</div>
