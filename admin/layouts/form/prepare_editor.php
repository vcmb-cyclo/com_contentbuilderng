<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Language\Text;

$snippetOptions = is_array($displayData['snippetOptions'] ?? null) ? $displayData['snippetOptions'] : [];
$effectOptions = is_array($displayData['effectOptions'] ?? null) ? $displayData['effectOptions'] : [];
$selectId = (string) ($displayData['selectId'] ?? '');
$slotName = (string) ($displayData['slotName'] ?? '');
$slotValueId = (string) ($displayData['slotValueId'] ?? '');
$slotLabelId = (string) ($displayData['slotLabelId'] ?? '');
$effectSelectId = (string) ($displayData['effectSelectId'] ?? '');
$addButtonId = (string) ($displayData['addButtonId'] ?? '');
$addButtonOnclick = (string) ($displayData['addButtonOnclick'] ?? '');
$hintId = (string) ($displayData['hintId'] ?? '');
$fieldName = (string) ($displayData['fieldName'] ?? '');
$editorId = (string) ($displayData['editorId'] ?? '');
$value = (string) ($displayData['value'] ?? '');
$emptyValue = (string) ($displayData['emptyValue'] ?? '');
$addButtonTextKey = (string) ($displayData['addButtonTextKey'] ?? 'COM_CONTENTBUILDERNG_DETAILS_PREPARE_SNIPPET_ADD');
$showExamplesModal = !empty($displayData['showExamplesModal']);
$examplesText = (string) ($displayData['examplesText'] ?? '');

if (trim($value) === '' && $emptyValue !== '') {
    $value = $emptyValue;
}
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3 cb-prepare-tools">
    <label class="form-label mb-0" for="<?php echo $selectId; ?>">
        <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_LABEL'); ?>
    </label>
    <select class="form-select form-select-sm cb-snippet-select" id="<?php echo $selectId; ?>">
        <?php if (!empty($snippetOptions)) : ?>
            <option value=""><?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_PLACEHOLDER'); ?></option>
            <?php foreach ($snippetOptions as $snippetOption) : ?>
                <option value="<?php echo htmlspecialchars((string) ($snippetOption['item_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($snippetOption['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        <?php else : ?>
            <option value=""><?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_EMPTY'); ?></option>
        <?php endif; ?>
    </select>
    <span class="d-inline-flex align-items-center gap-2">
        <span class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="radio" name="<?php echo $slotName; ?>" id="<?php echo $slotValueId; ?>" value="value" checked="checked" <?php echo empty($snippetOptions) ? 'disabled="disabled"' : ''; ?> />
            <label class="form-check-label" for="<?php echo $slotValueId; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_COLUMN_HEADER_VALUE'); ?></label>
        </span>
        <span class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="radio" name="<?php echo $slotName; ?>" id="<?php echo $slotLabelId; ?>" value="label" <?php echo empty($snippetOptions) ? 'disabled="disabled"' : ''; ?> />
            <label class="form-check-label" for="<?php echo $slotLabelId; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_TARGET_LABEL_OPTION'); ?></label>
        </span>
    </span>
    <label class="form-label mb-0" for="<?php echo $effectSelectId; ?>">
        <?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_LABEL'); ?>
    </label>
    <select class="form-select form-select-sm cb-effect-select" id="<?php echo $effectSelectId; ?>" <?php echo empty($snippetOptions) ? 'disabled="disabled"' : ''; ?>>
        <?php foreach ($effectOptions as $effectOption) : ?>
            <option value="<?php echo htmlspecialchars((string) ($effectOption['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) ($effectOption['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button
        type="button"
        class="btn btn-sm btn-outline-secondary text-nowrap"
        id="<?php echo $addButtonId; ?>"
        onclick="<?php echo $addButtonOnclick; ?>"
        <?php echo empty($snippetOptions) ? 'disabled="disabled"' : ''; ?>>
        <?php echo Text::_($addButtonTextKey); ?>
    </button>
    <button
        type="button"
        class="btn btn-sm px-2"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="<?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_BUTTON_TIP'); ?>"
        aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_BUTTON_TIP'); ?>"
        onclick="cbOpenPrepareExamples(this);">
        <span class="fa-solid fa-circle-question" aria-hidden="true"></span>
    </button>
    <small id="<?php echo $hintId; ?>" class="text-success d-none">
        <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_HINT'); ?>
    </small>
</div>
<?php if ($showExamplesModal) : ?>
    <div class="modal fade" id="cb-prepare-examples-modal" tabindex="-1" aria-labelledby="cb-prepare-examples-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cb-prepare-examples-modal-label"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_TITLE'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body">
                    <pre class="mb-0"><code><?php echo htmlspecialchars($examplesText, ENT_QUOTES, 'UTF-8'); ?></code></pre>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php
$params = ['syntax' => 'php'];
$editor = Editor::getInstance('codemirror');
echo $editor->display(
    $fieldName,
    $value,
    '100%',
    '550',
    '75',
    '20',
    false,
    $editorId,
    null,
    null,
    $params
);
?>
