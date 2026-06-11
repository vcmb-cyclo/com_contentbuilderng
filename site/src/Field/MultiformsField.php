<?php

/**
 * @package     BreezingCommerce
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\Database\DatabaseInterface;

class MultiformsField extends FormField
{
    protected $type = 'Multiforms';

    protected function getInput()
    {
        $class = (string) ($this->element['class'] ?: '');
        $multiple = 'multiple="multiple" ';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name']))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('name') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query);
        $status = $db->loadObjectList();

        $selectedValues = array_map('strval', (array) $this->value);
        $inputClass = trim($class . ' cb-menu-multiforms-select');
        $select = '<select id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '"'
            . ' name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '"'
            . ' ' . $multiple
            . ' onchange="if(typeof contentbuilderng_setFormId != \'undefined\') { contentbuilderng_setFormId(this.options[this.selectedIndex].value); }"'
            . ' class="' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($status as $form) {
            $value = (string) ($form->id ?? '');
            $selected = in_array($value, $selectedValues, true) ? ' selected="selected"' : '';
            $select .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars((string) ($form->name ?? ''), ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        return $select . '</select>';
    }
}
