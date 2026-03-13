<?php

/**
 * @package     BreezingCommerce
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

class MultiformsField extends FormField
{
    protected $type = 'Multiforms';

    protected function getInput()
    {
        $class = (string) ($this->element['class'] ?: '');
        $multiple = 'multiple="multiple" ';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery("Select id,`name` From #__contentbuilderng_forms Where published = 1 Order By `ordering`");
        $status = $db->loadObjectList();

        return HTMLHelper::_(
            'select.genericlist',
            $status,
            $this->name,
            $multiple . 'style="width: 100%;" onchange="if(typeof contentbuilderng_setFormId != \'undefined\') { contentbuilderng_setFormId(this.options[this.selectedIndex].value); }" class="' . $class . '"',
            'id',
            'name',
            $this->value
        );
    }
}
