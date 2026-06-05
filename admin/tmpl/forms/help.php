<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="container-fluid p-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_TITLE'); ?></h1>
    <p class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_INTRO'); ?></p>
    <ul class="mb-3">
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_POINT_1'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_POINT_2'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_POINT_3'); ?></li>
    </ul>
    <a class="btn btn-primary btn-sm" href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=forms'); ?>">
        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_BACK_TO_VIEWS'); ?>
    </a>
</div>
