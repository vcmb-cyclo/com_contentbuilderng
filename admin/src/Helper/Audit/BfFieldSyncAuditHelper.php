<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use Joomla\Database\DatabaseInterface;

final class BfFieldSyncAuditHelper
{
    /**
     * @return array{
     *   0:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     type:string,
     *     reference_id:int,
     *     source_name:string,
     *     source_exists:int,
     *     source_total:int,
     *     cb_total:int,
     *     missing_count:int,
     *     orphan_count:int,
     *     missing_in_cb:array<int,string>,
     *     orphan_in_cb:array<int,string>
     *   }>,
     *   1:array<int,string>
     * }
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'type', 'reference_id']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where(
                    $db->quoteName('type') . ' IN ('
                    . $db->quote('com_breezingforms') . ','
                    . $db->quote('com_breezingforms_ng') . ')'
                )
                ->where($db->quoteName('reference_id') . ' > 0');

            $db->setQuery($query);
            $forms = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect CB views linked to BF sources: ' . $e->getMessage();
            return [[], $errors];
        }

        foreach ($forms as $formRow) {
            $formId = (int) ($formRow['id'] ?? 0);
            $formName = trim((string) ($formRow['name'] ?? ''));
            $type = trim((string) ($formRow['type'] ?? ''));
            $referenceId = (int) ($formRow['reference_id'] ?? 0);

            if ($formId < 1 || $referenceId < 1 || $type === '') {
                continue;
            }

            $cbByReference = [];

            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['reference_id', 'label']))
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->where($db->quoteName('form_id') . ' = ' . $formId);

                $db->setQuery($query);
                $cbElements = $db->loadAssocList() ?: [];

                foreach ($cbElements as $cbElement) {
                    $refId = trim((string) ($cbElement['reference_id'] ?? ''));
                    if ($refId === '') {
                        continue;
                    }

                    $label = trim((string) ($cbElement['label'] ?? ''));
                    $cbByReference[$refId] = $label !== '' ? $label : $refId;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect CB elements for view #' . $formId . ': ' . $e->getMessage();
                continue;
            }

            try {
                $sourceForm = FormSourceFactory::getForm($type, (string) $referenceId);
            } catch (\Throwable $e) {
                $errors[] = 'Could not load source form for view #' . $formId . ': ' . $e->getMessage();
                continue;
            }

            if (!is_object($sourceForm) || empty($sourceForm->exists)) {
                $issues[] = [
                    'form_id' => $formId,
                    'form_name' => $formName,
                    'type' => $type,
                    'reference_id' => $referenceId,
                    'source_name' => '',
                    'source_exists' => 0,
                    'source_total' => 0,
                    'cb_total' => count($cbByReference),
                    'missing_count' => 0,
                    'orphan_count' => 0,
                    'missing_in_cb' => [],
                    'orphan_in_cb' => [],
                ];
                continue;
            }

            $sourceName = '';
            if (isset($sourceForm->properties) && isset($sourceForm->properties->name)) {
                $sourceName = trim((string) $sourceForm->properties->name);
            }

            $sourceElements = (array) $sourceForm->getElementLabels();
            $sourceByReference = [];

            foreach ($sourceElements as $reference => $label) {
                $refId = trim((string) $reference);
                if ($refId === '') {
                    continue;
                }

                $sourceLabel = trim((string) $label);
                $sourceByReference[$refId] = $sourceLabel !== '' ? $sourceLabel : $refId;
            }

            $missingRefs = array_diff_key($sourceByReference, $cbByReference);
            $orphanRefs = array_diff_key($cbByReference, $sourceByReference);

            if ($missingRefs === [] && $orphanRefs === []) {
                continue;
            }

            $missingLabels = array_values(array_unique(array_values($missingRefs)));
            $orphanLabels = array_values(array_unique(array_values($orphanRefs)));
            sort($missingLabels, SORT_NATURAL | SORT_FLAG_CASE);
            sort($orphanLabels, SORT_NATURAL | SORT_FLAG_CASE);

            $issues[] = [
                'form_id' => $formId,
                'form_name' => $formName,
                'type' => $type,
                'reference_id' => $referenceId,
                'source_name' => $sourceName,
                'source_exists' => 1,
                'source_total' => count($sourceByReference),
                'cb_total' => count($cbByReference),
                'missing_count' => count($missingLabels),
                'orphan_count' => count($orphanLabels),
                'missing_in_cb' => $missingLabels,
                'orphan_in_cb' => $orphanLabels,
            ];
        }

        usort(
            $issues,
            static fn(array $a, array $b): int => (int) ($a['form_id'] ?? 0) <=> (int) ($b['form_id'] ?? 0)
        );

        return [$issues, $errors];
    }
}
