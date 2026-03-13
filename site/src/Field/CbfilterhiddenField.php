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

class CbfilterhiddenField extends FormField
{
    protected $type = 'Cbfilterhidden';

    protected function getInput()
    {
        $out = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . $this->value . '"/>' . "\n";
        $out .= '
                <script type="text/javascript">
                <!--
                var cb_value = {};
                var currval = "' . str_replace(["\n", "\r"], ["\\n", ""], addslashes($this->value)) . '";
                
                function contentbuilderng_addValue(element_id, value){
                    cb_value[element_id] = value;
                    var contents = "";
                    for(var x in cb_value){
                        contents += x + "\t" + cb_value[x] + "\n";
                    }
                    document.getElementById("' . $this->id . '").value = contents;
                }
                //-->
                </script>';

        return $out;
    }
}
