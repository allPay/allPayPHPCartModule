<?php
class OPay_Credit12_Helper_Data extends Mage_Payment_Helper_Data {

    public function getPendingPaymentStatus() {
        if (version_compare(Mage::getVersion(), '1.4.0', '<')) {
            return Mage_Sales_Model_Order::STATE_HOLDED;
        }
        return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
    }
}
