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
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;


$element = $this->element ?? null;
if (!is_object($element) || empty($element->id)) {
?>
    <div class="alert alert-danger">
        <?php echo Text::_('COM_CONTENTBUILDERNG_ERROR'); ?>: Invalid or missing `element_id`.
    </div>
<?php
    return;
}

$plugins = (new FormSupportService())->getFormElementsPlugins();

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

$renderTooltipLabel = static function (string $for, string $labelKey, ?string $tipKey = null): string {
    $label = Text::_($labelKey);

    if ($tipKey === null) {
        return '<label for="' . htmlspecialchars($for, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</label>';
    }

    $tip = Text::_($tipKey);

    return '<label for="' . htmlspecialchars($for, ENT_QUOTES, 'UTF-8') . '" class="cb-tooltip-label editlinktip hasTip"'
        . ' tabindex="0"'
        . ' aria-label="' . htmlspecialchars($label . ': ' . $tip, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-bs-toggle="tooltip" data-bs-placement="top"'
        . ' title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-bs-title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . ':</label>';
};

$typeIconMap = [
    'text' => 'fa-solid fa-font',
    'textarea' => 'fa-solid fa-align-left',
    'checkboxgroup' => 'fa-regular fa-square-check',
    'radiogroup' => 'fa-regular fa-circle-dot',
    'select' => 'fa-solid fa-list',
    'upload' => 'fa-solid fa-upload',
    'calendar' => 'fa-regular fa-calendar',
    'hidden' => 'fa-solid fa-eye-slash',
    'captcha' => 'fa-solid fa-shield-halved',
];
?>
<style type="text/css">
    label { display: inline; }
    .cb-elementoptions-shell{padding:.85rem .95rem 1rem;background:linear-gradient(180deg,var(--bs-body-bg),var(--bs-tertiary-bg));border:1px solid var(--bs-border-color);border-radius:1rem}
    .cb-elementoptions-toolbar{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.8rem;padding:.65rem .75rem;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:.9rem;box-shadow:0 .2rem .65rem rgba(16,24,40,.06)}
    .cb-elementoptions-toolbar .form-select{min-width:220px;max-width:360px}
    .cb-elementoptions-title{margin:0;font-size:1rem;font-weight:700;color:var(--bs-emphasis-color)}
    .cb-elementoptions-type-label{margin:0 0 0 .2rem;color:var(--bs-secondary-color);font-size:.82rem;font-weight:600;white-space:nowrap}
    .cb-elementoptions-shell fieldset legend{padding:0 .35rem;margin-bottom:.85rem;font-size:.96rem;font-weight:700}
    .cb-elementoptions-shell .admintable{width:100%!important;margin:0}
    .cb-elementoptions-shell .admintable td{padding:.45rem .4rem;vertical-align:top}
    .cb-elementoptions-shell .admintable td.key{width:170px;color:var(--bs-emphasis-color);font-weight:600}
    .cb-elementoptions-shell textarea.form-control{min-height:110px}
    .cb-elementoptions-shell .form-check-input{margin-right:.35rem}
    .cb-elementoptions-shell .cb-inline-grid{display:grid;grid-template-columns:170px minmax(0,1fr) 170px minmax(0,1fr);gap:.45rem .7rem;align-items:center}
    .cb-elementoptions-shell .cb-inline-grid label{font-weight:600;color:var(--bs-emphasis-color)}
    .cb-elementoptions-shell .cb-inline-flags{display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap}
    .cb-validation-help{margin-top:.45rem;color:var(--bs-secondary-color);font-size:.82rem}
    .cb-type-picker{position:relative;min-width:220px;max-width:360px}
    .cb-type-picker-button{display:flex;align-items:center;justify-content:space-between;gap:.7rem;width:100%;padding:.375rem .75rem}
    .cb-type-picker-current{display:inline-flex;align-items:center;gap:.55rem;min-width:0}
    .cb-type-picker-current-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .cb-type-picker-menu{position:absolute;top:calc(100% + .35rem);left:0;right:0;z-index:1080;display:none;min-width:100%;max-height:18rem;overflow:auto;padding:.35rem;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:.75rem;box-shadow:0 .65rem 1.6rem rgba(16,24,40,.18)}
    .cb-type-picker.is-open .cb-type-picker-menu{display:block}
    .cb-type-picker-option{display:flex;align-items:center;gap:.55rem;width:100%;padding:.45rem .65rem;border:0;border-radius:.55rem;background:transparent;color:var(--bs-body-color);text-align:left}
    .cb-type-picker-option:hover,
    .cb-type-picker-option:focus{background:var(--bs-tertiary-bg);outline:0}
    .cb-type-picker-option.is-active{background:var(--bs-secondary-bg);font-weight:600}
    .cb-type-picker-icon{display:inline-flex;align-items:center;justify-content:center;width:1.25rem;color:var(--bs-emphasis-color);flex:0 0 1.25rem}
    .cb-type-picker-caret{margin-left:auto}
    .cb-tooltip-label{display:inline-flex;align-items:center;cursor:help}
    .cb-tooltip-label:focus{outline:0;box-shadow:0 0 0 .15rem rgba(var(--bs-primary-rgb),.25);border-radius:.25rem}
    @media (max-width:767.98px){
        .cb-elementoptions-shell{padding:.8rem}
        .cb-elementoptions-toolbar{align-items:stretch}
        .cb-elementoptions-toolbar .form-select,
        .cb-elementoptions-toolbar .btn,
        .cb-type-picker{width:100%;max-width:none}
        .cb-elementoptions-type-label{margin-left:0}
        .cb-elementoptions-shell .admintable td,
        .cb-elementoptions-shell .admintable td.key{display:block;width:100%!important;padding:.3rem 0}
        .cb-elementoptions-shell .cb-inline-grid{grid-template-columns:1fr}
    }
</style>

<form action="index.php" method="post" name="adminForm" id="adminForm">
    <div class="cb-elementoptions-shell">
        <div class="cb-elementoptions-toolbar">
            <p class="cb-elementoptions-title me-auto"><?php echo htmlentities($this->element->label, ENT_QUOTES, 'UTF-8'); ?></p>
            <label class="cb-elementoptions-type-label" for="type_selection"><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE'); ?></label>
            <select class="form-select form-select-sm" name="type_selection"
                id="type_selection"
                onchange="document.getElementById('type_change').value='1';">
                <option value="text" data-icon="<?php echo $typeIconMap['text']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'text' || $this->element->type == '' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'); ?>
                </option>
                <option value="textarea" data-icon="<?php echo $typeIconMap['textarea']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXTAREA'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'textarea' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXTAREA'); ?>
                </option>
                <option value="checkboxgroup" data-icon="<?php echo $typeIconMap['checkboxgroup']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CHECKBOXGROUP'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'checkboxgroup' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CHECKBOXGROUP'); ?>
                </option>
                <option value="radiogroup" data-icon="<?php echo $typeIconMap['radiogroup']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_RADIO'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'radiogroup' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_RADIO'); ?>
                </option>
                <option value="select" data-icon="<?php echo $typeIconMap['select']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_SELECT'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'select' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_SELECT'); ?>
                </option>
                <option value="upload" data-icon="<?php echo $typeIconMap['upload']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_UPLOAD'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'upload' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_UPLOAD'); ?>
                </option>
                <option value="calendar" data-icon="<?php echo $typeIconMap['calendar']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CALENDAR'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'calendar' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CALENDAR'); ?>
                </option>
                <option value="hidden" data-icon="<?php echo $typeIconMap['hidden']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_HIDDEN'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'hidden' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_HIDDEN'); ?>
                </option>
                <option value="captcha" data-icon="<?php echo $typeIconMap['captcha']; ?>" data-type-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CAPTCHA'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == 'captcha' ? ' selected="selected"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CAPTCHA'); ?>
                </option>
                <?php
                foreach ($plugins as $plugin) {
                ?>
                    <option value="<?php echo $plugin; ?>" data-icon="fa-solid fa-puzzle-piece" data-type-label="<?php echo htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->element->type == $plugin ? ' selected="selected"' : ''; ?>>
                        <?php echo $plugin; ?>
                    </option>
                <?php
                }
                ?>
            </select>
            <div class="cb-type-picker d-none" id="type_selection_picker">
                <button class="btn btn-sm btn-outline-secondary cb-type-picker-button" type="button" id="type_selection_picker_button" aria-expanded="false" aria-haspopup="listbox">
                    <span class="cb-type-picker-current">
                        <span class="cb-type-picker-icon" id="type_selection_picker_icon" aria-hidden="true">
                            <span class="fa-solid fa-font" aria-hidden="true"></span>
                        </span>
                        <span class="cb-type-picker-current-label" id="type_selection_picker_label"></span>
                    </span>
                    <span class="cb-type-picker-caret fa-solid fa-chevron-down" aria-hidden="true"></span>
                </button>
                <div class="cb-type-picker-menu" id="type_selection_picker_menu" role="listbox" aria-labelledby="type_selection_picker_button">
                    <button class="cb-type-picker-option" type="button" data-value="text" data-icon="<?php echo htmlspecialchars($typeIconMap['text'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['text'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="textarea" data-icon="<?php echo htmlspecialchars($typeIconMap['textarea'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXTAREA'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['textarea'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXTAREA'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="checkboxgroup" data-icon="<?php echo htmlspecialchars($typeIconMap['checkboxgroup'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CHECKBOXGROUP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['checkboxgroup'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CHECKBOXGROUP'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="radiogroup" data-icon="<?php echo htmlspecialchars($typeIconMap['radiogroup'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_RADIO'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['radiogroup'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_RADIO'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="select" data-icon="<?php echo htmlspecialchars($typeIconMap['select'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_SELECT'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['select'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_SELECT'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="upload" data-icon="<?php echo htmlspecialchars($typeIconMap['upload'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_UPLOAD'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['upload'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_UPLOAD'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="calendar" data-icon="<?php echo htmlspecialchars($typeIconMap['calendar'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CALENDAR'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['calendar'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CALENDAR'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="hidden" data-icon="<?php echo htmlspecialchars($typeIconMap['hidden'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_HIDDEN'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['hidden'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_HIDDEN'); ?></span>
                    </button>
                    <button class="cb-type-picker-option" type="button" data-value="captcha" data-icon="<?php echo htmlspecialchars($typeIconMap['captcha'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CAPTCHA'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cb-type-picker-icon" aria-hidden="true"><span class="<?php echo htmlspecialchars($typeIconMap['captcha'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_CAPTCHA'); ?></span>
                    </button>
                    <?php
                    foreach ($plugins as $plugin) {
                    ?>
                        <button class="cb-type-picker-option" type="button" data-value="<?php echo htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8'); ?>" data-icon="fa-solid fa-puzzle-piece" data-label="<?php echo htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cb-type-picker-icon" aria-hidden="true"><span class="fa-solid fa-puzzle-piece" aria-hidden="true"></span></span>
                            <span><?php echo htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                    <?php
                    }
                    ?>
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-primary" onclick="document.getElementById('task').value='elementoptions.save';">
                <span class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_SAVE'); ?>
            </button>
        </div>

        <div class="w-100">
        <?php

        // Démarrer les onglets
        echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => 'tab0']);
        // Premier onglet
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', Text::_('COM_CONTENTBUILDERNG_BULK_OPTIONS'));
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
                                <td class="key text-start" style="width: 100px;">
                                    <label for="hint">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                    </label>
                                </td>
                                <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="allowed_file_extensions">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOWED_FILE_EXTENSIONS'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="allowed_file_extensions" id="allowed_file_extensions"
                                    value="<?php echo htmlentities(isset($this->element->options->allowed_file_extensions) && $this->element->options->allowed_file_extensions ? $this->element->options->allowed_file_extensions : 'zip, rar, 7z, pdf, doc, xls, ppt, jpg, jpeg, png, gif', ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="max_filesize">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAX_FILESIZE'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="max_filesize"
                                    id="max_filesize"
                                    value="<?php echo htmlentities(isset($this->element->options->max_filesize) && $this->element->options->max_filesize ? $this->element->options->max_filesize : '2M', ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="upload_directory">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_UPLOAD_DIRECTORY'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="hint">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_HINT'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
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
                                <td class="key text-start" style="width: 100px;">
                                    <label>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                    </label>
                                </td>
                                <td class="text-start">
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
                                <td class="key text-start" style="width: 100px;">
                                    <label for="multiple">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MULTIPLE'); ?>:
                                    </label>
                                </td>
                                <td class="text-start">
                                    <?php echo $renderCheckbox('multiple', 'multiple', isset($this->element->options->multiple) && $this->element->options->multiple); ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="key text-start" style="width: 100px;">
                                    <label for="length">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH'); ?>:
                                    </label>
                                </td>
                                <td class="text-start">
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
                                <td class="key text-start" style="width: 100px;">
                                    <label for="horizontal">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_HORIZONTAL'); ?>:
                                    </label>
                                </td>
                                <td class="text-start">
                                    <?php echo $renderCheckbox('horizontal', 'horizontal', isset($this->element->options->horizontal) && $this->element->options->horizontal); ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="key text-start" style="width: 100px;">
                                    <label for="horizontal_length">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_HORIZONTAL_LENGTH'); ?>:
                                    </label>
                                </td>
                                <td class="text-start">
                                    <input class="form-control form-control-sm" style="width: 95%" type="text"
                                        name="horizontal_length" id="horizontal_length"
                                        value="<?php echo isset($this->element->options->horizontal_length) ? $this->element->options->horizontal_length : ''; ?>" />
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('class', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="class"
                                    id="class"
                                    value="<?php echo isset($this->element->options->class) ? htmlentities($this->element->options->class, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="seperator">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_SEPERATOR'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="seperator"
                                    id="seperator"
                                    value="<?php echo isset($this->element->options->seperator) ? htmlentities($this->element->options->seperator, ENT_QUOTES, 'UTF-8') : ','; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('allow_encoding', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING_TIP'); ?>
                            </td>
                            <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('default_value', 'COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE', 'COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <textarea class="form-control" style="width: 95%; height: 100px;" name="default_value"
                                    id="default_value"><?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('hint', 'COM_CONTENTBUILDERNG_ELEMENT_HINT', 'COM_CONTENTBUILDERNG_ELEMENT_HINT_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="width">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_WIDTH'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="width"
                                    id="width"
                                    value="<?php echo isset($this->element->options->width) ? $this->element->options->width : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="height">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_HEIGHT'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="height"
                                    id="height"
                                    value="<?php echo isset($this->element->options->height) ? $this->element->options->height : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="maxlength">
                                    <?php echo $renderTooltipLabel('maxlength', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH_TIP'); ?>
                                </td>
                                <td class="text-start">
                                    <input class="form-control form-control-sm" style="width:95%;" type="text" name="maxlength"
                                    id="maxlength"
                                    value="<?php echo isset($this->element->options->maxlength) ? $this->element->options->maxlength : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('class', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="class"
                                    id="class"
                                    value="<?php echo isset($this->element->options->class) ? htmlentities($this->element->options->class, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('readonly', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY_TIP'); ?>
                                </td>
                                <td class="text-start">
                                    <?php echo $renderCheckbox('readonly', 'readonly', isset($this->element->options->readonly) && intval($this->element->options->readonly)); ?>
                                </td>
                            </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('allow_encoding', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING_TIP'); ?>
                            </td>
                            <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('default_value', 'COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE', 'COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="default_value"
                                    id="default_value"
                                    value="<?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('hint', 'COM_CONTENTBUILDERNG_ELEMENT_HINT', 'COM_CONTENTBUILDERNG_ELEMENT_HINT_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('length', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="length"
                                    id="length"
                                    value="<?php echo isset($this->element->options->length) ? $this->element->options->length : '100%'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('maxlength', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="maxlength"
                                    id="maxlength"
                                    value="<?php echo isset($this->element->options->maxlength) ? $this->element->options->maxlength : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="format">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_FORMAT'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="format"
                                    id="format"
                                    value="<?php echo isset($this->element->options->format) ? $this->element->options->format : '%Y-%m-%d'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('transfer_format', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_TRANSFER_FORMAT', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_TRANSFER_FORMAT_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="transfer_format" id="transfer_format"
                                    value="<?php echo isset($this->element->options->transfer_format) ? $this->element->options->transfer_format : 'YYYY-mm-dd'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('readonly', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY_TIP'); ?>
                                </td>
                                <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('default_value', 'COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE', 'COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="default_value"
                                    id="default_value"
                                    value="<?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('hint', 'COM_CONTENTBUILDERNG_ELEMENT_HINT', 'COM_CONTENTBUILDERNG_ELEMENT_HINT_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <textarea class="form-control" style="width:95%;height:100px;" name="hint"
                                    id="hint"><?php echo isset($this->element->hint) ? htmlentities($this->element->hint, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-start">
                                <div class="cb-inline-grid">
                                    <?php echo $renderTooltipLabel('length', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_LENGTH_TIP'); ?>
                                    <input class="form-control form-control-sm" type="text" name="length"
                                        id="length"
                                        value="<?php echo isset($this->element->options->length) ? $this->element->options->length : ''; ?>" />
                                    <?php echo $renderTooltipLabel('maxlength', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_MAXLENGTH_TIP'); ?>
                                    <input class="form-control form-control-sm" type="text" name="maxlength"
                                        id="maxlength"
                                        value="<?php echo isset($this->element->options->maxlength) ? $this->element->options->maxlength : ''; ?>" />
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('class', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_CLASS_TIP'); ?>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="class"
                                    id="class"
                                    value="<?php echo isset($this->element->options->class) ? htmlentities($this->element->options->class, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-start">
                                <div class="cb-inline-flags">
                                    <span>
                                        <?php echo $renderTooltipLabel('password', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_PASSWORD', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_PASSWORD_TIP'); ?>
                                        <?php echo $renderCheckbox('password', 'password', isset($this->element->options->password) && intval($this->element->options->password)); ?>
                                    </span>
                                    <span>
                                        <?php echo $renderTooltipLabel('readonly', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_READONLY_TIP'); ?>
                                        <?php echo $renderCheckbox('readonly', 'readonly', isset($this->element->options->readonly) && intval($this->element->options->readonly)); ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <?php echo $renderTooltipLabel('allow_encoding', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING', 'COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING_TIP'); ?>
                            </td>
                            <td class="text-start">
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="default_value">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_DEFAULT_VALUE'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text" name="default_value"
                                    id="default_value"
                                    value="<?php echo isset($this->element->default_value) ? htmlentities($this->element->default_value, ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="allow_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_ALLOW_ENCODING'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
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
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', Text::_('COM_CONTENTBUILDERNG_LIST_ITEM_WRAPPER'));
        ?>
        <h3>
            <?php echo htmlentities($this->element->label, ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <fieldset class="border rounded p-3 mb-3">
            <legend><?php echo Text::_('COM_CONTENTBUILDERNG_LIST_ITEM_WRAPPER'); ?></legend>
            <div class="mb-0">
                <label class="form-label" for="item_wrapper"><?php echo Text::_('COM_CONTENTBUILDERNG_LIST_ITEM_WRAPPER'); ?></label>
                <textarea class="form-control" name="item_wrapper" id="item_wrapper" rows="8"><?php echo htmlentities($this->element->item_wrapper ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="form-text"><?php echo Text::_('COM_CONTENTBUILDERNG_LIST_ITEM_WRAPPER_TIP'); ?></div>
            </div>
        </fieldset>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        if ($this->element->type != 'captcha') {
            echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab2', Text::_('COM_CONTENTBUILDERNG_ELEMENT_OPTIONS_SCRIPTS'));
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="validation_message">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_VALIDATION_MESSAGE'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <input class="form-control form-control-sm" style="width:95%;" type="text"
                                    name="validation_message" id="validation_message"
                                    value="<?php echo htmlentities((string) ($this->element->validation_message ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="validations">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_SELECT_VALIDATIONS'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <select class="form-select form-select-sm" style="width:95%;min-height:140px;" multiple="multiple" size="6"
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
                                <div class="cb-validation-help">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_SELECT_VALIDATIONS'); ?>: Ctrl/Cmd+click pour selection multiple.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="key text-start" style="width: 100px;">
                                <label for="custom_validation_script">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_VALIDATION_CODE'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <?php
                                $params = array('syntax' => 'php');
                                $editor = Editor::getInstance('codemirror');
                                echo $editor->display('custom_validation_script', (string) ($this->element->custom_validation_script ?? ''), '100%', '550', '75', '20', false, 'custom_validation_script', null, null, $params);
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="custom_init_script">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_INIT_CODE'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <?php
                                $params = array('syntax' => 'javascript');
                                $editor = Editor::getInstance('codemirror');
                                echo $editor->display('custom_init_script', (string) ($this->element->custom_init_script ?? ''), '100%', '550', '75', '20', false, 'custom_init_script', null, null, $params);
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
                            <td class="key text-start" style="width: 100px;">
                                <label for="custom_action_script">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_ACTION_CODE'); ?>:
                                </label>
                            </td>
                            <td class="text-start">
                                <?php
                                $params = array('syntax' => 'php');
                                $editor = Editor::getInstance('codemirror');
                                echo $editor->display('custom_action_script', (string) ($this->element->custom_action_script ?? ''), '100%', '550', '75', '20', false, 'custom_action_script', null, null, $params);
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    var adminForm = document.getElementById('adminForm');
    var typeSelect = document.getElementById('type_selection');
    var typePicker = document.getElementById('type_selection_picker');
    var typePickerButton = document.getElementById('type_selection_picker_button');
    var typePickerLabel = document.getElementById('type_selection_picker_label');
    var typePickerIcon = document.getElementById('type_selection_picker_icon');
    var typePickerMenu = document.getElementById('type_selection_picker_menu');
    var typeChange = document.getElementById('type_change');
    var taskField = document.getElementById('task');
    var typePickerOptions = typePicker ? typePicker.querySelectorAll('[data-value]') : [];

    if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            window.bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    if (!adminForm || !typeSelect || !typePicker || !typePickerButton || !typePickerLabel || !typePickerIcon || !typePickerMenu) {
        return;
    }

    typePicker.classList.remove('d-none');
    typeSelect.classList.add('d-none');

    var closeTypePicker = function() {
        typePicker.classList.remove('is-open');
        typePickerButton.setAttribute('aria-expanded', 'false');
    };

    var openTypePicker = function() {
        typePicker.classList.add('is-open');
        typePickerButton.setAttribute('aria-expanded', 'true');
    };

    var updateTypePicker = function() {
        var option = typeSelect.options[typeSelect.selectedIndex];
        if (!option) {
            return;
        }

        var iconClass = String(option.dataset.icon || 'fa-solid fa-puzzle-piece');
        var label = String(option.dataset.typeLabel || option.text || '');
        typePickerIcon.innerHTML = '<span class="' + iconClass + '" aria-hidden="true"></span>';
        typePickerLabel.textContent = label;

        typePickerOptions.forEach(function(pickerOption) {
            pickerOption.classList.toggle('is-active', pickerOption.dataset.value === option.value);
        });
    };

    typePickerOptions.forEach(function(pickerOption) {
        pickerOption.addEventListener('click', function() {
            if (typeSelect.value === pickerOption.dataset.value) {
                closeTypePicker();
                return;
            }

            typeSelect.value = pickerOption.dataset.value;
            updateTypePicker();
            closeTypePicker();

            if (typeChange) {
                typeChange.value = '1';
            }

            if (taskField) {
                taskField.value = 'elementoptions.save';
            }

            if (typeof adminForm.requestSubmit === 'function') {
                adminForm.requestSubmit();
                return;
            }

            adminForm.submit();
        });
    });

    typePickerButton.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();

        if (typePicker.classList.contains('is-open')) {
            closeTypePicker();
            return;
        }

        openTypePicker();
    });

    typePickerMenu.addEventListener('click', function(event) {
        event.stopPropagation();
    });

    document.addEventListener('click', function(event) {
        if (!typePicker.contains(event.target)) {
            closeTypePicker();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeTypePicker();
        }
    });

    typeSelect.addEventListener('change', updateTypePicker);
    updateTypePicker();
});
</script>
<?php
$_eoElement = $this->element ?? null;
if (is_object($_eoElement) && !empty($_eoElement->id)) {
    $_eoType = trim((string) ($_eoElement->type ?? ''));
    $_eoIsModified = false;
    if ($_eoType !== '' && $_eoType !== 'text') {
        $_eoIsModified = true;
    } elseif (trim((string) ($_eoElement->item_wrapper ?? '')) !== '') {
        $_eoIsModified = true;
    } else {
        foreach (['hint', 'default_value', 'validations', 'custom_init_script', 'custom_action_script', 'custom_validation_script', 'validation_message'] as $_eoField) {
            if (trim((string) ($_eoElement->{$_eoField} ?? '')) !== '') {
                $_eoIsModified = true;
                break;
            }
        }
        if (!$_eoIsModified) {
            $_eoOptions = $_eoElement->options ?? null;
            if (is_string($_eoOptions) || $_eoOptions === null) {
                $_eoOptions = PackedDataHelper::decodePackedData((string) ($_eoOptions ?? ''), null);
            }
            if (is_object($_eoOptions)) {
                $_eoOptions = (array) $_eoOptions;
            }
            if (is_array($_eoOptions)) {
                $_eoIgnore = ['length' => '', 'maxlength' => '', 'password' => 0, 'readonly' => 0, 'seperator' => ',', 'class' => '', 'allow_raw' => false, 'allow_html' => false];
                foreach ($_eoOptions as $_eoKey => $_eoVal) {
                    if (is_string($_eoVal)) {
                        $_eoVal = trim($_eoVal);
                    }
                    if (array_key_exists((string) $_eoKey, $_eoIgnore) && $_eoIgnore[(string) $_eoKey] === $_eoVal) {
                        continue;
                    }
                    if ($_eoVal === '' || $_eoVal === null || $_eoVal === false || $_eoVal === 0 || $_eoVal === '0') {
                        continue;
                    }
                    $_eoIsModified = true;
                    break;
                }
            }
        }
    }
    ?>
<script>
if (window.parent && window.parent !== window) {
    window.parent.postMessage({
        type: 'cbElementOptionsView',
        elementId: <?php echo (int) $_eoElement->id; ?>,
        isModified: <?php echo $_eoIsModified ? 'true' : 'false'; ?>
    }, window.location.origin);
}
</script>
<?php } ?>
