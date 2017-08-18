<?php
    /*
    *   需搭配 SDK(AllPay.Payment.Integration.php) 使用
    */
    abstract class URLType {
        const CREATE_ORDER = 0;
        const QUERY_ORDER = 1;
    }
    
    abstract class ServiceURL {
        const PROD_CO = 'https://payment.allpay.com.tw/Cashier/AioCheckOut/V2';
        const STAGE_CO = 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut/V2';
        const PROD_QO = 'https://payment.allpay.com.tw/Cashier/QueryTradeInfo/V2';
        const STAGE_QO = 'https://payment-stage.allpay.com.tw/Cashier/QueryTradeInfo/V2';
    }
    
    abstract class RoundMethod {
        const MHD_ROUND = '0';
        const MHD_CEIL = '1';
        const MHD_FLOOR = '2';
    }
    
    abstract class LogMsg {
        const RESP_DES = 'Recive allPay response.';
        const RESP_RES = 'Processed result: %s';
    }
    
    abstract class ErrorMsg {
        const EXT_MISS = 'allPay extension missed.';
        const C_FD_EMPTY = 'allPay checkout feedback is empty.';
        const Q_FD_EMPTY = 'allPay query feedback is empty.';
        const AMT_DIFF = 'Amount error.';
        const PAY_DIFF = 'Payment method error';
        const UPD_ODR = 'Order is modified.';
        const UNDEFINE = 'Undefine_';
    }
	
	abstract class CommentTpl {
		const GC_ATM = 'Bank Code : %s, Virtual Account : %s, Payment Deadline : %s.';
		const GC_CVS = 'Trade Code : %s, Payment Deadline : %s.';
		const SUCC = 'Paid Succeed.';
		const FAIL = 'Paid Failed, Exception(%s).';
	}
    
    abstract class ReturnCode {
        const PAID = 1;
        const DELV_PAID = 800;
        const GC_ATM = 2;
        const GC_CVS = 10100073;
    }
	
	abstract class TradeStatus {
		const UNPAID = 0;
		const PAID = 1;
	}
    
    abstract class ReturnType {
        const PAYMENT = 0;
        const GET_CODE = 1;
    }
    
    class AllpayCartExt {
        private $merchant_id = '';
        private $test_mode = false;
        public function __construct($merchant_id) {
            if (empty($merchant_id)) {
                throw new Exception('merchant_id missed.');
            }
            $this->merchant_id = $merchant_id;
            $this->test_mode = $this->isTestMode($this->merchant_id);
        }
        
        public function isTestMode() {
            return ($this->merchant_id == '2000132' or $this->merchant_id == '2000214');
        }
        
        public function getServiceURL($action = URLType::CREATE_ORDER) {
            if ($this->test_mode) {
                switch ($action) {
                    case URLType::CREATE_ORDER:
                        return ServiceURL::STAGE_CO;
                        break;
                    case URLType::QUERY_ORDER:
                        return ServiceURL::STAGE_QO;
                        break;
                    default:
                }
                
            } else {
                switch ($action) {
                    case URLType::CREATE_ORDER:
                        return ServiceURL::PROD_CO;
                        break;
                    case URLType::QUERY_ORDER:
                        return ServiceURL::PROD_QO;
                        break;
                    default:
                }
            }
            return '';
        }
        
        public function getMerchantTradeNo($order_id) {
            if ($this->test_mode) {
                return (date('ymdHis') . $order_id);
            } else {
                return $order_id;
            }
        }
        
		public function parsePayment($payment_type) {
			$type_pieces = explode('_', $payment_type);
            return $type_pieces[0];
		}
		
        public function getCartOrderID($order_id) {
            if ($this->test_mode) {
                return (substr($order_id, 12));
            } else {
                return $order_id;
            }
        }
        
        public function roundAmt($amt, $round_method = '0') {
            $round_amt = '';
            switch($round_method) {
                case RoundMethod::MHD_ROUND:
                    $round_amt = round($amt, 0);
                    break;
                case RoundMethod::MHD_CEIL:
                    $round_amt = ceil($amt);
                    break;
                case RoundMethod::MHD_FLOOR:
                    $round_amt = floor($amt);
                    break;
                default:
                    $round_amt = $amt;
            }
            return $round_amt;
        }
        
        public function setSendExt($payment, $params) {
            $send_ext = array();
            $atm_min_exp_dt = 1;
            $atm_max_exp_dt = 60;
            $data = array(
                'Installment' => 0,# 信用卡分期用
                'TotalAmount' => 0,# 信用卡分期用
                'ExpireDate' => 3,# ATM用
                'ReturnURL' => '',# ATM/CVS用
                'Email' => '-',# Alipay用
                'PhoneNo' => '-',# Alipay用
                'UserName' => '-',# Alipay用
            );
            foreach ($params as $name => $value) {
                if (isset($data[$name])) {
                    $data[$name] = $value;
                }
            }
            if (class_exists('PaymentMethod')) {
                switch ($payment) {
                    case PaymentMethod::WebATM:
                    case PaymentMethod::TopUpUsed:
                        break;
                    case PaymentMethod::Credit:
                        # 預設不支援銀聯卡
                        $send_ext['UnionPay'] = false;
                        
                        # 信用卡分期參數
                        if (!empty($data['Installment'])) {
                            $send_ext['CreditInstallment'] = $data['Installment'];
                            $send_ext['InstallmentAmount'] = $data['TotalAmount'];
                            $send_ext['Redeem'] = false;
                        }
                        break;
                    case PaymentMethod::ATM:
                        if ($data['ExpireDate'] < $atm_min_exp_dt or $data['ExpireDate'] > $atm_max_exp_dt) {
                            throw new Exception('ATM ExpireDate from ' . $atm_min_exp_dt . ' to ' . $atm_max_exp_dt . '.');
                        }
                        $send_ext['ExpireDate'] = $data['ExpireDate'];
                        $send_ext['PaymentInfoURL'] = $data['ReturnURL'];
                        $send_ext['ClientRedirectURL'] = '';
                        break;
                    case PaymentMethod::CVS:
                        $send_ext['Desc_1'] = '';
                        $send_ext['Desc_2'] = '';
                        $send_ext['Desc_3'] = '';
                        $send_ext['Desc_4'] = '';
                        $send_ext['PaymentInfoURL'] = $data['ReturnURL'];
                        $send_ext['ClientRedirectURL'] = '';
                        break;
                    case PaymentMethod::Alipay:
                        $send_ext['Email'] = $data['Email'];
                        $send_ext['PhoneNo'] = $data['PhoneNo'];
                        $send_ext['UserName'] = $data['UserName'];
                        break;
                    case PaymentMethod::Tenpay:
                        //$send_ext['ExpireTime'] = date('Y/m/d H:i:s', strtotime('+2 days'));
                        break;
                    default:
                        throw new Exception('Undefine payment method.');
                        break;
                }
            }
            return $send_ext;
        }
        
        public function validAmount($cart_amt, $rtn_amt, $query_amt) {
            if ($cart_amt != $rtn_amt or $cart_amt != $query_amt) {
                throw new Exception(ErrorMsg::AMT_DIFF);
            }
        }
        
        public function validPayment($rtn_payment, $query_payment) {
			if ($rtn_payment != $query_payment) {
                throw new Exception(ErrorMsg::PAY_DIFF . '01');
			}
        }
        
        public function validStatus($order_status, $create_status) {
            if ($order_status != $create_status) {
                throw new Exception(ErrorMsg::UPD_ODR);
            }
        }
		
		public function getCommentTpl($payment, $rtn_code) {
			$comment_tpl = '';
            $is_paid = $this->isPaid($rtn_code);
            $rtn_type = $this->getReturnType($payment, $rtn_code);
            $get_code_payment = array(PaymentMethod::ATM, PaymentMethod::CVS);
            if ($rtn_type == ReturnType::PAYMENT) {
                $comment_tpl = $this->getTpl(($is_paid ? 'succ' : 'fail'));
            } else if ($rtn_type == ReturnType::GET_CODE) {
                if (in_array($payment, $get_code_payment)) {
                    $comment_tpl = $this->getGetcodeTpl($payment);
                }
            }
            
            if (empty($comment_tpl)) {
                throw new Exception(ErrorMsg::UNDEFINE . '01');
            }
            
            return $comment_tpl;
		}
		
		public function getComment($payment, $tpl, $feedback) {
			$comment = '';
            if (!empty($tpl)){
                $fail_tpl = $this->getTpl('fail');
                $succ_tpl = $this->getTpl('succ');
                $gc_tpl = $this->getGetcodeTpl($payment);
                switch ($tpl) {
                    case $fail_tpl:
                        $comment = $this->getFailComment($feedback['RtnMsg'], $tpl);
                        break;
                    case $gc_tpl:
                        switch ($payment) {
                            case PaymentMethod::ATM:
                                $comment = sprintf(
                                    $tpl,
                                    $feedback['BankCode'],
                                    $feedback['vAccount'],
                                    $feedback['ExpireDate']
                                );
                                break;
                            case PaymentMethod::CVS:
                                $comment = sprintf(
                                    $tpl,
                                    $feedback['PaymentNo'],
                                    $feedback['ExpireDate']
                                );
                                break;
                            default:
                                throw new Exception(ErrorMsg::UNDEFINE . '02');
                        }
                        break;
                    case $succ_tpl:
                        $comment = $tpl;
                        break;
                    default:
                        throw new Exception(ErrorMsg::UNDEFINE . '03');
                }
            } else {
                throw new Exception(ErrorMsg::UNDEFINE . '04');
            }

			return $comment;
		}
		
        public function getPayment($payment_name) {
            $payment = array(
                'credit' => PaymentMethod::Credit,
                'webatm' => PaymentMethod::WebATM,
                'atm' => PaymentMethod::ATM,
                'cvs' => PaymentMethod::CVS,
                'alipay' => PaymentMethod::Alipay,
                'tenpay' => PaymentMethod::Tenpay,
                'topupused' => PaymentMethod::TopUpUsed
            );
            return $payment[$payment_name];
        }
		
        public function getGetcodeTpl($payment) {
            $gc_tpl = array(
                PaymentMethod::ATM => CommentTpl::GC_ATM,
                PaymentMethod::CVS => CommentTpl::GC_CVS
            );
            if (isset($gc_tpl[$payment])) {
                return $gc_tpl[$payment];
            } else {
                return '';
            }
        }
        
        public function getTpl($tpl_type) {
            $tpl = array(
                'succ' => CommentTpl::SUCC,
                'fail' => CommentTpl::FAIL
            );
            return $tpl[$tpl_type];
        }
        
		public function getFailComment($msg, $tpl = '') {
			$comment = sprintf($tpl, $msg);
			return $comment;
		}
		
        public function isPaid($rtn_code) {
            if ($rtn_code == ReturnCode::PAID) {
                return true;
            } else {
                return false;
            }
        }
        
        
        public function isGetCode($payment, $rtn_code) {
            $rtn_type = $this->getReturnType($payment, $rtn_code);
            if ($rtn_type == ReturnType::GET_CODE) {
                return true;
            } else {
                return false;
            }
        }
        
        public function getReturnType($payment, $rtn_code) {
            $return_type = '';
            $gc_code = array(
				PaymentMethod::ATM => ReturnCode::GC_ATM,
				PaymentMethod::CVS => ReturnCode::GC_CVS
			);
            if (isset($gc_code[$payment]) and $rtn_code == $gc_code[$payment]) {
                $return_type = ReturnType::GET_CODE;
            } else {
                $return_type = ReturnType::PAYMENT;
            }
            return $return_type;
        }
        
    }
?>