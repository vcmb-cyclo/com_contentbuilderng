<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

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
$permGroupLabel = $displayData['permGroupLabel'] ?? null;
$permOptionLabel = static function (string $for, string $labelKey, ?string $tipKey = null): string {
    $label = Text::_($labelKey);

    if ($tipKey === null) {
        return '<label class="cb-perm-users-label" for="' . htmlspecialchars($for, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    }

    $tip = Text::_($tipKey);

    return '<label class="cb-perm-users-label" for="' . htmlspecialchars($for, ENT_QUOTES, 'UTF-8') . '"><span class="cb-perm-header-tip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'
        . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</span></label>';
};
$permSectionTitle = static function (string $labelKey, string $iconClass, ?string $tipKey = null): string {
    $label = Text::_($labelKey);
    $labelHtml = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    if ($tipKey !== null) {
        $tip = Text::_($tipKey);
        $labelHtml = '<span class="cb-perm-header-tip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'
            . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
            . $labelHtml
            . '</span>';
    }

    return '<h3 class="cb-perm-users-title"><span class="' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8')
        . '" aria-hidden="true"></span><span>' . $labelHtml . '</span></h3>';
};

$activePermTab = $session ? $session->get('slideStartOffset', 'permtab1', 'com_contentbuilderng') : 'permtab1';
echo HTMLHelper::_('uitab.startTabSet', 'perm-pane', ['active' => $activePermTab]);
echo HTMLHelper::_('uitab.addTab', 'perm-pane', 'permtab1', Text::_('COM_CONTENTBUILDERNG_DISPLAY_FRONTEND'));
?>
<table id="cb-form-permissions-frontend" class="table table-striped">
    <tr class="row0">
        <td class="key text-end" style="width: 20%;">
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
        <td class="key text-end" style="width: 20%;">
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
        <td class="key text-end" style="width: 20%;">
            <label>
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
        <td class="key text-end" style="width: 20%;">
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
            <td class="key text-end" style="width: 20%;">
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
            <td class="key text-end" style="width: 20%;">
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
<table id="cb-form-permissions-frontend-groups" class="table table-striped">
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

    <?php $groupLineage = []; ?>
    <?php foreach ($gmap as $groupIndex => $entry) : ?>
        <?php
        $k = 0;
        $depth = max(0, (int) ($entry->depth ?? 0));
        foreach (array_keys($groupLineage) as $lineageDepth) {
            if ($lineageDepth >= $depth) {
                unset($groupLineage[$lineageDepth]);
            }
        }
        $ancestorGroupIds = array_values($groupLineage);
        $nextEntry = $gmap[$groupIndex + 1] ?? null;
        $nextDepth = max(0, (int) ($nextEntry->depth ?? 0));
        $isLastAtDepth = $nextDepth <= $depth;
        $branchHtml = '';

        if ($depth > 0) {
            for ($level = 1; $level < $depth; $level++) {
                $branchHtml .= '<span class="cb-perm-group-branch cb-perm-group-branch-guide" aria-hidden="true">&nbsp;</span>';
            }

            $branchHtml .= '<span class="cb-perm-group-branch cb-perm-group-branch-node" aria-hidden="true">'
                . ($isLastAtDepth ? '└─' : '├─')
                . '</span>';
        }

        $groupLineage[$depth] = (int) ($entry->value ?? 0);
        ?>
        <tr class="<?php echo 'row' . $k; ?>">
            <td>
                <span class="cb-perm-group-label">
                    <?php if ($branchHtml !== '') : ?>
                        <span class="cb-perm-group-tree"><?php echo $branchHtml; ?></span>
                    <?php endif; ?>
                    <?php
                    echo is_callable($permGroupLabel)
                        ? $permGroupLabel(
                            (string) ($entry->text ?? ''),
                            (int) ($entry->value ?? 0),
                            (string) ($entry->path ?? ''),
                            (string) ($entry->title ?? '')
                        )
                        : '<span class="cb-perm-group-text">' . htmlspecialchars((string) $entry->text, ENT_QUOTES, 'UTF-8') . '</span>';
                    ?>
                </span>
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

                $ancestorIds = implode(',', array_map('intval', $ancestorGroupIds));
                $tdClass = '';
                $tdTitle = '';

                echo '<td' . $tdClass . $tdTitle . '>'
                    . (is_callable($renderCheckbox)
                        ? $renderCheckbox($permName, $permId, $isChecked, '1', [
                            'data-cb-perm-matrix' => '1',
                            'data-cb-group-id' => (int) ($entry->value ?? 0),
                            'data-cb-perm-key' => $permKey,
                            'data-cb-ancestor-ids' => $ancestorIds,
                        ])
                        : '')
                    . '</td>';
            }
            ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.addTab', 'perm-pane', 'permtab2', Text::_('COM_CONTENTBUILDERNG_EMAIL_USERS'));
