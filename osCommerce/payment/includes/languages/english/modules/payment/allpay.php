<?php
	/**
	 * @copyright  Copyright (c) 2015 AllPay (http://www.allpay.com.tw)
	 * @version 1.0.1021
	 * @author Shawn.Chang
	*/
			
	# Module description
	define('MODULE_PAYMENT_ALLPAY_TITLE_TEXT', 'allPay');
	define('MODULE_PAYMENT_ALLPAY_DESC_TEXT', 'allPay all in one payment');
	
	# Configurations description
	define('MODULE_PAYMENT_ALLPAY_REQUIRE_FIELD_TEXT', 'Required field');
	define('MODULE_PAYMENT_ALLPAY_ENABLE_TEXT', 'Enable allPay Payment');
	define('MODULE_PAYMENT_ALLPAY_TEST_MODE_TEXT', 'Test Mode');
	define('MODULE_PAYMENT_ALLPAY_TEST_MODE_DESC_TEXT', 'Test order will add date as prefix');
	define('MODULE_PAYMENT_ALLPAY_MERCHANT_ID_TEXT', 'Merchant ID');
	define('MODULE_PAYMENT_ALLPAY_HASH_KEY_TEXT', 'Hash Key');
	define('MODULE_PAYMENT_ALLPAY_HASH_IV_TEXT', 'Hash IV');
	define('MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID_TEXT', 'Order Created Status');
	define('MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID_TEXT', 'Paid Status');
	define('MODULE_PAYMENT_ALLPAY_UNPAID_STATUS_ID_TEXT', 'Unpaid Status');
	define('MODULE_PAYMENT_ALLPAY_AVAILABLE_PAYMENTS_TEXT', 'Available Payments');
	define('MODULE_PAYMENT_ALLPAY_AVAILABLE_INSTALLMENTS_TEXT', 'Available Credit Installments');
	define('MODULE_PAYMENT_ALLPAY_SORT_ORDER_TEXT', 'Sort Order');
	define('MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE_TEXT', 'allPay Payment Zone');
	define('MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE_DESC_TEXT', 'If a zone is selected, only enable allPay payment for that zone');
	
	# Payments description
	define('MODULE_PAYMENT_ALLPAY_CREDIT', 'Credit');
	define('MODULE_PAYMENT_ALLPAY_WEBATM', 'WEB-ATM');
	define('MODULE_PAYMENT_ALLPAY_ATM', 'ATM');
	define('MODULE_PAYMENT_ALLPAY_CVS', 'CVS');
	// define('MODULE_PAYMENT_ALLPAY_BARCODE', 'BARCODE');
	define('MODULE_PAYMENT_ALLPAY_ALIPAY', 'Alipay');
	define('MODULE_PAYMENT_ALLPAY_TENPAY', 'Tenpay');
	define('MODULE_PAYMENT_ALLPAY_TOPUPUSED', 'TopUpUsed');
	define('MODULE_PAYMENT_ALLPAY_INSTALLMENT', 'Installments');
	
	# Web description
	define('MODULE_PAYMENT_ALLPAY_CHOOSE_PAYMENT_TITLE', 'Payment');
	define('MODULE_PAYMENT_ALLPAY_CHOOSE_INSTALLMENT_TITLE', 'Credit installment');
	
	# Product description
	define('MODULE_PAYMENT_ALLPAY_PRODUCT_NAME', 'A package of online goods');

	# Order comment
	define('MODULE_PAYMENT_ALLPAY_COMMON_COMMENTS', 'Payment Method : %s' . "\n" . 'Trade Time : %s' . "\n");
	define('MODULE_PAYMENT_ALLPAY_ATM_COMMENTS', 'Bank Code : %s' . "\n" . 'Virtual Account : %s' . "\n" . 'Payment Deadline : %s' . "\n");
	// define('MODULE_PAYMENT_ALLPAY_BARCODE_COMMENTS', 'Payment Deadline : %s' . "\n" . 'BARCODE 1 : %s' . "\n" . 'BARCODE 2 : %s' . "\n" . 'BARCODE 3 : %s' . "\n");
	define('MODULE_PAYMENT_ALLPAY_CVS_COMMENTS', 'Trade Code : %s' . "\n" . 'Payment Deadline : %s' . "\n");
	define('MODULE_PAYMENT_ALLPAY_GET_CODE_RESULT_COMMENTS', 'Getting Code Result : (%s)%s');
	define('MODULE_PAYMENT_ALLPAY_PAYMENT_RESULT_COMMENTS', 'Payment Result : (%s)%s');
	define('MODULE_PAYMENT_ALLPAY_FAILED_COMMENTS', 'Paid failed');
?>