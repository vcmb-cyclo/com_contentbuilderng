<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$item = $displayData['item'] ?? null;
$session = $displayData['session'] ?? null;
$gmap = is_array($displayData['gmap'] ?? null) ? $displayData['gmap'] : [];
$elements = is_array($displayData['elements'] ?? null) ? $displayData['elements'] : [];
$verificationPlugins = is_array($displayData['verificationPlugins'] ?? null) ? $displayData['verificationPlugins'] : [];
$permissionColumns = is_array($displayData['permissionColumns'] ?? null) ? $displayData['permissionColumns'] : [];
$defaultCheckedForNewPermissions = is_array($displayData['defaultCheckedForNewPermissions'] ?? null) ? $displayData['defaultCheckedForNewPermissions'] : [];
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$permHeaderLabel = $displayData['permHeaderLabel'] ?? null;

$activePermTab = $session ? $session->get('slideStartOffset', 'permtab1', 'com_contentbuilderng') : 'permtab1';
echo HTMLHelper::_('uitab.startTabSet', 'perm-pane', ['active' => $activePermTab]);
echo HTMLHelper::_('uitab.addTab', 'perm-pane', 'permtab1', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_FRONTEND'));
?>
<table class="table table-striped">
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="own_only_fe">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN_OWNLY_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN_OWNLY'); ?>
                </span>:
            </label>
        </td>
        <td>
            <input type="hidden" name="jform[own_only_fe]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[own_only_fe]', 'own_only_fe', (bool) ($item->own_only_fe ?? false)) : ''; ?>
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="limited_article_options_fe">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMITED_ARTICLE_OPTIONS_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMITED_ARTICLE_OPTIONS'); ?>
                </span>:
            </label>
        </td>
        <td>
            <input type="hidden" name="jform[limited_article_options_fe]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[limited_article_options_fe]', 'limited_article_options_fe', (bool) ($item->limited_article_options_fe ?? false)) : ''; ?>
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="own_fe_view">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN'); ?>
                </span>:
            </label>
        </td>
        <td>
            <?php foreach ($permissionColumns as $permissionColumn) : ?>
                <?php
                $permKey = $permissionColumn['key'];
                $permId = 'own_fe_' . $permKey;
                $permName = 'jform[own_fe][' . $permKey . ']';
                $isChecked = !empty($item->config['own_fe'][$permKey]);
                ?>
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox($permName, $permId, $isChecked) : ''; ?>
                <label class="form-check-label me-2" for="<?php echo $permId; ?>">
                    <?php
                    echo is_callable($permHeaderLabel)
                        ? $permHeaderLabel($permissionColumn['label'], $permissionColumn['tip'])
                        : Text::_($permissionColumn['label']);
                    ?>
                </label>
            <?php endforeach; ?>
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="show_all_languages_fe">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_SHOW_ALL_LANGUAGES_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_SHOW_ALL_LANGUAGES'); ?>
                </span>:
            </label>
        </td>
        <td>
            <input type="hidden" name="jform[show_all_languages_fe]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[show_all_languages_fe]', 'show_all_languages_fe', (bool) ($item->show_all_languages_fe ?? false)) : ''; ?>
        </td>
    </tr>
    <?php if (!empty($item->edit_by_type)) : ?>
        <tr class="row0">
            <td width="20%" align="right" class="key">
                <label for="force_login">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_LOGIN'); ?>
                </label>
            </td>
            <td>
                <input type="hidden" name="jform[force_login]" value="0" />
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[force_login]', 'force_login', (bool) ($item->force_login ?? false)) : ''; ?>
            </td>
        </tr>
        <tr class="row0">
            <td width="20%" align="right" class="key">
                <label for="force_url">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_URL'); ?>
                </label>
            </td>
            <td>
                <input style="width: 100%;" id="force_url" name="jform[force_url]" type="text"
                    value="<?php echo htmlentities($item->force_url ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </td>
        </tr>
    <?php endif; ?>
</table>
<table class="table table-striped">
    <thead>
        <tr>
            <th>
                <?php echo is_callable($permHeaderLabel) ? $permHeaderLabel('COM_CONTENTBUILDERNG_PERM_GROUP', 'COM_CONTENTBUILDERNG_PERM_GROUP_TIP') : Text::_('COM_CONTENTBUILDERNG_PERM_GROUP'); ?>
            </th>
            <?php foreach ($permissionColumns as $permissionColumn) : ?>
                <th>
                    <?php echo is_callable($permHeaderLabel) ? $permHeaderLabel($permissionColumn['label'], $permissionColumn['tip']) : Text::_($permissionColumn['label']); ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tr>
        <td class="bg-body-tertiary"></td>
        <?php foreach ($permissionColumns as $permissionColumn) : ?>
            <?php
            $permKey = $permissionColumn['key'];
            $permId = 'perms_fe_select_' . $permKey;
            ?>
            <td class="bg-body-tertiary">
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox('', $permId, false, $permKey, ['onclick' => "contentbuilderng_selectAll(this,'fe')"]) : ''; ?>
            </td>
        <?php endforeach; ?>
    </tr>

    <?php foreach ($gmap as $entry) : ?>
        <?php $k = 0; ?>
        <tr class="<?php echo 'row' . $k; ?>">
            <td>
                <?php echo $entry->text; ?>
            </td>
            <?php
            $groupPermissions = $item->config['permissions_fe'][$entry->value] ?? [];
            foreach ($permissionColumns as $permissionColumn) {
                $permKey = $permissionColumn['key'];
                $permName = 'jform[perms_fe][' . $entry->value . '][' . $permKey . ']';
                $permId = 'perms_fe_' . $entry->value . '_' . $permKey;
                $isChecked = !$item->id && !empty($defaultCheckedForNewPermissions[$permKey]);

                if (!$isChecked) {
                    $isChecked = !empty($groupPermissions[$permKey]);
                }

                echo '<td>' . (is_callable($renderCheckbox) ? $renderCheckbox($permName, $permId, $isChecked) : '') . '</td>';
            }
            ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.addTab', 'perm-pane', 'permtab2', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_USERS'));
