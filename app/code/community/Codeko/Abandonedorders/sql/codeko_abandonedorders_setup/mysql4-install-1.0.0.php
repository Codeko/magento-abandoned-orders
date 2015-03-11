<?php
$installer = $this;
$helper = Mage::helper("codeko_abandonedorders");

Mage::log("Installing module Abandonedorders", null, "abandonedorders.log");

$installer->startSetup();
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

try {
    // When installing abandonedorders, create auto canceled status and relationship
    $installer->getConnection()->insertArray($statusTable, array(
        'status',
        'label'
    ), array(
        array(
            'status' => 'codeko_auto_canceled',
            'label' => 'Auto canceled for abandonment'
        )
    ));
    $installer->getConnection()->insertArray($statusStateTable, array(
        'status',
        'state',
        'is_default'
    ), array(
        array(
            'status' => 'codeko_auto_canceled',
            'state' => 'canceled',
            'is_default' => '0'
        )
    ));
} catch (Exception $ex) {
    Mage::logException($ex);
}

$installer->endSetup();

