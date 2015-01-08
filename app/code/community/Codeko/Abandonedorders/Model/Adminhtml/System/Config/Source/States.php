<?php

/**
* Class with different types of States
* @author Codeko
*
*/
class Codeko_Abandonedorders_Model_Adminhtml_System_Config_Source_States
{

    /**
     * Get array with different order states
     *
     * @return array Order states
     */
    public function toOptionArray()
    {
        $states = Mage::getSingleton('sales/order_config')->getStates();
        
        $options = array();
        $options[] = array(
            'value' => '',
            'label' => "-- " . Mage::helper('codeko_abandonedorders')->__('Please Select') . " --"
        );
        foreach ($states as $code => $label) {
            $options[] = array(
                'value' => $code,
                'label' => Mage::helper('codeko_abandonedorders')->__($label)
            );
        }
        return $options;
    }
}