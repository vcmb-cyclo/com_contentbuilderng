<?php

namespace CB\Plugin\ContentbuilderngThemes\Joomla6\Extension;

/**
 * @version     6.0
 * @package     ContentBuilderNG
 * @author      Xavier DANO / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */


\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;

final class Joomla6 extends CMSPlugin implements SubscriberInterface
{
    private const THEME_NAME = 'joomla6';

    private function acceptsThemeEvent(Event $event): bool
    {
        $requestedTheme = trim((string) ($event->getArgument('theme') ?? ''));

        return $requestedTheme === '' || $requestedTheme === self::THEME_NAME;
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
     * Appends a value to the event result payload.
     */
    private function pushEventResult(Event $event, string $value): void
    {
        $results = $event->getArgument('result') ?: [];
        if (!is_array($results)) {
            $results = [$results];
        }
        $results[] = $value;
        $event->setArgument('result', $results);
    }

    /* =========================
     * CSS / JS events
     * ========================= */

    public function onContentTemplateJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';

        // Event dispatch mode.
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        // Direct return mode.
        return $out;
    }

    public function onEditableTemplateJavascript($event = null)
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

    public function onListViewJavascript($event = null)
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

    public function onContentTemplateCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = \file_get_contents(__DIR__ . '/../../css/content.css');

        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        return $out;
    }

    public function onEditableTemplateCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        // Comme ton original: même CSS
        return $this->onContentTemplateCss($event);
    }

    public function onListViewCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = \file_get_contents(__DIR__ . '/../../css/list.css');

        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        return $out;
    }

    /* =========================
     * Template samples
     * ========================= */

    public function onContentTemplateSample($arg0, $arg1 = null)
    {
        // Event dispatch mode: dispatch(new Event('onContentTemplateSample', [$id, $form]))
        if ($arg0 instanceof Event) {
            $event = $arg0;
            if (!$this->acceptsThemeEvent($event)) {
                return;
            }
            $args  = $event->getArguments();

            $contentbuilderng_form_id = (int) ($args[0] ?? 0);
            $form = $args[1] ?? null;

            $out = $this->buildContentTemplateSample($contentbuilderng_form_id, $form);
            $this->pushEventResult($event, $out);
            return;
        }

        // Direct call mode: onContentTemplateSample($id, $form)
        $contentbuilderng_form_id = (int) $arg0;
        $form = $arg1;

        return $this->buildContentTemplateSample($contentbuilderng_form_id, $form);
    }

    private function buildContentTemplateSample(int $contentbuilderng_form_id, $form): string
    {
        if (!$contentbuilderng_form_id || !is_object($form)) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $elementTypes = $this->fetchElementTypes($db, $contentbuilderng_form_id, false);

        $out = '<ul class="list-group list-group-flush">' . "\n";
        $names = $form->getElementNames();

        foreach ($names as $reference_id => $name) {
            $type = $elementTypes[$reference_id] ?? null;

            if ($type !== null && $type !== 'hidden') {
                $out .= '{hide-if-empty ' . $name . '}' . "\n\n";
                $out .= '<li class="list-group-item"><div class="row g-2 align-items-start"><div class="col-3"><label class="form-label mb-0">{' . $name . ':label}</label></div><div class="col"><div class="form-control-plaintext py-0">{' . $name . ':value}</div></div></div></li>' . "\n\n";
                $out .= '{/hide}' . "\n\n";
            }
        }

        $out .= '</ul>' . "\n";
        return $out;
    }

    public function onEditableTemplateSample($arg0, $arg1 = null)
    {
        if ($arg0 instanceof Event) {
            $event = $arg0;
            if (!$this->acceptsThemeEvent($event)) {
                return;
            }
            $args  = $event->getArguments();

            $contentbuilderng_form_id = (int) ($args[0] ?? 0);
            $form = $args[1] ?? null;

            $out = $this->buildEditableTemplateSample($contentbuilderng_form_id, $form);
            $this->pushEventResult($event, $out);
            return;
        }

        $contentbuilderng_form_id = (int) $arg0;
        $form = $arg1;

        return $this->buildEditableTemplateSample($contentbuilderng_form_id, $form);
    }

    private function buildEditableTemplateSample(int $contentbuilderng_form_id, $form): string
    {
        if (!$contentbuilderng_form_id || !is_object($form)) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $elements = $this->fetchElementDefinitions($db, $contentbuilderng_form_id);
        $hasEditableElements = false;
        foreach ($elements as $element) {
            if ((bool) ($element['editable'] ?? false)) {
                $hasEditableElements = true;
                break;
            }
        }

        if (!$hasEditableElements) {
            $msg = 'No editable elements configured; generated editable sample uses all elements.';
            Factory::getApplication()->enqueueMessage($msg, 'warning');
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
        }

        $out = "\n";
        $names = $form->getElementNames();
        $hidden = [];

        foreach ($names as $reference_id => $name) {
            $element = $elements[$reference_id] ?? null;

            if ($element === null) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            $editable = (bool) ($element['editable'] ?? false);

            if ($type === 'hidden') {
                if ($editable) {
                    $hidden[] = '{' . $name . ':item}' . "\n";
                }
                continue;
            }

            if (!$editable) {
                $out .= '<div class="mb-3"><label class="form-label">{' . $name . ':label}</label><div class="form-control-plaintext py-0">{' . $name . ':value}</div></div>' . "\n";
                continue;
            }

            if ($type === 'checkboxgroup') {
                $out .= '<div class="mb-3"><div class="form-label">{' . $name . ':label}</div><div>{' . $name . ':item}</div></div>';
            } elseif ($type === 'radiogroup') {
                $out .= '<div class="mb-3"><div class="form-label">{' . $name . ':label}</div><div>{' . $name . ':item}</div></div>';
            } else {
                $out .= '<div class="mb-3"><label class="form-label">{' . $name . ':label}</label><div>{' . $name . ':item}</div></div>' . "\n";
            }
        }

        foreach ($hidden as $hid) {
            $out .= $hid;
        }

        return $out;
    }

    private function fetchElementDefinitions(DatabaseInterface $db, int $contentbuilderng_form_id): array
    {
        $db->setQuery(
            "SELECT reference_id, `type`, editable
             FROM #__contentbuilderng_elements
             WHERE published = 1 AND form_id = " . (int) $contentbuilderng_form_id
        );

        $rows = $db->loadAssocList();
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $elements = [];
        foreach ($rows as $row) {
            if (!isset($row['reference_id'])) {
                continue;
            }
            $elements[(string) $row['reference_id']] = [
                'type' => (string) ($row['type'] ?? ''),
                'editable' => (int) ($row['editable'] ?? 0) === 1,
            ];
        }

        return $elements;
    }

    private function fetchElementTypes(DatabaseInterface $db, int $contentbuilderng_form_id, bool $editableOnly): array
    {
        $where = "published = 1 AND form_id = " . (int) $contentbuilderng_form_id;

        if ($editableOnly) {
            $where .= " AND editable = 1";
        }

        $db->setQuery(
            "SELECT reference_id, `type`
             FROM #__contentbuilderng_elements
             WHERE " . $where
        );

        $rows = $db->loadAssocList();
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $elementTypes = [];
        foreach ($rows as $row) {
            if (!isset($row['reference_id'])) {
                continue;
            }
            $elementTypes[$row['reference_id']] = $row['type'] ?? '';
        }

        return $elementTypes;
    }
}
