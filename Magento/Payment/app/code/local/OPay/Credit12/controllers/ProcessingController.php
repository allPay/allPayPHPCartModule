<?php
require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_ProcessingController.php');

class OPay_Credit12_ProcessingController extends OPay_ProcessingController {
    protected $paymentName = 'credit12';
    protected $newOrderStatus = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
}