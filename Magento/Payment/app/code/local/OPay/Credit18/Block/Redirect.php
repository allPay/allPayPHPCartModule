<?php

require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_Block_Redirect.php');

class OPay_Credit18_Block_Redirect extends OPay_Block_Redirect {
    protected $paymentName = 'credit18';
    protected $choosePayment = PaymentMethod::Credit;

    protected function AutoSubmit() {
        # Installment extension parameters
        $this->sendExtend['CreditInstallment'] = 18;
        $this->sendExtend['InstallmentAmount'] = $this->_getTotal();
        $this->sendExtend['Redeem'] = false;
        $this->sendExtend['UnionPay'] = false;

        parent::AutoSubmit();
    }
}