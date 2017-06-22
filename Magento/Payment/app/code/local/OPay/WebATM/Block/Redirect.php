<?php

require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_WebATM_Block_Redirect extends OPay_Block_Redirect {
    protected $paymentName = 'webatm';
    protected $choosePayment = PaymentMethod::WebATM;
}