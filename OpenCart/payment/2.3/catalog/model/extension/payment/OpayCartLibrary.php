<?php

class OpayCartLibrary
{
    private $merchantId = '';
    private $stageMids = ['2000132', '2000214']; // Stage merchant id
    private $isTest = false; // Test mode
    private $provider = 'OPay'; // Service provider
    private $tradeTime = ''; // Trade time
    private $orderPrefix = ''; // MerchantTradeNo prefix
    private $encryptType = ''; // Encrypt type
    private $productUrl = 'https://payment.allpay.com.tw';
    private $stageUrl = 'https://payment-stage.allpay.com.tw';
    private $functionPath = [
        'checkOut' => '/Cashier/AioCheckOut/V4',
        'queryTrade' => '/Cashier/QueryTradeInfo/V2',
    ]; // API function path
    private $successCodes = [
        'payment' => 1,
        'atmGetCode' => 2,
        'cvsGetCode' => 10100073,
        'barcodeGetCode' => 10100073,
    ]; // API success return code

    public function __construct($data)
    {
        $this->loadSdk();
        $this->merchantId = $data['merchantId'];
        $this->isTest = $this->isTestMode();
        $this->tradeTime = $this->getDateTime('Y/m/d H:i:s', '');
        $this->orderPrefix = $this->getDateTime('ymdHis', $this->tradeTime);
        $this->encryptType = EncryptType::ENC_SHA256;
    }
    
    /**
     * Check test mode by merchant id
     * @return boolean
     */
    public function isTestMode()
    {
        return in_array($this->merchantId, $this->stageMids);
    }

    /**
     * Load AIO SDK
     * @return void
     */
    public function loadSdk()
    {
        if (!class_exists('AllInOne', false)) {
            include('AllPay.Payment.Integration.php');
        }
    }

    /**
     * Checkout
     * @param  array $data The data for checkout
     * @return void
     */
    public function checkout($data)
    {
        $paymentType = $data['choosePayment'];

        // Set SDK parameters
        $aio = $this->getAio(); // Get AIO object
        $aio->MerchantID = $this->merchantId;
        $aio->HashKey = $data['hashKey'];
        $aio->HashIV = $data['hashIv'];
        $aio->ServiceURL = $this->getUrl('checkOut'); // Get Checkout URL
        $aio->EncryptType = $this->encryptType;
        $aio->Send['ReturnURL'] = $data['returnUrl'];
        $aio->Send['ClientBackURL'] = $this->filterUrl($data['clientBackUrl']);
        $aio->Send['MerchantTradeNo'] = $this->getMerchantTradeNo($data['orderId']);
        $aio->Send['MerchantTradeDate'] = $this->tradeTime;
        $aio->Send['TradeDesc'] = $data['version'];
        $aio->Send['TotalAmount'] = $this->getAmount($data['total']);
        $aio->Send['ChoosePayment'] = $this->getPaymentMethod($paymentType);

        // Set the product info
        $aio->Send['Items'][] = [
            'Name' => $data['itemName'],
            'Price' => $aio->Send['TotalAmount'],
            'Quantity' => 1,
            'URL' => '',
        ];
        
        // Set the extend information
        switch ($aio->Send['ChoosePayment']) {
            case PaymentMethod::Credit:
                // Do not support UnionPay
                $aio->SendExtend['UnionPay'] = false;
                
                // Credit installment parameters
                $installments = $this->getInstallment($paymentType);
                if ($installments > 0) {
                    $aio->SendExtend['CreditInstallment'] = $installments;
                    $aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
                    $aio->SendExtend['Redeem'] = false;
                }
                break;
            case PaymentMethod::ATM:
                $aio->SendExtend['ExpireDate'] = 3;
                $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                break;
            case PaymentMethod::CVS:
                $aio->SendExtend['Desc_1'] = '';
                $aio->SendExtend['Desc_2'] = '';
                $aio->SendExtend['Desc_3'] = '';
                $aio->SendExtend['Desc_4'] = '';
                $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                break;
            case PaymentMethod::WebATM:
            default:
        }

        $aio->CheckOut();
        exit;
    }

    /**
     * Create AIO Object
     * @return object
     */
    public function getAio()
    {
        return new AllInOne(); 
    }

