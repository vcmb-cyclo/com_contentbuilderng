<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

// Charge les scripts Joomla nécessaires (checkAll, submit, etc.)
HTMLHelper::_('behavior.core');
HTMLHelper::_('behavior.multiselect');

// Sécurité: valeurs par défaut
$order     = $this->lists['order'] ?? 'a.ordering';
$orderDir  = $this->lists['order_Dir'] ?? 'asc';
$orderDir  = strtolower($orderDir) === 'desc' ? 'desc' : 'asc';

// Les flèches d'ordering ne doivent être actives QUE si on est trié sur ordering
$saveOrder = ($order === 'a.ordering');

$n = is_countable($this->items) ? count($this->items) : 0;

// Keep start synced with model state, then fallback to request.
$app = Factory::getApplication();
$list = (array) $app->input->get('list', [], 'array');
$listStart = (int) ($this->pagination->start ?? ($this->lists['list.start'] ?? 0));
if ($listStart < 0) {
    $listStart = 0;
}
if (isset($list['start'])) {
    $listStart = (int) $list['start'];
} elseif ($app->input->get('limitstart', null, 'raw') !== null) {
    $listStart = (int) $app->input->getInt('limitstart', 0);
}
$limitValue = (int) ($this->pagination->limit ?? 0);

$limitOptions = [];
for ($i = 5; $i <= 30; $i += 5) {
    $limitOptions[] = HTMLHelper::_('select.option', (string) $i);
}
$limitOptions[] = HTMLHelper::_('select.option', '50', Text::_('J50'));
$limitOptions[] = HTMLHelper::_('select.option', '100', Text::_('J100'));
$limitOptions[] = HTMLHelper::_('select.option', '0', Text::_('JALL'));

$limitSelect = HTMLHelper::_(
    'select.genericlist',
    $limitOptions,
    'list[limit]',
    'class="form-select js-select-submit-on-change active" id="list_limit" onchange="document.adminForm.submit();"',
    'value',
    'text',
    $limitValue
);

$filterSearch = (string) ($this->lists['filter_search'] ?? '');
$filterStateRaw = strtoupper((string) ($this->lists['filter_state'] ?? ''));
$filterState = in_array($filterStateRaw, ['P', '1', 'PUBLISHED'], true)
    ? 'P'
    : (in_array($filterStateRaw, ['U', '0', 'UNPUBLISHED'], true) ? 'U' : '');
$filterTag = (string) ($this->lists['filter_tag'] ?? '');
$previewLinks = is_array($this->previewLinks ?? null) ? $this->previewLinks : [];

