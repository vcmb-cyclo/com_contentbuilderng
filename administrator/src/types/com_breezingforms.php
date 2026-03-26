<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @copyright   Copyright © 2026 by XDA+GIL 
 */

namespace CB\Component\Contentbuilderng\Administrator\types;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\Filesystem\File;
use Joomla\CMS\Environment\Browser;

class contentbuilderng_com_breezingforms
{
    public $properties = null;
    public $elements = null;
    private $total = 0;
    private ?array $recordColumns = null;
    public $exists = false;

    private function getEffectiveActor(): array
    {
        $app = Factory::getApplication();
        $input = $app->input;
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

        $db->setQuery("Select * From #__facileforms_forms Where id = " . intval($id) . " " . ($published ? 'And published = 1' : '') . " Order By `ordering`");
        $this->properties = $db->loadObject();
        if ($this->properties instanceof \stdClass) {
            $this->exists = true;
            $db->setQuery("Select * From #__facileforms_elements Where `type` <> 'Sofortueberweisung' And `type` <> 'PayPal' And `type` <> 'Static Text/HTML' And `type` <> 'Rectangle' And `type` <> 'Image' And `type` <> 'Tooltip' And `type` <> 'Query List' And `type` <> 'Icon' And `type` <> 'Graphic Button' And `type` <> 'Regular Button' And `type`<> 'Unknown' And `type` <> 'Summarize' And `type` <> 'ReCaptcha' And form = " . intval($id) . " And published = 1 Order By `ordering`");
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

    public function synchRecords()
    {

        if (!is_object($this->properties)) return;

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select r.`id` 
                From 
                (
                    #__facileforms_records As r,
                    #__contentbuilderng_forms As f
                )
                Left Join 
                (
                    #__contentbuilderng_records As cr
                ) 
                On 
                (
                    r.form = '" . intval($this->properties->id) . "' And
                    f.`reference_id` = r.form And
                    cr.`type` = 'com_breezingforms' And
                    cr.`reference_id` = r.form And
                    cr.record_id = r.id
                )
                Where
                f.`type` = 'com_breezingforms' And
                f.`reference_id` = '" . intval($this->properties->id) . "' And
                r.form = f.`reference_id` And
                cr.`record_id` Is Null");


        $reference_ids = $db->loadColumn();

        if (is_array($reference_ids)) {
            foreach ($reference_ids as $reference_id) {
                $db->setQuery("Select `id` From #__contentbuilderng_records Where `type` = 'com_breezingforms' And `reference_id` = " . intval($this->properties->id) . ' And `record_id` = ' . intval($reference_id));
                $res = $db->loadResult();
                if (!$res) {
                    $db->setQuery("Insert Into #__contentbuilderng_records (`type`,`record_id`,`reference_id`) Values ('com_breezingforms','" . intval($reference_id) . "', '" . intval($this->properties->id) . "')");
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
        $where_add = '';
        if ($where_field != '' && $where != '') {
            $db->setQuery("Select Distinct s.`record` From #__facileforms_subrecords As s, #__facileforms_records As r Where r.form = " . $this->properties->id . " And r.id = s.record And s.`element` = " . intval($where_field) . " And s.`value` <> '' And s.`value` = " . $db->quote($where) . "  Order By s.`value`");

            $l = $db->loadColumn();

            if (count($l)) {
                $where_fields = '';
                foreach ($l as $ll) {
                    $where_fields .= $db->quote($ll) . ',';
                }
                $where_fields = rtrim($where_fields, ',');
                $where_add = " And r.`id` In (" . $where_fields . ") ";
            }
        }
        $db->setQuery("Select Distinct s.`value` From #__facileforms_subrecords As s, #__facileforms_records As r Where r.form = " . $this->properties->id . " And r.id = s.record And s.`element` = " . intval($element_id) . " And s.`value` <> '' $where_add  Order By s.`value`");
        return $db->loadColumn();
    }

    public function getAllElements()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select * From #__facileforms_elements Where form = " . intval($this->properties->id) . " And published = 1 Order By `ordering`");
        $e = $db->loadAssocList();
        $elements = array();
        if ($e) {
            foreach ($e as $element) {
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
        return $elements;
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

        $db->setQuery(
            "Select metakey, metadesc, author, robots, rights, xreference, edited, last_update"
            . " From #__contentbuilderng_records"
            . " Where `type` = 'com_breezingforms'"
            . " And reference_id = " . $db->quote($this->properties->id)
            . " And record_id = " . $db->quote($record_id)
        );
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
            $db->setQuery("Select * From #__facileforms_records Where id = " . $record_id);
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
        $db->setQuery("Select id, `title`, `name`, `type` From
                #__facileforms_elements
                Where
                form = " . $this->properties->id . "
                And
                published = 1
                And
                `name` <> 'bfFakeName'
                And
                `name` <> 'bfFakeName2'
                And
                `name` <> 'bfFakeName3'
                And
                `name` <> 'bfFakeName4'
                And
                `name` <> 'bfFakeName5'
                And
                `name` <> 'bfFakeName6'
        ");
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
                " . (!$show_all_languages ? " And ( joined_records.sef = " . $db->quote(Factory::getApplication()->input->getCmd('lang', '')) . " Or joined_records.sef = '' Or joined_records.sef is Null ) " : '') . "
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
        $db->setQuery("Select id, `type`, `name` From
                #__facileforms_elements
                Where
                form = " . $this->properties->id . "
                And
                published = 1
                And
                `name` <> 'bfFakeName'
                And
                `name` <> 'bfFakeName2'
                And
                `name` <> 'bfFakeName3'
                And
                `name` <> 'bfFakeName4'
                And
                `name` <> 'bfFakeName5'
                And
                `name` <> 'bfFakeName6'
        ");
        $elements = $db->loadAssocList();
        /////////////

        /////////////
        // Swapping rows to columns
        $selectors = '';
        $bottom = '';
        $force = '';
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
                $forcefield = false;
                if (isset($force_filter[$element['id']])) {
                    $forcefield = true;
                }
                if ($element['type'] == 'Checkbox' || $element['type'] == 'Checkbox Group' || $element['type'] == 'Select List') {
                    $radio_buttons[$element['id']] = $element['name'];
                    if (!$forcefield) {
                        $bottom .= $cast_open . "Trim( Both ', ' From GROUP_CONCAT( ( Case When s.`name` = '{$element['name']}' Then s.`value` Else '' End ) Order By s.`id` SEPARATOR ', ' ) )" . $cast_close . " As `col{$element['id']}`,";
                    } else {
                        $force .= $cast_open . "Trim( Both ', ' From GROUP_CONCAT( ( Case When s.`name` = '{$element['name']}' Then s.`value` Else '' End ) Order By s.`id` SEPARATOR ', ' ) )" . $cast_close . " As `col{$element['id']}`,";
                    }
                } else {
                    if (!$forcefield) {
                        $bottom .= $cast_open . "max( case when s.`element` = '{$element['id']}' then s.`value` end )" . $cast_close . " As `col{$element['id']}`,";
                    } else {
                        $force .= $cast_open . "max( case when s.`element` = '{$element['id']}' then s.`value` end )" . $cast_close . " As `col{$element['id']}`,";
                    }
                }
            }
        }

        // We want the visible ids on top, so they will be shown as supposed, as the list view will filter out the hidden ones
        foreach ($ids as $id) {

            if (!isset($act_as_registration[$id])) {

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
                || (is_string($value) && preg_match('/^col\d+$/', $value))
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

        $orderTail = $order ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : '';
        $secondaryOrder = ($orderKey === 'colState') ? ', colRecord asc' : '';

        $cbFormId = (int) ($form->id ?? 0);
        if ($cbFormId <= 0) {
            $cbFormId = (int) $this->properties->id;
        }

        $modifiedExpr = $this->buildRecordColumnSelect('modified', 'NULL');
        $createdExpr = $this->buildRecordColumnSelect('created', 'NULL');
        $submittedExpr = $this->buildRecordColumnSelect('submitted', 'NULL');

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

                " . (!$show_all_languages ? " And ( joined_records.sef = " . $db->quote(Factory::getApplication()->input->getCmd('lang', '')) . " Or joined_records.sef = '' Or joined_records.sef is Null ) " : '') . "
                " . ($show_all_languages ? " And ( joined_records.id is Null Or joined_records.id Is Not Null ) " : '') . "
                " . ($lang_code !== null ? " And joined_records.lang_code = " . $db->quote($lang_code) : '') . "
                " . (intval($own_only) > -1 ? ' And r.user_id=' . intval($own_only) . ' ' : '') . "
                " . (intval($state) > 0 ? " And list.state_id = " . intval($state) : "") . "
                " . ($published_only ? " And joined_records.published = 1 " : '') . "
                
            And
                s.record = r.id
            And
                r.archived = 0
            Group By s.record $search " . ($order ? " Order By " . $orderExpr . " " : ' Order By ' . (($init_order_by == -1 || $init_order_by == 0) ? 'colRecord' : "`" . $init_order_by . "`") . ' ' . ($order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc') . ', ' . (($init_order_by2 == -1 || $init_order_by2 == 0) ? 'colRecord' : "`" . $init_order_by2 . "`") . ' ' . ($order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc') . ', ' . (($init_order_by3 == -1 || $init_order_by3 == 0) ? 'colRecord' : "`" . $init_order_by3 . "`") . ' ' . ($order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc') . ' ') . " " . $orderTail . $secondaryOrder . "
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
        return $elements;
    }

    public function getPageTitle()
    {
        return $this->properties->title;
    }

    public static function getFormsList()
    {
        $list = array();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select `id`,`title`,`name` From #__facileforms_forms Where published = 1 Order By `ordering`");
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
        $db->setQuery("Select `type`, `flag1` From #__facileforms_elements Where id = " . intval($element_id));
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
        $db->setQuery("Select data2 From #__facileforms_elements Where `type` Not In ('Radio Button', 'Checkbox') And id = " . intval($element_id));
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
            $db->setQuery("Select `name` From #__facileforms_elements Where id = " . intval($element_id));
            $name = $db->loadResult();
            if ($name) {
                $db->setQuery("Select `data1` From #__facileforms_elements Where `type` In ('Radio Button', 'Checkbox') And name = " . $db->quote(trim($name)));
                $values = $db->loadColumn();

                foreach ($values as $value) {
                    $return[$value] = '';
                }
            }

            return $return;
        }
        return array();
    }

    public static function execPhp($result)
    {
        $value = $result;
        if (strpos(trim($result), '<?php') === 0) {

            $code = trim($result);

            if (function_exists('mb_strlen')) {
                $p1 = 0;
                $l = mb_strlen($code);
                $c = '';
                $n = 0;
                while ($p1 < $l) {
                    $p2 = mb_strpos($code, '<?php', $p1);
                    if ($p2 === false) $p2 = $l;
                    $c .= mb_substr($code, $p1, $p2 - $p1);
                    $p1 = $p2;
                    if ($p1 < $l) {
                        $p1 += 5;
                        $p2 = mb_strpos($code, '?>', $p1);
                        if ($p2 === false) $p2 = $l;
                        $n++;
                        $c .= eval(mb_substr($code, $p1, $p2 - $p1));
                        $p1 = $p2 + 2;
                    } // if
                } // while
            } else {
                $p1 = 0;
                $l = strlen($code);
                $c = '';
                $n = 0;
                while ($p1 < $l) {
                    $p2 = strpos($code, '<?php', $p1);
                    if ($p2 === false) $p2 = $l;
                    $c .= substr($code, $p1, $p2 - $p1);
                    $p1 = $p2;
                    if ($p1 < $l) {
                        $p1 += 5;
                        $p2 = strpos($code, '?>', $p1);
                        if ($p2 === false) $p2 = $l;
                        $n++;
                        $c .= eval(substr($code, $p1, $p2 - $p1));
                        $p1 = $p2 + 2;
                    } // if
                } // while
            }
        }

        return $value;
    }

    public function saveRecordUserData($record_id, $user_id, $fullname, $username)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Update #__facileforms_records Set user_id = " . intval($user_id) . ", username = " . $db->quote($username) . ", user_full_name = " . $db->quote($fullname) . " Where id = " . $db->quote($record_id));
        $db->execute();
    }

    public function clearDirtyRecordUserData($record_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Delete From #__facileforms_records Where user_id = 0 And id = " . $db->quote($record_id));
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

            $db->setQuery("Insert Into #__facileforms_records (
                `submitted`,
                `form`,
                `title`,
                `name`,
                `ip`,
                `browser`,
                `opsys`,
                `user_id`,
                `username`,
                `user_full_name`
            ) Values (
                '" . $now . "',
                " . $db->quote($this->properties->id) . ",
                " . $db->quote($this->properties->title) . ",
                " . $db->quote($this->properties->name) . ",
                " . $db->quote($_SERVER['REMOTE_ADDR']) . ",
                " . $db->quote(Browser::getInstance()->getAgentString()) . ",
                " . $db->quote(Browser::getInstance()->getPlatform()) . ",
                " . $db->quote((int) $actor['id']) . ",
                " . $db->quote((string) $actor['username']) . ",
                " . $db->quote((string) $actor['name']) . "
            )");
            $db->execute();
            $insert_id = $db->insertid();
        } else {
            // Keep BF audit columns in sync when an existing record is edited via CB.
            $actor = $this->getEffectiveActor();
            $modifierId = (int) $actor['id'];
            $modifierName = (string) $actor['name'];

            try {
                $db->setQuery(
                    "UPDATE #__facileforms_records
                     SET modified_user_id = " . $db->quote($modifierId) . ",
                         modified = " . $db->quote(Factory::getDate()->toSql()) . ",
                         modified_by = " . $db->quote($modifierName) . "
                     WHERE id = " . $db->quote($record_id)
                );
                $db->execute();
            } catch (\Throwable $e) {
                // Backward compatibility: older BF schemas may not have these audit columns.
            }
        }
        foreach ($cleaned_values as $id => $value) {
            $isGroup = $this->isGroup($id);

            if (!is_array($value) && !$isGroup) {
                if ($insert_id) {
                    $db->setQuery("Select `title`,`name`,`type` From #__facileforms_elements Where id = " . intval($id));
                    $the_element = $db->loadAssoc();
                    $db->setQuery(
                        "Insert Into #__facileforms_subrecords
                        (
                            `record`,
                            `value`,
                            `element`,
                            `title`,
                            `name`,
                            `type`
                        )
                        Values
                        (
                            $insert_id,
                            " . $db->quote($value) . ",
                            " . $db->quote($id) . ",
                            " . $db->quote($the_element['title']) . ",
                            " . $db->quote($the_element['name']) . ",
                            " . $db->quote($the_element['type']) . "
                        )"
                    );
                    $db->execute();
                } else {
                    $db->setQuery("
                        Delete From 
                            #__facileforms_subrecords
                        Where
                            element = " . $db->quote($id) . "
                        And
                            record = " . $db->quote(intval($record_id)) . "
                    ");
                    $db->execute();
                    $db->setQuery("Select `title`,`name`,`type` From #__facileforms_elements Where id = " . intval($id));
                    $the_element = $db->loadAssoc();
                    $db->setQuery(
                        "Insert Into #__facileforms_subrecords
                        (
                            `record`,
                            `value`,
                            `element`,
                            `title`,
                            `name`,
                            `type`
                        )
                        Values
                        (
                            " . $db->quote(intval($record_id)) . ",
                            " . $db->quote($value) . ",
                            " . $db->quote($id) . ",
                            " . $db->quote($the_element['title']) . ",
                            " . $db->quote($the_element['name']) . ",
                            " . $db->quote($the_element['type']) . "
                        )"
                    );
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
                $del = array();
                $groupdef = $this->getGroupDefinition($id);
                $db->setQuery("Select `title`,`name`,`type` From #__facileforms_elements Where id = " . intval($id));
                $the_element = $db->loadAssoc();

                foreach ($groupdef as $groupval => $grouplabel) {
                    if (!in_array($groupval, $value)) {
                        $del[] = $db->quote($groupval);
                    } else {
                        $db->setQuery("Select id From #__facileforms_subrecords Where `value` = " . $db->quote($groupval) . " And record = " . $db->quote($record_id) . " And element = " . $db->quote($id));
                        $exists = $db->loadResult();
                        if (!$exists) {
                            $db->setQuery("Insert Into #__facileforms_subrecords (`value`, record, element, `title`, `name`, `type`) Values (" . $db->quote($groupval) . "," . $db->quote($record_id) . "," . $db->quote($id) . "," . $db->quote($the_element['title']) . "," . $db->quote($the_element['name']) . "," . $db->quote($the_element['type']) . ")");
                            $db->execute();
                        }
                    }
                }
                if (count($del)) {
                    $db->setQuery("Delete From #__facileforms_subrecords Where `value` In (" . implode(',', $del) . ") And record = " . $db->quote($record_id) . " And element = " . $db->quote($id));
                    $db->execute();
                }
                /**
                 * Restore the input order based on the group definition
                 */
                foreach ($groupdef as $groupval => $grouplabel) {
                    $db->setQuery("Select id From #__facileforms_subrecords Where `value` = " . $db->quote($groupval) . " And record = " . $db->quote($record_id) . " And element = " . $db->quote($id));
                    $old_id = $db->loadResult();
                    $db->setQuery("Select `title`,`name`,`type` From #__facileforms_elements Where id = " . intval($id));
                    $the_element = $db->loadAssoc();
                    if ($old_id) {
                        $db->setQuery("Insert Into #__facileforms_subrecords (`value`, record, element, `title`, `name`, `type`) Values (" . $db->quote($groupval) . "," . $db->quote($record_id) . "," . $db->quote($id) . "," . $db->quote($the_element['title']) . "," . $db->quote($the_element['name']) . "," . $db->quote($the_element['type']) . ")");
                        $db->execute();
                        $db->setQuery("Delete From #__facileforms_subrecords Where id = " . $old_id);
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
            $db->setQuery("Delete From #__facileforms_records Where id In (" . implode(',', $items) . ")");
            $db->execute();
            $db->setQuery("Select `value` From #__facileforms_subrecords Where `type` = 'File Upload' And record In (" . implode(',', $items) . ")");
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
            $db->setQuery("Delete From #__facileforms_subrecords Where record In (" . implode(',', $items) . ")");
            $db->execute();
        }
        return true;
    }

    function isOwner($user_id, $record_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select id From #__facileforms_records Where id = " . intval($record_id) . " And user_id = " . intval($user_id));
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
