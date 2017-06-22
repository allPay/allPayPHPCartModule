<?php
require_once(Mage::getBaseDir('app') . '/code/local/OPay/Foundation/OPay_ProcessingController.php');

class OPay_CVS_ProcessingController extends OPay_ProcessingController {
    protected $paymentName = 'cvs';
    protected $newOrderStatus = Mage_Sales_Model_Order::STATE_NEW;

    protected function _generateGetCodeComment($request){
        $szPaymentType = $request['PaymentType'];
        $szTradeDate = $request['TradeDate'];
        $szBankCode = $request['PaymentNo'];
        $szExpireDate = $request['ExpireDate'];
        $szBarcode1 = $request['Barcode1'];
        $szBarcode2 = $request['Barcode2'];
        $szBarcode3 = $request['Barcode3'];

        $szComment = sprintf(Mage::helper('cvs')->__('付款方式: %s<br />付款時間: %s<br />繳費代碼: %s<br />付款截止日: %s<br />條碼第一段號碼: %s<br />條碼第二段號碼: %s<br />條碼第三段號碼: %s<br />'), $szPaymentType, $szTradeDate, $szBankCode, $szExpireDate, $szBarcode1, $szBarcode2, $szBarcode3);
        return $szComment;
    }
}