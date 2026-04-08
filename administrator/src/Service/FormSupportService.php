<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class FormSupportService
{
    public function __construct(
        private readonly PathService $pathService = new PathService()
    ) {
    }

    public function getLanguageCodes(): array
    {
        static $langs;

        if (is_array($langs)) {
            return $langs;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
        return (new TemplateSampleService())->createDetailsSample($formId, $form, $plugin);
    }

    public function createEmailSample($formId, $form, $html = false)
    {
        return (new TemplateSampleService())->createEmailSample($formId, $form, $html);
    }

    public function createEditableSample($formId, $form, $plugin)
    {
        return (new TemplateSampleService())->createEditableSample($formId, $form, $plugin);
    }

    public function synchElements($formId, $form): array
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

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $ids = [];
        $elements = (array) $form->getElementLabels();

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
            $options = new \stdClass();
            $options->length = '';
            $options->maxlength = '';
            $options->password = 0;
            $options->readonly = 0;
            $options->seperator = ',';
            $ids[] = $db->quote($referenceId);

            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('type'), $db->quoteName('options')])
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
                    ->columns($db->quoteName(['label', 'form_id', 'reference_id', 'type', 'options', 'ordering']))
                    ->values(implode(',', [
                        $db->quote($title),
                        $db->quote($formId),
                        $db->quote($referenceId),
                        $db->quote('text'),
                        $db->quote(PackedDataHelper::encodePackedData($options)),
                        (int) ($ordering ?: 0),
                    ]));
                $db->setQuery($insertQuery);
                $db->execute();
                $report['added'][] = trim((string) $title) !== '' ? trim((string) $title) : (string) $referenceId;
            }

            unset($existingByReference[(string) $referenceId]);
        }

        if ($ids !== []) {
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                ->where($db->quoteName('reference_id') . ' NOT IN (' . implode(',', $ids) . ')');
            $db->setQuery($deleteQuery);
            $db->execute();
        }

        foreach ($existingByReference as $removedRow) {
            $removedLabel = trim((string) ($removedRow['label'] ?? ''));
            $removedRef = (string) ($removedRow['reference_id'] ?? '');
            $report['removed'][] = $removedLabel !== '' ? $removedLabel : $removedRef;
        }

        $report['added_count'] = count($report['added']);
        $report['removed_count'] = count($report['removed']);

        return $report;
    }

    public function getTypes(): array
    {
        $types = [];

        if ($this->isBreezingFormsAvailable()) {
            $types[] = 'com_breezingforms';
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
                return call_user_func([$class, 'getFormsList']);
            }
        }

        $customPath = JPATH_SITE . '/media/contentbuilderng/types/' . $type . '.php';

        if (file_exists($customPath)) {
            require_once $customPath;
            $class = 'contentbuilderng_' . $type;

            if (class_exists($class)) {
                return call_user_func([$class, 'getFormsList']);
            }
        }

        return [];
    }

    public function getFormElementsPlugins(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
            JPATH_ROOT . '/administrator/components/com_breezingforms/breezingforms.xml',
            JPATH_ROOT . '/administrator/components/com_breezingforms/com_breezingforms.xml',
            JPATH_ROOT . '/administrator/components/com_breezingforms_ng/com_breezingforms_ng.xml',
            JPATH_ROOT . '/administrator/components/com_breezingformsng/com_breezingformsng.xml',
            JPATH_ROOT . '/administrator/components/com_breezingformsng/breezingformsng.xml',
        ];

        foreach ($manifestCandidates as $manifest) {
            if (file_exists($manifest)) {
                return true;
            }
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' IN (' . $db->quote('com_breezingforms') . ',' . $db->quote('com_breezingforms_ng') . ')');
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
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
