<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\StorageColumnTypeHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class FormSupportService
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TemplateSampleService $templateSampleService
    ) {
    }

    public function getLanguageCodes(): array
    {
        static $langs;

        if (is_array($langs)) {
            return $langs;
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('lang_code'))
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $langs = $db->loadColumn();

        return $langs;
    }

    public function createDetailsSample($formId, $form, $plugin)
    {
        return $this->templateSampleService->createDetailsSample($formId, $form, $plugin);
    }

    public function createEmailSample($formId, $form, $html = false)
    {
        return $this->templateSampleService->createEmailSample($formId, $form, $html);
    }

    public function createEditableSample($formId, $form, $plugin)
    {
        return $this->templateSampleService->createEditableSample($formId, $form, $plugin);
    }

    /**
     * Regenerates locked templates after an element-level change.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public function resyncLockedTemplates(int $formId, int $modifiedByUserId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['type', 'reference_id', 'theme_plugin', 'details_template', 'editable_template', 'details_template_locked', 'editable_template_locked']))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow)) {
            return [];
        }

        $lockedTemplates = [];
        if (!empty($formRow['details_template_locked'])) {
            $lockedTemplates['details_template'] = [
                'label' => 'COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY',
                'current' => (string) ($formRow['details_template'] ?? ''),
                'generator' => 'createDetailsSample',
            ];
        }
        if (!empty($formRow['editable_template_locked'])) {
            $lockedTemplates['editable_template'] = [
                'label' => 'COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY',
                'current' => (string) ($formRow['editable_template'] ?? ''),
                'generator' => 'createEditableSample',
            ];
        }
        if ($lockedTemplates === []) {
            return [];
        }

        try {
            [$sourceForm, $themePlugin] = $this->resolveFormForTemplateRegeneration($formId);
        } catch (\Throwable $e) {
            return array_values(array_map(
                static fn(array $template): array => [
                    'type' => 'warning',
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_TEMPLATE_LOCKED_RESYNC_FAILED',
                        Text::_($template['label']),
                        $e->getMessage()
                    ),
                ],
                $lockedTemplates
            ));
        }

        $messages = [];
        foreach ($lockedTemplates as $column => $template) {
            try {
                $regenerated = (string) $this->{$template['generator']}($formId, $sourceForm, $themePlugin);
                if (trim($regenerated) === '') {
                    throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_DETAILS_SAMPLE_EMPTY', $themePlugin));
                }
                if ($regenerated === $template['current']) {
                    continue;
                }
                $this->saveRegeneratedTemplate($formId, $column, $regenerated, $modifiedByUserId);
                $messages[] = [
                    'type' => 'message',
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_TEMPLATE_LOCKED_RESYNCED',
                        Text::_($template['label'])
                    ),
                ];
            } catch (\Throwable $e) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_TEMPLATE_LOCKED_RESYNC_FAILED',
                        Text::_($template['label']),
                        $e->getMessage()
                    ),
                ];
            }
        }

        return $messages;
    }

    /**
     * Regenerates and saves the editable-template sample for a view, using its
     * currently configured theme plugin. Shared by the admin About audit repair
     * action and the per-form audit tab repair action, which only differ in how
     * they read the form id and enforce their own ACL before calling this.
     *
     * @throws \RuntimeException when the form cannot be resolved or the theme
     *                            produces an empty template.
     *
     * @return string the form's name (HTML-escaped), for use in a confirmation message
     */

    public function regenerateEditableTemplate(int $formId, int $modifiedByUserId): string
    {
        [$sourceForm, $themePlugin, $formName] = $this->resolveFormForTemplateRegeneration($formId);

        $editableTemplate = (string) $this->createEditableSample($formId, $sourceForm, $themePlugin);

        if (trim($editableTemplate) === '') {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_EMPTY'));
        }

        $this->saveRegeneratedTemplate($formId, 'editable_template', $editableTemplate, $modifiedByUserId);

        return $formName;
    }

    /**
     * Regenerates and saves the details-template sample for a view, using its
     * currently configured theme plugin. See regenerateEditableTemplate() for
     * the shared resolution logic and callers.
     *
     * @throws \RuntimeException when the form cannot be resolved or the theme
     *                            produces an empty template.
     *
     * @return string the form's name (HTML-escaped), for use in a confirmation message
     */
    public function regenerateDetailsTemplate(int $formId, int $modifiedByUserId): string
    {
        [$sourceForm, $themePlugin, $formName] = $this->resolveFormForTemplateRegeneration($formId);

        $detailsTemplate = (string) $this->createDetailsSample($formId, $sourceForm, $themePlugin);

        if (trim($detailsTemplate) === '') {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_DETAILS_TEMPLATE_REPAIR_EMPTY'));
        }

        $this->saveRegeneratedTemplate($formId, 'details_template', $detailsTemplate, $modifiedByUserId);

        return $formName;
    }

    /**
     * Regenerates and saves both the details- and editable-template samples
     * for a view in one call, used when the audit flags both as empty at once.
     *
     * @throws \RuntimeException when the form cannot be resolved or the theme
     *                            produces an empty template for either one.
     *
     * @return string the form's name (HTML-escaped), for use in a confirmation message
     */
    public function regenerateBothTemplates(int $formId, int $modifiedByUserId): string
    {
        $this->regenerateDetailsTemplate($formId, $modifiedByUserId);

        return $this->regenerateEditableTemplate($formId, $modifiedByUserId);
    }

    /**
     * Replaces the {field:value} marker with {field:item} for a single field in
     * the existing, non-empty editable template, leaving everything else in the
     * template untouched. Used when the audit's "editable field without item"
     * check fires on a template that already has custom layout around it, where
     * a full regeneration from the theme would discard that customization.
     *
     * @throws \RuntimeException when the form cannot be resolved, the field name
     *                            is empty, or no {field:value} marker is found.
     *
     * @return string the form's name (HTML-escaped), for use in a confirmation message
     */
    public function replaceEditableFieldValueWithItem(int $formId, string $fieldName, int $modifiedByUserId): string
    {
        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_INVALID'));
        }

        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_ITEM_REPAIR_INVALID_FIELD'));
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['editable_template', 'name']))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_INVALID'));
        }

        $formName = htmlspecialchars(trim((string) ($formRow['name'] ?? '')) ?: ('#' . $formId), ENT_QUOTES, 'UTF-8');
        $editableTemplate = (string) ($formRow['editable_template'] ?? '');

        $pattern = '/\{' . preg_quote($fieldName, '/') . ':value\}/i';
        $updatedTemplate = preg_replace($pattern, '{' . $fieldName . ':item}', $editableTemplate, -1, $replacedCount);

        if ($updatedTemplate === null || $replacedCount < 1) {
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_ITEM_REPAIR_NOT_FOUND', $fieldName));
        }

        $this->saveRegeneratedTemplate($formId, 'editable_template', $updatedTemplate, $modifiedByUserId);

        return $formName;
    }

    /**
     * Removes one unknown field marker from an existing template while
     * preserving all other template content.
     *
     * @throws \RuntimeException when the form, template type or marker cannot
     *                            be resolved, or when the marker is not found.
     */
    public function removeUnknownTemplateMarker(
        int $formId,
        string $templateType,
        string $markerName,
        int $modifiedByUserId
    ): string {
        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_INVALID'));
        }

        $templateColumn = match (trim($templateType)) {
            'details' => 'details_template',
            'edit' => 'editable_template',
            default => throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_INVALID')),
        };
        $markerName = trim($markerName);

        if ($markerName === '') {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_INVALID'));
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['name', $templateColumn]))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_INVALID'));
        }

        $template = (string) ($formRow[$templateColumn] ?? '');
        $pattern = '/\{' . preg_quote($markerName, '/') . ':(label|value|item)\}/i';
        $updatedTemplate = preg_replace($pattern, '', $template, -1, $replacedCount);

        if ($updatedTemplate === null || $replacedCount < 1) {
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_NOT_FOUND', $markerName));
        }

        $formName = htmlspecialchars(trim((string) ($formRow['name'] ?? '')) ?: ('#' . $formId), ENT_QUOTES, 'UTF-8');
        $this->saveRegeneratedTemplate($formId, $templateColumn, $updatedTemplate, $modifiedByUserId);

        return $formName;
    }

    /**
     * @return array{0:object,1:string,2:string} the resolved source form, theme plugin and form name
     * @throws \RuntimeException when the form or its source cannot be resolved.
     */
    private function resolveFormForTemplateRegeneration(int $formId): array
    {
        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_INVALID'));
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['type', 'reference_id', 'theme_plugin', 'name']))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_INVALID'));
        }

        $sourceForm = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        if (!is_object($sourceForm)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'));
        }

        $themePlugin = trim((string) ($formRow['theme_plugin'] ?? '')) ?: 'thoth';
        $formName = trim((string) ($formRow['name'] ?? '')) ?: ('#' . $formId);
        $formName = htmlspecialchars($formName, ENT_QUOTES, 'UTF-8');

        return [$sourceForm, $themePlugin, $formName];
    }

    private function saveRegeneratedTemplate(int $formId, string $column, string $template, int $modifiedByUserId): void
    {
        $db = $this->db;
        $modifiedValue = (new Date())->toSql();
        $update = $db->getQuery(true)
            ->update($db->quoteName('#__contentbuilderng_forms'))
            ->set($db->quoteName($column) . ' = :template')
            ->set($db->quoteName('modified') . ' = :modified')
            ->set($db->quoteName('modified_by') . ' = :modifiedBy')
            ->where($db->quoteName('id') . ' = :formId')
            ->bind(':template', $template)
            ->bind(':modified', $modifiedValue)
            ->bind(':modifiedBy', $modifiedByUserId, ParameterType::INTEGER)
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $db->setQuery($update);
        $db->execute();
    }

    public function synchElements($formId, $form, bool $removeMissing = true): array
    {
        $report = [
            'added' => [],
            'removed' => [],
            'added_count' => 0,
            'removed_count' => 0,
        ];

        if (!$formId || !is_object($form)) {
            return $report;
        }

        $db = $this->db;
        $ids = [];
        $elements = (array) $form->getElementLabels();
        $editableTypes = method_exists($form, 'getEditableElementTypes') ? (array) $form->getEditableElementTypes() : [];
        $synchronizeEditableTypes = !$removeMissing
            && method_exists($form, 'shouldSynchronizeEditableElementTypes')
            && $form->shouldSynchronizeEditableElementTypes();
        $synchronizeSourceDefaultTypes = method_exists($form, 'shouldSynchronizeSourceDefaultEditableTypes')
            && $form->shouldSynchronizeSourceDefaultEditableTypes();

        $query = $db->getQuery(true)
            ->select([$db->quoteName('reference_id'), $db->quoteName('label')])
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);
        $existingRows = (array) $db->loadAssocList();
        $existingByReference = [];

        foreach ($existingRows as $row) {
            $referenceId = (string) ($row['reference_id'] ?? '');

            if ($referenceId !== '') {
                $existingByReference[$referenceId] = $row;
            }
        }

        foreach ($elements as $referenceId => $title) {
            $isReservedReference = method_exists($form, 'isSystemFieldReferenceId')
                && $form::isSystemFieldReferenceId($referenceId);
            $options = new \stdClass();
            $options->length = '';
            $options->maxlength = '';
            $options->password = 0;
            $options->readonly = 0;
            $options->seperator = ',';
            $ids[] = $db->quote($referenceId);

            if ($isReservedReference) {
                unset($existingByReference[(string) $referenceId]);
                continue;
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'type', 'change_type', 'options']))
                ->from($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                ->where($db->quoteName('reference_id') . ' = ' . $db->quote($referenceId));
            $db->setQuery($query);
            $assoc = $db->loadAssoc();

            if (!is_array($assoc)) {
                $orderingQuery = $db->getQuery(true)
                    ->select('MAX(' . $db->quoteName('ordering') . ') + 1')
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
                $db->setQuery($orderingQuery);
                $ordering = $db->loadResult();

                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName('#__contentbuilderng_elements'))
                    ->columns($db->quoteName(['label', 'form_id', 'reference_id', 'type', 'options', 'list_include', 'search_include', 'ordering']))
                    ->values(implode(',', [
                        $db->quote($title),
                        $db->quote($formId),
                        $db->quote($referenceId),
                        $db->quote((string) ($editableTypes[(string) $referenceId] ?? 'text')),
                        $db->quote(PackedDataHelper::encodePackedData($options)),
                        1,
                        0,
                        (int) ($ordering ?: 0),
                    ]));
                $db->setQuery($insertQuery);
                $db->execute();
                $report['added'][] = trim((string) $title) !== '' ? trim((string) $title) : (string) $referenceId;
            } elseif ($synchronizeEditableTypes || $synchronizeSourceDefaultTypes) {
                $currentType = (string) ($assoc['type'] ?? '');
                $expectedType = (string) ($editableTypes[(string) $referenceId] ?? 'text');
                $synchronizeStorageType = $synchronizeEditableTypes
                    && $currentType !== $expectedType
                    && StorageColumnTypeHelper::isStorageManagedEditableType($currentType);
                $synchronizeSourceType = $synchronizeSourceDefaultTypes
                    && ElementSettingsStateService::shouldSynchronizeSourceType((object) $assoc, $expectedType);

                if ($synchronizeStorageType || $synchronizeSourceType) {
                    $elementId = (int) ($assoc['id'] ?? 0);
                    $updateTypeQuery = $db->getQuery(true)
                        ->update($db->quoteName('#__contentbuilderng_elements'))
                        ->set($db->quoteName('type') . ' = ' . $db->quote($expectedType))
                        ->where($db->quoteName('id') . ' = ' . $elementId)
                        ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
                    $db->setQuery($updateTypeQuery);
                    $db->execute();
                }
            }

            unset($existingByReference[(string) $referenceId]);
        }

        if ($removeMissing && $ids !== []) {
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                ->where($db->quoteName('reference_id') . ' NOT IN (' . implode(',', $ids) . ')');
            $db->setQuery($deleteQuery);
            $db->execute();
        }

        if ($removeMissing) {
            foreach ($existingByReference as $removedRow) {
                $removedLabel = trim((string) ($removedRow['label'] ?? ''));
                $removedRef = (string) ($removedRow['reference_id'] ?? '');
                $report['removed'][] = $removedLabel !== '' ? $removedLabel : $removedRef;
            }
        }

        $report['added_count'] = count($report['added']);
        $report['removed_count'] = count($report['removed']);

        return $report;
    }

    public function getTypes(): array
    {
        $types = [];

        if ($this->isBreezingFormsAvailable()) {
            $types[] = 'com_breezingformsng';
        }

        $types[] = 'com_contentbuilderng';

        if (!is_dir(JPATH_SITE . '/media/contentbuilderng')) {
            Folder::create(JPATH_SITE . '/media/contentbuilderng');
        }

        $def = '';

        if (!file_exists(JPATH_SITE . '/media/contentbuilderng/index.html')) {
            File::write(JPATH_SITE . '/media/contentbuilderng/index.html', $def);
        }

        if (!is_dir(JPATH_SITE . '/media/contentbuilderng/types')) {
            Folder::create(JPATH_SITE . '/media/contentbuilderng/types');
        }

        if (!file_exists(JPATH_SITE . '/media/contentbuilderng/types/index.html')) {
            File::write(JPATH_SITE . '/media/contentbuilderng/types/index.html', $def);
        }

        $sourcePath = JPATH_SITE . '/media/contentbuilderng/types/';

        if (is_dir($sourcePath) && @is_readable($sourcePath) && ($handle = @opendir($sourcePath))) {
            while (false !== ($file = @readdir($handle))) {
                if (
                    $file !== '.'
                    && $file !== '..'
                    && strtolower($file) !== 'index.html'
                    && strtolower($file) !== '.cvs'
                    && strtolower($file) !== '.svn'
                ) {
                    $exploded = explode('.', $file);
                    unset($exploded[count($exploded) - 1]);
                    $types[] = implode('.', $exploded);
                }
            }

            @closedir($handle);
        }

        return $types;
    }

    public function getForms($type): array
    {
        $type = trim((string) $type);

        if ($type === '') {
            return [];
        }

        $namespace = 'CB\\Component\\Contentbuilderng\\Administrator\\types\\';
        $adminTypeCandidates = [$type];

        if ($type === 'com_contentbuilderng') {
            $adminTypeCandidates[] = 'com_contentbuilder';
        } elseif ($type === 'com_contentbuilder') {
            $adminTypeCandidates[] = 'com_contentbuilderng';
        }

        foreach ($adminTypeCandidates as $adminType) {
            $candidate = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/' . $adminType . '.php';

            if (file_exists($candidate)) {
                require_once $candidate;
            }
        }

        $classCandidates = [$namespace . 'contentbuilderng_' . $type];

        if ($type === 'com_contentbuilderng') {
            $classCandidates[] = $namespace . 'contentbuilderng_com_contentbuilder';
        } elseif ($type === 'com_contentbuilder') {
            $classCandidates[] = $namespace . 'contentbuilderng_com_contentbuilderng';
        }

        foreach ($classCandidates as $class) {
            if (class_exists($class)) {
                return $this->sortFormsList(call_user_func([$class, 'getFormsList']));
            }
        }

        $customPath = JPATH_SITE . '/media/contentbuilderng/types/' . $type . '.php';

        if (file_exists($customPath)) {
            require_once $customPath;
            $class = 'contentbuilderng_' . $type;

            if (class_exists($class)) {
                return $this->sortFormsList(call_user_func([$class, 'getFormsList']));
            }
        }

        return [];
    }

    private function sortFormsList(mixed $forms): array
    {
        if (!is_array($forms)) {
            return [];
        }

        uasort(
            $forms,
            static fn(mixed $a, mixed $b): int => strnatcasecmp((string) $a, (string) $b)
        );

        return $forms;
    }

    public function getFormElementsPlugins(): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_form_elements'))
            ->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);

        return $db->loadColumn();
    }

    private function isBreezingFormsAvailable(): bool
    {
        $manifestCandidates = [
            JPATH_ROOT . '/administrator/components/com_breezingformsng/com_breezingformsng.xml',
            JPATH_ROOT . '/administrator/components/com_breezingformsng/breezingformsng.xml',
        ];

        foreach ($manifestCandidates as $manifest) {
            if (file_exists($manifest)) {
                return true;
            }
        }

        try {
            $db = $this->db;
            $query = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_breezingformsng'));
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        try {
            $db = $this->db;
            $tables = array_map('strtolower', (array) $db->getTableList());
            $required = [
                strtolower($db->replacePrefix('#__facileforms_forms')),
                strtolower($db->replacePrefix('#__facileforms_records')),
            ];

            return !array_diff($required, $tables);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
