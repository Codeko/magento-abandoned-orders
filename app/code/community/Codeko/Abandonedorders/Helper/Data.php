<?php

/**
 * Helper with access to the various configuration parameters and utilities
 * @author Codeko
 *
 */
class Codeko_Abandonedorders_Helper_Data extends Mage_Core_Helper_Abstract
{

    const ENABLED = "codeko_abandonedorders/basic_settings/enabled";

    const DEFAULT_MINUTES = "codeko_abandonedorders/basic_settings/minutes";

    const NEW_ORDER_STATUS = "codeko_abandonedorders/status_settings/new_order_status";

    const ABANDONED_STATES = "codeko_abandonedorders/status_settings/abandoned_order_states";

    const PROCESS_NEW_PAYMENT_METHOD = "codeko_abandonedorders/payments/process_new_payment";

    const PNP_VALUE_NO_PROCCES = 0;

    const PNP_VALUE_DEFAULT_CONFIG = 1;

    const PAYMENT_VALUE_EMPTY = 0;

    const PAYMENT_VALUE_NO_PROCESS = 1;

    const PAYMENT_VALUE_CUSTOM_CONFIG = 2;

    const PAYMENT_VALUE_BASIC_CONFIG = 3;

    const CRON_STRING_PATH = "codeko_abandonedorders/basic_settings/cron_expr";

    const DEBUG_MODE = false;

    /**
     * Param enabled
     *
     * @return integer enabled 0 = no, 1 = enabled
     */
    public static function isEnabled()
    {
        return Mage::getStoreConfig(self::ENABLED);
    }

    /**
     * Param default minutes
     *
     * @return integer minutes
     */
    public static function getDefaultMinutes()
    {
        return Mage::getStoreConfig(self::DEFAULT_MINUTES);
    }

    /**
     * Param new order status
     *
     * @return string Status
     */
    public static function getNewOrderStatus()
    {
        return Mage::getStoreConfig(self::NEW_ORDER_STATUS);
    }

    /**
     * Params abandoned orders states
     *
     * @return array abandoned states
     */
    public static function getAbandonedStates()
    {
        return Mage::getStoreConfig(self::ABANDONED_STATES);
    }

    /**
     * Params procces new payment method
     *
     * @return int processnewpaymentmethod
     */
    public static function getProcessNewPaymentMethod()
    {
        return Mage::getStoreConfig(self::PROCESS_NEW_PAYMENT_METHOD);
    }

    /**
     * Param payment enabled of specific payment
     *
     * @param string $payment_code            
     * @return integer empty = 0 , enabled 1 = no, 2 = custom, 3 = general
     */
    public static function getPaymentEnabled($payment_code)
    {
        return Mage::getStoreConfig('codeko_abandonedorders/payments/group_' . $payment_code . '_enabled');
    }

    /**
     * Param payment enabled of specific payment
     *
     * @param string $payment_code            
     * @return integer minutes
     */
    public static function getPaymentMinutes($payment_code)
    {
        return Mage::getStoreConfig('codeko_abandonedorders/payments/group_' . $payment_code . '_minutes');
    }

    /**
     * Calculate MCD
     *
     * @param integer $a            
     * @param integer $b            
     * @return integer $c
     */
    public static function mcd($a, $b)
    {
        $x = 0;
        $nuevob = 0;
        $x = $a;
        
        if ($a < $b) {
            $a = $b;
            $b = $x;
            return self::mcd($a, $b);
        } else if ($b != 0) {
            $nuevob = $a % $b;
            $a = $b;
            $b = $nuevob;
            return self::mcd($a, $b);
        }
        return $a;
    }

    /**
     * Get cron interval
     *
     * @return integer
     */
    public static function getCronInterval()
    {
        // Getting a configuration payment minutes
        $minutes = array();
        $payments = Mage::getModel('payment/config')->getAllMethods();
        
        foreach ($payments as $pay) {
            $enabled = self::getPaymentEnabled($pay->getId());
            if ($enabled) {
                $aux = self::getPaymentMinutes($pay->getId());
                if (is_numeric($aux) && $aux > 0)
                    $minutes[] = $aux;
            }
        }
        
        $default = self::getDefaultMinutes();
        if (is_numeric($default) && $default > 0)
            $minutes[] = $default;
            
            // Getting mcd
        $mcd = 0;
        for ($i = 0; $i < count($minutes); $i++) {
            if ($i == 0) {
                $mcd = self::mcd($minutes[$i], $minutes[$i + 1]);
            } else if ($i != 1) {
                $mcd = self::mcd($mcd, $minutes[$i]);
            }
        }
        
        return $mcd;
    }
}


