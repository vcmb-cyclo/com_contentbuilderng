<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Table;

// No direct access
\defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ListTable extends Table
{
    public $id = 0;
    public $type = '';
    public $reference_id = 0;
    public $name = '';
    public $title = '';
    public $created = null;
    public $modified = null;
    public $created_by = '';
    public $details_template = '';
    public $modified_by = '';
    public $print_button = 1;
    public $metadata = 1;
    public $export_xls = 0;
    public $show_id_column = 0;
    public $use_view_name_as_title = 0;
    public $intro_text = '';
    public $display_in = 0;
    public $ordering = 0;
    public $published = 0;

    /**
     * Constructor
     *
     * @param object Database connector object
     */
    function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__contentbuilderng_forms', 'id', $db);

        // Joomla attend un champ "state" pour publish/unpublish au lieu de "published"
        $this->setColumnAlias('state', 'published');
    }
}
