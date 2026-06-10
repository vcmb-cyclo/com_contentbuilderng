<?php

/**
 * ContentBuilder NG Elementoptions table.
 *
 * Table description.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Tables
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Table;

// No direct access
\defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ElementoptionsTable extends Table
{
    /**
     * Primary Key
     *
     * @var int
     */
    public $id = null;

    public $reference_id = null;

    public $type = '';
    
    public $change_type = '';

    public $options = null;

    public $custom_init_script = '';
    
    public $custom_action_script = '';
    
    public $custom_validation_script = '';
    
    public $validation_message = '';
    
    public $default_value = '';
    
    public $hint = '';
    
    /**
     * @var string
     */
    public $label = null;

    public $list_include = 1;

    public $search_include = 0;

    /**
     * @var int
     */
    public $ordering = 0;

    public $linkable = 0;

    public $editable = 0;

    public $api_allowed = 0;

    /**
     * @var int
     */
    public $published = 1;

    public $item_wrapper = '';

    public $wordwrap = 0;

    public $order_type = '';
    
    /**
     * Constructor
     *
     * @param object Database connector object
     */
    function __construct( DatabaseDriver $db ) {
        parent::__construct('#__contentbuilderng_elements', 'id', $db);


        // Joomla attend un champ "state" pour publish/unpublish au lieu de "published"
        $this->setColumnAlias('state', 'published');
    }
}
