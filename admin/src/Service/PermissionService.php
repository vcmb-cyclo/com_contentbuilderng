<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;

class PermissionService
{
    private readonly FormResolverService $formResolverService;

    public function __construct()
    {
        $this->formResolverService = new FormResolverService();
    }

    private function getApp(): CMSApplication
    {
        return Factory::getApplication();
    }

    private function getInput()
    {
        return $this->getApp()->input;
    }

    private function getCurrentUserId(): int
    {
        return (int) ($this->getApp()->getIdentity()->id ?? 0);
    }

    /**
     * @return int[]
     */
    private function getEffectiveGroupIds(): array
    {
        $groupIds = array_map('intval', Access::getGroupsByUser($this->getCurrentUserId()));

        if ($groupIds === []) {
            return [];
        }

        static $parentByGroupId = null;

        if ($parentByGroupId === null) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('parent_id'),
                ])
                ->from($db->quoteName('#__usergroups'));
            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];

            $parentByGroupId = [];
            foreach ($rows as $row) {
                $groupId = (int) ($row['id'] ?? 0);
                if ($groupId < 1) {
                    continue;
                }

                $parentByGroupId[$groupId] = (int) ($row['parent_id'] ?? 0);
            }
        }

        $effectiveGroupIds = [];
        foreach ($groupIds as $groupId) {
            while ($groupId > 0 && !isset($effectiveGroupIds[$groupId])) {
                $effectiveGroupIds[$groupId] = true;
                $groupId = $parentByGroupId[$groupId] ?? 0;
            }
        }

        return array_map('intval', array_keys($effectiveGroupIds));
    }

    public function setPermissions($formId, $recordId = 0, string $suffix = ''): void
    {
        /** @var CMSApplication $app */
        $app = $this->getApp();
        $session = $app->getSession();
        $key = 'com_contentbuilderng.permissions' . $suffix;
        $isAdminPreview = $this->isSignedAdminPreviewRequest((int) $formId) || $app->getInput()->getBool('cb_preview_ok', false);
        $formPublishedClause = $isAdminPreview ? '' : ' And published = 1';

        $session->remove($key);

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('type'),
                $db->quoteName('reference_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        if (!$isAdminPreview) {
            $query->where($db->quoteName('published') . ' = 1');
        }
        $db->setQuery($query);
        $type = $db->loadAssoc();

        $numRecordsQuery = '';

        if (is_array($type)) {
            $referenceId = $type['reference_id'];
            $resolvedType = $type['type'];
            $_type = $resolvedType;

            if (file_exists(JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/' . $resolvedType . '.php')) {
                require_once JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/' . $resolvedType . '.php';
                $class = 'CB\\Component\\Contentbuilderng\\Administrator\\types\\contentbuilderng_' . $resolvedType;

                if (class_exists($class)) {
                    $numRecordsQuery = call_user_func([$class, 'getNumRecordsQuery'], $referenceId, $this->getCurrentUserId());
                }
            } elseif (file_exists(JPATH_SITE . '/media/contentbuilderng/types/' . $resolvedType . '.php')) {
                require_once JPATH_SITE . '/media/contentbuilderng/types/' . $resolvedType . '.php';
                $class = 'contentbuilderng_' . $resolvedType;

                if (class_exists($class)) {
                    $numRecordsQuery = call_user_func([$class, 'getNumRecordsQuery'], $referenceId, $this->getCurrentUserId());
                }
            }
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('forms.config'),
                $db->quoteName('forms.verification_required_view'),
                $db->quoteName('forms.verification_required_new'),
                $db->quoteName('forms.verification_required_edit'),
                $db->quoteName('forms.verification_days_view'),
                $db->quoteName('forms.verification_days_new'),
                $db->quoteName('forms.verification_days_edit'),
                $db->quoteName('forms.verification_url_view'),
                $db->quoteName('forms.verification_url_new'),
                $db->quoteName('forms.verification_url_edit'),
                $db->quoteName('contentbuilderng_users.userid'),
                $db->quoteName('forms.limit_add'),
                $db->quoteName('forms.limit_edit'),
                ($numRecordsQuery ? '(' . $numRecordsQuery . ')' : "'0'") . ' AS ' . $db->quoteName('amount_records'),
                $db->quoteName('contentbuilderng_users.verified_view'),
                $db->quoteName('contentbuilderng_users.verified_new'),
                $db->quoteName('contentbuilderng_users.verified_edit'),
                $db->quoteName('contentbuilderng_users.verification_date_view'),
                $db->quoteName('contentbuilderng_users.verification_date_new'),
                $db->quoteName('contentbuilderng_users.verification_date_edit'),
                $db->quoteName('contentbuilderng_users.limit_add', 'user_limit_add'),
                $db->quoteName('contentbuilderng_users.limit_edit', 'user_limit_edit'),
                $db->quoteName('contentbuilderng_users.published'),
                ($recordId && !is_array($recordId)) ? $db->quoteName('contentbuilderng_records.edited') : "'0' AS " . $db->quoteName('edited'),
            ])
            ->from($db->quoteName('#__contentbuilderng_forms', 'forms'))
            ->join(
                'LEFT',
                $db->quoteName('#__contentbuilderng_users', 'contentbuilderng_users')
                . ' ON (' . $db->quoteName('contentbuilderng_users.form_id') . ' = ' . $db->quoteName('forms.id')
                . ' AND ' . $db->quoteName('contentbuilderng_users.userid') . ' = ' . $this->getCurrentUserId() . ')'
            )
            ->where($db->quoteName('forms.id') . ' = ' . (int) $formId);
        if ($recordId && !is_array($recordId)) {
            $query->join(
                'LEFT',
                $db->quoteName('#__contentbuilderng_records', 'contentbuilderng_records')
                . ' ON (' . $db->quoteName('contentbuilderng_records.type') . ' = ' . $db->quote(isset($_type) ? $_type : '')
                . ' AND ' . $db->quoteName('contentbuilderng_records.reference_id') . ' = ' . $db->quoteName('forms.reference_id')
                . ' AND ' . $db->quoteName('contentbuilderng_records.record_id') . ' = ' . $db->quote($recordId) . ')'
            );
        }
        if (!$isAdminPreview) {
            $query->where($db->quoteName('forms.published') . ' = 1');
        }
        $db->setQuery($query);
        $result = $db->loadAssoc();

        if (!is_array($result)) {
            $permissions = [
                'published' => false,
                'limit_edit' => false,
                'limit_add' => false,
                'verify_view' => false,
                'verify_new' => false,
                'verify_edit' => false,
            ];
            $session->set($key, $permissions);

            return;
        }

        $config = PackedDataHelper::decodePackedData($result['config'] ?? '', [], true);

        if (!is_array($config)) {
            $config = [];
        }

        $permissions = [];
        $permissions['published'] = true;

        if (($result['published'] ?? null) !== null && !(bool) $result['published']) {
            $permissions['published'] = false;
        }

        $permissions['limit_edit'] = true;

        if ((int) ($result['limit_edit'] ?? 0) > 0 && (int) ($result['user_limit_edit'] ?? 0) > 0 && (int) ($result['edited'] ?? 0) >= (int) ($result['user_limit_edit'] ?? 0)) {
            $permissions['limit_edit'] = false;
        } elseif ((int) ($result['limit_edit'] ?? 0) > 0 && (int) ($result['user_limit_edit'] ?? 0) <= 0 && (int) ($result['edited'] ?? 0) >= (int) ($result['limit_edit'] ?? 0)) {
            $permissions['limit_edit'] = false;
        }

        $permissions['limit_add'] = true;

        if ((int) ($result['limit_add'] ?? 0) > 0 && (int) ($result['user_limit_add'] ?? 0) > 0 && (int) ($result['amount_records'] ?? 0) >= (int) ($result['user_limit_add'] ?? 0)) {
            $permissions['limit_add'] = false;
        } elseif ((int) ($result['limit_add'] ?? 0) > 0 && (int) ($result['user_limit_add'] ?? 0) <= 0 && (int) ($result['amount_records'] ?? 0) >= (int) ($result['limit_add'] ?? 0)) {
            $permissions['limit_add'] = false;
        }

        $jdate = Factory::getDate();
        $permissions['verify_view'] = $this->resolveVerificationPermission('view', $result, $jdate->toSql());
        $permissions['verify_new'] = $this->resolveVerificationPermission('new', $result, $jdate->toSql());
        $permissions['verify_edit'] = $this->resolveVerificationPermission('edit', $result, $jdate->toSql());

        foreach (['view', 'edit', 'delete', 'state', 'publish', 'fullarticle', 'language', 'rating', 'api', 'listaccess', 'new'] as $action) {
            if (isset($config['own' . $suffix][$action]) && $config['own' . $suffix][$action]) {
                $permissions['own' . $suffix] ??= [];
                $permissions['own' . $suffix][$action] = ['own' => true, 'form_id' => $formId, 'record_id' => $recordId];
            }
        }

        $groups = $this->getEffectiveGroupIds();

        foreach ($groups as $group) {
            foreach (['view', 'new', 'edit', 'delete', 'state', 'publish', 'fullarticle', 'language', 'rating', 'api', 'stats', 'listaccess'] as $action) {
                if (isset($config['permissions' . $suffix][$group][$action]) && $config['permissions' . $suffix][$group][$action]) {
                    $permissions[$group] ??= [];
                    $permissions[$group][$action] = true;
                }
            }
        }

        $session->set($key, $permissions);
    }

    public function checkPermissions($action, $errorMsg, string $suffix = '', bool $auth = false)
    {
        $allowed = false;
        /** @var CMSApplication $app */
        $app = $this->getApp();
        $session = $app->getSession();
        $currentSessionId = $session->getId();
        $key = 'com_contentbuilderng.permissions' . $suffix;
        $permissions = $session->get($key, []);
        $publishedReturn = $permissions['published'] ?? false;

        if (!$publishedReturn) {
            return $this->deny($errorMsg, $auth);
        }

        if ($action === 'edit' && !($permissions['limit_edit'] ?? false)) {
            return $this->deny($errorMsg, $auth);
        }

        if ($action === 'new' && !($permissions['limit_add'] ?? false)) {
            return $this->deny($errorMsg, $auth);
        }

        if (in_array($action, ['edit', 'new', 'view', 'delete'], true)) {
            $myAction = $action === 'delete' ? 'edit' : $action;
            $verifyReturn = $permissions['verify_' . $myAction] ?? false;

            if ($verifyReturn !== true) {
                if ($verifyReturn === false) {
                    return $this->deny($errorMsg, $auth);
                }

                if (is_string($verifyReturn)) {
                    if ($auth) {
                        return false;
                    }

                    $app->redirect($verifyReturn);
                }
            }
        }

        $gids = $this->getEffectiveGroupIds();

        foreach ($permissions as $groupId => $groupAction) {
            if (isset($groupAction[$action]) && $groupAction[$action] && in_array($groupId, $gids, true)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed && isset($permissions['own' . $suffix][$action])) {
            $userReturn = $permissions['own' . $suffix][$action];

            if (is_array($userReturn) && !empty($userReturn['own'])) {
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                static $typeref;

                if (isset($typeref[(int) $userReturn['form_id']]) && is_array($typeref[(int) $userReturn['form_id']])) {
                    $typerefid = $typeref[(int) $userReturn['form_id']];
                } else {
                    $query = $db->getQuery(true)
                        ->select([
                            $db->quoteName('type'),
                            $db->quoteName('reference_id'),
                        ])
                        ->from($db->quoteName('#__contentbuilderng_forms'))
                        ->where($db->quoteName('id') . ' = ' . (int) $userReturn['form_id']);
                    $db->setQuery($query);
                    $typerefid = $db->loadAssoc();
                    $typeref[(int) $userReturn['form_id']] = $typerefid;
                }

                if (is_array($typerefid)) {
                    $form = $this->formResolverService->getForm($typerefid['type'], $typerefid['reference_id']);

                    if ($form && !isset($userReturn['record_id'])) {
                        $allowed = in_array($action, ['new', 'listaccess'], true);
                    } elseif (is_array($userReturn['record_id'])) {
                        foreach ($userReturn['record_id'] as $recid) {
                            $query = $db->getQuery(true)
                                ->select($db->quoteName('session_id'))
                                ->from($db->quoteName('#__contentbuilderng_records'))
                                ->where($db->quoteName('record_id') . ' = ' . $db->quote($recid))
                                ->where($db->quoteName('type') . ' = ' . $db->quote($typerefid['type']))
                                ->where($db->quoteName('reference_id') . ' = ' . $db->quote($typerefid['reference_id']));
                            $db->setQuery($query);
                            $sessionId = $db->loadResult();

                            if ($form && $sessionId != $currentSessionId && !$form->isOwner($this->getCurrentUserId(), $recid)) {
                                $allowed = false;
                                break;
                            }

                            $allowed = true;
                        }
                    } else {
                        $query = $db->getQuery(true)
                            ->select($db->quoteName('session_id'))
                            ->from($db->quoteName('#__contentbuilderng_records'))
                            ->where($db->quoteName('record_id') . ' = ' . $db->quote($userReturn['record_id']))
                            ->where($db->quoteName('type') . ' = ' . $db->quote($typerefid['type']))
                            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($typerefid['reference_id']));
                        $db->setQuery($query);
                        $sessionId = $db->loadResult();

                        if (
                            $form
                            && (
                                ((string) $userReturn['record_id'] === '0' || $userReturn['record_id'] == false)
                                    ? in_array($action, ['new', 'listaccess'], true)
                                    : ($sessionId == $currentSessionId || ($this->getCurrentUserId() > 0 && $form->isOwner($this->getCurrentUserId(), $userReturn['record_id'])))
                            )
                        ) {
                            $allowed = true;
                        }
                    }
                }
            }
        }

        if (!$allowed) {
            if ($auth) {
                return false;
            }

            $actionLabel = $action ?: 'action';
            $recordId = $this->getInput()->getInt('record_id', 0);
            $formId = $this->getInput()->getInt('id', 0);
            $details = [];

            if ($formId) {
                $details[] = 'formulaire #' . $formId;
            }

            if ($recordId) {
                $details[] = 'enregistrement #' . $recordId;
            }

            $context = $details ? ' (' . implode(', ', $details) . ')' : '';
            $fallbackMsg = 'Accès refusé : vous n’avez pas l’autorisation pour l’action "' . $actionLabel . '"' . $context . '.';
            $msg = trim((string) $errorMsg) !== '' ? $errorMsg : $fallbackMsg;
            $this->getApp()->enqueueMessage($msg, 'error');
            throw new NotAllowed($msg, 403);
        }

        return $auth ? true : $allowed;
    }

    public function authorize($action): bool
    {
        return (bool) $this->checkPermissions($action, '', '', true);
    }

    public function authorizeFe($action): bool
    {
        return (bool) $this->checkPermissions($action, '', '_fe', true);
    }

    public function setStoragePreviewPermissions(int $storageId, string $suffix = '_fe'): void
    {
        if ($storageId < 1) {
            return;
        }

        $app = $this->getApp();
        $session = $app->getSession();
        $key = 'com_contentbuilderng.permissions' . $suffix;

        $permissions = [
            'published' => true,
            'limit_edit' => true,
            'limit_add' => true,
            'verify_view' => true,
            'verify_new' => true,
            'verify_edit' => true,
            'own' . $suffix => [
                'view' => ['own' => true, 'form_id' => 0, 'record_id' => 0],
                'new' => ['own' => true, 'form_id' => 0, 'record_id' => 0],
                'edit' => ['own' => true, 'form_id' => 0, 'record_id' => 0],
                'listaccess' => ['own' => true, 'form_id' => 0, 'record_id' => 0],
            ],
        ];

        foreach ($this->getEffectiveGroupIds() as $groupId) {
            $permissions[(int) $groupId] = [
                'view' => true,
                'new' => true,
                'edit' => true,
                'listaccess' => true,
                'stats' => true,
            ];
        }

        $session->set($key, $permissions);
    }

    private function isSignedAdminPreviewRequest(int $formId): bool
    {
        $app = $this->getApp();
        $input = $app->getInput();

        if ($formId < 1 || !$input->getBool('cb_preview', false)) {
            return false;
        }

        $until = (int) $input->getInt('cb_preview_until', 0);
        $sig = trim((string) $input->getString('cb_preview_sig', ''));

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) $app->get('secret');

        if ($secret === '') {
            return false;
        }

        $actorId = (int) $input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $input->getString('cb_preview_actor_name', ''));
        $userId = (int) $input->getInt('cb_preview_user_id', 0);
        if ($userId < 1) {
            return false;
        }

        $payload = PreviewLinkHelper::buildPayload((string) $formId, $until, $actorId, $actorName, $userId);

        return hash_equals(hash_hmac('sha256', $payload, $secret), $sig);
    }

    private function resolveVerificationPermission(string $action, array $result, string $nowSql)
    {
        $permissionKey = 'verification_required_' . $action;

        if (empty($result[$permissionKey])) {
            return true;
        }

        $days = (float) ($result['verification_days_' . $action] ?? 0) * 86400;
        $date = !empty($result['verification_date_' . $action]) ? strtotime((string) $result['verification_date_' . $action]) : 0;
        $validUntil = $date + $days;
        $now = strtotime($nowSql);
        $verified = !empty($result['verified_' . $action]);
        $url = trim((string) ($result['verification_url_' . $action] ?? ''));

        if ($verified && ($now < $validUntil || (float) ($result['verification_days_' . $action] ?? 0) <= 0)) {
            return true;
        }

        return $url !== '' ? $url : false;
    }

    private function deny(string $errorMsg, bool $auth)
    {
        if ($auth) {
            return false;
        }

        $this->getApp()->enqueueMessage($errorMsg, 'error');
        throw new NotAllowed($errorMsg, 403);
    }
}
