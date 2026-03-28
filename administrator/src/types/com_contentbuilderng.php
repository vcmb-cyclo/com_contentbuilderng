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
\defined('_JEXEC') or die('Restricted access');

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;

class contentbuilderng_com_contentbuilderng
{
    public $properties = null;
    public $elements = null;
    public $view_elements = null;
    private $total = 0;
    private $bytable = false;
    private ?array $sourceColumns = null;
    private ?array $sortableElements = null;
    public $exists = false;
    public $form_id = 0;


    function __construct($id, $published = true)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->form_id = intval($id);
        $db->setQuery(
            "Select * From #__contentbuilderng_storages Where id = " . intval($id)
            . ($published ? " And published = 1" : "")
            . " Order By `ordering`"
        );
        $this->properties = $db->loadObject();
        if ($this->properties instanceof \stdClass) {
            $this->exists = true;
            $this->bytable = $this->properties->bytable == 1 ? '' : '#__';

            $db->setQuery(
                "Select * From #__contentbuilderng_storage_fields"
                . " Where storage_id = " . intval($id)
                . " And COALESCE(published, 1) = 1"
                . " Order By `ordering`"
            );
            $this->elements = $db->loadAssocList();
        }
    }

    public function synchRecords()
    {
        if (!is_object($this->properties))
            return;

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("
                Select r.id
                From 
                " . $this->bytable . $this->properties->name . " As r
                Where r.id Not In (
                    Select record_id From #__contentbuilderng_records As cr Where cr.`type` = 'com_contentbuilderng' And cr.reference_id = '" . intval($this->properties->id) . "' And cr.record_id = r.id
                ) 
        ");

        $reference_ids = $db->loadColumn();

        if (is_array($reference_ids)) {
            foreach ($reference_ids as $reference_id) {
                $db->setQuery("Select `id` From #__contentbuilderng_records Where `type` = 'com_contentbuilderng' And `reference_id` = " . intval($this->properties->id) . ' And `record_id` = ' . intval($reference_id));
                $res = $db->loadResult();
                if (!$res) {
                    $db->setQuery("Insert Into #__contentbuilderng_records (`type`,`record_id`,`reference_id`) Values ('com_contentbuilderng','" . intval($reference_id) . "', '" . intval($this->properties->id) . "')");
                    $db->execute();
                }
            }
        }
    }

    public static function getNumRecordsQuery($form_id, $user_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select `name`,`bytable` From #__contentbuilderng_storages Where id = " . intval($form_id));
        $res = $db->loadAssoc();
        $res['bytable'] = $res['bytable'] == 1 ? '' : '#__';
        if (is_array($res)) {
            return 'Select count(id) From ' . $res['bytable'] . $res['name'] . ' Where user_id = ' . intval($user_id);
        }
        return '';
    }

    public function getUniqueValues($element_id, $where_field = '', $where = '')
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery(
            "Select `name` From #__contentbuilderng_storage_fields"
            . " Where id = " . intval($element_id)
            . " And storage_id = " . intval($this->properties->id)
            . " And COALESCE(published, 1) = 1"
            . " Order By `ordering`"
        );
        $name = $db->loadResult();
        $where_add = '';
        if ($where_field != '' && $where != '') {
            $db->setQuery(
                "Select `name` From #__contentbuilderng_storage_fields"
                . " Where id = " . intval($where_field)
                . " And storage_id = " . intval($this->properties->id)
                . " And COALESCE(published, 1) = 1"
                . " Order By `ordering`"
            );
            $where_name = $db->loadResult();
            if ($where_name) {
                $where_add = " And `" . $where_name . "` = " . $db->quote($where) . " ";
            }
        }
        if ($name) {
            $db->setQuery("Select Distinct `" . $name . "` From " . $this->bytable . $this->properties->name . " Where `" . $name . "` <> '' " . $where_add . " Order By `" . $name . "`");
            return $db->loadColumn();
        }
        return array();
    }

    public function getAllElements()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery(
            "Select * From #__contentbuilderng_storage_fields"
            . " Where storage_id = " . intval($this->properties->id)
            . " And COALESCE(published, 1) = 1"
            . " Order By `ordering`"
        );
        $e = $db->loadAssocList();
        $elements = array();
        if ($e) {
            foreach ($e as $element) {
                $elements[$element['id']] = $element['name'];
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
        $db->setQuery(
            "Select * From #__contentbuilderng_storage_fields"
            . " Where storage_id = " . intval($this->properties->id)
            . " Order By `ordering`"
        );
        $rows = $db->loadAssocList() ?: [];

        $elements = [];
        foreach ($rows as $element) {
            if ((int) ($element['is_group'] ?? 0) === 1) {
                continue;
            }

            $name = (string) ($element['name'] ?? '');
            if ($name === '' || !$this->hasSourceColumn($name)) {
                continue;
            }

            $elements[] = $element;
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
        $db->setQuery(
            "Select metakey, metadesc, author, robots, rights, xreference, edited, last_update"
            . " From #__contentbuilderng_records"
            . " Where `type` = 'com_contentbuilderng'"
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
        $obj = null;
        try {
            $db->setQuery("Select * From " . $this->bytable . $this->properties->name . " Where id = " . $record_id);
            $obj = $db->loadObject();
        } catch (\Exception $e) {

        }
        $data->created_id = 0;
        $data->created = '';
        $data->created_by = '';
        $data->modified_id = 0;
        $data->modified = '';
        $data->modified_by = '';
        if ($obj) {
            $data->created_id = (int) ($obj->user_id ?? 0);
            $data->created = (string) ($obj->created ?? '');
            $data->created_by = strpos($this->bytable, '#__') !== 0 ? '' : (string) ($obj->created_by ?? '');
            $data->modified_id = (int) ($obj->modified_user_id ?? 0);
            $data->modified = (string) ($obj->modified ?? '');
            $data->modified_by = strpos($this->bytable, '#__') !== 0 ? '' : (string) ($obj->modified_by ?? '');
        }

        // Fallback: if the storage table does not track modified fields,
        // rely on CB record tracker when at least one edit occurred.
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
        if ((int) $record_id === 0) {
            $out = [];
            $i = 0;
            foreach ($this->elements as $element) {
                $out[$i] = new \stdClass();
                $out[$i]->recElementId = $element['id'];
                $out[$i]->recTitle = $element['title'];
                $out[$i]->recName = $element['name'];
                $out[$i]->recType = '';
                $out[$i]->recRating = 0;
                $out[$i]->recRatingCount = 0;
                $out[$i]->recRatingSum = 0;
                $out[$i]->recValue = '';
                $i++;
            }
            return $out;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $i = 0;
        $elSize = count($this->elements);
        $selectors = '';
        foreach ($this->elements as $element) {
            $selectors .= "r.`" . $element['name'] . "` As `col" . $element['id'] . "Value`" . ($i + 1 < $elSize ? ',' : '');
            $i++;
        }

        $db->setQuery("
            Select
                " . ($selectors ? $selectors . ',' : '') . "
                joined_records.rating_sum / joined_records.rating_count As colRating,
                joined_records.rating_count As colRatingCount,
                joined_records.rating_sum As colRatingSum
            From
                " . $this->bytable . $this->properties->name . " As r
                " . ($published_only || !$show_all_languages || $show_all_languages ? " Left Join #__contentbuilderng_records As joined_records On ( joined_records.`type` = 'com_contentbuilderng' And joined_records.record_id = r.id And joined_records.reference_id = r.storage_id ) " : "") . "
                
            Where
                r.id = " . $db->quote(intval($record_id)) . " And
                joined_records.`type` = 'com_contentbuilderng'
                " . (!$show_all_languages ? " And ( joined_records.sef = " . $db->quote(Factory::getApplication()->input->getCmd('lang', '')) . " Or joined_records.sef = '' Or joined_records.sef is Null ) " : '') . "
                " . ($show_all_languages ? " And ( joined_records.id is Null Or joined_records.id Is Not Null ) " : '') . "
                " . (intval($own_only) > -1 ? ' And r.user_id=' . intval($own_only) . ' ' : '') . "
                " . ($published_only ? " And joined_records.published = 1 " : '') . "
            And
                r.storage_id = " . $this->properties->id . "
        ");

        $out = array();
        $colValues = $db->loadAssoc();

        if ($colValues) {
            $i = 0;
            foreach ($this->elements as $element) {
                $out[$i] = new \stdClass();
                $out[$i]->recElementId = $element['id'];
                $out[$i]->recTitle = $element['title'];
                $out[$i]->recName = $element['name'];
                $out[$i]->recType = '';
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

        $selectors = '';
        $bottom = '';
        $names = array();
        $allElements = $this->getSortableElements();
        foreach ($allElements as $element) {
            // filtering the ids above, we have them already, but we need all the other fields,
            // so we can search for their values from the fontend
            if (!in_array($element['id'], $ids)) {
                $bottom .= "r.`" . $element['name'] . "` As `col" . $element['id'] . "`,";
            }
            $names[$element['id']] = $element['name'];
        }

        // we want the visible ids on top, so they will be shown as supposed, as the list view will filter out the hidden ones
        foreach ($ids as $id) {
            if (!isset($act_as_registration[$id])) {
                $selectors .= "r.`" . $names[$id] . "` As `col" . $id . "`,";
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

        $selectors = rtrim($selectors . $bottom, ',');

        ///////////////
        // preparing the search
        $strlen = 0;
        if (function_exists('mb_strlen')) {
            $strlen = mb_strlen($filter);
        } else {
            $strlen = strlen($filter);
        }

        $search = '';
        if ($strlen > 0 && $strlen <= 1000) {
            $length = count($searchable_elements);
            $search .= "( (colRecord = " . $db->quote($filter) . ") ";
            $search .= " Or ( ( r.created_by Like " . $db->quote('%' . $filter . '%') . " ) ) ";
            $search .= " Or ( ( r.modified_by Like " . $db->quote('%' . $filter . '%') . " ) ) ";
            if ($strlen > 1) {
                foreach ($searchable_elements as $searchable_element) {
                    // TODO: how to deal with terms in this?
                    if (empty($form->filter_exact_match)) {
                        $limited = explode('|', str_replace(' ', '|', $filter));
                        $limited_count = count($limited);
                        $limited_count = $limited_count > 10 ? 10 : $limited_count;
                        for ($x = 0; $x < $limited_count; $x++) {
                            $search .= " Or (Replace(`col" . intval($searchable_element) . "`,' ','') Like  " . $db->quote('%' . str_replace(' ', '', $limited[$x]) . '%') . ") ";
                        }
                    } else {
                        $search .= " Or (Replace(`col" . intval($searchable_element) . "`,' ','') Like " . $db->quote('%' . str_replace(' ', '', $filter) . '%') . ") ";
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
            $search = ' Having (' . $search . ') ';
        }
        //////////////////

        /// CASTING FOR BEING ABLE TO SORT THE WAY DEDIRED
        if (isset($order_types[$order])) {
            switch ($order_types[$order]) {
                case 'CHAR':
                    $order = " Cast(`" . $order . "` As Char) ";
                    break;
                case 'DATETIME':
                    $order = " Cast(`" . $order . "` As Datetime) ";
                    break;
                case 'DATE':
                    $order = " Cast(`" . $order . "` As Date) ";
                    break;
                case 'TIME':
                    $order = " Cast(`" . $order . "` As Time) ";
                    break;
                case 'UNSIGNED':
                    $order = " Cast(`" . $order . "` As Unsigned) ";
                    break;
                case 'DECIMAL':
                    $order = " Cast(`" . $order . "` As Decimal(64,5)) ";
                    break;
                default:
                    $order = " `" . $order . "` ";
            }
        } else if ($order) {
            $order = " `" . $order . "` ";
        }

        if (isset($order_types[$init_order_by]) && $init_order_by != -1) {
            switch ($order_types[$init_order_by]) {
                case 'CHAR':
                    $init_order_by = " Cast(`" . $init_order_by . "` As Char) ";
                    break;
                case 'DATETIME':
                    $init_order_by = " Cast(`" . $init_order_by . "` As Datetime) ";
                    break;
                case 'DATE':
                    $init_order_by = " Cast(`" . $init_order_by . "` As Date) ";
                    break;
                case 'TIME':
                    $init_order_by = " Cast(`" . $init_order_by . "` As Time) ";
                    break;
                case 'UNSIGNED':
                    $init_order_by = " Cast(`" . $init_order_by . "` As Unsigned) ";
                    break;
                case 'DECIMAL':
                    $init_order_by = " Cast(`" . $init_order_by . "` As Decimal(64,5)) ";
                    break;
                default:
                    $init_order_by = " `" . $init_order_by . "` ";
            }
        } else if ($init_order_by != -1) {
            $init_order_by = " `" . $init_order_by . "` ";
        }

        if ($init_order_by2 != -1) {
            $init_order_by2 = " `" . $init_order_by2 . "` ";
        }

        if ($init_order_by3 != -1) {
            $init_order_by3 = " `" . $init_order_by3 . "` ";
        }

        // SORT CASTING END

        $createdByExpr = $this->buildSourceColumnSelect('created_by', "''");
        $modifiedByExpr = $this->buildSourceColumnSelect('modified_by', "''");
        $createdExpr = $this->buildSourceColumnSelect('created', 'NULL');
        $modifiedExpr = $this->buildSourceColumnSelect('modified', 'NULL');

        $selectClause = "
            joined_records.published As colPublished,
            joined_records.lang_code As colLanguage,
            joined_records.rating_sum / joined_records.rating_count As colRating,
            joined_records.rating_count As colRatingCount,
            joined_records.rating_sum As colRatingSum,
            joined_records.rand_date As colRand,
            r.id As colRecord,
            " . ($selectors ? $selectors . ',' : '') . "
            joined_articles.article_id As colArticleId,
            list_states.title As colState,
            " . $createdByExpr . " As colAuthor,
            " . $modifiedByExpr . " As colModifiedBy,
            COALESCE(
                NULLIF(" . $modifiedExpr . ", '0000-00-00 00:00:00'),
                NULLIF(joined_records.last_update, '0000-00-00 00:00:00'),
                NULLIF(" . $createdExpr . ", '0000-00-00 00:00:00')
            ) As colLastModification
        ";

        $fromClause = "
            From
                (
                    " . $this->bytable . $this->properties->name . " As r,
                    #__contentbuilderng_records As joined_records
                )
                Left Join (
                    #__contentbuilderng_articles As joined_articles,
                    #__contentbuilderng_forms As forms,
                    #__content As content
                ) On (
                    joined_articles.`type` = 'com_contentbuilderng' And
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
                    list.form_id = " . (int) $this->properties->id . " And
                    list.record_id = r.id
                )
                Left Join #__contentbuilderng_list_states As list_states On (
                    list_states.id = list.state_id
                )
            Where
                " . (intval($published) == 0 ? "(joined_records.published Is Null Or joined_records.published = 0) And" : "") . "
                " . (intval($published) == 1 ? "joined_records.published = 1 And" : "") . "
                " . ($record_id ? ' r.id = ' . $db->quote($record_id) . ' And ' : '') . "
                " . ($article_category_filter > -1 ? ' content.catid = ' . intval($article_category_filter) . ' And ' : '') . "
                joined_records.reference_id = r.storage_id And
                joined_records.record_id = r.id And
                joined_records.`type` = 'com_contentbuilderng'
                " . (!$show_all_languages ? " And ( joined_records.sef = " . $db->quote(Factory::getApplication()->input->getCmd('lang', '')) . " Or joined_records.sef = '' Or joined_records.sef is Null ) " : '') . "
                " . ($show_all_languages ? " And ( joined_records.id is Null Or joined_records.id Is Not Null ) " : '') . "
                " . ($lang_code !== null ? " And joined_records.lang_code = " . $db->quote($lang_code) : '') . "
                " . (intval($own_only) > -1 ? ' And r.user_id=' . intval($own_only) . ' ' : '') . "
                " . (intval($state) > 0 ? " And list.state_id = " . intval($state) : "") . "
                " . ($published_only ? " And joined_records.published = 1 " : '') . "
            Group By r.id $search
        ";

        $validOrderKeys = ['colRecord', 'colState', 'colPublished', 'colLanguage', 'colRating', 'colArticleId', 'colAuthor', 'colLastModification'];
        $isValidInitialOrder = static function ($value) use ($validOrderKeys): bool {
            return $value === -1
                || $value === '-1'
                || $value === 0
                || $value === '0'
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
        if ($order && !isset($order_types[$order]) && !in_array($order, $validOrderKeys, true)) {
            $order = '';
        }
        $initialOrder1 = $init_order_by == -1 || $init_order_by == 0 ? 'colRecord' : $init_order_by;
        $initialOrder2 = $init_order_by2 == -1 || $init_order_by2 == 0 ? 'colRecord' : $init_order_by2;
        $initialOrder3 = $init_order_by3 == -1 || $init_order_by3 == 0 ? 'colRecord' : $init_order_by3;
        $orderDirection = $order_Dir ? (strtolower($order_Dir) == 'asc' ? 'asc' : 'desc') : 'asc';

        $orderClause = ($order
            ? " Order By " . ($order == 'colRating' && $form !== null && $form->rating_slots == 1 ? 'colRatingCount' : $order) . " "
            : ' Order By ' . $initialOrder1 . ' ' . $orderDirection . ', ' . $initialOrder2 . ' ' . $orderDirection . ', ' . $initialOrder3 . ' ' . $orderDirection . ' ') . " " . ($order ? $orderDirection : '');

        $db->setQuery("
            Select
                $selectClause
            $fromClause
            $orderClause
        ", $limitstart, $limit);

        $return = $db->loadObjectList();
        $db->setQuery("
            Select Count(*) From (
                Select
                    $selectClause
                $fromClause
            ) As counted_records
        ");
        $this->total = (int) $db->loadResult();
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
                $elements[$element['id']] = $element['title'] . ' (' . $element['name'] . ')';
            }
        }
        return $elements;
    }

    public function getElementNames()
    {
        $elements = array();
        if ($this->elements) {
            foreach ($this->elements as $element) {
                $elements[$element['id']] = $element['name'];
            }
        }
        return $elements;
    }

    public function getElementLabels()
    {
        $elements = array();
        if ($this->elements) {
            foreach ($this->elements as $element) {
                $elements[$element['id']] = $element['title'];
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
        // In administrator context, allow selecting unpublished storages too.
        $db->setQuery("Select `id`,`title`,`name` From #__contentbuilderng_storages Order By `ordering`");
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
        $db->setQuery("Select is_group From #__contentbuilderng_storage_fields Where id = " . intval($element_id));
        $result = $db->loadResult();

        if ($result) {
            return true;
        }

        return false;
    }

    public function getGroupDefinition($element_id)
    {
        $return = array();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select group_definition From #__contentbuilderng_storage_fields Where id = " . intval($element_id));
        $result = $db->loadResult();
        if ($result) {

            $result = self::execPHP($result);

            $lines = explode("\n", str_replace("\r", '', $result));
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                $cols = explode(";", $line, 2);
                if (count($cols) == 2) {
                    $label = trim((string) $cols[0]);
                    $value = trim((string) $cols[1]);
                    if ($value === '') {
                        continue;
                    }
                    $return[$value] = $label;
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
                    if ($p2 === false)
                        $p2 = $l;
                    $c .= mb_substr($code, $p1, $p2 - $p1);
                    $p1 = $p2;
                    if ($p1 < $l) {
                        $p1 += 5;
                        $p2 = mb_strpos($code, '?>', $p1);
                        if ($p2 === false)
                            $p2 = $l;
                        $n++;
                        $c .= eval (mb_substr($code, $p1, $p2 - $p1));
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
                    if ($p2 === false)
                        $p2 = $l;
                    $c .= substr($code, $p1, $p2 - $p1);
                    $p1 = $p2;
                    if ($p1 < $l) {
                        $p1 += 5;
                        $p2 = strpos($code, '?>', $p1);
                        if ($p2 === false)
                            $p2 = $l;
                        $n++;
                        $c .= eval (substr($code, $p1, $p2 - $p1));
                        $p1 = $p2 + 2;
                    } // if
                } // while
            }
        }

        return $value;
    }

    public function saveRecordUserData($record_id, $user_id, $fullname, $username)
    {
        if (intval($user_id) <= 0) {
            return;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Update " . $this->bytable . $this->properties->name . " Set user_id = " . intval($user_id) . ", created_by = " . $db->quote($fullname) . " Where id = " . $db->quote($record_id));
        $db->execute();
    }

    public function clearDirtyRecordUserData($record_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Delete From " . $this->bytable . $this->properties->name . " Where user_id = 0 And id = " . $db->quote($record_id));
        $db->execute();
    }

    public function saveRecord($record_id, array $cleaned_values)
    {
        $record_id = intval($record_id);
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $insert_id = 0;
        $identity = Factory::getApplication()->getIdentity();
        $user_id = (int) ($identity->id ?? 0);
        $username = trim((string) ($identity->username ?? ''));
        $user_full_name = trim((string) ($identity->name ?? ''));
        $names = array();

        foreach ($this->elements as $element) {
            if (isset($cleaned_values[$element['id']])) {
                $names[$element['id']] = array('name' => $element['name'], 'value' => '');
            }
        }

        $input = Factory::getApplication()->input;
        if ($input->getBool('cb_preview_ok', false)) {
            $previewActorId = (int) $input->getInt('cb_preview_actor_id', 0);
            $previewActorName = trim((string) $input->getString('cb_preview_actor_name', ''));
            if ($previewActorId > 0) {
                $user_id = $previewActorId;
            }
            if ($previewActorName !== '') {
                $user_full_name = $previewActorName;
                if ($username === '') {
                    $username = $previewActorName;
                }
            }
        }

        if ($user_full_name === '') {
            $user_full_name = $username;
        }
        if ($user_full_name === '') {
            $user_full_name = 'guest';
        }
        if ($username === '') {
            $username = $user_full_name;
        }

        $date = Factory::getDate();
        $now = $date->toSql();
        foreach ($cleaned_values as $id => $value) {
            $options = null;

            $outVal = '';

            $isGroup = $this->isGroup($id);

            if (!$isGroup) {

                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                $outVal = $value;

            } else {

                $db->setQuery("Select e.* From #__contentbuilderng_elements As e, #__contentbuilderng_forms As f Where e.reference_id = " . $db->quote($id) . " And f.reference_id = " . $db->quote($this->form_id) . " And e.form_id = f.id Order By ordering");
                $element = $db->loadAssoc();

                //$options = null;

                if (isset($element['options'])) {
                    $options = PackedDataHelper::decodePackedData($element['options'], new \stdClass());
                    if (is_array($options)) {
                        $options = (object) $options;
                    }
                    if (!is_object($options)) {
                        $options = new \stdClass();
                    }
                }

                if (!isset($options->seperator)) {

                    $options->seperator = ', ';
                }

                if (!is_array($value)) {
                    $ex = explode($options->seperator, $value);
                    $value = array();
                    foreach ($ex as $content) {
                        $value[] = trim($content);
                    }
                }
                $value = array_values(array_filter(array_map(static fn($v) => trim((string) $v), (array) $value), static fn($v) => $v !== '' && $v !== 'cbGroupMark'));

                $groupdef = $this->getGroupDefinition($id);

                foreach ($groupdef as $groupval => $grouplabel) {
                    $groupval = trim((string) $groupval);
                    if ($groupval !== '' && in_array($groupval, $value, true)) {
                        $outVal .= $groupval . $options->seperator;
                    }
                }
            }

            if (!isset($options->seperator)) {
                $outVal = rtrim($outVal);
                $names[$id]['value'] = rtrim($outVal);
            } else {
                $outVal = rtrim($outVal);
                $names[$id]['value'] = rtrim($outVal, $options->seperator);
            }
            //$outVal = rtrim($outVal);

            //$names[$id]['value'] = rtrim($outVal,$options->seperator);
        }

        if (!$record_id) {

            $the_keys = '';
            $the_values = '';
            $cnt = count($names);

            $i = 0;
            foreach ($names as $id => $keys) {
                $the_keys .= '`' . $keys['name'] . '`' . ($i + 1 < $cnt ? ',' : '');
                $the_values .= $db->quote($keys['value']) . ($i + 1 < $cnt ? ',' : '');
                $i++;
            }

            if ($the_keys) {
                $the_keys = ',' . $the_keys;
            }

            if ($the_values) {
                $the_values = ',' . $the_values;
            }

            $db->setQuery("Insert Into " . $this->bytable . $this->properties->name . " (
                `created`,
                `user_id`,
                `created_by`
                $the_keys
            ) Values (
                '" . $now . "',
                " . $db->quote($user_id) . ",
                " . $db->quote($user_full_name) . "
                $the_values
            )");
            $db->execute();
            $record_id = $db->insertid();

        } else {

            $the_values = '';
            $cnt = count($names);

            $i = 0;
            foreach ($names as $id => $keys) {
                $the_values .= '`' . $keys['name'] . '` = ' . $db->quote($keys['value']) . ($i + 1 < $cnt ? ',' : '');
                $i++;
            }

            if ($the_values) {
                $the_values = ',' . $the_values;
            }

            $db->setQuery("Update " . $this->bytable . $this->properties->name . " Set
               `modified` = '" . $now . "',
               `modified_user_id` = " . $db->quote($user_id) . ",
               `modified_by` = " . $db->quote($user_full_name) . "
               $the_values
               Where
               id = $record_id
           ");
            $db->execute();
        }

        return $record_id;
    }

    function delete($items, $form_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        ArrayHelper::toInteger($items);
        if (!is_object($this->properties) || trim((string) ($this->properties->name ?? '')) === '') {
            throw new \RuntimeException('Storage source is not available for delete action.');
        }
        $tableName = trim((string) ($this->bytable . $this->properties->name));
        if ($tableName === '') {
            throw new \RuntimeException('Storage table name is empty for delete action.');
        }
        if (count($items)) {
            $db->setQuery("Select reference_id From #__contentbuilderng_elements Where `type` = 'upload' And form_id = " . intval($form_id));
            $refs = $db->loadColumn();

            if (count($refs)) {
                $db->setQuery("Select `name` From #__contentbuilderng_storage_fields Where id In (" . implode(',', $refs) . ")");
                $names = $db->loadColumn();

                if (count($names)) {
                    $_names = '';
                    foreach ($names as $name) {
                        $_names .= "`" . $name . "`,";
                    }
                    $_names = rtrim($_names, ',');
                    if ($_names != '') {
                        $db->setQuery("Select $_names From " . $tableName . " Where id In (" . implode(',', $items) . ")");
                        $upload_fields = $db->loadAssocList();
                        $length = count($upload_fields);
                        for ($i = 0; $i < $length; $i++) {
                            foreach ($upload_fields[$i] as $_value) {
                                if (strpos(strtolower($_value), '{cbsite}') === 0) {
                                    $_value = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $_value);
                                }
                                if (file_exists($_value)) {
                                    File::delete($_value);
                                }
                            }
                        }
                    }
                }
            }
            $db->setQuery("Delete From " . $tableName . " Where id In (" . implode(',', $items) . ")");
            $db->execute();
        }
        return true;
    }

    function isOwner($user_id, $record_id)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select id From " . $this->bytable . $this->properties->name . " Where id = " . intval($record_id) . " And user_id = " . intval($user_id));
        return $db->loadResult() !== null ? true : false;
    }

    private function getSourceColumns(): array
    {
        if ($this->sourceColumns !== null) {
            return $this->sourceColumns;
        }

        $tableName = trim((string) ($this->bytable . ($this->properties->name ?? '')));
        if ($tableName === '') {
            $this->sourceColumns = [];
            return $this->sourceColumns;
        }

        try {
            $columns = Factory::getContainer()->get(DatabaseInterface::class)->getTableColumns($tableName, false);
        } catch (\Throwable $e) {
            $columns = [];
        }

        $normalized = [];
        foreach ((array) $columns as $columnName => $_definition) {
            $normalized[strtolower((string) $columnName)] = true;
        }

        $this->sourceColumns = $normalized;

        return $this->sourceColumns;
    }

    private function hasSourceColumn(string $columnName): bool
    {
        return isset($this->getSourceColumns()[strtolower($columnName)]);
    }

    private function buildSourceColumnSelect(string $columnName, string $fallbackSql): string
    {
        if ($this->hasSourceColumn($columnName)) {
            return 'r.' . $columnName;
        }

        return $fallbackSql;
    }
}
