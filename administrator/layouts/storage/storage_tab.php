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
use Joomla\CMS\Session\Session;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

$item = $displayData['item'] ?? null;
$tables = is_array($displayData['tables'] ?? null) ? $displayData['tables'] : [];
$storageId = (int) ($displayData['storageId'] ?? 0);
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$csvToggleTooltip = (string) ($displayData['csvToggleTooltip'] ?? '');
$addFieldTooltip = (string) ($displayData['addFieldTooltip'] ?? '');
$sortLink = $displayData['sortLink'] ?? null;
$fields = is_iterable($displayData['fields'] ?? null) ? $displayData['fields'] : [];
$fieldsCount = (int) ($displayData['fieldsCount'] ?? 0);
$pagination = $displayData['pagination'] ?? null;
$ordering = !empty($displayData['ordering']);
?>
<table width="100%">
    <tr>
        <td width="200" valign="top">
            <fieldset class="border rounded p-3 mb-3">
                <table width="100%">
                    <tr>
                        <td style="min-width: 150px;">
                            <label for="name">
                                <b>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>
                                </b>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php if (!$item->bytable) : ?>
                                <input class="form-control form-control-sm w-100" type="text" id="name" name="jform[name]"
                                    value="<?php echo htmlentities($item->name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <br /><br />
                            <?php else : ?>
                                <input type="hidden" id="name" name="jform[name]"
                                    value="<?php echo htmlentities($item->name, ENT_QUOTES, 'UTF-8'); ?>" />
                            <?php endif; ?>

                            <?php if (!$item->id) : ?>
                                <b>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_CHOOSE_TABLE'); ?>
                                </b>
                                <br />
                                <select class="form-select-sm"
                                    onchange="if(this.selectedIndex != 0){ document.getElementById('name').disabled = true; document.getElementById('csvUploadHead').style.display = 'none'; document.getElementById('csvUpload').style.display = 'none'; alert('<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_CUSTOM_STORAGE_MSG')); ?>'); }else{ document.getElementById('name').disabled = false; document.getElementById('csvUploadHead').style.display = ''; document.getElementById('csvUpload').style.display = ''; }"
                                    name="jform[bytable]" id="bytable" style="max-width: 150px;">
                                    <option value=""> -
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                                    </option>
                                    <?php foreach ($tables as $table) : ?>
                                        <option value="<?php echo htmlentities($table, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlentities($table, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($item->bytable) : ?>
                                <input type="hidden" id="bytable" name="jform[bytable]"
                                    value="<?php echo htmlentities($item->name, ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php echo htmlentities($item->name, ENT_QUOTES, 'UTF-8'); ?>
                            <?php else : ?>
                                <input type="hidden" id="bytable" name="jform[bytable]" value="" />
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="100">
                            <label for="title">
                                <b>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?>
                                </b>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input class="form-control form-control-sm w-100" type="text" id="title"
                                name="jform[title]"
                                value="<?php echo htmlentities($item->title ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                    </tr>
                    <tr id="csvUploadHead">
                        <td width="100">
                            <br />
                            <button
                                type="button"
                                id="csvToggleButton"
                                class="btn btn-primary mb-2"
                                onclick="return toggleCsvUploadOptions();"
                                title="<?php echo htmlspecialchars($csvToggleTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                aria-controls="csvUpload"
                                aria-expanded="false"
                                data-cb-default-text="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-cb-create-text="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_CREATE_FROM_FILE'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-cb-new-storage="<?php echo ((int) $storageId === 0) ? '1' : '0'; ?>"
                                data-cb-preview-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_PREVIEW_FROM_FILE'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-cb-token="<?php echo Session::getFormToken(); ?>"
                            >
                                <i class="fa fa-file-excel me-1" aria-hidden="true"></i>
                                <span class="cb-csv-button-label">
                                    <?php echo ((int) $storageId === 0)
                                        ? Text::_('COM_CONTENTBUILDERNG_STORAGE_CREATE_FROM_FILE')
                                        : Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV'); ?>
                                </span>
                            </button>
                        </td>
                    </tr>
                    <tr style="display: none;" id="csvUpload">
                        <td>
                            <input size="9" type="file" id="csv_file" name="csv_file" accept=".csv,.xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                            <br />
                            Max.
                            <?php
                            $max_upload = (int) (ini_get('upload_max_filesize'));
                            $max_post = (int) (ini_get('post_max_size'));
                            $memory_limit = (int) (ini_get('memory_limit'));
                            $upload_mb = min($max_upload, $max_post, $memory_limit);
                            $val = trim((string) $upload_mb);
                            $last = strtolower($val[strlen($val) - 1] ?? '');
                            switch ($last) {
                                case 'g':
                                    $val .= ' GB';
                                    break;
                                case 'k':
                                    $val .= ' kb';
                                    break;
                                default:
                                    $val .= ' MB';
                            }
                            echo $val;
                            ?>
                            <br />
                            <br />
                            <label for="csv_drop_records">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_DROP_RECORDS'); ?>
                            </label> <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[csv_drop_records]', 'csv_drop_records', true) : ''; ?>
                            <br />
                            <label for="csv_published">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH'); ?>
                            </label> <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[csv_published]', 'csv_published', true) : ''; ?>
                            <br />
                            <label for="csv_delimiter">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_DELIMITER'); ?>
                            </label> <input class="form-control form-control-sm" maxlength="3" type="text"
                                size="1" id="csv_delimiter" name="jform[csv_delimiter]" value="," />
                            <br />
                            <br />
                            <label class="editlinktip hasTip"
                                title="<?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_REPAIR_ENCODING_TIP'); ?>"
                                for="csv_repair_encoding">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_REPAIR_ENCODING'); ?>*
                            </label>
                            <br />
                            <select class="form-select-sm" style="width: 150px;" name="jform[csv_repair_encoding]"
                                id="csv_repair_encoding">
                                <option value=""> -
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_NO_REPAIR_ENCODING'); ?>
                                    -
                                </option>
                                <option value="WINDOWS-1250">WINDOWS-1250</option>
                                <option value="WINDOWS-1251">WINDOWS-1251</option>
                                <option value="WINDOWS-1252">WINDOWS-1252 (ANSI)</option>
                                <option value="WINDOWS-1253">WINDOWS-1253</option>
                                <option value="WINDOWS-1254">WINDOWS-1254</option>
                                <option value="WINDOWS-1255">WINDOWS-1255</option>
                                <option value="WINDOWS-1256">WINDOWS-1256</option>
                                <option value="ISO-8859-1">ISO-8859-1 (LATIN1)</option>
                                <option value="ISO-8859-2">ISO-8859-2</option>
                                <option value="ISO-8859-3">ISO-8859-3</option>
                                <option value="ISO-8859-4">ISO-8859-4</option>
                                <option value="ISO-8859-5">ISO-8859-5</option>
                                <option value="ISO-8859-6">ISO-8859-6</option>
                                <option value="ISO-8859-7">ISO-8859-7</option>
                                <option value="ISO-8859-8">ISO-8859-8</option>
                                <option value="ISO-8859-9">ISO-8859-9</option>
                                <option value="ISO-8859-10">ISO-8859-10</option>
                                <option value="ISO-8859-11">ISO-8859-11</option>
                                <option value="ISO-8859-12">ISO-8859-12</option>
                                <option value="ISO-8859-13">ISO-8859-13</option>
                                <option value="ISO-8859-14">ISO-8859-14</option>
                                <option value="ISO-8859-15">ISO-8859-15 (LATIN-9)</option>
                                <option value="ISO-8859-16">ISO-8859-16</option>
                                <option value="UTF-8-MAC">UTF-8-MAC</option>
                                <option value="UTF-16">UTF-16</option>
                                <option value="UTF-16BE">UTF-16BE</option>
                                <option value="UTF-16LE">UTF-16LE</option>
                                <option value="UTF-32">UTF-32</option>
                                <option value="UTF-32BE">UTF-32BE</option>
                                <option value="UTF-32LE">UTF-32LE</option>
                                <option value="ASCII">ASCII</option>
                                <option value="BIG-5">BIG-5</option>
                                <option value="HEBREW">HEBREW</option>
                                <option value="CYRILLIC">CYRILLIC</option>
                                <option value="ARABIC">ARABIC</option>
                                <option value="GREEK">GREEK</option>
                                <option value="CHINESE">CHINESE</option>
                                <option value="KOREAN">KOREAN</option>
                                <option value="KOI8-R">KOI8-R</option>
                                <option value="KOI8-U">KOI8-U</option>
                                <option value="KOI8-RU">KOI8-RU</option>
                                <option value="EUC-JP">EUC-JP</option>
                            </select>
                            <div id="cbCsvPreviewPanel" class="cb-csv-preview-panel" style="display:none;">
                                <div class="cb-csv-preview-head"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_PREVIEW_FROM_FILE'); ?></div>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width:40%;"><?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?></th>
                                            <th style="width:45%;"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?></th>
                                            <th style="width:15%;"><?php echo Text::_('JSTATUS'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cbCsvPreviewBody"></tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </fieldset>
            <?php if (!$item->bytable) : ?>
                <fieldset class="border rounded p-3 mb-3">
                    <?php if ((int) $item->id === 0) : ?>
                        <div class="alert alert-info">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_SAVE_FIRST_ADD_FIELDS'); ?>
                        </div>
                        <button
                            type="button"
                            class="btn btn-success"
                            disabled
                            title="<?php echo htmlspecialchars($addFieldTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                        >+ Add Field</button>
                    <?php else : ?>
                        <button type="button"
                            class="btn btn-success"
                            title="<?php echo htmlspecialchars($addFieldTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            onclick="Joomla.submitbutton('storage.addfield');">
                            + Add Field
                        </button>
                        <table class="admintable" width="100%">
                            <tr>
                                <td>
                                    <label for="fieldname">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-control form-control-sm w-100" type="text" id="fieldname"
                                        name="jform[fieldname]" value="" />
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="fieldtitle">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-control form-control-sm w-100" type="text" id="fieldtitle"
                                        name="jform[fieldtitle]" value="" />
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="is_group">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-check-input" type="radio" id="is_group" name="jform[is_group]"
                                        value="1" /> <label for="is_group">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                                    </label>
                                    <input class="form-check-input" type="radio" id="is_group_no" name="jform[is_group]"
                                        value="0" checked="checked" /> <label for="is_group_no">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="group_definition">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP_DEFINITION'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td align="right">
                                    <textarea class="form-control form-control-sm" style="width: 100%; height: 100px;"
                                        id="group_definition" name="jform[group_definition]">Label 1;value1
Label 2;value2
Label 3;value3</textarea>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                </fieldset>
            <?php endif; ?>
        </td>

        <td valign="top">
            <table class="table table-striped m-3 cb-storage-fields-table" style="min-width: 697px;">
                <thead>
                    <tr>
                        <th width="20">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th>
                            <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_NAME'), 'name') : Text::_('COM_CONTENTBUILDERNG_NAME'); ?>
                        </th>
                        <th>
                            <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'), 'title') : Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?>
                        </th>
                        <th>
                            <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP'), 'group_definition') : Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP'); ?>
                        </th>
                        <th class="cb-order-col">
                            <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ORDERBY'), 'ordering') : Text::_('COM_CONTENTBUILDERNG_ORDERBY'); ?>
                        </th>
                        <th>
                            <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_PUBLISHED'), 'published') : Text::_('COM_CONTENTBUILDERNG_PUBLISHED'); ?>
                        </th>
                    </tr>
                </thead>
                <?php $n = $fieldsCount; ?>
                <?php foreach ($fields as $i => $row) :
                    $id = (int) ($row->id ?? 0);
                    $name = htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES, 'UTF-8');
                    $title = htmlspecialchars((string) ($row->title ?? ''), ENT_QUOTES, 'UTF-8');
                    $groupDefinition = htmlspecialchars((string) ($row->group_definition ?? ''), ENT_QUOTES, 'UTF-8');
                    $isGroup = !empty($row->is_group);
                    $checked = HTMLHelper::_('grid.id', $i, $id);
                    $published = ContentbuilderngHelper::listPublish('storage', $row, $i);
                ?>
                    <tr class="row<?php echo $i % 2; ?>" data-cb-row-id="<?php echo $id; ?>">
                        <td class="text-center"><?php echo $checked; ?></td>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $title; ?></td>
                        <td>
                            <input type="hidden" name="itemNames[<?php echo $id; ?>]" value="<?php echo $name; ?>" />
                            <input type="hidden" name="itemTitles[<?php echo $id; ?>]" value="<?php echo $title; ?>" />

                            <input class="form-check-input" type="radio"
                                name="itemIsGroup[<?php echo $id; ?>]"
                                value="1"
                                id="itemIsGroup_<?php echo $id; ?>"
                                <?php echo $isGroup ? 'checked="checked"' : ''; ?> />
                            <label for="itemIsGroup_<?php echo $id; ?>">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                            </label>

                            <input class="form-check-input" type="radio"
                                name="itemIsGroup[<?php echo $id; ?>]"
                                value="0"
                                id="itemIsGroupNo_<?php echo $id; ?>"
                                <?php echo !$isGroup ? 'checked="checked"' : ''; ?> />
                            <label for="itemIsGroupNo_<?php echo $id; ?>">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                            </label>

                            <div id="itemGroupDefinitions_<?php echo $id; ?>">
                                <button type="button" class="btn btn-link btn-sm p-0"
                                    onclick="document.getElementById('itemGroupDefinitions<?php echo $id; ?>').style.display='block'; this.parentNode.style.display='none'; document.getElementById('itemGroupDefinitions<?php echo $id; ?>').focus(); return false;">
                                    [<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>]
                                </button>
                            </div>
                            <textarea class="form-control form-control-sm mt-1"
                                onblur="this.style.display='none'; document.getElementById('itemGroupDefinitions_<?php echo $id; ?>').style.display='block';"
                                id="itemGroupDefinitions<?php echo $id; ?>"
                                style="display:none; width:100%; height:50px;"
                                name="itemGroupDefinitions[<?php echo $id; ?>]"><?php echo $groupDefinition; ?></textarea>
                        </td>
                        <td class="order cb-order-col">
                            <?php if ($ordering) : ?>
                                <span class="cb-order-icons">
                                    <span>
                                        <?php echo $pagination ? $pagination->orderUpIcon($i, true, 'storage.orderup', 'JLIB_HTML_MOVE_UP', $ordering) : ''; ?>
                                    </span>
                                    <span>
                                        <?php echo $pagination ? $pagination->orderDownIcon($i, $n, true, 'storage.orderdown', 'JLIB_HTML_MOVE_DOWN', $ordering) : ''; ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $published; ?></td>
                    </tr>
                <?php endforeach; ?>

                <tfoot>
                    <tr>
                        <td colspan="6">
                            <div class="cb-storage-pagination">
                                <div class="cbPagesCounter d-flex flex-wrap align-items-center gap-2">
                                    <?php if ($pagination) {
                                        echo $pagination->getPagesCounter();
                                    } ?>
                                    <?php
                                    echo '<span>' . Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM') . '&nbsp;</span>';
                                    echo '<div class="d-inline-block">' . ($pagination ? $pagination->getLimitBox() : '') . '</div>';
                                    ?>
                                </div>
                                <div class="cb-storage-pages">
                                    <?php if ($pagination) {
                                        echo $pagination->getPagesLinks();
                                    } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </td>
    </tr>
</table>
