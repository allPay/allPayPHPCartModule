# WHMCS_AllPay_Mod V1
##WHMCS 歐付寶金流

###使用說明
1.將sql內的sql檔導入至WHMCS資料庫內  
2.設定mod\allpay\mysql_connect.inc.php，將資料庫名稱、使用者、密碼對應  
3.將mod內的所有檔案上傳至 {WHMCS根目錄}/modules/gateways  
4.至WHMCS管理後台 Setup->Payments->Payment Gateways->All Payment Gateways  
將需要使用的模組啟用  
5.於Manage Existing Gateways內設定 歐付寶廠商、帳單效期、交易描述  

###注意事項
若有必要更換帳單前綴  
需先將gateways及callback資料夾檔案內的InvoicePrefix變數設定相同的前綴

###版本紀錄
####V1.1
2017/3/1   
-停止支援超商條碼支付 
####V1.0
2016/1/4   
+支援超商代碼支付  
+支援超商條碼支付  
+支援信用卡支付  

###技術支援
Email : admin@ihostup.com  
Web Site : https://ihostup.com/   
模組維護 : Ying@iHostUP