?>
<table class="table table-striped">
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="limit_add">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMIT_ADD'); ?>:
            </label>
        </td>
        <td>
            <input class="form-control form-control-sm w-100" id="limit_add" name="jform[limit_add]" type="text"
                value="<?php echo $item->limit_add; ?>" />
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="limit_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMIT_EDIT'); ?>:
            </label>
        </td>
        <td>
            <input class="form-control form-control-sm w-100" id="limit_edit" name="jform[limit_edit]" type="text"
                value="<?php echo $item->limit_edit; ?>" />
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="verification_required_view">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VIEW'); ?>:
            </label>
        </td>
        <td>
            <input type="hidden" name="jform[verification_required_view]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[verification_required_view]', 'verification_required_view', (bool) ($item->verification_required_view ?? false)) : ''; ?><label class="form-check-label" for="verification_required_view">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED'); ?>
            </label>
            <input class="form-control form-control-sm" style="width: 50px;" id="verification_days_view"
                name="jform[verification_days_view]" type="text"
                value="<?php echo $item->verification_days_view; ?>" /> <label
                for="verification_days_view">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS'); ?>
            </label>
            <input class="form-control form-control-sm" style="width: 300px;" id="verification_url_view"
                name="jform[verification_url_view]" type="text"
                value="<?php echo htmlentities($item->verification_url_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <label for="verification_url_view">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL'); ?>
            </label>
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="verification_required_new">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_NEW'); ?>:
            </label>
        </td>
        <td>
            <input type="hidden" name="jform[verification_required_new]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[verification_required_new]', 'verification_required_new', (bool) ($item->verification_required_new ?? false)) : ''; ?><label class="form-check-label" for="verification_required_new">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED'); ?>
            </label>
            <input class="form-control form-control-sm" style="width: 50px;" id="verification_days_new"
                name="jform[verification_days_new]" type="text"
                value="<?php echo $item->verification_days_new; ?>" /> <label for="verification_days_new">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS'); ?>
            </label>
            <input class="form-control form-control-sm" style="width: 300px;" id="verification_url_new"
                name="jform[verification_url_new]" type="text"
                value="<?php echo htmlentities($item->verification_url_new ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <label for="verification_url_new">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL'); ?>
            </label>
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label for="verification_required_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_EDIT'); ?>:
            </label>
        </td>
        <td>
            <input type="hidden" name="jform[verification_required_edit]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[verification_required_edit]', 'verification_required_edit', (bool) ($item->verification_required_edit ?? false)) : ''; ?><label class="form-check-label" for="verification_required_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED'); ?>
            </label>
            <input class="form-control form-control-sm" style="width: 50px;" id="verification_days_edit"
                name="jform[verification_days_edit]" type="text"
                value="<?php echo $item->verification_days_edit; ?>" /> <label
                for="verification_days_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS'); ?>
            </label>
            <input class="form-control form-control-sm" style="width: 300px;" id="verification_url_new"
                name="jform[verification_url_edit]" type="text"
                value="<?php echo htmlentities($item->verification_url_edit ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <label for="verification_url_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL'); ?>
            </label>
        </td>
    </tr>
    <tr class="row0">
        <td width="20%" align="right" class="key">
            <label>
                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_USERS'); ?>:
            </label>
        </td>
        <td>
            <?php echo '[<a href="index.php?option=com_contentbuilderng&amp;view=users&amp;tmpl=component&amp;form_id=' . $item->id . '" title="" data-bs-toggle="modal" data-bs-target="#edit-modal">' . Text::_('COM_CONTENTBUILDERNG_EDIT') . '</a>]'; ?>
        </td>
    </tr>
    <?php if (empty($item->edit_by_type)) : ?>
        <tr class="row0">
            <td width="20%" align="right" class="key" valign="top">
                <label for="act_as_registration">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION'); ?>:
                </label>
            </td>
            <td>
                <input type="hidden" name="jform[act_as_registration]" value="0" />
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[act_as_registration]', 'act_as_registration', (bool) ($item->act_as_registration ?? false)) : ''; ?>
                <br />
                <br />
                <?php
                $registrationFields = [
                    'registration_name_field' => 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_NAME_FIELD',
                    'registration_username_field' => 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_USERNAME_FIELD',
                    'registration_email_field' => 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_EMAIL_FIELD',
                    'registration_email_repeat_field' => 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_EMAIL_REPEAT_FIELD',
                    'registration_password_field' => 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_PASSWORD_FIELD',
                    'registration_password_repeat_field' => 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_PASSWORD_REPEAT_FIELD',
                ];
                foreach ($registrationFields as $fieldName => $labelKey) :
                ?>
                    <select class="form-select-sm" name="jform[<?php echo $fieldName; ?>]" id="<?php echo $fieldName; ?>" style="max-width: 200px;">
                        <option value=""> -
                            <?php echo Text::_($labelKey); ?> -
                        </option>
                        <?php foreach ($elements as $theElement) : ?>
                            <option value="<?php echo $theElement->reference_id; ?>" <?php echo ($item->{$fieldName} ?? null) == $theElement->reference_id ? ' selected="selected"' : ''; ?>>
                                <?php echo htmlentities($theElement->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <br />
                    <br />
                <?php endforeach; ?>
                <label for="force_login">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_LOGIN'); ?>
                </label>
                <br />
                <input type="hidden" name="jform[force_login]" value="0" />
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[force_login]', 'force_login', (bool) ($item->force_login ?? false)) : ''; ?>
                <br />
                <br />
                <label for="force_url">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_URL'); ?>
                </label>
                <br />
                <input class="form-control form-control-sm" id="force_url" name="jform[force_url]" type="text"
                    value="<?php echo htmlentities($item->force_url ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <br />
                <br />
                <label for="registration_bypass_plugin">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN'); ?>
                </label>
                <br />
                <select class="form-select-sm" name="jform[registration_bypass_plugin]" id="registration_bypass_plugin">
                    <option value=""> -
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                    </option>
                    <?php foreach ($verificationPlugins as $registrationBypassPlugin) : ?>
                        <option value="<?php echo $registrationBypassPlugin; ?>" <?php echo $registrationBypassPlugin == ($item->registration_bypass_plugin ?? null) ? ' selected="selected"' : ''; ?>>
                            <?php echo $registrationBypassPlugin; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br />
                <br />
                <label for="registration_bypass_verification_name">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_NAME'); ?>
                </label>
                <br />
                <input class="form-control form-control-sm" type="text" name="jform[registration_bypass_verification_name]"
                    id="registration_bypass_verification_name"
                    value="<?php echo htmlentities($item->registration_bypass_verification_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <br />
                <br />
                <label for="registration_bypass_verify_view">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_VIEW'); ?>
                </label>
                <br />
                <input class="form-control form-control-sm" type="text" name="jform[registration_bypass_verify_view]"
                    id="registration_bypass_verify_view"
                    value="<?php echo htmlentities($item->registration_bypass_verify_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <br />
                <br />
                <label for="registration_bypass_plugin_params">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN_PARAMS'); ?>
                </label>
                <br />
                <textarea class="form-control form-control-sm" style="width: 100%;height: 80px;"
                    name="jform[registration_bypass_plugin_params]"
                    id="registration_bypass_plugin_params"><?php echo htmlentities($item->registration_bypass_plugin_params ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </td>
        </tr>
    <?php else : ?>
        <input type="hidden" name="jform[act_as_registration]" value="<?php echo htmlentities($item->act_as_registration ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_name_field]" value="<?php echo htmlentities($item->registration_name_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_username_field]" value="<?php echo htmlentities($item->registration_username_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_email_field]" value="<?php echo htmlentities($item->registration_email_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_email_repeat_field]" value="<?php echo htmlentities($item->registration_email_repeat_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_password_field]" value="<?php echo htmlentities($item->registration_password_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_password_repeat_field]" value="<?php echo htmlentities($item->registration_password_repeat_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_bypass_plugin]" value="<?php echo htmlentities($item->registration_bypass_plugin ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_bypass_verification_name]" value="<?php echo htmlentities($item->registration_bypass_verification_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_bypass_verify_view]" value="<?php echo htmlentities($item->registration_bypass_verify_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="jform[registration_bypass_plugin_params]" value="<?php echo htmlentities($item->registration_bypass_plugin_params ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endif; ?>
</table>
<?php
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.endTabSet');
?>
