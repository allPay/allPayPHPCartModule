<?php

require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_Alipay_Block_Redirect extends OPay_Block_Redirect {
    protected $paymentName = 'alipay';
    protected $choosePayment = PaymentMethod::Alipay;

    protected function AutoSubmit() {
        $billing = $this->_getBilling();

        $this->sendExtend['Email'] = $billing->getEmail();
        $this->sendExtend['PhoneNo'] = $billing->getTelephone();
        $this->sendExtend['UserName'] = $billing->getName();
        $this->sendExtend['AlipayItemName'] = Mage::helper('alipay')->__('網路商品一批');
        $this->sendExtend['AlipayItemCounts'] = 1;
        $this->sendExtend['AlipayItemPrice'] = $this->_getTotal();

        parent::AutoSubmit();
    }
}