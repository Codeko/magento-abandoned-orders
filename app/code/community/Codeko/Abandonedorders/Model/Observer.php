<?php

/**
 * Cancel orders after a configured amount of minutes
 * @author Codeko
 *
 */
class Codeko_Abandonedorders_Model_Observer
{
    
    /**
     * General function Cancel Abandoned Orders
     */
    public function cancelAbandonedOrders()
    {
        $this->log("#################### Executing Abandoned Orders ... ####################");
        
        $helper = Mage::helper("codeko_abandonedorders");
        
        $enabled = $helper->isEnabled();
        
        if ($enabled) {
            // Getting specific order
            $abandoned_states = $helper->getAbandonedStates();
            $order_collection = $this->getOrderCollection($abandoned_states);
            
            // If there are orders
            if (!empty($order_collection)) {
                $this->iteratorOrders($order_collection);
            }
        }
        
        $this->log("#################### Finalized Abandoned Orders ####################");
    }

    /**
     * Iterate the order collection and cancel each order
     *
     * @param Mage_Sales_Model_Resource_Order_Collection $order_collection            
     */
    protected function iteratorOrders($order_collection)
    {
        foreach ($order_collection->getItems() as $order) {
            if (!empty($order) && $this->isCancelable($order)) {
                // If order meets the requirements to be canceled is canceled
                $this->cancelOrder($order);
            }
        }
    }

    /**
     * This provides us Collection of orders for the given selected states
     *
     * @param array $abandoned_states            
     * @return Mage_Sales_Model_Resource_Order_Collection order colletion
     */
    protected function getOrderCollection($abandoned_states)
    {
        if (empty($abandoned_states)) {
            return null;
        }
        
        $this->log("Getting order collecion");
        $order_collection = Mage::getResourceModel('sales/order_collection');
        // Make JOIN with the table order_payment to add a payment method to collection
        $payments_table=$order_collection->getResource()->getTable('sales/order_payment');
        $order_collection->getSelect()->join(
            array('p' => $payments_table), 
            'p.parent_id = main_table.entity_id', 
            array("method")
        );
        $order_collection->addFieldToFilter('state', array(
            'in' => array(
                explode(",", $abandoned_states)
            )
        ));
        $order_collection->addFieldToSelect('entity_id');
        $order_collection->addFieldToSelect('state');
        $order_collection->addFieldToSelect('status');
        $order_collection->addFieldToSelect('updated_at');
        $this->log($order_collection->getSelect()->__toString());
        Mage::dispatchEvent('codeko_abandonedorders_filter_order_collection', 
                    array('collection' => $order_collection));
        return $order_collection;
    }

    /**
     * Function canceled orders for a order_collection
     */
    
    /**
     * Cancel a abandoned order
     * @param array $order Order fields
     * @return boolean
     */
    protected function cancelOrder($order)
    {
        if (empty($order)) {
            return false;
        }
        try {    
            $order_id=$order['entity_id'];
            $order_model = Mage::getModel('sales/order')->load($order_id);
            //Set order attribute. The diferents methods and events will change it
            //to finally determine if the order should be canceled or not
            $order_model->setCodekoAbandonedOrdersDoCancel(true);

            Mage::dispatchEvent('codeko_abandonedorders_cancel_order_before', 
                array('order' => $order_model));

            $this->_checkIsCancelable($order_model);

            if ($order_model->getCodekoAbandonedOrdersDoCancel()) {
                $order_model->cancel();
            }

            Mage::dispatchEvent('codeko_abandonedorders_cancel_order_before_save', 
                    array('order' => $order_model));
            
            $this->_changeOrderStatus($order_model);

            $order_model->save();
            $this->log("Order " . $order["entity_id"] . " - CANCELED");

            Mage::dispatchEvent('codeko_abandonedorders_cancel_order_after', 
                array('order' => $order_model));
            return true;
        } catch (Exception $e) {
            $this->log("Error order cancelling: " . $e);
        }
        return false;
    }
    
    /**
     * Check if a order should be canceled
     * @param Mage_Sales_Model_Order $order_model
     */
    protected function _checkIsCancelable(Mage_Sales_Model_Order $order_model) {
        if ($order_model->getCodekoAbandonedOrdersDoCancel() &&
                (!$order_model->canCancel() || $order_model->getState() === Mage_Sales_Model_Order::STATE_CANCELED)) {
            // It may be the case it is canceled in state but not in status
            $order_model->setCodekoAbandonedOrdersDoCancel(false);
        }
        Mage::dispatchEvent('codeko_abandonedorders_check_order_is_cancelable', 
                    array('order' => $order_model));
    }

