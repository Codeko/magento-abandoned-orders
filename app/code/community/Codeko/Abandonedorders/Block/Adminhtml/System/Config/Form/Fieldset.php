<?php

/**
 * This class add configuration fields for each payment method 
 * @author Codeko
 * 
 */
class Codeko_Abandonedorders_Block_Adminhtml_System_Config_Form_Fieldset 
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    protected $_dummyElement;

    protected $_fieldRenderer;

    protected $_active_pm;

    protected $_values;

    protected $_values_new_payment;

  
    /**
     * Renders the diferents payment configuration fields
     * @param Varien_Data_Form_Element_Fieldset $element
     * @return string Html with the field content
     * 
     * @see Mage_Adminhtml_Block_System_Config_Form_Fieldset::render()
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        
        $html.=$this->_getJavascript();
        
        $html .= $this->_getDefaultPaymentConfigField($element)->toHtml();
        
        $html.="<tr><td colspan='4'><br/><div class='entry-edit-head'>"
                . "<a style='text-decoration:none;'>".Mage::helper('codeko_abandonedorders')->__('Per payment method configuration')."</a>"
                . "<button href='#' id='codeko_toggle_payment' class='show-hide'>"
                . "<span class='codeko_hide_payment'>" . Mage::helper('codeko_abandonedorders')->__('Hide disabled methods') . "</span>"
                . "<span class='codeko_show_payment' style='display: none;'>" . Mage::helper('codeko_abandonedorders')->__('Show all methods') . "</span>"
                . "</button></div><br/></td></tr>";
        
        $groups = Mage::getModel('payment/config')->getAllMethods();
        
        foreach ($groups as $group) {
            $fieldselect = $this->_getPaymentConfigField($element, $group);
            $fieldtext = $this->_getPaymentMinutesField($element, $group);
            $html .= $fieldselect->toHtml();
            $html .= $fieldtext->toHtml();
        }
        
        $html .= $this->_getFooterHtml($element);
        
        return $html;
    }
    
    /**
     * javascript and styles for the config screen
     * @return string 
     */
    protected function _getJavascript(){
        return <<< EOT
            <style>
                .codeko_hide{
                    display:none !important;
                }
                #codeko_toggle_payment{
                    float:right;
                }
            </style>
            <script type="text/javascript">
            //<![CDATA[
                document.observe('dom:loaded', function(event) {
                    
                    var \$hideshowpayment = $$('#codeko_toggle_payment');

                    \$hideshowpayment.invoke('observe', 'click', function(event) {
                        Event.stop(event);
                        var \$codekopminactive = $$('.codeko_payment_inactive');

                        $$('.codeko_hide_payment').each(Element.toggle);
                        $$('.codeko_show_payment').each(Element.toggle);

                        \$codekopminactive.each(function(item, index) {
                            Element.up(Element.up(item)).toggleClassName("codeko_hide");
                        });
                    });
                    $$('.codeko_hide_payment').invoke('click');
                });
            //]]>
            </script>
