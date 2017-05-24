<?php

require_once(Mage::getBaseDir('app') . '/code/local/AllPay/Foundation/AllPay_Block_Redirect.php');

class AllPay_WebATM_Block_Redirect extends AllPay_Block_Redirect {
    protected $paymentName = 'webatm';
    protected $choosePayment = PaymentMethod::WebATM;
}