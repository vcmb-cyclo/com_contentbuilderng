<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\types\contentbuilderng_com_breezingformsng;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

final class FormAuditService
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Audits a form configuration.
     *
     * @return array{info: array<string,string>, checks: array<int,array{status:string,message:string}>}
     */
    public function audit(int $formId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name', 'title', 'type', 'reference_id', 'details_template', 'editable_template', 'theme_plugin']))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . $formId);
        $db->setQuery($query, 0, 1);
        $form = $db->loadAssoc();

        if (!is_array($form)) {
            return [
                'info' => [],
                'checks' => [[
                    'status' => self::STATUS_ERROR,
                    'message' => Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'),
                ]],
            ];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['reference_id', 'label', 'published', 'editable', 'type']))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . $formId)
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $elements = $db->loadAssocList() ?: [];

        $sourceNames = [];
        $sourceAvailable = false;
        try {
            $source = FormSourceFactory::getForm((string) $form['type'], (string) $form['reference_id']);
            if (is_object($source) && method_exists($source, 'getElementNames')) {
                $sourceNames = (array) $source->getElementNames();
                $sourceAvailable = true;
            }
        } catch (\Throwable $e) {
            $sourceAvailable = false;
        }

        $recordsTotal = 0;
        $recordsCountUnavailable = false;
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__contentbuilderng_records'))
                ->where($db->quoteName('type') . ' = ' . $db->quote((string) $form['type']))
                ->where($db->quoteName('reference_id') . ' = ' . $db->quote((string) $form['reference_id']));
            $db->setQuery($query);
            $recordsTotal = (int) $db->loadResult();
        } catch (\Throwable $e) {
            // Records table unavailable: keep the count at zero, the audit stays usable,
            // but surface the failure instead of silently reporting zero records.
            $recordsCountUnavailable = true;
        }

        $published = array_values(array_filter($elements, static fn(array $row): bool => (int) $row['published'] === 1));
        $editable = array_values(array_filter($published, static fn(array $row): bool => (int) $row['editable'] === 1));

        $info = [
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_FORM') => trim((string) $form['name']) . ' (#' . (int) $form['id'] . ')',
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_SOURCE') => (string) $form['type'] . ' / ' . (string) $form['reference_id'],
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_ELEMENTS') => Text::sprintf(
                'COM_CONTENTBUILDERNG_AUDIT_INFO_ELEMENTS_VALUE',
                count($elements),
                count($published),
                count($editable)
            ),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_RECORDS') => (string) $recordsTotal,
        ];

        $checks = array_merge(
            $recordsCountUnavailable ? [[
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_RECORDS_COUNT_UNAVAILABLE'),
            ]] : [],
            $this->checkTheme((string) ($form['theme_plugin'] ?? '')),
            $this->checkSourceSync($elements, $sourceNames, $sourceAvailable, (string) $form['type'], (string) $form['reference_id']),
            $this->checkElementReferences($elements),
            $this->checkTemplates($published, $sourceNames, (string) $form['type'], (string) $form['details_template'], (string) $form['editable_template'])
        );

        if ($checks === []) {
            $checks[] = [
                'status' => self::STATUS_OK,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_ALL_OK'),
            ];
        }

        return ['info' => $info, 'checks' => $checks];
    }

    /**
     * @param array<int,array<string,mixed>> $elements
     * @param array<int|string,string> $sourceNames
     * @return array<int,array{status:string,message:string}>
     */
    private function checkSourceSync(array $elements, array $sourceNames, bool $sourceAvailable, string $sourceType, string $sourceReferenceId): array
    {
        $checks = [];
        $referenced = [];

        if (!$sourceAvailable) {
            $checks[] = [
                'status' => self::STATUS_ERROR,
                'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_SOURCE_UNAVAILABLE', $sourceType, $sourceReferenceId),
            ];

            return $checks;
        }

        foreach ($elements as $element) {
            $referenceId = (string) $element['reference_id'];
            $referenced[$referenceId] = true;

            if (!isset($sourceNames[$referenceId])) {
                $checks[] = [
                    'status' => self::STATUS_ERROR,
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_AUDIT_CHECK_SOURCE_MISSING',
                        (string) $element['label'],
                        $referenceId
                    ),
                ];
            }
        }

        foreach ($sourceNames as $referenceId => $name) {
            if ($this->isIgnoredUnsyncedSourceField($sourceType, (string) $name)) {
                continue;
            }

            if (!isset($referenced[(string) $referenceId])) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_SOURCE_UNSYNCED', (string) $name),
                ];
            }
        }

        return $checks;
    }

    private function isIgnoredUnsyncedSourceField(string $sourceType, string $name): bool
    {
        if ($sourceType !== 'com_breezingformsng') {
            return false;
        }

        $fieldName = trim($name);
        if ($fieldName === '') {
            return false;
        }

        return $this->isBreezingFormsSystemFieldName($fieldName);
    }

    private function isBreezingFormsSystemFieldName(string $fieldName): bool
    {
        return array_key_exists($fieldName, $this->getBreezingFormsSystemFieldDefinitionsByName());
    }

    /**
     * @return array<int,string>
     */
    private function getBreezingFormsSystemFieldNames(): array
    {
        return array_keys($this->getBreezingFormsSystemFieldDefinitionsByName());
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function getBreezingFormsSystemFieldDefinitionsByName(): array
    {
        static $definitionsByName = null;

        if (is_array($definitionsByName)) {
            return $definitionsByName;
        }

        if (!class_exists(contentbuilderng_com_breezingformsng::class)) {
            $file = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/com_breezingformsng.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        if (!class_exists(contentbuilderng_com_breezingformsng::class)) {
            return $definitionsByName = [
                'bf_viewed' => ['label' => 'bf_viewed', 'name' => 'bf_viewed'],
                'bf_exported' => ['label' => 'bf_exported', 'name' => 'bf_exported'],
                'bf_archived' => ['label' => 'bf_archived', 'name' => 'bf_archived'],
            ];
        }

        $definitionsByName = [];
        foreach (contentbuilderng_com_breezingformsng::getSystemFieldDefinitions() as $definition) {
            $fieldName = trim((string) ($definition['name'] ?? ''));
            if ($fieldName !== '') {
                $definitionsByName[$fieldName] = $definition;
            }
        }

        return $definitionsByName;
    }

    /**
     * @return array<int,array{status:string,message:string}>
     */
    private function checkTheme(string $themePlugin): array
    {
        $themePlugin = trim($themePlugin);
        if ($themePlugin === '') {
            return [[
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_THEME_EMPTY'),
            ]];
        }

        if (in_array($themePlugin, ['joomla3', 'joomla6'], true)) {
            return [[
                'status' => self::STATUS_ERROR,
                'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_THEME_LEGACY', $themePlugin),
            ]];
        }

        if (!PluginHelper::isEnabled('contentbuilderng_themes', $themePlugin)) {
            return [[
                'status' => self::STATUS_ERROR,
                'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_THEME_DISABLED', $themePlugin),
            ]];
        }

        return [];
    }

    /**
     * @param array<int,array<string,mixed>> $elements
     * @return array<int,array{status:string,message:string}>
     */
    private function checkElementReferences(array $elements): array
    {
        $checks = [];
        $labelsByReference = [];

        foreach ($elements as $element) {
            $referenceId = trim((string) ($element['reference_id'] ?? ''));
            if ($referenceId === '') {
                $checks[] = [
                    'status' => self::STATUS_ERROR,
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_AUDIT_CHECK_ELEMENT_REFERENCE_EMPTY',
                        (string) ($element['label'] ?? '')
                    ),
                ];
                continue;
            }

            $labelsByReference[$referenceId][] = (string) ($element['label'] ?? $referenceId);
        }

        foreach ($labelsByReference as $referenceId => $labels) {
            if (count($labels) < 2) {
                continue;
            }

            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_CHECK_ELEMENT_REFERENCE_DUPLICATE',
                    $referenceId,
                    implode(', ', $labels)
                ),
            ];
        }

        return $checks;
    }

    /**
     * @param array<int,array<string,mixed>> $published
     * @param array<int|string,string> $sourceNames
     * @return array<int,array{status:string,message:string}>
     */
    private function checkTemplates(array $published, array $sourceNames, string $sourceType, string $detailsTemplate, string $editableTemplate): array
    {
        $checks = [];

        if ($published !== [] && trim($detailsTemplate) === '') {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_DETAILS_TEMPLATE_EMPTY'),
            ];
        }

        if ($published !== [] && trim($editableTemplate) === '') {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_EDITABLE_TEMPLATE_EMPTY'),
            ];
        }

        foreach ($published as $element) {
            $referenceId = (string) $element['reference_id'];
            $name = (string) ($sourceNames[$referenceId] ?? '');
            if ($name === '') {
                continue;
            }
            $isSystemField = $this->isBreezingFormsSourceType($sourceType) && $this->isBreezingFormsSystemFieldName($name);
            $auditLabel = $this->formatSourceFieldAuditLabel($sourceType, $name);

            $quoted = preg_quote($name, '/');
            $inDetails = (bool) preg_match('/\\{' . $quoted . ':(label|value|item)\\}/i', $detailsTemplate);
            $hasEditItem = (bool) preg_match('/\\{' . $quoted . ':item\\}/i', $editableTemplate);
            $hasEditAny = $hasEditItem || preg_match('/\\{' . $quoted . ':(label|value)\\}/i', $editableTemplate);
            $isEditable = (int) $element['editable'] === 1;

            if ($detailsTemplate !== '' && !$inDetails) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf(
                        $isSystemField ? 'COM_CONTENTBUILDERNG_AUDIT_CHECK_SYSTEM_MISSING_IN_DETAILS' : 'COM_CONTENTBUILDERNG_AUDIT_CHECK_MISSING_IN_DETAILS',
                        $auditLabel
                    ),
                ];
            }

            if ($editableTemplate !== '' && !$hasEditAny) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf(
                        $isSystemField ? 'COM_CONTENTBUILDERNG_AUDIT_CHECK_SYSTEM_MISSING_IN_EDIT' : 'COM_CONTENTBUILDERNG_AUDIT_CHECK_MISSING_IN_EDIT',
                        $auditLabel
                    ),
                ];
            }

            if ($editableTemplate !== '' && $isEditable && $hasEditAny && !$hasEditItem) {
                $checks[] = [
                    'status' => self::STATUS_ERROR,
                    'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_EDITABLE_WITHOUT_ITEM', $name),
                ];
            }

            if ($editableTemplate !== '' && !$isEditable && $hasEditItem) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_NONEDITABLE_WITH_ITEM', $name),
                ];
            }
        }

        $lowerNames = array_map(
            static fn($name): string => function_exists('mb_strtolower') ? mb_strtolower((string) $name, 'UTF-8') : strtolower((string) $name),
            array_values($sourceNames)
        );

        foreach (['COM_CONTENTBUILDERNG_AUDIT_CHECK_UNKNOWN_MARKER_DETAILS' => $detailsTemplate, 'COM_CONTENTBUILDERNG_AUDIT_CHECK_UNKNOWN_MARKER_EDIT' => $editableTemplate] as $key => $template) {
            foreach ($this->extractMarkerNames($template) as $markerName) {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($markerName, 'UTF-8') : strtolower($markerName);
                if (!in_array($needle, $lowerNames, true)) {
                    $checks[] = [
                        'status' => self::STATUS_ERROR,
                        'message' => Text::sprintf($key, $markerName),
                    ];
                }
            }
        }

        return $checks;
    }

    private function isBreezingFormsSourceType(string $sourceType): bool
    {
        return $sourceType === 'com_breezingformsng';
    }

    private function formatSourceFieldAuditLabel(string $sourceType, string $name): string
    {
        if (!$this->isBreezingFormsSourceType($sourceType)) {
            return $name;
        }

        $definition = $this->getBreezingFormsSystemFieldDefinitionsByName()[$name] ?? null;
        if (!is_array($definition)) {
            return $name;
        }

        $label = trim((string) ($definition['label'] ?? ''));
        if ($label === '') {
            return $name;
        }

        return $label . ' (' . $name . ')';
    }

    /**
     * @return array<int,string>
     */
    private function extractMarkerNames(string $template): array
    {
        if ($template === '' || !preg_match_all('/\\{([^}:{]+):(label|value|item)\\}/i', $template, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn($name): string => trim((string) $name), $matches[1])));
    }
}
