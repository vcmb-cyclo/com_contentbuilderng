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

use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Input\Input;

final class PreviewColorModeHelper
{
    public const DEFAULT = 'default';
    public const LIGHT = 'light';
    public const DARK = 'dark';

    public static function resolve(Input $input, bool $previewActive): string
    {
        if (!$previewActive) {
            return self::DEFAULT;
        }

        $mode = $input->getCmd('cb_preview_color_mode', self::DEFAULT);

        return \in_array($mode, [self::LIGHT, self::DARK], true) ? $mode : self::DEFAULT;
    }

    public static function appendQuery(string $query, string $mode): string
    {
        return $mode === self::DEFAULT
            ? $query
            : $query . '&cb_preview_color_mode=' . rawurlencode($mode);
    }

    public static function appendHiddenField(string $fields, string $mode): string
    {
        if ($mode === self::DEFAULT) {
            return $fields;
        }

        return $fields . "\n"
            . '<input type="hidden" name="cb_preview_color_mode" value="'
            . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '" />';
    }

    public static function registerAssets(WebAssetManager $wa, string $mode): void
    {
        if ($mode === self::DEFAULT) {
            return;
        }

        $encodedMode = json_encode($mode, JSON_THROW_ON_ERROR);
        $wa->addInlineScript(
            <<<JS
(() => {
    const mode = {$encodedMode};
    const applyMode = () => {
        document.documentElement.setAttribute('data-bs-theme', mode);
        document.documentElement.style.colorScheme = mode;

        const updateRules = (rules) => {
            for (const rule of rules) {
                if (rule instanceof CSSMediaRule && rule.conditionText.includes('prefers-color-scheme')) {
                    rule.media.mediaText = mode === 'dark' ? 'all' : 'not all';
                }
                if (rule.cssRules) {
                    updateRules(rule.cssRules);
                }
            }
        };

        for (const sheet of document.styleSheets) {
            try {
                updateRules(sheet.cssRules);
            } catch (error) {
                // Cross-origin stylesheets are controlled by their own theme variables.
            }
        }
    };

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', applyMode, {once: true})
        : applyMode();
})();
JS
        );
    }
}
