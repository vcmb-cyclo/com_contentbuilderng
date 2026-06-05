<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

class TemplateSampleService
{
    public function __construct(
        private readonly CMSApplicationInterface $app,
        private readonly DatabaseInterface $db
    ) {
    }

    private function getDispatcher()
    {
        return $this->app->getDispatcher();
    }

    public function createDetailsSample($formId, $form, $plugin)
    {
        if (!$formId || !is_object($form)) {
            return;
        }

        $requestedPlugin = trim((string) $plugin);
        $activePlugin = $requestedPlugin !== '' ? $requestedPlugin : 'joomla6';

        if (!PluginHelper::isEnabled('contentbuilderng_themes', $activePlugin)) {
            $msg = "ContentBuilder NG theme plugin not enabled: contentbuilderng_themes/{$activePlugin}";
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
            $this->app->enqueueMessage($msg, 'warning');
        }

        if (!PluginHelper::importPlugin('contentbuilderng_themes', $activePlugin)) {
            $activePlugin = 'joomla6';
            PluginHelper::importPlugin('contentbuilderng_themes', $activePlugin);
            $msg = 'ContentBuilder NG theme fallback applied for details sample: contentbuilderng_themes/joomla6';
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
        }

        $dispatcher = $this->getDispatcher();
        $eventResult = $dispatcher->dispatch(
            'onContentTemplateSample',
            new \Joomla\CMS\Event\GenericEvent('onContentTemplateSample', [$formId, $form, 'theme' => $activePlugin])
        );
        $results = $eventResult->getArgument('result') ?: [];
        $out = implode('', $results);

        if ($activePlugin !== '' && $out === '') {
            $msg = "ContentBuilder NG theme plugin returned empty sample: contentbuilderng_themes/{$activePlugin}";
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
            $this->app->enqueueMessage($msg, 'warning');
        }

        return $out;
    }

    public function createEmailSample($formId, $form, $html = false)
    {
        if (!$formId || !is_object($form)) {
            return;
        }

        $db = $this->db;
        $out = '';

        if ($html) {
            $out = '<table border="0" width="100%"><tbody>' . "\n";
        }

        $names = $form->getElementNames();

        foreach ($names as $referenceId => $name) {
            $db->setQuery(
                'Select id, `type` From #__contentbuilderng_elements'
                . ' Where published = 1 And form_id = ' . (int) $formId
                . ' And reference_id = ' . $db->quote($referenceId)
            );
            $result = $db->loadAssoc();

            if (is_array($result) && $result['type'] !== 'hidden') {
                $out .= '{hide-if-empty ' . $name . '}';

                if ($html) {
                    $out .= '<tr><td width="20%" valign="top"><label>{' . $name . ':label}</label></td><td>{' . $name . ':value}</td></tr>' . "\r\n";
                } else {
                    $out .= '{' . $name . ':label}: {' . $name . ':value}';
                }

                $out .= "\r\n" . '{/hide}';
            }
        }

        if ($html) {
            $out .= '</tbody></table>' . "\n";
        }

        return $out;
    }

    public function createEditableSample($formId, $form, $plugin)
    {
        if (!$formId || !is_object($form)) {
            return;
        }

        $requestedPlugin = trim((string) $plugin);
        $activePlugin = $requestedPlugin !== '' ? $requestedPlugin : 'joomla6';

        if (!PluginHelper::isEnabled('contentbuilderng_themes', $activePlugin)) {
            $msg = "ContentBuilder NG theme plugin not enabled: contentbuilderng_themes/{$activePlugin}";
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
            $this->getApp()->enqueueMessage($msg, 'warning');
        }

        if (!PluginHelper::importPlugin('contentbuilderng_themes', $activePlugin)) {
            $activePlugin = 'joomla6';
            PluginHelper::importPlugin('contentbuilderng_themes', $activePlugin);
            $msg = 'ContentBuilder NG theme fallback applied for editable sample: contentbuilderng_themes/joomla6';
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
        }

        $dispatcher = $this->getDispatcher();
        $eventResult = $dispatcher->dispatch(
            'onEditableTemplateSample',
            new \Joomla\CMS\Event\GenericEvent('onEditableTemplateSample', [$formId, $form, 'theme' => $activePlugin])
        );
        $results = $eventResult->getArgument('result') ?: [];
        $out = implode('', $results);

        if ($activePlugin !== '' && $out === '') {
            $msg = "ContentBuilder NG theme plugin returned empty editable sample: contentbuilderng_themes/{$activePlugin}";
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
            $this->getApp()->enqueueMessage($msg, 'warning');
        }

        return $out;
    }
}
