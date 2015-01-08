<?php

/**
 * Makes operations cancellation of orders
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
        $this->log("#################### Executing abandonedOrders ... ####################");
        
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
        
        $this->log("#################### Finalized abandonedOrders ####################");
    }

    /**
     * Iterator orders
     *
     * @param unknown $order_collection            
     */
    private function iteratorOrders($order_collection)
    {
        foreach ($order_collection->getItems() as $order) {
            if (!empty($order) && $this->isCancelable($order)) {
                // If order meets the requirements to be canceled is canceled
                $this->cancelOrder($order);
            }
        }
    }

    /**
     * This provides us Collection of orders given selected states
     *
     * @param array $abandoned_states            
     * @return Collection order colletion
     */
    private function getOrderCollection($abandoned_states)
    {
        if (empty($abandoned_states)) {
            return null;
        }
        
        $this->log("Getting order collecion");
        $order_collection = Mage::getResourceModel('sales/order_collection');
        // Make JOIN with the table order_payment to add a payment method to collection
        $order_collection->getSelect()->join(array(
            'p' => $order_collection->getResource()
                ->getTable('sales/order_payment')
        ), 'p.parent_id = main_table.entity_id', array(
            "method"
        ));
        $order_collection->addFieldToFilter('state', array(
            'in' => array(
                explode(",", $abandoned_states)
            )
        ));
        $order_collection->addFieldToSelect('entity_id');
        $order_collection->addFieldToSelect('state');
        $order_collection->addFieldToSelect('status');
        $order_collection->addFieldToSelect('updated_at');
        $this->log($order_collection->getSelect()
            ->__toString());
        return $order_collection;
    }

    /**
     * Function canceled orders for a order_collection
     */
    private function cancelOrder($order)
    {
        try {
            if (!empty($order)) {
                $helper = Mage::helper("codeko_abandonedorders");
                $final_status = $helper->getNewOrderStatus();
                $order_model = Mage::getModel('sales/order');
                $order_model->load($order['entity_id']);
                $do_not_cancel = false;
                
                if (!$order_model->canCancel() && $order_model->getState() === Mage_Sales_Model_Order::STATE_CANCELED) {
                    // It may be the case it is canceled in state but not in status
                    $do_not_cancel = true;
                }
                
                if (!$do_not_cancel) {
                    $order_model->cancel();
                }
                
                $order_model->setStatus($final_status);
                
                $order_model->addStatusHistoryComment($helper->__('Order auto canceled'), $final_status);
                
                $order_model->save();
                $this->log("Order " . $order["entity_id"] . " - CANCELED");
            }
        } catch (Exception $e) {
            $this->log("Error order cancelling: " . $e);
        }
    }

    /**
     * Function that indicates if an order meets the conditions to be canceled
     *
     * @param
     *            booelan
     */
    private function isCancelable($order)
    {
        $helper = Mage::helper("codeko_abandonedorders");
        $updated_at = $order["updated_at"];
        $method = $order["method"];
        $minutes = 0;
        $payment_enabled = $helper->getPaymentEnabled($method);
        
        // Getting minutes
        if ($payment_enabled == null || $payment_enabled == "" || $payment_enabled == $helper::PAYMENT_VALUE_EMPTY) {
            // If the method of payment has no settings (possibly new payment method)
            $process_new_payment = $helper->getProcessNewPaymentMethod();
            if ($process_new_payment == $helper::PNP_VALUE_DEFAULT_CONFIG) {
                $minutes = $helper->getDefaultMinutes();
            }
        } else {
            // If the payment method is setup
            if ($payment_enabled != $helper::PAYMENT_VALUE_NO_PROCESS) {
                $minutes = $this->getPaymentMinutes($method);
            }
        }
        
        // Verify that the minutes are correct
        if ($minutes > 0) {
            $dateorder = (int) date("YmdHis", strtotime($updated_at));
            $datenow = (int) date("YmdHis");
            if ($dateorder < $datenow) {
                $this->log("Order " . $order["entity_id"] . " Date: " . $updated_at . " - Minutes for canceling " . $minutes . " TO BE CANCELED");
                return true;
            }
        } else {
            return false;
        }
        
        return false;
    }

    /**
     * Getting payment method minutes
     *
     * @param
     *            Method payment
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
            $mcm = 5;
        }
        $cronExprString = "*/" . $mcm . " * * * *";
        try {
            Mage::getModel('core/config_data')->load($helper::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath($helper::CRON_STRING_PATH)
                ->save();
            $this->log("Config cron " . $cronExprString);
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
        }
    }
}


