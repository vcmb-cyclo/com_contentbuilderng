<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2024-2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */



// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Application\CMSApplication;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
$wa->useStyle('com_contentbuilderng.frontend');

$toUnicodeSlug = static function (string $string): string {
    $str = preg_replace('/\xE3\x80\x80/', ' ', $string) ?? $string;
    $str = str_replace('-', ' ', $str);
    $str = preg_replace('#[:\#\*"@+=;!&\.%()\]\/\'\\\\|\[]#', ' ', $str) ?? $str;
    $str = str_replace('?', '', $str);
    $str = trim(strtolower($str));
    $str = preg_replace('#\x20+#', '-', $str) ?? $str;

    return $str;
};

$th = 'th';
if ($this->page_heading) {
    ?>
    <h1 class="display-6 mb-4">
        <?php $app = Factory::getApplication();/** @var CMSApplication $app */ echo $app->getDocument()->getTitle(); ?>
    </h1>
    <?php
}
?>
<form action="" method="get" id="adminForm" name="adminForm">

    <?php
    if ($this->show_tags) {
        ?>
        <div class="cb-pubforms-tag-filter">
            <label for="filter_tag"><?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_TAG'); ?> :</label>
            <select id="filter_tag" name="filter_tag" class="form-select form-select-sm w-auto"
                onchange="document.adminForm.submit();">
                <option value=""> -
                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FILTER_TAG_ALL'), ENT_QUOTES, 'UTF-8') ?> -
                </option>
                <?php
                foreach ($this->tags as $tag) {
                    ?>
                    <option value="<?php echo htmlspecialchars($tag->tag, ENT_QUOTES, 'UTF-8') ?>" <?php echo strtolower($this->lists['filter_tag']) == strtolower($tag->tag) ? ' selected="selected"' : ''; ?>>
                        <?php echo htmlspecialchars($tag->tag, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </div>
        <?php
    }
    ?>
    <div class="table-responsive">
    <table class="table table-hover table-sm">
        <thead>
            <tr>

                <?php
                if ($this->show_id) {
                    ?>

                    <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?>
                        <?php //echo HTMLHelper::_('grid.sort', Text::_( 'COM_CONTENTBUILDERNG_ID' ), 'id', $this->lists['order_Dir'], $this->lists['order'] );     ?>
                    </<?php echo $th; ?>>

                    <?php
                }
                ?>

                <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase cb-pubforms-th-name">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_FORM'); ?>
                    <?php // echo HTMLHelper::_('grid.sort', Text::_( 'COM_CONTENTBUILDERNG_VIEW_NAME' ), 'name', $this->lists['order_Dir'], $this->lists['order'] );     ?>
                </<?php echo $th; ?>>

                <?php
                if ($this->show_tags) {
                    ?>

                    <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase">
                        <?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_TAG'), 'tag', $this->lists['order_Dir'], $this->lists['order']); ?>
                    </<?php echo $th; ?>>

                    <?php
                }
                ?>

                <?php
                if ($this->introtext) {
                    ?>

                    <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_API_DESCRIPTION'); ?>
                    </<?php echo $th; ?>>

                    <?php
                }
                ?>

                <?php
                if ($this->show_permissions) {
                    ?>

                    <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ACCESS_VIEW'); ?>
                    </<?php echo $th; ?>>

                    <?php
                }
                ?>

                <?php
                if ($this->show_permissions_new) {
                    ?>

                    <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ACCESS_NEW'); ?>
                    </<?php echo $th; ?>>

                    <?php
                }
                ?>

                <?php
                if ($this->show_permissions_edit) {
                    ?>

                    <<?php echo $th; ?> class="align-middle text-nowrap small text-uppercase">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ACCESS_EDIT'); ?>
                    </<?php echo $th; ?>>

                    <?php
                }
                ?>

            </tr>
        </thead>
        <?php
        $k = 0;
        $n = count($this->items);
        for ($i = 0; $i < $n; $i++) {
            $row = $this->items[$i];
            $link_ = htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
            if (($this->show_permissions && $this->perms[$row->id]['view']) || !$this->show_permissions) {
                $link = Route::_('index.php?option=com_contentbuilderng&title=' . $toUnicodeSlug((string) $row->name) . '&task=list.display&id=' . $row->id);
                $link_ = '<a href="' . $link . '">' . htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8') . '</a>';
            }
            ?>
            <tr class="<?php echo"row$k"; ?>">

                <?php
                if ($this->show_id) {
                    ?>

                    <td class="align-top">
                        <?php echo $row->id; ?>
                    </td>

                    <?php
                }
                ?>

                <td class="align-top">
                    <?php echo $link_; ?>
                </td>

                <?php
                if ($this->show_tags) {
                    ?>

                    <td class="align-top">
                        <?php echo $row->tag; ?>
                    </td>

                    <?php
                }
                ?>

                <?php
                if ($this->introtext) {
                    // Search for the {readmore} tag and split the text up accordingly.
                    $pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
                    $tagPos = preg_match($pattern, $row->intro_text);

                    if ($tagPos == 0) {
                        $introtext = $row->intro_text;
                    } else {
                        list($introtext, $fulltext) = preg_split($pattern, $row->intro_text, 2);
                    }
                    ?>
                    <td>
                        <?php echo $introtext; ?>
                    </td>
                    <?php
                }
                ?>

                <?php
                if ($this->show_permissions) {
                    ?>

                    <td class="align-top">
                        <?php if ($this->perms[$row->id]['view']): ?>
                        <span class="fa-solid fa-check cb-pubforms-perm-icon is-allowed" role="img" aria-label="<?php echo Text::_('JYES'); ?>"></span>
                    <?php else: ?>
                        <span class="fa-solid fa-xmark cb-pubforms-perm-icon is-denied" role="img" aria-label="<?php echo Text::_('JNO'); ?>"></span>
                    <?php endif; ?>
                    </td>

                    <?php
                }
                ?>

                <?php
                if ($this->show_permissions && $this->show_permissions_new) {
                    ?>

                    <td class="align-top">
                        <?php if ($this->perms[$row->id]['new']): ?>
                        <span class="fa-solid fa-check cb-pubforms-perm-icon is-allowed" role="img" aria-label="<?php echo Text::_('JYES'); ?>"></span>
                    <?php else: ?>
                        <span class="fa-solid fa-xmark cb-pubforms-perm-icon is-denied" role="img" aria-label="<?php echo Text::_('JNO'); ?>"></span>
                    <?php endif; ?>
                    </td>

                    <?php
                }
                ?>

                <?php
                if ($this->show_permissions && $this->show_permissions_edit) {
                    ?>

                    <td class="align-top">
                        <?php if ($this->perms[$row->id]['edit']): ?>
                        <span class="fa-solid fa-check cb-pubforms-perm-icon is-allowed" role="img" aria-label="<?php echo Text::_('JYES'); ?>"></span>
                    <?php else: ?>
                        <span class="fa-solid fa-xmark cb-pubforms-perm-icon is-denied" role="img" aria-label="<?php echo Text::_('JNO'); ?>"></span>
                    <?php endif; ?>
                    </td>

                    <?php
                }
                ?>
            </tr>
            <?php
            $k = 1 - $k;
        }

        if ($n === 0) {
            ?>
            <tbody>
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <span class="fa-solid fa-folder-open me-2" aria-hidden="true"></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </td>
                </tr>
            </tbody>
            <?php
        }

        $pages_links = $this->pagination->getPagesLinks();
        if ($pages_links) {
            ?>
            <tfoot>
                <tr>
                    <td colspan="9">
                        <?php echo $pages_links; ?>
                    </td>
                </tr>
            </tfoot>
            <?php
        }
        ?>

    </table>
    </div><?php /* .table-responsive */ ?>


    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="Itemid" value="<?php echo Factory::getApplication()->getInput()->getInt('Itemid', 0); ?>" />
    <input type="hidden" name="limitstart" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="view" id="view" value="publicforms" />
    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
