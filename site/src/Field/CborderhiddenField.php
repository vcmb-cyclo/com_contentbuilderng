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

use Joomla\CMS\Form\FormField;

class CborderhiddenField extends FormField
{
    protected $type = 'Cborderhidden';

    protected function getInput()
    {
        $out = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . $this->value . '"/>' . "\n";
        $out .= '
                <script type="text/javascript">
                <!--
                var cb_value_order = {};
                var currval_order = "' . str_replace(["\n", "\r"], ["\\n", ""], addslashes($this->value)) . '";
                
                function contentbuilderng_addOrderValue(element_id, value){
                    cb_value_order[element_id] = value;
                    var contents = "";
                    for(var x in cb_value_order){
                        contents += x + "\t" + cb_value_order[x] + "\n";
                    }
                    document.getElementById("' . $this->id . '").value = contents;
                }
                //-->
                </script>';

        return $out;
    }
}
