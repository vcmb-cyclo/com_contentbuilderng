<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @copyright   Copyright © 2026 XDA+GIL 
 */

namespace CB\Component\Contentbuilderng\Administrator\types;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\Filesystem\File;
use Joomla\CMS\Environment\Browser;
use CB\Component\Contentbuilderng\Administrator\Helper\PhpTemplateHelper;

class contentbuilderng_com_breezingforms
{
    private const SYSTEM_FIELD_RECORD_ID = -1001;
    private const SYSTEM_FIELD_VIEWED = -1002;
    private const SYSTEM_FIELD_EXPORTED = -1003;
    private const SYSTEM_FIELD_ARCHIVED = -1004;
    private const SYSTEM_FIELD_DOUBLE_OPT_IN = -1005;
    private const SYSTEM_FIELD_CREATED = -1006;
    private const SYSTEM_FIELD_CREATED_BY = -1007;
    private const SYSTEM_FIELD_MODIFIED_USER_ID = -1008;
    private const SYSTEM_FIELD_MODIFIED = -1009;
    private const SYSTEM_FIELD_MODIFIED_BY = -1010;
    private const SYSTEM_FIELD_PAYMENT_TRANSACTION_ID = -1011;
    private const SYSTEM_FIELD_PAYMENT_TRANSACTION_DATE = -1012;
    private const SYSTEM_FIELD_PAYMENT_TEST_ACCOUNT = -1013;
    private const SYSTEM_FIELD_PAYMENT_DOWNLOAD_TRIES = -1014;
    private const SYSTEM_FIELD_BROWSER = -1015;
    private const SYSTEM_FIELD_SUBMITTED = -1016;
    private const SYSTEM_FIELD_OPSYS = -1017;
    private const SYSTEM_FIELD_USER_ID = -1018;
    private const SYSTEM_FIELD_USERNAME = -1019;
    private const SYSTEM_FIELD_USER_FULL_NAME = -1020;
    private const SYSTEM_FIELD_FORM_ID = -1021;
    private const SYSTEM_FIELD_FORM_TITLE = -1022;
    private const SYSTEM_FIELD_FORM_NAME = -1023;

    public $properties = null;
    public $elements = null;
    private $total = 0;
    private ?array $recordColumns = null;
    private ?array $sortableElements = null;
    public $exists = false;

