<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\TemplatePrepareHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class TemplateRenderService
{
    private readonly CMSApplication $app;
    private readonly DatabaseInterface $db;
    private readonly FormResolverService $formResolverService;
    private readonly FormSupportService $formSupportService;
    private readonly RuntimeUtilityService $runtimeUtilityService;
    private readonly TextUtilityService $textUtilityService;
    private array $contentPluginImportCache = [];

    public function __construct(
        CMSApplication $app,
        DatabaseInterface $db,
        FormResolverService $formResolverService,
        FormSupportService $formSupportService,
        RuntimeUtilityService $runtimeUtilityService,
        TextUtilityService $textUtilityService
    ) {
        $this->app = $app;
        $this->db = $db;
        $this->formResolverService = $formResolverService;
        $this->formSupportService = $formSupportService;
        $this->runtimeUtilityService = $runtimeUtilityService;
        $this->textUtilityService = $textUtilityService;
    }

    private function getTextUtilityService(): TextUtilityService
    {
        return $this->textUtilityService;
    }

    private function getApp(): CMSApplication
    {
        return $this->app;
    }

    private function getInput()
    {
        return $this->getApp()->input;
    }

    private function getCurrentUserId(): int
    {
        if ($this->getInput()->getBool('cb_preview_ok', false)) {
            $previewActorId = (int) $this->getInput()->getInt('cb_preview_actor_id', 0);

            if ($previewActorId > 0) {
                return $previewActorId;
            }
        }

        return (int) ($this->getApp()->getIdentity()->id ?? 0);
    }

    private function getCurrentUsername(): string
    {
        if ($this->getInput()->getBool('cb_preview_ok', false)) {
            $previewActorName = trim((string) $this->getInput()->getString('cb_preview_actor_name', ''));

            if ($previewActorName !== '') {
                return $previewActorName;
            }
        }

        return (string) ($this->getApp()->getIdentity()->username ?? '');
    }

    private function getCurrentUserFullName(): string
    {
        if ($this->getInput()->getBool('cb_preview_ok', false)) {
            $previewActorName = trim((string) $this->getInput()->getString('cb_preview_actor_name', ''));

            if ($previewActorName !== '') {
                return $previewActorName;
            }
        }

        return (string) ($this->getApp()->getIdentity()->name ?? '');
    }

    private function getDispatcher()
    {
        return $this->getApp()->getDispatcher();
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
        return $this->formSupportService->getFormElementsPlugins();
    }

    private function execPhp($result)
    {
        return $this->runtimeUtilityService->execPhp($result);
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

    private function replaceTemplateFieldToken(string $template, string $name, string $token, string $replacement): string
    {
        return (string) preg_replace(
            '/\\{' . preg_quote($name, '/') . ':' . preg_quote($token, '/') . '\\}/i',
            str_replace(['\\', '$'], ['\\\\', '\\$'], $replacement),
            $template
        );
    }

    private function replaceEditableReadonlyPair(string $template, string $name, string $labelHtml, string $valueHtml): string
    {
        $replacement = '<div class="mb-3">' . $labelHtml . '<div class="form-control-plaintext py-0">' . $valueHtml . '</div></div>';

        return (string) preg_replace(
            '/\\{' . preg_quote($name, '/') . ':label\\}\\s*\\{' . preg_quote($name, '/') . ':value\\}/i',
            str_replace(['\\', '$'], ['\\\\', '\\$'], $replacement),
            $template
        );
    }

    private function applyTemplateHideIfEmpty(string $template, string $name, string $rawValue, bool $preserveEditableItem): string
    {
        $quotedName = preg_quote($name, '/');

        return (string) preg_replace_callback(
            "/\\{hide-if-empty\\s+" . $quotedName . "\\}(.*?)\\{\\/hide\\}/is",
            static function (array $matches) use ($quotedName, $rawValue, $preserveEditableItem): string {
                $body = (string) ($matches[1] ?? '');

                if ($preserveEditableItem && preg_match('/\\{' . $quotedName . ':item\\}/i', $body)) {
                    return $body;
                }

                return trim($rawValue) === '' ? '' : $body;
            },
            $template
        );
    }

    private function applyTemplateHideIfMatches(string $template, string $name, string $rawValue, bool $preserveEditableItem): string
    {
        $quotedName = preg_quote($name, '/');

        return (string) preg_replace_callback(
            "/\\{hide-if-matches\\s+" . $quotedName . "\\s+([^}]*)\\}(.*?)\\{\\/hide-if-matches\\}/is",
            static function (array $matches) use ($quotedName, $rawValue, $preserveEditableItem): string {
                $expectedValue = trim((string) ($matches[1] ?? ''));
                $body = (string) ($matches[2] ?? '');

                if ($preserveEditableItem && preg_match('/\\{' . $quotedName . ':item\\}/i', $body)) {
                    return $body;
                }

                return trim($rawValue) === $expectedValue ? '' : $body;
            },
            $template
        );
    }

    private function applyDetailsHideIfEmpty(string $template, string $name, string $rawValue): string
    {
        return $this->applyTemplateHideIfEmpty($template, $name, $rawValue, false);
    }

    private function applyDetailsHideIfMatches(string $template, string $name, string $rawValue): string
    {
        return $this->applyTemplateHideIfMatches($template, $name, $rawValue, false);
    }

    private function addDebugTemplateWarning(int $formId, string $message): void
    {
        if ($formId < 1 || !$this->isFormDebugEnabled($formId)) {
            return;
        }

        $session = $this->getApp()->getSession();
        $warnings = $session->get('com_contentbuilderng.debug.template_warnings', []);
        if (!is_array($warnings)) {
            $warnings = [];
        }
        if (!in_array($message, $warnings, true)) {
            $warnings[] = $message;
        }
        $session->set('com_contentbuilderng.debug.template_warnings', $warnings);
    }

    private function isFormDebugEnabled(int $formId): bool
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('debug_mode'))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        return (int) $db->loadResult() === 1;
    }

    private function addCaseMismatchWarnings(int $formId, string $template, string $fieldName): void
    {
        $patterns = [
            '/\\{([^}:]+):(label|value|item)\\}/i',
            '/\\{webpath\\s+([^}]+)\\}/i',
            '/\\{hide-if-empty\\s+([^}]+)\\}/i',
            '/\\{hide-if-matches\\s+([^}\\s]+)\\s+[^}]*\\}/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $template, $matches)) {
                continue;
            }

            foreach ($matches[1] as $markerName) {
                $markerName = trim((string) $markerName);
                if (strcasecmp($markerName, $fieldName) === 0 && $markerName !== $fieldName) {
                    $this->addDebugTemplateWarning(
                        $formId,
                        Text::sprintf('COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_CASE_MISMATCH', $markerName, $fieldName)
                    );
                }
            }
        }
    }

    private function addEditableItemMarkerWarnings(int $formId, string $template, string $fieldName): void
    {
        $quotedName = preg_quote($fieldName, '/');

        if (
            preg_match('/\\{' . $quotedName . ':(label|value)\\}/i', $template)
            && !preg_match('/\\{' . $quotedName . ':item\\}/i', $template)
        ) {
            $this->addDebugTemplateWarning(
                $formId,
                Text::sprintf('COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_EDITABLE_WITHOUT_ITEM', $fieldName)
            );
        }
    }

    private function addUnclosedHideIfEmptyWarnings(int $formId, string $template): void
    {
        if (!preg_match_all('/\\{hide-if-empty\\s+([^}]+)\\}|\\{\\/hide\\}/i', $template, $matches, PREG_SET_ORDER)) {
            return;
        }

        $openFieldName = null;

        foreach ($matches as $match) {
            $token = (string) ($match[0] ?? '');

            if (stripos($token, '{hide-if-empty') === 0) {
                if ($openFieldName !== null) {
                    $this->addDebugTemplateWarning(
                        $formId,
                        Text::sprintf('COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_UNCLOSED_HIDE_IF_EMPTY', $openFieldName)
                    );
                }

                $openFieldName = trim((string) ($match[1] ?? ''));
                continue;
            }

            $openFieldName = null;
        }

        if ($openFieldName !== null) {
            $this->addDebugTemplateWarning(
                $formId,
                Text::sprintf('COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_UNCLOSED_HIDE_IF_EMPTY', $openFieldName)
            );
        }
    }

    private function removeUnknownTemplateMarkers(int $formId, string $template, array $knownFieldNames): string
    {
        $known = [];
        foreach ($knownFieldNames as $knownFieldName) {
            $known[strtolower((string) $knownFieldName)] = true;
        }

        foreach ($this->extractTemplateMarkerNames($template) as $markerName) {
            $lookup = strtolower($markerName);
            if (isset($known[$lookup])) {
                continue;
            }

            $this->addDebugTemplateWarning(
                $formId,
                Text::sprintf('COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_UNKNOWN_FIELD', $markerName)
            );

            $quotedName = preg_quote($markerName, '/');
            $template = (string) preg_replace("/\\{hide-if-empty\\s+" . $quotedName . "\\}.*?\\{\\/hide\\}/is", '', $template);
            $template = (string) preg_replace("/\\{hide-if-matches\\s+" . $quotedName . "\\s+[^}]*\\}.*?\\{\\/hide-if-matches\\}/is", '', $template);
            $template = (string) preg_replace('/\\{' . $quotedName . ':(label|value|item)\\}/i', '', $template);
            $template = (string) preg_replace('/\\{webpath\\s+' . $quotedName . '\\}/i', '', $template);
        }

        return $template;
    }

    private function extractTemplateMarkerNames(string $template): array
    {
        $names = [];
        $patterns = [
            '/\\{([^}:]+):(label|value|item)\\}/i',
            '/\\{webpath\\s+([^}]+)\\}/i',
            '/\\{hide-if-empty\\s+([^}]+)\\}/i',
            '/\\{hide-if-matches\\s+([^}\\s]+)\\s+[^}]*\\}/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $template, $matches)) {
                continue;
            }

            foreach ($matches[1] as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    private function applyEditableHideIfEmpty(string $template, string $name, string $rawValue): string
    {
        return $this->applyTemplateHideIfEmpty($template, $name, $rawValue, true);
    }

    private function applyEditableHideIfMatches(string $template, string $name, string $rawValue): string
    {
        return $this->applyTemplateHideIfMatches($template, $name, $rawValue, true);
    }

    /**
     * Convert simple HTML emphasis produced by editable_prepare snippets into
     * plain text plus field styling for non-HTML form controls.
     *
     * @return array{value:string,style:string,class:string}
     */
    private function normalizeEditableFieldPresentation(string $rawValue): array
    {
        $rawValue = (string) $rawValue;
        $styleParts = [];
        $classNames = [];

        if (preg_match('/color\s*:\s*([^;"\']+)/i', $rawValue, $matches)) {
            $color = trim((string) ($matches[1] ?? ''));
            if ($color !== '') {
                $styleParts[] = 'color:' . $color;
            }
        }

        if (preg_match('/<(b|strong)\b/i', $rawValue)) {
            $styleParts[] = 'font-weight:700';
        }

        if (preg_match('/<(i|em)\b/i', $rawValue)) {
            $styleParts[] = 'font-style:italic';
        }

        if (stripos($rawValue, 'cb-prepare-blink') !== false) {
            $classNames[] = 'cb-prepare-blink';
        }

        $normalizedValue = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $rawValue);
        $normalizedValue = html_entity_decode(strip_tags($normalizedValue), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'value' => $normalizedValue,
            'style' => $styleParts !== [] ? implode(';', $styleParts) . ';' : '',
            'class' => implode(' ', array_unique($classNames)),
        ];
    }

    private function normalizeGroupComparisonValue(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * Resolves which group option values are selected in a stored record value.
     * Options are matched greedily (longest first) against the whole value, so
     * option values containing the separator (commas) or line breaks are
     * recognised as a whole instead of being split apart.
     */
    private function matchSelectedGroupValues(array $optionValues, string $storedValue): array
    {
        $normalizedOptions = [];

        foreach ($optionValues as $optionValue) {
            $optionValue = trim((string) $optionValue);
            $normalized = $this->normalizeGroupComparisonValue($optionValue);

            if ($normalized !== '') {
                $normalizedOptions[$normalized] = $optionValue;
            }
        }

        if ($normalizedOptions === []) {
            return [];
        }

        uksort($normalizedOptions, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $remaining = $this->normalizeGroupComparisonValue($storedValue);
        $selected = [];

        while ($remaining !== '') {
            foreach ($normalizedOptions as $normalized => $original) {
                $length = mb_strlen($normalized);

                if (mb_substr($remaining, 0, $length) === $normalized) {
                    $selected[] = $original;
                    $remaining = ltrim(mb_substr($remaining, $length), " \t\n,");
                    continue 2;
                }
            }

            $nextSeparator = strpos($remaining, ',');

            if ($nextSeparator === false) {
                break;
            }

            $remaining = ltrim(substr($remaining, $nextSeparator + 1));
        }

        return array_values(array_unique($selected));
    }

    /**
     * Resolves which options are selected for an editable group/select field.
     * Order of precedence: values re-posted after a failed validation, then
     * the exact recValues array from the record source (when the source can
     * provide one, e.g. BreezingForms), then a best-effort match against the
     * flattened, comma-joined stored value.
     */
    private function resolveEditableGroupValues(
        array $groupDefinition,
        ?array $failedValues,
        $elementReferenceId,
        array $item,
        bool $hasRecords,
        $defaultValue
    ): array {
        if ($failedValues !== null && isset($failedValues[$elementReferenceId]) && is_array($failedValues[$elementReferenceId])) {
            return array_map(static fn($failedValue): string => trim((string) $failedValue), $failedValues[$elementReferenceId]);
        }

        if ($hasRecords && isset($item['values']) && is_array($item['values'])) {
            return array_map(static fn($v): string => trim((string) $v), $item['values']);
        }

        return $this->matchSelectedGroupValues(
            array_map('strval', array_keys($groupDefinition)),
            (string) ($hasRecords ? $item['value'] : $defaultValue)
        );
    }

    private function renderReadonlyGroupField(object $form, string $elementId, string $elementType, string $value, ?array $exactValues = null): string
    {
        if (!method_exists($form, 'isGroup') || !method_exists($form, 'getGroupDefinition') || !$form->isGroup($elementId)) {
            return '';
        }

        $groupDefinition = (array) $form->getGroupDefinition($elementId);
        if ($groupDefinition === []) {
            return '';
        }

        $selectedValues = $exactValues !== null
            ? array_values(array_unique(array_map(static fn($v): string => trim((string) $v), $exactValues)))
            : $this->matchSelectedGroupValues(array_map('strval', array_keys($groupDefinition)), $value);
        if ($elementType === 'select') {
            $selectedLabels = [];

            foreach ($groupDefinition as $optionValue => $optionLabel) {
                if (in_array(trim((string) $optionValue), $selectedValues, true)) {
                    $selectedLabels[] = htmlspecialchars(trim((string) $optionLabel), ENT_QUOTES, 'UTF-8');
                }
            }

            $displayText = $selectedLabels !== [] ? implode(', ', $selectedLabels) : '&mdash;';

            return '<div class="cbFormField cbSelectField">' . $displayText . '</div>';
        }

        $inputType = $elementType === 'checkboxgroup' ? 'checkbox' : 'radio';
        $elementIdAttribute = preg_replace('/[^A-Za-z0-9_\-]/', '_', $elementId) ?? $elementId;
        $html = '<div class="cbFormField cbGroupFields d-flex flex-wrap align-items-center gap-3">';
        $index = 0;

        foreach ($groupDefinition as $optionValue => $optionLabel) {
            $optionValue = trim((string) $optionValue);
            $optionLabel = trim((string) $optionLabel);
            $checked = in_array($optionValue, $selectedValues, true) ? ' checked="checked"' : '';
            $fieldId = 'cb_details_' . $elementIdAttribute . '_' . $index;

            $html .= '<div class="cbGroupField form-check form-check-inline d-inline-flex align-items-center gap-1 mb-0">'
                . '<input class="form-check-input mt-0" id="' . htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') . '" type="' . $inputType . '" disabled="disabled"' . $checked . ' />'
                . '<label class="form-check-label" for="' . htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</label>'
                . '</div>';

            $index++;
        }

        return $html . '</div>';
    }

    public function applyItemWrappers($contentbuilderngFormId, array $items, $form)
    {
        $db = $this->db;
        $article = new \Joomla\CMS\Table\Content($db);
        $registry = new Registry();
        $registry->loadString('{}');
        $onContentPrepare = 'onContentPrepare';

        $db->setQuery("Select reference_id, item_wrapper, wordwrap, `label`, `options` From #__contentbuilderng_elements Where published = 1 And form_id = " . (int) $contentbuilderngFormId);
        $wrappers = $db->loadAssocList();
        $wrapperMap = [];

        foreach ($wrappers as $wrapper) {
            $wrapperMap['col' . $wrapper['reference_id']] = $wrapper;
        }

        $dispatcher = $this->getDispatcher();
        $textUtilityService = $this->getTextUtilityService();

        foreach ($items as $item) {
            foreach ($item as $key => $value) {
                if (!isset($wrapperMap[$key])) {
                    continue;
                }

                $wrapper = $wrapperMap[$key];
                $newValue = '';

                if (strpos(trim($wrapper['item_wrapper'] ?? ''), '$') === 0) {
                    $article->id = 0;
                    $w = explode('$', (string) $wrapper['item_wrapper']);

                    if (count($w) < 2) {
                        continue;
                    }

                    $this->importContentPluginsForWrapper($w);
                    $templateBody = (string) end($w);

                    $article->text = trim($templateBody) ? trim($templateBody) : $value;
                    $article->text = str_replace('{value_inline}', $value, $article->text);
                    $recc = new \stdClass();
                    $recc->recName = $wrapper['label'];
                    $recc->recValue = $value;
                    $recc->recElementId = $wrapper['reference_id'];
                    $recc->colRecord = $item->colRecord;

                    $dispatcher->dispatch(
                        $onContentPrepare,
                        new ContentPrepareEvent($onContentPrepare, array('com_content.article', &$article, &$registry, 0, true, $form, $recc))
                    );
                    $dispatcher->clearListeners($onContentPrepare);

                    $item->$key = $article->text != $templateBody ? $article->text : '';

                    continue;
                }

                $allowHtml = false;
                $options = $this->decodePackedData($wrapper['options'], null, false);

                if ($options instanceof \stdClass && isset($options->allow_html) && $options->allow_html) {
                    $allowHtml = true;
                }

                if ($wrapper['wordwrap'] && !$allowHtml) {
                    $newValue = $textUtilityService->allhtmlspecialchars(
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
                        : $textUtilityService->allhtmlspecialchars($this->callContentbuilderngHelper('cbinternal', $value));
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
            }
        }

        return $items;
    }

    private function importContentPluginsForWrapper(array $wrapperParts): void
    {
        $pluginParts = $wrapperParts;
        array_pop($pluginParts);

        $cacheKey = implode('$', $pluginParts);

        if (isset($this->contentPluginImportCache[$cacheKey])) {
            return;
        }

        if (in_array('', $pluginParts, true)) {
            PluginHelper::importPlugin('content');
        }

        foreach ($pluginParts as $pluginName) {
            if ($pluginName === '') {
                continue;
            }

            PluginHelper::importPlugin('content', $pluginName);
        }

        $this->contentPluginImportCache[$cacheKey] = true;
    }

    public function getTemplate($contentbuilderngFormId, $recordId, array $record, array $elementsAllowed, $quietSkip = false)
    {
        $app = $this->getApp();
        $input = $this->getInput();

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

        $db = $this->db;
        $db->setQuery("Select `type`,reference_id,details_template, details_prepare, edit_by_type, act_as_registration, registration_name_field, registration_username_field, registration_email_field, registration_email_repeat_field, registration_password_field, registration_password_repeat_field From #__contentbuilderng_forms Where id = " . (int) $contentbuilderngFormId);
        $result = $db->loadAssoc();

        if (is_array($result) && $result['details_template']) {
            $form = $this->getForm($result['type'], $result['reference_id']);
            $sourceEditableTypes = is_object($form) && method_exists($form, 'getEditableElementTypes')
                ? (array) $form->getEditableElementTypes()
                : [];
            $user = null;
            if ($result['act_as_registration']) {
                $meta = $form->getRecordMetadata($recordId);
                $db->setQuery("Select * From #__users Where id = " . $meta->created_id);
                $user = $db->loadObject();
            }

            $_template = array();
            $labels = array();
            $allowHtml = array();
            $renderedGroupFields = array();

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
            $seenElementIds = array();

            foreach ($record as $item) {
                if (!in_array($item->recElementId, $elementsAllowed)) {
                    continue;
                }

                $this->addCaseMismatchWarnings((int) $contentbuilderngFormId, $template, (string) $item->recName);
                $seenElementIds[(string) $item->recElementId] = true;
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

                $items[$item->recName]['id'] = $item->recElementId;
                $itemValue = ($item->recValue != '' ? $item->recValue : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
                $items[$item->recName]['raw_value'] = (string) $item->recValue;
                $elementType = (string) ($sourceEditableTypes[(string) $item->recElementId] ?? '');

                if (
                    is_object($form)
                    && $item->recValue != ''
                    && in_array($elementType, ['radiogroup', 'checkboxgroup', 'select'], true)
                ) {
                    $renderedGroupValue = $this->renderReadonlyGroupField($form, (string) $item->recElementId, $elementType, (string) $item->recValue, property_exists($item, 'recValues') ? $item->recValues : null);

                    if ($renderedGroupValue !== '') {
                        $itemValue = $renderedGroupValue;
                        $renderedGroupFields[$item->recElementId] = true;
                    }
                }

                $items[$item->recName]['value'] = $itemValue;
            }

            if (is_object($form) && method_exists($form, 'getElementNames')) {
                $elementNames = (array) $form->getElementNames();
                $elementLabels = method_exists($form, 'getElementLabels') ? (array) $form->getElementLabels() : [];

                foreach ($elementNames as $elementId => $name) {
                    if (!in_array($elementId, $elementsAllowed) || isset($seenElementIds[(string) $elementId])) {
                        continue;
                    }

                    $this->addCaseMismatchWarnings((int) $contentbuilderngFormId, $template, (string) $name);
                    $items[$name] = [
                        'id' => $elementId,
                        'label' => $hasLabels ? ($labels[$elementId] ?? ($elementLabels[$elementId] ?? $name)) : ($elementLabels[$elementId] ?? $name),
                        'raw_value' => '',
                        'value' => '',
                    ];
                }
            }

            $template = $this->removeUnknownTemplateMarkers((int) $contentbuilderngFormId, $template, array_keys($items));
            $this->addUnclosedHideIfEmptyWarnings((int) $contentbuilderngFormId, $template);

            foreach ($items as $key => $item) {
                $rawValue = (string) ($item['raw_value'] ?? '');
                $template = $this->applyDetailsHideIfEmpty($template, (string) $key, $rawValue);
                $template = $this->applyDetailsHideIfMatches($template, (string) $key, $rawValue);
            }

            $template = (string) preg_replace("/\\{hide-if-matches\\s+([^}]*)\\}(.*?)\\{\\/hide-if-matches\\}/is", '$2', $template);

            $item = null;
            $rawItems = $items;
            $textUtilityService = $this->getTextUtilityService();
            foreach ($items as $key => $item) {
                if (!isset($item['label']) || !isset($item['id'])) {
                    continue;
                }
                $items[$key]['label'] = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                $items[$key]['value'] = isset($renderedGroupFields[$item['id']])
                    ? $item['value']
                    : (isset($allowHtml[$item['id']])
                    ? $textUtilityService->cleanString($item['value'])
                    : nl2br($textUtilityService->allhtmlspecialchars($this->callContentbuilderngHelper('cbinternal', $item['value']))));
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
                $template = $this->replaceTemplateFieldToken($template, (string) $key, 'label', (string) $item['label']);
                $template = $this->replaceTemplateFieldToken($template, (string) $key, 'value', (string) $item['value']);
                $template = (string) preg_replace(
                    '/\\{webpath\\s+' . preg_quote((string) $key, '/') . '\\}/i',
                    str_replace(['\\', '$'], ['\\\\', '\\$'], str_replace(array('{CBSite}', '{cbsite}', JPATH_SITE), Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost() . (Uri::getInstance()->getPort() == 80 ? '' : ':' . Uri::getInstance()->getPort()) . Uri::root(true), $rawItems[$key]['value'] ?? '')),
                    $template
                );
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

        $db = $this->db;
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
            $template = str_replace(array('{USER_ID}', '{user_id}'), $this->getCurrentUserId(), $template);
            $template = str_replace(array('{USERNAME}', '{username}'), $this->getCurrentUsername(), $template);
            $template = str_replace(array('{USER_FULL_NAME}', '{user_full_name}'), $this->getCurrentUserFullName(), $template);
            $template = str_replace(array('{VIEW_NAME}', '{view_name}'), $result['name'], $template);
            $template = str_replace(array('{VIEW_ID}', '{view_id}'), $contentbuilderngFormId, $template);
            $template = str_replace(array('{IP}', '{ip}'), $_SERVER['REMOTE_ADDR'], $template);

            foreach ($items as $key => $item) {
                $template = str_replace('{' . $key . ':label}', $html ? htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') : $item['label'], $template);
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
        $app = $this->getApp();
        $session = $app->getSession();

        $failedValuesKey = 'com_contentbuilderng.' . $contentbuilderngFormId . '.cb_failed_values';
        $failedValues = $session->get($failedValuesKey);

        if ($failedValues !== null) {
            $session->remove($failedValuesKey);
        }

        $db = $this->db;
        $db->setQuery("Select `type`, reference_id, editable_template, editable_prepare, edit_by_type, act_as_registration, registration_name_field, registration_username_field, registration_email_field, registration_email_repeat_field, registration_password_field, registration_password_repeat_field From #__contentbuilderng_forms Where id = " . (int) $contentbuilderngFormId);
        $result = $db->loadAssoc();

        if (!is_array($result) || trim((string) ($result['editable_template'] ?? '')) === '') {
            if (!is_array($result)) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'error');
            } else {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EDITABLE_TEMPLATE_NOT_SET'), 'error');
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
            } elseif ($this->getCurrentUserId()) {
                $db->setQuery("Select * From #__users Where id = " . $this->getCurrentUserId());
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
            $items[$item->recName]['values'] = property_exists($item, 'recValues') ? $item->recValues : null;
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
                $items[$name]['values'] = null;
            }
        }

        $template = $this->removeUnknownTemplateMarkers((int) $contentbuilderngFormId, $template, array_keys($items));
        $this->addUnclosedHideIfEmptyWarnings((int) $contentbuilderngFormId, $template);

        $sourceEditableTypes = method_exists($form, 'getEditableElementTypes') ? (array) $form->getEditableElementTypes() : [];
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
            $hideIfEmptyValue = $failedValues !== null && isset($failedValues[$item['id']])
                ? $failedValues[$item['id']]
                : ($hasRecords ? ($item['value'] ?? '') : '');
            if (is_array($hideIfEmptyValue)) {
                $hideIfEmptyValue = array_values(array_filter($hideIfEmptyValue, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
                $hideIfEmptyValue = implode(', ', $hideIfEmptyValue);
            }
            $template = $this->applyEditableHideIfEmpty($template, (string) $key, (string) $hideIfEmptyValue);
            $template = $this->applyEditableHideIfMatches($template, (string) $key, (string) $hideIfEmptyValue);

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

                $fallbackLabel = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $fallbackValue = htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8');
                $fallbackLabelHtml = '<label>' . $fallbackLabel . '</label>';

                $template = $this->replaceEditableReadonlyPair($template, (string) $key, $fallbackLabelHtml, $fallbackValue);
                $template = str_replace('{' . $key . ':label}', $fallbackLabelHtml, $template);
                $template = str_replace('{' . $key . ':value}', '<div class="form-control-plaintext py-0">' . $fallbackValue . '</div>', $template);
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

            if ($isEditable) {
                $this->addEditableItemMarkerWarnings((int) $contentbuilderngFormId, $template, (string) $key);
            }

            if ($elementType === 'text' && isset($sourceEditableTypes[(string) $elementReferenceId])) {
                $elementType = (string) $sourceEditableTypes[(string) $elementReferenceId];
            }

            if ($elementType == 'captcha' || trim($element['validations'] ?? '') != '' || trim($element['custom_validation_script'] ?? '') != '') {
                $asterisk = ' <span class="cbRequired" style="color:red;">*</span>';
            }

            $options = $this->decodePackedData($elementOptions, null, false);
            if (!is_object($options)) {
                $options = new \stdClass();
            }

            $normalizeScalarValue = static function ($value): string {
                if (is_array($value)) {
                    $value = array_values(array_filter($value, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
                    return implode(', ', array_map(static fn($v) => (string) $v, $value));
                }

                if ($value === null) {
                    return '';
                }

                return (string) $value;
            };

            $theItem = '';

            switch ($elementType) {
                case in_array($elementType, $this->getFormElementsPlugins()):
                    PluginHelper::importPlugin('contentbuilderng_form_elements', $elementType);
                    $dispatcher = $this->getDispatcher();
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
                    $textValue = $normalizeScalarValue($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value']));
                    $textPresentation = $this->normalizeEditableFieldPresentation($textValue);
                    $textStyle = ($options->length ? 'width:' . $options->length . ';' : '') . $textPresentation['style'];
                    $textClass = 'form-control form-control-sm' . ($textPresentation['class'] !== '' ? ' ' . $textPresentation['class'] : '');
                    $theItem = '<div class="cbFormField cbTextField"><input class="' . $textClass . '" ' . $autocomplete . '' . ($options->readonly ? 'readonly="readonly" ' : '') . 'style="' . $textStyle . '" ' . ($options->maxlength ? 'maxlength="' . (int) $options->maxlength . '" ' : '') . 'type="' . (isset($element['force_password']) || $options->password ? 'password' : 'text') . '" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '" value="' . htmlspecialchars($textPresentation['value'], ENT_QUOTES, 'UTF-8') . '"/></div>';
                    break;
                case 'textarea':
                    $options->width = $options->width ?? '';
                    $options->height = $options->height ?? '';
                    $options->maxlength = $options->maxlength ?? '';
                    $options->readonly = $options->readonly ?? '';
                    $options->allow_html = $options->allow_html ?? false;
                    $options->allow_raw = $options->allow_raw ?? false;
                    $textareaValue = $normalizeScalarValue($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value']));
                    if ($options->allow_html || $options->allow_raw) {
                        $editor = Editor::getInstance($app->get('editor'));
                        $theItem = '<div class="cbFormField cbTextArea">' . $editor->display('cb_' . $item['id'], htmlspecialchars($textareaValue, ENT_QUOTES, 'UTF-8'), $options->width ? $options->width : '100%', $options->height ? $options->height : '550', '75', '20') . '</div>';
                    } else {
                        $textareaPresentation = $this->normalizeEditableFieldPresentation($textareaValue);
                        $textareaStyle = ($options->width || $options->height ? ($options->width ? 'width:' . $options->width . ';' : '') . ($options->height ? 'height:' . $options->height . ';' : '') : '') . $textareaPresentation['style'];
                        $textareaClass = 'form-control form-control-sm' . ($textareaPresentation['class'] !== '' ? ' ' . $textareaPresentation['class'] : '');
                        $theItem = '<div class="cbFormField cbTextArea form-control form-control-sm"><textarea class="' . $textareaClass . '" ' . ($options->readonly ? 'readonly="readonly" ' : '') . 'style="' . $textareaStyle . '" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '">' . htmlspecialchars($textareaPresentation['value'], ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                    }
                    break;
                case 'checkboxgroup':
                case 'radiogroup':
                    $options->seperator = ',';
                    $options->horizontal = $options->horizontal ?? true;
                    $options->horizontal_length = $options->horizontal_length ?? '';
                    if ($form->isGroup($item['id'])) {
                        $groupdef = $form->getGroupDefinition($item['id']);
                        $i = 0;
                        $group = $this->resolveEditableGroupValues($groupdef, $failedValues, $element['reference_id'], $item, $hasRecords, $element['default_value']);
                        $theItem = '<input name="cb_' . $item['id'] . '[]" type="hidden" value="cbGroupMark"/>';
                        $theItem .= '<div class="cbFormField cbGroupFields d-flex flex-wrap align-items-center gap-3">';
                        foreach ($groupdef as $value => $label) {
                            $checked = in_array(trim((string) $value), $group, true) ? ' checked="checked"' : '';
                            $for = $i != 0 ? '_' . $i : '';
                            $theItem .= '<div style="' . ($options->horizontal_length ? 'width: ' . $options->horizontal_length . ';' : '') . '" class="cbGroupField form-check form-check-inline d-inline-flex align-items-center gap-1 mb-0"><input class="form-check-input mt-0" id="cb_' . $item['id'] . $for . '" name="cb_' . $item['id'] . '[]" type="' . ($elementType == 'checkboxgroup' ? 'checkbox' : 'radio') . '" value="' . htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') . '"' . $checked . '/> <label class="form-check-label" for="cb_' . $item['id'] . $for . '">' . htmlspecialchars(trim($label), ENT_QUOTES, 'UTF-8') . '</label> </div>';
                            $i++;
                        }
                        $theItem .= '</div>';
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
                        $multi = $options->multiple;
                        $group = $this->resolveEditableGroupValues($groupdef, $failedValues, $element['reference_id'], $item, $hasRecords, $element['default_value']);
                        $theItem = '<input name="cb_' . $item['id'] . '[]" type="hidden" value="cbGroupMark"/>';
                        $theItem .= '<div class="cbFormField cbSelectField"><select class="form-select form-select-sm" id="cb_' . $item['id'] . '" ' . ($options->length ? 'style="width:' . $options->length . ';" ' : '') . 'name="cb_' . $item['id'] . '[]"' . ($multi ? ' multiple="multiple"' : '') . '>';
                        foreach ($groupdef as $value => $label) {
                            $checked = in_array(trim((string) $value), $group, true) ? ' selected="selected"' : '';
                            $theItem .= '<option value="' . htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') . '"' . $checked . '>' . htmlspecialchars(trim($label), ENT_QUOTES, 'UTF-8') . '</option>';
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
                        $theItem .= '<div>' . ($deletable ? '<label for="cb_delete_' . $item['id'] . '">' . Text::_('COM_CONTENTBUILDERNG_DELETE') . '</label> <input type="checkbox" id="cb_delete_' . $item['id'] . '" name="cb_delete_' . $item['id'] . '" value="1"/> ' : '') . htmlspecialchars(basename($item['value']), ENT_QUOTES, 'UTF-8') . '</div><div style="clear:both;"></div>';
                    }
                    $theItem .= '</div>';
                    break;
                case 'captcha':
                    $theItem = '<div class="cbFormField cbCaptchaField">';
                    $captchaUrl = Uri::root(true) . '/media/com_contentbuilderng/images/securimage/securimage_show.php';
                    $theItem .= '<img width="250" height="80" id="cbCaptcha" alt="captcha" src="' . $captchaUrl . '?rand=' . rand(0, getrandmax()) . '"/>';
                    $theItem .= '<div>';
                    $theItem .= '<input class="form-control form-control-sm mt-1" autocomplete="off" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '" type="text" maxlength="12" />';
                    $theItem .= '<img style="cursor: pointer; padding-left: 7px;" onclick="document.getElementById(\'cbCaptcha\').src = \'' . $captchaUrl . '?\' + Math.random(); blur(); return false" border="0" alt="refresh" src="' . Uri::root(true) . '/media/com_contentbuilderng/images/securimage/refresh-captcha.png"/>';
                    $theItem .= '</div></div>';
                    break;
                case 'calendar':
                    $options->length = $options->length ?? '';
                    $options->maxlength = $options->maxlength ?? '';
                    $options->readonly = $options->readonly ?? '';
                    $options->format = $options->format ?? '%Y-%m-%d';
                    $options->transfer_format = $options->transfer_format ?? 'YYYY-mm-dd';
                    $calval = htmlspecialchars($normalizeScalarValue($failedValues !== null && isset($failedValues[$element['reference_id']]) ? $failedValues[$element['reference_id']] : ($hasRecords ? $item['value'] : $element['default_value'])), ENT_QUOTES, 'UTF-8');
                    $calval = $this->callContentbuilderngHelper('convertDate', $calval, $options->transfer_format, $options->format);
                    $calAttr = ['class' => 'cb_' . $item['id'], 'showTime' => true, 'timeFormat' => '24', 'singleHeader' => false, 'todayBtn' => true, 'weekNumbers' => true, 'minYear' => '', 'maxYear' => '', 'firstDay' => '1'];
                    $theItem = '<div class="cbFormField cbCalendarField">' . "\n" . '<div id="field-calendar_cb_' . $item['id'] . '">' . "\n" . '<div class="input-group">' . "\n";
                    $theItem .= HTMLHelper::_('calendar', $calval, 'cb_' . $item['id'], 'cb_' . $item['id'], $options->format, $calAttr);
                    $theItem .= "</div>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>";
                    break;
                case 'hidden':
                    $hiddenValue = $normalizeScalarValue($failedValues !== null && $elementReferenceId !== '' && isset($failedValues[$elementReferenceId]) ? $failedValues[$elementReferenceId] : ($hasRecords ? $item['value'] : $element['default_value']));
                    $theItem = '<input type="hidden" id="cb_' . $item['id'] . '" name="cb_' . $item['id'] . '" value="' . htmlspecialchars($hiddenValue, ENT_QUOTES, 'UTF-8') . '"/>';
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

                $theItem = htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8');
                $replaceTokens = true;
            }

            if ($theItem !== '' || $replaceTokens) {
                $tip = 'hasTip';
                $tipPrefix = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '::';
                $labelHtml = '<label ' . ($elementHint ? 'class="editlinktip ' . $tip . '" title="' . $tipPrefix . $elementHint . '" ' : '') . 'for="cb_' . $item['id'] . '">' . $item['label'] . $asterisk . ($elementHint ? ' <img style="cursor: pointer;" src="' . Uri::root(true) . '/media/com_contentbuilderng/images/icon_info.png" border="0"/>' : '') . '</label>';
                $valueHtml = nl2br(htmlspecialchars((string) $hideIfEmptyValue, ENT_QUOTES, 'UTF-8'));
                $template = $this->replaceEditableReadonlyPair($template, (string) $key, $labelHtml, $valueHtml);
                $template = str_replace('{' . $key . ':label}', $labelHtml, $template);
                $template = str_replace('{' . $key . ':value}', '<div class="form-control-plaintext py-0">' . $valueHtml . '</div>', $template);
                $template = str_replace('{' . $key . ':item}', $theItem, $template);
            }
        }

        return $template . $theInitScripts . "\n" . '//-->' . '</script>' . "\n";
    }
}