    /**
     * Get AIO URL
     * @param  string $type URL type
     * @return string
     */
    public function getUrl($type)
    {
        if ($this->isTest === true) {
            $url = $this->stageUrl;
        } else {
            $url = $this->productUrl;
        }
        return $url . $this->functionPath[$type];
    }

    /**
     * Filter the specific character
     * @param  string $url URL
     * @return string
     */
    public function filterUrl($url)
    {
        return str_replace('&amp;', '&', $url);
    }

    /**
     * Get AIO merchant trade number
     * @param  mix $orderId Order id
     * @return string
     */
    public function getMerchantTradeNo($orderId)
    {
        if ($this->isTest === true) {
            return $this->orderPrefix . $orderId;
        } else {
            return strval($orderId);
        }
    }

    /**
     * Get date time
     * @param  string $pattern    Date time pattern
     * @param  string $dateString Date string
     * @return string
     */
    public function getDateTime($pattern, $dateString = '')
    {
        if ($dateString !== '') {
            return date($pattern, strtotime($dateString));
        } else {
            return date($pattern);
        }
    }
    
    /**
     * Get the payment method from the payment type
     * @param  string $paymentType Payment type
     * @return string
     */
    public function getPaymentMethod($paymentType)
    {
        $pieces = explode('_', $paymentType);
        return $pieces[0];
    }

    /**
     * Get AIO feedback
     * @param  array $data The data for getting aio feedback
     * @return array
     */
    public function getFeedback($data)
    {
        $aio = $this->getAio();
        $aio->MerchantID = $this->merchantId;
        $aio->HashKey = $data['hashKey'];
        $aio->HashIV = $data['hashIv'];
        $aio->EncryptType = $this->encryptType;
        $feedback = $aio->CheckOutFeedback();
        if (count($feedback) < 1) {
            throw new Exception($this->provider . ' feedback is empty.');
        }
        return $feedback;
    }

    /**
     * Get AIO trade info
     * @param  array $feedback AIO feedback
     * @param  array $data     The data for querying aio trade info
     * @return array
     */
    public function getTradeInfo($feedback, $data)
    {
        $aio = $this->getAio();
        $aio->MerchantID = $this->merchantId;
        $aio->HashKey = $data['hashKey'];
        $aio->HashIV = $data['hashIv'];
        $aio->ServiceURL = $this->getUrl('queryTrade');
        $aio->EncryptType = $this->encryptType;
        $aio->Query['MerchantTradeNo'] = $feedback['MerchantTradeNo'];
        $info = $aio->QueryTradeInfo();
        if (count($info) < 1) {
            throw new Exception($this->provider . ' trade info is empty.');
        }
        return $info;
    }

    /**
     * Get AIO feedback and validate
     * @param  array $data The data for getting AIO feedback
     * @return array
     */
    public function getValidFeedback($data)
    {
        $feedback = $this->getFeedback($data); // AIO feedback
        $info = $this->getTradeInfo($feedback, $data); // Trade info

        // Check the amount
        if (!$this->validAmount($feedback['TradeAmt'], $info['TradeAmt'])) {
            throw new Exception('Invalid ' . $this->provider . ' feedback.(1)');
        }

        // Check the status when in product
        if ($this->isTest === false) {
            if ($this->isSuccess($feedback, 'payment') === true) {
                if ($this->toInt($info['TradeStatus']) !== 1) {
                    throw new Exception('Invalid ' . $this->provider . ' feedback.(2)');
                }
            }
        }
        return $feedback;
    }

    /**
     * Validate the amounts
     * @param  mix $source Source amount
     * @param  mix $target Target amount
     * @return boolean
     */
    public function validAmount($source, $target)
    {
        return ($this->getAmount($source) === $this->getAmount($target));
    }
    
    /**
     * Get the amount
     * @param  mix $amount Amount
     * @return integer
     */
    public function getAmount($amount)
    {
        return round($amount, 0);
    }

    /**
     * Get the order id from AIO merchant trade number
     * @param  string $merchantTradeNo AIO merchant trade number
     * @return integer
     */
    public function getOrderId($merchantTradeNo)
    {
        if ($this->isTest === true) {
            $start = strlen($this->orderPrefix);
            $orderId = substr($merchantTradeNo, $start);
        } else {
            $orderId = $merchantTradeNo;
        }
        return $this->toInt($orderId);
    }