EOT;
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
     * @return Mage_Adminhtml_Block_System_Config_Form_Field
     */
    protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }
        return $this->_fieldRenderer;
    }

    /**
     * 
     * @return array Diferentes options for per payment method settings
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
                    'label' => Mage::helper('codeko_abandonedorders')->__('Auto cancel'),
                    'value' => $helper::PAYMENT_VALUE_BASIC_CONFIG
                ),
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Auto cancel after custom minutes'),
                    'value' => $helper::PAYMENT_VALUE_CUSTOM_CONFIG
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
    
    /**
     * Check if a payment method is active by id
     * @param string $payment_id
     * @return boolean true if the payment is active, false if not
     */
    protected function _isPaymentMethodActive($payment_id)
    {
        $actives = $this->_getActivePaymentMethods();
        $is_active=false;
        foreach ($actives as $active) {
            if ($active->getId() == $payment_id) {
                $is_active = true;
                break;
            }
        }
        return $is_active;
    }

    /**
     * 
     * @return array
     */
    protected function _getDefaultValuesForPayment()
    {
        if (empty($this->_values_new_payment)) {
            $helper = Mage::helper("codeko_abandonedorders");
            
            $this->_values_new_payment = array(
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Ignore until I configure it'),
                    'value' => $helper::PNP_VALUE_NO_PROCCES
                ),
                array(
                    'label' => Mage::helper('codeko_abandonedorders')->__('Auto cancel orders'),
                    'value' => $helper::PNP_VALUE_DEFAULT_CONFIG
                )
            );
        }
        return $this->_values_new_payment;
    }

    /**
     * 
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     * @return Varien_Data_Form_Element_Select
     */
    protected function _getDefaultPaymentConfigField(Varien_Data_Form_Element_Fieldset $fieldset)
    {
        $field_type = "select";
        $values = $this->_getDefaultValuesForPayment();
        $label = Mage::helper('codeko_abandonedorders')->__("Default action");
        $comment = Mage::helper('codeko_abandonedorders')->__("What should be done with not configured payments methods?");
        $field_name = 'groups[payments][fields][process_new_payment][value]';
        
        $path = 'codeko_abandonedorders/payments/process_new_payment'; 
        $current_value=$this->_getCurrentValue($path);
        
        $extra_config = array('comment' => $comment); 
        
        $field_data = $this->_getFieldConfigData($field_name, $label, $values, $current_value, $extra_config);
        
        $field = $fieldset->addField("process_new_payment", $field_type, $field_data)
                ->setRenderer($this->_getFieldRenderer());
        
        return $field;
    }

    /**
     * Return the select config field for a payment method
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     * @param Mage_Payment_Model_Method_Abstract $payment_method
     * @return Varien_Data_Form_Element_Abstract
     */
    protected function _getPaymentConfigField(Varien_Data_Form_Element_Fieldset $fieldset, Mage_Payment_Model_Method_Abstract $payment_method)
    {
        $name = "_enabled";
        $field_type = "select";
        $label=Mage::helper('payment')->getMethodInstance($payment_method->getId())->getTitle();
        $values = $this->_getValuesEnabled();
        return $this->_getPaymentField($fieldset, $payment_method, $name, $field_type, $label, $values);
    }

    /**
     * Return the custom minutes config field for a payment method
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     * @param Mage_Payment_Model_Method_Abstract $payment_method
     * @return Varien_Data_Form_Element_Abstract
     */
    protected function _getPaymentMinutesField(Varien_Data_Form_Element_Fieldset $fieldset, Mage_Payment_Model_Method_Abstract $payment_method)
    {
        $name = "_minutes";
        $field_type = "text";
        $values = "";
        $label="";
        $after_html='<script type="text/javascript"> '
                . 'new FormElementDependenceController({"'.$payment_method->getId().$name.'":{"'.$payment_method->getId().'_enabled":"2"}}); '
                . '</script>';
        $comment=Mage::helper('codeko_abandonedorders')
                ->__("Minutes after an order with this payment method is considered abandoned and canceled");
        
        $extra_data = array(   
            'class' => "validate-digits validate-digits-range digits-range-0-6000",
            'comment' => $comment,
            'after_element_html' => $after_html,
        );
        
        return $this->_getPaymentField($fieldset, $payment_method, $name, $field_type, $label, $values,$extra_data);
    }
    
    /**
     * Generate a configuration field
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     * @param Mage_Payment_Model_Method_Abstract $payment_method
     * @param string $name
     * @param string $field_type
     * @param string $label
     * @param array $values
     * @param array $extra_config
     * @return Varien_Data_Form_Element_Abstract
     */
    protected function _getPaymentField(Varien_Data_Form_Element_Fieldset $fieldset, Mage_Payment_Model_Method_Abstract $payment_method, $name, $field_type, $label, $values, $extra_config=array()){
        
        $array_field= $this->_getPaymentFieldConfigData($payment_method, $name, $values, $label, $extra_config);
        
        $class = "codeko_select_payment";
        if (!$this->_isPaymentMethodActive($payment_method->getId())) {
            $class .= " codeko_payment_inactive";
        }
        
        $field = $fieldset->addField($payment_method->getId() . $name, $field_type, $array_field)
            ->setRenderer($this->_getFieldRenderer())
            ->addClass($class);
        
        return $field;
    }
    
    /**
     * Generate field configuration array
     * @param Mage_Payment_Model_Method_Abstract $payment_method
     * @param string $name
     * @param array $values
     * @param string $label
     * @param array $extra_config
     * @return array Array with the config data of the field
     */
    protected function _getPaymentFieldConfigData(Mage_Payment_Model_Method_Abstract $payment_method,
            $name,$values,$label,$extra_config=array()){
        
        // this value is composed by the section name, group name and field name. 
        // The field name must not be numerical (that's why I added 'group_' in front of it)
        $path = 'codeko_abandonedorders/payments/group_' . $payment_method->getId() . $name; 
        
        $current_value=$this->_getCurrentValue($path);
        
        if($current_value["value"]==0){
            $current_value["value"]="";
        }
        
        $field_name='groups[payments][fields][group_' . $payment_method->getId() . $name . '][value]';
        
        return $this->_getFieldConfigData($field_name, $label, $values, $current_value, $extra_config);
    }
    
    protected function _getFieldConfigData($field_name,$label,$values,$current_value,$extra_config=array()){
        $dummy = $this->_getDummyElement();
        $array_field = array(
            // this is groups[group name][fields][field name][value]
            'name' => $field_name,
            'label' => $label,
            'values' => $values,
            'can_use_default_value' => $this->getForm()->canUseDefaultValue($dummy), 
            'can_use_website_value' => $this->getForm()->canUseWebsiteValue($dummy)
        );
        return array_merge($array_field,$current_value,$extra_config);
    }


    protected function _getCurrentValue($path){
        $configData = $this->getConfigData();
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int) (string) $this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }
        return array('value' => $data, 'inherit' => $inherit);
    }
}