?>
<div class="cb-perm-users-grid">
    <section id="cb-form-permissions-users" class="cb-perm-users-card">
        <?php echo $permSectionTitle('COM_CONTENTBUILDERNG_PERMISSIONS_USERS', 'fa-solid fa-user-shield', 'COM_CONTENTBUILDERNG_PERM_USERS_TIP'); ?>
        <div class="cb-perm-users-fields">
            <div class="cb-perm-users-field">
                <?php echo $permOptionLabel('limit_add', 'COM_CONTENTBUILDERNG_PERM_LIMIT_ADD', 'COM_CONTENTBUILDERNG_PERM_LIMIT_ADD_TIP'); ?>
                <input class="form-control form-control-sm" id="limit_add" name="jform[limit_add]" type="text"
                    value="<?php echo $item->limit_add; ?>" />
            </div>
            <div class="cb-perm-users-field">
                <?php echo $permOptionLabel('limit_edit', 'COM_CONTENTBUILDERNG_PERM_LIMIT_EDIT', 'COM_CONTENTBUILDERNG_PERM_LIMIT_EDIT_TIP'); ?>
                <input class="form-control form-control-sm" id="limit_edit" name="jform[limit_edit]" type="text"
                    value="<?php echo $item->limit_edit; ?>" />
            </div>
            <div class="cb-perm-users-field cb-perm-users-field-wide">
                <?php echo $permOptionLabel('cb_perm_users_manage', 'COM_CONTENTBUILDERNG_PERM_USERS', 'COM_CONTENTBUILDERNG_PERM_USERS_TIP'); ?>
                <div>
                    <a class="btn btn-primary" id="cb_perm_users_manage" href="index.php?option=com_contentbuilderng&amp;view=users&amp;tmpl=component&amp;form_id=<?php echo (int) $item->id; ?>" data-bs-toggle="modal" data-bs-target="#edit-modal">
                        <span class="fa-solid fa-user-gear me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section id="cb-form-permissions-verification" class="cb-perm-users-card">
        <?php echo $permSectionTitle('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED', 'fa-solid fa-user-check', 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED_TIP'); ?>
        <div class="cb-perm-verify-stack">
            <?php foreach (['view' => 'COM_CONTENTBUILDERNG_PERM_VIEW', 'new' => 'COM_CONTENTBUILDERNG_PERM_NEW', 'edit' => 'COM_CONTENTBUILDERNG_PERM_EDIT'] as $permSuffix => $permLabelKey) : ?>
                <div class="cb-perm-verify-row">
                    <div class="cb-perm-verify-head">
                        <span class="cb-perm-verify-badge"><?php echo Text::_($permLabelKey); ?></span>
                    </div>
                    <div class="cb-perm-verify-controls">
                        <div class="cb-perm-verify-toggle">
                            <input type="hidden" name="jform[verification_required_<?php echo $permSuffix; ?>]" value="0" />
                            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[verification_required_' . $permSuffix . ']', 'verification_required_' . $permSuffix, (bool) ($item->{'verification_required_' . $permSuffix} ?? false)) : ''; ?>
                            <?php echo $permOptionLabel('verification_required_' . $permSuffix, 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED', 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED_TIP'); ?>
                        </div>
                        <div class="cb-perm-users-field">
                            <?php echo $permOptionLabel('verification_days_' . $permSuffix, 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS', 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS_TIP'); ?>
                            <input class="form-control form-control-sm" id="verification_days_<?php echo $permSuffix; ?>"
                                name="jform[verification_days_<?php echo $permSuffix; ?>]" type="text"
                                value="<?php echo $item->{'verification_days_' . $permSuffix}; ?>" />
                        </div>
                        <div class="cb-perm-users-field cb-perm-users-field-grow">
                            <?php echo $permOptionLabel('verification_url_' . $permSuffix, 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL', 'COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL_TIP'); ?>
                            <input class="form-control form-control-sm" id="verification_url_<?php echo $permSuffix; ?>"
                                name="jform[verification_url_<?php echo $permSuffix; ?>]" type="text"
                                value="<?php echo htmlentities($item->{'verification_url_' . $permSuffix} ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (empty($item->edit_by_type)) : ?>
        <section id="cb-form-permissions-registration" class="cb-perm-users-card cb-perm-users-card-wide">
            <?php echo $permSectionTitle('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION', 'fa-solid fa-id-card', 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_TIP'); ?>
            <div class="cb-perm-users-fields">
                <div class="cb-perm-users-field cb-perm-users-field-wide">
                    <div class="cb-perm-verify-toggle">
                        <input type="hidden" name="jform[act_as_registration]" value="0" />
                        <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[act_as_registration]', 'act_as_registration', (bool) ($item->act_as_registration ?? false)) : ''; ?>
                        <?php echo $permOptionLabel('act_as_registration', 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION', 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_TIP'); ?>
                    </div>
                </div>
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
                    <div class="cb-perm-users-field">
                        <?php echo $permOptionLabel($fieldName, $labelKey, 'COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_FIELDS_TIP'); ?>
                        <select class="form-select form-select-sm" name="jform[<?php echo $fieldName; ?>]" id="<?php echo $fieldName; ?>">
                            <option value="">- <?php echo Text::_($labelKey); ?> -</option>
                            <?php foreach ($elements as $theElement) : ?>
                                <option value="<?php echo $theElement->reference_id; ?>" <?php echo ($item->{$fieldName} ?? null) == $theElement->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($theElement->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="cb-perm-users-field">
                    <div class="cb-perm-verify-toggle">
                        <input type="hidden" name="jform[force_login]" value="0" />
                        <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[force_login]', 'force_login', (bool) ($item->force_login ?? false)) : ''; ?>
                        <?php echo $permOptionLabel('force_login', 'COM_CONTENTBUILDERNG_PERM_FORCE_LOGIN', 'COM_CONTENTBUILDERNG_PERM_FORCE_LOGIN_TIP'); ?>
                    </div>
                </div>
                <div class="cb-perm-users-field cb-perm-users-field-grow">
                    <?php echo $permOptionLabel('force_url', 'COM_CONTENTBUILDERNG_PERM_FORCE_URL', 'COM_CONTENTBUILDERNG_PERM_FORCE_URL_TIP'); ?>
                    <input class="form-control form-control-sm" id="force_url" name="jform[force_url]" type="text"
                        value="<?php echo htmlentities($item->force_url ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="cb-perm-users-field">
                    <?php echo $permOptionLabel('registration_bypass_plugin', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN_TIP'); ?>
                    <select class="form-select form-select-sm" name="jform[registration_bypass_plugin]" id="registration_bypass_plugin">
                        <option value="">- <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -</option>
                        <?php foreach ($verificationPlugins as $registrationBypassPlugin) : ?>
                            <option value="<?php echo $registrationBypassPlugin; ?>" <?php echo $registrationBypassPlugin == ($item->registration_bypass_plugin ?? null) ? ' selected="selected"' : ''; ?>>
                                <?php echo $registrationBypassPlugin; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cb-perm-users-field">
                    <?php echo $permOptionLabel('registration_bypass_verification_name', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_NAME', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_NAME_TIP'); ?>
                    <input class="form-control form-control-sm" type="text" name="jform[registration_bypass_verification_name]"
                        id="registration_bypass_verification_name"
                        value="<?php echo htmlentities($item->registration_bypass_verification_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="cb-perm-users-field">
                    <?php echo $permOptionLabel('registration_bypass_verify_view', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_VIEW', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_VIEW_TIP'); ?>
                    <input class="form-control form-control-sm" type="text" name="jform[registration_bypass_verify_view]"
                        id="registration_bypass_verify_view"
                        value="<?php echo htmlentities($item->registration_bypass_verify_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="cb-perm-users-field cb-perm-users-field-wide">
                    <?php echo $permOptionLabel('registration_bypass_plugin_params', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN_PARAMS', 'COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN_PARAMS_TIP'); ?>
                    <textarea class="form-control form-control-sm" style="min-height: 96px;"
                        name="jform[registration_bypass_plugin_params]"
                        id="registration_bypass_plugin_params"><?php echo htmlentities($item->registration_bypass_plugin_params ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        </section>
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
</div>
<?php
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.endTabSet');
?>
