<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$item = $displayData['item'] ?? null;
$form = $displayData['form'] ?? null;
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$editablePrepareSnippetOptions = is_array($displayData['editablePrepareSnippetOptions'] ?? null) ? $displayData['editablePrepareSnippetOptions'] : [];
$prepareEffectOptions = is_array($displayData['prepareEffectOptions'] ?? null) ? $displayData['prepareEffectOptions'] : [];

$detailsDefaults = [
    'cb_show_details_top_bar' => 1,
    'cb_show_details_bottom_bar' => 0,
];

$prepareExamplesText = <<<'TXT'
// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template d'édition.

// Adaptez la valeur et le libellé avec du code PHP.
// Les données sont stockées dans le tableau $items.

// Exemple : la valeur du champ "NAME" sera affichée en majuscules, en gras et en rouge.
$items["NAME"]["value"] = strtoupper((string) $items["NAME"]["value"]);
$items["NAME"]["value"] = "<b>" . $items["NAME"]["value"] . "</b>";
$items["NAME"]["value"] = "<span style=\"color:#dc3545\">" . $items["NAME"]["value"] . "</span>";

// Exemple : la valeur du champ "COUNT" sera affichée en rouge si elle est < 0.
$items["COUNT"]["value"] = (is_numeric((string) $items["COUNT"]["value"]) && (float) $items["COUNT"]["value"] < 0)
    ? "<span style=\"color:#dc3545\">" . $items["COUNT"]["value"] . "</span>"
    : $items["COUNT"]["value"];

// Exemple : ajouter la date courante à un champ de libellé.
$items["DATE_LABEL"]["label"] = (string) $items["DATE_LABEL"]["label"] . " (" . date("Y-m-d") . ")";
TXT;
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <h3 id="cb-form-details-display" class="mb-0">
        <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY'); ?>
    </h3>
    <button
        type="button"
        class="btn btn-secondary"
        id="cb-reset-details-display"
        title="<?php echo Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_TOOLTIP'); ?>"
        aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_TOOLTIP'); ?>"
        data-defaults="<?php echo htmlspecialchars(json_encode($detailsDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
        data-confirm="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
        <?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>
    </button>
</div>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_PERMISSION_HINT'); ?>
</div>
<div class="row gx-3 gy-1 mt-0 align-items-stretch mb-3" id="cb-form-details-show-buttons-row">
    <div class="col-12 col-xl-4 d-flex" id="cb-form-details-show-buttons">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1" id="cb-form-details-show-buttons-card">
            <h4 class="h6 text-body-secondary mb-2">
                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BUTTON_OPTIONS'); ?>
            </h4>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <input type="hidden" name="jform[cb_show_details_top_bar]" id="cb-form-details-show-top-bar-hidden" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_details_top_bar]', 'cb_show_details_top_bar', (bool) ($item->cb_show_details_top_bar ?? true)); ?>
                    <label class="form-check-label" for="cb_show_details_top_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DETAIL_TOP_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DETAIL_TOP_BAR'); ?>
                        </span>
                    </label>
                </div>
                <div>
                    <input type="hidden" name="jform[cb_show_details_bottom_bar]" id="cb-form-details-show-bottom-bar-hidden" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_details_bottom_bar]', 'cb_show_details_bottom_bar', (bool) ($item->cb_show_details_bottom_bar ?? false)); ?>
                    <label class="form-check-label" for="cb_show_details_bottom_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DETAIL_BOTTOM_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DETAIL_BOTTOM_BAR'); ?>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4 d-flex" id="cb-form-details-create-sample-card-col">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1" id="cb-form-details-create-sample-card">
            <h4 class="h6 text-body-secondary mb-2">
                <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE'); ?>
            </h4>
            <input type="hidden" name="jform[create_sample]" id="cb_create_sample_flag" value="0" />
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="create_sample"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE') . ' - ' . Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                    aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE') . ' - ' . Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                    onclick="cbQueueDetailsSampleGeneration(this);">
                    <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE'); ?>
                </button>
                <small id="cb_create_sample_hint" class="text-success d-none">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                </small>
            </div>
        </div>
    </div>
</div>
<div id="cb-form-details-template-field-group">
    <?php echo $form ? $form->renderField('details_template') : ''; ?>
</div>
<hr />
<h3 id="cb-form-details-prepare" class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_MODE_TITLE'); ?>
</h3>
<?php
echo LayoutHelper::render(
    'form.prepare_editor',
    [
        'snippetOptions' => $editablePrepareSnippetOptions,
        'effectOptions' => $prepareEffectOptions,
        'selectId' => 'cb_details_prepare_snippet_select',
        'slotName' => 'cb_details_prepare_slot',
        'slotValueId' => 'cb_details_prepare_slot_value',
        'slotLabelId' => 'cb_details_prepare_slot_label',
        'effectSelectId' => 'cb_details_prepare_effect_select',
        'addButtonId' => 'cb_add_details_prepare_snippet',
        'addButtonOnclick' => 'cbInsertDetailsPrepareSnippet();',
        'hintId' => 'cb_details_prepare_snippet_hint',
        'fieldName' => 'jform[details_prepare]',
        'editorId' => 'jform_details_prepare',
        'value' => (string) ($item->details_prepare ?? ''),
        'emptyValue' => Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_EMPTY_VALUE') . "\n",
        'addButtonTextKey' => 'COM_CONTENTBUILDERNG_DETAILS_PREPARE_SNIPPET_ADD',
        'showExamplesModal' => true,
        'examplesText' => $prepareExamplesText,
    ],
    JPATH_COMPONENT_ADMINISTRATOR . '/layouts'
);
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('cb-reset-details-display');

    if (!button) {
        return;
    }

    var defaults = {};

    try {
        defaults = JSON.parse(button.getAttribute('data-defaults') || '{}');
    } catch (error) {
        defaults = {};
    }

    var setCheckbox = function (name, checked) {
        var field = document.querySelector('input[type="checkbox"][name="jform[' + name + ']"]');

        if (!field) {
            return;
        }

        field.checked = checked;
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    button.addEventListener('click', function () {
        var confirmMessage = button.getAttribute('data-confirm') || '';

        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        Object.keys(defaults).forEach(function (name) {
            var value = defaults[name];

            setCheckbox(name, Number(value) === 1);
        });
    });
});
</script>
