<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$permissions = is_array($displayData['permissions'] ?? null) ? $displayData['permissions'] : [];
$filters = is_array($displayData['filters'] ?? null) ? $displayData['filters'] : [];
$logs = is_array($displayData['logs'] ?? null) ? $displayData['logs'] : [];
$warnings = is_array($displayData['warnings'] ?? null) ? $displayData['warnings'] : [];
$fields = is_array($displayData['fields'] ?? null) ? $displayData['fields'] : [];
$formId = (int) ($displayData['formId'] ?? 0);
$cbRecordId = (int) ($displayData['cbRecordId'] ?? 0);
$showPermissions = !empty($displayData['showPermissions']);
$showFilters = !empty($displayData['showFilters']);
$showLogs = !empty($displayData['showLogs']);
$showCbRecordId = !empty($displayData['showCbRecordId']);
$debugIdBase = 'cb-debug-form-' . max(0, $formId);
if ($cbRecordId > 0) {
    $debugIdBase .= '-record-' . $cbRecordId;
}

$identity = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getIdentity();
$accountId = (int) ($identity->id ?? 0);
$accountName = trim((string) ($identity->name ?? ''));
$accountUsername = trim((string) ($identity->username ?? ''));

if ($accountName === '') {
    $accountName = $accountUsername !== ''
        ? $accountUsername
        : Text::_('COM_CONTENTBUILDERNG_DEBUG_GUEST_ACCOUNT');
}

$accountLabel = $accountName;
if ($accountUsername !== '' && $accountUsername !== $accountName) {
    $accountLabel .= ' (' . $accountUsername . ')';
}
$accountLabel .= ' (#' . $accountId . ')';

