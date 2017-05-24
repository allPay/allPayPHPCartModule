<?php

require_once(Mage::getBaseDir('app') . '/code/local/AllPay/Foundation/AllPay_Block_Redirect.php');

class AllPay_ATM_Block_Redirect extends AllPay_Block_Redirect {
    protected $paymentName = 'atm';
    protected $choosePayment = PaymentMethod::ATM;

    protected function AutoSubmit() {
        $this->sendExtend['ExpireDate'] = 3;
        $this->sendExtend['PaymentInfoURL'] = $this->_getUrl('response');

        parent::AutoSubmit();
    }
}
