<?php

require_once(Mage::getBaseDir('app') . '/code/local/AllPay/Foundation/AllPay_Block_Redirect.php');

class AllPay_Credit3_Block_Redirect extends AllPay_Block_Redirect {
    protected $paymentName = 'credit3';
    protected $choosePayment = PaymentMethod::Credit;

    protected function AutoSubmit() {
        # Installment extension parameters
        $this->sendExtend['CreditInstallment'] = 3;
        $this->sendExtend['InstallmentAmount'] = $this->_getTotal();
        $this->sendExtend['Redeem'] = false;
        $this->sendExtend['UnionPay'] = false;

        parent::AutoSubmit();
    }
}