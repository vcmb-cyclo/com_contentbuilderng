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

$item = $displayData['item'] ?? null;
$elements = is_array($displayData['elements'] ?? null) ? $displayData['elements'] : [];
$renderCheckbox = $displayData['renderCheckbox'] ?? null;

if (!is_callable($renderCheckbox)) {
    $renderCheckbox = static fn (): string => '';
}
?>
<div class="bg-body-tertiary p-3" id="advancedOptions">

    <fieldset>
        <legend>
            <h3 class="editlinktip hasTip"
                title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_COLUMNS_TIP'); ?>">
                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW'); ?>
            </h3>
        </legend>


        <div class="row gx-3 gy-1 mt-0 align-items-stretch">
            <div class="col-12 col-xl-3 d-flex">
                <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
                    <h4 class="h6 text-body-secondary mb-2">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DATA_OPTIONS'); ?>
                    </h4>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <input type="hidden" name="jform[show_id_column]" value="0" />
                            <?php echo $renderCheckbox('jform[show_id_column]', 'show_id_column', (bool) ($item->show_id_column ?? false)); ?>
                            <label class="form-check-label" for="show_id_column">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_ID_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ID_COLUMN'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[select_column]" value="0" />
                            <?php echo $renderCheckbox('jform[select_column]', 'select_column', (bool) ($item->select_column ?? false)); ?>
                            <label class="form-check-label" for="select_column">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_SELECT_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_SELECT_COLUMN'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_state]" value="0" />
                            <?php echo $renderCheckbox('jform[list_state]', 'list_state', (bool) ($item->list_state ?? false)); ?>
                            <label class="form-check-label" for="list_state">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_STATE_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_publish]" value="0" />
                            <?php echo $renderCheckbox('jform[list_publish]', 'list_publish', (bool) ($item->list_publish ?? false)); ?>
                            <label class="form-check-label" for="list_publish">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PUBLISH_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_language]" value="0" />
                            <?php echo $renderCheckbox('jform[list_language]', 'list_language', (bool) ($item->list_language ?? false)); ?>
                            <label class="form-check-label" for="list_language">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_LANGUAGE_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_article]" value="0" />
                            <?php echo $renderCheckbox('jform[list_article]', 'list_article', (bool) ($item->list_article ?? false)); ?>
                            <label class="form-check-label" for="list_article">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_ARTICLE_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_author]" value="0" />
                            <?php echo $renderCheckbox('jform[list_author]', 'list_author', (bool) ($item->list_author ?? false)); ?>
                            <label class="form-check-label" for="list_author">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_AUTHOR_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_last_modification]" value="0" />
                            <?php echo $renderCheckbox('jform[list_last_modification]', 'list_last_modification', (bool) ($item->list_last_modification ?? false)); ?>
                            <label class="form-check-label" for="list_last_modification">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_LAST_MODIFICATION_COLUMN_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_LAST_MODIFICATION'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[metadata]" value="0" />
                            <?php echo $renderCheckbox('jform[metadata]', 'metadata', (bool) ($item->metadata ?? false)); ?>
                            <label class="form-check-label" for="metadata">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_METADATA_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_METADATA'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-3 d-flex">
                <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
                    <h4 class="h6 text-body-secondary mb-2">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BUTTON_OPTIONS'); ?>
                    </h4>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <input type="hidden" name="jform[edit_button]" value="0" />
                            <?php echo $renderCheckbox('jform[edit_button]', 'edit_button', (bool) ($item->edit_button ?? false)); ?>
                            <label class="form-check-label" for="edit_button">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_EDIT_BUTTON_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[new_button]" value="0" />
                            <?php echo $renderCheckbox('jform[new_button]', 'new_button', (bool) ($item->new_button ?? false)); ?>
                            <label class="form-check-label" for="new_button">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_NEW_BUTTON_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_NEW'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[export_xls]" value="0" />
                            <?php echo $renderCheckbox('jform[export_xls]', 'export_xls', (bool) ($item->export_xls ?? false)); ?>
                            <label class="form-check-label" for="export_xls">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_XLSEXPORT_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_XLSEXPORT'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[print_button]" value="0" />
                            <?php echo $renderCheckbox('jform[print_button]', 'print_button', (bool) ($item->print_button ?? false)); ?>
                            <label class="form-check-label" for="print_button">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PRINTBUTTON_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PRINTBUTTON'); ?>
                                </span>
                            </label>
                        </div>
                        <div class="w-100"></div>
                        <div>
                            <input type="hidden" name="jform[button_bar_sticky]" value="0" />
                            <?php echo $renderCheckbox('jform[button_bar_sticky]', 'button_bar_sticky', (bool) ($item->button_bar_sticky ?? false)); ?>
                            <label class="form-check-label" for="button_bar_sticky">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_BUTTON_BAR_STICKY_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_BUTTON_BAR_STICKY'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-3 d-flex">
                <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
                    <h4 class="h6 text-body-secondary mb-2">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_OPTIONS'); ?>
                    </h4>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <input type="hidden" name="jform[use_view_name_as_title]" value="0" />
                            <?php echo $renderCheckbox('jform[use_view_name_as_title]', 'use_view_name_as_title', (bool) ($item->use_view_name_as_title ?? false)); ?>
                            <label class="form-check-label" for="use_view_name_as_title">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_USE_VIEW_NAME_AS_TITLE_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_USE_VIEW_NAME_AS_TITLE'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[list_header_sticky]" value="0" />
                            <?php echo $renderCheckbox('jform[list_header_sticky]', 'list_header_sticky', (bool) ($item->list_header_sticky ?? false)); ?>
                            <label class="form-check-label" for="list_header_sticky">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_HEADER_STICKY_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_HEADER_STICKY'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[show_preview_link]" value="0" />
                            <?php echo $renderCheckbox('jform[show_preview_link]', 'show_preview_link', (bool) ($item->show_preview_link ?? false)); ?>
                            <label class="form-check-label" for="show_preview_link">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PREVIEW_LINK_TIP'); ?>">
                                    <span class="fa-solid fa-eye" aria-hidden="true"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-3 d-flex">
                <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
                    <h4 class="h6 text-body-secondary mb-2">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_FILTERS'); ?>
                    </h4>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <input type="hidden" name="jform[show_filter]" value="0" />
                            <?php echo $renderCheckbox('jform[show_filter]', 'show_filter', (bool) ($item->show_filter ?? false)); ?>
                            <label class="form-check-label" for="show_filter">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_FILTER_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[show_records_per_page]" value="0" />
                            <?php echo $renderCheckbox('jform[show_records_per_page]', 'show_records_per_page', (bool) ($item->show_records_per_page ?? false)); ?>
                            <label class="form-check-label" for="show_records_per_page">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_RECORDS_PER_PAGE_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_LIMIT_LABEL'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[published_only]" value="0" />
                            <?php echo $renderCheckbox('jform[published_only]', 'published_only', (bool) ($item->published_only ?? false)); ?>
                            <label class="form-check-label" for="published_only">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISHED_ONLY_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISHED_ONLY'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[allow_external_filter]" value="0" />
                            <?php echo $renderCheckbox('jform[allow_external_filter]', 'allow_external_filter', (bool) ($item->allow_external_filter ?? false)); ?>
                            <label class="form-check-label" for="allow_external_filter">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_ALLOW_EXTERNAL_FILTER_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ALLOW_EXTERNAL_FILTER'); ?>
                                </span>
                            </label>
                        </div>
                        <div>
                            <input type="hidden" name="jform[filter_exact_match]" value="0" />
                            <?php echo $renderCheckbox('jform[filter_exact_match]', 'filter_exact_match', (bool) ($item->filter_exact_match ?? false)); ?>
                            <label class="form-check-label" for="filter_exact_match">
                                <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_EXACT_MATCH_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_EXACT_MATCH'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </fieldset>

    <hr />

    <div class="row g-3 align-items-stretch">
        <div class="col-12 col-xl-8 d-flex">
            <fieldset class="d-flex flex-column flex-grow-1">
                <legend>
                    <h3>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_SORTING'); ?>
                    </h3>
                </legend>
                <div class="alert flex-grow-1 mb-0">
                    <label for="initial_sort_order">
                        <span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_TIP'); ?>"><b>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER'); ?>:
                            </b></span>
                    </label>
                    <select class="form-select-sm" name="jform[initial_sort_order]" id="initial_sort_order"
                        style="max-width: 200px;">
                        <option value="">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_BY_ID'); ?>
                        </option>
                        <?php foreach ($elements as $sortable) : ?>
                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo ($item->initial_sort_order ?? null) == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select-sm" name="jform[initial_sort_order2]" id="initial_sort_order2"
                        style="max-width: 200px;">
                        <option value="-1">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?>
                        </option>
                        <option value="0">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_BY_ID'); ?>
                        </option>
                        <?php foreach ($elements as $sortable) : ?>
                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo ($item->initial_sort_order2 ?? null) == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select-sm" name="jform[initial_sort_order3]" id="initial_sort_order3"
                        style="max-width: 200px;">
                        <option value="-1">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?>
                        </option>
                        <option value="0">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_BY_ID'); ?>
                        </option>
                        <?php foreach ($elements as $sortable) : ?>
                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo ($item->initial_sort_order3 ?? null) == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div></div>
                    <input class="form-check-input" type="radio" name="jform[initial_order_dir]"
                        id="initial_order_dir" value="asc" <?php echo ($item->initial_order_dir ?? '') === 'asc' ? ' checked="checked"' : ''; ?> />
                    <label for="initial_order_dir">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_ASC'); ?>
                    </label>
                    <input class="form-check-input" type="radio" name="jform[initial_order_dir]"
                        id="initial_order_dir_desc" value="desc" <?php echo ($item->initial_order_dir ?? '') === 'desc' ? ' checked="checked"' : ''; ?> />
                    <label for="initial_order_dir_desc">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_DESC'); ?>
                    </label>
                </div>
            </fieldset>
        </div>
        <div class="col-12 col-xl-4 d-flex">
            <fieldset class="d-flex flex-column flex-grow-1">
                <legend>
                    <h3>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_RATING'); ?>
                    </h3>
                </legend>
                <div class="alert flex-grow-1 mb-0">
                    <input type="hidden" name="jform[list_rating]" value="0" />
                    <?php echo $renderCheckbox('jform[list_rating]', 'list_rating', (bool) ($item->list_rating ?? false)); ?>
                    <label class="form-check-label" for="list_rating">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_RATING'); ?>
                    </label>

                    <select class="form-select-sm" name="jform[rating_slots]" id="rating_slots">
                        <option value="1" <?php echo (int) ($item->rating_slots ?? 0) === 1 ? ' selected="selected"' : ''; ?>>1</option>
                        <option value="2" <?php echo (int) ($item->rating_slots ?? 0) === 2 ? ' selected="selected"' : ''; ?>>2</option>
                        <option value="3" <?php echo (int) ($item->rating_slots ?? 0) === 3 ? ' selected="selected"' : ''; ?>>3</option>
                        <option value="4" <?php echo (int) ($item->rating_slots ?? 0) === 4 ? ' selected="selected"' : ''; ?>>4</option>
                        <option value="5" <?php echo (int) ($item->rating_slots ?? 0) === 5 ? ' selected="selected"' : ''; ?>>5</option>
                    </select>
                    <label for="rating_slots">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_RATING_SLOTS'); ?>
                    </label>
                </div>
            </fieldset>
        </div>
    </div>

    <hr />

    <fieldset>
        <legend>
            <h3>
                <?php echo Text::_('COM_CONTENTBUILDERNG_BUTTONS'); ?>
            </h3>
        </legend>
        <div class="alert">
            <label for="save_button_title">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_SAVE_BUTTON_TITLE_TIP'); ?>"><b>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_SAVE_BUTTON_TITLE'); ?>:
                    </b></span>
            </label>
            <input class="form-control form-control-sm" type="text" id="save_button_title"
                name="jform[save_button_title]"
                value="<?php echo htmlentities($item->save_button_title ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

            <label for="apply_button_title">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_APPLY_BUTTON_TITLE_TIP'); ?>"><b>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_APPLY_BUTTON_TITLE'); ?>:
                    </b></span>
            </label>
            <input class="form-control form-control-sm" type="text" id="apply_button_title"
                name="jform[apply_button_title]"
                value="<?php echo htmlentities($item->apply_button_title ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
    </fieldset>

</div>