    public static function getSystemFieldDefinitions(): array
    {
        return [
            self::SYSTEM_FIELD_RECORD_ID => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_RECORD_ID'),
                'name'        => 'bf_record_id',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_RECORD_ID_DESC'),
            ],
            self::SYSTEM_FIELD_VIEWED => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_VIEWED'),
                'name'        => 'bf_viewed',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_VIEWED_DESC'),
            ],
            self::SYSTEM_FIELD_EXPORTED => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_EXPORTED'),
                'name'        => 'bf_exported',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_EXPORTED_DESC'),
            ],
            self::SYSTEM_FIELD_ARCHIVED => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_ARCHIVED'),
                'name'        => 'bf_archived',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_ARCHIVED_DESC'),
            ],
            self::SYSTEM_FIELD_DOUBLE_OPT_IN => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_DOUBLE_OPT_IN'),
                'name'        => 'bf_double_opt_in',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_DOUBLE_OPT_IN_DESC'),
            ],
            self::SYSTEM_FIELD_BROWSER => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_BROWSER'),
                'name'        => 'bf_browser',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_BROWSER_DESC'),
            ],
            self::SYSTEM_FIELD_SUBMITTED => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_SUBMITTED'),
                'name'        => 'bf_submitted',
                'type'        => 'DATETIME',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_SUBMITTED_DESC'),
            ],
            self::SYSTEM_FIELD_OPSYS => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_OPSYS'),
                'name'        => 'bf_opsys',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_OPSYS_DESC'),
            ],
            self::SYSTEM_FIELD_USER_ID => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_USER_ID'),
                'name'        => 'bf_user_id',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_USER_ID_DESC'),
            ],
            self::SYSTEM_FIELD_USERNAME => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_USERNAME'),
                'name'        => 'bf_username',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_USERNAME_DESC'),
            ],
            self::SYSTEM_FIELD_USER_FULL_NAME => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_USER_FULL_NAME'),
                'name'        => 'bf_user_full_name',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_USER_FULL_NAME_DESC'),
            ],
            self::SYSTEM_FIELD_CREATED => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_CREATED'),
                'name'        => 'bf_created',
                'type'        => 'DATETIME',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_CREATED_DESC'),
            ],
            self::SYSTEM_FIELD_CREATED_BY => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_CREATED_BY'),
                'name'        => 'bf_created_by',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_CREATED_BY_DESC'),
            ],
            self::SYSTEM_FIELD_MODIFIED_USER_ID => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODIFIED_USER_ID'),
                'name'        => 'bf_modified_user_id',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODIFIED_USER_ID_DESC'),
            ],
            self::SYSTEM_FIELD_MODIFIED => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODIFIED'),
                'name'        => 'bf_modified',
                'type'        => 'DATETIME',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODIFIED_DESC'),
            ],
            self::SYSTEM_FIELD_MODIFIED_BY => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODIFIED_BY'),
                'name'        => 'bf_modified_by',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODIFIED_BY_DESC'),
            ],
            self::SYSTEM_FIELD_PAYMENT_TRANSACTION_ID => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_TRANSACTION_ID'),
                'name'        => 'bf_payment_transaction_id',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_TRANSACTION_ID_DESC'),
            ],
            self::SYSTEM_FIELD_PAYMENT_TRANSACTION_DATE => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_TRANSACTION_DATE'),
                'name'        => 'bf_payment_transaction_date',
                'type'        => 'DATETIME',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_TRANSACTION_DATE_DESC'),
            ],
            self::SYSTEM_FIELD_PAYMENT_TEST_ACCOUNT => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_TEST_ACCOUNT'),
                'name'        => 'bf_payment_test_account',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_TEST_ACCOUNT_DESC'),
            ],
            self::SYSTEM_FIELD_PAYMENT_DOWNLOAD_TRIES => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_DOWNLOAD_TRIES'),
                'name'        => 'bf_payment_download_tries',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_PAYMENT_DOWNLOAD_TRIES_DESC'),
            ],
            self::SYSTEM_FIELD_FORM_ID => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_FORM_ID'),
                'name'        => 'bf_form_id',
                'type'        => 'UNSIGNED',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_FORM_ID_DESC'),
            ],
            self::SYSTEM_FIELD_FORM_TITLE => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_FORM_TITLE'),
                'name'        => 'bf_form_title',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_FORM_TITLE_DESC'),
            ],
            self::SYSTEM_FIELD_FORM_NAME => [
                'label'       => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_FORM_NAME'),
                'name'        => 'bf_form_name',
                'type'        => 'CHAR',
                'description' => Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_FORM_NAME_DESC'),
            ],
        ];
    }

    public static function getSystemFieldLabels(): array
    {
        $labels = [];

        foreach (self::getSystemFieldDefinitions() as $referenceId => $definition) {
            $labels[(string) $referenceId] = (string) $definition['label'];
        }

        return $labels;
    }

    public static function isSystemFieldReferenceId($referenceId): bool
    {
        return isset(self::getSystemFieldDefinitions()[(int) $referenceId]);
    }

    private function getFirstRecordColumnSelect(array $columnNames, string $fallbackSql): string
    {
        foreach ($columnNames as $columnName) {
            if ($this->hasRecordColumn($columnName)) {
                return 'r.' . $columnName;
            }
        }

        return $fallbackSql;
    }

    private function getSystemFieldSelectExpression(int $referenceId): string
    {
        return match ($referenceId) {
            self::SYSTEM_FIELD_RECORD_ID => 'r.id',
            self::SYSTEM_FIELD_VIEWED => $this->buildRecordColumnSelect('viewed', '0'),
            self::SYSTEM_FIELD_EXPORTED => $this->buildRecordColumnSelect('exported', '0'),
            self::SYSTEM_FIELD_ARCHIVED => $this->buildRecordColumnSelect('archived', '0'),
            self::SYSTEM_FIELD_DOUBLE_OPT_IN => $this->getFirstRecordColumnSelect(['double_opt_in', 'double_optin', 'double_opted_in', 'opt_in', 'opted_in', 'opted', 'verified'], '0'),
            self::SYSTEM_FIELD_BROWSER => $this->buildRecordColumnSelect('browser', "''"),
            self::SYSTEM_FIELD_SUBMITTED => $this->buildRecordColumnSelect('submitted', "''"),
            self::SYSTEM_FIELD_OPSYS => $this->buildRecordColumnSelect('opsys', "''"),
            self::SYSTEM_FIELD_USER_ID => $this->buildRecordColumnSelect('user_id', '0'),
            self::SYSTEM_FIELD_USERNAME => $this->buildRecordColumnSelect('username', "''"),
            self::SYSTEM_FIELD_USER_FULL_NAME => $this->buildRecordColumnSelect('user_full_name', "''"),
            self::SYSTEM_FIELD_CREATED => $this->buildRecordColumnSelect('created', "''"),
            self::SYSTEM_FIELD_CREATED_BY => $this->buildRecordColumnSelect('created_by', "''"),
            self::SYSTEM_FIELD_MODIFIED_USER_ID => $this->buildRecordColumnSelect('modified_user_id', '0'),
            self::SYSTEM_FIELD_MODIFIED => $this->buildRecordColumnSelect('modified', 'NULL'),
            self::SYSTEM_FIELD_MODIFIED_BY => $this->buildRecordColumnSelect('modified_by', "''"),
            self::SYSTEM_FIELD_PAYMENT_TRANSACTION_ID => $this->getFirstRecordColumnSelect(['paypal_tx_id', 'paypal_transaction_id', 'transaction_id', 'payment_transaction_id'], "''"),
            self::SYSTEM_FIELD_PAYMENT_TRANSACTION_DATE => $this->getFirstRecordColumnSelect(['paypal_payment_date', 'paypal_transaction_date', 'transaction_date', 'payment_transaction_date'], "''"),
            self::SYSTEM_FIELD_PAYMENT_TEST_ACCOUNT => $this->getFirstRecordColumnSelect(['paypal_testaccount', 'paypal_test_account', 'testaccount', 'test_account', 'payment_testaccount'], '0'),
            self::SYSTEM_FIELD_PAYMENT_DOWNLOAD_TRIES => $this->getFirstRecordColumnSelect(['paypal_download_tries', 'paypal_download_attempts', 'download_tries', 'download_attempts', 'payment_download_tries'], '0'),
            self::SYSTEM_FIELD_FORM_ID => (string) (int) ($this->properties->id ?? 0),
            self::SYSTEM_FIELD_FORM_TITLE => Factory::getContainer()->get(DatabaseInterface::class)->quote((string) ($this->properties->title ?? '')),
            self::SYSTEM_FIELD_FORM_NAME => Factory::getContainer()->get(DatabaseInterface::class)->quote((string) ($this->properties->name ?? '')),
            default => "''",
        };
    }

    private function getSystemFieldValue(int $referenceId, object $record): string
    {
        $fieldValue = static function (object $row, array $names, string $default = ''): string {
            foreach ($names as $name) {
                if (property_exists($row, $name)) {
                    return (string) ($row->$name ?? $default);
                }
            }

            return $default;
        };

        return match ($referenceId) {
            self::SYSTEM_FIELD_RECORD_ID => (string) ($record->id ?? ''),
            self::SYSTEM_FIELD_VIEWED => $fieldValue($record, ['viewed'], '0'),
            self::SYSTEM_FIELD_EXPORTED => $fieldValue($record, ['exported'], '0'),
            self::SYSTEM_FIELD_ARCHIVED => $fieldValue($record, ['archived'], '0'),
            self::SYSTEM_FIELD_DOUBLE_OPT_IN => $fieldValue($record, ['double_opt_in', 'double_optin', 'double_opted_in', 'opt_in', 'opted_in', 'opted', 'verified'], '0'),
            self::SYSTEM_FIELD_BROWSER => $fieldValue($record, ['browser']),
            self::SYSTEM_FIELD_SUBMITTED => $fieldValue($record, ['submitted']),
            self::SYSTEM_FIELD_OPSYS => $fieldValue($record, ['opsys']),
            self::SYSTEM_FIELD_USER_ID => $fieldValue($record, ['user_id'], '0'),
            self::SYSTEM_FIELD_USERNAME => $fieldValue($record, ['username']),
            self::SYSTEM_FIELD_USER_FULL_NAME => $fieldValue($record, ['user_full_name']),
            self::SYSTEM_FIELD_CREATED => $fieldValue($record, ['created']),
            self::SYSTEM_FIELD_CREATED_BY => $fieldValue($record, ['created_by']),
            self::SYSTEM_FIELD_MODIFIED_USER_ID => $fieldValue($record, ['modified_user_id'], '0'),
            self::SYSTEM_FIELD_MODIFIED => $fieldValue($record, ['modified']),
            self::SYSTEM_FIELD_MODIFIED_BY => $fieldValue($record, ['modified_by']),
            self::SYSTEM_FIELD_PAYMENT_TRANSACTION_ID => $fieldValue($record, ['paypal_tx_id', 'paypal_transaction_id', 'transaction_id', 'payment_transaction_id']),
            self::SYSTEM_FIELD_PAYMENT_TRANSACTION_DATE => $fieldValue($record, ['paypal_payment_date', 'paypal_transaction_date', 'transaction_date', 'payment_transaction_date']),
            self::SYSTEM_FIELD_PAYMENT_TEST_ACCOUNT => $fieldValue($record, ['paypal_testaccount', 'paypal_test_account', 'testaccount', 'test_account', 'payment_testaccount'], '0'),
            self::SYSTEM_FIELD_PAYMENT_DOWNLOAD_TRIES => $fieldValue($record, ['paypal_download_tries', 'paypal_download_attempts', 'download_tries', 'download_attempts', 'payment_download_tries'], '0'),
            self::SYSTEM_FIELD_FORM_ID => (string) (int) ($this->properties->id ?? 0),
            self::SYSTEM_FIELD_FORM_TITLE => (string) ($this->properties->title ?? ''),
            self::SYSTEM_FIELD_FORM_NAME => (string) ($this->properties->name ?? ''),
            default => '',
        };
    }

    private function getConfiguredSystemFieldDefinitions(): array
    {
        $contentbuilderFormId = (int) Factory::getApplication()->getInput()->getInt('id', 0);

        if ($contentbuilderFormId <= 0) {
            return [];
        }

        $definitions = self::getSystemFieldDefinitions();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . $contentbuilderFormId)
            ->where($db->quoteName('reference_id') . ' < 0')
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $configuredReferenceIds = array_map('intval', (array) $db->loadColumn());

        $configured = [];
        foreach ($configuredReferenceIds as $referenceId) {
            if (isset($definitions[$referenceId])) {
                $configured[$referenceId] = $definitions[$referenceId];
            }
        }

        return $configured;
    }

    private static function normalizeGroupValueForMatch(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(
            ["\u{00A0}", "\u{2018}", "\u{2019}", "\u{201B}", "\u{2032}", "\u{0060}", "\u{00B4}"],
            [' ', "'", "'", "'", "'", "'", "'"],
            $value
        );
        $value = strtr($value, [
            "A\u{0300}" => 'À',
            "A\u{0301}" => 'Á',
            "A\u{0302}" => 'Â',
            "A\u{0308}" => 'Ä',
            "C\u{0327}" => 'Ç',
            "E\u{0300}" => 'È',
            "E\u{0301}" => 'É',
            "E\u{0302}" => 'Ê',
            "E\u{0308}" => 'Ë',
            "I\u{0300}" => 'Ì',
            "I\u{0301}" => 'Í',
            "I\u{0302}" => 'Î',
            "I\u{0308}" => 'Ï',
            "O\u{0300}" => 'Ò',
            "O\u{0301}" => 'Ó',
            "O\u{0302}" => 'Ô',
            "O\u{0308}" => 'Ö',
            "U\u{0300}" => 'Ù',
            "U\u{0301}" => 'Ú',
            "U\u{0302}" => 'Û',
            "U\u{0308}" => 'Ü',
            "Y\u{0308}" => 'Ÿ',
            "a\u{0300}" => 'à',
            "a\u{0301}" => 'á',
            "a\u{0302}" => 'â',
            "a\u{0308}" => 'ä',
            "c\u{0327}" => 'ç',
            "e\u{0300}" => 'è',
            "e\u{0301}" => 'é',
            "e\u{0302}" => 'ê',
            "e\u{0308}" => 'ë',
            "i\u{0300}" => 'ì',
            "i\u{0301}" => 'í',
            "i\u{0302}" => 'î',
            "i\u{0308}" => 'ï',
            "o\u{0300}" => 'ò',
            "o\u{0301}" => 'ó',
            "o\u{0302}" => 'ô',
            "o\u{0308}" => 'ö',
            "u\u{0300}" => 'ù',
            "u\u{0301}" => 'ú',
            "u\u{0302}" => 'û',
            "u\u{0308}" => 'ü',
            "y\u{0308}" => 'ÿ',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function getEffectiveActor(): array
    {
        $app = Factory::getApplication();
        $input = $app->getInput();
        $identity = $app->getIdentity();
        $actorId = (int) ($identity->id ?? 0);
        $actorName = trim((string) ($identity->name ?? ''));
        $actorUsername = trim((string) ($identity->username ?? ''));

        if ($input->getBool('cb_preview_ok', false)) {
            $previewActorId = (int) $input->getInt('cb_preview_actor_id', 0);
            $previewActorName = trim((string) $input->getString('cb_preview_actor_name', ''));

            if ($previewActorId > 0) {
                $actorId = $previewActorId;
            }

            if ($previewActorName !== '') {
                $actorName = $previewActorName;

                if ($actorUsername === '') {
                    $actorUsername = $previewActorName;
                }
            }
        }

        if ($actorName === '') {
            $actorName = $actorUsername;
        }

        if ($actorUsername === '') {
            $actorUsername = $actorName;
        }

        if ($actorName === '') {
            $actorName = $actorId > 0 ? 'user#' . $actorId : 'guest';
        }

        if ($actorUsername === '') {
            $actorUsername = $actorName;
        }

        return [
            'id' => $actorId,
            'name' => $actorName,
            'username' => $actorUsername,
        ];
    }

    function __construct($id, $published = true)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery("SET SESSION group_concat_max_len = 9999999");
        $db->execute();

        $formQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__facileforms_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $id)
            ->order($db->quoteName('ordering'));
        if ($published) {
            $formQuery->where($db->quoteName('published') . ' = 1');
        }
        $db->setQuery($formQuery);
        $this->properties = $db->loadObject();
        if ($this->properties instanceof \stdClass) {
            $this->exists = true;
            $excludedTypes = [
                'Sofortueberweisung', 'PayPal', 'Static Text/HTML', 'Rectangle', 'Image',
                'Tooltip', 'Query List', 'Icon', 'Graphic Button', 'Regular Button',
                'Unknown', 'Summarize', 'ReCaptcha',
            ];
            $elemQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__facileforms_elements'))
                ->where($db->quoteName('type') . ' NOT IN (' . implode(',', array_map([$db, 'quote'], $excludedTypes)) . ')')
                ->where($db->quoteName('form') . ' = ' . (int) $id)
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('logging') . ' = 1')
                ->order($db->quoteName('ordering'));
            $db->setQuery($elemQuery);
            $this->elements = $db->loadAssocList();
            $elements = array();

            $radio_buttons = array();
            foreach ($this->elements as $element) {
                if (!isset($radio_buttons[$element['name']])) {
                    $radio_buttons[$element['name']] = true;
                    $elements[] = $element;
                }
            }

            $this->elements = $elements;
        }
    }

    public function synchRecords(?int $contentbuilderngFormId = null)
    {

        if (!is_object($this->properties)) return;

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $formId = (int) $this->properties->id;
        $syncQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('r.id', 'record_id'),
                'MAX(' . $db->quoteName('f.auto_publish') . ') AS ' . $db->quoteName('auto_publish'),
            ])
            ->from($db->quoteName('#__facileforms_records', 'r'))
            ->join('INNER', $db->quoteName('#__contentbuilderng_forms', 'f') . ' ON ' . $db->quoteName('f.reference_id') . ' = ' . $db->quoteName('r.form'))
            ->join('LEFT', $db->quoteName('#__contentbuilderng_records', 'cr') . ' ON ('
                . $db->quoteName('r.form') . ' = ' . $formId . ' AND '
                . $db->quoteName('cr.type') . ' = ' . $db->quote('com_breezingforms') . ' AND '
                . $db->quoteName('cr.reference_id') . ' = ' . $db->quoteName('r.form') . ' AND '
                . $db->quoteName('cr.record_id') . ' = ' . $db->quoteName('r.id') . ')')
            ->where($db->quoteName('f.type') . ' = ' . $db->quote('com_breezingforms'))
            ->where($db->quoteName('f.reference_id') . ' = ' . $formId)
            ->where($db->quoteName('r.form') . ' = ' . $db->quoteName('f.reference_id'))
            ->where($db->quoteName('cr.record_id') . ' IS NULL')
            ->group($db->quoteName('r.id'));

        if ($contentbuilderngFormId !== null && $contentbuilderngFormId > 0) {
            $syncQuery->where($db->quoteName('f.id') . ' = ' . (int) $contentbuilderngFormId);
        }

        $db->setQuery($syncQuery);

        $records = $db->loadAssocList() ?: [];

        if ($records) {
            foreach ($records as $record) {
                $reference_id = (int) ($record['record_id'] ?? 0);
                if ($reference_id < 1) {
                    continue;
                }

                $checkQuery = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__contentbuilderng_records'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('com_breezingforms'))
                    ->where($db->quoteName('reference_id') . ' = ' . $formId)
                    ->where($db->quoteName('record_id') . ' = ' . $reference_id);
                $db->setQuery($checkQuery);
                $res = $db->loadResult();
                if (!$res) {
                    $insertQuery = $db->getQuery(true)
                        ->insert($db->quoteName('#__contentbuilderng_records'))
                        ->columns($db->quoteName(['type', 'record_id', 'reference_id', 'published']))
                        ->values(implode(',', [
                            $db->quote('com_breezingforms'),
                            $reference_id,
                            $formId,
                            !empty($record['auto_publish']) ? 1 : 0,
                        ]));
                    $db->setQuery($insertQuery);
                    $db->execute();
                }
            }
        }
    }

    public static function getNumRecordsQuery($form_id, $user_id)
    {
        return 'Select count(id) From #__facileforms_records Where form = ' . intval($form_id) . ' And user_id = ' . intval($user_id);
    }

    public function getUniqueValues($element_id, $where_field = '', $where = '')
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $formId = (int) $this->properties->id;
        $where_add = '';
        if ($where_field != '' && $where != '') {
            $whereQuery = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('s.record'))
                ->from($db->quoteName('#__facileforms_subrecords', 's'))
                ->join('INNER', $db->quoteName('#__facileforms_records', 'r') . ' ON ' . $db->quoteName('r.id') . ' = ' . $db->quoteName('s.record'))
                ->where($db->quoteName('r.form') . ' = ' . $formId)
                ->where($db->quoteName('s.element') . ' = ' . (int) $where_field)
                ->where($db->quoteName('s.value') . ' <> ' . $db->quote(''))
                ->where($db->quoteName('s.value') . ' = ' . $db->quote($where))
                ->order($db->quoteName('s.value'));
            $db->setQuery($whereQuery);
            $l = $db->loadColumn();

            if (count($l)) {
                $where_fields = implode(',', array_map([$db, 'quote'], $l));
                $where_add = $db->quoteName('r.id') . ' IN (' . $where_fields . ')';
            }
        }
        $valueQuery = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('s.value'))
            ->from($db->quoteName('#__facileforms_subrecords', 's'))
            ->join('INNER', $db->quoteName('#__facileforms_records', 'r') . ' ON ' . $db->quoteName('r.id') . ' = ' . $db->quoteName('s.record'))
            ->where($db->quoteName('r.form') . ' = ' . $formId)
            ->where($db->quoteName('s.element') . ' = ' . (int) $element_id)
            ->where($db->quoteName('s.value') . ' <> ' . $db->quote(''))
            ->order($db->quoteName('s.value'));
        if ($where_add !== '') {
            $valueQuery->where($where_add);
        }
        $db->setQuery($valueQuery);
        return $db->loadColumn();
    }

    public function getAllElements()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__facileforms_elements'))
            ->where($db->quoteName('form') . ' = ' . (int) $this->properties->id)
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $e = $db->loadAssocList();
        $elements = array();
        $fakeNames = ['bfFakeName', 'bfFakeName2', 'bfFakeName3', 'bfFakeName4', 'bfFakeName5', 'bfFakeName6'];
        if ($e) {
            foreach ($e as $element) {
                if (!in_array($element['name'], $fakeNames, true)) {
                    $elements[$element['id']] = $element['name'];
                }
            }
        }
        return $elements;
    }

    private function getSortableElements(): array
    {
        if ($this->sortableElements !== null) {
            return $this->sortableElements;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__facileforms_elements'))
            ->where($db->quoteName('form') . ' = ' . (int) $this->properties->id)
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $e = $db->loadAssocList();
        $elements = array();
        $fakeNames = ['bfFakeName', 'bfFakeName2', 'bfFakeName3', 'bfFakeName4', 'bfFakeName5', 'bfFakeName6'];
        if ($e) {
            foreach ($e as $element) {
                if (!in_array($element['name'], $fakeNames, true)) {
                    $elements[] = $element;
                }
            }
        }

        $this->sortableElements = $elements;

        return $this->sortableElements;
    }

    public function getReferenceId()
    {
        if ($this->properties) {
            return $this->properties->id;
        }
        return 0;
    }

    public function getTitle()
    {
        if ($this->properties) {
            return $this->properties->title . ' (' . $this->properties->name . ')';
        }
        return '';
    }

    public function getRecordMetadata($record_id)
    {

        $data = new \stdClass();

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $metaQuery = $db->getQuery(true)
            ->select($db->quoteName(['metakey', 'metadesc', 'author', 'robots', 'rights', 'xreference', 'edited', 'last_update']))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('com_breezingforms'))
            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($this->properties->id))
            ->where($db->quoteName('record_id') . ' = ' . $db->quote($record_id));
        $db->setQuery($metaQuery);
        $metadata = $db->loadObject();

        $data->metadesc = '';
        $data->metakey = '';
        $data->author = '';
        $data->rights = '';
        $data->robots = '';
        $data->xreference = '';
        $data->last_update = '';
        $data->edited = 0;
        if ($metadata) {
            $data->metadesc = $metadata->metadesc;
            $data->metakey = $metadata->metakey;
            $data->author = $metadata->author;
            $data->rights = $metadata->rights;
            $data->robots = $metadata->robots;
            $data->xreference = $metadata->xreference;
            $data->last_update = (string) ($metadata->last_update ?? '');
            $data->edited = (int) ($metadata->edited ?? 0);
        }

        try {
            $recordQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__facileforms_records'))
                ->where($db->quoteName('id') . ' = ' . (int) $record_id);
            $db->setQuery($recordQuery);
            $obj = $db->loadObject();
        } catch (\Exception $e) {
            $obj = null;
        }

        $data->created_id = 0;
        $data->created = '';
        $data->created_by = '';
        $data->modified_id = 0;
        $data->modified = '';
        $data->modified_by = '';
        if ($obj) {
            $createdBy = trim((string) ($obj->created_by ?? ''));
            if ($createdBy === '') {
                $legacyCreatedBy = trim((string) ($obj->user_full_name ?? ''));
                if ($legacyCreatedBy !== '-' && $legacyCreatedBy !== '') {
                    $createdBy = $legacyCreatedBy;
                }
            }

            $data->created_id = (int) ($obj->user_id ?? 0);
            $data->created = (string) (($obj->created ?? '') !== '' ? $obj->created : ($obj->submitted ?? ''));
            $data->created_by = $createdBy;
            $data->modified_id = (int) ($obj->modified_user_id ?? 0);
            $data->modified = (string) ($obj->modified ?? '');
            $data->modified_by = trim((string) ($obj->modified_by ?? ''));
        }

        // Fallback: use CB tracking information when record has been edited.
        if (
            $data->modified === ''
            && (int) $data->edited > 0
            && $data->last_update !== ''
            && $data->last_update !== '0000-00-00 00:00:00'
        ) {
            $data->modified = $data->last_update;
        }
        return $data;
    }

    public function getRecord(
        $record_id,
        $published_only = false,
        $own_only = -1,
        $show_all_languages = false
    ) {
        if (!is_object($this->properties)) return array();
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        /////////////
        // we need all elements, so they will be searchable through having
        $fakeNames = ['bfFakeName', 'bfFakeName2', 'bfFakeName3', 'bfFakeName4', 'bfFakeName5', 'bfFakeName6'];
        $elemsQuery = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'name', 'type']))
            ->from($db->quoteName('#__facileforms_elements'))
            ->where($db->quoteName('form') . ' = ' . (int) $this->properties->id)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('name') . ' NOT IN (' . implode(',', array_map([$db, 'quote'], $fakeNames)) . ')');
        $db->setQuery($elemsQuery);
        $elements = $db->loadAssocList();
        /////////////

        /////////////
        // Swapping rows to columns
        $selectors = '';
        foreach ($elements as $element) {
            if ($element['type'] == 'Radio Button' || $element['type'] == 'Checkbox') {
                $selectors .= "GROUP_CONCAT( ( Case When s.`name` = '{$element['name']}' Then s.`value` End ) Order By s.`id` SEPARATOR ', ' ) As `col{$element['id']}Value`,";
            } else {
                $selectors .= "GROUP_CONCAT( ( Case When s.`element` = '{$element['id']}' Then s.`value` End ) Order By s.`id` SEPARATOR ', ' ) As `col{$element['id']}Value`,";
            }
        }
        $selectors = rtrim($selectors, ',');
        ////////////

        $db->setQuery("
            Select
                " . ($selectors ? $selectors . ',' : '') . "
                joined_records.rating_sum / joined_records.rating_count As colRating,
                joined_records.rating_count As colRatingCount,
                joined_records.rating_sum As colRatingSum
            From
                #__facileforms_subrecords As s,
                #__facileforms_records As r
                " . ($published_only || !$show_all_languages || $show_all_languages ? " Left Join #__contentbuilderng_records As joined_records On ( joined_records.`type` = 'com_breezingforms' And joined_records.record_id = r.id And joined_records.reference_id = r.form ) " : "") . "
                
            Where
                r.id = " . $db->quote(intval($record_id)) . " And
                joined_records.`type` = 'com_breezingforms'
                " . (!$show_all_languages ? " And ( joined_records.sef = " . $db->quote(Factory::getApplication()->getInput()->getCmd('lang', '')) . " Or joined_records.sef = '' Or joined_records.sef is Null ) " : '') . "
                " . ($show_all_languages ? " And ( joined_records.id is Null Or joined_records.id Is Not Null ) " : '') . "
                " . (intval($own_only) > -1 ? ' And r.user_id=' . intval($own_only) . ' ' : '') . "
                " . ($published_only ? " And joined_records.published = 1 " : '') . "
            And
                r.form = " . $this->properties->id . "
            And
                s.record = r.id
            And
                r.archived = 0
            Group By s.record
        ");

        $out = array();
        $colValues = null;
        try {
            $colValues = $db->loadAssoc();
        } catch (\Exception $e) {
        }

        if ($colValues) {
            $i = 0;
            foreach ($elements as $element) {
                $out[$i] = new \stdClass();
                $out[$i]->recElementId = $element['id'];
                $out[$i]->recTitle = $element['title'];
                $out[$i]->recName = $element['name'];
                $out[$i]->recType = $element['type'];
                $out[$i]->recRating = $colValues['colRating'];
                $out[$i]->recRatingCount = $colValues['colRatingCount'];
                $out[$i]->recRatingSum = $colValues['colRatingSum'];
                $out[$i]->recValue = '';
                if (isset($colValues['col' . $element['id'] . 'Value'])) {
                    $out[$i]->recValue = $colValues['col' . $element['id'] . 'Value'];
                }
                $i++;
            }

            $recordQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__facileforms_records'))
                ->where($db->quoteName('id') . ' = ' . (int) $record_id)
                ->where($db->quoteName('form') . ' = ' . (int) $this->properties->id);
            $db->setQuery($recordQuery);
            $recordRow = $db->loadObject();

            if (is_object($recordRow)) {
                foreach ($this->getConfiguredSystemFieldDefinitions() as $referenceId => $definition) {
                    $out[$i] = new \stdClass();
                    $out[$i]->recElementId = (int) $referenceId;
                    $out[$i]->recTitle = (string) $definition['label'];
                    $out[$i]->recName = (string) $definition['name'];
                    $out[$i]->recType = 'System';
                    $out[$i]->recRating = $colValues['colRating'];
                    $out[$i]->recRatingCount = $colValues['colRatingCount'];
                    $out[$i]->recRatingSum = $colValues['colRatingSum'];
                    $out[$i]->recValue = $this->getSystemFieldValue((int) $referenceId, $recordRow);
                    $i++;
                }
            }
        }
        return $out;
    }

    public function getListRecords(
        array $ids,
        $filter = '',
        $searchable_elements = array(),
        $limitstart = 0,
        $limit = 0,
        $order = '',
        $order_types = array(),
        $order_Dir = 'asc',
        $record_id = 0,
        $published_only = false,
        $own_only = -1,
        $state = 0,
        $published = -1,
        $init_order_by = -1,
        $init_order_by2 = -1,
        $init_order_by3 = -1,
        $force_filter = array(),
        $show_all_languages = false,
        $lang_code = null,
        $act_as_registration = array(),
        $form = null,
        $article_category_filter = -1
    ) {

        if (!count($ids)) {
            return array();
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        /////////////
        // we need all elements, so they will be searchable through having
        $elements = $this->getSortableElements();
        /////////////

        /////////////
        // Swapping rows to columns
        $selectors = '';
        $bottom = '';
        $force = '';
        $orderExpressions = array();
        $radio_buttons = array();

        foreach ($elements as $element) {
            $colKey = 'col' . $element['id'];
            $needsSelector = !in_array($element['id'], $ids) || $order === $colKey;
            // We still need the column in SELECT when it is used for ordering.
            if ($needsSelector) {

                /// CASTING FOR BEING ABLE TO SORT THE WAY DEDIRED
                // In BreezingForms, we have to cast on selection level, since casting in order by is not allowed
                $cast_open = '';
                $cast_close = '';

                if (isset($order_types[$colKey])) {
                    switch ($order_types[$colKey]) {
                        case 'CHAR':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Char) ';
                            break;
                        case 'DATETIME':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Datetime) ';
                            break;
                        case 'DATE':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Date) ';
                            break;
                        case 'TIME':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Time) ';
                            break;
                        case 'UNSIGNED':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Unsigned) ';
                            break;
                        case 'DECIMAL':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Decimal(64,2)) ';
                            break;
                    }
                }
                if ($element['type'] == 'Checkbox' || $element['type'] == 'Checkbox Group' || $element['type'] == 'Select List') {
                    $baseExpr = "Trim( Both ', ' From GROUP_CONCAT( ( Case When s.`name` = '{$element['name']}' Then s.`value` Else '' End ) Order By s.`id` SEPARATOR ', ' ) )";
                } else {
                    $baseExpr = "max( case when s.`element` = '{$element['id']}' then s.`value` end )";
                }
                $orderExpressions[$colKey] = $cast_open . $baseExpr . $cast_close;
                $forcefield = false;
                if (isset($force_filter[$element['id']])) {
                    $forcefield = true;
                }
                if ($element['type'] == 'Checkbox' || $element['type'] == 'Checkbox Group' || $element['type'] == 'Select List') {
                    $radio_buttons[$element['id']] = $element['name'];
                    if (!$forcefield) {
                        $bottom .= $orderExpressions[$colKey] . " As `col{$element['id']}`,";
                    } else {
                        $force .= $orderExpressions[$colKey] . " As `col{$element['id']}`,";
                    }
                } else {
                    if (!$forcefield) {
                        $bottom .= $orderExpressions[$colKey] . " As `col{$element['id']}`,";
                    } else {
                        $force .= $orderExpressions[$colKey] . " As `col{$element['id']}`,";
                    }
                }
            }
        }

        // We want the visible ids on top, so they will be shown as supposed, as the list view will filter out the hidden ones
        foreach ($ids as $id) {
            $id = (int) $id;

            if (!isset($act_as_registration[$id])) {
                if (self::isSystemFieldReferenceId($id)) {
                    $systemExpression = $this->getSystemFieldSelectExpression($id);
                    $selectors .= $systemExpression . " As `col$id`,";
                    $orderExpressions['col' . $id] = $systemExpression;
                    continue;
                }

                /// CASTING FOR BEING ABLE TO SORT THE WAY DEDIRED
                // In BreezingForms, we have to cast on selection level, since casting in order by is not allowed
                $cast_open = '';
                $cast_close = '';

                if (isset($order_types['col' . $id])) {
                    switch ($order_types['col' . $id]) {
                        case 'CHAR':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Char) ';
                            break;
                        case 'DATETIME':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Datetime) ';
                            break;
                        case 'DATE':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Date) ';
                            break;
                        case 'TIME':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Time) ';
                            break;
                        case 'UNSIGNED':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Unsigned) ';
                            break;
                        case 'DECIMAL':
                            $cast_open = 'Cast(';
                            $cast_close = ' As Decimal(64,2)) ';
                            break;
                    }
                }

                $type = '';
                $name = '';
                foreach ($elements as $element) {
                    if ($element['id'] == $id) {
                        $type = $element['type'];
                        $name = $element['name'];
                        break;
                    }
                }

                if ($type == 'Checkbox' || $type == 'Checkbox Group' || $type == 'Select List') {
                    $selectors .= $cast_open . "Trim( Both ', ' From GROUP_CONCAT( ( Case When s.`name` = '$name' Then s.`value` Else '' End ) Order By s.`id` SEPARATOR ', ' ) )" . $cast_close . " As `col$id`,";
                } else {
                    $selectors .= $cast_open . "max( case when s.`element` = '" . intval($id) . "' then s.`value` end )" . $cast_close . " As `col$id`,";
                }
            } else {
                switch ($act_as_registration[$id]) {
                    case 'registration_name_field':
                        $selectors .= "joined_users.`name` As `col" . $id . "`,";
                        break;
                    case 'registration_email_field':
                        $selectors .= "joined_users.`email` As `col" . $id . "`,";
                        break;
                    case 'registration_username_field':
                        $selectors .= "joined_users.`username` As `col" . $id . "`,";
                        break;
                }
            }
        }

        $selectors = $selectors . $force . ($filter ? $bottom : '');
        $selectors = rtrim($selectors, ',');
        ////////////

        ///////////////
        // preparing the search, since we have a key/value storage, we must search by HAVING
        $strlen = 0;
        if (function_exists('mb_strlen')) {
            $strlen = mb_strlen($filter);
        } else {
            $strlen = strlen($filter);
        }

        $search = '';
        if ($filter && $strlen > 0 && $strlen <= 1000) {
            $length = count($searchable_elements);
            $search .= "( (colRecord = " . $db->quote($filter) . ") Or ";
            $search .= " ( (r.user_full_name = " . $db->quote($filter) . ") ) ";
            if ($strlen > 1) {
                foreach ($searchable_elements as $searchable_element) {
                    if (!$form->filter_exact_match) {

                        $limited = explode('|', str_replace(' ', '|', $filter));
                        $limited_count = count($limited);
                        $limited_count = $limited_count > 10 ? 10 : $limited_count;
                        for ($x = 0; $x < $limited_count; $x++) {
                            $search .= " Or (`col" . intval($searchable_element) . "` Like " . $db->quote('%' . $limited[$x] . '%') . ") ";
                        }
                    } else {
                        $search .= " Or (`col" . intval($searchable_element) . "` Like " . $db->quote('%' . $filter . '%') . ") ";
                    }
                }
            }
            $search .= ' ) ';
        }

        foreach ($force_filter as $filter_record_id => $terms) {

            if ($cnt = count($terms)) {

                if ($search) {
                    $search .= ' And ';
                }

                $search .= '( ';

                if (count($terms) == 3 && strtolower($terms[0]) == '@range') {

                    $ex = explode('to', $terms[2]);

                    switch (trim(strtolower($terms[1]))) {
                        case 'number':
                            if (count($ex) == 2) {
                                if (trim($ex[0])) {
                                    $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`),  Decimal) >= ' . $db->quote(trim($ex[0])) . ' And Convert(Trim(`col' . intval($filter_record_id) . '`), Decimal) <= ' . $db->quote(trim($ex[1])) . ')';
                                } else {
                                    $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`), Decimal) <= ' . $db->quote(trim($ex[1])) . ')';
                                }
                            } else if (count($ex) > 0) {
                                $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`),  Decimal) >= ' . $db->quote(trim($ex[0])) . ' )';
                            }
                            break;
                        case 'date':
                            if (count($ex) == 2) {

                                //if(trim($ex[0])){
                                //    $search .= '(Convert(Trim(`col'.intval($filter_record_id).'`),  Datetime) >= ' . $db->quote(trim($ex[0])) . ' And Convert(Trim(`col'.intval($filter_record_id).'`), Datetime) <= ' . $db->quote(trim($ex[1])) . ')'; 
                                //}else{
                                //    $search .= '(Convert(Trim(`col'.intval($filter_record_id).'`), Datetime) <= ' . $db->quote(trim($ex[1])) . ')'; 
                                //}

                                if (trim($ex[0])) {
                                    if ($db->quote(trim($ex[1])) == "''") {
                                        $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`), Datetime) >= ' . $db->quote(trim($ex[0])) . ')';
                                    } else {
                                        $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`), Datetime) >= ' . $db->quote(trim($ex[0])) . ' And Convert(Trim(`col' . intval($filter_record_id) . '`), Datetime) <= ' . $db->quote(trim($ex[1])) . ')';
                                    }
                                } else {
                                    $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`), Datetime) <= ' . $db->quote(trim($ex[1])) . ')';
                                }
                            } else if (count($ex) > 0) {
                                $search .= '(Convert(Trim(`col' . intval($filter_record_id) . '`),  Datetime) >= ' . $db->quote(trim($ex[0])) . ' )';
                            }
                            break;
                    }
                } else if (count($terms) == 2 && strtolower($terms[0]) == '@match') {

                    $ex = explode(';', $terms[1]);
                    $size = count($ex);
                    $i = 0;
                    foreach ($ex as $groupval) {
                        $search .= ' ( Trim(`col' . intval($filter_record_id) . '`) Like ' . $db->quote('%' . trim($groupval) . '%') . ' ) ';
                        if ($i + 1 < $size) {
                            $search .= ' Or ';
                        }
                        $i++;
                    }
                } else {
                    $i = 0;
                    foreach ($terms as $term) {
                        $search .= 'Trim(`col' . intval($filter_record_id) . '`) Like ' . $db->quote(trim($term));
                        if ($i + 1 < $cnt) {
                            $search .= ' Or ';
                        }
                        $i++;
                    }
                }

                $search .= ')';
            }
        }

        if ($search) {
            $search = ' HAVING (' . $search . ') ';
        }
        //////////////////

        $validOrderKeys = ['colRecord', 'colState', 'colPublished', 'colLanguage', 'colRating', 'colArticleId', 'colAuthor', 'colLastModification'];
        $isValidInitialOrder = static function ($value) use ($validOrderKeys): bool {
            return $value === -1
                || $value === '-1'
                || (is_string($value) && preg_match('/^col-?\d+$/', $value))
                || in_array($value, $validOrderKeys, true);
        };
        if (!$isValidInitialOrder($init_order_by)) {
            $init_order_by = -1;
        }
        if (!$isValidInitialOrder($init_order_by2)) {
            $init_order_by2 = -1;
        }
        if (!$isValidInitialOrder($init_order_by3)) {
            $init_order_by3 = -1;
        }
        $orderExpr = '';
        $orderKey = '';
        if ($order && !isset($order_types[$order]) && !in_array($order, $validOrderKeys, true)) {
            $order = '';
        }
        if ($order) {
            $orderKey = ($order === 'colRating' && $form !== null && $form->rating_slots == 1)
                ? 'colRatingCount'
                : $order;
            if (isset($orderExpressions[$orderKey])) {
                $orderExpr = $orderExpressions[$orderKey];
            } else {
                switch ($orderKey) {
                    case 'colState':
                        // Sorting by state id is more stable than the title (often NULL).
                        $orderExpr = 'COALESCE(list.state_id, 0)';
                        break;
                    case 'colPublished':
                        $orderExpr = 'joined_records.published';
                        break;
                    case 'colLanguage':
                        $orderExpr = 'joined_records.lang_code';
                        break;
                    default:
                        $orderExpr = '`' . $orderKey . '`';
                        break;
                }
            }
        }

        $orderTail = $order ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : '';
        $secondaryOrder = ($orderKey === 'colState') ? ', colRecord asc' : '';

        $cbFormId = (int) ($form->id ?? 0);
        if ($cbFormId <= 0) {
            $cbFormId = (int) $this->properties->id;
        }

        $modifiedExpr = $this->buildRecordColumnSelect('modified', 'NULL');
        $createdExpr = $this->buildRecordColumnSelect('created', 'NULL');
        $submittedExpr = $this->buildRecordColumnSelect('submitted', 'NULL');
        $initialOrder1Expr = $init_order_by == -1 || $init_order_by == 0
            ? 'colRecord'
            : (isset($orderExpressions[$init_order_by]) ? $orderExpressions[$init_order_by] : '`' . $init_order_by . '`');
        $initialOrder2Expr = $init_order_by2 == -1 || $init_order_by2 == 0
            ? 'colRecord'
            : (isset($orderExpressions[$init_order_by2]) ? $orderExpressions[$init_order_by2] : '`' . $init_order_by2 . '`');
        $initialOrder3Expr = $init_order_by3 == -1 || $init_order_by3 == 0
            ? 'colRecord'
            : (isset($orderExpressions[$init_order_by3]) ? $orderExpressions[$init_order_by3] : '`' . $init_order_by3 . '`');

        $db->setQuery("
            Select
                SQL_CALC_FOUND_ROWS
                joined_records.published As colPublished,
                joined_records.lang_code As colLanguage,
                list.state_id As colStateId,
                list_states.title As colState,
                s.record As colRecord,
                joined_records.rating_sum / joined_records.rating_count As colRating,
                joined_records.rating_count As colRatingCount,
                joined_records.rating_sum As colRatingSum,
                joined_records.rand_date As colRand,
                " . ($selectors ? $selectors . ',' : '') . "
                joined_articles.article_id As colArticleId,
                r.user_full_name As colAuthor,
                COALESCE(
                    NULLIF(" . $modifiedExpr . ", '0000-00-00 00:00:00'),
                    NULLIF(joined_records.last_update, '0000-00-00 00:00:00'),
                    NULLIF(" . $createdExpr . ", '0000-00-00 00:00:00'),
                    NULLIF(" . $submittedExpr . ", '0000-00-00 00:00:00')
                ) As colLastModification
            From
                (
                    #__facileforms_subrecords As s,
                    #__facileforms_records As r,
                    #__contentbuilderng_records As joined_records
                )
                
                Left Join (
                    #__contentbuilderng_articles As joined_articles,
                    #__contentbuilderng_forms As forms,
                    #__content As content
                ) On (
                    joined_articles.`type` = 'com_breezingforms' And
                    joined_articles.reference_id = " . $this->properties->id . " And
                    joined_records.reference_id = joined_articles.reference_id And
                    joined_records.record_id = joined_articles.record_id And
                    joined_records.`type` = joined_articles.`type` And
                    joined_articles.form_id = forms.id And
                    joined_articles.article_id = content.id And
                    (content.state = 1 Or content.state = 0)
                )
                " . (count($act_as_registration) ? '
                Left Join (
                    #__users As joined_users
                ) On (
                    r.user_id = joined_users.id
                )' : '') . "
                Left Join #__contentbuilderng_list_records As list On (
                    list.form_id = " . $cbFormId . " And
                    list.record_id = r.id
                )
                Left Join #__contentbuilderng_list_states As list_states On (
                    list_states.id = list.state_id
                )
                Where
                " . (intval($published) == 0 ? "(joined_records.published Is Null Or joined_records.published = 0) And" : "") . "
                " . (intval($published) == 1 ? "joined_records.published = 1 And" : "") . "
                " . ($record_id ? ' r.id = ' . $db->quote($record_id) . ' And ' : '') . "
                r.form = " . $this->properties->id . " And
                " . ($article_category_filter > -1 ? ' content.catid = ' . intval($article_category_filter) . ' And ' : '') . "
                joined_records.reference_id = r.form And
                joined_records.record_id = r.id And
                joined_records.`type` = 'com_breezingforms'

                " . (!$show_all_languages ? " And ( joined_records.sef = " . $db->quote(Factory::getApplication()->getInput()->getCmd('lang', '')) . " Or joined_records.sef = '' Or joined_records.sef is Null ) " : '') . "
                " . ($show_all_languages ? " And ( joined_records.id is Null Or joined_records.id Is Not Null ) " : '') . "
                " . ($lang_code !== null ? " And joined_records.lang_code = " . $db->quote($lang_code) : '') . "
                " . (intval($own_only) > -1 ? ' And r.user_id=' . intval($own_only) . ' ' : '') . "
                " . (intval($state) > 0 ? " And list.state_id = " . intval($state) : "") . "
                " . ($published_only ? " And joined_records.published = 1 " : '') . "
                
            And
                s.record = r.id
            And
                r.archived = 0
            Group By s.record $search " . ($order ? " Order By " . $orderExpr . " " : ' Order By ' . $initialOrder1Expr . ' ' . ($order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc') . ', ' . $initialOrder2Expr . ' ' . ($order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc') . ', ' . $initialOrder3Expr . ' ' . ($order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc') . ' ') . " " . $orderTail . $secondaryOrder . "
        ", $limitstart, $limit);

        try {
            $return = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            echo "<br/>" . $e->getMessage();
            exit;
        }

        $db->setQuery('SELECT FOUND_ROWS();');
        $this->total = $db->loadResult();
        return $return;
    }

    public function getListRecordsTotal(array $ids, $filter = '', $searchable_elements = array())
    {
        if (!count($ids)) {
            return 0;
        }
        return $this->total;
    }

    public function getElements()
    {
        $elements = array();
        if ($this->elements) {
            foreach ($this->elements as $element) {
                if (
                    $element['name'] != 'bfFakeName'  &&
                    $element['name'] != 'bfFakeName2' &&
                    $element['name'] != 'bfFakeName3' &&
                    $element['name'] != 'bfFakeName4' &&
                    $element['name'] != 'bfFakeName5' &&
                    $element['name'] != 'bfFakeName6'
                ) {
                    $elements[$element['id']] = $element['title'] . ' (' . $element['name'] . ')';
                }
            }
        }
        return $elements;
    }

    public function getElementNames()
    {
        $elements = array();
        if ($this->elements) {
            foreach ($this->elements as $element) {
                if (
                    $element['name'] != 'bfFakeName'  &&
                    $element['name'] != 'bfFakeName2' &&
                    $element['name'] != 'bfFakeName3' &&
                    $element['name'] != 'bfFakeName4' &&
                    $element['name'] != 'bfFakeName5' &&
                    $element['name'] != 'bfFakeName6'
                ) {
                    $elements[$element['id']] = $element['name'];
                }
            }
        }

        foreach (self::getSystemFieldDefinitions() as $referenceId => $definition) {
            $elements[(string) $referenceId] = (string) $definition['name'];
        }

        return $elements;
    }

    public function getElementLabels()
    {
        $elements = array();
        if ($this->elements) {
            foreach ($this->elements as $element) {
                if (
                    $element['name'] != 'bfFakeName'  &&
                    $element['name'] != 'bfFakeName2' &&
                    $element['name'] != 'bfFakeName3' &&
                    $element['name'] != 'bfFakeName4' &&
                    $element['name'] != 'bfFakeName5' &&
                    $element['name'] != 'bfFakeName6'
                ) {
                    $elements[$element['id']] = $element['title'];
                }
            }
        }

        foreach (self::getSystemFieldDefinitions() as $referenceId => $definition) {
            $elements[(string) $referenceId] = (string) $definition['label'];
        }

        return $elements;
    }

    public function getEditableElementTypes(): array
    {
        $types = array();

        if ($this->elements) {
            foreach ($this->elements as $element) {
                $referenceId = (string) ($element['id'] ?? '');
                if ($referenceId === '') {
                    continue;
                }

                switch ((string) ($element['type'] ?? '')) {
                    case 'Radio Group':
                        $types[$referenceId] = 'radiogroup';
                        break;
                    case 'Select List':
                        $types[$referenceId] = 'select';
                        break;
                    case 'Checkbox Group':
                        $types[$referenceId] = 'checkboxgroup';
                        break;
                    default:
                        $types[$referenceId] = 'text';
                }
            }
        }

        return $types;
    }

    public function getPageTitle()
    {
        return $this->properties->title;
    }

    public static function getFormsList()
    {
        $list = array();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'name']))
            ->from($db->quoteName('#__facileforms_forms'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $rows = $db->loadAssocList();
        foreach ($rows as $row) {
            $list[$row['id']] = $row['title'] . ' (' . $row['name'] . ')';
        }
        return $list;
    }

    /**
     *
     * NEW AS OF Content Builder
     * 
     */

    public function isGroup($element_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['type', 'flag1']))
            ->from($db->quoteName('#__facileforms_elements'))
            ->where($db->quoteName('id') . ' = ' . (int) $element_id);
        $db->setQuery($query);
        $result = $db->loadAssoc();
        if (is_array($result)) {
            switch ($result['type']) {
                case 'Radio Group':
                case 'Radio Button':
                    return true;
                    break;
                case 'Checkbox Group':
                case 'Checkbox':
                    return true;
                    break;
                case 'Select List':
                    return true;
                    break;
            }
        }

        return false;
    }

    public function getGroupDefinition($element_id)
    {
        $return = array();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q1 = $db->getQuery(true)
            ->select($db->quoteName('data2'))
            ->from($db->quoteName('#__facileforms_elements'))
            ->where($db->quoteName('type') . ' NOT IN (' . $db->quote('Radio Button') . ',' . $db->quote('Checkbox') . ')')
            ->where($db->quoteName('id') . ' = ' . (int) $element_id);
        $db->setQuery($q1);
        $result = $db->loadResult();
        if ($result) {

            $result = self::execPHP($result);

            $lines = explode("\n", str_replace("\r", '', $result));
            foreach ($lines as $line) {
                $cols = explode(";", $line);
                if (count($cols) == 3) {
                    $return[$cols[2]] = $cols[1];
                }
            }
            return $return;
        } else {
            $nameQuery = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__facileforms_elements'))
                ->where($db->quoteName('id') . ' = ' . (int) $element_id);
            $db->setQuery($nameQuery);
            $name = $db->loadResult();
            if ($name) {
                $valuesQuery = $db->getQuery(true)
                    ->select($db->quoteName('data1'))
                    ->from($db->quoteName('#__facileforms_elements'))
                    ->where($db->quoteName('type') . ' IN (' . $db->quote('Radio Button') . ',' . $db->quote('Checkbox') . ')')
                    ->where($db->quoteName('name') . ' = ' . $db->quote(trim($name)));
                $db->setQuery($valuesQuery);
                $values = $db->loadColumn();

                foreach ($values as $value) {
                    $return[$value] = '';
                }
            }

            return $return;
        }
        return array();
    }

    public static function execPhp($result): string
    {
        return PhpTemplateHelper::evaluate((string) $result);
    }

    public function saveRecordUserData($record_id, $user_id, $fullname, $username)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__facileforms_records'))
            ->set($db->quoteName('user_id') . ' = ' . (int) $user_id)
            ->set($db->quoteName('username') . ' = ' . $db->quote($username))
            ->set($db->quoteName('user_full_name') . ' = ' . $db->quote($fullname))
            ->where($db->quoteName('id') . ' = ' . $db->quote($record_id));
        $db->setQuery($query);
        $db->execute();
    }

    public function clearDirtyRecordUserData($record_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__facileforms_records'))
            ->where($db->quoteName('user_id') . ' = 0')
            ->where($db->quoteName('id') . ' = ' . $db->quote($record_id));
        $db->setQuery($query);
        $db->execute();
    }

    public function saveRecord($record_id, array $cleaned_values)
    {
        $record_id = intval($record_id);
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $insert_id = 0;
        if (!$record_id) {
            $username = '-';
            $user_full_name = '-';
            if ((int) (Factory::getApplication()->getIdentity()->id ?? 0) > 0) {
                $username = (string) (Factory::getApplication()->getIdentity()->username ?? '');
                $user_full_name = (string) (Factory::getApplication()->getIdentity()->name ?? '');
            }
            $now = Factory::getDate()->toSql();
            $actor = $this->getEffectiveActor();

            $insertQuery = $db->getQuery(true)
                ->insert($db->quoteName('#__facileforms_records'))
                ->columns($db->quoteName(['submitted', 'form', 'title', 'name', 'ip', 'browser', 'opsys', 'user_id', 'username', 'user_full_name']))
                ->values(implode(',', [
                    $db->quote($now),
                    $db->quote($this->properties->id),
                    $db->quote($this->properties->title),
                    $db->quote($this->properties->name),
                    $db->quote($_SERVER['REMOTE_ADDR']),
                    $db->quote(Browser::getInstance()->getAgentString()),
                    $db->quote(Browser::getInstance()->getPlatform()),
                    (int) $actor['id'],
                    $db->quote((string) $actor['username']),
                    $db->quote((string) $actor['name']),
                ]));
            $db->setQuery($insertQuery);
            $db->execute();
            $insert_id = $db->insertid();
        } else {
            // Keep BF audit columns in sync when an existing record is edited via CB.
            $actor = $this->getEffectiveActor();
            $modifierId = (int) $actor['id'];
            $modifierName = (string) $actor['name'];

            try {
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__facileforms_records'))
                    ->set($db->quoteName('modified_user_id') . ' = ' . $db->quote($modifierId))
                    ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                    ->set($db->quoteName('modified_by') . ' = ' . $db->quote($modifierName))
                    ->where($db->quoteName('id') . ' = ' . $db->quote($record_id));
                $db->setQuery($updateQuery);
                $db->execute();
            } catch (\Throwable $e) {
                // Backward compatibility: older BF schemas may not have these audit columns.
            }
        }
        foreach ($cleaned_values as $id => $value) {
            $isGroup = $this->isGroup($id);

            if (!is_array($value) && !$isGroup) {
                $elemInfoQuery = $db->getQuery(true)
                    ->select($db->quoteName(['title', 'name', 'type']))
                    ->from($db->quoteName('#__facileforms_elements'))
                    ->where($db->quoteName('id') . ' = ' . (int) $id);
                $db->setQuery($elemInfoQuery);
                $the_element = $db->loadAssoc();
                if ($insert_id) {
                    $subInsert = $db->getQuery(true)
                        ->insert($db->quoteName('#__facileforms_subrecords'))
                        ->columns($db->quoteName(['record', 'value', 'element', 'title', 'name', 'type']))
                        ->values(implode(',', [
                            (int) $insert_id,
                            $db->quote($value),
                            $db->quote($id),
                            $db->quote($the_element['title']),
                            $db->quote($the_element['name']),
                            $db->quote($the_element['type']),
                        ]));
                    $db->setQuery($subInsert);
                    $db->execute();
                } else {
                    $subDelete = $db->getQuery(true)
                        ->delete($db->quoteName('#__facileforms_subrecords'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote($id))
                        ->where($db->quoteName('record') . ' = ' . (int) $record_id);
                    $db->setQuery($subDelete);
                    $db->execute();
                    $subInsert = $db->getQuery(true)
                        ->insert($db->quoteName('#__facileforms_subrecords'))
                        ->columns($db->quoteName(['record', 'value', 'element', 'title', 'name', 'type']))
                        ->values(implode(',', [
                            (int) $record_id,
                            $db->quote($value),
                            $db->quote($id),
                            $db->quote($the_element['title']),
                            $db->quote($the_element['name']),
                            $db->quote($the_element['type']),
                        ]));
                    $db->setQuery($subInsert);
                    $db->execute();
                }
            } else {
                if ($insert_id) {
                    $record_id = $insert_id;
                }
                // assuming comma seperated value if defined as group but no array based group value given
                if ($isGroup && !is_array($value)) {
                    $ex = explode(',', $value);
                    $value = array();
                    foreach ($ex as $content) {
                        $value[] = trim($content);
                    }
                }
                $value = array_values(array_filter(
                    array_map(static fn($item): string => trim((string) $item), (array) $value),
                    static fn(string $item): bool => $item !== '' && $item !== 'cbGroupMark'
                ));
                $selectedGroupValues = [];
                foreach ($value as $selectedValue) {
                    $selectedGroupValues[self::normalizeGroupValueForMatch($selectedValue)] = true;
                }
                $del = array();
                $groupdef = $this->getGroupDefinition($id);
                $elemInfoQuery2 = $db->getQuery(true)
                    ->select($db->quoteName(['title', 'name', 'type']))
                    ->from($db->quoteName('#__facileforms_elements'))
                    ->where($db->quoteName('id') . ' = ' . (int) $id);
                $db->setQuery($elemInfoQuery2);
                $the_element = $db->loadAssoc();

                foreach ($groupdef as $groupval => $grouplabel) {
                    if (!isset($selectedGroupValues[self::normalizeGroupValueForMatch((string) $groupval)])) {
                        $del[] = $db->quote($groupval);
                    } else {
                        $existsQuery = $db->getQuery(true)
                            ->select($db->quoteName('id'))
                            ->from($db->quoteName('#__facileforms_subrecords'))
                            ->where($db->quoteName('value') . ' = ' . $db->quote($groupval))
                            ->where($db->quoteName('record') . ' = ' . $db->quote($record_id))
                            ->where($db->quoteName('element') . ' = ' . $db->quote($id));
                        $db->setQuery($existsQuery);
                        $exists = $db->loadResult();
                        if (!$exists) {
                            $groupInsert = $db->getQuery(true)
                                ->insert($db->quoteName('#__facileforms_subrecords'))
                                ->columns($db->quoteName(['value', 'record', 'element', 'title', 'name', 'type']))
                                ->values(implode(',', [
                                    $db->quote($groupval),
                                    $db->quote($record_id),
                                    $db->quote($id),
                                    $db->quote($the_element['title']),
                                    $db->quote($the_element['name']),
                                    $db->quote($the_element['type']),
                                ]));
                            $db->setQuery($groupInsert);
                            $db->execute();
                        }
                    }
                }
                if (count($del)) {
                    $delQuery = $db->getQuery(true)
                        ->delete($db->quoteName('#__facileforms_subrecords'))
                        ->where($db->quoteName('value') . ' IN (' . implode(',', $del) . ')')
                        ->where($db->quoteName('record') . ' = ' . $db->quote($record_id))
                        ->where($db->quoteName('element') . ' = ' . $db->quote($id));
                    $db->setQuery($delQuery);
                    $db->execute();
                }
                /**
                 * Restore the input order based on the group definition
                 */
                foreach ($groupdef as $groupval => $grouplabel) {
                    $oldIdQuery = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__facileforms_subrecords'))
                        ->where($db->quoteName('value') . ' = ' . $db->quote($groupval))
                        ->where($db->quoteName('record') . ' = ' . $db->quote($record_id))
                        ->where($db->quoteName('element') . ' = ' . $db->quote($id));
                    $db->setQuery($oldIdQuery);
                    $old_id = $db->loadResult();
                    $elemInfoQuery3 = $db->getQuery(true)
                        ->select($db->quoteName(['title', 'name', 'type']))
                        ->from($db->quoteName('#__facileforms_elements'))
                        ->where($db->quoteName('id') . ' = ' . (int) $id);
                    $db->setQuery($elemInfoQuery3);
                    $the_element = $db->loadAssoc();
                    if ($old_id) {
                        $reorderInsert = $db->getQuery(true)
                            ->insert($db->quoteName('#__facileforms_subrecords'))
                            ->columns($db->quoteName(['value', 'record', 'element', 'title', 'name', 'type']))
                            ->values(implode(',', [
                                $db->quote($groupval),
                                $db->quote($record_id),
                                $db->quote($id),
                                $db->quote($the_element['title']),
                                $db->quote($the_element['name']),
                                $db->quote($the_element['type']),
                            ]));
                        $db->setQuery($reorderInsert);
                        $db->execute();
                        $oldDelete = $db->getQuery(true)
                            ->delete($db->quoteName('#__facileforms_subrecords'))
                            ->where($db->quoteName('id') . ' = ' . (int) $old_id);
                        $db->setQuery($oldDelete);
                        $db->execute();
                    }
                }
            }
        }

        if ($insert_id) {
            return $insert_id;
        }
        return $record_id;
    }

    function delete($items, $form_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $idList = implode(',', $items);
            $delRecords = $db->getQuery(true)
                ->delete($db->quoteName('#__facileforms_records'))
                ->where($db->quoteName('id') . ' IN (' . $idList . ')');
            $db->setQuery($delRecords);
            $db->execute();
            $filesQuery = $db->getQuery(true)
                ->select($db->quoteName('value'))
                ->from($db->quoteName('#__facileforms_subrecords'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('File Upload'))
                ->where($db->quoteName('record') . ' IN (' . $idList . ')');
            $db->setQuery($filesQuery);
            $files = $db->loadColumn();

            foreach ($files as $file) {
                $_values = explode("\n", $file);
                foreach ($_values as $_value) {
                    if (strpos(strtolower($_value), '{cbsite}') === 0) {
                        $_value = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $_value);
                    }
                    if (file_exists($_value)) {
                        File::delete($_value);
                    }
                }
            }
            $delSubs = $db->getQuery(true)
                ->delete($db->quoteName('#__facileforms_subrecords'))
                ->where($db->quoteName('record') . ' IN (' . $idList . ')');
            $db->setQuery($delSubs);
            $db->execute();
        }
        return true;
    }

    function isOwner($user_id, $record_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__facileforms_records'))
            ->where($db->quoteName('id') . ' = ' . (int) $record_id)
            ->where($db->quoteName('user_id') . ' = ' . (int) $user_id);
        $db->setQuery($query);
        return $db->loadResult() !== null ? true : false;
    }

    private function getRecordColumns(): array
    {
        if ($this->recordColumns !== null) {
            return $this->recordColumns;
        }

        try {
            $columns = Factory::getContainer()->get(DatabaseInterface::class)->getTableColumns('#__facileforms_records', false);
        } catch (\Throwable $e) {
            $columns = [];
        }

        $normalized = [];
        foreach ((array) $columns as $columnName => $_definition) {
            $normalized[strtolower((string) $columnName)] = true;
        }

        $this->recordColumns = $normalized;

        return $this->recordColumns;
    }

    private function hasRecordColumn(string $columnName): bool
    {
        return isset($this->getRecordColumns()[strtolower($columnName)]);
    }

    private function buildRecordColumnSelect(string $columnName, string $fallbackSql): string
    {
        if ($this->hasRecordColumn($columnName)) {
            return 'r.' . $columnName;
        }

        return $fallbackSql;
    }
}
