<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

final class PreviewLinkHelper
{
    public static function buildPayload(string $target, int $until, int $actorId, string $actorName, int $userId): string
    {
        return $target . '|' . $until . '|' . $actorId . '|' . $actorName . '|' . $userId;
    }

    public static function buildQuery(int $until, int $actorId, string $actorName, int $userId, string $sig, string $adminReturn = ''): string
    {
        if ($until <= 0 || $sig === '') {
            return '';
        }

        return '&cb_preview=1'
            . '&cb_preview_until=' . $until
            . '&cb_preview_actor_id=' . $actorId
            . '&cb_preview_actor_name=' . rawurlencode($actorName)
            . '&cb_preview_user_id=' . $userId
            . '&cb_preview_sig=' . rawurlencode($sig)
            . ($adminReturn !== '' ? '&cb_admin_return=' . rawurlencode($adminReturn) : '');
    }

    public static function buildHiddenFields(int $until, int $actorId, string $actorName, int $userId, string $sig, string $adminReturn = ''): string
    {
        if ($until <= 0 || $sig === '') {
            return '';
        }

        return '<input type="hidden" name="cb_preview" value="1" />' . "\n"
            . '<input type="hidden" name="cb_preview_until" value="' . (int) $until . '" />' . "\n"
            . '<input type="hidden" name="cb_preview_actor_id" value="' . (int) $actorId . '" />' . "\n"
            . '<input type="hidden" name="cb_preview_actor_name" value="' . htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') . '" />' . "\n"
            . '<input type="hidden" name="cb_preview_user_id" value="' . (int) $userId . '" />' . "\n"
            . '<input type="hidden" name="cb_preview_sig" value="' . htmlspecialchars($sig, ENT_QUOTES, 'UTF-8') . '" />'
            . ($adminReturn !== '' ? "\n" . '<input type="hidden" name="cb_admin_return" value="' . htmlspecialchars($adminReturn, ENT_QUOTES, 'UTF-8') . '" />' : '');
    }
}
