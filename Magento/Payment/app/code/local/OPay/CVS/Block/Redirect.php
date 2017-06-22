<?php

require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_CVS_Block_Redirect extends OPay_Block_Redirect {
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