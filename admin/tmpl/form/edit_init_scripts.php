<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$cbFormEditConfig = [
    'formId' => (int) ($this->item->id ?? 0),
    'debugModeEnabled' => !empty($this->item->debug_mode),
    'isBreezingFormsType' => $isBreezingFormsType,
    'breezingFormsEditableToken' => $breezingFormsEditableToken,
    'limitstart' => \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('limitstart', 0),
    'text' => [
        'columns' => Text::_('COM_CONTENTBUILDERNG_COLUMNS'),
        'typeEditEnableBfConfirm' => Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT_ENABLE_BF_CONFIRM'),
        'formNotFound' => Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'),
        'saveFailed' => Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'),
        'confirmCloseUnsaved' => Text::_('COM_CONTENTBUILDERNG_CONFIRM_CLOSE_UNSAVED'),
        'unnamed' => Text::_('COM_CONTENTBUILDERNG_UNNAMED'),
        'inheritedFrom' => Text::_('COM_CONTENTBUILDERNG_INHERITED_FROM'),
        'errorEnterFormname' => Text::_('COM_CONTENTBUILDERNG_ERROR_ENTER_FORMNAME'),
        'errorEnterFormnameAll' => Text::_('COM_CONTENTBUILDERNG_ERROR_ENTER_FORMNAME_ALL'),
        'initialiseOverwriteConfirm' => Text::_('COM_CONTENTBUILDERNG_INITIALISE_OVERWRITE_CONFIRM'),
    ],
];

$cbFormEditInitScriptPath = JPATH_ROOT . '/media/com_contentbuilderng/js/form-edit-init.js';
$cbFormEditInitScriptVersion = is_file($cbFormEditInitScriptPath) ? (string) filemtime($cbFormEditInitScriptPath) : '1';
?>

<script>
    window.cbFormEditConfig = <?php echo json_encode($cbFormEditConfig, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(Uri::root(true) . '/media/com_contentbuilderng/js/form-edit-init.js?' . $cbFormEditInitScriptVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php if ($isBreezingFormsType && (int) ($this->item->id ?? 0) > 0) : ?>
<?php require __DIR__ . '/bf_system_fields_modal_scripts.php'; ?>
<?php endif; ?>
