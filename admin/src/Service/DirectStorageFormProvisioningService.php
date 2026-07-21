<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\StorageColumnTypeHelper;

/**
 * Resolves the #__contentbuilderng_forms record backing a "direct storage"
 * List/Edit/Details screen (bytable storage accessed via storage_id instead
 * of a regular form id), creating it -- along with its elements and default
 * templates -- on the fly from the storage definition the first time it's needed.
 */
final class DirectStorageFormProvisioningService
{
    private const TYPE = 'com_contentbuilderng';

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly FormSupportService $formSupportService
    ) {
    }

    public function resolveOrCreateFormId(int $storageId, string $themePlugin = 'thoth'): int
    {
        if ($storageId < 1) {
            return 0;
        }

        $formId = $this->findExistingFormId($storageId);

        if ($formId > 0) {
            $this->ensureTemplatesProvisioned($formId, $storageId, $themePlugin);

            return $formId;
        }

        $formId = $this->createForm($storageId);

        if ($formId < 1) {
            return 0;
        }

        $this->ensureTemplatesProvisioned($formId, $storageId, $themePlugin);

        return $formId;
    }

    private function findExistingFormId(int $storageId): int
    {
        $type = self::TYPE;
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__contentbuilderng_forms'))
            ->where($this->db->quoteName('type') . ' = :cbType')
            ->where($this->db->quoteName('reference_id') . ' = :cbReferenceId')
            ->bind(':cbType', $type)
            ->bind(':cbReferenceId', $storageId, ParameterType::INTEGER);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    private function createForm(int $storageId): int
    {
        $storageQuery = $this->db->getQuery(true)
            ->select($this->db->quoteName(['name', 'title']))
            ->from($this->db->quoteName('#__contentbuilderng_storages'))
            ->where($this->db->quoteName('id') . ' = :storageId')
            ->bind(':storageId', $storageId, ParameterType::INTEGER);
        $this->db->setQuery($storageQuery);
        $storage = $this->db->loadAssoc();

        if (!is_array($storage)) {
            return 0;
        }

        $type = self::TYPE;
        $tag = 'Auto';
        $name = (string) $storage['name'];
        $title = (string) $storage['title'];
        $config = PackedDataHelper::encodePackedData($this->defaultPermissionsConfig());

        $insertQuery = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__contentbuilderng_forms'))
            ->columns($this->db->quoteName(['type', 'reference_id', 'name', 'title', 'tag', 'published', 'config']))
            ->values(':cbType, :cbReferenceId, :cbName, :cbTitle, :cbTag, 1, :cbConfig')
            ->bind(':cbType', $type)
            ->bind(':cbReferenceId', $storageId, ParameterType::INTEGER)
            ->bind(':cbName', $name)
            ->bind(':cbTitle', $title)
            ->bind(':cbTag', $tag)
            ->bind(':cbConfig', $config);
        $this->db->setQuery($insertQuery);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Mirrors the checkboxes pre-checked by admin/layouts/form/permissions_tab.php
     * for a brand-new form (listaccess/view/new), plus edit, applied to every
     * user group so a freshly provisioned direct-storage form is immediately
     * usable on the frontend without a manual permissions setup pass.
     */
    private function defaultPermissionsConfig(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__usergroups'));
        $this->db->setQuery($query);
        $groupIds = $this->db->loadColumn() ?: [];

        $permissions = [];
        foreach ($groupIds as $groupId) {
            $permissions[(int) $groupId] = [
                'listaccess' => true,
                'view' => true,
                'new' => true,
                'edit' => true,
            ];
        }

        return ['permissions_fe' => $permissions];
    }

    /**
     * Syncs #__contentbuilderng_elements from the storage fields and fills in
     * editable_template/details_template from the theme sample generator,
     * whichever of the two is still empty (form created here or by hand
     * without running the samples yet).
     */
    private function ensureTemplatesProvisioned(int $formId, int $storageId, string $themePlugin): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['editable_template', 'details_template']))
            ->from($this->db->quoteName('#__contentbuilderng_forms'))
            ->where($this->db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        if (!is_array($row)) {
            return;
        }

        $editableTemplate = trim((string) ($row['editable_template'] ?? ''));
        $detailsTemplate = trim((string) ($row['details_template'] ?? ''));

        if ($editableTemplate !== '' && $detailsTemplate !== '') {
            return;
        }

        $form = FormSourceFactory::getForm(self::TYPE, $storageId);

        if (!is_object($form)) {
            return;
        }

        $this->formSupportService->synchElements($formId, $form);

        // synchElements() leaves new elements non-editable (editable=0) by
        // design for the manual admin flow (fields are reviewed before being
        // opened up). A form auto-provisioned from a storage has no such
        // review step, so make its fields immediately usable.
        $formIdForElements = $formId;
        $elementsUpdate = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__contentbuilderng_elements'))
            ->set($this->db->quoteName('editable') . ' = 1')
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->bind(':formId', $formIdForElements, ParameterType::INTEGER);
        $this->db->setQuery($elementsUpdate);
        $this->db->execute();

        $this->enableSearchForTextAndDateFields($formId, $storageId);

        $update = $this->db->getQuery(true)->update($this->db->quoteName('#__contentbuilderng_forms'));

        if ($editableTemplate === '') {
            $editableTemplate = (string) $this->formSupportService->createEditableSample($formId, $form, $themePlugin);
            $update->set($this->db->quoteName('editable_template') . ' = :editableTemplate')
                ->bind(':editableTemplate', $editableTemplate);
        }

        if ($detailsTemplate === '') {
            $detailsTemplate = (string) $this->formSupportService->createDetailsSample($formId, $form, $themePlugin);
            $update->set($this->db->quoteName('details_template') . ' = :detailsTemplate')
                ->bind(':detailsTemplate', $detailsTemplate);
        }

        if ($editableTemplate === '' && $detailsTemplate === '') {
            return;
        }

        $formIdBind = $formId;
        $update->where($this->db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formIdBind, ParameterType::INTEGER);
        $this->db->setQuery($update);
        $this->db->execute();
    }

    /**
     * Enables list search (search_include) on the elements synced from
     * text/varchar and date/datetime storage fields, so free-text search on
     * the frontend list covers them out of the box.
     */
    private function enableSearchForTextAndDateFields(int $formId, int $storageId): void
    {
        $fieldsQuery = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'sql_type']))
            ->from($this->db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($this->db->quoteName('storage_id') . ' = :storageId')
            ->bind(':storageId', $storageId, ParameterType::INTEGER);
        $this->db->setQuery($fieldsQuery);
        $fields = $this->db->loadAssocList() ?: [];

        $searchableTypes = ['text', 'varchar', 'date', 'datetime'];
        $searchableIds = [];

        foreach ($fields as $field) {
            if (in_array(StorageColumnTypeHelper::normalize($field['sql_type'] ?? null), $searchableTypes, true)) {
                $searchableIds[] = (int) $field['id'];
            }
        }

        if ($searchableIds === []) {
            return;
        }

        $formIdForSearch = $formId;
        $update = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__contentbuilderng_elements'))
            ->set($this->db->quoteName('search_include') . ' = 1')
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->where($this->db->quoteName('reference_id') . ' IN (' . implode(',', $searchableIds) . ')')
            ->bind(':formId', $formIdForSearch, ParameterType::INTEGER);
        $this->db->setQuery($update);
        $this->db->execute();
    }
}
