<?php

/**
 * This class add dynamically configuring Abandoned Orders for payment
 * @author Codeko
 * 
 */
class Codeko_Abandonedorders_Block_Adminhtml_System_Config_Form_Fieldset extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    protected $_dummyElement;

    protected $_fieldRenderer;

    protected $_active_pm;

    protected $_values;

    protected $_values_new_payment;

    /**
     * This render fileds payment
     *
     * @see Mage_Adminhtml_Block_System_Config_Form_Fieldset::render()
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        // here you cand loop through all the fields you want to add
        // for each element you neet to call $this->_getFieldHtml($element, $group);
        $groups = Mage::getModel('payment/config')->getAllMethods();
        
        foreach ($groups as $group) {
            $fieldselect = $this->_getFieldSelectHtml($element, $group);
            $fieldtext = $this->_getFieldTextHtml($element, $group);
            $html .= $fieldselect->toHtml();
            $html .= $fieldtext->toHtml();
        }
        
        $html .= $this->_getProcessNewPaymentFieldSelectHtml($element)->toHtml();
        
        $html .= "<a href='#' id='codeko_toggle_payment'><span class='codeko_hide_payment'>" . Mage::helper('codeko_abandonedorders')->__('Hide inactive payment methods') . "</span><span class='codeko_show_payment' style='display: none;'>" . Mage::helper('codeko_abandonedorders')->__('Show inactive payment methods') . "</span></a>";
        
        $html .= $this->_getFooterHtml($element);
        
        return $html;
    }

    /**
     * This creates a dummy element so you can say if your config fields are
     * available on default and website level - you can skip this and add the
     * scope for each element in _getFieldHtml method
     *
     * @return Varien_Object
     */
    protected function _getDummyElement()
    {
        if (empty($this->_dummyElement)) {
            $this->_dummyElement = new Varien_Object(array(
                'show_in_default' => 1,
                'show_in_website' => 1,
                'show_in_store' => 1
            ));
        }
        return $this->_dummyElement;
    }

    /**
     * This sets the fields renderer.
     * If you have a custom renderer tou can change this.
     *
     * @return Ambigous <object, boolean>
     */
    protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }
        return $this->_fieldRenderer;
    }

    /**
     * This is usefull in case you need to create a config field with type
     * dropdown or multiselect.
     * For text and texareaa you can skip it.
     */
    protected function _getValuesEnabled()
    {
        if (empty($this->_values)) {
            $helper = Mage::helper("codeko_abandonedorders");
            
            $this->_values = array(
                array(
                    'label' => "",
                    'value' => $helper::PAYMENT_VALUE_EMPTY
                ),
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Ignore'),
                    'value' => $helper::PAYMENT_VALUE_NO_PROCESS
                ),
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Custom minutes with this payment method'),
                    'value' => $helper::PAYMENT_VALUE_CUSTOM_CONFIG
                ),
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Use default minutes'),
                    'value' => $helper::PAYMENT_VALUE_BASIC_CONFIG
                )
            );
        }
        return $this->_values;
    }

    /**
     * Get active payment methods
     */
    protected function _getActivePaymentMethods()
    {
        if (empty($this->_active_pm)) {
            $this->_active_pm = Mage::getSingleton('payment/config')->getActiveMethods();
        }
        return $this->_active_pm;
    }

    protected function _isPaymentMethodActive($id_payment)
    {
        $actives = $this->_getActivePaymentMethods();
        foreach ($actives as $active) {
            if ($active->getId() == $id_payment) {
                return true;
            }
        }
        return false;
    }

    /**
     * This is usefull in case you need to create a config field with type
     * dropdown or multiselect.
     * For text and texareaa you can skip it.
     */
    protected function _getValuesProcessNewPayment()
    {
        if (empty($this->_values_new_payment)) {
            $helper = Mage::helper("codeko_abandonedorders");
            
            $this->_values_new_payment = array(
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('No process'),
                    'value' => $helper::PNP_VALUE_NO_PROCCES
                ),
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Use default configuration'),
                    'value' => $helper::PNP_VALUE_DEFAULT_CONFIG
                )
            );
        }
        return $this->_values_new_payment;
    }

    /**
     * This actually gets the html for a Process new payment
     *
     * @param unknown $fieldset            
     * @param unknown $group            
     */
    protected function _getProcessNewPaymentFieldSelectHtml($fieldset)
    {
        $field_type = "select";
        $values = $this->_getValuesProcessNewPayment();
        
        $configData = $this->getConfigData();
        $path = 'codeko_abandonedorders/payments/process_new_payment'; // this value is composed by the section name, group name and field name. The field name must not be numerical (that's why I added 'group_' in front of it)
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int) (string) $this->getForm()
                ->getConfigRoot()
                ->descend($path);
            $inherit = true;
        }
        
        $e = $this->_getDummyElement(); // get the dummy element
        
        $array_field = array(
            'name' => 'groups[payments][fields][process_new_payment][value]', // this is groups[group name][fields][field name][value]
            'label' => Mage::helper('codeko_abandonedorders')->__("Process new payment"),
            'value' => $data, // this is the current value
            'values' => $values, // this is necessary if the type is select or multiselect
            'inherit' => $inherit,
            'can_use_default_value' => $this->getForm()->canUseDefaultValue($e), // sets if it can be changed on the default level
            'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e)
        ); // sets if can be changed on website level
        
        $field = $fieldset->addField("process_new_payment", $field_type, $array_field)->setRenderer($this->_getFieldRenderer());
        
        return $field;
    }

    /**
     * This actually gets the html for a field
     *
     * @param unknown $fieldset            
     * @param unknown $group            
     */
    protected function _getFieldSelectHtml($fieldset, $group)
    {
        $config = "_enabled";
        $field_type = "select";
        $values = $this->_getValuesEnabled();
        
        $configData = $this->getConfigData();
        $path = 'codeko_abandonedorders/payments/group_' . $group->getId() . $config; // this value is composed by the section name, group name and field name. The field name must not be numerical (that's why I added 'group_' in front of it)
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int) (string) $this->getForm()
                ->getConfigRoot()
                ->descend($path);
            $inherit = true;
        }
        
        $e = $this->_getDummyElement(); // get the dummy element
        
        $array_field = array(
            'name' => 'groups[payments][fields][group_' . $group->getId() . $config . '][value]', // this is groups[group name][fields][field name][value]
            'label' => Mage::helper('payment')->getMethodInstance($group->getId())
                ->getTitle(), // this is the label of the element
            'value' => $data, // this is the current value
            'values' => $values, // this is necessary if the type is select or multiselect
            'inherit' => $inherit,
            "comment" => Mage::helper('codeko_abandonedorders')->__("Enable Abandonedorder system of payment method") . " " . Mage::helper('payment')->getMethodInstance($group->getId())
                ->getTitle(),
            'can_use_default_value' => $this->getForm()->canUseDefaultValue($e), // sets if it can be changed on the default level
            'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e)
        ); // sets if can be changed on website level
        
        $class = "codeko_select_payment";
        if (!$this->_isPaymentMethodActive($group->getId())) {
            $class .= " codeko_payment_inactive";
        }
        
        $field = $fieldset->addField($group->getId() . $config, $field_type, $array_field)
            ->setRenderer($this->_getFieldRenderer())
            ->addClass($class);
        
        return $field;
    }

    /**
     * This actually gets the html for a field
     *
     * @param unknown $fieldset            
     * @param unknown $group            
     */
    protected function _getFieldTextHtml($fieldset, $group)
    {
        $config = "_minutes";
        $field_type = "text";
        $values = "";
        
        $configData = $this->getConfigData();
        $path = 'codeko_abandonedorders/payments/group_' . $group->getId() . $config; // this value is composed by the section name, group name and field name. The field name must not be numerical (that's why I added 'group_' in front of it)
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int) (string) $this->getForm()
                ->getConfigRoot()
                ->descend($path);
            $inherit = true;
        }
        
        $e = $this->_getDummyElement(); // get the dummy element
        
        $array_field = array(
            'name' => 'groups[payments][fields][group_' . $group->getId() . $config . '][value]', // this is groups[group name][fields][field name][value]
            'value' => $data, // this is the current value
            'values' => $values, // this is necessary if the type is select or multiselect
            'inherit' => $inherit,
            'class' => "validate-digits validate-digits-range digits-range-0-6000",
            'comment' => Mage::helper('codeko_abandonedorders')->__("Minutes after an order is considered abandoned and canceled"),
            'before_element_html' => "<div>hola mundo</div>",
            'can_use_default_value' => $this->getForm()->canUseDefaultValue($e), // sets if it can be changed on the default level
            'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e)
        ); // sets if can be changed on website level
        
        $class = "";
        if (!$this->_isPaymentMethodActive($group->getId())) {
            $class .= " codeko_payment_inactive";
        }
        
        $field = $fieldset->addField($group->getId() . $config, $field_type, $array_field)
            ->setRenderer($this->_getFieldRenderer())
            ->addClass($class);
        
        return $field;
    }

/**
 * Add Dependences
 *
 * @param unknown $fieldselect            
 * @param unknown $fieldtext            
 */
    // private function addDependences($fieldselect, $fieldtext)
    // {
    // $this->getForm()->setChild('form_after', $this->getForm()->getLayout()
    // ->createBlock('adminhtml/widget_form_element_dependence')
    // ->addFieldMap($fieldselect->getHtmlId(), $fieldselect->getName())
    // ->addFieldMap($fieldtext->getHtmlId(), $fieldtext->getName())
    // ->addFieldDependence($fieldtext->getName(), $fieldselect->getName(), 1));
    // }
}