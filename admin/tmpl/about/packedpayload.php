<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$packedPayloadReport = is_array($this->packedPayloadReport ?? null) ? $this->packedPayloadReport : [];
$status = (string) ($packedPayloadReport['status'] ?? 'error');
$table = trim((string) ($packedPayloadReport['table'] ?? ''));
$column = trim((string) ($packedPayloadReport['column'] ?? ''));
$recordId = (int) ($packedPayloadReport['record_id'] ?? 0);
$recordLabel = trim((string) ($packedPayloadReport['record_label'] ?? ''));
$rawValue = (string) ($packedPayloadReport['raw_value'] ?? '');
$message = trim((string) ($packedPayloadReport['message'] ?? ''));
$backUrl = Route::_('index.php?option=com_contentbuilderng&view=about', false);
?>
<div class="card mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h3 class="h6 card-title mb-1"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW_TITLE'); ?></h3>
                <p class="text-muted mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW_NOTE'); ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo Text::_('COM_CONTENTBUILDERNG_BACK'); ?>
            </a>
        </div>

        <dl class="row mb-0">
            <dt class="col-sm-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($table !== '' ? $table : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'), ENT_QUOTES, 'UTF-8'); ?></dd>

            <dt class="col-sm-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($column !== '' ? $column : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'), ENT_QUOTES, 'UTF-8'); ?></dd>

            <dt class="col-sm-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></dt>
            <dd class="col-sm-9"><?php echo $recordId > 0 ? (int) $recordId : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></dd>

            <dt class="col-sm-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RECORD'); ?></dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($recordLabel !== '' ? $recordLabel : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'), ENT_QUOTES, 'UTF-8'); ?></dd>
        </dl>

        <?php if ($status !== 'ok') : ?>
            <div class="alert alert-warning mt-3 mb-0">
                <?php echo htmlspecialchars($message !== '' ? $message : Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW_NOT_FOUND'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php else : ?>
            <pre class="border rounded p-3 mt-3 mb-0 bg-body-tertiary" style="white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($rawValue !== '' ? $rawValue : Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW_EMPTY'), ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endif; ?>
    </div>
</div>
