<?php

/**
 * ContentBuilder Form Model.
 *
 * Handles CRUD and publish state for form in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright (C) 2011–2026 by XDA+GIL
 * @license     GNU/GPL v2 or later
 * @link        https://breezingforms.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */


namespace CB\Component\Contentbuilder_ng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderLegacyHelper;
use CB\Component\Contentbuilder_ng\Administrator\Helper\Logger;
use CB\Component\Contentbuilder_ng\Administrator\Helper\PackedDataHelper;

class FormModel extends AdminModel
{
    protected int $formId = 0;

    private $_default_list_states = array(
        array('id' => -1, 'action' => '', 'title' => 'State 1', 'color' => '60E309', 'published' => 1),
        array('id' => -2, 'action' => '', 'title' => 'State 2', 'color' => 'FCFC00', 'published' => 1),
        array('id' => -3, 'action' => '', 'title' => 'State 3', 'color' => 'FC0000', 'published' => 1),
        array('id' => -4, 'action' => '', 'title' => 'State 4', 'color' => 'FFFFFF', 'published' => 0),
        array('id' => -5, 'action' => '', 'title' => 'State 5', 'color' => 'FFFFFF', 'published' => 0),
        array('id' => -6, 'action' => '', 'title' => 'State 6', 'color' => 'FFFFFF', 'published' => 0),
        array('id' => -7, 'action' => '', 'title' => 'State 7', 'color' => 'FFFFFF', 'published' => 0),
        array('id' => -8, 'action' => '', 'title' => 'State 8', 'color' => 'FFFFFF', 'published' => 0),
        array('id' => -9, 'action' => '', 'title' => 'State 9', 'color' => 'FFFFFF', 'published' => 0),
        array('id' => -10, 'action' => '', 'title' => 'State 10', 'color' => 'FFFFFF', 'published' => 0)
    );

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);
        $this->option = 'com_contentbuilder_ng';
    }

    private function normalizeListStateColor($value): string
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

    private function ensureListDisplayColumns(): void
    {
        $db = $this->getDatabase();
        $tableName = $db->getPrefix() . 'contentbuilder_ng_forms';

        try {
            $columns = $db->getTableColumns($tableName, true);
        } catch (\Throwable $e) {
            Logger::warning('Could not inspect form table columns', ['error' => $e->getMessage()]);
            return;
        }

        $knownColumns = [];
        foreach ((array) $columns as $columnName => $_type) {
            $knownColumns[strtolower((string) $columnName)] = true;
        }

        $requiredColumns = [
            'button_bar_sticky' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'show_preview_link' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];

        foreach ($requiredColumns as $columnName => $definition) {
            if (isset($knownColumns[$columnName])) {
                continue;
            }

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__contentbuilder_ng_forms')
                    . ' ADD ' . $db->quoteName($columnName) . ' ' . $definition
                );
                $db->execute();
            } catch (\Throwable $e) {
                Logger::warning('Could not add missing form table column', [
                    'column' => $columnName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
            $wrapper = $itemWrapper[$elementId] ?? '';
            $ord     = isset($order[$elementId]) ? (int) $order[$elementId] : 0;

            $db->setQuery(
                "UPDATE #__contentbuilder_ng_elements
                 SET `order_type`   = " . $db->quote((string) $otype) . ",
                     `label`        = " . $db->quote((string) $label) . ",
                     `wordwrap`     = " . (int) $wrap . ",
                     `item_wrapper` = " . $db->quote(trim((string) $wrapper)) . ",
                     `ordering`     = " . (int) $ord . "
                 WHERE form_id = " . (int) $formId . " AND id = " . (int) $elementId
            );
            $db->execute();
        }
    }

    public function saveElementListSettingsFromRequest(int $formId): bool
    {
        if ($formId <= 0) {
            return true;
        }

        try {
            $input = Factory::getApplication()->input;
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
        return $this->loadForm(
            'com_contentbuilder_ng.form',
            'form',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    protected function populateState(): void
    {
        // Déjà le parent.
        parent::populateState();

        // 2) ID depuis l'URL (standard Joomla en admin)
        $app   = Factory::getApplication();
        $input = $app->input;
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
        $app = Factory::getApplication();
        $data = $app->getUserState('com_contentbuilder_ng.edit.form.data', []);

        return !empty($data) ? $data : (array) $this->getItem();
    }


    function setListEditable()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }


        $db = $this->getDatabase();
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set editable = 1 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
            $db->execute();
        }
    }

    function setListListInclude()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set list_include = 1 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
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
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set search_include = 1 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
            $db->execute();
        }
    }

    function setListNotLinkable()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set linkable = 0 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
            $db->execute();
        }
    }

    function setListNotEditable()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set editable = 0 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
            $db->execute();
        }
    }

    function setListNoListInclude()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set list_include = 0 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
            $db->execute();
        }
    }

    function setListNoSearchInclude()
    {
        $formId = (int) $this->getState($this->getName() . '.id');
        $formId = (int) $this->getState($this->getName() . '.id');
        if ($formId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $db->setQuery(' Update #__contentbuilder_ng_elements ' .
                '  Set search_include = 0 Where form_id = ' . $formId . ' And id In ( ' . implode(',', $items) . ')');
            $db->execute();
        }
    }

    function getListStatesActionPlugins()
    {
        $db = $this->getDatabase();
        $db->setQuery("Select `element` From #__extensions Where `folder` = 'contentbuilder_ng_listaction' And `enabled` = 1");
        $res = $db->loadColumn();
        return $res;
    }

    function getThemePlugins()
    {
        $db = $this->getDatabase();
        $themes = [];

        $enabledQueries = [
            "Select `element` From #__extensions Where `type` = 'plugin' And `folder` = 'contentbuilder_ng_themes' And `enabled` = 1",
            "Select `element` From #__extensions Where `type` = 'plugin' And `folder` = 'contentbuilder_themes_ng' And `enabled` = 1",
        ];

        foreach ($enabledQueries as $query) {
            try {
                $db->setQuery($query);
                $rows = $db->loadColumn() ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $row = trim((string) $row);
                if ($row !== '') {
                    $themes[] = $row;
                }
            }
        }

        // If no enabled plugin row could be found, keep a safe fallback list.
        if (empty($themes)) {
            $fallbackQueries = [
                "Select `element` From #__extensions Where `type` = 'plugin' And `folder` = 'contentbuilder_ng_themes'",
                "Select `element` From #__extensions Where `type` = 'plugin' And `folder` = 'contentbuilder_themes_ng'",
            ];

            foreach ($fallbackQueries as $query) {
                try {
                    $db->setQuery($query);
                    $rows = $db->loadColumn() ?: [];
                } catch (\Throwable $e) {
                    $rows = [];
                }

                foreach ($rows as $row) {
                    $row = trim((string) $row);
                    if ($row !== '') {
                        $themes[] = $row;
                    }
                }
            }
        }

        // Last-resort fallback: scan plugin directories if extension rows are missing.
        foreach ([JPATH_ROOT . '/plugins/contentbuilder_ng_themes', JPATH_ROOT . '/plugins/contentbuilder_themes_ng'] as $path) {
            if (!Folder::exists($path)) {
                continue;
            }
            foreach (Folder::folders($path) as $folder) {
                $folder = trim((string) $folder);
                if ($folder !== '') {
                    $themes[] = $folder;
                }
            }
        }

        $themes = array_values(array_unique($themes));

        if (!in_array('dark', $themes, true)) {
            $themes[] = 'dark';
        }

        if (empty($themes)) {
            return ['joomla6', 'dark', 'blank', 'khepri'];
        }

        usort($themes, static function (string $a, string $b): int {
            $order = ['joomla6' => 0, 'dark' => 1, 'blank' => 2, 'khepri' => 3];
            $rankA = $order[$a] ?? 99;
            $rankB = $order[$b] ?? 99;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return strcasecmp($a, $b);
        });

        if (!in_array('joomla6', $themes, true)) {
            array_unshift($themes, 'joomla6');
        }

        return array_values(array_unique($themes));
    }

    function getVerificationPlugins()
    {
        $db = $this->getDatabase();
        $db->setQuery("Select `element` From #__extensions Where `folder` = 'contentbuilder_ng_verify' And `enabled` = 1");
        $res = $db->loadColumn();
        return $res;
    }


    /*
     * MAIN DETAILS AREA
     */

    public function getItem($formId = null)
    {
        if ($formId === null) {
            $formId = (int) $this->getState($this->getName() . '.id');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilder_ng_forms'))
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
            $data->upload_directory = JPATH_SITE . '/media/com_contentbuilder_ng/upload';
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
            $data->list_rating = 0;
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

            $data->theme_plugin = 'joomla6';

            $data->rating_slots = 5;

            $data->rand_date_update = null;

            $data->rand_update = '86400';

            $data->article_record_impact_publish = 0;
            $data->article_record_impact_language = 0;

            $data->allow_external_filter = 0;

            $data->show_filter = 1;

            $data->show_records_per_page = 1;

            $data->button_bar_sticky = 0;

            $data->show_preview_link = 0;

            $data->initial_list_limit = 20;

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

        if (!isset($data->show_preview_link)) {
            $data->show_preview_link = 0;
        }

        $data->forms = array();
        $data->types = ContentbuilderLegacyHelper::getTypes();

        if ($data->type) {
            $data->forms = ContentbuilderLegacyHelper::getForms($data->type);
        }

        $data->form = null;
        if ($data->type && $data->reference_id) {
            $data->form = ContentbuilderLegacyHelper::getForm($data->type, $data->reference_id);
            if (!$data->form || !$data->form->exists) {
                if ((string) $data->type === 'com_breezingforms') {
                    Factory::getApplication()->enqueueMessage(
                        Text::sprintf('COM_CONTENTBUILDER_NG_BREEZINGFORMS_SOURCE_NOT_FOUND', (int) $data->reference_id),
                        'warning'
                    );
                } else {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_CONTENTBUILDER_NG_FORM_NOT_FOUND'), 'warning');
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
                    ContentbuilderLegacyHelper::synchElements($data->id, $data->form);
                    $elements_table = $this->getTable('Elementoptions');
                    $elements_table->reorder('form_id=' . $data->id);
                }
            }
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__contentbuilder_ng_list_states'))
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->order('id ASC')
        );

        $list_states = $db->loadAssocList();

        if (count($list_states)) {
            $data->list_states = $list_states;
        } else {
            $data->list_states = $this->_default_list_states;
        }

        $data->language_codes = ContentbuilderLegacyHelper::getLanguageCodes();

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

        // Filter by the type
        $query->where('(a.extension = ' . $db->quote('com_content') . ' OR a.parent_id = 0)');

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
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
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

        $now  = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        $table->name  = trim((string) $table->name);
        $table->title = trim((string) $table->title);
        $table->tag   = trim((string) ($table->tag ?? ''));
        
        // Si tes champs existent bien en JTable (c'est le cas)
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

    public function save($data): bool
    {
        $app   = Factory::getApplication();
        $input = $app->input;
        $db    = $this->getDatabase();

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
            'show_preview_link',
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
        ];

        foreach ($boolFields as $bf) {
            if (array_key_exists($bf, $jform)) {
                $jform[$bf] = !empty($jform[$bf]) ? 1 : 0;
            }
        }

        // Tag défaut
        if (empty($jform['tag'])) {
            $jform['tag'] = 'default';
        }

        $selectedThemePlugin = (string) ($jform['theme_plugin'] ?? '');
        if ($selectedThemePlugin !== '') {
            $availableThemePlugins = $this->getThemePlugins();
            if (!in_array($selectedThemePlugin, $availableThemePlugins, true)) {
                $jform['theme_plugin'] = 'joomla6';
            }
        }

        // 4) Capture list_states puis on l'enlève du bind principal (car stocké ailleurs)
        $list_states = (array) ($jform['list_states'] ?? []);
        unset($jform['list_states']);

        // 5) Upload directory: keep current folder, do not fallback to another path.
        if (!isset($jform['upload_directory']) || $jform['upload_directory'] === '') {
            $jform['upload_directory'] = 'media/com_contentbuilder_ng/upload';
        }

        $upl_ex = explode('|', (string) $jform['upload_directory']);
        $basePath = trim((string) $upl_ex[0]);
        $tokens   = isset($upl_ex[1]) ? '|' . $upl_ex[1] : '';

        $basePath = str_replace('\\', '/', $basePath);
        $basePath = str_ireplace(
            ['{CBSite}/media/contentbuilder_ng', '{cbsite}/media/contentbuilder_ng'],
            ['{CBSite}/media/com_contentbuilder_ng', '{cbsite}/media/com_contentbuilder_ng'],
            $basePath
        );
        if (stripos($basePath, '/media/contentbuilder_ng') === 0) {
            $basePath = 'media/com_contentbuilder_ng' . substr($basePath, strlen('/media/contentbuilder_ng'));
        } elseif (stripos($basePath, 'media/contentbuilder_ng') === 0) {
            $basePath = 'media/com_contentbuilder_ng' . substr($basePath, strlen('media/contentbuilder_ng'));
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

        $resolved = ContentbuilderLegacyHelper::makeSafeFolder($resolved);

        $protect = !empty($jform['protect_upload_directory']);

        // Create the configured folder when missing (no fallback path switching).
        if (!is_dir($resolved)) {
            if (!Folder::create($resolved)) {
                $app->enqueueMessage('Could not create upload folder: ' . $resolved, 'error');
            } else {
                File::write($resolved . '/index.html', '');
                if ($protect) {
                    File::write($resolved . '/.htaccess', 'deny from all');
                }
            }
        }

        // Applique protection (safe folder) si besoin
        if ($protect) {
            $safe = ContentbuilderLegacyHelper::makeSafeFolder($resolved);

            if (is_dir($safe)) {
                if (!file_exists($safe . '/index.html')) {
                    File::write($safe . '/index.html', '');
                }
                if (!file_exists($safe . '/.htaccess')) {
                    File::write($safe . '/.htaccess', 'deny from all');
                }
            }
        } else {
            $safe = ContentbuilderLegacyHelper::makeSafeFolder($resolved);
            if (file_exists($safe . '/.htaccess')) {
                File::delete($safe . '/.htaccess');
            }
        }

        // On restaure le format legacy upload_directory avec tokens (comme avant)
        $jform['upload_directory'] = $tmp_upload_directory . $tokens;

        // 6) Permissions/config legacy : on reconstruit proprement (sans 200 if)
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
            'rating'
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
            ->from($db->quoteName('#__usergroups', 'node'));
        $db->setQuery($q);
        $groupIds = $db->loadColumn() ?: [];

        foreach ($groupIds as $gid) {
            $gid = (int) $gid;

            $config['permissions'][$gid] = [];
            $config['permissions_fe'][$gid] = [];

            foreach ($ownKeys as $k) {
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
            $formObj = ContentbuilderLegacyHelper::getForm($jform['type'], $jform['reference_id']);
        }

        $createSample = !empty($jform['create_sample']);
        if ($createSample) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDER_NG_FORM_NOT_FOUND'), 'warning');
            }
            $sample = ContentbuilderLegacyHelper::createDetailsSample($id, $formObj, $jform['theme_plugin']);
            Logger::info('Details sample requested', [
                'form_id' => $id,
                'theme_plugin' => $jform['theme_plugin'] ?? null,
                'sample_length' => is_string($sample) ? strlen($sample) : null,
            ]);
            if ($sample === '' || $sample === null) {
                $app->enqueueMessage('Details sample generation returned empty output (theme: ' . ($jform['theme_plugin'] ?? 'none') . ').', 'warning');
            }
            $jform['details_template'] = (string) $sample;
        }

        $createEditableSample = !empty($jform['create_editable_sample']);
        if ($createEditableSample) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDER_NG_FORM_NOT_FOUND'), 'warning');
            }
            $jform['editable_template'] = ContentbuilderLegacyHelper::createEditableSample($id, $formObj, $jform['theme_plugin']);
        }

        $emailAdminHtml = !empty($jform['email_admin_html']);
        $emailAdminTemplate = !empty($jform['email_admin_create_sample']);
        if ($emailAdminTemplate) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDER_NG_FORM_NOT_FOUND'), 'warning');
            }
            $jform['email_admin_template'] = ContentbuilderLegacyHelper::createEmailSample($id, $formObj, $emailAdminHtml);
        }

        $emailCreateSample = !empty($jform['email_create_sample']);
        if ($emailCreateSample) {
            if (!$formObj) {
                $app->enqueueMessage(Text::_('COM_CONTENTBUILDER_NG_FORM_NOT_FOUND'), 'warning');
            }
            $jform['email_template'] = ContentbuilderLegacyHelper::createEmailSample($id, $formObj, Factory::getApplication()->input->getBool('email_html', false));
        }

        $isBreezingFormsType = in_array(
            (string) ($jform['type'] ?? ''),
            ['com_breezingforms', 'com_breezingforms_ng'],
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

        // Config legacy
        $jform['config'] = PackedDataHelper::encodePackedData($config);

        // Last_update.
        $jform['last_update'] = Factory::getDate()->toSql();

        // 7) Ajustements legacy divers (si nécessaire)
        // - default_category depuis sectioncategories (comme avant)
        if (isset($jform['sectioncategories'])) {
            $jform['default_category'] = (int) $jform['sectioncategories'];
            unset($jform['sectioncategories']);
        }

        // 8) Sauvegarde STANDARD Joomla (bind/check/store + prepareTable() + events)
        // IMPORTANT: parent::save() prend un array "jform-like"
        $this->ensureListDisplayColumns();
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

        // 10) Mettre à jour/insérer list_states (même logique que ton code)
        if (!empty($list_states)) {
            foreach ($list_states as $state_id => $item) {
                $sid = (int) $state_id;
                if ($sid > 0) {
                    $db->setQuery(
                        "UPDATE #__contentbuilder_ng_list_states
                     SET published = " . (isset($item['published']) && $item['published'] ? 1 : 0) . ",
                         `title`    = " . $db->quote(stripslashes(strip_tags((string) ($item['title'] ?? '')))) . ",
                         color      = " . $db->quote($this->normalizeListStateColor($item['color'] ?? 'FFFFFF')) . ",
                         action     = " . $db->quote((string) ($item['action'] ?? '')) . "
                     WHERE form_id = " . (int) $formId . " AND id = " . (int) $sid
                    );
                    $db->execute();
                }
            }
        }

        // Fallback: si pas assez d'états, on complète
        $db->setQuery("SELECT COUNT(id) FROM #__contentbuilder_ng_list_states WHERE form_id = " . (int) $formId);
        $existingCount = (int) $db->loadResult();

        $defaultCount = count($this->_default_list_states);
        if ($existingCount < 1) {
            // rien du tout -> on insert tout
            for ($i = 0; $i < $defaultCount; $i++) {
                $db->setQuery(
                    "INSERT INTO #__contentbuilder_ng_list_states (form_id, `title`, color, action)
                 VALUES (" . (int) $formId . ", " . $db->quote('State') . ", " . $db->quote('FFFFFF') . ", " . $db->quote('') . ")"
                );
                $db->execute();
            }
        } elseif ($existingCount < $defaultCount) {
            // on complète le delta
            $add = $defaultCount - $existingCount;
            for ($i = 0; $i < $add; $i++) {
                $db->setQuery(
                    "INSERT INTO #__contentbuilder_ng_list_states (form_id, `title`, color, action)
                 VALUES (" . (int) $formId . ", " . $db->quote('State') . ", " . $db->quote('FFFFFF') . ", " . $db->quote('') . ")"
                );
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

        // 13) Synchronisation éventuelle des éléments (si tu en as besoin)
        // IMPORTANT: Evite de le faire dans getItem() (effets de bord).
        // Ici tu peux le déclencher si tu as type/reference_id stables :
        // - récupérer l'item fraîchement sauvegardé (standard)
        // $item = $this->getItem($formId);
        // if (!empty($item->type) && !empty($item->reference_id)) {
        //     $form = ContentbuilderLegacyHelper::getForm($item->type, $item->reference_id);
        //     if (is_object($form) && !empty($form->exists)) {
        //         ContentbuilderLegacyHelper::synchElements($formId, $form);
        //     }
        // }

        // 14) Mettre à jour l'état du modèle / input (utile pour save2new/apply)
        $this->setState($this->getName() . '.id', $formId);
        $input->set('id', $formId);

        $jform['id'] = $formId;
        $input->post->set('jform', $jform);

        return true;
    }


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
            $db->setQuery("Select article.article_id From #__contentbuilder_ng_articles As article, #__contentbuilder_ng_forms As form Where form.delete_articles > 0 And form.id = article.form_id And article.form_id = " . intval($cid));
            $articles = $db->loadColumn();
            if (count($articles)) {
                $article_items = array();
                foreach ($articles as $article) {
                    $article_items[] = $db->quote('com_content.article.' . $article);
                    $table = new \Joomla\CMS\Table\Content($db);

                    // Trigger the onContentBeforeDelete event.
                    if ($table->load($article)) {
                        $dispatcher = Factory::getApplication()->getDispatcher();
                        $eventObj = new \Joomla\CMS\Event\Model\BeforeDeleteEvent('onContentBeforeDelete', [
                            'context' => 'com_content.article',
                            'subject' => $table,
                        ]);
                        $dispatcher->dispatch('onContentBeforeDelete', $eventObj);
                    }
                    $db->setQuery("Delete From #__content Where id = " . intval($article));
                    $db->execute();

                    // Trigger the onContentAfterDelete event.
                    $table->reset();
                    $dispatcher = Factory::getApplication()->getDispatcher();
                    $eventObj = new \Joomla\CMS\Event\Model\AfterDeleteEvent('onContentAfterDelete', [
                        'context' => 'com_content.article',
                        'subject' => $table,
                    ]);
                    $dispatcher->dispatch('onContentAfterDelete', $eventObj);
                }
                $db->setQuery("Delete From #__assets Where `name` In (" . implode(',', $article_items) . ")");
                $db->execute();
            }


            $db->setQuery("
                Delete
                    `elements`.*
                From
                    #__contentbuilder_ng_elements As `elements`
                Where
                    `elements`.form_id = " . $cid);

            $db->execute();

            $db->setQuery("
                Delete
                    `states`.*
                From
                    #__contentbuilder_ng_list_states As `states`
                Where
                    `states`.form_id = " . $cid);

            $db->execute();

            $db->setQuery("
                Delete
                    `records`.*
                From
                    #__contentbuilder_ng_list_records As `records`
                Where
                    `records`.form_id = " . $cid);

            $db->execute();

            $db->setQuery("
                Delete
                    `access`.*
                From
                    #__contentbuilder_ng_resource_access As `access`
                Where
                    `access`.form_id = " . $cid);

            $db->execute();

            $db->setQuery("
                Delete
                    `users`.*
                From
                    #__contentbuilder_ng_users As `users`
                Where
                    `users`.form_id = " . $cid);

            $db->execute();

            $db->setQuery("
                Delete
                    `users`.*
                From
                    #__contentbuilder_ng_registered_users As `users`
                Where
                    `users`.form_id = " . $cid);

            $db->execute();

            $this->getTable('Elementoptions')->reorder('form_id = ' . $cid);

            $db->setQuery("Delete From #__menu Where `link` = 'index.php?option=com_contentbuilder_ng&task=list.display&id=" . intval($cid) . "'");
            $db->execute();
            $db->setQuery("Select count(id) From #__menu Where `link` Like 'index.php?option=com_contentbuilder_ng&task=list.display&id=%'");
            $amount = $db->loadResult();
            if (!$amount) {
                $db->setQuery("Delete From #__menu Where `link` = 'index.php?option=com_contentbuilder_ng&viewcontainer=true'");
                $db->execute();
            }

            if (!$row->delete($cid)) {
                return false;
            }
        }

        $row->reorder();

        // article deletion if required
        $db->setQuery("Select `id` From #__contentbuilder_ng_forms");
        $references = $db->loadColumn();

        $cnt = count($references);
        if ($cnt) {
            $new_items = array();
            for ($i = 0; $i < $cnt; $i++) {
                $new_items[] = $db->quote($references[$i]);
            }
            $db->setQuery("Delete From #__contentbuilder_ng_articles Where `form_id` Not In (" . implode(',', $new_items) . ") ");
            $db->execute();
        } else {
            $db->setQuery("Delete From #__contentbuilder_ng_articles");
            $db->execute();
        }

        return true;
    }

    public function move($direction): bool
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

    private function copyByIds($cids): bool
    {
        $cids = Factory::getApplication()->input->get('cid', [], 'array');
        ArrayHelper::toInteger($cids);

        if (!count($cids))
            return false;

        $db = $this->getDatabase();
        $table = $this->getTable('Form', '');
        $db->setQuery(' Select * From #__contentbuilder_ng_forms ' .
            '  Where id In ( ' . implode(',', $cids) . ')');
        $result = $db->loadObjectList();

        foreach ($result as $obj) {
            $origId = $obj->id;
            unset($obj->id);

            $obj->name = 'Copy of ' . $obj->name;
            $obj->published = 0;

            // $obj->created = Factory::getDate()->toSql();
            // $obj->created_by = Factory::getApplication()->getIdentity()->id;
            $obj->modified = Factory::getDate()->toSql();
            $obj->modified_by = Factory::getApplication()->getIdentity()->id;
            
            $db->insertObject('#__contentbuilder_ng_forms', $obj);
            $insertId = $db->insertid();

            // Elements
            $db->setQuery(' Select * From #__contentbuilder_ng_elements ' .
                '  Where form_id = ' . $origId);
            $elements = $db->loadObjectList();
            foreach ($elements as $element) {
                unset($element->id);
                $element->form_id = $insertId;
                $db->insertObject('#__contentbuilder_ng_elements', $element);
            }

            // list states
            $db->setQuery(' Select * From #__contentbuilder_ng_list_states ' .
                '  Where form_id = ' . $origId);
            $elements = $db->loadObjectList();
            foreach ($elements as $element) {
                unset($element->id);
                $element->form_id = $insertId;
                $db->insertObject('#__contentbuilder_ng_list_states', $element);
            }
            // XDA-Gil fix 'Copy of Form' in Component Menu in Backen CB View
            // ContentbuilderLegacyHelper::createBackendMenuItem($insertId, $obj->name, true);
        }

        $table->reorder();

        return true;
    }
}
