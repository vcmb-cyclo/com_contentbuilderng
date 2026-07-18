<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;

class VerifyModel extends BaseDatabaseModel
{

    private $frontend = false;
    private AdministratorApplication|SiteApplication $app;

    private function getComponent(): ContentbuilderngComponent
    {
        $component = parent::getComponent();

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function createMailer()
    {
        return $this->getComponent()->getContainer()->get(MailerFactoryInterface::class)->createMailer();
    }

    private function getUserFactory(): UserFactoryInterface
    {
        return $this->getComponent()->getContainer()->get(UserFactoryInterface::class);
    }

    private function getInput(): Input
    {
        return $this->app->getInput();
    }

    private function decodePackedQueryString(string $encoded): array
    {
        if ($encoded === '') {
            return [];
        }

        $decoded = base64_decode($encoded, true);
        $payload = $decoded !== false ? $decoded : $encoded;
        parse_str($payload, $opts);

        return is_array($opts) ? $opts : [];
    }

    private function decodeInternalReturn(?string $encoded): string
    {
        $encoded = trim((string) $encoded);
        if ($encoded === '') {
            return '';
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false || !Uri::isInternal($decoded)) {
            return '';
        }

        return $decoded;
    }

    private function safeRedirectTarget(?string $target, string $fallback = 'index.php'): string
    {
        $target = trim((string) $target);
        if ($target !== '' && Uri::isInternal($target)) {
            return $target;
        }

        return $fallback;
    }

    public function __construct(
        array $config = [],
        ?MVCFactoryInterface $factory = null
    ) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);

        $app = $this->getComponent()->getContainer()->get(CMSApplicationInterface::class);
        if (!$app instanceof AdministratorApplication && !$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }
        $this->app = $app;
        $this->frontend = $this->app->isClient('site');

        $option = 'com_contentbuilderng';

        $input = $this->getInput();
        $plugin = $input->get('plugin', '', 'string');
        $verification_name = $input->get('verification_name', '', 'string');

        $verification_id = $input->get('verification_id', '', 'string');
        $setup = '';
        $user_id = 0;

        if ($input->getBool('verify_by_admin', 0)) {

            $this->activate_by_admin($input->get('token', '', 'string'));
        }

        if (!$verification_id) {
            $user_id = (int) ($this->app->getIdentity()->id ?? 0);
            $setup = $this->app->getSession()->get('com_contentbuilderng.verify.' . $plugin . $verification_name, '');
        } else {
            $query = $this->getDatabase()->getQuery(true)
                ->select([
                    $this->getDatabase()->quoteName('setup'),
                    $this->getDatabase()->quoteName('user_id'),
                ])
                ->from($this->getDatabase()->quoteName('#__contentbuilderng_verifications'))
                ->where($this->getDatabase()->quoteName('verification_hash') . ' = ' . $this->getDatabase()->quote($verification_id));
            $this->getDatabase()->setQuery($query);
            $setup = $this->getDatabase()->loadAssoc();
            if (is_array($setup)) {
                $user_id = $setup['user_id'];
                $setup = $setup['setup'];
            }
        }

        $out = array();

        if ($setup) {
            parse_str($setup, $out);
        }

        if (isset($out['plugin']) && $out['plugin'] && isset($out['verification_name']) && $out['verification_name'] && isset($out['verify_view']) && $out['verify_view']) {
            // alright 
        } else {
            $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_VERIFICATION_INVALID_ID'), 'error');
            $this->app->redirect('index.php');
        }

        $out['plugin_options'] = isset($out['plugin_options'])
            ? $this->decodePackedQueryString((string) $out['plugin_options'])
            : [];

        $_now = (new Date());

        //$this->getDatabase()->setQuery("Select count(id) From #__contentbuilderng_verifications Where Timestampdiff(Second, `start_date`, '".strtotime($_now->toSQL())."') < 1 And ip = " . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']));
        //$ver = $this->getDatabase()->loadResult();

        //if($ver >= 5){
        //    $this->getDatabase()->setQuery("Delete From #__contentbuilderng_verifications Where `verification_date` IS NULL And ip = " . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']));
        //    $this->getDatabase()->execute();
        //    throw new \RuntimeException('Penetration denied', 500);
        //}

        //$this->getDatabase()->setQuery("Delete From #__contentbuilderng_verifications Where Timestampdiff(Second, `start_date`, '".strtotime($_now->toSQL())."') > 86400 And `verification_date` IS NULL");
        //$this->getDatabase()->execute();

