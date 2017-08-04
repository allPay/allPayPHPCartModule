<?php


class ControllerExtensionPaymentOpayInvoice extends Controller
{
	protected function index()
	{
	}

	// 寫入發票資訊
	public function set_invoice_info()
	{
		if($this->request->post['invoice_type'] && $this->request->post['invoice_type'])
		{
		
			$this->session->data["invoice_type"] 	= $this->request->post['invoice_type'];
			$this->session->data["company_write"] 	= $this->request->post['company_write'];
			$this->session->data["love_code"] 	= $this->request->post['love_code'];	
			$this->session->data["invoice_status"] 	= $this->request->post['invoice_status'];		
		}
	
	}
	
	// 刪除發票資訊
	public function del_invoice_info()
	{
		$this->session->data["invoice_type"] 	= '';
		$this->session->data["company_write"] 	= '';
		$this->session->data["love_code"] 	= '';	
		$this->session->data["invoice_status"] 	= 0 ;

	}
}

?>
