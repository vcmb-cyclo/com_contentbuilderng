<?php
/**
 * @package     ContentBuilder NG
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$form = $displayData['form'] ?? null;

if (!$form) {
    return;
}
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <h3 id="cb-form-list-intro-text" class="mb-0">
        <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INTRO_MODE_TITLE'); ?>
    </h3>
    <button
        type="button"
        class="btn btn-secondary"
        id="cb-reset-list-intro"
        title="<?php echo Text::_('COM_CONTENTBUILDERNG_RESET_LIST_INTRO_TOOLTIP'); ?>"
        aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_RESET_LIST_INTRO_TOOLTIP'); ?>"
        data-confirm="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_RESET_LIST_INTRO_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
        <?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>
    </button>
</div>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INTRO_MODE_INTRO'); ?>
</p>
<?php echo $form->renderField('intro_text'); ?>
