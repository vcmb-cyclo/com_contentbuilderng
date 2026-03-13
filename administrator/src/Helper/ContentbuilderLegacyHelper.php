<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @copyright   Copyright © 2026 by XDA+GIL
 */


namespace CB\Component\Contentbuilderng\Administrator\Helper;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\Service\ArticleService;
use CB\Component\Contentbuilderng\Administrator\Service\FormResolverService;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\LegacyUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\MenuService;
use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateRenderService;
use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;

final class ContentbuilderLegacyHelper
{
    private static function getTextUtilityService(): TextUtilityService
    {
        return new TextUtilityService();
    }

    /**
     * Decode base64 packed payload.
     * New format: base64("j:" + json)
     * Legacy format: base64(serialize(...))
     */
    public static function decodePackedData($raw, $default = null, bool $assoc = false)
    {
        if (!class_exists(PackedDataHelper::class)) {
            require_once __DIR__ . '/PackedDataHelper.php';
        }

        return PackedDataHelper::decodePackedData($raw, $default, $assoc);
    }

    /**
     * Encode payload to base64 JSON (prefixed with j:).
     * Falls back to legacy serialize() if JSON encoding fails.
     */
    public static function encodePackedData($value): string
    {
        if (!class_exists(PackedDataHelper::class)) {
            require_once __DIR__ . '/PackedDataHelper.php';
        }

        return PackedDataHelper::encodePackedData($value);
    }

    /**
     * Resolve "hidden filter" values safely without eval().
     * Supports only known dynamic tokens.
     */
    public static function sanitizeHiddenFilterValue(string $value): string
    {
        return (new LegacyUtilityService())->sanitizeHiddenFilterValue($value);
    }

    public static function makeSafeFolder($path)
    {
        if (!class_exists(PathService::class)) {
            require_once dirname(__DIR__) . '/Service/PathService.php';
        }

        if (method_exists(Factory::class, 'getContainer')) {
            try {
                return Factory::getContainer()->get(PathService::class)->makeSafeFolder($path);
            } catch (\Throwable $e) {
            }
        }

        return (new PathService())->makeSafeFolder($path);
    }

    public static function getPagination($limitstart, $limit, $total)
    {
        return (new LegacyUtilityService())->getPagination($limitstart, $limit, $total);
    }

    /**
     * @deprecated 6.1.1 Use RatingHelper::getRating() instead.
     */
    public static function getRating($form_id, $record_id, $colRating, $rating_slots, $lang, $rating_allowed, $rating_count, $rating_sum)
    {
        return RatingHelper::getRating(
            $form_id,
            $record_id,
            $colRating,
            $rating_slots,
            $lang,
            $rating_allowed,
            $rating_count,
            $rating_sum
        );
    }

    public static function execPhpValue($code)
    {
        return self::sanitizeHiddenFilterValue((string) $code);
    }

    public static function execPhp($result)
    {
        return (new LegacyUtilityService())->execPhp($result);
    }

    public static function createBackendMenuItem($contentbuilderng_form_id, $name, $update)
    {
        (new MenuService())->createBackendMenuItem($contentbuilderng_form_id, $name, $update);
    }

    public static function getLanguageCodes()
    {
        return (new FormSupportService(new PathService()))->getLanguageCodes();
    }

    public static function applyItemWrappers($contentbuilderng_form_id, array $items, $form)
    {
        return (new TemplateRenderService())->applyItemWrappers($contentbuilderng_form_id, $items, $form);
    }

    public static function createBackendMenuItem15($contentbuilderng_form_id, $name, $update)
    {
        (new MenuService())->createBackendMenuItem15($contentbuilderng_form_id, $name, $update);
    }

    public static function createBackendMenuItem16($contentbuilderng_form_id, $name, $update)
    {
        (new MenuService())->createBackendMenuItem16($contentbuilderng_form_id, $name, $update);
    }

    public static function createBackendMenuItem3($contentbuilderng_form_id, $name, $update)
    {
        (new MenuService())->createBackendMenuItem3($contentbuilderng_form_id, $name, $update);
    }

    public static function createDetailsSample($contentbuilderng_form_id, $form, $plugin)
    {
        return (new FormSupportService(new PathService()))->createDetailsSample($contentbuilderng_form_id, $form, $plugin);
    }

    public static function createEmailSample($contentbuilderng_form_id, $form, $html = false)
    {
        return (new FormSupportService(new PathService()))->createEmailSample($contentbuilderng_form_id, $form, $html);
    }

