<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class ApiFieldPermissionService
{
    /**
     * @return array<string,bool>
     */
    public function getAllowedReferenceMap(int $formId): array
    {
        if ($formId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('api_allowed') . ' = 1');
        $db->setQuery($query);

        $allowed = [];
        foreach ((array) $db->loadColumn() as $referenceId) {
            $referenceId = trim((string) $referenceId);
            if ($referenceId !== '') {
                $allowed[$referenceId] = true;
            }
        }

        return $allowed;
    }

    public function isReferenceAllowed(int $formId, string $referenceId): bool
    {
        $referenceId = trim($referenceId);

        return $referenceId !== '' && isset($this->getAllowedReferenceMap($formId)[$referenceId]);
    }
}
