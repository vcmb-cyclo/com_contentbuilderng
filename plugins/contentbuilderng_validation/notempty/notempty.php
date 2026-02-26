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
\defined('_JEXEC') or die ('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;

class plgContentbuilderng_validationNotempty extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onValidate' => 'onValidate'];
    }

    public function onValidate(Event $event): string
    {
        $args = array_values($event->getArguments());
        $field = isset($args[0]) && is_array($args[0]) ? $args[0] : [];
        $record_id = isset($args[2]) ? (int) $args[2] : 0;
        $form = $args[3] ?? null;
        $value = $args[4] ?? null;
        if (!$field) {
            return '';
        }

        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_contentbuilderng_validation_notempty', JPATH_ADMINISTRATOR);

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $msg = '';

        if (!is_array($value)) {

            if ($field['type'] == 'upload') {
                $msg = '';
                $record_with_file_found = false;
                $record = $form->getRecord($record_id, false, -1, true);
                foreach ($record as $item) {
                    if ($item->recElementId == $field['reference_id']) {
                        if ($item->recValue != '') {
                            $record_with_file_found = true;
                        }
                        break;
                    }
                }
                if (!$record_with_file_found && empty ($value)) {
                    $msg = trim($field['validation_message']) ? trim($field['validation_message']) : Text::_('COM_CONTENTBUILDERNG_VALIDATION_VALUE_EMPTY') . ': ' . $field['label'];
                }
            } else {
                $value = trim($value);
                if (empty ($value)) {
                    $msg = trim($field['validation_message']) ? trim($field['validation_message']) : Text::_('COM_CONTENTBUILDERNG_VALIDATION_VALUE_EMPTY') . ': ' . $field['label'];
                }
            }
        } else {
            $has = '';
            foreach ($value as $item) {
                if ($item != 'cbGroupMark') {
                    $has .= $item;
                }
            }
            if (!$has) {
                $msg = trim($field['validation_message']) ? trim($field['validation_message']) : Text::_('COM_CONTENTBUILDERNG_VALIDATION_VALUE_EMPTY') . ': ' . $field['label'];
            }
        }
        return $msg;
    }
}