$formatValue = static function ($value): string {
    if (is_bool($value)) {
        return $value ? Text::_('JYES') : Text::_('JNO');
    }

    if ($value === null || $value === '' || $value === []) {
        return Text::_('COM_CONTENTBUILDERNG_DEBUG_EMPTY_VALUE');
    }

    if (is_array($value) || is_object($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false ? $json : Text::_('COM_CONTENTBUILDERNG_DEBUG_EMPTY_VALUE');
    }

    return (string) $value;
};

$wa = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
$wa->useStyle('com_contentbuilderng.debug-panel');
?>
<details id="<?php echo $debugIdBase; ?>" class="cb-debug-details border border-danger rounded bg-body-tertiary mb-3" aria-labelledby="<?php echo $debugIdBase; ?>-summary">
    <summary id="<?php echo $debugIdBase; ?>-summary" class="fw-semibold">
        <span class="fa-solid fa-bug me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_DETAILS'); ?>
    </summary>
    <div id="<?php echo $debugIdBase; ?>-body" class="cb-debug-details-body">

        <h3 id="<?php echo $debugIdBase; ?>-context-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CONTEXT'); ?></h3>
        <dl id="<?php echo $debugIdBase; ?>-context" class="row mb-0" aria-labelledby="<?php echo $debugIdBase; ?>-context-heading">
            <dt id="<?php echo $debugIdBase; ?>-current-account-label" class="col-sm-4"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CURRENT_ACCOUNT'); ?></dt>
            <dd id="<?php echo $debugIdBase; ?>-current-account-value" class="col-sm-8"><code><?php echo htmlspecialchars($accountLabel, ENT_QUOTES, 'UTF-8'); ?></code></dd>
            <dt id="<?php echo $debugIdBase; ?>-form-id-label" class="col-sm-4"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FORM_ID'); ?></dt>
            <dd id="<?php echo $debugIdBase; ?>-form-id-value" class="col-sm-8"><code><?php echo $formId > 0 ? $formId : Text::_('COM_CONTENTBUILDERNG_DEBUG_EMPTY_VALUE'); ?></code></dd>
        </dl>

    <?php if ($showCbRecordId) : ?>
        <h3 id="<?php echo $debugIdBase; ?>-record-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CB_RECORD_ID'); ?></h3>
        <code id="<?php echo $debugIdBase; ?>-record-id" aria-labelledby="<?php echo $debugIdBase; ?>-record-heading"><?php echo $cbRecordId > 0 ? $cbRecordId : Text::_('COM_CONTENTBUILDERNG_DEBUG_EMPTY_VALUE'); ?></code>
    <?php endif; ?>

    <?php if ($showPermissions) : ?>
        <h3 id="<?php echo $debugIdBase; ?>-permissions-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_PERMISSIONS'); ?></h3>
        <div id="<?php echo $debugIdBase; ?>-permissions" class="cb-debug-permissions" aria-labelledby="<?php echo $debugIdBase; ?>-permissions-heading">
            <?php foreach ($permissions as $label => $allowed) : ?>
                <?php $permissionId = $debugIdBase . '-permission-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $label); ?>
                <div id="<?php echo htmlspecialchars($permissionId . '-item', ENT_QUOTES, 'UTF-8'); ?>" class="form-check cb-debug-permission">
                    <input class="form-check-input" type="checkbox"
                        id="<?php echo htmlspecialchars($permissionId, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo !empty($allowed) ? ' checked' : ''; ?> disabled />
                    <label class="form-check-label" for="<?php echo htmlspecialchars($permissionId, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($showFilters) : ?>
        <h3 id="<?php echo $debugIdBase; ?>-filters-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FILTERS'); ?></h3>
        <dl id="<?php echo $debugIdBase; ?>-filters" class="row mb-0" aria-labelledby="<?php echo $debugIdBase; ?>-filters-heading">
            <?php foreach ($filters as $label => $value) : ?>
                <?php $filterId = $debugIdBase . '-filter-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $label); ?>
                <dt id="<?php echo htmlspecialchars($filterId . '-label', ENT_QUOTES, 'UTF-8'); ?>" class="col-sm-4"><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></dt>
                <dd id="<?php echo htmlspecialchars($filterId . '-value', ENT_QUOTES, 'UTF-8'); ?>" class="col-sm-8"><code><?php echo htmlspecialchars($formatValue($value), ENT_QUOTES, 'UTF-8'); ?></code></dd>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ($warnings !== []) : ?>
        <h3 id="<?php echo $debugIdBase; ?>-warnings-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_WARNINGS'); ?></h3>
        <ul id="<?php echo $debugIdBase; ?>-warnings" class="list-group list-group-flush" aria-labelledby="<?php echo $debugIdBase; ?>-warnings-heading">
            <?php foreach ($warnings as $warningIndex => $warning) : ?>
                <li id="<?php echo $debugIdBase; ?>-warning-<?php echo (int) $warningIndex + 1; ?>" class="list-group-item bg-warning-subtle border-warning-subtle">
                    <?php echo htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8'); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($fields !== []) : ?>
        <h3 id="<?php echo $debugIdBase; ?>-fields-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FIELDS'); ?></h3>
        <div id="<?php echo $debugIdBase; ?>-fields-wrapper" class="table-responsive">
            <table id="<?php echo $debugIdBase; ?>-fields" class="table table-sm table-striped mb-0" aria-labelledby="<?php echo $debugIdBase; ?>-fields-heading">
                <thead>
                    <tr>
                        <th id="<?php echo $debugIdBase; ?>-fields-col-position" class="text-end text-muted pe-2" style="width:2.5rem">#</th>
                        <th id="<?php echo $debugIdBase; ?>-fields-col-label"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FIELD_LABEL'); ?></th>
                        <th id="<?php echo $debugIdBase; ?>-fields-col-reference"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FIELD_REFERENCE'); ?></th>
                        <th id="<?php echo $debugIdBase; ?>-fields-col-type"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FIELD_TYPE'); ?></th>
                        <th id="<?php echo $debugIdBase; ?>-fields-col-editable" class="text-center"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FIELD_EDITABLE'); ?></th>
                        <th id="<?php echo $debugIdBase; ?>-fields-col-published" class="text-center"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FIELD_PUBLISHED'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $i => $field) : ?>
                        <?php $rowEditable = !empty($field['editable']); $rowPublished = !empty($field['published']); ?>
                        <?php $fieldRowId = $debugIdBase . '-field-' . ((int) $i + 1); ?>
                        <tr id="<?php echo $fieldRowId; ?>" class="<?php echo (!$rowEditable || !$rowPublished) ? 'table-warning' : ''; ?>">
                            <td class="text-end text-muted pe-2"><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><code><?php echo htmlspecialchars((string) ($field['reference_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                            <td><code><?php echo htmlspecialchars((string) ($field['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                            <td class="text-center">
                                <input id="<?php echo $fieldRowId; ?>-editable" class="form-check-input" type="checkbox" <?php echo $rowEditable ? 'checked' : ''; ?> disabled aria-label="<?php echo htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                            <td class="text-center">
                                <input id="<?php echo $fieldRowId; ?>-published" class="form-check-input" type="checkbox" <?php echo $rowPublished ? 'checked' : ''; ?> disabled aria-label="<?php echo htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($showLogs) : ?>
        <h3 id="<?php echo $debugIdBase; ?>-logs-heading" class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_REQUEST_LOGS'); ?></h3>
        <?php if ($logs === []) : ?>
            <p id="<?php echo $debugIdBase; ?>-logs-empty" class="text-muted mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_NO_REQUEST_LOGS'); ?></p>
        <?php else : ?>
            <div id="<?php echo $debugIdBase; ?>-logs-wrapper" class="table-responsive">
                <table id="<?php echo $debugIdBase; ?>-logs" class="table table-sm table-striped mb-0" aria-labelledby="<?php echo $debugIdBase; ?>-logs-heading">
                    <thead>
                        <tr>
                            <th id="<?php echo $debugIdBase; ?>-logs-col-time"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_LOG_TIME'); ?></th>
                            <th id="<?php echo $debugIdBase; ?>-logs-col-level"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_LOG_LEVEL'); ?></th>
                            <th id="<?php echo $debugIdBase; ?>-logs-col-message"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_LOG_MESSAGE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $logIndex => $entry) : ?>
                            <tr id="<?php echo $debugIdBase; ?>-log-<?php echo (int) $logIndex + 1; ?>">
                                <td><code><?php echo htmlspecialchars((string) ($entry['time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><code><?php echo htmlspecialchars((string) ($entry['level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><code><?php echo htmlspecialchars((string) ($entry['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</details>
