<?php

require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_TopUpUsed_Block_Redirect extends OPay_Block_Redirect {
    protected $paymentName = 'topupused';
    protected $choosePayment = PaymentMethod::TopUpUsed;
}