<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$item = $displayData['item'] ?? null;
$form = $displayData['form'] ?? null;
$session = $displayData['session'] ?? null;
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$breezingFormsProvidedMessage = (string) ($displayData['breezingFormsProvidedMessage'] ?? '');
?>
<h3 id="cb-form-email" class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_TEMPLATES'); ?></h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_TAB_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_TAB_PERMISSION_HINT'); ?>
</div>
<div id="cb-form-email-notifications" class="border rounded-3 p-3 mb-3 bg-body-tertiary">
    <div class="row g-3 align-items-start">
        <div class="col-lg-4 d-flex align-items-start gap-2">
            <input type="hidden" name="jform[email_notifications]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[email_notifications]', 'email_notifications', (bool) ($item->email_notifications ?? false)) : ''; ?>
            <label class="form-check-label" for="email_notifications">
                <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_NOTIFICATIONS'); ?>
            </label>
        </div>
        <div class="col-lg-8">
            <small class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_NOTIFICATIONS_DESC'); ?></small>
        </div>
        <div class="col-lg-4 d-flex align-items-start gap-2">
            <input type="hidden" name="jform[email_update_notifications]" value="0" />
            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[email_update_notifications]', 'email_update_notifications', (bool) ($item->email_update_notifications ?? false)) : ''; ?>
            <label class="form-check-label" for="email_update_notifications">
                <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_UPDATE_NOTIFICATIONS'); ?>
            </label>
        </div>
        <div class="col-lg-8">
            <small class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_UPDATE_NOTIFICATIONS_DESC'); ?></small>
        </div>
    </div>
</div>
<?php if (!empty($item->edit_by_type)) : ?>
    <?php echo $breezingFormsProvidedMessage; ?>
    <input type="hidden" name="jform[email_admin_template]" value="<?php echo htmlentities($item->email_admin_template ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_template]" value="<?php echo htmlentities($item->email_template ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_admin_subject]" value="<?php echo htmlentities($item->email_admin_subject ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_admin_alternative_from]" value="<?php echo htmlentities($item->email_admin_alternative_from ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_admin_alternative_fromname]" value="<?php echo htmlentities($item->email_admin_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_admin_recipients]" value="<?php echo htmlentities($item->email_admin_recipients ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_admin_recipients_attach_uploads]" value="<?php echo htmlentities($item->email_admin_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_admin_html]" value="<?php echo htmlentities($item->email_admin_html ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>

    <input type="hidden" name="jform[email_subject]" value="<?php echo htmlentities($item->email_subject ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_alternative_from]" value="<?php echo htmlentities($item->email_alternative_from ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_alternative_fromname]" value="<?php echo htmlentities($item->email_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_recipients]" value="<?php echo htmlentities($item->email_recipients ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_recipients_attach_uploads]" value="<?php echo htmlentities($item->email_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
    <input type="hidden" name="jform[email_html]" value="<?php echo htmlentities($item->email_html ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
