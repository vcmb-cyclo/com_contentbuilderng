<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$item = $displayData['item'] ?? null;
$form = $displayData['form'] ?? null;
$allElements = is_array($displayData['allElements'] ?? null) ? $displayData['allElements'] : [];
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$editablePrepareSnippetOptions = is_array($displayData['editablePrepareSnippetOptions'] ?? null) ? $displayData['editablePrepareSnippetOptions'] : [];
$prepareEffectOptions = is_array($displayData['prepareEffectOptions'] ?? null) ? $displayData['prepareEffectOptions'] : [];
$isBreezingFormsType = !empty($displayData['isBreezingFormsType']);

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
<h3 id="cb-form-details-display" class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY'); ?>
</h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_PERMISSION_HINT'); ?>
</div>
<div class="row gx-3 gy-1 mt-0 align-items-stretch mb-3">
    <div class="col-12 col-xl-4 d-flex" id="cb-form-details-show-buttons">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-2">
                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BUTTON_OPTIONS'); ?>
            </h4>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <input type="hidden" name="jform[cb_show_details_top_bar]" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_details_top_bar]', 'cb_show_details_top_bar', (bool) ($item->cb_show_details_top_bar ?? true)); ?>
                    <label class="form-check-label" for="cb_show_details_top_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_TOP_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_TOP_BAR'); ?>
                        </span>
                    </label>
                </div>
                <div>
                    <input type="hidden" name="jform[cb_show_details_bottom_bar]" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_details_bottom_bar]', 'cb_show_details_bottom_bar', (bool) ($item->cb_show_details_bottom_bar ?? false)); ?>
                    <label class="form-check-label" for="cb_show_details_bottom_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_BOTTOM_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_BOTTOM_BAR'); ?>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
