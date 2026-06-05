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
$storageTableExists = $displayData['storageTableExists'] ?? null;
$storageTableLookupName = trim((string) ($displayData['storageTableLookupName'] ?? ''));
$storageTableErrorMessage = trim((string) ($displayData['storageTableErrorMessage'] ?? ''));
$storageName = trim((string) ($displayData['storageName'] ?? ''));
$storageTitle = trim((string) ($displayData['storageTitle'] ?? ''));
$publishedToggleHtml = (string) ($displayData['publishedToggleHtml'] ?? '');
$publishedIconClass = (string) ($displayData['publishedIconClass'] ?? '');
$publishedIconTitle = (string) ($displayData['publishedIconTitle'] ?? '');
$dataTableName = (string) ($displayData['dataTableName'] ?? '-');
$storageModeKey = (string) ($displayData['storageModeKey'] ?? '');
$fieldsCount = (int) ($displayData['fieldsCount'] ?? 0);
$recordsCount = $displayData['recordsCount'] ?? null;
$createdBy = trim((string) ($displayData['createdBy'] ?? ''));
$modifiedBy = trim((string) ($displayData['modifiedBy'] ?? ''));
$formatDate = $displayData['formatDate'] ?? null;
?>
<?php if ($storageTableExists === false) : ?>
    <div class="alert alert-danger mb-3" role="alert">
        <strong><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_MISSING_TABLE'); ?></strong><br>
        <?php echo htmlspecialchars($storageTableErrorMessage !== '' ? $storageTableErrorMessage : Text::_('COM_CONTENTBUILDERNG_STORAGE_TABLE_NOT_FOUND'), ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($storageTableLookupName !== '') : ?>
            <br><code><?php echo htmlspecialchars($storageTableLookupName, ENT_QUOTES, 'UTF-8'); ?></code>
        <?php endif; ?>
    </div>
<?php elseif ($storageTableErrorMessage !== '') : ?>
    <div class="alert alert-warning mb-3" role="alert">
        <?php echo htmlspecialchars($storageTableErrorMessage, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="mb-2">
    <span class="badge bg-body-tertiary text-body border">
        <?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?> #<?php echo (int) ($item->id ?? 0); ?>
    </span>
</div>

<div class="card border rounded-3 mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <tbody>
                <tr>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?></th>
                    <td colspan="3"><?php echo htmlspecialchars($storageName !== '' ? $storageName : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_TITLE'); ?></th>
                    <td colspan="3"><?php echo htmlspecialchars($storageTitle !== '' ? $storageTitle : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'); ?></th>
                    <td colspan="3">
                        <?php if ((int) ($item->id ?? 0) > 0) : ?>
                            <?php echo $publishedToggleHtml; ?>
                            <input type="checkbox"
                                name="cid[]"
                                id="cbstorageitem0"
                                value="<?php echo (int) ($item->id ?? 0); ?>"
                                style="display:none" />
                        <?php else : ?>
                            <span class="<?php echo $publishedIconClass; ?>" aria-hidden="true" title="<?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?>"></span>
                            <span class="visually-hidden"><?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TABLE'); ?></th>
                    <td><?php echo htmlspecialchars($dataTableName, ENT_QUOTES, 'UTF-8'); ?></td>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_MODE'); ?></th>
                    <td><?php echo htmlspecialchars(Text::_($storageModeKey), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_FIELDS_COUNT'); ?></th>
                    <td colspan="3"><?php echo (int) $fieldsCount; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_RECORDS_COUNT'); ?></th>
                    <td colspan="3"><?php echo $recordsCount === null ? '-' : (int) $recordsCount; ?></td>
                </tr>
                <tr class="text-secondary">
                    <th scope="row" style="width: 240px;"><?php echo Text::_('COM_CONTENTBUILDERNG_CREATED_ON'); ?></th>
                    <td><?php echo htmlspecialchars(is_callable($formatDate) ? $formatDate($item->created ?? null) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <th scope="row" style="width: 240px;"><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></th>
                    <td><?php echo htmlspecialchars($createdBy !== '' ? $createdBy : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr class="text-secondary">
                    <th scope="row"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'); ?></th>
                    <td><?php echo htmlspecialchars(is_callable($formatDate) ? $formatDate($item->modified ?? null) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <th scope="row"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_BY_LABEL'); ?></th>
                    <td><?php echo htmlspecialchars($modifiedBy !== '' ? $modifiedBy : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
