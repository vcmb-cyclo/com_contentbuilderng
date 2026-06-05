<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

final class ApiPermissionRequirementService
{
    /**
     * @return list<string>
     */
    public function getRequiredPermissions(string $method, string $action, int $recordId): array
    {
        $method = strtoupper(trim($method));
        $action = trim($action);

        if ($action === 'stats') {
            return ['stats'];
        }

        if ($action === 'get-unique-values') {
            return ['api', 'listaccess'];
        }

        if ($action === 'rating') {
            return ['api', 'rating'];
        }

        if ($action !== '') {
            return ['api'];
        }

        if ($method === 'GET') {
            return $recordId > 0
                ? ['api', 'view']
                : ['api', 'view', 'listaccess'];
        }

        if (in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
            return ['api', 'edit'];
        }

        return ['api'];
    }
}
