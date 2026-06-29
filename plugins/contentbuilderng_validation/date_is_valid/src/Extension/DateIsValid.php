<?php

namespace CB\Plugin\ContentbuilderngValidation\DateIsValid\Extension;

/**
 * @version     6.0
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

final class DateIsValid extends CMSPlugin implements SubscriberInterface
{
    private const VALIDATION_NAME = 'date_is_valid';

    private function pushEventResult(Event $event, string $value): string
    {
        $results = $event->getArgument('result') ?: [];
        if (!is_array($results)) {
            $results = [$results];
        }
        $results[] = $value;
        $event->setArgument('result', $results);

        return $value;
    }

    private function isValidationEnabled(array $field): bool
    {
        $validations = array_filter(array_map('trim', explode(',', (string) ($field['validations'] ?? ''))));
        return in_array(self::VALIDATION_NAME, $validations, true);
    }

    public static function getSubscribedEvents(): array
    {
        return ['onValidate' => 'onValidate'];
    }

    public function onValidate(Event $event): string
    {
        $args = array_values($event->getArguments());
        $field = isset($args[0]) && is_array($args[0]) ? $args[0] : [];
        $value = $args[4] ?? null;
        if (!$field || !$this->isValidationEnabled($field)) {
            return '';
        }


        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_contentbuilderng_validation_date_is_valid', JPATH_ADMINISTRATOR);

        $options = $field['options'];

        $values = array();
        $values[0] = $value;

        if (is_array($value)) {
            $values = array();
            foreach ($value as $val) {
                $values[] = $val;
            }
        }

        foreach ($values as $val) {
            if (!ContentbuilderngHelper::isValidDate($val, isset($options->transfer_format) ? $options->transfer_format : 'YYYY-mm-dd')) {
                return $this->pushEventResult($event, Text::_('COM_CONTENTBUILDERNG_VALIDATION_DATE_IS_VALID') . ': ' . $field['label'] . ($val ? ' (' . $val . ')' : ''));
            }
        }

        return $this->pushEventResult($event, '');
    }
}