<?php else : ?>
    <div id="cb-form-email-admins" style="cursor:pointer; width: 100%; background-color: var(--bs-body-bg);"
        onclick="if(document.adminForm.email_admins.value=='none'){document.adminForm.email_admins.value='';document.getElementById('email_admins_div').style.display='';}else{document.adminForm.email_admins.value='none';document.getElementById('email_admins_div').style.display='none';}">
        <h3>
            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ADMINS'); ?>
        </h3>
    </div>
    <div id="email_admins_div"
        style="display:<?php echo $session ? $session->get('email_admins', '', 'com_contentbuilderng') : ''; ?>">
        <table width="100%" class="table table-striped">
            <tr>
                <td width="20%">
                    <label for="email_admin_subject"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_SUBJECT_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_SUBJECT'); ?>
                        </span></label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_admin_subject" type="text"
                        name="jform[email_admin_subject]"
                        value="<?php echo htmlentities($item->email_admin_subject ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
                <td width="20%">
                    <label for="email_admin_alternative_from">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROM'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_admin_alternative_from" type="text"
                        name="jform[email_admin_alternative_from]"
                        value="<?php echo htmlentities($item->email_admin_alternative_from ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="email_admin_alternative_fromname">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROMNAME'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_admin_alternative_fromname"
                        type="text" name="jform[email_admin_alternative_fromname]"
                        value="<?php echo htmlentities($item->email_admin_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
                <td width="20%">
                    <label for="email_admin_recipients"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_RECIPIENTS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_RECIPIENTS'); ?>
                        </span></label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_admin_recipients" type="text"
                        name="jform[email_admin_recipients]"
                        value="<?php echo htmlentities($item->email_admin_recipients ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="email_admin_recipients_attach_uploads"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ATTACH_UPLOADS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ATTACH_UPLOADS'); ?>
                        </span></label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_admin_recipients_attach_uploads"
                        type="text" name="jform[email_admin_recipients_attach_uploads]"
                        value="<?php echo htmlentities($item->email_admin_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
                <td width="20%">
                    <label for="email_admin_html">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_HTML'); ?>
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[email_admin_html]" value="0" />
                    <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[email_admin_html]', 'email_admin_html', (bool) ($item->email_admin_html ?? false)) : ''; ?>
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="email_admin_create_sample_button">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[email_admin_create_sample]" id="cb_email_admin_create_sample_flag" value="0" />
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="email_admin_create_sample_button"
                            onclick="cbQueueEmailAdminSampleGeneration(this);">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                        </button>
                        <small id="cb_email_admin_create_sample_hint" class="text-success d-none">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                        </small>
                    </div>
                </td>
                <td width="20%"></td>
                <td></td>
            </tr>
        </table>
        <?php echo $form ? $form->renderField('email_admin_template') : ''; ?>
    </div>

    <div id="cb-form-email-users" style="cursor:pointer; width: 100%; background-color: var(--bs-body-bg);">
        <h3>
            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_USERS'); ?>
        </h3>
    </div>
    <div id="email_users_div">
        <table width="100%" class="table table-striped">
            <tr>
                <td width="20%">
                    <label for="email_subject">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_SUBJECT'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_subject" type="text"
                        name="jform[email_subject]"
                        value="<?php echo htmlentities($item->email_subject ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
                <td width="20%">
                    <label for="email_alternative_from">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROM'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_alternative_from" type="text"
                        name="jform[email_alternative_from]"
                        value="<?php echo htmlentities($item->email_alternative_from ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="email_alternative_fromname">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROMNAME'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_alternative_fromname" type="text"
                        name="jform[email_alternative_fromname]"
                        value="<?php echo htmlentities($item->email_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
                <td width="20%">
                    <label for="email_recipients">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_RECIPIENTS'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_recipients" type="text"
                        name="jform[email_recipients]"
                        value="<?php echo htmlentities($item->email_recipients ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="email_recipients_attach_uploads">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ATTACH_UPLOADS'); ?>
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="email_recipients_attach_uploads"
                        type="text" name="jform[email_recipients_attach_uploads]"
                        value="<?php echo htmlentities($item->email_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </td>
                <td width="20%">
                    <label for="email_html">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_HTML'); ?>
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[email_html]" value="0" />
                    <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[email_html]', 'email_html', (bool) ($item->email_html ?? false)) : ''; ?>
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="email_create_sample_button">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[email_create_sample]" id="cb_email_create_sample_flag" value="0" />
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="email_create_sample_button"
                            onclick="cbQueueEmailUserSampleGeneration(this);">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                        </button>
                        <small id="cb_email_create_sample_hint" class="text-success d-none">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                        </small>
                    </div>
                </td>
                <td width="20%"></td>
                <td></td>
            </tr>
        </table>
        <?php echo $form ? $form->renderField('email_template') : ''; ?>
    </div>
<?php endif; ?>