        $rec = null;
        $redirect_view = '';

        if (isset($out['require_view']) && is_numeric($out['require_view']) && intval($out['require_view']) > 0) {

            if ($this->app->getSession()->get('com_contentbuilderng.cb_last_record_user_id', 0)) {
                $user_id = $this->app->getSession()->get('com_contentbuilderng.cb_last_record_user_id', 0);
                $this->app->getSession()->remove('com_contentbuilderng.cb_last_record_user_id');
            }

            $id = intval($out['require_view']);

            $query = $this->getDatabase()->getQuery(true)
                ->select([
                    $this->getDatabase()->quoteName('type'),
                    $this->getDatabase()->quoteName('reference_id'),
                    $this->getDatabase()->quoteName('show_all_languages_fe'),
                ])
                ->from($this->getDatabase()->quoteName('#__contentbuilderng_forms'))
                ->where($this->getDatabase()->quoteName('published') . ' = 1')
                ->where($this->getDatabase()->quoteName('id') . ' = ' . $id);
            $this->getDatabase()->setQuery($query);
            $formsettings = $this->getDatabase()->loadAssoc();

            if (!is_array($formsettings)) {
                throw new \Exception(Text::sprintf('COM_CONTENTBUILDERNG_VERIFICATION_SETUP_VIEW_UNAVAILABLE', $out['require_view']), 500);
            }

            $form = FormSourceFactory::getForm((string) $formsettings['type'], (string) $formsettings['reference_id']);
            $labels = $form->getElementLabels();

            $ids = array();

            foreach ($labels as $reference_id => $label) {
                $ids[] = $reference_id;
            }

            if (intval($user_id) == 0) {
                $this->app->redirect('index.php?option=com_contentbuilderng&lang=' . $input->getCmd('lang', '') . '&return=' . base64_encode(Uri::getInstance()->toString()) . '&task=edit.display&record_id=&id=' . $id . '&rand=' . rand(0, getrandmax()));
            }

            $rec = $form->getListRecords($ids, '', array(), 0, 1, '', array(), 'desc', 0, false, $user_id, 0, -1, -1, -1, -1, array(), true, null);

            if (count($rec) > 0) {
                $rec = $rec[0];
                $rec = $form->getRecord($rec->colRecord, false, -1, true);
            }

            if (!$form->getListRecordsTotal($ids)) {
                $this->app->redirect('index.php?option=com_contentbuilderng&lang=' . $input->getCmd('lang', '') . '&return=' . base64_encode(Uri::getInstance()->toString()) . '&task=edit.display&record_id=&id=' . $id . '&rand=' . rand(0, getrandmax()));
            }
        }

        // clearing session after possible required view to make re-visits possible
        $this->app->getSession()->remove('com_contentbuilderng.verify.' . $plugin . $verification_name);

        $verification_data = '';
        if (is_array($rec) && count($rec)) {
            foreach ($rec as $value) {
                $verification_data .= urlencode(str_replace(array("\r", "\n"), '', $value->recTitle)) . "=" . urlencode(str_replace(array("\r", "\n"), '', $value->recValue)) . "&";
            }
            $verification_data = rtrim($verification_data, '&');
        }

        if (!$input->getBool('verify', 0) && !$input->get('token', '', 'string')) {
            $___now = $_now->toSql();

            $verification_id = md5(uniqid("", true) . mt_rand(0, mt_getrandmax()) . $user_id);
            $query = $this->getDatabase()->getQuery(true)
                ->insert($this->getDatabase()->quoteName('#__contentbuilderng_verifications'))
                ->columns($this->getDatabase()->quoteName([
                    'verification_hash',
                    'start_date',
                    'verification_data',
                    'user_id',
                    'plugin',
                    'ip',
                    'setup',
                    'client',
                ]))
                ->values(implode(', ', [
                    $this->getDatabase()->quote($verification_id),
                    $this->getDatabase()->quote($___now),
                    $this->getDatabase()->quote('type=normal&' . $verification_data),
                    (int) $user_id,
                    $this->getDatabase()->quote($plugin),
                    $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']),
                    $this->getDatabase()->quote($setup),
                    (int) ($out['client'] ?? 0),
                ]));
            $this->getDatabase()->setQuery($query);
            $this->getDatabase()->execute();
        }

