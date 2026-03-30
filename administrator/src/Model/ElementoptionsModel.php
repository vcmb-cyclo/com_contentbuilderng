<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Utilities\ArrayHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;

class ElementoptionsModel extends BaseDatabaseModel
{
    private $_element_id = 0;

    private function getApp()
    {
        return Factory::getApplication();
    }

    private function getInput()
    {
        return $this->getApp()->input;
    }

    private function getAppDispatcher()
    {
        return $this->getApp()->getDispatcher();
    }

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory);

        $this->_db = Factory::getContainer()->get(DatabaseInterface::class);

        $input = $this->getInput();
        $formId = $input->getInt('id', $input->getInt('form_id', 0));
        $elementId = $input->getInt('element_id', 0);

        // Fallback for list actions that send selected ids as cid[].
        if ($elementId < 1) {
            $cid = (array) $input->get('cid', [], 'array');
            if (!empty($cid)) {
                $elementId = (int) reset($cid);
            }
        }

        $this->setIds($formId, $elementId);
    }

    /*
     * MAIN DETAILS AREA
     */

    /**
     *
     * @param int $id
     */
    function setIds($id, $element_id)
    {
        // Set id and wipe data
        $this->_id = $id;
        $this->_element_id = $element_id;
        $this->_data = null;
    }

    private function _buildQuery()
    {
        $db = $this->getDatabase();
        return $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('id') . ' = ' . (int)$this->_element_id);
    }

    function getData()
    {
        // Lets load the data if it doesn't already exist.
        // Store false when no row exists to avoid re-querying and null warnings downstream.
        if ($this->_data === null) {
            if ((int) $this->_element_id < 1 && (int) $this->_id > 0) {
                // Fallback: no explicit element selected, use the first field of the current form.
                $db = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->where($db->quoteName('form_id') . ' = ' . (int)$this->_id)
                    ->order($db->quoteName('ordering') . ', ' . $db->quoteName('id'))
                    ->setLimit(1);
                $db->setQuery($query);
                $fallbackElementId = (int) $db->loadResult();
                if ($fallbackElementId > 0) {
                    $this->_element_id = $fallbackElementId;
                }
            }

            if ((int) $this->_element_id < 1) {
                $this->_data = false;
                return null;
            }

            $query = $this->_buildQuery();
            $this->getDatabase()->setQuery($query);
            $row = $this->getDatabase()->loadObject();

            if (is_object($row)) {
                $decodedOptions = PackedDataHelper::decodePackedData($row->options ?? '', null);
                if (is_array($decodedOptions)) {
                    $decodedOptions = (object) $decodedOptions;
                }
                $row->options = $decodedOptions;
                if (!empty($row->form_id)) {
                    $this->_id = (int) $row->form_id;
                }
                $this->_data = $row;
            } else {
                $this->_data = false;
            }
        }

        return is_object($this->_data) ? $this->_data : null;
    }

    function getValidationPlugins()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_validation'))
            ->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);

        $res = $db->loadColumn();
        return $res;
    }

    function getGroupDefinition()
    {
        if ($this->_data === null) {
            $this->getData();
        }

        if (!is_object($this->_data) || empty($this->_data->reference_id)) {
            return array();
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int)$this->_id);
        $db->setQuery($query);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow) || empty($formRow['type']) || empty($formRow['reference_id'])) {
            return array();
        }

        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);

        if ($form && $form->isGroup($this->_data->reference_id)) {
            return $form->getGroupDefinition($this->_data->reference_id);
        }

        return array();
    }

    function store()
    {
        $input = $this->getInput();

        if ($input->getInt('type_change', 0)) {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($db->quoteName('type') . ' = ' . $db->quote($input->getCmd('type_selection', '')))
                ->where($db->quoteName('id') . ' = ' . (int)$this->_element_id);
            $db->setQuery($query);
            $db->execute();
            return 1;
        }
        $setClauses = [];
        $formSupportService = new FormSupportService(new PathService());
        $pathService = new PathService();
        $plugins = $formSupportService->getFormElementsPlugins();
        $type = $input->getCmd('field_type', '');
        $db = $this->getDatabase();
        switch ($type) {
            case in_array($input->getCmd('field_type', ''), $formSupportService->getFormElementsPlugins()):

                $hint = $input->post->get('hint', '', 'html');

                \Joomla\CMS\Plugin\PluginHelper::importPlugin('contentbuilderng_form_elements', $input->getCmd('field_type', ''));

                $dispatcher = $this->getAppDispatcher();
                $eventResult = $dispatcher->dispatch('onSettingsStore', new \Joomla\CMS\Event\GenericEvent('onSettingsStore', array()));
                $results = $eventResult->getArgument('result') ?: [];
                $this->getAppDispatcher()->clearListeners('onSettingsStore');

                if (count($results)) {
                    $results = $results[0];
                }

                $the_item = $results;

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($the_item['options']));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote($input->getCmd('field_type', ''));
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote($input->getCmd('field_type', ''));
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($the_item['default_value']);
                break;

            case '':
            case 'text':
                $length = $input->get('length', '', 'string');
                $maxlength = $input->getInt('maxlength', '');
                $password = $input->getInt('password', 0);
                $readonly = $input->getInt('readonly', 0);
                $default_value = $input->post->get('default_value', '', 'raw');
                $class = $input->get('class', '', 'string');
                $allow_raw = $input->getInt('allow_encoding', 0) == 2 ? true : false; // 0 = filter on, 1 = allow html, 2 = allow raw
                $allow_html = $input->getInt('allow_encoding', 0) == 1 ? true : false;
                $hint = $input->post->get('hint', '', 'html');

                $options = new \stdClass();
                $options->length = $length;
                $options->class = $class;
                $options->maxlength = $maxlength;
                $options->password = $password;
                $options->readonly = $readonly;
                $options->allow_raw = $allow_raw;
                $options->allow_html = $allow_html;

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote('text');
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote('text');
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;

            case 'textarea':
                $maxlength = $input->getInt('maxlength', '');
                $width = $input->get('width', '', 'string');
                $height = $input->get('height', '', 'string');
                $default_value = $input->post->get('default_value', '', 'raw');
                $class = $input->get('class', '', 'string');
                $readonly = $input->getInt('readonly', 0);
                $allow_raw = $input->getInt('allow_encoding', 0) == 2 ? true : false; // 0 = filter on, 1 = allow html, 2 = allow raw
                $allow_html = $input->getInt('allow_encoding', 0) == 1 ? true : false;
                $hint = $input->post->get('hint', '', 'html');

                $options = new \stdClass();
                $options->class = $class;
                $options->maxlength = $maxlength;
                $options->width = $width;
                $options->height = $height;
                $options->readonly = $readonly;
                $options->allow_raw = $allow_raw;
                $options->allow_html = $allow_html;

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote('textarea');
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote('textarea');
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;

            case 'checkboxgroup':
            case 'radiogroup':
            case 'select':
                $seperator = $input->post->get('seperator', ',', 'raw');

                if ($seperator == '\n') {
                    $seperator = "\n";
                }

                $defaultValues = $input->post->get('default_value', [], 'array');
                $default_value = implode($seperator, $defaultValues);
                $class = $input->get('class', '', 'string');
                $allow_raw = $input->getInt('allow_encoding', 0) == 2 ? true : false; // 0 = filter on, 1 = allow html, 2 = allow raw
                $allow_html = $input->getInt('allow_encoding', 0) == 1 ? true : false;
                $hint = $input->post->get('hint', '', 'html');

                $options = new \stdClass();
                $options->class = $class;
                $options->seperator = $seperator;
                $options->allow_raw = $allow_raw;
                $options->allow_html = $allow_html;

                if ($type == 'select') {
                    $multi = $input->getInt('multiple', 0);
                    $options->multiple = $multi;
                    $options->length = $input->get('length', '', 'string');
                }

                if ($type == 'checkboxgroup' || $type == 'radiogroup') {
                    $options->horizontal = $input->getBool('horizontal', 0);
                    $options->horizontal_length = $input->get('horizontal_length', '', 'string');
                }

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;

            case 'upload':
                $setupQuery = $db->getQuery(true)
                    ->select([$db->quoteName('upload_directory'), $db->quoteName('protect_upload_directory')])
                    ->from($db->quoteName('#__contentbuilderng_forms'))
                    ->where($db->quoteName('id') . ' = ' . (int)$this->_id);
                $db->setQuery($setupQuery);
                $setup = $db->loadAssoc();

                // rel check for setup

                $tokens = '';

                $upl_ex = explode('|', (string) ($setup['upload_directory'] ?? ''), 2);
                $setupUploadDirectory = trim((string) ($upl_ex[0] ?? ''));
                if ($setupUploadDirectory === '') {
                    $setupUploadDirectory = 'media/com_contentbuilderng/upload';
                }
                $setupUploadDirectory = str_replace('\\', '/', $setupUploadDirectory);
                $setupUploadDirectory = str_ireplace(
                    ['{CBSite}/media/contentbuilderng', '{cbsite}/media/contentbuilderng'],
                    ['{CBSite}/media/com_contentbuilderng', '{cbsite}/media/com_contentbuilderng'],
                    $setupUploadDirectory
                );
                if (stripos($setupUploadDirectory, '/media/contentbuilderng') === 0) {
                    $setupUploadDirectory = 'media/com_contentbuilderng' . substr($setupUploadDirectory, strlen('/media/contentbuilderng'));
                } elseif (stripos($setupUploadDirectory, 'media/contentbuilderng') === 0) {
                    $setupUploadDirectory = 'media/com_contentbuilderng' . substr($setupUploadDirectory, strlen('media/contentbuilderng'));
                }

                $upl_ex2 = explode('|', trim((string) $input->get('upload_directory', '', 'string')), 2);
                $optionUploadDirectory = trim((string) ($upl_ex2[0] ?? ''));
                $optionUploadDirectory = str_replace('\\', '/', $optionUploadDirectory);
                $optionUploadDirectory = str_ireplace(
                    ['{CBSite}/media/contentbuilderng', '{cbsite}/media/contentbuilderng'],
                    ['{CBSite}/media/com_contentbuilderng', '{cbsite}/media/com_contentbuilderng'],
                    $optionUploadDirectory
                );
                if (stripos($optionUploadDirectory, '/media/contentbuilderng') === 0) {
                    $optionUploadDirectory = 'media/com_contentbuilderng' . substr($optionUploadDirectory, strlen('/media/contentbuilderng'));
                } elseif (stripos($optionUploadDirectory, 'media/contentbuilderng') === 0) {
                    $optionUploadDirectory = 'media/com_contentbuilderng' . substr($optionUploadDirectory, strlen('media/contentbuilderng'));
                }
                $input->set('upload_directory', $optionUploadDirectory);

                $siteRoot = rtrim(str_replace('\\', '/', JPATH_SITE), '/');

                $is_relative = strpos(strtolower($setupUploadDirectory), '{cbsite}') === 0;
                $tmp_upload_directory = $setupUploadDirectory;
                if ($is_relative) {
                    $upload_directory = str_ireplace(array('{CBSite}', '{cbsite}'), JPATH_SITE, $setupUploadDirectory);
                } else {
                    $isWinAbs = (bool) preg_match('#^[A-Za-z]:/#', $setupUploadDirectory);
                    if (!$isWinAbs && strpos($setupUploadDirectory, '/') === 0) {
                        $setupUploadDirectory = ltrim($setupUploadDirectory, '/');
                        $tmp_upload_directory = $setupUploadDirectory;
                    }
                    $upload_directory = ($isWinAbs || stripos($setupUploadDirectory, $siteRoot . '/') === 0 || strcasecmp($setupUploadDirectory, $siteRoot) === 0)
                        ? $setupUploadDirectory
                        : $siteRoot . '/' . ltrim($setupUploadDirectory, '/');
                }
                $upload_directory = $pathService->makeSafeFolder($upload_directory);

                // rel check for element options
                $is_opt_relative = strpos(strtolower($optionUploadDirectory), '{cbsite}') === 0;
                $tmp_opt_upload_directory = $optionUploadDirectory;
                if ($is_opt_relative) {
                    $opt_upload_directory = str_ireplace(array('{CBSite}', '{cbsite}'), JPATH_SITE, $optionUploadDirectory);
                } else {
                    $isOptWinAbs = (bool) preg_match('#^[A-Za-z]:/#', $optionUploadDirectory);
                    if (!$isOptWinAbs && strpos($optionUploadDirectory, '/') === 0) {
                        $optionUploadDirectory = ltrim($optionUploadDirectory, '/');
                        $tmp_opt_upload_directory = $optionUploadDirectory;
                    }
                    $opt_upload_directory = ($isOptWinAbs || stripos($optionUploadDirectory, $siteRoot . '/') === 0 || strcasecmp($optionUploadDirectory, $siteRoot) === 0)
                        ? $optionUploadDirectory
                        : $siteRoot . '/' . ltrim($optionUploadDirectory, '/');
                }
                $opt_upload_directory = $pathService->makeSafeFolder($opt_upload_directory);


                $protect = $setup['protect_upload_directory'];

                if ($optionUploadDirectory === '') {
                    if ($upload_directory !== '' && !is_dir($upload_directory)) {
                        Folder::create($upload_directory);
                        File::write($upload_directory . '/index.html', '');
                    }
                    if (isset($upl_ex[1])) {
                        $tokens = '|' . $upl_ex[1];
                    }
                } else if ($opt_upload_directory !== '' && !is_dir($opt_upload_directory)) {
                    $upload_directory = $opt_upload_directory;
                    Folder::create($upload_directory);
                    File::write($upload_directory . '/index.html', '');
                    $is_relative = $is_opt_relative ? 1 : 0;
                    $tmp_upload_directory = $tmp_opt_upload_directory;
                    if (isset($upl_ex2[1])) {
                        $tokens = '|' . $upl_ex2[1];
                    }
                } else if ($opt_upload_directory !== '') {
                    $upload_directory = $opt_upload_directory;
                    $is_relative = $is_opt_relative ? 1 : 0;
                    $tmp_upload_directory = $tmp_opt_upload_directory;
                    if (isset($upl_ex2[1])) {
                        $tokens = '|' . $upl_ex2[1];
                    }
                } else {
                    if (isset($upl_ex[1])) {
                        $tokens = '|' . $upl_ex[1];
                    }
                }

                if ($protect && is_dir($upload_directory)) {

                    File::write($pathService->makeSafeFolder($upload_directory) .'/.htaccess', $def = 'deny from all');

                } else if (!$protect && is_dir($upload_directory)) {
                    if (file_exists($pathService->makeSafeFolder($upload_directory) .'/.htaccess')) {
                        File::delete($pathService->makeSafeFolder($upload_directory) .'/.htaccess');
                    }

                }

                $default_value = $input->get('default_value', '', 'string');
                $hint = $input->post->get('hint', '', 'html');

                $options = new \stdClass();
                $options->upload_directory = is_dir($upload_directory) ? ($is_relative ? $tmp_upload_directory : $upload_directory) . $tokens : '';
                $options->allowed_file_extensions = $input->get('allowed_file_extensions', '', 'string');
                $options->max_filesize = $input->get('max_filesize', '', 'string');

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;
            case 'captcha':
                $default_value = $input->get('default_value', '', 'string');
                $hint = $input->post->get('hint', '', 'html');

                $options = new \stdClass();

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;
            case 'calendar':
                $length = $input->get('length', '', 'string');
                $format = $input->get('format', '', 'string');
                $transfer_format = $input->get('transfer_format', '', 'string');
                $maxlength = $input->getInt('maxlength', '');
                $readonly = $input->getInt('readonly', 0);
                $default_value = $input->post->get('default_value', '', 'raw');
                $hint = $input->post->get('hint', '', 'html');

                $options = new \stdClass();
                $options->length = $length;
                $options->maxlength = $maxlength;
                $options->readonly = $readonly;
                $options->format = $format;
                $options->transfer_format = $transfer_format;

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote('calendar');
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote('calendar');
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;
            case 'hidden':
                $allow_raw = $input->getInt('allow_encoding', 0) == 2 ? true : false; // 0 = filter on, 1 = allow html, 2 = allow raw
                $allow_html = $input->getInt('allow_encoding', 0) == 1 ? true : false;
                $default_value = $input->post->get('default_value', '', 'raw');
                $hint = '';

                $options = new \stdClass();
                $options->allow_raw = $allow_raw;
                $options->allow_html = $allow_html;

                $setClauses[] = $db->quoteName('options') . ' = ' . $db->quote(PackedDataHelper::encodePackedData($options));
                $setClauses[] = $db->quoteName('type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('change_type') . ' = ' . $db->quote($type);
                $setClauses[] = $db->quoteName('hint') . ' = ' . $db->quote($hint);
                $setClauses[] = $db->quoteName('default_value') . ' = ' . $db->quote($default_value);
                break;
        }

        if (!empty($setClauses)) {
            $custom_init_script = $input->post->get('custom_init_script', '', 'raw');
            $custom_action_script = $input->post->get('custom_action_script', '', 'raw');
            $custom_validation_script = $input->post->get('custom_validation_script', '', 'raw');
            $validation_message = $input->get('validation_message', '', 'string');
            $validations = $input->get('validations', [], 'array');
            $validations = is_array($validations) ? $validations : [];

            $setClauses[] = $db->quoteName('validations') . ' = ' . $db->quote(implode(',', $validations));
            $setClauses[] = $db->quoteName('custom_init_script') . ' = ' . $db->quote($custom_init_script);
            $setClauses[] = $db->quoteName('custom_action_script') . ' = ' . $db->quote($custom_action_script);
            $setClauses[] = $db->quoteName('custom_validation_script') . ' = ' . $db->quote($custom_validation_script);
            $setClauses[] = $db->quoteName('validation_message') . ' = ' . $db->quote($validation_message);

            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_elements'))
                ->set($setClauses)
                ->where($db->quoteName('id') . ' = ' . (int)$this->_element_id);
            $db->setQuery($updateQuery);
            $db->execute();
            return true;
        }
        return false;
    }

    /**
     * Publie ou dépublie plusieurs Elements.
     */
    public function publish(array $pks, int $value = 1): bool
    {
        $pks = (array) $pks;

        if (empty($pks)) {
            throw new \RuntimeException(
              Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED')
            );
        }

        ArrayHelper::toInteger($pks);
        $pks = array_filter($pks);

        Logger::info('DB publish', [
            'value' => $value,
            'pks'   => $pks,
        ]);

        $value = (int) $value;
        $db = $this->getDatabase();
        // Safely implode integer array for WHERE IN clause
        $safePks = array_map('intval', $pks);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__contentbuilderng_elements'))
            ->set($db->quoteName('published') . ' = ' . $value)
            ->where($db->quoteName('id') . ' IN (' . implode(',', $safePks) . ')');

        $db->setQuery($query);

        try {
            $db->execute();
        } catch (\Throwable $e) {
            Logger::exception($e);
            return false;
        }

        return true;
    }


    public function fieldUpdate(array $pks, string $field, int $value): bool
    {
        $pks = (array) $pks;

        if (empty($pks)) {
            throw new \RuntimeException(
              Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED')
            );
        }

        ArrayHelper::toInteger($pks);
        $pks = array_filter($pks);

        Logger::info('DB publish', [
            'value' => $value,
            'pks'   => $pks,
        ]);

        $value = (int) $value;
        $db = $this->getDatabase();

        // Safely implode integer array for WHERE IN clause
        $safePks = array_map('intval', $pks);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__contentbuilderng_elements'))
            ->set($db->quoteName($field) . ' = ' . (int) $value)
            ->where($db->quoteName('id') . ' IN (' . implode(',', $safePks) . ')');

        $db->setQuery($query);
        try {
            $db->execute();
        } catch (\Throwable $e) {
            Logger::exception($e);
            return false;
        }

        return true;
    }
}
