<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
?>
<div class="container-fluid p-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_TITLE'); ?></h1>

    <div class="alert alert-info mb-3">
        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_INTRO'); ?>
    </div>

    <ul class="mb-4">
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_POINT_1'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_POINT_2'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_POINT_3'); ?></li>
    </ul>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_WHAT'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_WHAT_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_WHEN'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_WHEN_TEXT'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-warning mb-3">
        <div class="card-body">
            <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_SYNC'); ?></h2>
            <p><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_SYNC_TEXT'); ?></p>
            <div class="alert alert-warning mb-0">
                <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_SYNC_WARN'); ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_STATE'); ?></h2>
            <ul class="mb-2">
                <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_STATE_ON'); ?></li>
                <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_STATE_OFF'); ?></li>
            </ul>
            <p class="mb-0 text-muted small"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_SEC_STATE_ORDER'); ?></p>
        </div>
    </div>

</div>