<table id="cb-form-details-settings" width="100%" class="table table-striped">
    <tr>
        <td width="20%">
            <label for="create_sample"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE'); ?><span></label>
        </td>
        <td>
            <input type="hidden" name="jform[create_sample]" id="cb_create_sample_flag" value="0" />
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="create_sample"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                    aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                    onclick="cbQueueDetailsSampleGeneration(this);">
                    <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE'); ?>
                </button>
                <small id="cb_create_sample_hint" class="text-success d-none">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                </small>
            </div>
        </td>
        <td width="20%">
            <div class="mb-2">
                <label for="create_articles_yes"><span class="editlinktip hasTip"
                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TIP'); ?>">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_ARTICLES_LABEL'); ?>
                    </span></label>
            </div>
            <div class="mb-1">
                <label for="delete_articles"><span class="editlinktip hasTip"
                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES_TIP'); ?>">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES'); ?>
                    </span></label>
            </div>
        </td>
        <td>
            <div class="mb-2">
                <input class="form-check-input" type="radio" value="1" name="jform[create_articles]" id="create_articles_yes"
                    <?php echo (int) ($item->create_articles ?? 0) === 1 ? ' checked="checked"' : ''; ?> />
                <label for="create_articles_yes">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                </label>
                <input class="form-check-input" type="radio" value="0" name="jform[create_articles]" id="create_articles_no"
                    <?php echo (int) ($item->create_articles ?? 0) !== 1 ? ' checked="checked"' : ''; ?> />
                <label for="create_articles_no">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                </label>
            </div>
            <input class="form-check-input" type="radio" value="1" name="jform[delete_articles]" id="delete_articles"
                <?php echo !empty($item->delete_articles) ? ' checked="checked"' : '' ?> /> <label
                for="delete_articles">
                <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
            </label>
            <input class="form-check-input" type="radio" value="0" name="jform[delete_articles]"
                id="delete_articles_no" <?php echo empty($item->delete_articles) ? ' checked="checked"' : '' ?> /> <label for="delete_articles_no">
                <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <td width="20%">
            <label for="title_field"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_TITLE_FIELD_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_TITLE_FIELD'); ?>
                </span></label>
        </td>
        <td>
            <select class="form-select-sm" name="jform[title_field]" id="title_field">
                <option value="0">
                    - <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                </option>
                <?php foreach ($allElements as $sortable) : ?>
                    <option value="<?php echo $sortable->reference_id; ?>" <?php echo ($item->title_field ?? null) == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                        <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td width="20%">
            <label for="default_category"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY'); ?>
                </span></label>
        </td>
        <td>
            <select class="form-select-sm" id="default_category" name="jform[sectioncategories]">
                <?php foreach ((array) ($item->sectioncategories ?? []) as $category) : ?>
                    <option <?php echo ($item->default_category ?? null) == $category->value ? ' selected="selected"' : '' ?>value="<?php echo $category->value; ?>">
                        <?php echo htmlentities($category->text ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <td width="20%" class="align-top">
            <label for="default_lang_code"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE'); ?>
                </span></label>
        </td>
        <td class="align-top">
            <select class="form-select-sm" name="jform[default_lang_code]" id="default_lang_code">
                <option value="*">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ANY'); ?>
                </option>
                <?php foreach ((array) ($item->language_codes ?? []) as $langCode) : ?>
                    <option value="<?php echo $langCode ?>" <?php echo $langCode == ($item->default_lang_code ?? null) ? ' selected="selected"' : ''; ?>>
                        <?php echo $langCode; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br /><br />
            <label for="article_record_impact_language_yes"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT'); ?>
                </span></label>
            <input class="form-check-input" <?php echo !empty($item->article_record_impact_language) ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_language]"
                id="article_record_impact_language_yes" value="1" />
            <label for="article_record_impact_language_yes">
                <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
            </label>
            <input class="form-check-input" <?php echo empty($item->article_record_impact_language) ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_language]"
                id="article_record_impact_language_no" value="0" />
            <label for="article_record_impact_language_no">
                <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
            </label>
        </td>
        <td width="20%" class="align-top">
            <label for="default_lang_code_ignore_yes"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE'); ?>
                </span></label>
        </td>
        <td class="align-top">
            <input class="form-check-input" <?php echo !empty($item->default_lang_code_ignore) ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_lang_code_ignore]"
                id="default_lang_code_ignore_yes" value="1" />
            <label for="default_lang_code_ignore_yes">
                <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
            </label>

            <input class="form-check-input" <?php echo empty($item->default_lang_code_ignore) ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_lang_code_ignore]"
                id="default_lang_code_ignore_no" value="0" />
            <label for="default_lang_code_ignore_no">
                <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <td width="20%" class="align-top">
            <label for="default_publish_up_days"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_UP_DAYS_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_UP_DAYS'); ?>
                </span></label>
        </td>
        <td class="align-top">
            <input class="form-control form-control-sm w-100" type="text" name="jform[default_publish_up_days]"
                id="default_publish_up_days" value="<?php echo $item->default_publish_up_days; ?>" />
            <br /><br />
            <label for="article_record_impact_publish_yes"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT'); ?>
                </span></label>
            <input class="form-check-input" <?php echo !empty($item->article_record_impact_publish) ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_publish]"
                id="article_record_impact_publish_yes" value="1" />
            <label for="article_record_impact_publish_yes">
                <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
            </label>
            <input class="form-check-input" <?php echo empty($item->article_record_impact_publish) ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_publish]"
                id="article_record_impact_publish_no" value="0" />
            <label for="article_record_impact_publish_no">
                <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
            </label>

        </td>
        <td width="20%" class="align-top">
            <label for="default_publish_down_days"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_DOWN_DAYS_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_DOWN_DAYS'); ?>
                </span></label>
        </td>
        <td class="align-top">
            <input class="form-control form-control-sm w-100" type="text" name="jform[default_publish_down_days]"
                id="default_publish_down_days" value="<?php echo $item->default_publish_down_days; ?>" />
        </td>

    </tr>
    <tr>
        <td width="20%">
            <label for="default_access"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_ACCESS_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_ACCESS'); ?>
                </span></label>
        </td>
        <td>
            <?php echo HTMLHelper::_('access.level', 'default_access', $item->default_access, '', [], 'default_access'); ?>
        </td>
        <td width="20%">
            <label for="default_featured"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED'); ?>
                </span></label>
        </td>
        <td>
            <input class="form-check-input" <?php echo !empty($item->default_featured) ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_featured]" id="default_featured"
                value="1" />
            <label for="default_featured">
                <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
            </label>

            <input class="form-check-input" <?php echo empty($item->default_featured) ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_featured]" id="default_featured_no"
                value="0" />
            <label for="default_featured_no">
                <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
            </label>

        </td>
    </tr>
    <tr>
        <td width="20%">
            <label for="auto_publish"><span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH'); ?>
                </span></label>
        </td>
        <td>
            <input type="hidden" name="jform[auto_publish]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[auto_publish]', 'auto_publish', (int) $item->auto_publish === 1) : ''; ?>
        </td>
        <td width="20%">
            <?php if (!empty($item->edit_by_type) && $isBreezingFormsType) : ?>
                <label for="protect_upload_directory"><span class="editlinktip hasTip"
                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_UPLOAD_DIRECTORY_TYPE_TIP'); ?>">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PROTECT_UPLOAD_DIRECTORY'); ?>
                    </span></label>
            <?php endif; ?>
        </td>
        <td>
            <?php if (!empty($item->edit_by_type) && $isBreezingFormsType) : ?>
                <input type="hidden" name="jform[protect_upload_directory]" value="0" />
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[protect_upload_directory]', 'protect_upload_directory', trim((string) $item->protect_upload_directory) !== '') : ''; ?>
            <?php endif; ?>
        </td>
    </tr>
</table>

<?php echo $form ? $form->renderField('details_template') : ''; ?>
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
        'emptyValue' => '// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template détail.' . "\n",
        'addButtonTextKey' => 'COM_CONTENTBUILDERNG_DETAILS_PREPARE_SNIPPET_ADD',
        'showExamplesModal' => true,
        'examplesText' => $prepareExamplesText,
    ],
    JPATH_COMPONENT_ADMINISTRATOR . '/layouts'
);
?>
