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

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

final class PackedDataHelper
{
    /**
     * Decode a base64 JSON packed payload.
     */
    public static function decodePackedData($raw, $default = null, bool $assoc = false)
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = base64_decode((string) $raw, true);
        if ($decoded === false) {
            return $default;
        }

        if (strpos($decoded, 'j:') !== 0) {
            return $default;
        }

        try {
            return json_decode(substr($decoded, 2), $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return $default;
        }

        return $default;
    }

    /**
     * Encode payload to base64 JSON (prefixed with j:).
     */
    public static function encodePackedData($value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return base64_encode('j:' . $json);
    }
}
