<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class UserModel extends BaseDatabaseModel
{
    private $_form_id = 0;

    private function getApp()
    {
        return Factory::getApplication();
    }

    private function getInput()
    {
        return $this->getApp()->input;
    }

    private function getCurrentFormId(): int
    {
        return (int) $this->getInput()->getInt('form_id', 0);
    }

    private function getSelectedUserIds(): array
    {
        $items = (array) $this->getInput()->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        return $items;
    }

    private function ensureContentbuilderngUserRow(int $formId, int $userId): void
    {
        if ($formId <= 0 || $userId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__contentbuilderng_users'))
            ->where($db->quoteName('form_id') . ' = ' . (int)$formId)
            ->where($db->quoteName('userid') . ' = ' . (int)$userId);
        $db->setQuery($query);
        if (!$db->loadResult()) {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_users'))
                ->columns([$db->quoteName('form_id'), $db->quoteName('userid'), $db->quoteName('published')])
                ->values((int)$formId . ', ' . (int)$userId . ', 1');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function  __construct($config)
    {
        parent::__construct($config);

        $input = $this->getInput();
        $this->setIds($input->getInt('joomla_userid', 0), $input->getInt('form_id', 0));
        
    }

    /*
     * MAIN DETAILS AREA
     */

    /**
     *
     * @param int $id
     */
    function setIds($id, $form_id) {
        // Set id and wipe data
        $this->_id = $id;
        $this->_form_id = $form_id;
        $this->_data = null;
    }

    private function _buildQuery(){
        return 'Select users.*, contentbuilderng_users.limit_edit, contentbuilderng_users.limit_add, contentbuilderng_users.id As cb_id, contentbuilderng_users.form_id, contentbuilderng_users.verification_date_edit, contentbuilderng_users.verification_date_new, contentbuilderng_users.verification_date_view, contentbuilderng_users.verified_view, contentbuilderng_users.verified_new, contentbuilderng_users.verified_edit, contentbuilderng_users.records, contentbuilderng_users.published From #__users As users Left Join #__contentbuilderng_users As contentbuilderng_users On ( users.id = contentbuilderng_users.userid And contentbuilderng_users.form_id = ' . $this->getCurrentFormId() . ' ) Where users.id = ' . $this->_id;
                
    }
    
    function setListVerifiedView()
    {
        $items = $this->getSelectedUserIds();
        if (count($items)) {
            foreach ($items as $cid) {
                $this->ensureContentbuilderngUserRow($this->getCurrentFormId(), (int) $cid);
            }
            
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_users'))
                ->set($db->quoteName('verified_view') . ' = 1')
                ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
                ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }
    
    function setListNotVerifiedView()
    {
        $items = $this->getSelectedUserIds();
        if (count($items)) {
            
            foreach ($items as $cid) {
                $this->ensureContentbuilderngUserRow($this->getCurrentFormId(), (int) $cid);
            }
            
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_users'))
                ->set($db->quoteName('verified_view') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
                ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    function setListVerifiedNew()
    {
        $items = $this->getSelectedUserIds();
        if (count($items)) {
            foreach ($items as $cid) {
                $this->ensureContentbuilderngUserRow($this->getCurrentFormId(), (int) $cid);
            }
            
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_users'))
                ->set($db->quoteName('verified_new') . ' = 1')
                ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
                ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }
    
    function setListNotVerifiedNew()
    {
        $items = $this->getSelectedUserIds();
        if (count($items)) {
            
            foreach ($items as $cid) {
                $this->ensureContentbuilderngUserRow($this->getCurrentFormId(), (int) $cid);
            }
            
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_users'))
                ->set($db->quoteName('verified_new') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
                ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }
    
    function setListVerifiedEdit()
    {
        $items = $this->getSelectedUserIds();
        if (count($items)) {
            foreach ($items as $cid) {
                $this->ensureContentbuilderngUserRow($this->getCurrentFormId(), (int) $cid);
            }
            
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_users'))
                ->set($db->quoteName('verified_edit') . ' = 1')
                ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
                ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }
    
    function setListNotVerifiedEdit()
    {
        $items = $this->getSelectedUserIds();
        if (count($items)) {
            
            foreach ($items as $cid) {
                $this->ensureContentbuilderngUserRow($this->getCurrentFormId(), (int) $cid);
            }
            
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_users'))
                ->set($db->quoteName('verified_edit') . ' = 0')
                ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
                ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }
    
    function getData()
    {
        // Lets load the data if it doesn't already exist
        if (empty( $this->_data ))
        {
            $query = $this->_buildQuery();
            $this->getDatabase()->setQuery($query);
            $this->_data = $this->getDatabase()->loadObject();
            
            if($this->_data->published === null){
                $this->_data->published = 1;
            }
            
            return $this->_data;
        }
        return null;
    }
    
    function store()
    {
        $insert = 0;
        $input = $this->getInput();
        $formId = $this->getCurrentFormId();
        $joomlaUserId = (int) $input->getInt('joomla_userid', 0);
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__contentbuilderng_users'))
            ->where($db->quoteName('form_id') . ' = ' . (int)$formId)
            ->where($db->quoteName('userid') . ' = ' . (int)$joomlaUserId);
        $db->setQuery($query);
        if(!$db->loadResult() && $formId && $joomlaUserId){
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_users'))
                ->columns([$db->quoteName('form_id'), $db->quoteName('userid'), $db->quoteName('published')])
                ->values((int)$formId . ', ' . (int)$joomlaUserId . ', 1');
            $db->setQuery($query);
            $this->getDatabase()->execute();
            $insert = $this->getDatabase()->insertid();
        }
        
        $data = $input->post->getArray();
        
        if(!$insert){
            $data['id'] = intval($data['cb_id']);
        }else{
            $data['id'] = $insert;
        }
        
        $data['userid'] = $data['joomla_userid'];
        
        
        $data['verified_view'] = $input->getInt('verified_view',0);
        $data['verified_new'] = $input->getInt('verified_new',0);
        $data['verified_edit'] = $input->getInt('verified_edit',0);
        $data['published'] = $input->getInt('published',0);
        
        $row = $this->getTable('Cbuser');
        
        if (!$row->bind($data)) {
            return false;
        }

        if (!$row->check()) {
            return false;
        }
        
        $storeRes = $row->store();

        if (!$storeRes) {
            return false;
        }
        
        return true;
    }
}
