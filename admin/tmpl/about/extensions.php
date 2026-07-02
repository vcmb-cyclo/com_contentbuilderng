<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$plugins = is_array($this->plugins ?? null) ? $this->plugins : [];
?>
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h5 mb-1"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXTENSIONS_TITLE'); ?></h2>
            <p class="text-muted mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXTENSIONS_DESC'); ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_plugins&view=plugins&filter[search]=' . rawurlencode('contentbuilder ng'), false); ?>">
                <span class="fa-solid fa-plug" aria-hidden="true"></span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXTENSIONS_MANAGE_PLUGINS'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=about', false); ?>">
                <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_BACK_TO_ABOUT'); ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($plugins === []) : ?>
            <div class="alert alert-info mb-0">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PLUGINS_NOT_AVAILABLE'); ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table id="cb-extensions-table" class="table table-sm table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXTENSION_CATEGORY'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN_STATUS'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXTENSION_PURPOSE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXTENSION_USAGE'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plugins as $plugin) : ?>
                        <tr>
                            <th scope="row">
                                <strong><?php echo htmlspecialchars((string) ($plugin['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="text-muted small d-block">
                                    <?php echo htmlspecialchars((string) ($plugin['group'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    /
                                    <?php echo htmlspecialchars((string) ($plugin['element'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ((string) ($plugin['version'] ?? '') !== '') : ?>
                                        · <?php echo htmlspecialchars((string) $plugin['version'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </span>
                            </th>
                            <td><?php echo htmlspecialchars((string) ($plugin['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge <?php echo !empty($plugin['enabled']) ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo Text::_(!empty($plugin['enabled']) ? 'COM_CONTENTBUILDERNG_PUBLISHED' : 'COM_CONTENTBUILDERNG_UNPUBLISHED'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($plugin['purpose'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($plugin['usage'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
