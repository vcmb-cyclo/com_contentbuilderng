<?php

/**
 * @package     BreezingCommerce
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Element;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

class JFormFieldCbfilter extends FormField
{
    protected $type = 'Cbfilter';

    protected function getInput()
    {
        $selectedFormId = (int) ($this->form?->getValue('form_id', 'params.settings', 0) ?? 0);
        if ($selectedFormId <= 0) {
            $selectedFormId = (int) ($this->form?->getValue('form_id', 'params', 0) ?? 0);
        }
        if ($selectedFormId <= 0 && method_exists($this->form, 'getData')) {
            $data = $this->form->getData();
            if (is_object($data) && method_exists($data, 'get')) {
                $selectedFormId = (int) $data->get('params.settings.form_id', 0);
                if ($selectedFormId <= 0) {
                    $selectedFormId = (int) $data->get('params.form_id', 0);
                }
            }
        }
        if ($selectedFormId <= 0) {
            $selectedFormId = (int) $this->value;
        }

        $out = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . htmlentities($this->value, ENT_QUOTES, 'UTF-8') . '"/>';
        $wrapperId = $this->id . '_elements_wrapper';
        $out .= '<div id="' . $wrapperId . '">';
        $class = $this->element['class'] ? $this->element['class'] : "text_area";
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if ($selectedFormId > 0) {
            $db->setQuery("Select * From #__contentbuilderng_elements Where published = 1 And form_id = " . $selectedFormId);
            $elements = $db->loadAssocList();
            $i = 0;

            foreach ($elements as $element) {
                $out .= '<div class="mb-2"><label class="w-15">' . htmlentities($element['label'], ENT_QUOTES, 'UTF-8') . '</label> <input class="form-control w-25" style="display:inline-block;" value="" type="text" onchange="contentbuilderng_addValue(\'' . $element['reference_id'] . '\',this.value);" name="element_' . $element['reference_id'] . '" id="element_' . $element['reference_id'] . '"/>';
                $out .= ' <label class="ms-2 me-1" for="element_' . $element['reference_id'] . '_order">Ordre</label><input class="form-control w-10" style="display: inline-block;" value="" type="number" min="1" step="1" onchange="contentbuilderng_addOrderValue(\'' . $element['reference_id'] . '\',this.value);" name="element_' . $element['reference_id'] . '_order" id="element_' . $element['reference_id'] . '_order"/></div>';

                $i++;
            }

        } else {
            $out .= '<br/><br/>' . Text::_('COM_CONTENTBUILDERNG_ADD_LIST_VIEW_SELECT_FORM_FIRST');
        }
        $out .= '</div>';
        $out .= '
                <script type="text/javascript">
                <!--
                function contentbuilderng_findField(selectors){
                    for (var i = 0; i < selectors.length; i++) {
                        var field = document.querySelector(selectors[i]);
                        if (field) {
                            return field;
                        }
                    }

                    return null;
                }

                var formField = contentbuilderng_findField([
                    "#jform_params_settings_form_id",
                    "[name=\\"jform[params][settings][form_id]\\"]"
                ]);
                var hiddenFilterField = contentbuilderng_findField([
                    "#jform_params_settings_cb_list_filterhidden",
                    "[name=\\"jform[params][settings][cb_list_filterhidden]\\"]"
                ]);
                var hiddenOrderField = contentbuilderng_findField([
                    "#jform_params_settings_cb_list_orderhidden",
                    "[name=\\"jform[params][settings][cb_list_orderhidden]\\"]"
                ]);
                var currentFilterField = document.getElementById("' . $this->id . '");
                var wrapper = document.getElementById("' . $wrapperId . '");
                var form_id = formField ? formField.value : "";
                var curr_form_id = "' . $selectedFormId . '";

                if (currentFilterField && form_id !== "") {
                    currentFilterField.value = form_id;
                }

                if (curr_form_id !== "" && form_id !== "" && curr_form_id != form_id) {
                    if (wrapper) {
                        wrapper.innerHTML = "' . addslashes(Text::_('COM_CONTENTBUILDERNG_ADD_LIST_VIEW_SELECT_FORM_FIRST')) . '";
                    }
                    if (hiddenFilterField) {
                        hiddenFilterField.value = "";
                    }
                    if (hiddenOrderField) {
                        hiddenOrderField.value = "";
                    }
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
                    if (currentFilterField) {
                        currentFilterField.value = form_id;
                    }
                    if (wrapper) {
                        wrapper.innerHTML = "' . addslashes(Text::_('COM_CONTENTBUILDERNG_ADD_LIST_VIEW_SELECT_FORM_FIRST')) . '";
                    }
                    if (hiddenFilterField) {
                        hiddenFilterField.value = "";
                    }
                    if (hiddenOrderField) {
                        hiddenOrderField.value = "";
                    }
                }
                //-->
                </script>';
        return $out;
    }
}