$sortLink = function (string $label, string $field) use ($order, $orderDir, $limitValue, $filterSearch, $filterState, $filterTag): string {
    $isActive = ($order === $field);
    $nextDir = ($isActive && $orderDir === 'asc') ? 'desc' : 'asc';
    $indicator = $isActive
        ? ($orderDir === 'asc'
            ? ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-up" aria-hidden="true"></span>'
            : ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-down" aria-hidden="true"></span>')
        : '';
    $url = Route::_(
        'index.php?option=com_contentbuilderng&view=forms&list[start]=0&list[ordering]='
        . $field . '&list[direction]=' . $nextDir . '&list[limit]=' . $limitValue
        . '&filter_search=' . rawurlencode($filterSearch)
        . '&filter_state=' . rawurlencode($filterState)
        . '&filter_tag=' . rawurlencode($filterTag)
    );

    return '<a href="' . $url . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $indicator . '</a>';
};

?>
<style>
    .cb-forms-preview-link::before,
    .cb-forms-preview-link::after {
        content: none !important;
        display: none !important;
    }
</style>
<form action="index.php"
    method="post"
    name="adminForm"
    id="adminForm">

    <div id="editcell">
        <div class="js-stools mb-3">
            <div class="clearfix">
                <div class="js-stools-container-bar">
                    <div class="btn-toolbar flex-wrap gap-2" role="toolbar">
                        <div class="input-group input-group-sm" style="max-width: 380px;">
                            <input
                                type="text"
                                name="filter_search"
                                id="filter_search"
                                class="form-control"
                                value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>">
                            <button type="submit" class="btn btn-primary">
                                <?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                onclick="document.getElementById('filter_search').value='';document.getElementById('filter_state').value='';document.getElementById('filter_tag').value='';document.adminForm.submit();">
                                <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                            </button>
                        </div>

                        <div class="btn-group">
                            <label for="filter_state" class="visually-hidden"><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></label>
                            <select
                                name="filter_state"
                                id="filter_state"
                                class="form-select form-select-sm js-select-submit-on-change"
                                onchange="var form=document.adminForm;if(form){var start=form.elements['list[start]'];if(start){start.value=0;}var limitStart=form.elements['limitstart'];if(limitStart){limitStart.value=0;}form.submit();}">
                                <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                                <option value="P" <?php echo $filterState === 'P' ? 'selected="selected"' : ''; ?>>
                                    <?php echo Text::_('JPUBLISHED'); ?>
                                </option>
                                <option value="U" <?php echo $filterState === 'U' ? 'selected="selected"' : ''; ?>>
                                    <?php echo Text::_('JUNPUBLISHED'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="btn-group">
                            <label for="filter_tag" class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_TAG'); ?></label>
                            <select
                                class="form-select form-select-sm js-select-submit-on-change"
                                id="filter_tag"
                                name="filter_tag"
                                onchange="var form=document.adminForm;if(form){var start=form.elements['list[start]'];if(start){start.value=0;}var limitStart=form.elements['limitstart'];if(limitStart){limitStart.value=0;}form.submit();}">
                                <option value="">
                                    <?php echo htmlentities(Text::_('COM_CONTENTBUILDERNG_FILTER_TAG_ALL'), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php foreach ($this->tags as $tag) : ?>
                                    <option
                                        value="<?php echo htmlentities($tag->tag, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo strtolower($filterTag) === strtolower((string) $tag->tag) ? 'selected="selected"' : ''; ?>>
                                        <?php echo htmlentities($tag->tag, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th width="5">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_ID'), 'a.id'); ?>
                    </th>
                    <th width="20">
                        <?php echo HTMLHelper::_('grid.checkall'); ?>
                    </th>
                    <th width="60" class="text-center">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_VIEW_NAME'), 'a.name'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_TAG'), 'a.tag'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_FORM_SOURCE'), 'a.title'); ?>
                    </th>
                    <th width="90" class="text-center">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_TYPE'), 'a.type'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_DISPLAY'), 'a.display_in'); ?>
                    </th>


                    <th class="w-10 text-nowrap">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_ORDERBY'), 'a.ordering'); ?>
                    </th>
                    <th class="text-nowrap">
                        <?php echo $sortLink(Text::_('JGLOBAL_MODIFIED'), 'a.modified'); ?>
                    </th>
                    <th class="w-1 text-center">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_PUBLISHED'), 'a.published'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $k = 0;
                $n = count($this->items);
                for ($i = 0; $i < $n; $i++) {
                    $row = $this->items[$i];
                    $checked = HTMLHelper::_('grid.id', $i, $row->id);
                    $link = Route::_('index.php?option=com_contentbuilderng&task=form.edit&id=' . $row->id);
                    $published = ContentbuilderngHelper::listPublish('forms', $row, $i);
                ?>
                    <tr class="<?php echo "row$k"; ?>">
                        <td>
                            <?php echo $row->id; ?>
                        </td>
                        <td>
                            <?php echo $checked; ?>
                        </td>
                        <td class="text-center">
                            <?php $previewUrl = (string) ($previewLinks[(int) $row->id] ?? ''); ?>
                            <?php if ($previewUrl !== '') : ?>
                                <a
                                    class="btn btn-sm btn-link p-0 cb-forms-preview-link"
                                    href="<?php echo htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW'); ?>"
                                >
                                    <span class="fa-solid fa-eye" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW'); ?></span>
                                </a>
                            <?php else : ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo $row->name; ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo $row->tag; ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php
                                $sourceTitle = (string) ($row->source_title ?? $row->title ?? '');
                                echo htmlspecialchars($sourceTitle, ENT_QUOTES, 'UTF-8');
                                ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $link; ?>">
                                <?php
                                $typeCode = (string) ($row->type ?? '');
                                $typeShortMap = [
                                    'com_breezingforms'  => 'BF',
                                    'com_contentbuilderng' => 'CB',
                                    'com_contentbuilderng'  => 'CB',
                                ];
                                $typeShort = $typeShortMap[$typeCode] ?? $typeCode;
                                echo htmlspecialchars($typeShort, ENT_QUOTES, 'UTF-8');
                                ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo $row->display_in == 0 ? Text::_('COM_CONTENTBUILDERNG_DISPLAY_FRONTEND') : ($row->display_in == 1 ? Text::_('COM_CONTENTBUILDERNG_DISPLAY_BACKEND') : Text::_('COM_CONTENTBUILDERNG_DISPLAY_BOTH')); ?>
                            </a>
                        </td>
                        <td class="order,text-nowrap">
                            <span>
                                <?php echo $this->pagination->orderUpIcon($i, $saveOrder, 'forms.orderup', Text::_('JLIB_HTML_MOVE_UP'), $this->ordering);
                                ?>
                            </span>
                            <span>
                                <?php echo 
                                $this->pagination->orderDownIcon($i, $n, $saveOrder, 'forms.orderdown', Text::_('JLIB_HTML_MOVE_DOWN'), $this->ordering);
                                ?>
                            </span>
                            <?php $disabled = $this->ordering ? '' : 'disabled="disabled"'; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php
                            $m = $row->modified ?? '';
                            if ($m && $m !== '0000-00-00 00:00:00') {
                                echo HTMLHelper::_('date', $m, Text::_('DATE_FORMAT_LC5'));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $published; ?>
                        </td>


                    </tr>
                <?php
                    $k = 1 - $k;
                }
                ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="11">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php echo $this->pagination->getPagesCounter(); ?>
                    <span><?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM'); ?></span>
                    <span class="d-inline-block"><?php echo $limitSelect; ?></span>
                    <span><?php echo Text::_('COM_CONTENTBUILDERNG_OF'); ?></span>
                    <span><?php echo (int) ($this->pagination->total ?? 0); ?></span>
                    </div>

                    <div>
                    <?php echo $this->pagination->getPagesLinks(); ?>
                    </div>

                </div>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="view" value="forms" />
    <input type="hidden" name="limitstart" value="<?php echo (int) $listStart; ?>" />
    <input type="hidden" name="list[start]" value="<?php echo (int) $listStart; ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($order, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($orderDir, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
