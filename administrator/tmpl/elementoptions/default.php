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

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;


$element = $this->element ?? null;
if (!is_object($element) || empty($element->id)) {
?>
    <div class="alert alert-danger">
        <?php echo Text::_('COM_CONTENTBUILDERNG_ERROR'); ?>: Invalid or missing `element_id`.
    </div>
<?php
    return;
}

$plugins = ContentbuilderLegacyHelper::getFormElementsPlugins();

$elementType = is_string($this->element->type) ? $this->element->type : '';
if ($elementType !== '') {
    \Joomla\CMS\Plugin\PluginHelper::importPlugin('contentbuilderng_form_elements', $elementType);
}

$dispatcher = Factory::getApplication()->getDispatcher();
$eventResult = $dispatcher->dispatch('onSettingsDisplay', new \Joomla\CMS\Event\GenericEvent('onSettingsDisplay', array($this->element->options ?? null)));
$results = $eventResult->getArgument('result') ?: [];
$dispatcher->clearListeners('onSettingsDisplay');

if (count($results)) {
    $results = $results[0];
}

$the_item = is_array($results) ? $results : [];
$is_plugin = false;

$renderCheckbox = static function (string $name, string $id, bool $checked = false, string $value = '1'): string {
    return '<span class="form-check d-inline-block mb-0"><input class="form-check-input" type="checkbox" name="'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
        . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
        . ($checked ? ' checked="checked"' : '') . ' /></span>';
};
?>
<style type="text/css">
    label {
        display: inline;
    }
</style>

