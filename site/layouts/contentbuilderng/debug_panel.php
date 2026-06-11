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

use Joomla\CMS\Language\Text;

$permissions = is_array($displayData['permissions'] ?? null) ? $displayData['permissions'] : [];
$filters = is_array($displayData['filters'] ?? null) ? $displayData['filters'] : [];
$logs = is_array($displayData['logs'] ?? null) ? $displayData['logs'] : [];
$cbRecordId = (int) ($displayData['cbRecordId'] ?? 0);
$showPermissions = !empty($displayData['showPermissions']);
$showFilters = !empty($displayData['showFilters']);
$showLogs = !empty($displayData['showLogs']);
$showCbRecordId = !empty($displayData['showCbRecordId']);

if (!$showPermissions && !$showFilters && !$showLogs && !$showCbRecordId) {
    return;
}

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
?>
<style>
    .cb-debug-details {
        overflow: hidden;
    }

    .cb-debug-details > summary {
        padding: .85rem 1rem;
        cursor: pointer;
    }

    .cb-debug-details:not([open]) > summary {
        color: var(--bs-white, #fff);
        background: var(--bs-danger, #b02a37);
    }

    .cb-debug-details[open] > summary {
        color: var(--bs-danger-text-emphasis, #58151c);
        background: var(--bs-danger-bg-subtle, #f8d7da);
        border-bottom: 1px solid var(--bs-danger-border-subtle, #f1aeb5);
    }

    .cb-debug-details-body {
        padding: 0 1rem 1rem;
    }

    .cb-debug-permissions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .65rem 1.25rem;
    }

    .cb-debug-permission {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        margin: 0;
        min-height: 1.5rem;
    }

    .cb-debug-permission .form-check-input {
        float: none;
        margin: 0;
        opacity: 1;
    }

    .cb-debug-permission .form-check-input:disabled ~ .form-check-label {
        opacity: 1;
    }
</style>
<details class="cb-debug-details border border-danger rounded bg-body-tertiary mb-3">
    <summary class="fw-semibold">
        <span class="fa-solid fa-bug me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_DETAILS'); ?>
    </summary>
    <div class="cb-debug-details-body">

    <?php if ($showCbRecordId) : ?>
        <h3 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CB_RECORD_ID'); ?></h3>
        <code><?php echo $cbRecordId > 0 ? $cbRecordId : Text::_('COM_CONTENTBUILDERNG_DEBUG_EMPTY_VALUE'); ?></code>
    <?php endif; ?>

    <?php if ($showPermissions) : ?>
        <h3 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_PERMISSIONS'); ?></h3>
        <div class="cb-debug-permissions">
            <?php foreach ($permissions as $label => $allowed) : ?>
                <?php $permissionId = 'cb-debug-permission-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $label); ?>
                <div class="form-check cb-debug-permission">
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
        <h3 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_FILTERS'); ?></h3>
        <dl class="row mb-0">
            <?php foreach ($filters as $label => $value) : ?>
                <dt class="col-sm-4"><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></dt>
                <dd class="col-sm-8"><code><?php echo htmlspecialchars($formatValue($value), ENT_QUOTES, 'UTF-8'); ?></code></dd>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ($showLogs) : ?>
        <h3 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_REQUEST_LOGS'); ?></h3>
        <?php if ($logs === []) : ?>
            <p class="text-muted mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_NO_REQUEST_LOGS'); ?></p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_LOG_TIME'); ?></th>
                            <th><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_LOG_LEVEL'); ?></th>
                            <th><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_LOG_MESSAGE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $entry) : ?>
                            <tr>
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
