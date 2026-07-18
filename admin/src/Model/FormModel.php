<?php

/**
 * ContentBuilder Form Model.
 *
 * Handles CRUD and publish state for form in the admin interface.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */


namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Date\Date;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;

class FormModel extends AdminModel
{
    protected int $formId = 0;

    private array $_default_list_states = [];

    public function __construct(
        array $config,
        ?MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);
        $this->option = 'com_contentbuilderng';
        $this->_default_list_states = $this->buildDefaultListStates();
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getApp(): CMSApplication
    {
        $app = $this->getComponent()->getContainer()->get(CMSApplicationInterface::class);

        if (!$app instanceof CMSApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    private function getInput()
    {
        return $this->getApp()->getInput();
    }

    public function getDispatcher()
    {
        return $this->getApp()->getDispatcher();
    }

    private function getFormSupportService(): FormSupportService
    {
        return $this->getComponent()->getContainer()->get(FormSupportService::class);
    }

    private function getCurrentFormId(): int
    {
        return (int) $this->getState($this->getName() . '.id');
    }

    private function getSelectedElementIdsFromRequest(): array
    {
        $items = $this->getInput()->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);

        return array_values(array_filter($items, static fn(int $item): bool => $item > 0));
    }

    private function buildDefaultListStates(): array
    {
        $states = [];

        for ($index = 1; $index <= 10; $index++) {
            $states[] = [
                'id' => -1 * $index,
                'action' => '',
                'title' => Text::sprintf('COM_CONTENTBUILDERNG_LIST_STATE_DEFAULT_TITLE', $index),
                'color' => $index === 1 ? '60E309' : ($index === 2 ? 'FCFC00' : ($index === 3 ? 'FC0000' : 'FFFFFF')),
                'published' => $index <= 3 ? 1 : 0,
            ];
        }

        return $states;
    }

    private function normalizeListStateColor(mixed $value): string
    {
        $hex = strtoupper(ltrim(trim((string) $value), '#'));

        if (preg_match('/^[0-9A-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0]
                . $hex[1] . $hex[1]
                . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[0-9A-F]{6}$/', $hex)) {
            return 'FFFFFF';
        }

        return $hex;
    }

    private function saveElementListSettings(
        int $formId,
        array $labels,
        array $wordwrap,
        array $orderTypes,
        array $itemWrapper,
        array $order
    ): void {
        if ($formId <= 0) {
            return;
        }

        $elementIds = array_unique(array_merge(
            array_keys($labels),
            array_keys($wordwrap),
            array_keys($orderTypes),
            array_keys($itemWrapper),
            array_keys($order)
        ));

        if (empty($elementIds)) {
            return;
        }

        ArrayHelper::toInteger($wordwrap);
        $db = $this->getDatabase();

        foreach ($elementIds as $elementId) {
            $elementId = (int) $elementId;
            if ($elementId <= 0) {
                continue;
            }

            $label   = $labels[$elementId] ?? '';
            $wrap    = $wordwrap[$elementId] ?? 0;
            $wrap    = max(0, min(9999, (int) $wrap));
            $otype   = $orderTypes[$elementId] ?? '';
            $ord     = isset($order[$elementId]) ? (int) $order[$elementId] : 0;

            $set = [
                $db->quoteName('order_type') . ' = ' . $db->quote((string) $otype),
                $db->quoteName('label') . ' = ' . $db->quote((string) $label),
                $db->quoteName('wordwrap') . ' = ' . (int) $wrap,
                $db->quoteName('ordering') . ' = ' . (int) $ord,
            ];

            if (array_key_exists($elementId, $itemWrapper)) {
                $set[] = $db->quoteName('item_wrapper') . ' = ' . $db->quote(trim((string) $itemWrapper[$elementId]));
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($set)
                ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                ->where($db->quoteName('id') . ' = ' . (int) $elementId);
            $db->setQuery($query);
            $db->execute();
        }
    }

    private function formatSyncFieldList(array $labels, int $totalCount, int $maxPreview = 8): string
    {
        $clean = array();
        foreach ($labels as $label) {
            $value = trim(strip_tags((string) $label));
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        $clean = array_values(array_unique($clean));
        if (!$clean) {
            return (string) $totalCount;
        }

        $visible = array_slice($clean, 0, $maxPreview);
        $remaining = max(0, $totalCount - count($visible));
        $list = implode(', ', $visible);

        if ($remaining > 0) {
            $list .= ' +' . $remaining;
        }

        return $list;
    }

    private function enqueueSourceSchemaSyncWarning(array $syncReport): void
    {
        $added = (array) ($syncReport['added'] ?? array());
        $removed = (array) ($syncReport['removed'] ?? array());
        $addedCount = (int) ($syncReport['added_count'] ?? count($added));
        $removedCount = (int) ($syncReport['removed_count'] ?? count($removed));

        if ($addedCount < 1 && $removedCount < 1) {
            return;
        }

        $msg = Text::sprintf(
            'COM_CONTENTBUILDERNG_SOURCE_FIELDS_SYNC_CHANGED',
            $addedCount,
            $removedCount
        );

        if ($addedCount > 0) {
            $msg .= ' ' . Text::sprintf(
                'COM_CONTENTBUILDERNG_SOURCE_FIELDS_SYNC_ADDED',
                $this->formatSyncFieldList($added, $addedCount)
            );
        }

        if ($removedCount > 0) {
            $msg .= ' ' . Text::sprintf(
                'COM_CONTENTBUILDERNG_SOURCE_FIELDS_SYNC_REMOVED',
                $this->formatSyncFieldList($removed, $removedCount)
            );
        }

        $this->getApp()->enqueueMessage($msg, 'warning');
    }

    public function saveElementListSettingsFromRequest(int $formId): bool
    {
        if ($formId <= 0) {
            return true;
        }

        try {
            $input = $this->getInput();
            $jform = (array) $input->post->get('jform', [], 'array');
            $jformRaw = (array) $input->post->get('jform', [], 'raw');

            $this->saveElementListSettings(
                $formId,
                (array) ($jform['itemLabels'] ?? []),
                (array) ($jform['itemWordwrap'] ?? []),
                (array) ($jform['itemOrderTypes'] ?? []),
                (array) ($jformRaw['itemWrapper'] ?? []),
                (array) ($jform['order'] ?? [])
            );
        } catch (\Throwable $e) {
            Logger::exception($e);
            return false;
        }

        return true;
    }

    public function getForm($data = [], $loadData = true)
    {
        // Intelephense may not resolve inherited AdminModel::loadForm() in this workspace.
        $loadForm = 'loadForm';
        $form = $this->{$loadForm}(
            $this->option . '.form',
            'form',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        return $form ?: false;
    }

    #[\Override]
    protected function populateState(): void
    {
        // Déjà le parent.
        parent::populateState();

        // 2) ID depuis l'URL (standard Joomla en admin)
        /** @var CMSApplication $app */
        $app   = $this->getApp();
        $input = $app->getInput();
        $formId = $input->getInt('id', 0);

        // 3) Fallback si on arrive via POST (save/apply etc.)
        if (!$formId) {
            $jform = $input->post->get('jform', [], 'array');
            $formId = (int) ($jform['id'] ?? 0);
        }

        // 4) État standard Joomla pour un AdminModel
        $this->setState($this->getName() . '.id', $formId);
    }


    protected function loadFormData()
    {
        /** @var CMSApplication $app */
        $app = $this->getApp();
        $data = $app->getUserState('com_contentbuilderng.edit.form.data', []);

        return !empty($data) ? $data : (array) $this->getItem();
    }


    function setListEditable()
    {
        $formId = $this->getCurrentFormId();
        if ($formId <= 0) {
            return;
        }


        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('editable') . ' = 1')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListListInclude()
    {
        $formId = $this->getCurrentFormId();
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('list_include') . ' = 1')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListSearchInclude()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('search_include') . ' = 1')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListNotLinkable()
    {
        $formId = $this->getCurrentFormId();
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('linkable') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListNotEditable()
    {
        $formId = $this->getCurrentFormId();
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('editable') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListNoListInclude()
    {
        $formId = $this->getCurrentFormId();
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('list_include') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListNoSearchInclude()
    {
        $formId = $this->getCurrentFormId();
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = $this->getSelectedElementIdsFromRequest();
        if (count($items)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('search_include') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('id') . ' IN (' . implode(',', $items) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function getListStatesActionPlugins()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_listaction'))
            ->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);
        $res = $db->loadColumn();
        return $res;
    }

    function getThemePlugins()
    {
        $db = $this->getDatabase();
        $themes = [];

        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_themes'))
            ->where($db->quoteName('enabled') . ' = 1');

        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];

        foreach ($rows as $row) {
            $row = trim((string) $row);
            if ($row !== '') {
                $themes[] = $row;
            }
        }

        $themes = array_values(array_unique($themes));

        usort($themes, static function (string $a, string $b): int {
            $order = ['thoth' => 0, 'dark' => 1, 'blank' => 2, 'khepri' => 3];
            $rankA = $order[$a] ?? 99;
            $rankB = $order[$b] ?? 99;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return strcasecmp($a, $b);
        });

        return $themes;
    }

    function getVerificationPlugins()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_verify'))
            ->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);
        $res = $db->loadColumn();
        return $res;
    }


    /*
     * MAIN DETAILS AREA
     */

    #[\Override]
    public function getItem($formId = null)
    {
        if ($formId === null) {
            $formId = (int) $this->getState($this->getName() . '.id');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int)$formId);

        $db->setQuery($query);
        $data = $db->loadObject();

        if (!$data) {
            $data = new \stdClass();
            $data->id = 0;
            $data->type = null;
            $data->reference_id = null;
            $data->name = null;
            $data->tag = null;
            $data->details_template = null;
            $data->details_prepare = null;
            $data->intro_text = null;
            $data->title = null;
            $data->created = null;
            $data->modified = null;
            $data->metadata = false;
            $data->export_xls = true;
            $data->print_button = false;
            $data->created_by = null;
            $data->modified_by = null;
            $data->published = null;
            $data->display_in = null;
            $data->published_only = null;
            $data->show_id_column = true;
            $data->select_column = true;
            $data->edit_button = false;
            $data->new_button = false;
            $data->list_states = false;
            $data->config = null;
            $data->editable_prepare = null;
            $data->editable_template = null;
            $data->use_view_name_as_title = false;
            $data->list_states = $this->_default_list_states;
            $data->own_only = false;
            $data->own_only_fe = false;
            $data->list_state = false;
            $data->list_publish = false;
            $data->initial_sort_order = -1;
            $data->initial_sort_order2 = -1;
            $data->initial_sort_order3 = -1;
            $data->initial_order_dir = 'desc';
            $data->default_section = 0;
            $data->default_category = 0;
            $data->create_articles = 0;
            $data->title_field = 0;
            $data->delete_articles = 1;
            $data->edit_by_type = 0;
            $data->email_notifications = 1;
            $data->email_update_notifications = 0;
            $data->limited_article_options = 1;
            $data->limited_article_options_fe = 1;
            $data->upload_directory = JPATH_SITE . '/media/com_contentbuilderng/upload';
            $data->protect_upload_directory = 1;
            $data->limit_add = 0;
            $data->limit_edit = 0;
            $data->verification_required_view = 0;
            $data->verification_days_view = 0;
            $data->verification_required_new = 0;
            $data->verification_days_new = 0;
            $data->verification_required_edit = 0;
            $data->verification_days_edit = 0;
            $data->verification_url_new = '';
            $data->verification_url_view = '';
            $data->verification_url_edit = '';
            $data->default_lang_code = '*';
            $data->default_lang_code_ignore = 0;
            $data->show_all_languages_fe = 1;
            $data->list_language = 0;
            $data->default_publish_up_days = 0;
            $data->default_publish_down_days = 0;
            $data->default_access = 0;
            $data->default_featured = 0;
            $data->list_article = 0;
            $data->list_author = 0;
            $data->list_last_modification = 0;
            $data->list_rating = 0;
            $data->cb_show_author = 1;
            $data->cb_show_top_bar = 1;
            $data->cb_show_bottom_bar = 0;
            $data->cb_show_details_top_bar = 1;
            $data->cb_show_details_bottom_bar = 0;
            $data->show_back_button = 1;
            $data->show_title_breadcrumb = 1;
            $data->cb_filter_in_title = 0;
            $data->cb_prefix_in_title = 0;
            $data->debug_mode = 0;
            $data->debug_show_bf_id = 0;
            $data->debug_enable_logs = 0;
            $data->debug_show_request_logs = 0;
            $data->debug_show_permissions = 0;
            $data->debug_show_filters = 0;
            $data->debug_show_cb_id = 0;
            $data->email_template = '';
            $data->email_subject = '';
            $data->email_alternative_from = '';
            $data->email_alternative_fromname = '';
            $data->email_recipients = '';
            $data->email_recipients_attach_uploads = '';
            $data->email_html = '';

            $data->email_admin_template = '';
            $data->email_admin_subject = '';
            $data->email_admin_alternative_from = '';
            $data->email_admin_alternative_fromname = '';
            $data->email_admin_recipients = '';
            $data->email_admin_recipients_attach_uploads = '';
            $data->email_admin_html = '';

            $data->act_as_registration = 0;
            $data->registration_username_field = '';
            $data->registration_password_field = '';
            $data->registration_password_repeat_field = '';
            $data->registration_email_field = '';
            $data->registration_email_repeat_field = '';
            $data->registration_name_field = '';

            $data->auto_publish = 0;

            $data->force_login = 0;
            $data->force_url = '';

            $data->registration_bypass_plugin = '';
            $data->registration_bypass_plugin_params = '';
            $data->registration_bypass_verification_name = '';
            $data->registration_bypass_verify_view = '';

            $data->theme_plugin = 'thoth';

            $data->rating_slots = 5;

            $data->rand_date_update = null;

            $data->rand_update = '86400';

            $data->article_record_impact_publish = 0;
            $data->article_record_impact_language = 0;

            $data->allow_external_filter = 0;

            $data->show_filter = 1;

            $data->show_records_per_page = 1;

            $data->button_bar_sticky = 0;

            $data->list_header_sticky = 0;

            $data->show_preview_link = 0;

            $data->initial_list_limit = 50;

            $data->save_button_title = '';

            $data->apply_button_title = '';

            $data->filter_exact_match = 1;

            $data->ordering = 0;
        }

        if (!isset($data->new_button)) {
            $data->new_button = 0;
        }

        if (!isset($data->button_bar_sticky)) {
            $data->button_bar_sticky = 0;
        }

        if (!isset($data->list_header_sticky)) {
            $data->list_header_sticky = 0;
        }

        if (!isset($data->show_preview_link)) {
            $data->show_preview_link = 0;
        }

        if (!isset($data->cb_show_author)) {
            $data->cb_show_author = 1;
        }

        if (!isset($data->cb_show_top_bar)) {
            $data->cb_show_top_bar = 1;
        }

        if (!isset($data->cb_show_bottom_bar)) {
            $data->cb_show_bottom_bar = 0;
        }

        if (!isset($data->cb_show_details_top_bar)) {
            $data->cb_show_details_top_bar = 1;
        }

        if (!isset($data->cb_show_details_bottom_bar)) {
            $data->cb_show_details_bottom_bar = 0;
        }

        if (!isset($data->show_back_button)) {
            $data->show_back_button = 1;
        }

        if (!isset($data->show_title_breadcrumb)) {
            $data->show_title_breadcrumb = 1;
        }

        if (!isset($data->cb_filter_in_title)) {
            $data->cb_filter_in_title = 0;
        }

        if (!isset($data->cb_prefix_in_title)) {
            $data->cb_prefix_in_title = 0;
        }

        if (!isset($data->debug_mode)) {
            $data->debug_mode = 0;
        }

        if (!isset($data->debug_show_bf_id)) {
            $data->debug_show_bf_id = 0;
        }

        foreach (['debug_enable_logs', 'debug_show_request_logs', 'debug_show_permissions', 'debug_show_filters', 'debug_show_cb_id'] as $debugField) {
            if (!isset($data->$debugField)) {
                $data->$debugField = 0;
            }
        }

        $data->forms = array();
        $formSupportService = $this->getFormSupportService();
        $pathService = new PathService();

        $data->types = $formSupportService->getTypes();

        if ($data->type) {
            $data->forms = $formSupportService->getForms($data->type);
        }

        $data->form = null;
        if ($data->type && $data->reference_id) {
            $data->form = FormSourceFactory::getForm((string) $data->type, (string) $data->reference_id);
            if (!$data->form || !$data->form->exists) {
                if ((string) $data->type === 'com_breezingformsng') {
                    $this->getApp()->enqueueMessage(
                        Text::sprintf('COM_CONTENTBUILDERNG_BREEZINGFORMS_SOURCE_NOT_FOUND', (int) $data->reference_id),
                        'warning'
                    );
                } else {
                    $this->getApp()->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'warning');
                }

                // Keep the form editable and let admin choose a new source.
                $data->reference_id = 0;
                $data->form = null;
                $data->type_name = '';
            } else {
                if (isset($data->form->properties) && isset($data->form->properties->name)) {
                    $data->type_name = trim($data->form->properties->name);
                } else {
                    $data->type_name = '';
                }
                $data->title = trim($data->form->getPageTitle());

                // En charge de la sauvegarde de la partie Element
                if (is_object($data->form)) {
                    $syncReport = $formSupportService->synchElements($data->id, $data->form);
                    $elements_table = $this->getTable('Elementoptions');
                    $elements_table->reorder('form_id=' . $data->id);
                    $this->enqueueSourceSchemaSyncWarning($syncReport);
                }
            }
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__contentbuilderng_list_states'))
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->order('id ASC')
        );

        $list_states = $db->loadAssocList();

        if (count($list_states)) {
            $data->list_states = $list_states;
        } else {
            $data->list_states = $this->_default_list_states;
        }

        $data->language_codes = $formSupportService->getLanguageCodes();

        $data->sectioncategories = $this->getOptions();
        $data->accesslevels = array();

        return $data;
    }

    private function getOptions()
    {
        // Initialise variables.
        $options = array();

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('a.id AS value, a.title AS text, a.level');
        $query->from('#__categories AS a');
        $query->join('LEFT', '`#__categories` AS b ON a.lft > b.lft AND a.rgt < b.rgt');

        // Article creation requires a real com_content category, not the system root.
        $query->where('a.extension = ' . $db->quote('com_content'));

        $query->where('a.published IN (0,1)');
        $query->group('a.id');
        $query->order('a.lft ASC');

        // Get the options.
        $db->setQuery($query);

        try {
            $options = $db->loadObjectList();
        } catch (\Exception $e) {
            Logger::exception($e);
            // Check for a database error.
            $this->getApp()->enqueueMessage($e->getMessage(), 'error');
        }

        // Pad the option text with spaces using depth level as a multiplier.
        for ($i = 0, $n = count($options); $i < $n; $i++) {
            // Translate ROOT
            if ($options[$i]->level == 0) {
                $options[$i]->text = Text::_('JGLOBAL_ROOT_PARENT');
            }

            $options[$i]->text = str_repeat('- ', $options[$i]->level) . $options[$i]->text;
        }

        if (isset($row) && !isset($options[0])) {
            if ($row->parent_id == '1') {
                $parent = new \stdClass();
                $parent->text = Text::_('JGLOBAL_ROOT_PARENT');
                array_unshift($options, $parent);
            }
        }

        return $options;
    }


    // Nettoie les données avant sauvegarde.
    protected function prepareTable($table): void
    {
        parent::prepareTable($table);

        $now  = (new Date())->toSql();
        $user = $this->getApp()->getIdentity();

        $table->name  = trim((string) $table->name);
        $table->title = trim((string) $table->title);
        $table->tag   = trim((string) ($table->tag ?? ''));

        if (empty($table->id)) {
            // Création
            if (empty($table->created)) {
                $table->created = $now;
            }
            if (empty($table->created_by)) {
                $table->created_by = (int) $user->id;
            }

            // En création, tu peux laisser modified vide (standard) ou l'aligner sur created
            // $table->modified = null;
            // $table->modified_by = 0;
        } else {
            // Modification
            $table->modified = $now;
            $table->modified_by = (int) $user->id;
        }
    }

    #[\Override]
    public function save($data): bool
    {
        $app   = $this->getApp();
        $input = $app->getInput();
        $db    = $this->getDatabase();
        $formSupportService = $this->getFormSupportService();
        $pathService = new PathService();

        // 1) Récupération standard + RAW/HTML (nécessaire pour tes éditeurs)
        $jform     = (array) $input->post->get('jform', [], 'array');
        $jformRaw  = (array) $input->post->getRaw('jform');
        $jformHtml = (array) $input->post->getHtml('jform');

        // ID (standard admin)
        $id = (int) ($input->getInt('id', 0) ?: (int) ($jform['id'] ?? 0));
        $jform['id'] = $id;

        Logger::info('Form save flags', [
            'task' => $input->getCmd('task', ''),
            'create_sample' => $jform['create_sample'] ?? null,
            'create_sample_raw' => $jformRaw['create_sample'] ?? null,
            'theme_plugin' => $jform['theme_plugin'] ?? null,
        ]);

        // 2) Override champs sensibles : on force RAW pour les templates/scripts
        $rawFields = [
            'intro_text',
            'details_template',
            'editable_template',
            'details_prepare',
            'editable_prepare',
            'email_admin_template',
            'email_template',
        ];

        foreach ($rawFields as $f) {
            if (array_key_exists($f, $jformRaw)) {
                $jform[$f] = $jformRaw[$f];
            }
        }

        // 3) Normalisation des checkboxes / flags (standardiser en 0/1)
        $boolFields = [
            'protect_upload_directory',
            'create_articles',
            'verification_required_view',
            'verification_required_new',
            'verification_required_edit',
            'show_all_languages_fe',
            'default_lang_code_ignore',
            'edit_by_type',
            'act_as_registration',
            'email_notifications',
            'email_update_notifications',
            'limited_article_options',
            'limited_article_options_fe',
            'own_only',
            'own_only_fe',
            'email_html',
            'email_admin_html',
            'show_filter',
            'show_records_per_page',
            'button_bar_sticky',
            'list_header_sticky',
            'show_preview_link',
            'cb_show_author',
            'cb_show_top_bar',
            'cb_show_bottom_bar',
            'cb_show_details_top_bar',
            'cb_show_details_bottom_bar',
            'show_back_button',
            'show_title_breadcrumb',
            'cb_filter_in_title',
            'cb_prefix_in_title',
            'debug_mode',
            'debug_show_bf_id',
            'debug_enable_logs',
            'debug_show_request_logs',
            'debug_show_permissions',
            'debug_show_filters',
            'debug_show_cb_id',
            'metadata',
            'export_xls',
            'print_button',
            'auto_publish',
            'force_login',
            'protect_upload_directory',
            'allow_external_filter',
            'show_id_column',
            'select_column',
            'edit_button',
            'new_button',
            'list_state',
            'list_publish',
            'list_rating',
            'list_language',
            'list_article',
            'list_author',
            'list_last_modification',
        ];

        foreach ($boolFields as $bf) {
            if (array_key_exists($bf, $jform)) {
                $jform[$bf] = !empty($jform[$bf]) ? 1 : 0;
            }
        }

        if (!array_key_exists('initial_list_limit', $jform) || (string) $jform['initial_list_limit'] === '') {
            $jform['initial_list_limit'] = 50;
        } else {
            $jform['initial_list_limit'] = max(1, (int) $jform['initial_list_limit']);
        }

        // Tag défaut
        if (empty($jform['tag'])) {
            $jform['tag'] = 'default';
        }

        $selectedThemePlugin = (string) ($jform['theme_plugin'] ?? '');
        if ($selectedThemePlugin !== '') {
            $availableThemePlugins = $this->getThemePlugins();
            if (!in_array($selectedThemePlugin, $availableThemePlugins, true)) {
                $jform['theme_plugin'] = 'thoth';
            }
        }

        // 4) Capture list_states puis on l'enlève du bind principal (car stocké ailleurs)
        $list_states = (array) ($jform['list_states'] ?? []);
        unset($jform['list_states']);

        // 5) Upload directory: keep current folder, do not fallback to another path.
        if (!isset($jform['upload_directory']) || $jform['upload_directory'] === '') {
            $jform['upload_directory'] = 'media/com_contentbuilderng/upload';
        }

        $upl_ex = explode('|', (string) $jform['upload_directory']);
        $basePath = trim((string) $upl_ex[0]);
        $tokens   = isset($upl_ex[1]) ? '|' . $upl_ex[1] : '';

        $basePath = str_replace('\\', '/', $basePath);
        $basePath = str_ireplace(
            ['{CBSite}/media/contentbuilderng', '{cbsite}/media/contentbuilderng'],
            ['{CBSite}/media/com_contentbuilderng', '{cbsite}/media/com_contentbuilderng'],
            $basePath
        );
        if (stripos($basePath, '/media/contentbuilderng') === 0) {
            $basePath = 'media/com_contentbuilderng' . substr($basePath, strlen('/media/contentbuilderng'));
        } elseif (stripos($basePath, 'media/contentbuilderng') === 0) {
            $basePath = 'media/com_contentbuilderng' . substr($basePath, strlen('media/contentbuilderng'));
        }

        $is_relative = (stripos($basePath, '{cbsite}') === 0);
        $tmp_upload_directory = $basePath;

        if ($is_relative) {
            $resolved = str_ireplace(['{CBSite}', '{cbsite}'], JPATH_SITE, $basePath);
        } else {
            $siteRoot = rtrim(str_replace('\\', '/', JPATH_SITE), '/');
            $isWindowsAbsolute = (bool) preg_match('#^[A-Za-z]:/#', $basePath);
            $isUnixAbsolute = !$isWindowsAbsolute && strpos($basePath, '/') === 0;

            if ($isUnixAbsolute) {
                // Treat web-style absolute paths like "/media/..." as site-relative.
                $basePath = ltrim($basePath, '/');
                $tmp_upload_directory = $basePath;
            }

            $resolved = ($isWindowsAbsolute || stripos($basePath, $siteRoot . '/') === 0 || strcasecmp($basePath, $siteRoot) === 0)
                ? $basePath
                : $siteRoot . '/' . ltrim($basePath, '/');
        }

        $resolved = $pathService->makeSafeFolder($resolved);

        $protect = !empty($jform['protect_upload_directory']);

        // Create the configured folder when missing (no fallback path switching).
        if (!is_dir($resolved)) {
            if (!Folder::create($resolved)) {
                $app->enqueueMessage(Text::sprintf('COM_CONTENTBUILDERNG_UPLOAD_FOLDER_CREATE_FAILED', $resolved), 'error');
            } else {
                File::write($resolved . '/index.html', '');
                if ($protect) {
                    File::write($resolved . '/.htaccess', 'deny from all');
                }
            }
        }

        // Applique protection (safe folder) si besoin
        if ($protect) {
            $safe = $pathService->makeSafeFolder($resolved);

            if (is_dir($safe)) {
                if (!file_exists($safe . '/index.html')) {
                    File::write($safe . '/index.html', '');
                }
                if (!file_exists($safe . '/.htaccess')) {
                    File::write($safe . '/.htaccess', 'deny from all');
                }
            }
        } else {
            $safe = $pathService->makeSafeFolder($resolved);
            if (file_exists($safe . '/.htaccess')) {
                File::delete($safe . '/.htaccess');
            }
        }

        // On restaure le format upload_directory avec tokens (comme avant)
        $jform['upload_directory'] = $tmp_upload_directory . $tokens;

        // 6) Permissions/config : on reconstruit proprement (sans 200 if)
        $config = [
            'permissions'    => [],
            'permissions_fe' => [],
            'own'            => [],
            'own_fe'         => [],
        ];

        // own / own_fe (valeurs bool)
        $ownKeys = [
            'view',
            'edit',
            'delete',
            'state',
            'publish',
            'fullarticle',
            'listaccess',
            'new',
            'language',
            'rating',
            'api'
        ];
        $permissionKeys = [
            ...$ownKeys,
            'stats',
        ];
        $own    = (array) ($jform['own'] ?? []);
        $own_fe = (array) ($jform['own_fe'] ?? []);

        foreach ($ownKeys as $k) {
            $config['own'][$k]    = !empty($own[$k]) ? true : false;
            $config['own_fe'][$k] = !empty($own_fe[$k]) ? true : false;
        }

        // permissions / permissions_fe par usergroup (structure: perms[group][action]=1)
        $perms    = (array) ($jform['perms'] ?? []);
        $perms_fe = (array) ($jform['perms_fe'] ?? []);

        // Liste des groupes (tu l’utilises déjà)
        $q = $db->getQuery(true)
            ->select("node.id AS value")
            ->from($db->quoteName('#__usergroups') . ' AS ' . $db->quoteName('node'));
        $db->setQuery($q);
        $groupIds = $db->loadColumn() ?: [];

        foreach ($groupIds as $gid) {
            $gid = (int) $gid;

            $config['permissions'][$gid] = [];
            $config['permissions_fe'][$gid] = [];

            foreach ($permissionKeys as $k) {
                $config['permissions'][$gid][$k] =
                    !empty($perms[$gid][$k]) ? true : false;

                $config['permissions_fe'][$gid][$k] =
                    !empty($perms_fe[$gid][$k]) ? true : false;
            }
        }

        // Nettoyage des champs temporaires (on ne les stocke pas en colonnes)
        unset($jform['perms'], $jform['perms_fe'], $jform['own'], $jform['own_fe']);

        $formObj = null;
        if (!empty($jform['type']) && !empty($jform['reference_id'])) {
            $formObj = FormSourceFactory::getForm((string) $jform['type'], (string) $jform['reference_id']);
        }

        $createSample = !empty($jform['create_sample']);
        if ($createSample) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'warning');
            }
            $sample = $formSupportService->createDetailsSample($id, $formObj, $jform['theme_plugin']);
            Logger::info('Details sample requested', [
                'form_id' => $id,
                'theme_plugin' => $jform['theme_plugin'] ?? null,
                'sample_length' => is_string($sample) ? strlen($sample) : null,
            ]);
            if ($sample === '' || $sample === null) {
                $app->enqueueMessage(
                    Text::sprintf(
                        'COM_CONTENTBUILDERNG_DETAILS_SAMPLE_EMPTY',
                        (string) ($jform['theme_plugin'] ?? Text::_('COM_CONTENTBUILDERNG_NONE'))
                    ),
                    'warning'
                );
            }
            $jform['details_template'] = (string) $sample;
        }

