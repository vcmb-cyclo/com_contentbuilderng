<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Service\ApiPermissionRequirementService;
use Joomla\CMS\Language\Text;

$apiExampleDetailUrl = (string) ($displayData['apiExampleDetailUrl'] ?? '');
$apiExampleListUrl = (string) ($displayData['apiExampleListUrl'] ?? '');
$apiExampleUpdateUrl = (string) ($displayData['apiExampleUpdateUrl'] ?? '');
$apiExampleStatsUrl = (string) ($displayData['apiExampleStatsUrl'] ?? '');
$apiExampleFilteredStatsUrl = (string) ($displayData['apiExampleFilteredStatsUrl'] ?? '');
$apiExampleVerboseUrl = (string) ($displayData['apiExampleVerboseUrl'] ?? '');
$apiExampleDetailDisplayUrl = (string) ($displayData['apiExampleDetailDisplayUrl'] ?? '');
$apiExampleListDisplayUrl = (string) ($displayData['apiExampleListDisplayUrl'] ?? '');
$apiExampleStatsDisplayUrl = (string) ($displayData['apiExampleStatsDisplayUrl'] ?? '');
$apiExampleFilteredStatsDisplayUrl = (string) ($displayData['apiExampleFilteredStatsDisplayUrl'] ?? '');
$apiExampleVerboseDisplayUrl = (string) ($displayData['apiExampleVerboseDisplayUrl'] ?? '');
$apiExampleSparseListUrl = (string) ($displayData['apiExampleSparseListUrl'] ?? '');
$apiExampleSparseDetailUrl = (string) ($displayData['apiExampleSparseDetailUrl'] ?? '');
$apiExampleSparseStatsUrl = (string) ($displayData['apiExampleSparseStatsUrl'] ?? '');
$apiExampleSparseListDisplayUrl = (string) ($displayData['apiExampleSparseListDisplayUrl'] ?? '');
$apiExampleSparseDetailDisplayUrl = (string) ($displayData['apiExampleSparseDetailDisplayUrl'] ?? '');
$apiExampleSparseStatsDisplayUrl = (string) ($displayData['apiExampleSparseStatsDisplayUrl'] ?? '');
$apiExamplePayloadJson = (string) ($displayData['apiExamplePayloadJson'] ?? '');
$formId = (int) ($displayData['formId'] ?? 0);
$cbStatsTotalSyntax = '{CBStats id=' . $formId . ' output=total}';
$cbStatsDebugSyntax = '{CBStats id=' . $formId . ' output=total debug=1}';
$cbStatsFilterSyntax = '{CBStats id=' . $formId . ' filter[field]=NomDuChamp filter[value]="200 km* | 300 km*" output=total}';
$cbStatsTableSyntax = '{CBStats id=' . $formId . ' field=NomDuChamp output=table}';
$cbStatsSumSyntax   = '{CBStats id=' . $formId . ' field=NomDuChamp output=sum}';
$apiPermissionRequirements = new ApiPermissionRequirementService();
$permissionLabelKeys = [
    'api' => 'COM_CONTENTBUILDERNG_PERM_API',
    'view' => 'COM_CONTENTBUILDERNG_PERM_VIEW',
    'listaccess' => 'COM_CONTENTBUILDERNG_PERM_LIST_ACCESS',
    'edit' => 'COM_CONTENTBUILDERNG_PERM_EDIT',
    'rating' => 'COM_CONTENTBUILDERNG_PERM_RATING',
    'stats' => 'COM_CONTENTBUILDERNG_PERM_STATS',
];
$renderPermissions = static function (array $permissions) use ($permissionLabelKeys): string {
    $items = [];

    foreach ($permissions as $permission) {
        $labelKey = $permissionLabelKeys[$permission] ?? '';
        if ($labelKey === '') {
            continue;
        }

        $items[] = '<span class="badge bg-secondary">' . htmlspecialchars(Text::_($labelKey), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return implode(' <span class="text-muted">+</span> ', $items);
};
?>
<style>
    #cb-form-api-endpoints {
        width: 100%;
        min-width: 0;
    }

    #cb-form-api-endpoints th:first-child,
    #cb-form-api-endpoints td:first-child {
        width: 8rem;
    }

    #cb-form-api-endpoints th:nth-child(2),
    #cb-form-api-endpoints td:nth-child(2),
    #cb-form-api-endpoints th:nth-child(3),
    #cb-form-api-endpoints td:nth-child(3) {
        width: 36%;
    }

    #cb-form-api-endpoints th:last-child,
    #cb-form-api-endpoints td:last-child {
        width: 10rem;
    }

    #cb-form-api-endpoints code,
    .cb-form-api-inline-code {
        overflow-wrap: anywhere;
        white-space: normal;
        word-break: break-word;
    }
