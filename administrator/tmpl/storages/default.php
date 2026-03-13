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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

// Charge les scripts Joomla nécessaires (checkAll, submit, etc.)
HTMLHelper::_('behavior.core');
HTMLHelper::_('behavior.multiselect');

// Sécurité: valeurs par défaut
$listOrder = (string) $this->state->get('list.ordering', 'a.ordering');
$listDirn  = strtolower((string) $this->state->get('list.direction', 'asc'));
$listDirn  = ($listDirn === 'desc') ? 'desc' : 'asc';

$saveOrder = ($listOrder === 'a.ordering');

$n = is_countable($this->items) ? count($this->items) : 0;
$limitValue = (int) $this->state->get('list.limit', (int) ($this->pagination->limit ?? 0));
$listStart = (int) $this->state->get('list.start', 0);

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

$filterSearch = (string) $this->state->get('filter.search', '');
$filterStateRaw = strtoupper((string) $this->state->get('filter.state', ''));
$filterState = in_array($filterStateRaw, ['P', '1', 'PUBLISHED'], true)
    ? 'P'
    : (in_array($filterStateRaw, ['U', '0', 'UNPUBLISHED'], true) ? 'U' : '');

$___tableOrdering = "Joomla.tableOrdering = function";
?>
<script type="text/javascript">
<?php echo $___tableOrdering; ?>(order, dir, task) {
var form = document.adminForm;
if (!form) {
    return;
}

    task = task || 'storages.display';

var setValue = function(name, value) {
    var element = form.elements[name];
    if (element) {
        element.value = value;
    }
};

var setStart = function(value) {
    setValue('limitstart', value);
    setValue('list[start]', value);
};

setValue('filter_order', order);
setValue('filter_order_Dir', dir);
setValue('list[ordering]', order);
setValue('list[direction]', dir);
setStart(0);
setValue('task', task);

    form.submit();
};
</script>

<form action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=storages.display'); ?>"
    method="post"
    name="adminForm"
    id="adminForm">

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
                            onclick="document.getElementById('filter_search').value='';document.getElementById('filter_state').value='';document.adminForm.submit();">
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
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th class="w-1 text-nowrap">
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_ID'), 'a.id', $listDirn, $listOrder); ?>
                    </th>

                    <th class="w-1 text-center">
                        <?php echo HTMLHelper::_('grid.checkall'); ?>
                    </th>

                    <th>
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_NAME'), 'a.name', $listDirn, $listOrder); ?>
                    </th>

                    <th>
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'), 'a.title', $listDirn, $listOrder); ?>
                    </th>

                    <th class="text-nowrap">
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_STORAGE_MODE'), 'a.bytable', $listDirn, $listOrder); ?>
                    </th>

                    <th class="w-10 text-nowrap">
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_ORDERBY'), 'a.ordering', $listDirn, $listOrder); ?>
                    </th>

                    <th class="w-10 text-nowrap">
                        <?php echo HTMLHelper::_('grid.sort', Text::_('JGLOBAL_MODIFIED'), 'a.modified', $listDirn, $listOrder); ?>
                    </th>

                    <th class="w-1 text-center">
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_PUBLISHED'), 'a.published', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php if ($n === 0) : ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($this->items as $i => $row) :

                        $id        = (int) ($row->id ?? 0);
                        $name      = htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES, 'UTF-8');
                        $title     = htmlspecialchars((string) ($row->title ?? ''), ENT_QUOTES, 'UTF-8');
                        $storageMode = ((int) ($row->bytable ?? 0) === 1)
                            ? Text::_('COM_CONTENTBUILDERNG_STORAGE_MODE_EXTERNAL')
                            : Text::_('COM_CONTENTBUILDERNG_STORAGE_MODE_INTERNAL');
                        $lastUpdateRaw = $row->modified ?? ($row->created ?? '');
                        $lastUpdate = $lastUpdateRaw
                            ? HTMLHelper::_('date', $lastUpdateRaw, Text::_('DATE_FORMAT_LC5'))
                            : '-';

                        // ⚠️ Vérifie ta convention : task=storage.edit (singulier) ou storages.edit (pluriel)
                        $link = Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . $id);

                        $checked   = HTMLHelper::_('grid.id', $i, $id);
                        $published = ContentbuilderngHelper::listPublish('storages', $row, $i);

                    ?>
                    <tr>
                        <td class="text-nowrap"><?php echo $id; ?></td>
                        <td class="text-center"><?php echo $checked; ?></td>

                        <td><a href="<?php echo $link; ?>"><?php echo $name; ?></a></td>
                        <td><a href="<?php echo $link; ?>"><?php echo $title; ?></a></td>
                        <td class="text-nowrap"><?php echo htmlspecialchars($storageMode, ENT_QUOTES, 'UTF-8'); ?></td>

                        <td class="order text-nowrap">
                            <?php if ($saveOrder) : ?>
                                <span class="me-2">
                                    <?php echo $this->pagination->orderUpIcon($i, $saveOrder, 'storages.orderup', 'JLIB_HTML_MOVE_UP', $saveOrder); ?>
                                </span>
                                <span>
                                    <?php echo $this->pagination->orderDownIcon($i, $n, $saveOrder, 'storages.orderdown', 'JLIB_HTML_MOVE_DOWN', $saveOrder); ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-nowrap">
                            <?php echo htmlspecialchars((string) $lastUpdate, ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td class="text-center">
                            <?php echo $published; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="8">
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

    <input type="hidden" name="option" value="com_contentbuilderng">
    <input type="hidden" name="task" value="storages.display">
    <input type="hidden" name="limitstart" value="<?php echo (int) $listStart; ?>">
    <input type="hidden" name="list[start]" value="<?php echo (int) $listStart; ?>">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>">

    <?php echo HTMLHelper::_('form.token'); ?>
</form>
