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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class CbfilterField extends FormField
{
    protected $type = 'Cbfilter';

    protected function getInput()
    {
        $out = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . htmlentities($this->value, ENT_QUOTES, 'UTF-8') . '"/>';
        $out .= '<div id="cbElementsWrapper">';
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if ($this->value) {
            $db->setQuery("Select * From #__contentbuilderng_elements Where published = 1 And form_id = " . intval($this->value));
            $elements = $db->loadAssocList();

            foreach ($elements as $element) {
                $out .= '<div class="mb-2"><label class="w-15">' . htmlentities($element['label'], ENT_QUOTES, 'UTF-8') . '</label> <input class="form-control w-25" style="display:inline-block;" value="" type="text" onchange="contentbuilderng_addValue(\'' . $element['reference_id'] . '\',this.value);" name="element_' . $element['reference_id'] . '" id="element_' . $element['reference_id'] . '"/>';
                $out .= ' <input class="form-control w-10" style="display: inline-block;" value="" type="text" onchange="contentbuilderng_addOrderValue(\'' . $element['reference_id'] . '\',this.value);" name="element_' . $element['reference_id'] . '_order" id="element_' . $element['reference_id'] . '_order"/></div>';
            }
        } else {
            $out .= '<br/><br/>' . Text::_('COM_CONTENTBUILDERNG_ADD_LIST_VIEW_SELECT_FORM_FIRST');
        }

        $out .= '</div>';
        $out .= '
                <script type="text/javascript">
                <!--
                var form_id = document.getElementById("jformparamsform_id").options[document.getElementById("jformparamsform_id").selectedIndex].value;
                var curr_form_id = document.getElementById("' . $this->id . '").value;
               
                document.getElementById("' . $this->id . '").value = form_id;
                
                if(curr_form_id != form_id){
                    document.getElementById("cbElementsWrapper").innerHTML = "' . addslashes(Text::_('COM_CONTENTBUILDERNG_ADD_LIST_VIEW_SELECT_FORM_FIRST')) . '";
                    document.getElementById("jform_params_cb_list_filterhidden").value = "";
                    document.getElementById("jform_params_cb_list_orderhidden").value = "";
                }

                var currval_splitted = currval.split("\n");
                for(var i = 0; i < currval_splitted.length; i++){
                    if( currval_splitted[i] != "" ){
                        var keyval = currval_splitted[i].split("\t");
                        if( keyval.length == 2 ){
                            cb_value[keyval[0]] = keyval[1];
                            if(document.getElementById("element_"+keyval[0])){
                                document.getElementById("element_"+keyval[0]).value = keyval[1];
                            }
                        }
                    }
                }
                
                var currval_order_splitted = currval_order.split("\n");
                for(var i = 0; i < currval_order_splitted.length; i++){
                    if( currval_order_splitted[i] != "" ){
                        var keyval_order = currval_order_splitted[i].split("\t");
                        if( keyval_order.length == 2 ){
                            cb_value_order[keyval_order[0]] = keyval_order[1];
                            if(document.getElementById("element_"+keyval_order[0]+"_order")){
                                document.getElementById("element_"+keyval_order[0]+"_order").value = keyval_order[1];
                            }
                        }
                    }
                }

                function contentbuilderng_setFormId(form_id){
                    document.getElementById("' . $this->id . '").value = form_id;
                    document.getElementById("cbElementsWrapper").innerHTML = "' . addslashes(Text::_('COM_CONTENTBUILDERNG_ADD_LIST_VIEW_SELECT_FORM_FIRST')) . '";
                    document.getElementById("jform_params_cb_list_filterhidden").value = "";
                    document.getElementById("jform_params_cb_list_orderhidden").value = "";
                }
                //-->
                </script>';

        return $out;
    }
}
