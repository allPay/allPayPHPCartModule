<?php

# Required File Includes
if (file_exists("../../../init.php")) { // For new version
    include("../../../init.php");
    $whmcs->load_function('gateway');
    $whmcs->load_function('invoice');
} else {
    include("../../../dbconnect.php");
    include("../../../includes/functions.php");
    include("../../../includes/gatewayfunctions.php");
    include("../../../includes/invoicefunctions.php");
}

$gatewaymodule = "allpaycvs"; # Enter your gateway module name here replacing template
$InvoicePrefix = "CVS";

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

# Get Returned Variables - Adjust for Post Variable Names from your Gateway's Documentation
$status = $_POST["RtnCode"];
$transid = $_POST["TradeNo"];
$amount = $_POST["TradeAmt"];
$fee = $_POST["PaymentTypeChargeFee"];
$invoiceid = $_POST["MerchantTradeNo"];
$invoiceid = str_replace($InvoicePrefix,"",$invoiceid);

checkCbInvoiceID($invoiceid, $GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing

$sql = "SELECT * FROM mod_allpay where MerchantTradeNo = '".$InvoicePrefix."$invoiceid'";
$result = mysql_query($sql);
$row = @mysql_fetch_row($result);
if($row[14]==1){#Allpay回傳第二次相同付款訊息就不理他,直接回傳1|OK告知收到
	echo "1|OK";
}
else{
	if ($status=="1") {
		# Successful
		$sql = "UPDATE mod_allpay SET `Paid` = '1' WHERE MerchantTradeNo = '".$InvoicePrefix."$invoiceid'";
		if(mysql_query($sql)){
			addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
			logTransaction($GATEWAY["name"],$_POST,"Successful"); # Save to Gateway Log: name, data array, status
			echo "1|OK";
		}
		else
		{
			echo "0|ErrorMessage";
		}
	} else {
		# Unsuccessful
		logTransaction($GATEWAY["name"],$_POST,"Unsuccessful"); # Save to Gateway Log: name, data array, status
		echo "0|ErrorMessage";
	}
}

?>