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

class StorageFieldsTable extends Table
{
    /**
     * Primary Key
     *
     * @var int
     */
    public $id = null;

    public $storage_id = 0;

    public $name = '';

    public $title = '';

    public $is_group = 0;

    public $group_definition = "Label 1;value1\nLabel 2;value2\nLabel 3;value3";

    public $ordering = 0;

    public $published = 1;

    /**
     * Constructor
     *
     * @param object Database connector object
     */
    function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__contentbuilderng_storage_fields', 'id', $db);

        // Joomla attend un champ "state" pour publish/unpublish au lieu de "published"
        $this->setColumnAlias('state', 'published');
    }
}