    /**
     * Change order status and add comments
     * @param Mage_Sales_Model_Order $order_model
     */
    protected function _changeOrderStatus(Mage_Sales_Model_Order $order_model){
        $helper = Mage::helper("codeko_abandonedorders");
        $final_status = $helper->getNewOrderStatus();    
        $order_model->setStatus($final_status);
        $order_model->addStatusHistoryComment($helper->__('Abandoned order auto canceled'), $final_status);
    }

    /**
     * Check if an order meets the conditions to be canceled
     * 
     * @param Mage_Sales_Model_Order $order
     * @return boolean True if the order can be cancel, false if not
     */
    protected function isCancelable($order)
    {
        
        $updated_at = $order["updated_at"];
        $payment_method = $order["method"];
        $is_cancelable=false;
        
        $minutes= $this->_getPaymentMinutesConfig($payment_method);
        
        // Verify that the minutes are correct
        if ($minutes > 0) {
            $dateorder = strtotime($updated_at);
            $datenow = time();
            $diff=round(abs($datenow - $dateorder) / 60,2);
            if ($diff>=$minutes) {
                $this->log("Order " . $order["entity_id"] . " Date: $updated_at will be cancelled after $diff minutes ($minutes configured)");
                $is_cancelable=true;
            }
        }
        
        return $is_cancelable;
    }
    
    /**
     * Returns the configured minutes for a payment method
     * @param string $payment_method
     * @return int minutes configured for this payment method
     */
    protected function _getPaymentMinutesConfig($payment_method){
        $helper = Mage::helper("codeko_abandonedorders");
        $minutes = 0;
        $payment_enabled = $helper->getPaymentEnabled($payment_method);
        
        // Getting minutes
        if (empty($payment_enabled) || $payment_enabled == $helper::PAYMENT_VALUE_EMPTY) {
            // If the method of payment has no settings (possibly new payment method)
            $process_new_payment = $helper->getProcessNewPaymentMethod();
            if ($process_new_payment == $helper::PNP_VALUE_DEFAULT_CONFIG) {
                $minutes = $helper->getDefaultMinutes();
            }
        } else {
            // If the payment method is setup
            if ($payment_enabled != $helper::PAYMENT_VALUE_NO_PROCESS) {
                $minutes = $this->getPaymentMinutes($payment_method);
            }
        }
        return $minutes;
    }

    /**
     * Get payment method minutes
     *
     * @param string Payment method code
     * @return integer minutes
     */
    private function getPaymentMinutes($method_payment)
    {
        $helper = Mage::helper("codeko_abandonedorders");
        $payment_enabled = $helper->getPaymentEnabled($method_payment);
        if ($payment_enabled == $helper::PAYMENT_VALUE_CUSTOM_CONFIG) {
            // Take the minutes of the configuration of the payment
            $minutes = $helper->getPaymentMinutes($method_payment);
        } else if ($payment_enabled == $helper::PAYMENT_VALUE_BASIC_CONFIG) {
            // Take the minutes of the general configuration
            $minutes = $helper->getDefaultMinutes();
        }
        return $minutes;
    }

    /**
     * Print log in abandonedorders.log.
     * Only DEBUG == true
     *
     * @param string $log            
     */
    private function log($log, $force_log = false)
    {
        $helper = Mage::helper("codeko_abandonedorders");
        if ($helper::DEBUG_MODE || $force_log) {
            Mage::log($log, null, "abandonedorders.log");
        }
    }

    /**
     * Define cron shedule expression
     *
     * @see Mage_Core_Model_Abstract::_afterSave()
     */
    public function setCronSchedule()
    {
        $helper = Mage::helper("codeko_abandonedorders");
        $mcm = $helper->getCronInterval();
        if (!is_numeric($mcm) || $mcm <= 0) {
            $mcm = $helper::DEFAULT_CRON_INTERVAL;
        }
        $cronExprString = "*/" . $mcm . " * * * *";
        try {
            Mage::getModel('core/config_data')->load($helper::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath($helper::CRON_STRING_PATH)
                ->save();
            $this->log("Config cron " . $cronExprString);
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'),$e->getCode(),$e);
        }
    }
}


