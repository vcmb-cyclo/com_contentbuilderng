<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;

final class FormSourceFactory
{
    /**
     * Validates signed admin preview links generated in backend.
     * Mirrors frontend controllers so source loading can safely allow unpublished forms.
     */
    private static function isSignedAdminPreviewRequest(int $formId): bool
    {
        $app = Factory::getApplication();
        $input = $app->input;

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

        $payload = $formId . '|' . $until;
        $expected = hash_hmac('sha256', $payload, $secret);
        $actorPayload = $payload . '|' . $actorId . '|' . $actorName;
        $actorExpected = hash_hmac('sha256', $actorPayload, $secret);

        if (($actorId > 0 || $actorName !== '') && hash_equals($actorExpected, $sig)) {
            return true;
        }

        return hash_equals($expected, $sig);
    }

    /**
     * Resolve a source form object using Joomla 6 namespaced type classes first,
     * and fallback to the dynamic loader for custom/third-party types.
     *
     * @param   string     $type
     * @param   int|string $referenceId
     *
     * @return  object|null
     */
    public static function getForm(string $type, $referenceId)
    {
        static $cache = [];

        $type = trim($type);
        $referenceId = (int) $referenceId;

        if ($type === '' || $referenceId <= 0) {
            return null;
        }

        if (isset($cache[$type][$referenceId])) {
            return $cache[$type][$referenceId];
        }

        $resolved = self::createKnownTypeForm($type, $referenceId);

        if (!is_array($cache)) {
            $cache = [];
        }
        $cache[$type][$referenceId] = $resolved;

        return is_object($resolved) ? $resolved : null;
    }

    /**
     * Instantiate known source type classes directly.
     */
    private static function createKnownTypeForm(string $type, int $referenceId)
    {
        $normalizedType = $type === 'com_contentbuilder' ? 'com_contentbuilderng' : $type;

        $classMap = [
            'com_contentbuilderng' => 'CB\\Component\\Contentbuilderng\\Administrator\\types\\contentbuilderng_com_contentbuilderng',
            'com_breezingforms' => 'CB\\Component\\Contentbuilderng\\Administrator\\types\\contentbuilderng_com_breezingforms',
        ];

        if (!isset($classMap[$normalizedType])) {
            return null;
        }

        $file = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/' . $normalizedType . '.php';
        if (is_file($file)) {
            require_once $file;
        }

        $class = $classMap[$normalizedType];
        if (!class_exists($class)) {
            return null;
        }

        $app = Factory::getApplication();
        $allowUnpublishedSource = $app->isClient('administrator')
            || $app->input->getBool('cb_preview_ok', false)
            || self::isSignedAdminPreviewRequest((int) $app->input->getInt('id', 0));

        $form = new $class($referenceId);
        $exists = !property_exists($form, 'exists') || (bool) ($form->exists ?? false);

        if ($allowUnpublishedSource && !$exists) {
            try {
                $previewForm = new $class($referenceId, false);
                if (is_object($previewForm)) {
                    $form = $previewForm;
                }
            } catch (\ArgumentCountError|\TypeError $e) {
                // Type class does not support an unpublished flag; keep initial instance.
            }
        }

        return $form;
    }
}
