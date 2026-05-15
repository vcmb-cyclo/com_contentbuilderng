<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Service;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Service\ApiFieldPermissionService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

final class StatsService
{
    public function getStatsPayload(int $formId, array $options = []): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('type'),
                $db->quoteName('reference_id'),
                $db->quoteName('published'),
            ])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow) || empty($formRow['type']) || empty($formRow['reference_id'])) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        $nowSql = Factory::getDate()->toSql();
        $recordWhere = [
            $db->quoteName('type') . ' = ' . $db->quote((string) $formRow['type']),
            $db->quoteName('reference_id') . ' = ' . $db->quote((string) $formRow['reference_id']),
        ];
        $statsFilter = $this->getStatsFilterPayload($formId, $formRow, $options);

        if ($statsFilter !== null) {
            $recordWhere[] = $statsFilter['where'];
        }

        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) AS ' . $db->quoteName('total'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('published'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('published') . ' = 0 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('unpublished'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('is_future') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('future'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('edited') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('edited'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('publish_up') . ' IS NOT NULL AND ' . $db->quoteName('publish_up') . ' > ' . $db->quote($nowSql) . ' THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('scheduled'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('publish_down') . ' IS NOT NULL AND ' . $db->quoteName('publish_down') . ' < ' . $db->quote($nowSql) . ' THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('expired'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('rating_count') . ' > 0 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('rated_records'),
                'COALESCE(SUM(' . $db->quoteName('rating_count') . '), 0) AS ' . $db->quoteName('rating_count'),
                'COALESCE(SUM(' . $db->quoteName('rating_sum') . '), 0) AS ' . $db->quoteName('rating_sum'),
                'MAX(' . $db->quoteName('last_update') . ') AS ' . $db->quoteName('last_update'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($recordWhere);
        $db->setQuery($query, 0, 1);
        $records = $db->loadAssoc() ?: [];

        $ratingCount = (int) ($records['rating_count'] ?? 0);
        $ratingSum = (int) ($records['rating_sum'] ?? 0);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lang_code'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($recordWhere)
            ->group($db->quoteName('lang_code'))
            ->order($db->quoteName('lang_code'));
        $db->setQuery($query);
        $languageRows = $db->loadAssocList() ?: [];
        $languages = [];

        foreach ($languageRows as $languageRow) {
            $languages[(string) ($languageRow['lang_code'] ?? '*')] = (int) ($languageRow['total'] ?? 0);
        }

        $fieldStats = $this->getStatsFieldPayload($formId, $formRow, $options);

        return [
            'form' => [
                'id' => (int) $formRow['id'],
                'name' => (string) ($formRow['name'] ?? ''),
            ],
            'records' => [
                'total' => (int) ($records['total'] ?? 0),
                'published' => (int) ($records['published'] ?? 0),
                'unpublished' => (int) ($records['unpublished'] ?? 0),
                'future' => (int) ($records['future'] ?? 0),
                'edited' => (int) ($records['edited'] ?? 0),
                'scheduled' => (int) ($records['scheduled'] ?? 0),
                'expired' => (int) ($records['expired'] ?? 0),
                'last_update' => (string) ($records['last_update'] ?? ''),
            ],
            'ratings' => [
                'rated_records' => (int) ($records['rated_records'] ?? 0),
                'rating_count' => $ratingCount,
                'rating_sum' => $ratingSum,
                'average' => $ratingCount > 0 ? round($ratingSum / $ratingCount, 4) : 0.0,
            ],
            'languages' => $languages,
        ] + ($statsFilter !== null ? ['filter' => $statsFilter['payload']] : []) + ($fieldStats !== null ? ['field' => $fieldStats] : []);
    }

    private function getStatsFilterPayload(int $formId, array $formRow, array $options): ?array
    {
        $filter = $this->getRequestedStatsFilter($options);

        if ($filter === null) {
            return null;
        }

        $field = $this->resolveStatsField($formId, $formRow, $filter['field']);
        if ($field === null) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED'), 403);
        }

        return [
            'where' => $this->getStatsFilterWhere($formRow, $field, $filter['value']),
            'payload' => [
                'field' => $filter['field'],
                'value' => $filter['value'],
            ],
        ];
    }

    private function getRequestedStatsFilter(array $options): ?array
    {
        $filter = (array) ($options['filter'] ?? []);
        $field = trim((string) ($filter['field'] ?? ''));
        $value = trim((string) ($filter['value'] ?? ''));

        if ($field !== '' && $value !== '') {
            return ['field' => $field, 'value' => $value];
        }

        return null;
    }

    private function getStatsFilterWhere(array $formRow, array $field, string $value): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return match ((string) $formRow['type']) {
            'com_contentbuilderng' => $this->getContentbuilderngStatsFilterWhere($formRow, $field, $value),
            'com_breezingforms' => $db->quoteName('record_id') . ' IN (' . $this->getBreezingFormsStatsFilterRecordQuery($formRow, $field, $value) . ')',
            default => '1 = 0',
        };
    }

    private function getContentbuilderngStatsFilterWhere(array $formRow, array $field, string $value): string
    {
        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        $properties = is_object($form) && isset($form->properties) && is_object($form->properties) ? $form->properties : null;

        if (!$properties || empty($properties->name)) {
            return '1 = 0';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableName = ((int) ($properties->bytable ?? 0) === 1 ? '' : '#__') . (string) $properties->name;
        $query = $db->getQuery(true)
            ->select($db->quoteName('source.id'))
            ->from($db->quoteName($tableName, 'source'))
            ->where('TRIM(' . $db->quoteName('source.' . (string) $field['name']) . ') = ' . $db->quote($value));

        return $db->quoteName('record_id') . ' IN (' . (string) $query . ')';
    }

    private function getBreezingFormsStatsFilterRecordQuery(array $formRow, array $field, string $value): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $valueColumn = $db->quoteName('subrecords.value');
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('bf_records.id'))
            ->from($db->quoteName('#__facileforms_records', 'bf_records'))
            ->join('INNER', $db->quoteName('#__facileforms_subrecords', 'subrecords') . ' ON ' . $db->quoteName('subrecords.record') . ' = ' . $db->quoteName('bf_records.id'))
            ->where($db->quoteName('bf_records.form') . ' = ' . (int) $formRow['reference_id'])
            ->where('('
                . $db->quoteName('subrecords.element') . ' = ' . (int) $field['reference_id']
                . ' OR ' . $db->quoteName('subrecords.name') . ' = ' . $db->quote((string) $field['name'])
                . ')')
            ->where('TRIM(' . $valueColumn . ') = ' . $db->quote($value));

        return (string) $query;
    }

    private function getStatsFieldPayload(int $formId, array $formRow, array $options): ?array
    {
        $requestedField = trim((string) ($options['field'] ?? ''));

        if ($requestedField === '') {
            return null;
        }

        $field = $this->resolveStatsField($formId, $formRow, $requestedField);
        if ($field === null) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED'), 403);
        }

        $values = match ((string) $formRow['type']) {
            'com_contentbuilderng' => $this->getContentbuilderngStatsFieldValues($formRow, $field),
            'com_breezingforms' => $this->getBreezingFormsStatsFieldValues($formRow, $field),
            default => [],
        };

        return [
            'requested' => $requestedField,
            'reference_id' => (int) $field['reference_id'],
            'name' => (string) $field['name'],
            'label' => (string) $field['label'],
            'total' => array_sum($values),
            'values' => $values,
        ];
    }

    private function resolveStatsField(int $formId, array $formRow, string $requestedField): ?array
    {
        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        if (!$form || !is_object($form)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_ERROR'), 404);
        }

        $names = method_exists($form, 'getElementNames') ? (array) $form->getElementNames() : [];
        $labels = method_exists($form, 'getElementLabels') ? (array) $form->getElementLabels() : [];
        $allowedReferences = (new ApiFieldPermissionService())->getAllowedReferenceMap($formId);

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('reference_id'), $db->quoteName('label')])
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('api_allowed') . ' = 1')
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];

        $needle = $this->normalizeStatsFieldName($requestedField);

        foreach ($rows as $row) {
            $referenceId = (string) ($row['reference_id'] ?? '');
            if ($referenceId === '' || !isset($allowedReferences[$referenceId])) {
                continue;
            }
            $name = (string) ($names[$referenceId] ?? '');
            $label = (string) (($row['label'] ?? '') !== '' ? $row['label'] : ($labels[$referenceId] ?? ''));
            $candidates = [$referenceId, $name, $label, (string) ($labels[$referenceId] ?? '')];

            if ($label !== '' && $name !== '') {
                $candidates[] = $label . ' (' . $name . ')';
            }

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && $this->normalizeStatsFieldName($candidate) === $needle) {
                    return [
                        'reference_id' => (int) $referenceId,
                        'name' => $name !== '' ? $name : $referenceId,
                        'label' => $label !== '' ? $label : ($name !== '' ? $name : $referenceId),
                    ];
                }
            }
        }

        return null;
    }

    private function normalizeStatsFieldName(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function getContentbuilderngStatsFieldValues(array $formRow, array $field): array
    {
        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        $properties = is_object($form) && isset($form->properties) && is_object($form->properties) ? $form->properties : null;

        if (!$properties || empty($properties->name)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableName = ((int) ($properties->bytable ?? 0) === 1 ? '' : '#__') . (string) $properties->name;
        $valueColumn = $db->quoteName('source.' . (string) $field['name']);
        $query = $db->getQuery(true)
            ->select([
                'TRIM(' . $valueColumn . ') AS ' . $db->quoteName('value'),
                'COUNT(DISTINCT ' . $db->quoteName('records.record_id') . ') AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->join('INNER', $db->quoteName($tableName, 'source') . ' ON ' . $db->quoteName('source.id') . ' = ' . $db->quoteName('records.record_id'))
            ->where($this->getStatsRecordWhere($formRow, 'records'))
            ->where('TRIM(' . $valueColumn . ') <> ' . $db->quote(''))
            ->group('TRIM(' . $valueColumn . ')')
            ->order('TRIM(' . $valueColumn . ')');
        $db->setQuery($query);

        return $this->formatStatsFieldRows($db->loadAssocList() ?: []);
    }

    private function getBreezingFormsStatsFieldValues(array $formRow, array $field): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $valueColumn = $db->quoteName('subrecords.value');
        $query = $db->getQuery(true)
            ->select([
                'TRIM(' . $valueColumn . ') AS ' . $db->quoteName('value'),
                'COUNT(DISTINCT ' . $db->quoteName('records.record_id') . ') AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->join('INNER', $db->quoteName('#__facileforms_records', 'bf_records') . ' ON ' . $db->quoteName('bf_records.id') . ' = ' . $db->quoteName('records.record_id'))
            ->join('INNER', $db->quoteName('#__facileforms_subrecords', 'subrecords') . ' ON ' . $db->quoteName('subrecords.record') . ' = ' . $db->quoteName('bf_records.id'))
            ->where($this->getStatsRecordWhere($formRow, 'records'))
            ->where($db->quoteName('bf_records.form') . ' = ' . (int) $formRow['reference_id'])
            ->where('('
                . $db->quoteName('subrecords.element') . ' = ' . (int) $field['reference_id']
                . ' OR ' . $db->quoteName('subrecords.name') . ' = ' . $db->quote((string) $field['name'])
                . ')')
            ->where('TRIM(' . $valueColumn . ') <> ' . $db->quote(''))
            ->group('TRIM(' . $valueColumn . ')')
            ->order('TRIM(' . $valueColumn . ')');
        $db->setQuery($query);

        return $this->formatStatsFieldRows($db->loadAssocList() ?: []);
    }

    private function getStatsRecordWhere(array $formRow, string $alias = ''): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $prefix = $alias !== '' ? $alias . '.' : '';

        return [
            $db->quoteName($prefix . 'type') . ' = ' . $db->quote((string) $formRow['type']),
            $db->quoteName($prefix . 'reference_id') . ' = ' . $db->quote((string) $formRow['reference_id']),
        ];
    }

    private function formatStatsFieldRows(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = (string) ($row['value'] ?? '');
            if ($value === '') {
                continue;
            }

            $values[$value] = (int) ($row['total'] ?? 0);
        }

        return $values;
    }
}
