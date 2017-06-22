<?php
require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_ProcessingController.php');

class OPay_ATM_ProcessingController extends OPay_ProcessingController {
    protected $paymentName = 'atm';
    protected $newOrderStatus = Mage_Sales_Model_Order::STATE_NEW;

    protected function _generateGetCodeComment($request){
        $szPaymentType = $request['PaymentType'];
        $szTradeDate = $request['TradeDate'];
        $szBankCode = $request['BankCode'];
        $szVirtualAccount = $request['vAccount'];
        $szExpireDate = $request['ExpireDate'];

        $szComment = sprintf(Mage::helper('atm')->__('付款方式: %s<br />付款時間: %s<br />銀行代碼: %s<br />虛擬帳號: %s<br />付款截止日: %s<br />'), $szPaymentType, $szTradeDate, $szBankCode, $szVirtualAccount, $szExpireDate);
        return $szComment;
    }
}
