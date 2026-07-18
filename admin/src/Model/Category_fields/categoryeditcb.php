<?php

/**
 * @copyright	Copyright © 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @copyright   Copyright © 2026 XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
  */

namespace CB\Component\Contentbuilderng\Administrator\Model\CategoryFields;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;

\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\ListField;
use Joomla\Database\DatabaseInterface;

class FormFieldCategoryEditCb extends ListField
{
    /**
     * A flexible category list that respects access controls
     *
     * @var		string
     * @since	1.6
     */
    public $type = 'CategoryEditCb';

    protected function getDatabase(): DatabaseInterface
    {
        return RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng')->getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Method to get a list of categories that respects access controls and can be used for
     * either category assignment or parent category assignment in edit screens.
     * Use the parent element to indicate that the field will be used for assigning parent categories.
     *
     * @return	array	The field option objects.
     * @since	1.6
     */
    protected function getOptions($startcat = 1)
    {

        if ($startcat < 1) {
            $startcat = 1;
        }

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

        $options = $db->loadObjectList();

        // Database exceptions are surfaced by the driver when query execution fails.

        // Pad the option text with spaces using depth level as a multiplier.
        for ($i = 0, $n = count($options); $i < $n; $i++) {
            // Translate ROOT
            if ($options[$i]->level == 0) {
                $options[$i]->text = Text::_('JGLOBAL_ROOT_PARENT');
            }

            $options[$i]->text = str_repeat('- ', $options[$i]->level) . $options[$i]->text;
        }

        if (isset ($row) && !isset ($options[0])) {
            if ($row->parent_id == '1') {
                $parent = new \stdClass();
                $parent->text = Text::_('JGLOBAL_ROOT_PARENT');
                array_unshift($options, $parent);
            }
        }

        return $options;
    }
}