    /**
     * Get AIO response status
     * @param  array $feedback  AIO feedback
     * @param  array $orderInfo Order info
     * @return integer
     */
    public function getResponseStatus($feedback, $orderInfo)
    {
        $orderId = $orderInfo['orderId'];
        $validStatus = $orderInfo['validStatus'];
        $paymentMethod = $this->getPaymentMethod($feedback['PaymentType']);
        $paymentFailed = $this->getPaymentFailed($orderId, $feedback);
        $statusError = $this->getStatusError($orderId);

        // Check the response status
        //   0:failed
        //   1:Paid
        //   2:ATM get code
        //   3:CVS get code
        $responseStatus = 0;
        switch($paymentMethod) {
            case PaymentMethod::Credit:
            case PaymentMethod::WebATM:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case PaymentMethod::ATM:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } elseif ($this->isSuccess($feedback, 'atmGetCode') === true) {
                    $responseStatus = 2; // ATM get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case PaymentMethod::CVS:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } elseif ($this->isSuccess($feedback, 'cvsGetCode') === true) {
                    $responseStatus = 3; // CVS/Barcode get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            default:
                throw new Exception($this->getInvalidPayment($orderId));
        }
        return $responseStatus;
    }

    /**
     * Get payment failed message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getPaymentFailed($orderId, $feedback)
    {
        return sprintf('Order %s Exception.(%s: %s)', $orderId, $feedback['RtnCode'], $feedback['RtnMsg']);
    }

    /**
     * Get invalid payment message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getInvalidPayment($orderId)
    {
        return sprintf('Order %s, payment method is invalid.', $orderId);
    }

    /**
     * Get order status error message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getStatusError($orderId)
    {
        return sprintf('Order %s status error.', $orderId);
    }

    /**
     * Get amount error message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getAmountError($orderId)
    {
        return sprintf('Order %s amount are not identical.', $orderId);
    }

    /**
     * Check AIO feedback status
     * @param  array   $feedback AIO feedback
     * @param  string  $type     Feedback type
     * @return boolean
     */
    public function isSuccess($feedback, $type)
    {
        return ($this->toInt($feedback['RtnCode']) === $this->toInt($this->successCodes[$type]));
    }

    /**
     * Get the installment
     * @param  string $paymentType Payment type
     * @return integer
     */
    public function getInstallment($paymentType)
    {
        $pieces = explode('_', $paymentType);
        if (isset($pieces[1]) === true) {
            return $this->getAmount($pieces[1]);
        } else {
            return 0;
        }
    }

    /**
     * Get payment success message
     * @param  string $partten  Message pattern
     * @param  array  $feedback AIO feedback
     * @return string
     */
    public function getPaymentSuccessComment($partten, $feedback)
    {
        return sprintf($partten, $feedback['RtnCode'], $feedback['RtnMsg']);
    }

    /**
     * Get obtaining code comment
     * @param  string $partten  Message pattern
     * @param  array  $feedback AIO feedback
     * @return string
     */
    public function getObtainingCodeComment($partten, $feedback)
    {
        $type = $this->getPaymentMethod($feedback['PaymentType']);
        switch($type) {
            case 'ATM':
                return sprintf(
                    $partten,
                    $feedback['RtnCode'],
                    $feedback['RtnMsg'],
                    $feedback['BankCode'],
                    $feedback['vAccount'],
                    $feedback['ExpireDate']
                );
                break;
            case 'CVS':
                return sprintf(
                    $partten,
                    $feedback['RtnCode'],
                    $feedback['RtnMsg'],
                    $feedback['PaymentNo'],
                    $feedback['ExpireDate']
                );
                break;
            case 'BARCODE':
                return sprintf(
                    $partten,
                    $feedback['RtnCode'],
                    $feedback['RtnMsg'],
                    $feedback['ExpireDate'],
                    $feedback['Barcode1'],
                    $feedback['Barcode2'],
                    $feedback['Barcode3']
                );
                break;
            default:
        }
        return 'undefine';
    }

    /**
     * Get obtaining code comment
     * @param  string $partten  Message pattern
     * @param  array  $error    Error message
     * @return string
     */
    public function getFailedComment($partten, $error)
    {
        return sprintf($partten, $error);
    }

    /**
     * Chang the value to integer
     * @param  mix $value Value
     * @return integer
     */
    public function toInt($value)
    {
        return intval($value);
    }
}