<form action="index.php" method="post" name="adminForm" id="adminForm">

    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE'); ?>
    <select class="form-select-sm" name="type_selection"
        onchange="document.getElementById('type_change').value='1';">
        <option value="text" <?php echo $this->element->type == 'text' || $this->element->type == '' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'); ?>
        </option>
        <option value="textarea" <?php echo $this->element->type == 'textarea' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXTAREA'); ?>
        </option>
        <option value="checkboxgroup" <?php echo $this->element->type == 'checkboxgroup' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CHECKBOXGROUP'); ?>
        </option>
        <option value="radiogroup" <?php echo $this->element->type == 'radiogroup' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_RADIO'); ?>
        </option>
        <option value="select" <?php echo $this->element->type == 'select' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_SELECT'); ?>
        </option>
        <option value="upload" <?php echo $this->element->type == 'upload' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_UPLOAD'); ?>
        </option>
        <option value="calendar" <?php echo $this->element->type == 'calendar' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CALENDAR'); ?>
        </option>
        <option value="hidden" <?php echo $this->element->type == 'hidden' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_HIDDEN'); ?>
        </option>
        <option value="captcha" <?php echo $this->element->type == 'captcha' ? ' selected="selected"' : ''; ?>>
            <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CAPTCHA'); ?>
        </option>
        <?php
        foreach ($plugins as $plugin) {
        ?>
            <option value="<?php echo $plugin; ?>" <?php echo $this->element->type == $plugin ? ' selected="selected"' : ''; ?>>
                <?php echo $plugin; ?>
            </option>
        <?php
        }
        ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary" onclick="document.getElementById('task').value='elementoptions.save';">
        <?php echo Text::_('COM_CONTENTBUILDERNG_SAVE'); ?>
    </button>

    <hr />

    <div class="w-100">
        <?php

        // Démarrer les onglets
        echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => 'tab0']);
        // Premier onglet
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS'));
        ?>
        <h3>
            <?php echo htmlentities($this->element->label, ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <?php
        switch ($this->element->type) {
            case is_array($the_item) && in_array($this->element->type, $plugins):
                $is_plugin = true;
        ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo htmlentities($the_item['element_type'] ?? $this->element->type, ENT_QUOTES, 'UTF-8'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <?php
                        if (isset($the_item['has_hint']) && $the_item['has_hint']) {
                        ?>
                            <tr>
                                <td width="100" align="left" class="key">
                                    <label for="hint">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                    </label>
                                </td>
                                <td align="left">
                                    <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                        id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </table>
                    <?php
                    echo $the_item['settings'] ?? '';
                    ?>
                </fieldset>
                <input type="hidden" name="field_type" value="<?php echo $this->element->type; ?>" />
            <?php
                break;
            case 'captcha':
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CAPTCHA'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="captcha" />
            <?php
                break;
            case 'upload':
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_UPLOAD'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="allowed_file_extensions">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOWED_FILE_EXTENSIONS'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="allowed_file_extensions" id="allowed_file_extensions"
                                    value="<?php echo htmlentities(isset($this->element->options->allowed_file_extensions) && $this->element->options->allowed_file_extensions ? $this->element->options->allowed_file_extensions : 'zip, rar, 7z, pdf, doc, xls, ppt, jpg, jpeg, png, gif', ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="max_filesize">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAX_FILESIZE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="max_filesize"
                                    id="max_filesize"
                                    value="<?php echo htmlentities(isset($this->element->options->max_filesize) && $this->element->options->max_filesize ? $this->element->options->max_filesize : '2M', ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="upload_directory">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_UPLOAD_DIRECTORY'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="upload_directory" id="upload_directory"
                                    value="<?php echo htmlentities(isset($this->element->options->upload_directory) && $this->element->options->upload_directory ? $this->element->options->upload_directory : '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="upload" />
            <?php
                break;
            case 'checkboxgroup':
            case 'radiogroup':
            case 'select':
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo $this->element->type == 'checkboxgroup' ? Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CHECKBOXGROUP') : ($this->element->type == 'select' ? Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_SELECT') : Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_RADIO')); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <?php
                        $groupCnt = count($this->group_definition);
                        if ($groupCnt) {
                            $def = array();

                            if (!isset($this->element->options->seperator)) {
                                $this->element->options->seperator = '';
                            }

                            if (isset($this->element->default_value)) {

                                if ($this->element->options->seperator == '') {
                                    $def = explode(" ", $this->element->default_value);
                                } else {
                                    $def = explode($this->element->options->seperator, $this->element->default_value);
                                }
                            }

                        ?>

                            <tr>
                                <td width="100" align="left" class="key">
                                    <label>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                    </label>
                                </td>
                                <td align="left">
                                    <?php
                                    foreach ($this->group_definition as $key => $value) {
                                    ?>
                                        <?php
                                        $defaultValueId = 'default_value' . htmlentities($key, ENT_QUOTES, 'UTF-8');
                                        echo $renderCheckbox('default_value[]', $defaultValueId, in_array($key, $def), (string) $key);
                                        ?>
                                        <label for="default_value<?php echo htmlentities($key, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                        <br />
                                    <?php
                                    }
                                    ?>
                                </td>
                            </tr>

                        <?php
                        }

                        if ($this->element->type == 'select') {
                        ?>
                            <tr>
                                <td width="100" align="left" class="key">
                                    <label for="multiple">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MULTIPLE'); ?>:
                                    </label>
                                </td>
                                <td align="left">
                                    <?php echo $renderCheckbox('multiple', 'multiple', isset($this->element->options->multiple) && $this->element->options->multiple); ?>
                                </td>
                            </tr>
                            <tr>
                                <td width="100" align="left" class="key">
                                    <label for="length">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH'); ?>:
                                    </label>
                                </td>
                                <td align="left">
                                    <input class="form-control form-control-sm" style="width:95%;" type="text" name="length"
                                        id="length"
                                        value="<?php echo isset($this->element->options->length) ? $this->element->options->length : ''; ?>" />
                                </td>
                            </tr>
                        <?php
                        }
                        if ($this->element->type == 'checkboxgroup' || $this->element->type == 'radiogroup') {
                        ?>
                            <tr>
                                <td width="100" align="left" class="key">
                                    <label for="horizontal">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_HORIZONTAL'); ?>:
                                    </label>
                                </td>
                                <td align="left">
                                    <?php echo $renderCheckbox('horizontal', 'horizontal', isset($this->element->options->horizontal) && $this->element->options->horizontal); ?>
                                </td>
                            </tr>
                            <tr>
                                <td width="100" align="left" class="key">
                                    <label for="horizontal_length">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_HORIZONTAL_LENGTH'); ?>:
                                    </label>
                                </td>
                                <td align="left">
                                    <input class="form-control form-control-sm" style="width: 95%" type="text"
                                        name="horizontal_length" id="horizontal_length"
                                        value="<?php echo isset($this->element->options->horizontal_length) ? $this->element->options->horizontal_length : ''; ?>" />
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="class">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="class"
                                    id="class"
                                    value="<?php echo isset($this->element->options->class) ? htmlentities($this->element->options->class, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="seperator">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_SEPERATOR'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="seperator"
                                    id="seperator"
                                    value="<?php echo isset($this->element->options->seperator) ? htmlentities($this->element->options->seperator, ENT_QUOTES, 'UTF-8') : ','; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding" value="0"
                                    <?php echo (!isset($this->element->options->allow_html) || !$this->element->options->allow_html) && (!isset($this->element->options->allow_raw) || !$this->element->options->allow_raw) ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_ALL'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding1"
                                    value="1" <?php echo isset($this->element->options->allow_html) && $this->element->options->allow_html ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding1">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_HTML'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding2"
                                    value="2" <?php echo isset($this->element->options->allow_raw) && $this->element->options->allow_raw ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding2">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_RAW'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="<?php echo $this->element->type; ?>" />
            <?php
                break;
            case 'textarea':
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXTAREA'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="default_value">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width: 95%; height: 100px;" name="default_value"
                                    id="default_value"><?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="width">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_WIDTH'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="width"
                                    id="width"
                                    value="<?php echo isset($this->element->options->width) ? $this->element->options->width : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="height">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_HEIGHT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="height"
                                    id="height"
                                    value="<?php echo isset($this->element->options->height) ? $this->element->options->height : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="maxlength">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="maxlength"
                                    id="maxlength"
                                    value="<?php echo isset($this->element->options->maxlength) ? $this->element->options->maxlength : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="class">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="class"
                                    id="class"
                                    value="<?php echo isset($this->element->options->class) ? htmlentities($this->element->options->class, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="readonly">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY'); ?>:
                                </label>
                                </td>
                                <td align="left">
                                    <?php echo $renderCheckbox('readonly', 'readonly', isset($this->element->options->readonly) && intval($this->element->options->readonly)); ?>
                                </td>
                            </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding" value="0"
                                    <?php echo (!isset($this->element->options->allow_html) || !$this->element->options->allow_html) && (!isset($this->element->options->allow_raw) || !$this->element->options->allow_raw) ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_ALL'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding1"
                                    value="1" <?php echo isset($this->element->options->allow_html) && $this->element->options->allow_html ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding1">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_HTML'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding2"
                                    value="2" <?php echo isset($this->element->options->allow_raw) && $this->element->options->allow_raw ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding2">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_RAW'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="textarea" />
            <?php
                break;
            case 'calendar':
            ?>

                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CALENDAR'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="default_value">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="default_value"
                                    id="default_value"
                                    value="<?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="length">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="length"
                                    id="length"
                                    value="<?php echo isset($this->element->options->length) ? $this->element->options->length : '100%'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="maxlength">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="maxlength"
                                    id="maxlength"
                                    value="<?php echo isset($this->element->options->maxlength) ? $this->element->options->maxlength : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="format">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_FORMAT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="format"
                                    id="format"
                                    value="<?php echo isset($this->element->options->format) ? $this->element->options->format : '%Y-%m-%d'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="transfer_format">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_TRANSFER_FORMAT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="transfer_format" id="transfer_format"
                                    value="<?php echo isset($this->element->options->transfer_format) ? $this->element->options->transfer_format : 'YYYY-mm-dd'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="readonly">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY'); ?>:
                                </label>
                                </td>
                                <td align="left">
                                    <?php echo $renderCheckbox('readonly', 'readonly', isset($this->element->options->readonly) && intval($this->element->options->readonly)); ?>
                                </td>
                            </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="calendar" />
            <?php
                break;
            case '':
            case 'text':
            ?>

                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="default_value">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="default_value"
                                    id="default_value"
                                    value="<?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="length">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="length"
                                    id="length"
                                    value="<?php echo isset($this->element->options->length) ? $this->element->options->length : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="maxlength">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="maxlength"
                                    id="maxlength"
                                    value="<?php echo isset($this->element->options->maxlength) ? $this->element->options->maxlength : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="class">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="class"
                                    id="class"
                                    value="<?php echo isset($this->element->options->class) ? htmlentities($this->element->options->class, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="password">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_PASSWORD'); ?>:
                                </label>
                                </td>
                                <td align="left">
                                    <?php echo $renderCheckbox('password', 'password', isset($this->element->options->password) && intval($this->element->options->password)); ?>
                                </td>
                            </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="readonly">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY'); ?>:
                                </label>
                                </td>
                                <td align="left">
                                    <?php echo $renderCheckbox('readonly', 'readonly', isset($this->element->options->readonly) && intval($this->element->options->readonly)); ?>
                                </td>
                            </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding" value="0"
                                    <?php echo (!isset($this->element->options->allow_html) || !$this->element->options->allow_html) && (!isset($this->element->options->allow_raw) || !$this->element->options->allow_raw) ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_ALL'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding1"
                                    value="1" <?php echo isset($this->element->options->allow_html) && $this->element->options->allow_html ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding1">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_HTML'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding2"
                                    value="2" <?php echo isset($this->element->options->allow_raw) && $this->element->options->allow_raw ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding2">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_RAW'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="text" />
            <?php
                break;
            case 'hidden':
            ?>

                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_HIDDEN'); ?>
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="default_value">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="default_value"
                                    id="default_value"
                                    value="<?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding" value="0"
                                    <?php echo (!isset($this->element->options->allow_html) || !$this->element->options->allow_html) && (!isset($this->element->options->allow_raw) || !$this->element->options->allow_raw) ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_ALL'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding1"
                                    value="1" <?php echo isset($this->element->options->allow_html) && $this->element->options->allow_html ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding1">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_HTML'); ?>
                                </label>
                                <input class="form-check-input" type="radio" name="allow_encoding" id="allow_encoding2"
                                    value="2" <?php echo isset($this->element->options->allow_raw) && $this->element->options->allow_raw ? ' checked="checked"' : ''; ?> /> <label
                                    for="allow_encoding2">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_RAW'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <input type="hidden" name="field_type" value="hidden" />
            <?php
                break;
        }

        echo HTMLHelper::_('uitab.endTab');
        if ($this->element->type != 'captcha') {
            echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_SCRIPTS'));
            ?>
            <h3>
                <?php echo htmlentities($this->element->label, ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <?php
            if (($is_plugin && !empty($the_item['show_validation_settings'])) || !$is_plugin) {
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_VALIDATION'); ?> (PHP)
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="validation_message">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_VALIDATION_MESSAGE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="validation_message" id="validation_message"
                                    value="<?php echo htmlentities((string) ($this->element->validation_message ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="validations">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_SELECT_VALIDATIONS'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <select class="form-select-sm" style="width: 95%;height: 100px;" multiple="multiple"
                                    name="validations[]" id="validations">
                                    <?php
                                    $selected_validations = explode(',', (string) ($this->element->validations ?? ''));
                                    foreach ($this->validations as $validation) {
                                    ?>
                                        <option <?php echo in_array($validation, $selected_validations) ? 'selected="selected" ' : ''; ?>value="<?php echo htmlentities($validation, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlentities($validation, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="custom_validation_script">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_VALIDATION_CODE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <?php
                                $params = array('syntax' => 'php');
                                $editor = Editor::getInstance('codemirror');
                                echo $editor->display("custom_validation_script", $this->element->custom_validation_script, '100%', '550', '75', '20', false, null, null, null, $params);
                                ?>
                            </td>
                        </tr>
                    </table>
                </fieldset>
            <?php
            }

            if (($is_plugin && !empty($the_item['show_init_code_settings'])) || !$is_plugin) {
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_INIT'); ?> (JS)
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="custom_init_script">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_INIT_CODE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <?php
                                $params = array('syntax' => 'javascript');
                                $editor = Editor::getInstance('codemirror');
                                echo $editor->display("custom_init_script", $this->element->custom_init_script, '100%', '550', '75', '20', false, null, null, null, $params);
                                ?>
                            </td>
                        </tr>
                    </table>
                </fieldset>
            <?php
            }
            if (($is_plugin && !empty($the_item['show_action_code_settings'])) || !$is_plugin) {
            ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_ACTION'); ?> (PHP)
                    </legend>
                    <table class="admintable" width="95%">
                        <tr>
                            <td width="100" align="left" class="key">
                                <label for="custom_action_script">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_ACTION_CODE'); ?>:
                                </label>
                            </td>
                            <td align="left">
                                <?php
                                $params = array('syntax' => 'php');
                                $editor = Editor::getInstance('codemirror');
                                echo $editor->display("custom_action_script", $this->element->custom_action_script, '100%', '550', '75', '20', false, null, null, null, $params);
                                ?>
                            </td>
                        </tr>
                    </table>
                </fieldset>
            <?php
            }
            ?>
        <?php
            echo HTMLHelper::_('uitab.endTab');
        }
        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    </div>


    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="view" value="elementoptions" />
    <input type="hidden" name="task" id="task" value="" />
    <input type="hidden" name="type_change" id="type_change" value="0" />
    <input type="hidden" name="id" value="<?php echo $this->element->form_id; ?>" />
    <input type="hidden" name="element_id" value="<?php echo $this->element->id; ?>" />
    <input type="hidden" name="tmpl" value="component" />
    <input type="hidden" name="tabStartOffset" value="0" />
    <input type="hidden" name="ordering" value="<?php echo $this->element->ordering; ?>" />
    <input type="hidden" name="published" value="<?php echo $this->element->published; ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
