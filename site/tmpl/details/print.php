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

// No direct access
\defined('_JEXEC') or die('Restricted access');


use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Application\CMSApplication;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;

$app = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication();
/** @var CMSApplication $app */
$showAuthorToggle = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_author', (int) ($this->cb_show_author ?? 1));

$wa = $app->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
$wa->useStyle('com_contentbuilderng.frontend');

$themeCss = trim((string) ($this->theme_css ?? ''));
if ($themeCss !== '') {
    $wa->addInlineStyle($themeCss);
}

$themeJs = (string) ($this->theme_js ?? '');
if (trim($themeJs) !== '') {
    $wa->addInlineScript($themeJs);
}


?>

<div class="cb-print-wrapper">

<div class="cb-print-actions">
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
        <span class="fa-solid fa-print me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_PRINT') ?>
    </button>
    <button class="btn btn-sm btn-outline-secondary" onclick="self.close()">
        <span class="icon-times me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_CLOSE') ?>
    </button>
</div>
<h1 class="display-6 mb-4">
    <?php echo $this->page_title; ?>
</h1>
<?php echo $this->event->afterDisplayTitle; ?>
<?php
if ($showAuthorToggle === 1) {
    ?>
    <div class="cb-print-meta">
    <?php if ($this->created): ?>
        <span class="small created-by">
            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATED_ON'); ?>
            <?php echo HTMLHelper::_('date', $this->created, Text::_('DATE_FORMAT_LC5')); ?>
        </span>
    <?php endif; ?>

    <?php if ($this->created_by): ?>
        <span class="small created-by">
            <?php echo Text::_('COM_CONTENTBUILDERNG_BY'); ?>
            <?php echo $this->created_by; ?>
        </span>
    <?php endif; ?>
    </div>
    <?php
}
?>

<div class="mt-3">
<?php echo $this->event->beforeDisplayContent; ?>
<?php echo $this->toc ?>
<?php echo $this->tpl ?>
<?php echo $this->event->afterDisplayContent; ?>
</div>

<?php
if ($showAuthorToggle === 1) {
    ?>

    <?php if ($this->modified_by): ?>
        <div class="cb-print-meta mt-3">
        <?php if ($this->modified): ?>
            <span class="small created-by">
                <?php echo Text::_('COM_CONTENTBUILDERNG_LAST_UPDATED_ON'); ?>
                <?php echo HTMLHelper::_('date', $this->modified, Text::_('DATE_FORMAT_LC5')); ?>
            </span>
        <?php endif; ?>

        <span class="small created-by">
            <?php echo Text::_('COM_CONTENTBUILDERNG_BY'); ?>
            <?php echo $this->modified_by; ?>
        </span>
        </div>
    <?php endif; ?>

    <?php
}
?>

</div><?php /* .cb-print-wrapper */ ?>