</style>
<h3 id="cb-form-api" class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_TITLE'); ?></h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_PERMISSION_HINT'); ?>
</div>
<div class="table-responsive mb-3">
    <table id="cb-form-api-endpoints" class="table table-striped align-middle">
        <thead>
            <tr>
                <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_METHOD'); ?></th>
                <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_ENDPOINT'); ?></th>
                <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_DESCRIPTION'); ?></th>
                <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_PERMISSIONS'); ?></th>
            </tr>
        </thead>
        <tr>
            <td><code>GET</code></td>
            <td>
                <a href="<?php echo htmlspecialchars($apiExampleDetailUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <code><?php echo htmlspecialchars($apiExampleDetailDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </a>
            </td>
            <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_DETAIL_DESC'); ?></td>
            <td><?php echo $renderPermissions($apiPermissionRequirements->getRequiredPermissions('GET', '', 1)); ?></td>
        </tr>
        <tr>
            <td><code>GET</code></td>
            <td>
                <a href="<?php echo htmlspecialchars($apiExampleListUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <code><?php echo htmlspecialchars($apiExampleListDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </a>
            </td>
            <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_LIST_DESC'); ?></td>
            <td><?php echo $renderPermissions($apiPermissionRequirements->getRequiredPermissions('GET', '', 0)); ?></td>
        </tr>
        <tr>
            <td><code>GET</code></td>
            <td>
                <div class="mb-2">
                    <strong class="d-block"><?php echo Text::_('COM_CONTENTBUILDERNG_API_STATS_GLOBAL_EXAMPLE'); ?></strong>
                    <a href="<?php echo htmlspecialchars($apiExampleStatsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <code><?php echo htmlspecialchars($apiExampleStatsDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                    </a>
                </div>
                <div>
                    <strong class="d-block"><?php echo Text::_('COM_CONTENTBUILDERNG_API_STATS_FILTERED_EXAMPLE'); ?></strong>
                    <a href="<?php echo htmlspecialchars($apiExampleFilteredStatsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <code><?php echo htmlspecialchars($apiExampleFilteredStatsDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                    </a>
                </div>
            </td>
            <td>
                <strong class="d-block mb-1"><?php echo Text::_('COM_CONTENTBUILDERNG_API_STATS_SECTION_TITLE'); ?></strong>
                <?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_STATS_DESC'); ?>
            </td>
            <td><?php echo $renderPermissions($apiPermissionRequirements->getRequiredPermissions('GET', 'stats', 0)); ?></td>
        </tr>
        <tr>
            <td><code><?php echo Text::_('COM_CONTENTBUILDERNG_API_CONTENT_PLUGIN_METHOD'); ?></code></td>
            <td>
                <code><?php echo htmlspecialchars($cbStatsTotalSyntax, ENT_QUOTES, 'UTF-8'); ?></code><br>
                <code><?php echo htmlspecialchars($cbStatsDebugSyntax, ENT_QUOTES, 'UTF-8'); ?></code><br>
                <code><?php echo htmlspecialchars($cbStatsFilterSyntax, ENT_QUOTES, 'UTF-8'); ?></code><br>
                <code><?php echo htmlspecialchars($cbStatsTableSyntax, ENT_QUOTES, 'UTF-8'); ?></code><br>
                <code><?php echo htmlspecialchars($cbStatsSumSyntax, ENT_QUOTES, 'UTF-8'); ?></code>
            </td>
            <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_CONTENT_PLUGIN_STATS_DESC'); ?></td>
            <td><?php echo $renderPermissions($apiPermissionRequirements->getRequiredPermissions('GET', 'stats', 0)); ?></td>
        </tr>
        <tr>
            <td><code>PUT</code> / <code>PATCH</code> / <code>POST</code></td>
            <td>
                <a href="<?php echo htmlspecialchars($apiExampleUpdateUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <code><?php echo htmlspecialchars($apiExampleUpdateUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </a>
            </td>
            <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_UPDATE_DESC'); ?></td>
            <td><?php echo $renderPermissions($apiPermissionRequirements->getRequiredPermissions('PUT', '', 1)); ?></td>
        </tr>
    </table>
</div>
<div class="alert alert-secondary py-2 mb-3">
    <strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_VERBOSE_OPTION_TITLE'); ?></strong>
    <?php echo Text::_('COM_CONTENTBUILDERNG_API_VERBOSE_OPTION_TEXT'); ?>
    <a href="<?php echo htmlspecialchars($apiExampleVerboseUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
        <code class="cb-form-api-inline-code"><?php echo htmlspecialchars($apiExampleVerboseDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
    </a>
</div>
<div class="card mb-3">
    <div class="card-body">
        <h4 class="card-title"><?php echo Text::_('COM_CONTENTBUILDERNG_API_SPARSE_FIELDSETS_TITLE'); ?></h4>
        <p><?php echo Text::_('COM_CONTENTBUILDERNG_API_SPARSE_FIELDSETS_INTRO'); ?></p>
        <ul class="mb-2">
            <li>
                <strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_SPARSE_FIELDSETS_LIST'); ?></strong>
                <a href="<?php echo htmlspecialchars($apiExampleSparseListUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <code class="cb-form-api-inline-code"><?php echo htmlspecialchars($apiExampleSparseListDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </a>
            </li>
            <li>
                <strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_SPARSE_FIELDSETS_DETAIL'); ?></strong>
                <a href="<?php echo htmlspecialchars($apiExampleSparseDetailUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <code class="cb-form-api-inline-code"><?php echo htmlspecialchars($apiExampleSparseDetailDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </a>
            </li>
            <li>
                <strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_SPARSE_FIELDSETS_STATS'); ?></strong>
                <a href="<?php echo htmlspecialchars($apiExampleSparseStatsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <code class="cb-form-api-inline-code"><?php echo htmlspecialchars($apiExampleSparseStatsDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </a>
            </li>
        </ul>
        <p class="text-muted mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_API_SPARSE_FIELDSETS_NOTE'); ?></p>
    </div>
</div>
<label id="cb-form-api-payload" for="cb_api_example_payload" class="form-label"><strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_JSON_LABEL'); ?></strong></label>
<textarea id="cb_api_example_payload" class="form-control" rows="7" readonly="readonly"><?php echo htmlspecialchars($apiExamplePayloadJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
