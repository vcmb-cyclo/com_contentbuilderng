<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;

final class DebugPermissionHelper
{
    private const ACTIONS = [
        'listaccess',
        'view',
        'new',
        'edit',
        'delete',
        'state',
        'publish',
        'api',
        'stats',
        'fullarticle',
        'language',
        'rating',
    ];

    /**
     * @return array<string, bool>
     */
    public static function resolvePermissions(
        PermissionService $permissionService,
        $app,
        int $formId,
        bool $frontend
    ): array {
        $permissions = [];

        foreach (self::ACTIONS as $action) {
            $permissions[$action] = $action === 'new' && $formId > 0
                ? self::authorizeNewForForm($permissionService, $app, $formId, $frontend)
                : self::authorize($permissionService, $action, $frontend);
        }

        return $permissions;
    }

    private static function authorize(PermissionService $permissionService, string $action, bool $frontend): bool
    {
        return $frontend
            ? $permissionService->authorizeFe($action)
            : $permissionService->authorize($action);
    }

    private static function authorizeNewForForm(PermissionService $permissionService, $app, int $formId, bool $frontend): bool
    {
        $session = $app->getSession();
        $suffix = $frontend ? '_fe' : '';
        $key = 'com_contentbuilderng.permissions' . $suffix;
        $missing = "\0contentbuilderng-debug-permissions-missing\0";
        $currentPermissions = $session->get($key, $missing);

        try {
            $permissionService->setPermissions($formId, 0, $suffix);

            return self::authorize($permissionService, 'new', $frontend);
        } finally {
            if ($currentPermissions === $missing) {
                $session->remove($key);
            } else {
                $session->set($key, $currentPermissions);
            }
        }
    }
}
