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

final class ElementReferenceAuditHelper
{
    /**
     * @return array{0:array<int,array{form_id:int,form_name:string,type:string,reference_id:int,empty_reference_ids:array<int,string>,duplicate_reference_ids:array<int,array{reference_id:string,count:int,labels:array<int,string>}>,orphan_reference_ids:array<int,array{reference_id:string,label:string}>}>,1:array<int,string>}
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'type', 'reference_id']))
                    ->from($db->quoteName('#__contentbuilderng_forms'))
                    ->order($db->quoteName('id') . ' ASC')
            );
            $forms = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect forms for element reference audit: ' . $e->getMessage()]];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'form_id', 'label', 'reference_id']))
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->order($db->quoteName('ordering') . ' ASC')
            );
            $elements = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect elements for reference audit: ' . $e->getMessage()]];
        }

        $elementsByForm = [];
        foreach ($elements as $element) {
            $elementsByForm[(int) ($element['form_id'] ?? 0)][] = $element;
        }

        foreach ($forms as $form) {
            $formId = (int) ($form['id'] ?? 0);
            $formElements = (array) ($elementsByForm[$formId] ?? []);

            if ($formElements === []) {
                continue;
            }

            $emptyRefs = [];
            $refBuckets = [];

            foreach ($formElements as $element) {
                $label = trim((string) ($element['label'] ?? ''));
                $referenceId = trim((string) ($element['reference_id'] ?? ''));
                $label = $label !== '' ? $label : ('#' . (int) ($element['id'] ?? 0));

                if ($referenceId === '') {
                    $emptyRefs[] = $label;
                    continue;
                }

                $refBuckets[$referenceId][] = $label;
            }

            $duplicateRefs = [];
            foreach ($refBuckets as $referenceId => $labels) {
                if (count($labels) > 1) {
                    $duplicateRefs[] = [
                        'reference_id' => $referenceId,
                        'count' => count($labels),
                        'labels' => array_values(array_unique($labels)),
                    ];
                }
            }

            $orphanRefs = [];
            $type = trim((string) ($form['type'] ?? ''));
            $sourceRefId = (int) ($form['reference_id'] ?? 0);

            if ($type !== '' && $sourceRefId > 0) {
                try {
                    $sourceForm = FormSourceFactory::getForm($type, (string) $sourceRefId);
                    if (is_object($sourceForm) && !empty($sourceForm->exists)) {
                        $sourceRefs = array_fill_keys(
                            array_map('strval', array_keys((array) $sourceForm->getElementLabels())),
                            true
                        );

                        foreach ($formElements as $element) {
                            $referenceId = trim((string) ($element['reference_id'] ?? ''));
                            if ($referenceId === '' || isset($sourceRefs[$referenceId])) {
                                continue;
                            }

                            $label = trim((string) ($element['label'] ?? ''));
                            $orphanRefs[$referenceId] = [
                                'reference_id' => $referenceId,
                                'label' => $label !== '' ? $label : ('#' . (int) ($element['id'] ?? 0)),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Could not inspect source schema for form #' . $formId . ': ' . $e->getMessage();
                }
            }

            if ($emptyRefs !== [] || $duplicateRefs !== [] || $orphanRefs !== []) {
                $issues[] = [
                    'form_id' => $formId,
                    'form_name' => trim((string) ($form['name'] ?? '')),
                    'type' => $type,
                    'reference_id' => $sourceRefId,
                    'empty_reference_ids' => array_values(array_unique($emptyRefs)),
                    'duplicate_reference_ids' => $duplicateRefs,
                    'orphan_reference_ids' => array_values($orphanRefs),
                ];
            }
        }

        return [$issues, $errors];
    }
}
