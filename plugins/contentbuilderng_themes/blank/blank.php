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

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;

class plgContentbuilderng_themesBlank extends CMSPlugin implements SubscriberInterface
{
    private const THEME_NAME = 'blank';

    private function acceptsThemeEvent(Event $event): bool
    {
        $requestedTheme = trim((string) ($event->getArgument('theme') ?? ''));

        return $requestedTheme === '' || $requestedTheme === self::THEME_NAME;
    }

    private function pushEventResult(Event $event, string $value): void
    {
        $results = $event->getArgument('result') ?: [];

        if (!is_array($results)) {
            $results = [$results];
        }

        $results[] = $value;
        $event->setArgument('result', $results);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentTemplateJavascript' => 'onContentTemplateJavascript',
            'onEditableTemplateJavascript' => 'onEditableTemplateJavascript',
            'onListViewJavascript' => 'onListViewJavascript',
            'onContentTemplateCss' => 'onContentTemplateCss',
            'onEditableTemplateCss' => 'onEditableTemplateCss',
            'onListViewCss' => 'onListViewCss',
            'onContentTemplateSample' => 'onContentTemplateSample',
            'onEditableTemplateSample' => 'onEditableTemplateSample',
        ];
    }

    /**
     * Any content template specific JS?
     * Return it here
     * 
     * @return string
     */
    function onContentTemplateJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return $out;
    }

    /**
     * Any editable template specific JS?
     * Return it here
     * 
     * @return string
     */
    function onEditableTemplateJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return $out;
    }

    /**
     * Any list view specific JS?
     * Return it here
     * 
     * @return string
     */
    function onListViewJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return $out;
    }

    /**
     * Any content template specific CSS?
     * Return it here
     * 
     * @return string
     */
    function onContentTemplateCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return $out;
    }

    /**
     * Any editable template specific CSS?
     * Return it here
     * 
     * @return string
     */
    function onEditableTemplateCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = $this->onContentTemplateCss(null);
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return $out;
    }

    /**
     * Any list view specific CSS?
     * Return it here
     * 
     * @return string
     */
    function onListViewCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = <<<'CSS'
.cb-list-filters .form-select:disabled,
.cb-list-filters .form-control:disabled{
    color:var(--bs-secondary-color,#6c757d);
    background-color:var(--bs-secondary-bg,#e9ecef);
    border-color:var(--bs-border-color,#dee2e6);
    opacity:1;
}
CSS;
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return $out;
    }

    /**
     * Return the sample html code for content here (triggered in view admin, after checking "SAMPLE"
     * 
     * @return string
     */
    function onContentTemplateSample($arg0, $arg1 = null)
    {
        $event = null;
        if ($arg0 instanceof Event) {
            $event = $arg0;
            if (!$this->acceptsThemeEvent($event)) {
                return;
            }
            $args = $event->getArguments();
            $contentbuilderng_form_id = (int) ($args[0] ?? 0);
            $form = $args[1] ?? null;
        } else {
            $contentbuilderng_form_id = (int) $arg0;
            $form = $arg1;
        }

        if (!$contentbuilderng_form_id || !is_object($form)) {
            if ($event instanceof Event) {
                $this->pushEventResult($event, '');
                return;
            }
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $out = '<table border="0" width="100%" class="blanktable_content"><tbody>' . "\n";
        $names = $form->getElementNames();
        foreach ($names as $reference_id => $name) {
            $db->setQuery("Select id, `type` From #__contentbuilderng_elements Where published = 1 And form_id = " . intval($contentbuilderng_form_id) . " And reference_id = " . $db->Quote($reference_id));
            $result = $db->loadAssoc();
            if (is_array($result)) {
                if ($result['type'] != 'hidden') {
                    $out .= '{hide-if-empty ' . $name . '}' . "\n\n";
                    $out .= '<tr class="blanktable_content_row"><td width="20%" class="key" valign="top"><label>{' . $name . ':label}</label></td><td>{' . $name . ':value}</td></tr>' . "\n\n";
                    $out .= '{/hide}' . "\n\n";
                }
            }
        }
        $out .= '</tbody></table>' . "\n";
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return (string) $out;
    }

    /**
     * Return the sample html code for editables here (triggered in view admin, after checking "SAMPLE"
     * 
     * @return string
     */
    function onEditableTemplateSample($arg0, $arg1 = null)
    {
        $event = null;
        if ($arg0 instanceof Event) {
            $event = $arg0;
            if (!$this->acceptsThemeEvent($event)) {
                return;
            }
            $args = $event->getArguments();
            $contentbuilderng_form_id = (int) ($args[0] ?? 0);
            $form = $args[1] ?? null;
        } else {
            $contentbuilderng_form_id = (int) $arg0;
            $form = $arg1;
        }

        if (!$contentbuilderng_form_id || !is_object($form)) {
            if ($event instanceof Event) {
                $this->pushEventResult($event, '');
                return;
            }
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $checkEditable = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__contentbuilderng_elements')
            ->where('published = 1')
            ->where('editable = 1')
            ->where('form_id = ' . (int) $contentbuilderng_form_id);
        $db->setQuery($checkEditable);
        $hasEditable = (int) $db->loadResult() > 0;
        if (!$hasEditable) {
            $msg = 'No editable elements configured; generated editable sample uses all elements.';
            Factory::getApplication()->enqueueMessage($msg, 'warning');
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
        }
        $out = '<table border="0" width="100%" class="blanktable_edit"><tbody>' . "\n";
        $names = $form->getElementNames();
        $hidden = array();
        foreach ($names as $reference_id => $name) {
            $whereEditable = $hasEditable ? " And editable = 1" : "";
            $db->setQuery("Select id, `type` From #__contentbuilderng_elements Where published = 1" . $whereEditable . " And form_id = " . intval($contentbuilderng_form_id) . " And reference_id = " . $db->Quote($reference_id));
            $result = $db->loadAssoc();
            if (is_array($result)) {
                if ($result['type'] != 'hidden') {
                    $out .= '<tr class="blanktable_edit_row"><td width="20%" class="key" valign="top">{' . $name . ':label}</td><td>{' . $name . ':item}</td></tr>' . "\n";
                } else {
                    $hidden[] = '{' . $name . ':item}' . "\n";
                }
            }
        }
        $out .= '</tbody></table>' . "\n";
        foreach ($hidden as $hid) {
            $out .= $hid;
        }
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }
        return (string) $out;
    }
}
