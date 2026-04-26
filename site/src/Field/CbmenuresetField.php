<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class CbmenuresetField extends FormField
{
    protected $type = 'Cbmenureset';

    private const MENU_OPTIONS_STYLE = 'com_contentbuilderng.menu-options.direct.css';
    private const MENU_OPTIONS_SCRIPT = 'com_contentbuilderng.menu-options.direct.js';

    protected function getInput()
    {
        $defaults = [];
        $skipFields = [
            'form_id',
            'forms',
            'record_id',
            'cb_controller',
            'cb_latest',
            'cb_menu_reset',
        ];

        $xml = $this->form?->getXml();
        if ($xml) {
            $fieldsets = $xml->xpath('//fieldset[@name="settings"]/field');
            if (is_array($fieldsets)) {
                foreach ($fieldsets as $field) {
                    $name = (string) ($field['name'] ?? '');
                    if ($name === '' || in_array($name, $skipFields, true)) {
                        continue;
                    }

                    $defaults[$name] = (string) ($field['default'] ?? '');
                }
            }
        }

        $buttonId = $this->id . '_button';
        $defaultsJson = json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $buttonLabel = Text::_('COM_CONTENTBUILDERNG_RESET');
        $confirmLabel = Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_CONFIRM');
        if ($confirmLabel === 'COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_CONFIRM') {
            $confirmLabel = $buttonLabel;
        }
        $tooltipLabel = Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_TOOLTIP');
        if ($tooltipLabel === 'COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_TOOLTIP') {
            $tooltipLabel = $buttonLabel;
        }
        $document = Factory::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        if (!$wa->assetExists('style', self::MENU_OPTIONS_STYLE)) {
            $wa->registerStyle(
                self::MENU_OPTIONS_STYLE,
                'media/com_contentbuilderng/css/menu-options.css',
                [],
                ['media' => 'all']
            );
        }
        if (!$wa->assetExists('script', self::MENU_OPTIONS_SCRIPT)) {
            $wa->registerScript(
                self::MENU_OPTIONS_SCRIPT,
                'media/com_contentbuilderng/js/menu-options.js',
                [],
                ['defer' => true],
                ['core']
            );
        }
        $wa->useStyle(self::MENU_OPTIONS_STYLE);
        $wa->useScript(self::MENU_OPTIONS_SCRIPT);

        $confirmText = htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8');
        $defaultsData = htmlspecialchars($defaultsJson ?: '{}', ENT_QUOTES, 'UTF-8');
        $tooltipText = htmlspecialchars($tooltipLabel, ENT_QUOTES, 'UTF-8');
        $panelId = $this->id . '_panel';

        return '
            <div id="' . $panelId . '" class="cb-menu-reset-panel d-flex justify-content-end w-100 mb-3" data-cb-menu-reset-panel>
                <button type="button" class="btn btn-sm btn-secondary" id="' . $buttonId . '" title="' . $tooltipText . '" data-bs-toggle="tooltip" data-bs-placement="top" data-cb-menu-reset-button data-cb-menu-reset-defaults="' . $defaultsData . '" data-cb-menu-reset-confirm="' . $confirmText . '">
                    <span class="fa-solid fa-rotate-left me-1" aria-hidden="true"></span>
                    <span>' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '</span>
                </button>
            </div>
        ';
    }
}
