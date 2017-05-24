<?php

require_once(Mage::getBaseDir('app') . '/code/local/AllPay/Foundation/AllPay_Block_Redirect.php');

class AllPay_Credit_Block_Redirect extends AllPay_Block_Redirect {
    protected $paymentName = 'credit';
    protected $choosePayment = PaymentMethod::Credit;
}