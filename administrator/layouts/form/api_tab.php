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

$apiExampleDetailUrl = (string) ($displayData['apiExampleDetailUrl'] ?? '');
$apiExampleListUrl = (string) ($displayData['apiExampleListUrl'] ?? '');
$apiExampleUpdateUrl = (string) ($displayData['apiExampleUpdateUrl'] ?? '');
$apiExampleVerboseUrl = (string) ($displayData['apiExampleVerboseUrl'] ?? '');
$apiExampleDetailDisplayUrl = (string) ($displayData['apiExampleDetailDisplayUrl'] ?? '');
$apiExampleListDisplayUrl = (string) ($displayData['apiExampleListDisplayUrl'] ?? '');
$apiExampleVerboseDisplayUrl = (string) ($displayData['apiExampleVerboseDisplayUrl'] ?? '');
$apiExamplePayloadJson = (string) ($displayData['apiExamplePayloadJson'] ?? '');
?>
<h3 id="cb-form-api" class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_TITLE'); ?></h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_PERMISSION_HINT'); ?>
</div>
<table id="cb-form-api-endpoints" class="table table-striped">
    <tr>
        <th style="width:180px;"><?php echo Text::_('COM_CONTENTBUILDERNG_API_METHOD'); ?></th>
        <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_ENDPOINT'); ?></th>
        <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_DESCRIPTION'); ?></th>
    </tr>
    <tr>
        <td><code>GET</code></td>
        <td>
            <a href="<?php echo htmlspecialchars($apiExampleDetailUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                <code><?php echo htmlspecialchars($apiExampleDetailDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
            </a>
        </td>
        <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_DETAIL_DESC'); ?></td>
    </tr>
    <tr>
        <td><code>GET</code></td>
        <td>
            <a href="<?php echo htmlspecialchars($apiExampleListUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                <code><?php echo htmlspecialchars($apiExampleListDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
            </a>
        </td>
        <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_LIST_DESC'); ?></td>
    </tr>
    <tr>
        <td><code>PUT</code> / <code>PATCH</code> / <code>POST</code></td>
        <td>
            <a href="<?php echo htmlspecialchars($apiExampleUpdateUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                <code><?php echo htmlspecialchars($apiExampleUpdateUrl, ENT_QUOTES, 'UTF-8'); ?></code>
            </a>
        </td>
        <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_UPDATE_DESC'); ?></td>
    </tr>
</table>
<div class="alert alert-secondary py-2 mb-3">
    <strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_VERBOSE_OPTION_TITLE'); ?></strong>
    <?php echo Text::_('COM_CONTENTBUILDERNG_API_VERBOSE_OPTION_TEXT'); ?>
    <a href="<?php echo htmlspecialchars($apiExampleVerboseUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
        <code><?php echo htmlspecialchars($apiExampleVerboseDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
    </a>
</div>
<label id="cb-form-api-payload" for="cb_api_example_payload" class="form-label"><strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_JSON_LABEL'); ?></strong></label>
<textarea id="cb_api_example_payload" class="form-control" rows="7" readonly="readonly"><?php echo htmlspecialchars($apiExamplePayloadJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
