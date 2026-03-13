<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\TemplatePrepareHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class TemplateRenderService
{
    private readonly FormResolverService $formResolverService;
    private readonly LegacyUtilityService $legacyUtilityService;

    public function __construct()
    {
        $this->formResolverService = new FormResolverService();
        $this->legacyUtilityService = new LegacyUtilityService();
    }

    private function getTextUtilityService(): TextUtilityService
    {
        return new TextUtilityService();
    }

    private function helperClassName(): string
    {
        return 'CB\\Component\\Contentbuilderng\\Administrator\\Helper\\ContentbuilderngHelper';
    }

    private function callContentbuilderngHelper(string $method, ...$arguments)
    {
        $helperClass = $this->helperClassName();

        if (!class_exists($helperClass) || !method_exists($helperClass, $method)) {
            throw new \RuntimeException('Missing ContentbuilderngHelper::' . $method);
        }

        return $helperClass::$method(...$arguments);
    }

    private function decodePackedData($raw, $default = null, bool $assoc = false)
    {
        return PackedDataHelper::decodePackedData($raw, $default, $assoc);
    }

    private function getForm($type, $referenceId)
    {
        return $this->formResolverService->getForm($type, $referenceId);
    }

    private function getFormElementsPlugins(): array
    {
        return (new FormSupportService(new PathService()))->getFormElementsPlugins();
    }

    private function execPhp($result)
    {
        return $this->legacyUtilityService->execPhp($result);
    }

    private function normalizeTemplateMarkers(string $template): string
    {
        if (stripos($template, '{hide-if-empty') === false && stripos($template, '{/hide}') === false) {
            return $template;
        }

        $replacements = array(
            '/<li>\s*{hide-if-empty\s+([^}]+)}\s*<\/li>/i' => '{hide-if-empty $1}',
            '/<li>\s*{\/hide}\s*{hide-if-empty\s+([^}]+)}\s*<\/li>/i' => "{/hide}\n{hide-if-empty $1}",
            '/<li>\s*{\/hide}\s*<\/li>/i' => '{/hide}',
        );

        return preg_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function applyItemWrappers($contentbuilderngFormId, array $items, $form)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $article = new \Joomla\CMS\Table\Content($db);
        $registry = new Registry();
        $registry->loadString('{}');
        $onContentPrepare = 'onContentPrepare';

        $db->setQuery("Select reference_id, item_wrapper, wordwrap, `label`, `options` From #__contentbuilderng_elements Where published = 1 And form_id = " . (int) $contentbuilderngFormId);
        $wrappers = $db->loadAssocList();

        foreach ($wrappers as $wrapper) {
            foreach ($items as $item) {
                foreach ($item as $key => $value) {
                    if ($key !== 'col' . $wrapper['reference_id']) {
                        continue;
                    }

                    $newValue = '';

                    if (strpos(trim($wrapper['item_wrapper'] ?? ''), '$') === 0) {
                        $article->id = 0;

                        $w = explode('$', $wrapper['item_wrapper'], 2);
                        if (count($w) !== 2) {
                            break;
                        }

                        $w = explode('$', implode('$', $w));

                        if (count($w) === 2) {
                            PluginHelper::importPlugin('content');
                        } else {
                            $size = count($w) - 1;
                            for ($j = 0; $j < $size; $j++) {
                                PluginHelper::importPlugin('content', $w[$j]);
                            }
                        }

                        $article->text = trim($w[count($w) - 1]) ? trim($w[count($w) - 1]) : $value;
                        $article->text = str_replace('{value_inline}', $value, $article->text);
                        $recc = new \stdClass();
                        $recc->recName = $wrapper['label'];
                        $recc->recValue = $value;
                        $recc->recElementId = $wrapper['reference_id'];
                        $recc->colRecord = $item->colRecord;

                        $dispatcher = Factory::getApplication()->getDispatcher();
                        $dispatcher->dispatch(
                            $onContentPrepare,
                            new ContentPrepareEvent($onContentPrepare, array('com_content.article', &$article, &$registry, 0, true, $form, $recc))
                        );
                        $dispatcher->clearListeners($onContentPrepare);

                        if ($article->text != $w[count($w) - 1]) {
                            $item->$key = $article->text;
                            break;
                        }

                        $item->$key = '';
                        break;
                    }

                    $allowHtml = false;
                    $options = $this->decodePackedData($wrapper['options'], null, false);

                    if ($options instanceof \stdClass && isset($options->allow_html) && $options->allow_html) {
                        $allowHtml = true;
                    }

                    $textUtilityService = $this->getTextUtilityService();

                    if ($wrapper['wordwrap'] && !$allowHtml) {
                        $newValue = $textUtilityService->allhtmlentities(
                            $this->callContentbuilderngHelper(
                                'contentbuilderng_wordwrap',
                                $this->callContentbuilderngHelper('cbinternal', $value),
                                $wrapper['wordwrap'],
                                "\n",
                                true
                            )
                        );
                    } else {
                        $newValue = $allowHtml
                            ? $textUtilityService->cleanString($this->callContentbuilderngHelper('cbinternal', $value))
                            : $textUtilityService->allhtmlentities($this->callContentbuilderngHelper('cbinternal', $value));
                    }

                    $wrapperTemplate = trim((string) ($wrapper['item_wrapper'] ?? ''));

                    if (strpos($wrapperTemplate, '<?php') === 0) {
                        $value = $newValue;
                        $code = $wrapperTemplate;

                        if (function_exists('mb_strlen')) {
                            $p1 = 0;
                            $l = mb_strlen($code);
                            $c = '';
                            while ($p1 < $l) {
                                $p2 = mb_strpos($code, '<?php', $p1);
                                if ($p2 === false) {
                                    $p2 = $l;
                                }
                                $c .= mb_substr($code, $p1, $p2 - $p1);
                                $p1 = $p2;
                                if ($p1 < $l) {
                                    $p1 += 5;
                                    $p2 = mb_strpos($code, '?>', $p1);
                                    if ($p2 === false) {
                                        $p2 = $l;
                                    }
                                    $c .= eval(mb_substr($code, $p1, $p2 - $p1));
                                    $p1 = $p2 + 2;
                                }
                            }
                        } else {
                            $p1 = 0;
                            $l = strlen($code);
                            $c = '';
                            while ($p1 < $l) {
                                $p2 = strpos($code, '<?php', $p1);
                                if ($p2 === false) {
                                    $p2 = $l;
                                }
                                $c .= substr($code, $p1, $p2 - $p1);
                                $p1 = $p2;
                                if ($p1 < $l) {
                                    $p1 += 5;
                                    $p2 = strpos($code, '?>', $p1);
                                    if ($p2 === false) {
                                        $p2 = $l;
                                    }
                                    $c .= eval(substr($code, $p1, $p2 - $p1));
                                    $p1 = $p2 + 2;
                                }
                            }
                        }

                        $item->$key = $c;
                    } elseif ($wrapperTemplate !== '') {
                        $item->$key = str_replace('{value}', $newValue, $wrapperTemplate);
                        $item->$key = str_replace(
                            '{webpath}',
                            str_replace(
                                array('{CBSite}', '{cbsite}', JPATH_SITE),
                                Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost() . (Uri::getInstance()->getPort() == 80 ? '' : ':' . Uri::getInstance()->getPort()) . Uri::root(true),
                                $value ?? ''
                            ),
                            $item->$key
                        );
                    } else {
                        $item->$key = $newValue;
                    }

                    break;
                }
            }
        }

        return $items;
    }

    public function getTemplate($contentbuilderngFormId, $recordId, array $record, array $elementsAllowed, $quietSkip = false)
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $input = $app->input;

        if (
            $app->isClient('site')
            && (
                $input->getCmd('view', '') === 'list'
                || $input->getCmd('view', '') === 'edit'
                || str_starts_with($input->getCmd('task', ''), 'list.')
                || $input->getCmd('task', '') === 'edit.display'
            )
        ) {
            return '';
        }

        static $_template;

        $hash = md5($contentbuilderngFormId . $recordId . implode(',', $elementsAllowed));

        if (is_array($_template) && isset($_template[$hash])) {
            return $_template[$hash];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select `type`,reference_id,details_template, details_prepare, edit_by_type, act_as_registration, registration_name_field, registration_username_field, registration_email_field, registration_email_repeat_field, registration_password_field, registration_password_repeat_field From #__contentbuilderng_forms Where id = " . (int) $contentbuilderngFormId);
        $result = $db->loadAssoc();

        if (is_array($result) && $result['details_template']) {
            $user = null;
            if ($result['act_as_registration']) {
                $form = $this->getForm($result['type'], $result['reference_id']);
                $meta = $form->getRecordMetadata($recordId);
                $db->setQuery("Select * From #__users Where id = " . $meta->created_id);
                $user = $db->loadObject();
            }

            $_template = array();
            $labels = array();
            $allowHtml = array();

            $db->setQuery("Select `label`,`reference_id`,`options` From #__contentbuilderng_elements Where form_id = " . (int) $contentbuilderngFormId);
            $labels_ = $db->loadAssocList();

            foreach ($labels_ as $label_) {
                $labels[$label_['reference_id']] = $label_['label'];
                $opts = $this->decodePackedData($label_['options'], null, false);
                if (is_object($opts) && ((isset($opts->allow_html) && $opts->allow_html) || (isset($opts->allow_raw) && $opts->allow_raw))) {
                    $allowHtml[$label_['reference_id']] = $opts;
                }
            }

            $template = $this->normalizeTemplateMarkers($result['details_template']);
            $items = array();
            $hasLabels = count($labels);

            foreach ($record as $item) {
                if (!in_array($item->recElementId, $elementsAllowed)) {
                    continue;
                }

                $items[$item->recName] = array();
                $items[$item->recName]['label'] = $hasLabels ? $labels[$item->recElementId] : $item->recTitle;

                if ($result['act_as_registration'] && $user !== null) {
                    if ($result['registration_name_field'] == $item->recElementId) {
                        $item->recValue = $user->name;
                    } elseif ($result['registration_username_field'] == $item->recElementId) {
                        $item->recValue = $user->username;
                    } elseif ($result['registration_email_field'] == $item->recElementId) {
                        $item->recValue = $user->email;
                    } elseif ($result['registration_email_repeat_field'] == $item->recElementId || $result['registration_password_field'] == $item->recElementId || $result['registration_password_repeat_field'] == $item->recElementId) {
                        $item->recValue = '';
                    }
                }

                $items[$item->recName]['value'] = ($item->recValue != '' ? $item->recValue : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
                $items[$item->recName]['id'] = $item->recElementId;
                $regex = "/([\{]hide-if-empty " . $item->recName . "[\}])(.*)([\{][\/]hide[\}])/isU";
                $regex2 = "/([\{]hide-if-matches " . $item->recName . " (.*)[\}])(.*)([\{][\/]hide-if-matches[\}])/isU";
                $matches = array();
                preg_match_all($regex2, $template, $matches);

                if (isset($matches[2]) && in_array($item->recValue, $matches[2])) {
                    $regex3 = "/([\{]hide-if-matches " . $item->recName . " " . trim($item->recValue) . "[\}])(.*)([\{][\/]hide-if-matches[\}])/isU";
                    $template = preg_replace($regex3, "", $template);
                }

                if ($item->recValue == '') {
                    $template = preg_replace($regex, "", $template);
                } else {
                    $template = preg_replace($regex, '$2', $template);
                }
            }

            $regex3 = "/([\{]hide-if-matches (.*) (.*)[\}])(.*)([\{][\/]hide-if-matches[\}])/isU";
            $template = preg_replace($regex3, '$4', $template);

            $item = null;
            $rawItems = $items;
            $textUtilityService = $this->getTextUtilityService();
            foreach ($items as $key => $item) {
                if (!isset($item['label']) || !isset($item['id'])) {
                    continue;
                }
                $items[$key]['label'] = htmlentities($item['label'], ENT_QUOTES, 'UTF-8');
                $items[$key]['value'] = isset($allowHtml[$item['id']])
                    ? $textUtilityService->cleanString($item['value'])
                    : nl2br($textUtilityService->allhtmlentities($this->callContentbuilderngHelper('cbinternal', $item['value'])));
            }

            $detailsPrepare = $result['details_prepare'] ?? '';
            TemplatePrepareHelper::execute(
                $detailsPrepare,
                'details_prepare',
                function (string $prepareCode) use (&$items, &$template, &$rawItems, &$item, $record, $result, $recordId, $elementsAllowed, $contentbuilderngFormId): void {
                    eval($prepareCode);
                }
            );

            foreach ($items as $key => $item) {
                if (!isset($item['label']) || !isset($item['id'])) {
                    continue;
                }
                $template = str_replace('{' . $key . ':label}', $item['label'], $template);
                $template = str_replace('{' . $key . ':value}', $item['value'], $template);
                $template = str_replace('{webpath ' . $key . '}', str_replace(array('{CBSite}', '{cbsite}', JPATH_SITE), Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost() . (Uri::getInstance()->getPort() == 80 ? '' : ':' . Uri::getInstance()->getPort()) . Uri::root(true), $rawItems[$key]['value']), $template);
            }

            $_template[$hash] = $template;
            return $template;
        }

        if ($quietSkip) {
            return '';
        }

        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_TEMPLATE_NOT_FOUND'), 404);
    }

    public function getEmailTemplate($contentbuilderngFormId, $recordId, array $record, array $elementsAllowed, $isAdmin)
    {
        static $_template;

        $hash = md5(($isAdmin ? 'admin' : 'user') . $contentbuilderngFormId . $recordId . implode(',', $elementsAllowed));

        if (is_array($_template) && isset($_template[$hash])) {
            return $_template[$hash];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select `name`,`type`,reference_id,email_template, email_admin_template, email_html, email_admin_html, act_as_registration, registration_name_field, registration_username_field, registration_email_field  From #__contentbuilderng_forms Where id = " . (int) $contentbuilderngFormId);
        $result = $db->loadAssoc();

        if (is_array($result)) {
            $user = null;
            if ($result['act_as_registration']) {
                $form = $this->getForm($result['type'], $result['reference_id']);
                $meta = $form->getRecordMetadata($recordId);
                $db->setQuery("Select * From #__users Where id = " . $meta->created_id);
                $user = $db->loadObject();
            }

            $_template = array();
            $labels = array();
            $allowHtml = array();

            $db->setQuery("Select `label`,`reference_id`,`options` From #__contentbuilderng_elements Where form_id = " . (int) $contentbuilderngFormId);
            $labels_ = $db->loadAssocList();

            foreach ($labels_ as $label_) {
                $labels[$label_['reference_id']] = $label_['label'];
                $opts = $this->decodePackedData($label_['options'], null, false);
                if (is_object($opts) && isset($opts->allow_html) && $opts->allow_html) {
                    $allowHtml[$label_['reference_id']] = $opts;
                }
            }

            $template = $isAdmin ? $result['email_admin_template'] : $result['email_template'];
            $html = $isAdmin ? $result['email_admin_html'] : $result['email_html'];
            $items = array();
            $hasLabels = count($labels);

            foreach ($record as $item) {
                if (!in_array($item->recElementId, $elementsAllowed)) {
                    continue;
                }

                $items[$item->recName] = array();
                $items[$item->recName]['label'] = $hasLabels ? $labels[$item->recElementId] : $item->recTitle;
                if ($result['act_as_registration'] && $user !== null) {
                    if ($result['registration_name_field'] == $item->recElementId) {
                        $item->recValue = $user->name;
                    } elseif ($result['registration_username_field'] == $item->recElementId) {
                        $item->recValue = $user->username;
                    } elseif ($result['registration_email_field'] == $item->recElementId) {
                        $item->recValue = $user->email;
                    }
                }

                $items[$item->recName]['value'] = ($item->recValue != '' ? $item->recValue : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
                $items[$item->recName]['id'] = $item->recElementId;
                $regex = "/([\{]hide-if-empty " . $item->recName . "[\}])(.*)([\{][\/]hide[\}])/isU";
                $regex2 = "/([\{]hide-if-matches " . $item->recName . " (.*)[\}])(.*)([\{][\/]-if-matches[\}])/isU";
                $matches = array();
                preg_match_all($regex2, $template, $matches);

                if (isset($matches[2]) && in_array($item->recValue, $matches[2])) {
                    $regex3 = "/([\{]hide-if-matches " . $item->recName . " " . trim($item->recValue) . "[\}])(.*)([\{][\/]-if-matches[\}])/isU";
                    $template = preg_replace($regex3, "", $template);
                }

                if ($item->recValue == '') {
                    $template = preg_replace($regex, "", $template);
                } else {
                    $template = preg_replace($regex, '$2', $template);
                    $template = preg_replace($regex2, '$2', $template);
                }
            }

            $regex3 = "/([\{]hide-if-matches (.*) (.*)[\}])(.*)([\{][\/]-if-matches[\}])/isU";
            $template = preg_replace($regex3, '$4', $template);

            $template = str_replace(array('{RECORD_ID}', '{record_id}'), $recordId, $template);
            $template = str_replace(array('{USER_ID}', '{user_id}'), Factory::getApplication()->getIdentity()->id, $template);
            $template = str_replace(array('{USERNAME}', '{username}'), Factory::getApplication()->getIdentity()->username, $template);
            $template = str_replace(array('{USER_FULL_NAME}', '{user_full_name}'), Factory::getApplication()->getIdentity()->name, $template);
            $template = str_replace(array('{VIEW_NAME}', '{view_name}'), $result['name'], $template);
            $template = str_replace(array('{VIEW_ID}', '{view_id}'), $contentbuilderngFormId, $template);
            $template = str_replace(array('{IP}', '{ip}'), $_SERVER['REMOTE_ADDR'], $template);

            foreach ($items as $key => $item) {
                $template = str_replace('{' . $key . ':label}', $html ? htmlentities($item['label'], ENT_QUOTES, 'UTF-8') : $item['label'], $template);
                $template = str_replace('{' . $key . ':value}', isset($allowHtml[$item['id']]) && $html ? ($this->callContentbuilderngHelper('is_internal_path', $item['value']) ? basename($item['value']) : $item['value']) : nl2br(strip_tags(($this->callContentbuilderngHelper('is_internal_path', $item['value']) ? basename($item['value']) : $item['value']))), $template);
                $template = str_replace('{webpath ' . $key . '}', str_replace(array('{CBSite}', '{cbsite}', JPATH_SITE), Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost() . (Uri::getInstance()->getPort() == 80 ? '' : ':' . Uri::getInstance()->getPort()) . Uri::root(true), $item['value']), $template);
            }

            $_template[$hash] = $template;
            return $template;
        }

        return '';
    }

    public function getEditableTemplate($contentbuilderngFormId, $recordId, array $record, array $elementsAllowed, $execPrepare = true)
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $session = $app->getSession();

        $failedValues = $session->get('cb_failed_values', null, 'com_contentbuilderng.' . $contentbuilderngFormId);

        if ($failedValues !== null) {
            $session->clear('cb_failed_values', 'com_contentbuilderng.' . $contentbuilderngFormId);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select `type`, reference_id, editable_template, editable_prepare, edit_by_type, act_as_registration, registration_name_field, registration_username_field, registration_email_field, registration_email_repeat_field, registration_password_field, registration_password_repeat_field From #__contentbuilderng_forms Where id = " . (int) $contentbuilderngFormId);
        $result = $db->loadAssoc();

        if (!is_array($result) || trim((string) ($result['editable_template'] ?? '')) === '') {
            if (!is_array($result)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'error');
            } else {
                Factory::getApplication()->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EDITABLE_TEMPLATE_NOT_SET'), 'error');
            }

            return '';
        }

        $user = null;
        if ($result['act_as_registration']) {
            if ($recordId) {
                $form = $this->getForm($result['type'], $result['reference_id']);
                $meta = $form->getRecordMetadata($recordId);
                $db->setQuery("Select * From #__users Where id = " . $meta->created_id);
                $user = $db->loadObject();
            } elseif ((int) (Factory::getApplication()->getIdentity()->id ?? 0)) {
                $db->setQuery("Select * From #__users Where id = " . (int) (Factory::getApplication()->getIdentity()->id ?? 0));
                $user = $db->loadObject();
            }
        }

        $labels = array();
        $validations = array();

        if (!$result['edit_by_type']) {
            $db->setQuery("Select `label`,`reference_id`,`validations` From #__contentbuilderng_elements Where form_id = " . (int) $contentbuilderngFormId);
            $labels_ = $db->loadAssocList();
            foreach ($labels_ as $label_) {
                $labels[$label_['reference_id']] = $label_['label'];
                $validations[$label_['reference_id']] = $label_['validations'];
            }
        }

        $hasLabels = count($labels);
        $formType = $result['type'];
        $formReferenceId = $result['reference_id'];
        $form = $this->getForm($formType, $formReferenceId);
        $template = $result['editable_template'];
        $items = array();

        foreach ($record as $item) {
            if (!in_array($item->recElementId, $elementsAllowed)) {
                continue;
            }

            $items[$item->recName] = array();
            $items[$item->recName]['id'] = $item->recElementId;
            $items[$item->recName]['label'] = $hasLabels ? $labels[$item->recElementId] : $item->recTitle;

            if ($result['act_as_registration'] && $user !== null) {
                if ($result['registration_name_field'] == $item->recElementId) {
                    $item->recValue = $user->name;
                } elseif ($result['registration_username_field'] == $item->recElementId) {
                    $item->recValue = $user->username;
                } elseif ($result['registration_email_field'] == $item->recElementId || $result['registration_email_repeat_field'] == $item->recElementId) {
                    $item->recValue = $user->email;
                }
            }
            $items[$item->recName]['value'] = ($item->recValue ? $item->recValue : '');
        }

        $hasRecords = true;
        if (!count($record)) {
            $hasRecords = false;
            $names = $form->getElementNames();
            if (!count($labels)) {
                $labels = $form->getElementLabels();
            }
            foreach ($names as $elementId => $name) {
                if (!isset($items[$name])) {
                    $items[$name] = array();
                }
                $items[$name]['id'] = $elementId;
                $items[$name]['label'] = $labels[$elementId];
                $items[$name]['value'] = '';
            }
        }

        $item = null;
        if ($execPrepare) {
            $editablePrepare = $result['editable_prepare'] ?? '';
            TemplatePrepareHelper::execute(
                $editablePrepare,
                'editable_prepare',
                function (string $prepareCode) use (&$items, &$template, &$item, $record, $result, $recordId, $elementsAllowed, $contentbuilderngFormId): void {
                    eval($prepareCode);
                }
            );
        }

        $theInitScripts = "\n" . '<script type="text/javascript">' . "\n" . '<!--' . "\n";

        foreach ($items as $key => $item) {
            $db->setQuery(
                "Select * From #__contentbuilderng_elements"
                . " Where published = 1"
                . " And reference_id = " . $db->quote($item['id'])
                . " And form_id = " . (int) $contentbuilderngFormId
                . " Order By ordering"
            );
            $element = $db->loadAssoc();

            if (!is_array($element) || !$element) {
                $rawValue = ($failedValues !== null && isset($failedValues[$item['id']]))
                    ? $failedValues[$item['id']]
                    : ($hasRecords ? ($item['value'] ?? '') : '');

                if (is_array($rawValue)) {
                    $rawValue = array_values(array_filter($rawValue, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
                    $rawValue = implode(', ', $rawValue);
                }

                $fallbackLabel = htmlentities((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $fallbackValue = htmlentities((string) $rawValue, ENT_QUOTES, 'UTF-8');

                $template = str_replace('{' . $key . ':label}', '<label>' . $fallbackLabel . '</label>', $template);
                $template = str_replace('{' . $key . ':item}', $fallbackValue, $template);
                continue;
            }

            $autocomplete = '';
            if ($result['act_as_registration']) {
                if ($result['registration_name_field'] == $element['reference_id']) {
                    $element['default_value'] = $user !== null ? $user->name : '';
                    $autocomplete = 'autocomplete="off" ';
                } elseif ($result['registration_username_field'] == $element['reference_id']) {
                    $element['default_value'] = $user !== null ? $user->username : '';
                    $autocomplete = 'autocomplete="off" ';
                } elseif ($result['registration_email_field'] == $element['reference_id']) {
                    $element['default_value'] = $user !== null ? $user->email : '';
                    $autocomplete = 'autocomplete="off" ';
                } elseif ($result['registration_email_repeat_field'] == $element['reference_id']) {
                    $element['default_value'] = $user !== null ? $user->email : '';
                    $autocomplete = 'autocomplete="off" ';
                } elseif ($result['registration_password_field'] == $element['reference_id'] || $result['registration_password_repeat_field'] == $element['reference_id']) {
                    $element['force_password'] = true;
                    $autocomplete = 'autocomplete="off" ';
                }
            }

            if (!isset($element['default_value']) && !$hasRecords) {
                $element['default_value'] = $item['value'];
            } elseif (isset($element['default_value']) && !$hasRecords) {
                $element['default_value'] = $this->execPhp($element['default_value']);
            }

            $asterisk = '';
            $elementType = $element['type'] ?? '';
            $elementOptions = $element['options'] ?? '';
            $elementReferenceId = $element['reference_id'] ?? '';
            $elementCustomInit = $element['custom_init_script'] ?? '';
            $elementHint = $element['hint'] ?? '';
            $isEditable = (int) ($element['editable'] ?? 1) === 1;

            if ($elementType == 'captcha' || trim($element['validations'] ?? '') != '' || trim($element['custom_validation_script'] ?? '') != '') {
                $asterisk = ' <span class="cbRequired" style="color:red;">*</span>';
            }

            $options = $this->decodePackedData($elementOptions, null, false);
            if (!is_object($options)) {
                $options = new \stdClass();
            }

            $theItem = '';

            switch ($elementType) {
                case in_array($elementType, $this->getFormElementsPlugins()):
                    PluginHelper::importPlugin('contentbuilderng_form_elements', $elementType);
                    $dispatcher = Factory::getApplication()->getDispatcher();
                    $eventResult = $dispatcher->dispatch('onRenderElement', new \Joomla\CMS\Event\GenericEvent('onRenderElement', array($item, $element, $options, $failedValues, $result, $hasRecords)));
                    $results = $eventResult->getArgument('result') ?: [];
                    $dispatcher->clearListeners('onRenderElement');
                    if (count($results)) {
                        $results = $results[0];
                    }
                    $theItem = $results;
                    break;
                case '':
                case 'text':
                    $options->length = $options->length ?? '';
                    $options->maxlength = $options->maxlength ?? '';
                    $options->password = $options->password ?? '';
                    $options->readonly = $options->readonly ?? '';
                    $theItem = '<div class="cbFormField cbTextField"><input class="form-control form-control-sm" ' . $autocomplete . '' . ($options->readonly ? 'readonly="readonly" ' : '') . 'style="' . ($options->length ? 'width:' . $options->length . ';' : '') . '" ' . ($options->maxlength ? 'maxlength="' . (int) $options->maxlength . '" ' : '') . 'type="' . (isset($element['force_password']) || $options->password ? 'password' : 'text') . '" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '" value="' . htmlentities($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value']), ENT_QUOTES, 'UTF-8') . '"/></div>';
                    break;
                case 'textarea':
                    $options->width = $options->width ?? '';
                    $options->height = $options->height ?? '';
                    $options->maxlength = $options->maxlength ?? '';
                    $options->readonly = $options->readonly ?? '';
                    $options->allow_html = $options->allow_html ?? false;
                    $options->allow_raw = $options->allow_raw ?? false;
                    if ($options->allow_html || $options->allow_raw) {
                        $editor = Editor::getInstance(Factory::getApplication()->get('editor'));
                        $theItem = '<div class="cbFormField cbTextArea">' . $editor->display('cb_' . $item['id'], htmlentities($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value']), ENT_QUOTES, 'UTF-8'), $options->width ? $options->width : '100%', $options->height ? $options->height : '550', '75', '20') . '</div>';
                    } else {
                        $theItem = '<div class="cbFormField cbTextArea form-control form-control-sm"><textarea class="form-control form-control-sm" ' . ($options->readonly ? 'readonly="readonly" ' : '') . 'style="' . ($options->width || $options->height ? ($options->width ? 'width:' . $options->width . ';' : '') . ($options->height ? 'height:' . $options->height . ';' : '') : '') . '" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '">' . htmlentities($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value']), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                    }
                    break;
                case 'checkboxgroup':
                case 'radiogroup':
                    $options->seperator = ',';
                    $options->horizontal = $options->horizontal ?? false;
                    $options->horizontal_length = $options->horizontal_length ?? '';
                    if ($form->isGroup($item['id'])) {
                        $groupdef = $form->getGroupDefinition($item['id']);
                        $i = 0;
                        $sep = $options->seperator;
                        $group = explode($sep, $failedValues !== null && isset($failedValues[$element['reference_id']]) && is_array($failedValues[$element['reference_id']]) ? implode($sep, $failedValues[$element['reference_id']]) : ($hasRecords ? $item['value'] : $element['default_value']));
                        $theItem = '<input name="cb_' . $item['id'] . '[]" type="hidden" value="cbGroupMark"/>';
                        foreach ($groupdef as $value => $label) {
                            $checked = '';
                            $for = $i != 0 ? '_' . $i : '';
                            foreach ($group as $selectedValue) {
                                if (trim($value) == trim($selectedValue)) {
                                    $checked = ' checked="checked"';
                                    break;
                                }
                            }
                            $theItem .= '<div style="' . ($options->horizontal ? 'float: left;' . ($options->horizontal_length ? 'width: ' . $options->horizontal_length . ';' : '') . 'display: inline; margin-right: 2px;' : '') . '" class="cbFormField cbGroupField"><input class="form-check-input" id="cb_' . $item['id'] . $for . '" name="cb_' . $item['id'] . '[]" type="' . ($element['type'] == 'checkboxgroup' ? 'checkbox' : 'radio') . '" value="' . htmlentities(trim($value), ENT_QUOTES, 'UTF-8') . '"' . $checked . '/> <label for="cb_' . $item['id'] . $for . '">' . htmlentities(trim($label), ENT_QUOTES, 'UTF-8') . '</label> </div>';
                            $i++;
                        }
                        if ($options->horizontal) {
                            $theItem .= '<div style="clear:both;"></div>';
                        }
                    } else {
                        $theItem .= '<span style="color:red">ELEMENT IS NOT A GROUP</span>';
                    }
                    break;
                case 'select':
                    $options->seperator = ',';
                    $options->multiple = $options->multiple ?? 0;
                    $options->length = $options->length ?? '';
                    if ($form->isGroup($item['id'])) {
                        $groupdef = $form->getGroupDefinition($item['id']);
                        $sep = $options->seperator;
                        $multi = $options->multiple;
                        $group = explode($sep, $failedValues !== null && isset($failedValues[$element['reference_id']]) && is_array($failedValues[$element['reference_id']]) ? implode($sep, $failedValues[$element['reference_id']]) : ($hasRecords ? $item['value'] : $element['default_value']));
                        $theItem = '<input name="cb_' . $item['id'] . '[]" type="hidden" value="cbGroupMark"/>';
                        $theItem .= '<div class="cbFormField cbSelectField"><select class="form-select form-select-sm" id="cb_' . $item['id'] . '" ' . ($options->length ? 'style="width:' . $options->length . ';" ' : '') . 'name="cb_' . $item['id'] . '[]"' . ($multi ? ' multiple="multiple"' : '') . '>';
                        foreach ($groupdef as $value => $label) {
                            $checked = '';
                            foreach ($group as $selectedValue) {
                                if (trim($value) == trim($selectedValue)) {
                                    $checked = ' selected="selected"';
                                    break;
                                }
                            }
                            $theItem .= '<option value="' . htmlentities(trim($value), ENT_QUOTES, 'UTF-8') . '"' . $checked . '>' . htmlentities(trim($label), ENT_QUOTES, 'UTF-8') . '</option>';
                        }
                        $theItem .= '</select></div>';
                    } else {
                        $theItem .= '<span style="color:red">ELEMENT IS NOT A GROUP</span>';
                    }
                    break;
                case 'upload':
                    $deletable = isset($validations[$item['id']]) && $validations[$item['id']] == '';
                    $theItem = '<div class="cbFormField cbUploadField">';
                    $theItem .= '<input type="file" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '"/>';
                    if (trim($item['value']) != '') {
                        $theItem .= '<div>' . ($deletable ? '<label for="cb_delete_' . $item['id'] . '">' . Text::_('COM_CONTENTBUILDERNG_DELETE') . '</label> <input type="checkbox" id="cb_delete_' . $item['id'] . '" name="cb_delete_' . $item['id'] . '" value="1"/> ' : '') . htmlentities(basename($item['value']), ENT_QUOTES, 'UTF-8') . '</div><div style="clear:both;"></div>';
                    }
                    $theItem .= '</div>';
                    break;
                case 'captcha':
                    $theItem = '<div class="cbFormField cbCaptchaField">';
                    $captchaUrl = Factory::getApplication()->isClient('site')
                        ? Uri::root(true) . '/components/com_contentbuilderng/images/securimage/securimage_show.php'
                        : Uri::root(true) . '/administrator/components/com_contentbuilderng/assets/images/securimage_show.php';
                    $theItem .= '<img width="250" height="80" id="cbCaptcha" alt="captcha" src="' . $captchaUrl . '?rand=' . rand(0, getrandmax()) . '"/>';
                    $theItem .= '<div>';
                    $theItem .= '<input class="form-control form-control-sm mt-1" autocomplete="off" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '" type="text" maxlength="12" />';
                    $theItem .= '<img style="cursor: pointer; padding-left: 7px;" onclick="document.getElementById(\'cbCaptcha\').src = \'' . $captchaUrl . '?\' + Math.random(); blur(); return false" border="0" alt="refresh" src="' . Uri::root(true) . '/components/com_contentbuilderng/images/securimage/refresh-captcha.png"/>';
                    $theItem .= '</div></div>';
                    break;
                case 'calendar':
                    $options->length = $options->length ?? '';
                    $options->maxlength = $options->maxlength ?? '';
                    $options->readonly = $options->readonly ?? '';
                    $options->format = $options->format ?? '%Y-%m-%d';
                    $options->transfer_format = $options->transfer_format ?? 'YYYY-mm-dd';
                    $calval = htmlentities($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value']), ENT_QUOTES, 'UTF-8');
                    $calval = $this->callContentbuilderngHelper('convertDate', $calval, $options->transfer_format, $options->format);
                    $calAttr = ['class' => 'cb_' . $item['id'], 'showTime' => true, 'timeFormat' => '24', 'singleHeader' => false, 'todayBtn' => true, 'weekNumbers' => true, 'minYear' => '', 'maxYear' => '', 'firstDay' => '1'];
                    $theItem = '<div class="cbFormField cbCalendarField">' . "\n" . '<div id="field-calendar_cb_' . $item['id'] . '">' . "\n" . '<div class="input-group">' . "\n";
                    $theItem .= HTMLHelper::_('calendar', $calval, 'cb_' . $item['id'], 'cb_' . $item['id'], $options->format, $calAttr);
                    $theItem .= "</div>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>";
                    break;
                case 'hidden':
                    $theItem = '<input type="hidden" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '" value="' . htmlentities($failedValues !== null && $elementReferenceId !== '' && isset($failedValues[$elementReferenceId]) ? $failedValues[$elementReferenceId] : ($hasRecords ? $item['value'] : $element['default_value']), ENT_QUOTES, 'UTF-8') . '"/>';
                    break;
            }

            if (!$isEditable && $elementType !== 'hidden' && $theItem !== '') {
                $disableControl = static function (string $tag, bool $addReadonly): string {
                    if (preg_match('/\btype\s*=\s*([\'"])hidden\1/i', $tag)) {
                        return $tag;
                    }
                    if (stripos($tag, ' disabled=') === false) {
                        $tag = rtrim($tag, '>') . ' disabled="disabled" aria-disabled="true">';
                    }
                    if ($addReadonly && stripos($tag, ' readonly=') === false) {
                        $tag = rtrim($tag, '>') . ' readonly="readonly">';
                    }
                    return $tag;
                };

                $theItem = preg_replace_callback('/<input\b[^>]*>/i', static fn($m) => $disableControl($m[0], true), $theItem);
                $theItem = preg_replace_callback('/<textarea\b[^>]*>/i', static fn($m) => $disableControl($m[0], true), $theItem);
                $theItem = preg_replace_callback('/<select\b[^>]*>/i', static fn($m) => $disableControl($m[0], false), $theItem);
                $theItem = preg_replace_callback('/<button\b[^>]*>/i', static fn($m) => $disableControl($m[0], false), $theItem);
                $theItem = preg_replace('/\s+onclick="[^"]*"/i', '', $theItem);
            }

            if ($elementCustomInit) {
                $theInitScripts .= $elementCustomInit . "\n";
            }

            $replaceTokens = false;
            if ($theItem === '' || $theItem === null) {
                $rawValue = ($failedValues !== null && isset($failedValues[$element['reference_id']]))
                    ? $failedValues[$element['reference_id']]
                    : ($hasRecords ? ($item['value'] ?? '') : ($element['default_value'] ?? ''));

                if (is_array($rawValue)) {
                    $rawValue = array_values(array_filter($rawValue, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
                    $rawValue = implode(', ', $rawValue);
                }

                $theItem = htmlentities((string) $rawValue, ENT_QUOTES, 'UTF-8');
                $replaceTokens = true;
            }

            if ($theItem !== '' || $replaceTokens) {
                $tip = 'hasTip';
                $tipPrefix = htmlentities($item['label'], ENT_QUOTES, 'UTF-8') . '::';
                $template = str_replace('{' . $key . ':label}', '<label ' . ($elementHint ? 'class="editlinktip ' . $tip . '" title="' . $tipPrefix . $elementHint . '" ' : '') . 'for="cb_' . $item['id'] . '">' . $item['label'] . $asterisk . ($elementHint ? ' <img style="cursor: pointer;" src="' . Uri::root(true) . '/components/com_contentbuilderng/images/icon_info.png" border="0"/>' : '') . '</label>', $template);
                $template = str_replace('{' . $key . ':item}', $theItem, $template);
            }
        }

        return $template . $theInitScripts . "\n" . '//-->' . '</script>' . "\n";
    }
}
