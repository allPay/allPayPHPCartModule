<?php

require_once(Mage::getBaseDir('app') . '/code/local/AllPay/Foundation/AllPay_Block_Redirect.php');

class AllPay_CVS_Block_Redirect extends AllPay_Block_Redirect {
    protected $paymentName = 'cvs';
    protected $choosePayment = PaymentMethod::CVS;

    protected function AutoSubmit() {
        $this->sendExtend["Desc_1"] = '';
        $this->sendExtend["Desc_2"] = '';
        $this->sendExtend["Desc_3"] = '';
        $this->sendExtend["Desc_4"] = '';
        $this->sendExtend["PaymentInfoURL"] = $this->_getUrl('response');

        parent::AutoSubmit();
    }
}