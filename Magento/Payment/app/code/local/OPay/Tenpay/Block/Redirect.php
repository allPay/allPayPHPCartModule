<?php
require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_Tenpay_Block_Redirect extends OPay_Block_Redirect {
    protected $paymentName = 'tenpay';
    protected $choosePayment = PaymentMethod::Tenpay;

    protected function AutoSubmit() {
        $this->sendExtend['ExpireTime'] = date("Y/m/d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 3, date("Y")));

        parent::AutoSubmit();
    }
}