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

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
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

    /**
     * @param bool $isAdminProvisioned Set to true when the form is created
     *   from a deliberate, authenticated admin action (e.g. the Storage
     *   wizard) rather than an anonymous/authenticated frontend request
     *   auto-provisioning it on the fly. Only affects the default permissions
     *   applied when the form does not already exist: an admin-provisioned
     *   form is granted sensible read/write defaults so it is immediately
     *   usable, while a frontend-auto-provisioned form stays read-only for
     *   Guest and grants nothing else, since nobody reviewed it.
     */
    public function resolveOrCreateFormId(int $storageId, string $themePlugin = 'thoth', bool $isAdminProvisioned = false): int
    {
        if ($storageId < 1) {
            return 0;
        }

        $formId = $this->findExistingFormId($storageId);

        if ($formId > 0) {
            if ($isAdminProvisioned) {
                $this->ensureAdminProvisionedPermissions($formId);
            }

            $this->ensureTemplatesProvisioned($formId, $storageId, $themePlugin);

            return $formId;
        }

        $formId = $this->createForm($storageId, $isAdminProvisioned);

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

    private function createForm(int $storageId, bool $isAdminProvisioned): int
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
        $config = PackedDataHelper::encodePackedData($this->defaultPermissionsConfig($isAdminProvisioned));
        $now = (new Date())->toSql();
        // created_by/modified_by are varchar(255) columns, mirroring FormModel::prepareTable().
        $userId = (string) (int) Factory::getApplication()->getIdentity()->id;

        $insertQuery = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__contentbuilderng_forms'))
            ->columns($this->db->quoteName([
                'type',
                'reference_id',
                'name',
                'title',
                'tag',
                'published',
                'create_articles',
                'config',
                'created',
                'created_by',
            ]))
            ->values(':cbType, :cbReferenceId, :cbName, :cbTitle, :cbTag, 1, 0, :cbConfig, :cbCreated, :cbCreatedBy')
            ->bind(':cbType', $type)
            ->bind(':cbReferenceId', $storageId, ParameterType::INTEGER)
            ->bind(':cbName', $name)
            ->bind(':cbTitle', $title)
            ->bind(':cbTag', $tag)
            ->bind(':cbConfig', $config)
            ->bind(':cbCreated', $now)
            ->bind(':cbCreatedBy', $userId);
        $this->db->setQuery($insertQuery);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * A frontend-auto-provisioned form (triggered anonymously or by a mere
     * authenticated visit, never reviewed by an admin) grants read access to
     * Guest only. An admin-provisioned form (built deliberately through the
     * Storage wizard, an authenticated core.manage action) instead mirrors
     * the checkboxes pre-checked for a brand-new form in the classic Form
     * screen (listaccess/view/new, with edit left unchecked) applied to every
     * non-Guest usergroup so it is immediately usable on the frontend. Guest
     * remains read-only in both provisioning paths.
     */
    private function defaultPermissionsConfig(bool $isAdminProvisioned): array
    {
        $guestGroupId = (int) Factory::getApplication()->get('guest_usergroup');

        if (!$isAdminProvisioned) {
            $permissions = $guestGroupId > 0
                ? [
                    $guestGroupId => [
                        'listaccess' => true,
                        'view' => true,
                        'new' => false,
                        'edit' => false,
                        'delete' => false,
                        'state' => false,
                        'publish' => false,
                        'api' => false,
                        'stats' => false,
                        'fullarticle' => false,
                        'language' => false,
                        'rating' => false,
                    ],
                ]
                : [];

            return ['permissions_fe' => $permissions];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__usergroups'));
        $this->db->setQuery($query);
        $groupIds = $this->db->loadColumn() ?: [];

        $permissions = [];
        foreach ($groupIds as $groupId) {
            $groupId = (int) $groupId;
            $permissions[$groupId] = [
                'listaccess' => true,
                'view' => true,
                'new' => $groupId !== $guestGroupId,
                'edit' => false,
            ];
        }

        return ['permissions_fe' => $permissions];
    }

    /**
     * Completes a form that was auto-created before an administrator opened
     * the wizard. Existing group settings are intentionally left untouched;
     * only groups missing from the frontend permission matrix receive the
     * normal new-form defaults.
     */
    private function ensureAdminProvisionedPermissions(int $formId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('config'))
            ->from($this->db->quoteName('#__contentbuilderng_forms'))
            ->where($this->db->quoteName('id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $config = PackedDataHelper::decodePackedData((string) $this->db->loadResult(), [], true);

        if (!is_array($config)) {
            $config = [];
        }

        $permissions = is_array($config['permissions_fe'] ?? null)
            ? $config['permissions_fe']
            : [];
        $defaults = $this->defaultPermissionsConfig(true)['permissions_fe'] ?? [];
        $changed = false;

        foreach ($defaults as $groupId => $groupPermissions) {
            if (array_key_exists($groupId, $permissions) || array_key_exists((string) $groupId, $permissions)) {
                continue;
            }

            $permissions[$groupId] = $groupPermissions;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $config['permissions_fe'] = $permissions;
        $updateQuery = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__contentbuilderng_forms'))
            ->set($this->db->quoteName('config') . ' = :config')
            ->where($this->db->quoteName('id') . ' = :formId')
            ->bind(':config', PackedDataHelper::encodePackedData($config))
            ->bind(':formId', $formId, ParameterType::INTEGER);
        $this->db->setQuery($updateQuery);
        $this->db->execute();
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

        $form = FormSourceFactory::getForm(self::TYPE, $storageId);

        if (!is_object($form)) {
            return;
        }

        $existingReferencesQuery = $this->db->getQuery(true)
            ->select($this->db->quoteName('reference_id'))
            ->from($this->db->quoteName('#__contentbuilderng_elements'))
            ->where($this->db->quoteName('form_id') . ' = ' . $formId);
        $this->db->setQuery($existingReferencesQuery);
        $existingReferences = array_map('strval', $this->db->loadColumn() ?: []);

        // Runtime provisioning is additive: a temporarily unpublished
        // Storage field must not delete its form element and lose customized
        // options before it is published again.
        $this->formSupportService->synchElements($formId, $form, false);

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

        if ($existingReferences !== []) {
            $elementsUpdate->where(
                $this->db->quoteName('reference_id') . ' NOT IN ('
                . implode(',', array_map([$this->db, 'quote'], $existingReferences)) . ')'
            );
        }

        $this->db->setQuery($elementsUpdate);
        $this->db->execute();

        $this->enableSearchForTextAndDateFields($formId, $storageId);

        if ($editableTemplate !== '' && $detailsTemplate !== '') {
            return;
        }

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

        $now = (new Date())->toSql();
        // modified_by is a varchar(255) column, mirroring FormModel::prepareTable().
        $userId = (string) (int) Factory::getApplication()->getIdentity()->id;
        $update->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :modifiedBy')
            ->bind(':modified', $now)
            ->bind(':modifiedBy', $userId);

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
