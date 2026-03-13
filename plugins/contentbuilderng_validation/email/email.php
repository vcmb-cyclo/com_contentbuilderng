<?php
/**
 * @version     6.0
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

class plgContentbuilderng_validationEmail extends CMSPlugin implements SubscriberInterface
{
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

    public static function getSubscribedEvents(): array
    {
        return ['onValidate' => 'onValidate'];
    }

    public function onValidate(Event $event): string
    {
        $args = array_values($event->getArguments());
        $field = isset($args[0]) && is_array($args[0]) ? $args[0] : [];
        $value = $args[4] ?? null;
        if (!$field) {
            return '';
        }


        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_contentbuilderng_validation_email', JPATH_ADMINISTRATOR);

        $msg = '';

        if (!is_array($value)) {
            if (!ContentbuilderngHelper::isEmail($value)) {
                return $this->pushEventResult($event, Text::_('COM_CONTENTBUILDERNG_VALIDATION_EMAIL_INVALID') . ': ' . $field['label']);
            }
        } else {
            foreach ($value as $val) {
                if (!ContentbuilderngHelper::isEmail($val)) {
                    $msg .= $val;
                }
            }
            if ($msg) {
                return $this->pushEventResult($event, Text::_('COM_CONTENTBUILDERNG_VALIDATION_EMAIL_INVALID') . ': ' . $field['label'] . ' (' . $msg . ')');
            }
        }

        return $this->pushEventResult($event, $msg);
    }
}
