<?php
require_once(Mage::getBaseDir('app') . '/code/local/OPay/AllPay.Payment.Integration.php');

class OPay_ProcessingController extends Mage_Core_Controller_Front_Action {

    protected $paymentName = 'paymentName';
    protected $newOrderStatus = 'newOrderStatus';

    protected function _generateGetCodeComment($request){
        return '';
    }


	private function _getCheckout() {
		return $this->_getSession();
	}
		
    private function _getOrder($orderID = NULL) {
        if (!isset($orderID)) {
            $orderID = $this->_getSession()->getLastRealOrderId();
        }
        if ($orderID) {
            return Mage::getModel('sales/order')->loadByIncrementId($orderID);
        } else {
            return null;
        }
    }

    private function _getSession() {
        return Mage::getSingleton('checkout/session');
    }

    private function _getConfigData($keyword) {
        return Mage::getStoreConfig('payment/' . $this->paymentName . '/' . $keyword, null);
    }

    /**
     * main entry point
     */
    public function viewAction() {
        
    }

    public function redirectAction() {
        try {
            $oSession = $this->_getSession();
            $oOrder = $this->_getOrder();

            if (!$oOrder->getId()) {
                Mage::throwException(Mage::helper($this->paymentName)->__('Order No not found'));
            }
            if ($oOrder->getState() != $this->newOrderStatus) {
                $oOrder->setState(
                        $this->newOrderStatus, $this->newOrderStatus, Mage::helper($this->paymentName)->__('Redirect to pay page')
                )->save();

                $oOrder->sendNewOrderEmail();  //發出E-mail通知信
                $oOrder->setEmailSent(true);
            }

            if ($oSession->getQuoteId() && $oSession->getLastSuccessQuoteId()) {
                $oSession->setAllpayQuoteId($oSession->getQuoteId());
                $oSession->setAllpaySuccessQuoteId($oSession->getLastSuccessQuoteId());
                $oSession->setAllpayRealOrderId($oSession->getLastRealOrderId());
                $oSession->getQuote()->setIsActive(false)->save();
                $oSession->clear();
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    public function responseAction() {
        try {
            $oPayment = new AllInOne();
            $oPayment->HashKey = $this->_getConfigData('hash_key');
            $oPayment->HashIV = $this->_getConfigData('hash_iv');
            $oPayment->MerchantID = $this->_getConfigData('merchant_id');

            $arFeedback = $oPayment->CheckOutFeedback();

            $isSuccess = $this->_processSale($arFeedback);

            if ($isSuccess === true) {
                print '1|OK';
            } else {
                Mage::throwException('Paid faild.');
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
            print '0|' . $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            print '0|' . $e->getMessage();
        }
    }

    public function resultAction() {
        $isSuccess = FALSE;

        try {
            $oSession = $this->_getSession();
            $oSession->unsAllpayRealOrderId();
            $oSession->setQuoteId($oSession->getAllpayQuoteId(true));
            $oSession->setLastSuccessQuoteId($oSession->getAllpaySuccessQuoteId(true));

            $oPayment = new AllInOne();
            $oPayment->HashKey = $this->_getConfigData('hash_key');
            $oPayment->HashIV = $this->_getConfigData('hash_iv');
            $oPayment->MerchantID = $this->_getConfigData('merchant_id');

            $arFeedback = $oPayment->CheckOutFeedback();

            if (sizeof($arFeedback) > 0) {
                $isSuccess = $this->_processSale($arFeedback);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }

        if ($isSuccess) {
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('checkout/onepage/failure');
        }
    }

    /**
     * Process success response
     */
    protected function _processSale($request) {
        $isSuccess = false;

        try {
            $szTradeNo = $request['TradeNo'];
            $szMerchantTradeNo = ($this->_getConfigData('test_mode') ? str_replace($this->_getConfigData('test_order_prefix'), '', $request['MerchantTradeNo']) : $request['MerchantTradeNo']);
            $szReturnCode = $request['RtnCode'];
            $szPaymenttype = $request['PaymentType'];
            
            $oOrder = $this->_getOrder($szMerchantTradeNo);
            // check transaction amount and currency
            if ($this->_getConfigData('use_store_currency')) {
                $dePrice = $oOrder->getGrandTotal();
                $szCurrency = $oOrder->getOrderCurrencyCode();
            } else {
                $dePrice = $oOrder->getBaseGrandTotal();
                $szCurrency = $oOrder->getBaseCurrencyCode();
            }
            // check transaction amount
            if (round($dePrice) != $request['TradeAmt']) {
                Mage::throwException('Transaction amount doesn\'t match.');
            }
            // save transaction information
            Mage::log($oOrder->getState());
            if (($request['RtnCode'] == '2' || $request['RtnCode'] == '10100073') && $oOrder->getState() == Mage_Sales_Model_Order::STATE_NEW) {
                $szComment = $this->_generateGetCodeComment($request);
                $oOrder->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, TRUE, $szComment, FALSE);
                $oOrder->save();
                
                $isSuccess = true;
            } elseif ($request['RtnCode'] == '1' && $oOrder->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $oOrder->getPayment()
                        ->setTransactionId($szTradeNo)
                        ->setLastTransId($szMerchantTradeNo)
                        ->setCcStatus($szReturnCode)
                        ->setCcType($szPaymenttype);
                // 產生發票
                if ($oOrder->canInvoice()) {
                    $oInvoice = $oOrder->prepareInvoice();
                    $oInvoice->register()->capture();
                    $oInvoice->sendEmail(); //將發票E-mail給客戶
                    Mage::getModel('core/resource_transaction')
                            ->addObject($oInvoice)
                            ->addObject($oInvoice->getOrder())
                            ->save();
                }
                $oOrder->setState(Mage_Sales_Model_Order::STATE_PROCESSING, TRUE);
                $oOrder->save();
                
                $isSuccess = true;
            } else {
                $oOrder->setState(Mage_Sales_Model_Order::STATE_HOLDED, TRUE, '失敗');
                $oOrder->save();
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $isSuccess;
    }
}
