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
use Joomla\CMS\Uri\Uri;

$currentMode = (string) ($displayData['mode'] ?? 'default');
$modes = [
    'default' => ['icon' => 'fa-solid fa-circle-half-stroke', 'label' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_COLOR_MODE_DEFAULT')],
    'light' => ['icon' => 'fa-solid fa-sun', 'label' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_COLOR_MODE_LIGHT')],
    'dark' => ['icon' => 'fa-solid fa-moon', 'label' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_COLOR_MODE_DARK')],
];
?>
<span class="d-inline-flex align-items-center gap-2 ms-2">
    <span class="btn-group btn-group-sm" role="group" aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_PREVIEW_COLOR_MODE'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php foreach ($modes as $mode => $config) : ?>
            <?php
            $uri = clone Uri::getInstance();
            if ($mode === 'default') {
                $uri->delVar('cb_preview_color_mode');
            } else {
                $uri->setVar('cb_preview_color_mode', $mode);
            }
            ?>
            <a
                class="btn btn-outline-secondary<?php echo $currentMode === $mode ? ' active' : ''; ?>"
                href="<?php echo htmlspecialchars($uri->toString(), ENT_QUOTES, 'UTF-8'); ?>"
                title="<?php echo htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $currentMode === $mode ? 'aria-current="true"' : ''; ?>
            >
                <span class="<?php echo htmlspecialchars($config['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
    </span>
</span>
