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
$canEditByType = !empty($displayData['canEditByType']);
$isBreezingFormsType = !empty($displayData['isBreezingFormsType']);
$breezingFormsProvidedMessage = (string) ($displayData['breezingFormsProvidedMessage'] ?? '');
$breezingFormsEditableToken = (string) ($displayData['breezingFormsEditableToken'] ?? '');
$editablePrepareSnippetOptions = is_array($displayData['editablePrepareSnippetOptions'] ?? null) ? $displayData['editablePrepareSnippetOptions'] : [];
$prepareEffectOptions = is_array($displayData['prepareEffectOptions'] ?? null) ? $displayData['prepareEffectOptions'] : [];
?>
<h3 class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY'); ?>
</h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY_PERMISSION_HINT'); ?>
</div>
<div class="row gx-3 gy-1 mt-0 align-items-stretch mb-3">
    <div class="col-12 col-xl-4 d-flex" id="cbEditScreenPanels">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-2">
                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BUTTON_OPTIONS'); ?>
            </h4>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <input type="hidden" name="jform[cb_show_top_bar]" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_top_bar]', 'cb_show_top_bar', (bool) ($item->cb_show_top_bar ?? true)); ?>
                    <label class="form-check-label" for="cb_show_top_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_TOP_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_TOP_BAR'); ?>
                        </span>
                    </label>
                </div>
                <div>
                    <input type="hidden" name="jform[cb_show_bottom_bar]" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_bottom_bar]', 'cb_show_bottom_bar', (bool) ($item->cb_show_bottom_bar ?? true)); ?>
                    <label class="form-check-label" for="cb_show_bottom_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BOTTOM_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BOTTOM_BAR'); ?>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" name="jform[edit_by_type]" value="0" />
<?php if ($canEditByType) : ?>
    <div class="form-check mb-3">
        <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[edit_by_type]', 'edit_by_type', (bool) ($item->edit_by_type ?? false)) : ''; ?>
        <label class="form-check-label" for="edit_by_type">
            <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT_TIP'); ?>">
                <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT'); ?>
            </span>
        </label>
    </div>
<?php endif; ?>
<?php if (!empty($item->edit_by_type) && $isBreezingFormsType) : ?>
    <?php echo $breezingFormsProvidedMessage; ?>
    <input type="hidden" name="jform[editable_template]" value="<?php echo htmlspecialchars($breezingFormsEditableToken, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="jform[upload_directory]" value="<?php echo htmlspecialchars(trim((string) ($item->upload_directory ?? '')) ?: JPATH_SITE . '/media/com_contentbuilderng/upload', ENT_QUOTES, 'UTF-8'); ?>" />
<?php else : ?>
    <input type="hidden" name="jform[protect_upload_directory]" value="0" />
    <div class="cb-upload-box">
        <div class="row g-3 align-items-end">
            <div class="col-lg-8">
                <label for="upload_directory" class="form-label mb-2"><span class="editlinktip hasTip"
                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_UPLOAD_DIRECTORY_TIP'); ?>">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_UPLOAD_DIRECTORY'); ?>
                    </span></label>
                <input class="form-control form-control-sm" type="text"
                    value="<?php echo htmlspecialchars(trim((string) ($item->upload_directory ?? '')) ?: JPATH_SITE . '/media/com_contentbuilderng/upload', ENT_QUOTES, 'UTF-8'); ?>"
                    name="jform[upload_directory]" id="upload_directory" />
            </div>
            <div class="col-lg-auto">
                <div class="form-check mb-1">
                    <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[protect_upload_directory]', 'protect_upload_directory', trim((string) ($item->protect_upload_directory ?? '')) !== '') : ''; ?>
                    <label class="form-check-label" for="protect_upload_directory">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PROTECT_UPLOAD_DIRECTORY'); ?>
                    </label>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" name="jform[create_editable_sample]" id="cb_create_editable_sample_flag" value="0" />
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="create_editable_sample"
            title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
            aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
            onclick="cbQueueEditableSampleGeneration(this);">
            <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE'); ?>
        </button>
        <small id="cb_create_editable_sample_hint" class="text-success d-none">
            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
        </small>
    </div>
    <br />
    <br />
    <?php echo $form ? $form->renderField('editable_template') : ''; ?>
<?php endif; ?>
<hr />
<h3 class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_MODE_TITLE'); ?>
</h3>
<?php if (!empty($item->edit_by_type)) : ?>
    <?php echo $breezingFormsProvidedMessage; ?>
    <input type="hidden" name="jform[editable_prepare]" value="<?php echo htmlentities($item->editable_prepare ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
<?php else : ?>
    <?php
    echo LayoutHelper::render(
        'form.prepare_editor',
        [
            'snippetOptions' => $editablePrepareSnippetOptions,
            'effectOptions' => $prepareEffectOptions,
            'selectId' => 'cb_editable_prepare_snippet_select',
            'slotName' => 'cb_editable_prepare_slot',
            'slotValueId' => 'cb_editable_prepare_slot_value',
            'slotLabelId' => 'cb_editable_prepare_slot_label',
            'effectSelectId' => 'cb_editable_prepare_effect_select',
            'addButtonId' => 'cb_add_editable_prepare_snippet',
            'addButtonOnclick' => 'cbInsertEditablePrepareSnippet();',
            'hintId' => 'cb_editable_prepare_snippet_hint',
            'fieldName' => 'jform[editable_prepare]',
            'editorId' => 'jform_editable_prepare',
            'value' => (string) ($item->editable_prepare ?? ''),
            'emptyValue' => '// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template d\'édition.' . "\n",
            'addButtonTextKey' => 'COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_ADD',
            'showExamplesModal' => false,
        ],
        JPATH_COMPONENT_ADMINISTRATOR . '/layouts'
    );
    ?>
<?php endif; ?>