        $createEditableSample = !empty($jform['create_editable_sample']);
        if ($createEditableSample) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'warning');
            }
            $jform['editable_template'] = $formSupportService->createEditableSample($id, $formObj, $jform['theme_plugin']);
        }

        $emailAdminHtml = !empty($jform['email_admin_html']);
        $emailAdminTemplate = !empty($jform['email_admin_create_sample']);
        if ($emailAdminTemplate) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'warning');
            }
            $jform['email_admin_template'] = $formSupportService->createEmailSample($id, $formObj, $emailAdminHtml);
        }

        $emailCreateSample = !empty($jform['email_create_sample']);
        if ($emailCreateSample) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 'warning');
            }
            $jform['email_template'] = $formSupportService->createEmailSample($id, $formObj, $this->getInput()->getBool('email_html', false));
        }

        $isBreezingFormsType = in_array(
            (string) ($jform['type'] ?? ''),
            ['com_breezingformsng'],
            true
        );
        $editByTypeEnabled = !empty($jform['edit_by_type']);

        if ($isBreezingFormsType && $editByTypeEnabled) {
            $typeName = trim((string) ($jform['type_name'] ?? ''));

            if ($typeName === '' && is_object($formObj) && isset($formObj->properties->name)) {
                $typeName = trim((string) $formObj->properties->name);
                $jform['type_name'] = $typeName;
            }

            if ($typeName !== '') {
                $jform['editable_template'] = '{BreezingForms: ' . $typeName . '}';
            }
        }

        if (
            !$editByTypeEnabled
            && isset($jform['editable_template'])
            && preg_match('/^\s*\{BreezingForms\s*:[^}]+\}\s*$/i', (string) $jform['editable_template'])
        ) {
            $jform['editable_template'] = '';
        }

        // Config
        $jform['config'] = PackedDataHelper::encodePackedData($config);

        // Last_update.
        $jform['last_update'] = (new Date())->toSql();

        // 7) Ajustements divers (si nécessaire)
        // - default_category depuis sectioncategories (comme avant)
        if (isset($jform['sectioncategories'])) {
            $jform['default_category'] = (int) $jform['sectioncategories'];
            unset($jform['sectioncategories']);
        }

        if (!empty($jform['create_articles'])) {
            $defaultCategory = (int) ($jform['default_category'] ?? 0);
            $categoryQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = ' . $defaultCategory)
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('published') . ' IN (0, 1)');
            $db->setQuery($categoryQuery);

            if (!$db->loadResult()) {
                $this->setError(Text::_('COM_CONTENTBUILDERNG_CREATE_ARTICLES_CATEGORY_REQUIRED'));
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_CREATE_ARTICLES_CATEGORY_REQUIRED'), 'error');
                return false;
            }
        }

        // 8) Sauvegarde STANDARD Joomla (bind/check/store + prepareTable() + events)
        // IMPORTANT: parent::save() prend un array "jform-like"
        $ok = parent::save($jform);
        if (!$ok) {
            return false;
        }

        // 9) POST-SAVE : on récupère l'ID officiel
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId < 1) {
            // ne devrait pas arriver, mais on sécurise
            return false;
        }

        $detailsOptionColumns = [
            'cb_show_details_top_bar',
            'cb_show_details_bottom_bar',
            'create_articles',
            'delete_articles',
            'title_field',
            'default_category',
            'default_access',
            'default_featured',
            'default_lang_code',
            'default_lang_code_ignore',
            'default_publish_up_days',
            'default_publish_down_days',
            'article_record_impact_language',
            'article_record_impact_publish',
            'auto_publish',
        ];
        $detailsOptionUpdates = [];

        foreach ($detailsOptionColumns as $column) {
            if (array_key_exists($column, $jform)) {
                $detailsOptionUpdates[] = $db->quoteName($column) . ' = ' . $db->quote((string) $jform[$column]);
            }
        }

        if ($detailsOptionUpdates) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_forms'))
                ->set($detailsOptionUpdates)
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($query);
            $db->execute();
        }

        if (
            (string) ($jform['type'] ?? '') === 'com_breezingformsng'
            && (int) ($jform['reference_id'] ?? 0) < 1
        ) {
            $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_BREEZINGFORMS_SOURCE_SELECT_REQUIRED'), 'notice');
        }

        // 10) Mettre à jour/insérer list_states (même logique que ton code)
        if (!empty($list_states)) {
            foreach ($list_states as $state_id => $item) {
                $sid = (int) $state_id;
                if ($sid > 0) {
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__contentbuilderng_list_states'))
                        ->set($db->quoteName('published') . ' = ' . (isset($item['published']) && $item['published'] ? 1 : 0))
                        ->set($db->quoteName('title') . ' = ' . $db->quote(stripslashes(strip_tags((string) ($item['title'] ?? '')))))
                        ->set($db->quoteName('color') . ' = ' . $db->quote($this->normalizeListStateColor($item['color'] ?? 'FFFFFF')))
                        ->set($db->quoteName('action') . ' = ' . $db->quote((string) ($item['action'] ?? '')))
                        ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                        ->where($db->quoteName('id') . ' = ' . (int) $sid);
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        }

        // Fallback: si pas assez d'états, on complète
        $query = $db->getQuery(true)
            ->select('COUNT(' . $db->quoteName('id') . ')')
            ->from($db->quoteName('#__contentbuilderng_list_states'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);
        $existingCount = (int) $db->loadResult();

        $defaultCount = count($this->_default_list_states);
        if ($existingCount < 1) {
            // rien du tout -> on insert tout
            for ($i = 0; $i < $defaultCount; $i++) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__contentbuilderng_list_states'))
                    ->columns($db->quoteName(['form_id', 'title', 'color', 'action']))
                    ->values(
                        implode(', ', [
                            (int) $formId,
                            $db->quote('State'),
                            $db->quote('FFFFFF'),
                            $db->quote(''),
                        ])
                    );
                $db->setQuery($query);
                $db->execute();
            }
        } elseif ($existingCount < $defaultCount) {
            // on complète le delta
            $add = $defaultCount - $existingCount;
            for ($i = 0; $i < $add; $i++) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__contentbuilderng_list_states'))
                    ->columns($db->quoteName(['form_id', 'title', 'color', 'action']))
                    ->values(
                        implode(', ', [
                            (int) $formId,
                            $db->quote('State'),
                            $db->quote('FFFFFF'),
                            $db->quote(''),
                        ])
                    );
                $db->setQuery($query);
                $db->execute();
            }
        }

        // 11) Reorder de la table forms (si tu le faisais)
        try {
            $row = $this->getTable('Form', '');
            $row->reorder();
        } catch (\Throwable $e) {
            // non bloquant
        }

        // 12) Update elements (labels/order/wrappers/wordwrap)
        $this->saveElementListSettings(
            $formId,
            (array) ($jform['itemLabels'] ?? []),
            (array) ($jform['itemWordwrap'] ?? []),
            (array) ($jform['itemOrderTypes'] ?? []),
            (array) ($jformRaw['itemWrapper'] ?? []),
            (array) ($jform['order'] ?? [])
        );

        // 13) Mettre à jour l'état du modèle / input (utile pour save2new/apply)
        $this->setState($this->getName() . '.id', $formId);
        $input->set('id', $formId);

        $jform['id'] = $formId;
        $input->post->set('jform', $jform);

        return true;
    }


    #[\Override]
    public function delete(&$pks)
    {
        if (empty($pks)) {
            throw new \RuntimeException(
                Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED')
            );
        }

        $pks = array_filter(array_map('intval', $pks));

        return $this->deleteByIds($pks);
    }

    private function deleteByIds(array $cids): bool
    {
        $row = $this->getTable('Form', '');
        $db = $this->getDatabase();

        foreach ($cids as $cid) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('article.article_id'))
                ->from($db->quoteName('#__contentbuilderng_articles', 'article'))
                ->join(
                    'INNER',
                    $db->quoteName('#__contentbuilderng_forms', 'form')
                    . ' ON ' . $db->quoteName('form.id') . ' = ' . $db->quoteName('article.form_id')
                )
                ->where($db->quoteName('form.delete_articles') . ' > 0')
                ->where($db->quoteName('article.form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $articles = $db->loadColumn();
            if (count($articles)) {
                $article_items = array();
                foreach ($articles as $article) {
                    $article_items[] = $db->quote('com_content.article.' . $article);
                    $table = new \Joomla\CMS\Table\Content($db);

                    // Trigger the onContentBeforeDelete event.
                    if ($table->load($article)) {
                        $dispatcher = $this->getDispatcher();
                        $eventObj = new \Joomla\CMS\Event\Model\BeforeDeleteEvent('onContentBeforeDelete', [
                            'context' => 'com_content.article',
                            'subject' => $table,
                        ]);
                        $dispatcher->dispatch('onContentBeforeDelete', $eventObj);
                    }
                    $query = $db->getQuery(true)
                        ->delete($db->quoteName('#__content'))
                        ->where($db->quoteName('id') . ' = ' . (int) $article);
                    $db->setQuery($query);
                    $db->execute();

                    // Trigger the onContentAfterDelete event.
                    $table->reset();
                    $dispatcher = $this->getDispatcher();
                    $eventObj = new \Joomla\CMS\Event\Model\AfterDeleteEvent('onContentAfterDelete', [
                        'context' => 'com_content.article',
                        'subject' => $table,
                    ]);
                    $dispatcher->dispatch('onContentAfterDelete', $eventObj);
                }
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__assets'))
                    ->where($db->quoteName('name') . ' IN (' . implode(',', $article_items) . ')');
                $db->setQuery($query);
                $db->execute();
            }


            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_list_states'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_list_records'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_resource_access'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_users'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_registered_users'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $cid);
            $db->setQuery($query);
            $db->execute();

            $this->getTable('Elementoptions')->reorder('form_id = ' . $cid);

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__menu'))
                ->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=' . (int) $cid));
            $db->setQuery($query);
            $db->execute();
            $query = $db->getQuery(true)
                ->select('COUNT(' . $db->quoteName('id') . ')')
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=%'));
            $db->setQuery($query);
            $amount = $db->loadResult();
            if (!$amount) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__menu'))
                    ->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng&viewcontainer=true'));
                $db->setQuery($query);
                $db->execute();
            }

            if (!$row->delete($cid)) {
                return false;
            }
        }

        $row->reorder();

        // article deletion if required
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__contentbuilderng_forms'));
        $db->setQuery($query);
        $references = $db->loadColumn();

        $cnt = count($references);
        if ($cnt) {
            $new_items = array();
            for ($i = 0; $i < $cnt; $i++) {
                $new_items[] = $db->quote($references[$i]);
            }
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_articles'))
                ->where($db->quoteName('form_id') . ' NOT IN (' . implode(',', $new_items) . ')');
            $db->setQuery($query);
            $db->execute();
        } else {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_articles'));
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }

    public function move(int $direction): bool
    {
        $pk = (int) $this->getState($this->getName() . '.id');

        $row = $this->getTable('Form', '');

        if (!$row->load($pk)) {
            return false;
        }

        if (!$row->move((int) $direction)) {
            return false;
        }

        return true;
    }



    public function copy(array $pks): bool
    {
        if (empty($pks)) {
            throw new \RuntimeException(
                Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED')
            );
        }

        $pks = array_filter(array_map('intval', $pks));

        return $this->copyByIds($pks);
    }

    private function copyByIds(array $cids): bool
    {
        $cids = array_values((array) $cids);
        ArrayHelper::toInteger($cids);

        if (!count($cids))
            return false;

        $db = $this->getDatabase();
        $table = $this->getTable('Form', '');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $cids) . ')');
        $db->setQuery($query);
        $result = $db->loadObjectList();

        foreach ($result as $obj) {
            $origId = $obj->id;
            unset($obj->id);

            $obj->name = Text::sprintf('COM_CONTENTBUILDERNG_COPY_OF', $obj->name);
            $obj->published = 0;

            // $obj->created = (new Date())->toSql();
            $obj->modified = (new Date())->toSql();
            $obj->modified_by = $this->getApp()->getIdentity()->id;

            $db->insertObject('#__contentbuilderng_forms', $obj);
            $insertId = $db->insertid();

            // Elements
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $origId);
            $db->setQuery($query);
            $elements = $db->loadObjectList();
            foreach ($elements as $element) {
                unset($element->id);
                $element->form_id = $insertId;
                $db->insertObject('#__contentbuilderng_elements', $element);
            }

            // list states
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__contentbuilderng_list_states'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $origId);
            $db->setQuery($query);
            $elements = $db->loadObjectList();
            foreach ($elements as $element) {
                unset($element->id);
                $element->form_id = $insertId;
                $db->insertObject('#__contentbuilderng_list_states', $element);
            }
            // XDA-Gil fix 'Copy of Form' in Component Menu in Backen CB View
            // MenuService::createBackendMenuItem($insertId, $obj->name, true);
        }

        $table->reorder();

        return true;
    }
}
