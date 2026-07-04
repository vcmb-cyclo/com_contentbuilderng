<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Service\FormAuditService;
use Joomla\CMS\Language\Text;

$audit = (array) ($this->audit ?? ['info' => [], 'checks' => []]);
$statusBadges = [
    FormAuditService::STATUS_OK => ['bg-success', 'COM_CONTENTBUILDERNG_AUDIT_STATUS_OK'],
    FormAuditService::STATUS_WARNING => ['bg-warning text-dark', 'COM_CONTENTBUILDERNG_AUDIT_STATUS_WARNING'],
    FormAuditService::STATUS_ERROR => ['bg-danger', 'COM_CONTENTBUILDERNG_AUDIT_STATUS_ERROR'],
];
?>
<div class="p-3">
    <?php if (!empty($audit['info'])) : ?>
        <h2 class="h5"><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_HEADING'); ?></h2>
        <table class="table table-sm">
            <tbody>
                <?php foreach ($audit['info'] as $label => $value) : ?>
                    <tr>
                        <th scope="row" class="w-25"><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></th>
                        <td><?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 class="h5 mt-4"><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECKS_HEADING'); ?></h2>
    <ul class="list-group">
        <?php foreach ((array) $audit['checks'] as $check) : ?>
            <?php [$badgeClass, $badgeKey] = $statusBadges[(string) ($check['status'] ?? FormAuditService::STATUS_WARNING)] ?? $statusBadges[FormAuditService::STATUS_WARNING]; ?>
            <li class="list-group-item d-flex align-items-start gap-2">
                <span class="badge <?php echo $badgeClass; ?>"><?php echo Text::_($badgeKey); ?></span>
                <span><?php echo htmlspecialchars((string) ($check['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
