<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */



// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$app = Factory::getApplication();
$formId = $app->getInput()->getInt('form_id', 0);

$renderCheckbox = static function (string $name, string $id, bool $checked = false): string {
    return '<span class="form-check d-inline-block mb-0"><input class="form-check-input" type="checkbox" name="'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
        . '" value="1"' . ($checked ? ' checked="checked"' : '') . ' /></span>';
};

?>
<style type="text/css">
    label {
        display: inline;
    }
</style>

<div style="float:right" class="mb-3">
    <input type="button" class="btn btn-sm btn-primary w-25" value="<?php echo Text::_('COM_CONTENTBUILDERNG_SAVE'); ?>"
        onclick="document.adminForm.task.value='user.save';document.adminForm.submit();" />
    <input type="button" class="btn btn-sm btn-primary w-25"
        value="<?php echo Text::_('COM_CONTENTBUILDERNG_CANCEL'); ?>"
        onclick="document.adminForm.task.value='user.cancel';document.adminForm.submit();" />
</div>

<form action="index.php" method="post" name="adminForm" id="adminForm">
    <div style="clear:both;"></div>

    <div class="w-100">
        <table class="table table-striped">
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($this->subject->id, ENT_QUOTES, 'UTF-8'); ?>
                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($this->subject->name, ENT_QUOTES, 'UTF-8'); ?>
                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_USERNAME'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($this->subject->username, ENT_QUOTES, 'UTF-8'); ?>
                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <label for="limit_add">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMIT_ADD'); ?>:
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-25" id="limit_add" name="limit_add" type="text"
                        value="<?php echo $this->subject->limit_add; ?>" />
                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <label for="limit_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMIT_EDIT'); ?>:
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-25" id="limit_edit" name="limit_edit" type="text"
                        value="<?php echo $this->subject->limit_edit; ?>" />
                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <label for="verification_date_view">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DATE_VIEW'); ?>:
                    </label>
                </td>
                <td>
                    <?php
                    $calAttr = [
                        'class' => 'verification_date_view',
                        'showTime' => true,
                        'timeFormat' => '24',
                        'singleHeader' => false,
                        'todayBtn' => true,
                        'weekNumbers' => true,
                        'minYear' => '',
                        'maxYear' => '',
                        'firstDay' => '1',
                    ];

                    echo HTMLHelper::_('calendar', $this->subject->verification_date_view, 'verification_date_view', 'verification_date_view', '%Y-%m-%d %H:%M:00', $calAttr);

                    ?>
                    <?php echo $renderCheckbox('verified_view', 'verified_view', (bool) $this->subject->verified_view); ?> <label class="form-check-label" for="verified_view">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_VERIFIED_VIEW'); ?>
                    </label>

                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <label for="verification_date_new">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DATE_NEW'); ?>:
                    </label>
                </td>
                <td>

                    <?php
                    $calAttr = [
                        'class' => 'verification_date_new',
                        'showTime' => true,
                        'timeFormat' => '24',
                        'singleHeader' => false,
                        'todayBtn' => true,
                        'weekNumbers' => true,
                        'minYear' => '',
                        'maxYear' => '',
                        'firstDay' => '1',
                    ];

                    echo HTMLHelper::_('calendar', $this->subject->verification_date_new, 'verification_date_new', 'verification_date_new', '%Y-%m-%d %H:%M:00', $calAttr);
                    ?>

                    <?php echo $renderCheckbox('verified_new', 'verified_new', (bool) $this->subject->verified_new); ?> <label class="form-check-label" for="verified_new">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_VERIFIED_NEW'); ?>
                    </label>

                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <label for="verification_date_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DATE_EDIT'); ?>:
                    </label>
                </td>
                <td>

                    <?php
                    $calAttr = [
                        'class' => 'verification_date_edit',
                        'showTime' => true,
                        'timeFormat' => '24',
                        'singleHeader' => false,
                        'todayBtn' => true,
                        'weekNumbers' => true,
                        'minYear' => '',
                        'maxYear' => '',
                        'firstDay' => '1',
                    ];

                    echo HTMLHelper::_('calendar', $this->subject->verification_date_edit, 'verification_date_edit', 'verification_date_edit', '%Y-%m-%d %H:%M:00', $calAttr);
                    ?>

                    <?php echo $renderCheckbox('verified_edit', 'verified_edit', (bool) $this->subject->verified_edit); ?> <label class="form-check-label" for="verified_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_VERIFIED_EDIT'); ?>
                    </label>

                </td>
            </tr>
            <tr class="row0">
                <td class="key text-end" style="width: 20%;">
                    <label for="published">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'); ?>
                    </label>
                </td>
                <td>
                    <?php echo $renderCheckbox('published', 'published', (bool) $this->subject->published); ?>

                </td>
            </tr>
        </table>
    </div>


    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="task" id="task" value="" />
    <input type="hidden" name="form_id" value="<?php echo $formId; ?>" />
    <input type="hidden" name="joomla_userid" value="<?php echo $this->subject->id; ?>" />
    <input type="hidden" name="cb_id" value="<?php echo $this->subject->cb_id; ?>" />
    <input type="hidden" name="tmpl" value="<?php echo $app->getInput()->getCmd('tmpl', ''); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