    public static function createEditableSample($contentbuilderng_form_id, $form, $plugin)
    {
        return (new FormSupportService(new PathService()))->createEditableSample($contentbuilderng_form_id, $form, $plugin);
    }

    public static function synchElements($contentbuilderng_form_id, $form): array
    {
        return (new FormSupportService(new PathService()))->synchElements($contentbuilderng_form_id, $form);
    }

    public static function getTypes()
    {
        return (new FormSupportService(new PathService()))->getTypes();
    }

    public static function getForms($type)
    {
        return (new FormSupportService(new PathService()))->getForms($type);
    }

    public static function getForm($type, $reference_id)
    {
        return (new FormResolverService())->getForm($type, $reference_id);
    }

    public static function getListSearchableElements($contentbuilderng_form_id)
    {
        return (new ListSupportService())->getListSearchableElements((int) $contentbuilderng_form_id);
    }

    public static function getListLinkableElements($contentbuilderng_form_id)
    {
        return (new ListSupportService())->getListLinkableElements((int) $contentbuilderng_form_id);
    }

    public static function getListEditableElements($contentbuilderng_form_id)
    {
        return (new ListSupportService())->getListEditableElements((int) $contentbuilderng_form_id);
    }

    public static function getListNonEditableElements($contentbuilderng_form_id)
    {
        return (new ListSupportService())->getListNonEditableElements((int) $contentbuilderng_form_id);
    }

    public static function getTemplate($contentbuilderng_form_id, $record_id, array $record, array $elements_allowed, $quiet_skip = false)
    {
        return (new TemplateRenderService())->getTemplate($contentbuilderng_form_id, $record_id, $record, $elements_allowed, $quiet_skip);
    }

    public static function allhtmlentities($string)
    {
        return self::getTextUtilityService()->allhtmlentities($string);
    }

    public static function cleanString($string)
    {
        return self::getTextUtilityService()->cleanString($string);
    }

    public static function getFormElementsPlugins()
    {
        return (new FormSupportService(new PathService()))->getFormElementsPlugins();
    }

    public static function getEmailTemplate($contentbuilderng_form_id, $record_id, array $record, array $elements_allowed, $isAdmin)
    {
        return (new TemplateRenderService())->getEmailTemplate($contentbuilderng_form_id, $record_id, $record, $elements_allowed, $isAdmin);
    }

    public static function getEditableTemplate($contentbuilderng_form_id, $record_id, array $record, array $elements_allowed, $execPrepare = true)
    {
        return (new TemplateRenderService())->getEditableTemplate($contentbuilderng_form_id, $record_id, $record, $elements_allowed, $execPrepare);
    }

    public static function createArticle($contentbuilderng_form_id, $record_id, array $record, array $elements_allowed, $title_field = '', $metadata = null, $config = array(), $full = false, $limited_options = true, $menu_cat_id = null)
    {
        return (new ArticleService())->createArticle($contentbuilderng_form_id, $record_id, $record, $elements_allowed, $title_field, $metadata, $config, $full, $limited_options, $menu_cat_id);
    }

    public static function setPermissions($form_id, $record_id = 0, $suffix = '')
    {
        (new PermissionService())->setPermissions($form_id, $record_id, (string) $suffix);
    }

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

    public static function stringURLUnicodeSlug($string)
    {
        return self::getTextUtilityService()->stringURLUnicodeSlug($string);
    }

    public static function checkPermissions($action, $error_msg, $suffix = '', $auth = false)
    {
        return (new PermissionService())->checkPermissions($action, (string) $error_msg, (string) $suffix, (bool) $auth);
    }

    public static function authorize($action)
    {
        return (new PermissionService())->authorize($action);
    }

    public static function authorizeFe($action)
    {
        return (new PermissionService())->authorizeFe($action);
    }

    public static function getListStates($id)
    {
        return (new ListSupportService())->getListStates((int) $id);
    }

    public static function getStateColors($items, $id)
    {
        return (new ListSupportService())->getStateColors((array) $items, (int) $id);
    }

    public static function getStateTitles($items, $id)
    {
        return (new ListSupportService())->getStateTitles((array) $items, (int) $id);
    }

    public static function getRecordsPublishInfo($items, $type, $reference_id)
    {
        return (new ListSupportService())->getRecordsPublishInfo((array) $items, (string) $type, $reference_id);
    }

    public static function getRecordsLanguage($items, $type, $reference_id)
    {
        return (new ListSupportService())->getRecordsLanguage((array) $items, (string) $type, $reference_id);
    }
}