        /*
         if(intval($out['client']) && !$this->app->isClient('administrator')){
            parse_str(Uri::getInstance()->getQuery(), $data1);
            $this_page = Uri::getInstance()->base() . 'administrator/index.php?'.http_build_query($data1, '', '&');
        }else{
            parse_str(Uri::getInstance()->getQuery(), $data1);
            $urlex = explode('?', Uri::getInstance()->toString());
            $this_page = $urlex[0] . '?' . http_build_query($data1, '', '&');
        }
         */
        if (intval($out['client']) && !$this->app->isClient('administrator')) {
            $this_page = Uri::getInstance()->base() . 'administrator/index.php?' . Uri::getInstance()->getQuery();
        } else {
            $this_page = Uri::getInstance()->toString();
        }

        PluginHelper::importPlugin('contentbuilderng_verify', $plugin);

        $eventResult = $this->app->getDispatcher()->dispatch('onSetup', new \Joomla\CMS\Event\GenericEvent('onSetup', array($this_page, $out)));
        $setup_result = $eventResult->getArgument('result') ?: [];
        if (!implode('', $setup_result)) {

            if (!$input->getBool('verify', 0)) {

                if ($this->app->isClient('administrator')) {
                    $local = explode('/', Uri::getInstance()->base());
                    unset($local[count($local) - 1]);
                    unset($local[count($local) - 1]);
                    parse_str(Uri::getInstance()->getQuery(), $data);
                    $this_page = implode('/', $local) . '/index.php?' . http_build_query($data, '', '&') . '&verify=1&verification_id=' . $verification_id;
                } else {
                    parse_str(Uri::getInstance()->getQuery(), $data);
                    $urlex = explode('?', Uri::getInstance()->toString());
                    $this_page = $urlex[0] . '?' . http_build_query($data, '', '&') . '&verify=1&verification_id=' . $verification_id;
                }

                $eventResult = $this->app->getDispatcher()->dispatch('onForward', new \Joomla\CMS\Event\GenericEvent('onForward', array($this_page, $out)));
                $forward_result = $eventResult->getArgument('result') ?: [];
                $forward = implode('', $forward_result);

                if ($forward) {
                    $this->app->redirect($this->safeRedirectTarget($forward));
                }
            } else {

                if ($verification_id) {

                    $msg = '';
                    $eventResult = $this->app->getDispatcher()->dispatch('onVerify', new \Joomla\CMS\Event\GenericEvent('onVerify', array($this_page, $out)));
                    $verify_result = $eventResult->getArgument('result') ?: [];

                    if (count($verify_result)) {

                        if ($verify_result[0] === false) {

                            $msg = Text::_('COM_CONTENTBUILDERNG_VERIFICATION_FAILED');

                        } else {

                            if (isset($verify_result[0]['msg']) && $verify_result[0]['msg']) {

                                $msg = $verify_result[0]['msg'];
                            } else {
                                if (isset($out['verification_msg']) && $out['verification_msg']) {
                                    $msg = urldecode($out['verification_msg']);
                                } else {
                                    $msg = Text::_('COM_CONTENTBUILDERNG_VERIFICATION_SUCCESS');
                                }
                            }

                            if ((!$out['client'] && (!isset($out['return-site']) || !$out['return-site'])) || ($out['client'] && (!isset($out['return-admin']) || !$out['return-admin']))) {
                                if (intval($out['client']) && !$this->app->isClient('administrator')) {
                                    $redirect_view = Uri::getInstance()->base() . 'administrator/index.php?option=com_contentbuilderng&task=list.display&lang=' . $input->getCmd('lang', '') . '&id=' . $out['verify_view'];
                                } else {
                                    $redirect_view = 'index.php?option=com_contentbuilderng&task=list.display&lang=' . $input->getCmd('lang', '') . '&id=' . $out['verify_view'];
                                }
                            }

                            $query = $this->getDatabase()->getQuery(true)
                                ->select($this->getDatabase()->quoteName('id'))
                                ->from($this->getDatabase()->quoteName('#__contentbuilderng_users'))
                                ->where($this->getDatabase()->quoteName('userid') . ' = ' . $this->getDatabase()->quote($user_id))
                                ->where($this->getDatabase()->quoteName('form_id') . ' = ' . (int) $out['verify_view']);
                            $this->getDatabase()->setQuery($query);
                            $usertableid = $this->getDatabase()->loadResult();

                            $levels = explode(',', $out['verify_levels']);
                            $___now = $_now->toSql();
                            if ($usertableid) {
                                $query = $this->getDatabase()->getQuery(true)
                                    ->update($this->getDatabase()->quoteName('#__contentbuilderng_users'))
                                    ->set($this->getDatabase()->quoteName('published') . ' = 1')
                                    ->where($this->getDatabase()->quoteName('id') . ' = ' . (int) $usertableid);
                                if (in_array('view', $levels)) {
                                    $query->set($this->getDatabase()->quoteName('verified_view') . ' = 1')
                                        ->set($this->getDatabase()->quoteName('verification_date_view') . ' = ' . $this->getDatabase()->quote($___now));
                                }
                                if (in_array('new', $levels)) {
                                    $query->set($this->getDatabase()->quoteName('verified_new') . ' = 1')
                                        ->set($this->getDatabase()->quoteName('verification_date_new') . ' = ' . $this->getDatabase()->quote($___now));
                                }
                                if (in_array('edit', $levels)) {
                                    $query->set($this->getDatabase()->quoteName('verified_edit') . ' = 1')
                                        ->set($this->getDatabase()->quoteName('verification_date_edit') . ' = ' . $this->getDatabase()->quote($___now));
                                }
                                $this->getDatabase()->setQuery($query);
                                $this->getDatabase()->execute();
                            } else {
                                $columns = ['published', 'userid', 'form_id'];
                                $values = ['1', $this->getDatabase()->quote($user_id), (int) $out['verify_view']];
                                if (in_array('view', $levels)) {
                                    array_unshift($columns, 'verification_date_view');
                                    array_unshift($columns, 'verified_view');
                                    array_unshift($values, $this->getDatabase()->quote($___now));
                                    array_unshift($values, '1');
                                }
                                if (in_array('new', $levels)) {
                                    array_splice($columns, count($columns) - 3, 0, ['verified_new', 'verification_date_new']);
                                    array_splice($values, count($values) - 3, 0, ['1', $this->getDatabase()->quote($___now)]);
                                }
                                if (in_array('edit', $levels)) {
                                    array_splice($columns, count($columns) - 3, 0, ['verified_edit', 'verification_date_edit']);
                                    array_splice($values, count($values) - 3, 0, ['1', $this->getDatabase()->quote($___now)]);
                                }
                                $query = $this->getDatabase()->getQuery(true)
                                    ->insert($this->getDatabase()->quoteName('#__contentbuilderng_users'))
                                    ->columns($this->getDatabase()->quoteName($columns))
                                    ->values(implode(', ', $values));
                                $this->getDatabase()->setQuery($query);
                                $this->getDatabase()->execute();
                            }

                            $verification_data = ($verification_data ? '&' : '') . '';
                            if (isset($verify_result[0]['data']) && is_array($verify_result[0]['data']) && count($verify_result[0]['data'])) {
                                foreach ($verify_result[0]['data'] as $key => $value) {
                                    $verification_data .= urlencode(str_replace(array("\r", "\n"), '', $key)) . "=" . urlencode(str_replace(array("\r", "\n"), '', $value)) . "&";
                                }
                                $verification_data = rtrim($verification_data, '&');
                            }

                            $query = $this->getDatabase()->getQuery(true)
                                ->update($this->getDatabase()->quoteName('#__contentbuilderng_verifications'))
                                ->set($this->getDatabase()->quoteName('verification_hash') . " = ''")
                                ->set($this->getDatabase()->quoteName('is_test') . ' = ' . (isset($verify_result[0]['is_test']) ? (int) isset($verify_result[0]['is_test']) : 0))
                                ->set($this->getDatabase()->quoteName('verification_date') . ' = ' . $this->getDatabase()->quote($___now))
                                ->where($this->getDatabase()->quoteName('verification_hash') . ' = ' . $this->getDatabase()->quote($verification_id))
                                ->where($this->getDatabase()->quoteName('verification_hash') . " <> ''")
                                ->where($this->getDatabase()->quoteName('verification_date') . ' IS NULL');
                            if ($verification_data) {
                                $query->set($this->getDatabase()->quoteName('verification_data') . ' = concat(' . $this->getDatabase()->quoteName('verification_data') . ', ' . $this->getDatabase()->quote($verification_data) . ')');
                            }
                            $this->getDatabase()->setQuery($query);
                            $this->getDatabase()->execute();

                            // token check if given
                            if ($input->get('token', '', 'string')) {
                                $this->activate($input->get('token', '', 'string'));
                            }

                            // exit if requested
                            if (count($verify_result) && isset($verify_result[0]['exit']) && $verify_result[0]['exit']) {

                                @ob_end_clean();

                                if (isset($verify_result[0]['header']) && $verify_result[0]['header']) {
                                    header($verify_result[0]['header']);
                                }

                                exit;
                            }
                        }
                    }
                } else {
                    $msg = Text::_('COM_CONTENTBUILDERNG_VERIFICATION_NOT_EXECUTED');
                }

                $this->app->enqueueMessage($msg, 'warning');

                $returnSite = $this->decodeInternalReturn($out['return-site'] ?? '');
                $returnAdmin = $this->decodeInternalReturn($out['return-admin'] ?? '');

                if (!$out['client']) {
                    $target = $redirect_view ? $redirect_view : ($returnSite ?: 'index.php');
                    $this->app->redirect($this->safeRedirectTarget($target));
                } else {
                    $target = $redirect_view ? $redirect_view : ($returnAdmin ?: 'index.php');
                    $this->app->redirect($this->safeRedirectTarget($target));
                }
            }
        } else {
            throw new \Exception(Text::sprintf('COM_CONTENTBUILDERNG_VERIFICATION_SETUP_FAILED', implode('', $setup_result)), 500);
        }
    }

    public function activate_by_admin(string $token)
    {

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.create', 'com_users')) {

            throw new \Exception('You are not allowed to perform this action.', 500);
        }

        $this->app->getLanguage()->load('com_users', JPATH_SITE);

        $userParams = ComponentHelper::getParams('com_users');
        $db = $this->getDatabase();

        // Get the user id based on the token.
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('activation') . ' = ' . $db->quote($token))
            ->where($db->quoteName('block') . ' = 1')
            ->where($db->quoteName('lastvisitDate') . ' = ' . $db->quote($db->getNullDate()));
        $db->setQuery($query);
        $userId = (int) $db->loadResult();

        // Check for a valid user id.
        if (!$userId) {
            throw new \Exception(Text::_('COM_USERS_ACTIVATION_TOKEN_NOT_FOUND'), 500);
        }

        // Load the users plugin group.
        PluginHelper::importPlugin('user');

        $query = $db->getQuery(true);

        // Activate the user.
        // To create a user instance:
        $user = $this->getUserFactory()->loadUserById($userId);
        $user->set('activation', '');
        $user->set('block', '0');

        // Store the user object.
        if (!$user->save()) {
            throw new \Exception(
                Text::sprintf('COM_USERS_REGISTRATION_ACTIVATION_SAVE_FAILED', Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED')),
                500
            );
        }

        $params = ComponentHelper::getParams('com_users');

        // Compile the notification mail values.
        $data = $user->getProperties();
        $data['fromname'] = (string) $this->app->get('fromname');
        $data['mailfrom'] = (string) $this->app->get('mailfrom');
        $data['sitename'] = (string) $this->app->get('sitename');
        $data['siteurl'] = Uri::root();

        $sendpassword = $params->get('sendpassword', 1);

        $emailSubject = Text::sprintf(
            'COM_USERS_EMAIL_ACCOUNT_DETAILS',
            $data['name'],
            $data['sitename']
        );

        if ($sendpassword) {
            $emailBody = Text::sprintf(
                'COM_USERS_EMAIL_REGISTERED_BODY',
                $data['name'],
                $data['sitename'],
                $data['siteurl'],
                $data['username'],
                $data['password_clear']
            );
        } else {
            $emailBody = Text::sprintf(
                'COM_USERS_EMAIL_REGISTERED_BODY_NOPW',
                $data['name'],
                $data['sitename'],
                $data['siteurl']
            );
        }


        // Send the registration email.
        $return = $this->createMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);

        $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_ADMINACTIVATE_SUCCESS'));
        $this->app->redirect(Route::_('index.php?option=com_users', false));
    }

    public function activate(string $token)
    {
        $this->app->getLanguage()->load('com_users', JPATH_SITE);

        $userParams = ComponentHelper::getParams('com_users');
        $db = $this->getDatabase();

        // Get the user id based on the token.
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('activation') . ' = ' . $db->quote($token))
            ->where($db->quoteName('block') . ' = 1')
            ->where($db->quoteName('lastvisitDate') . ' = ' . $db->quote($db->getNullDate()));
        $db->setQuery($query);
        $userId = (int) $db->loadResult();

        // Check for a valid user id.
        if (!$userId) {
            throw new \Exception(Text::_('COM_USERS_ACTIVATION_TOKEN_NOT_FOUND'), 500);
        }

        // Load the users plugin group.
        PluginHelper::importPlugin('user');

        $query = $db->getQuery(true);
        $userFactory = $this->getUserFactory();

        // Activate the user.
        $user = $userFactory->loadUserById($userId);

        // Admin activation is on and user is verifying their email
        if (($userParams->get('useractivation') == 2) && !$user->getParam('activate', 0)) {
            $uri = Uri::getInstance();

            // Compile the admin notification mail values.
            $data = $user->getProperties();
            $data['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword());
            $user->set('activation', $data['activation']);
            $data['siteurl'] = Uri::root();
            $data['activate'] = Uri::root() . 'index.php?option=com_contentbuilderng&view=verify&token=' . $data['activation'] . '&verify_by_admin=1&format=raw';

            // Remove administrator/ from activate url in case this method is called from admin
            if ($this->app->isClient('administrator')) {
                $adminPos = strrpos($data['activate'], 'administrator/');
                $data['activate'] = substr_replace($data['activate'], '', $adminPos, 14);
            }

            $data['fromname'] = (string) $this->app->get('fromname');
            $data['mailfrom'] = (string) $this->app->get('mailfrom');
            $data['sitename'] = (string) $this->app->get('sitename');
            $user->setParam('activate', 1);
            $emailSubject = Text::sprintf(
                'COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_SUBJECT',
                $data['name'],
                $data['sitename']
            );

            $emailBody = Text::sprintf(
                'COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_BODY',
                $data['sitename'],
                $data['name'],
                $data['email'],
                $data['username'],
                $data['activate']
            );

            // Get all admin users
            $query->clear()
                ->select($db->quoteName(array('name', 'email', 'sendEmail', 'id')))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('sendEmail') . ' = ' . 1);

            $db->setQuery($query);

            try {
                $rows = $db->loadObjectList();
            } catch (\RuntimeException $e) {
                $this->app->enqueueMessage(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
                return false;
            }

            // Send mail to all users with users creating permissions and receiving system emails
            foreach ($rows as $row) {
                $usercreator = $userFactory->loadUserById((int) $row->id);

                if ($usercreator->authorise('core.create', 'com_users')) {
                    $return = $this->createMailer()->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBody);

                    // Check for an error.
                    if ($return !== true) {
                        $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'), 'error');
                        return false;
                    }
                }
            }

            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_VERIFY_SUCCESS'));
        }
        // Admin activation is on and admin is activating the account
        elseif (($userParams->get('useractivation') == 2) && $user->getParam('activate', 0)) {
            $user->set('activation', '');
            $user->set('block', '0');

            // Compile the user activated notification mail values.
            $data = $user->getProperties();
            $user->setParam('activate', 0);
            $data['fromname'] = (string) $this->app->get('fromname');
            $data['mailfrom'] = (string) $this->app->get('mailfrom');
            $data['sitename'] = (string) $this->app->get('sitename');
            $data['siteurl'] = Uri::root();
            $emailSubject = Text::sprintf(
                'COM_USERS_EMAIL_ACTIVATED_BY_ADMIN_ACTIVATION_SUBJECT',
                $data['name'],
                $data['sitename']
            );

            $emailBody = Text::sprintf(
                'COM_USERS_EMAIL_ACTIVATED_BY_ADMIN_ACTIVATION_BODY',
                $data['name'],
                $data['siteurl'],
                $data['username']
            );

            $return = $this->createMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);

            // Check for an error.
            if ($return !== true) {
                $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'), 'error');
                return false;
            }

            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_VERIFY_SUCCESS'));
        } else {

            $user->set('activation', '');
            $user->set('block', '0');

            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_SAVE_SUCCESS'));

        }

        // Store the user object.
        if (!$user->save()) {
            throw new \Exception(
                Text::sprintf('COM_USERS_REGISTRATION_ACTIVATION_SAVE_FAILED', Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED')),
                500
            );
        }

        return true;
    }
}
