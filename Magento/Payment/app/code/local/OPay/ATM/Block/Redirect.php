<?php

require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_ATM_Block_Redirect extends OPay_Block_Redirect {
    protected $paymentName = 'atm';
    protected $choosePayment = PaymentMethod::ATM;

    protected function AutoSubmit() {
        $this->sendExtend['ExpireDate'] = 3;
        $this->sendExtend['PaymentInfoURL'] = $this->_getUrl('response');

        parent::AutoSubmit();
    }
}
