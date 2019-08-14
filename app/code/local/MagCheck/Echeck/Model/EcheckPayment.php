<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    MagCheck
 * @package     MagCheck_Echeck
 * @copyright   Copyright (c) 2013 MagCheck (http://www.MagCheck.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
class MagCheck_Echeck_Model_EcheckPayment extends Mage_Paygate_Model_Authorizenet
{

	protected $_formBlockType = 'echeck/form_echeck';
    protected $_infoBlockType = 'echeck/info_echeck';

    const REQUEST_METHOD_CC     = 'CREDIT';
    const REQUEST_METHOD_ECHECK = 'ACH';

    const REQUEST_TYPE_AUTH_CAPTURE = 'SALE';
    const REQUEST_TYPE_AUTH_ONLY    = 'AUTH';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE';
    const REQUEST_TYPE_CREDIT       = 'CREDIT';
    const REQUEST_TYPE_VOID         = 'VOID';
    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';

    const ECHECK_ACCT_TYPE_CHECKING = 'CHECKING';
    const ECHECK_ACCT_TYPE_BUSINESS = 'BUSINESSCHECKING';
    const ECHECK_ACCT_TYPE_SAVINGS  = 'SAVINGS';

    const ECHECK_TRANS_TYPE_CCD = 'CCD';
    const ECHECK_TRANS_TYPE_PPD = 'PPD';
    const ECHECK_TRANS_TYPE_TEL = 'TEL';
    const ECHECK_TRANS_TYPE_WEB = 'WEB';

    const RESPONSE_DELIM_CHAR = ',';

    const RESPONSE_CODE_APPROVED = 'APPROVED';
    const RESPONSE_CODE_DECLINED = 'DECLINED';
    const RESPONSE_CODE_ERROR    = 'ERROR';
    const RESPONSE_CODE_HELD     = 4;
	
	const INVOICE_ID = 0;
	const BANK_NAME = 1;
	const PAYMENT_ACCOUNT = 2;
	const AUTH_CODE = 3;
	const CARD_TYPE = 4;
	const AMOUNT = 5;
	const REBID = 6;
	const AVS = 7;
	const ORDER_ID = 8;
	const CARD_EXPIRE = 9;
	const Result = 10;
	const RRNO = 11;
	const CVV2 = 12;
	const PAYMENT_TYPE = 13;
	const MESSAGE = 14;
	
	protected $responseHeaders;

    protected $_code  = 'echeckpayment';

    /**
     * Availability options
     */
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc = false;

	public function authorize(Varien_Object $payment, $amount)
    {
		Mage::throwException(Mage::helper('echeck')->__('Error:'));
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for authorization.'));
        }
        $payment->setTransactionType(self::REQUEST_TYPE_AUTH_CAPTURE);
        $payment->setAmount($amount);

        $request= $this->_buildRequest($payment);
        $result = $this->_postRequest($request);

        $payment->setCcApproval($result->getAuthCode())
            ->setLastTransId($result->getRrno())
            ->setTransactionId($result->getRrno())
            ->setIsTransactionClosed(0)
            ->setCcTransId($result->getRrno())
            ->setCcAvsStatus($result->getAvs())
            ->setCcCidStatus($result->getCvv2());
Mage::throwException(Mage::helper('paygate')->__('Error: ' . $result->getMessage()));
        switch ($result->getResult()) {
            case self::RESPONSE_CODE_APPROVED:
                $payment->setStatus(self::STATUS_APPROVED);
				Mage::throwException(Mage::helper('echeck')->__('Error: ' . $result->getMessage()));
                return $this;
            case self::RESPONSE_CODE_DECLINED:
                Mage::throwException(Mage::helper('echeck')->__('The transaction has been declined'));
			case self::RESPONSE_CODE_ERROR:
                Mage::throwException(Mage::helper('echeck')->__('Error: ' . $result->getMessage()));
			default:
                Mage::throwException(Mage::helper('echeck')->__('Error!'));
        }
    }


    public function capture(Varien_Object $payment, $amount)
    {
        $error = false;
        if ($payment->getCcTransId()) {
            $payment->setTransactionType(self::REQUEST_TYPE_AUTH_CAPTURE);
        } else {
            $payment->setTransactionType(self::REQUEST_TYPE_AUTH_CAPTURE);
        }
        $payment->setAmount($amount);

        $request= $this->_buildRequest($payment);
        $result = $this->_postRequest($request);
        if ($result->getResult() == self::RESPONSE_CODE_APPROVED) {
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setLastTransId($result->getRrno())
			->setTransactionId($result->getRrno());
			return $this;
			}
       if ($result->getMessage()) {
            Mage::throwException($this->_wrapGatewayError($result->getMessage()));
        }
		Mage::throwException(Mage::helper('echeck')->__('Error in capturing the payment.'));
        if ($error !== false) {
            Mage::throwException($error);
        }
    }

    public function void(Varien_Object $payment)
    {
        $error = false;
        if($payment->getParentTransactionId()){
            $payment->setTransactionType(self::REQUEST_TYPE_CREDIT);
            $request = $this->_buildRequest($payment);
            $result = $this->_postRequest($request);
            if($result->getResult()==self::RESPONSE_CODE_APPROVED){
                 $payment->setStatus(self::STATUS_SUCCESS );
				 $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
				 return $this;
            }
            $payment->setStatus(self::STATUS_ERROR);
            Mage::throwException($this->_wrapGatewayError($result->getMessage()));
		}
		$payment->setStatus(self::STATUS_ERROR);
		Mage::throwException(Mage::helper('echeck')->__('Invalid transaction ID.'));
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if ($payment->getRefundTransactionId() && $amount > 0) {
            $payment->setTransactionType(self::REQUEST_TYPE_CREDIT);
			$payment->setRrno($payment->getRefundTransactionId());
			$payment->setAmount($amount);
            $request = $this->_buildRequest($payment);
            $request->setRrno($payment->getRefundTransactionId());
            $result = $this->_postRequest($request);
            if ($result->getResult()==self::RESPONSE_CODE_APPROVED) {
                $payment->setStatus(self::STATUS_SUCCESS);
                return $this;
            }
			if ($result->getResult()==self::RESPONSE_CODE_DECLINED) {
                Mage::throwException($this->_wrapGatewayError('DECLINED'));
            }
			if ($result->getResult()==self::RESPONSE_CODE_ERROR) {
                Mage::throwException($this->_wrapGatewayError('ERROR'));
            }			
            Mage::throwException($this->_wrapGatewayError($result->getRrno()));
        }
        Mage::throwException(Mage::helper('echeck')->__('Error in refunding the payment.'));
    }

    protected function _buildRequest(Varien_Object $payment)
    {
        $order = $payment->getOrder();

        $this->setStore($order->getStoreId());

        if (!$payment->getPaymentType()) {
            $payment->setPaymentType(self::REQUEST_METHOD_ECHECK);
        }
		$payment->setPaymentType(self::REQUEST_METHOD_ECHECK);
        $request = Mage::getModel('echeck/EcheckPayment_request');

        if ($order && $order->getIncrementId()) {
            $request->setInvoiceID($order->getIncrementId());
        }

        $request->setMode(($this->getConfigData('test_mode') == 'TEST') ? 'TEST' : 'LIVE');
        $request->setMerchant($this->getConfigData('login'))
            ->setTransactionType($payment->getTransactionType())
            ->setPaymentType($payment->getPaymentType())
			->setTamperProofSeal($this->calcTPS($payment));
        if($payment->getAmount()){
            $request->setAmount($payment->getAmount(),2);
        }
        switch ($payment->getTransactionType()) {
            case self::REQUEST_TYPE_CREDIT:
            case self::REQUEST_TYPE_VOID:
            case self::REQUEST_TYPE_CAPTURE_ONLY:
                $request->setRrno($payment->getCcTransId());
                break;
        }
		$cart = Mage::helper('checkout/cart')->getCart()->getItemsCount();
		$cartSummary = Mage::helper('checkout/cart')->getCart()->getSummaryQty();
		Mage::getSingleton('core/session', array('name'=>'frontend'));
		$session = Mage::getSingleton('checkout/session');

		$comment = "";

		foreach ($session->getQuote()->getAllItems() as $item) {
    
			$comment .= $item->getQty() . ' ';
			$comment .= '[' . $item->getSku() . ']' . ' ';
			$comment .= $item->getName() . ' ';
			$comment .= $item->getDescription() . ' ';
			$comment .= $item->getBaseCalculationPrice . ' ';
		}
		
        if (!empty($order)) {
            $billing = $order->getBillingAddress();
            if (!empty($billing)) {
                $request->setName1($billing->getFirstname())
                    ->setName2($billing->getLastname())
                    ->setCompany($billing->getCompany())
                    ->setAddr1($billing->getStreet(1))
                    ->setCity($billing->getCity())
                    ->setState($billing->getRegion())
                    ->setZipcode($billing->getPostcode())
                    ->setCountry($billing->getCountry())
                    ->setPhone($billing->getTelephone())
                    ->setFax($billing->getFax())
                    ->setCustomId($billing->getCustomerId())
					->setComment($comment)
                    ->setEmail($order->getCustomerEmail());
            }
        }

        switch ($payment->getPaymentType()) {
            case self::REQUEST_METHOD_ECHECK:
                $request->setAchRouting($payment->getEcheckRoutingNumber())
                    ->setAchAccount($payment->getEcheckBankAcctNum())
                    ->setAchAccountType($payment->getEcheckAccountType())
                    ->setName($payment->getEcheckAccountName())
                    ->setDocType(self::ECHECK_TRANS_TYPE_CCD)
					->setAchCheckNumber($payment->getEcheckCheckNumber())
					->setAchCheckDate($payment->getEcheckCheckDate())
					->setAchBankName($payment->getEcheckBankName())
					;
                break;
        }
		
        return $request;
    }

    protected function _postRequest(Varien_Object $request)
    {
        $debugData = array('request' => $request->getData());
		
		$requestdata = $request->getData();

        $result = Mage::getModel('echeck/EcheckPayment_result');

		$d = str_split($requestdata[ach_routing],1);
		$Check_Digit =  (7*($d[0]+$d[3]+$d[6])+3*($d[1]+$d[4]+$d[7])+9*($d[2]+$d[5]))%10;
		
		$isvalid = false;
		
		$stateArray = array(
			"Alabama"=>"AL",
			"Alaska"=>"AK",
			"Arizona"=>"AZ",
			"Arkansas"=>"AR",
			"California"=>"CA",
			"Colorado"=>"CO",
			"Connecticut"=>"CT",
			"Delaware"=>"DE",
			"District of Columbia"=>"DC",
			"Florida"=>"FL",
			"Georgia"=>"GA",
			"Hawaii"=>"HI",
			"Idaho"=>"ID",
			"Illinois"=>"IL",
			"Indiana"=>"IN",
			"Iowa"=>"IA",
			"Kansas"=>"KS",
			"Kentucky"=>"KY",
			"Louisiana"=>"LA",
			"Maine"=>"ME",
			"Maryland"=>"MD",
			"Massachusetts"=>"MA",
			"Michigan"=>"MI",
			"Minnesota"=>"MN",
			"Mississippi"=>"MS",
			"Missouri"=>"MO",
			"Montana"=>"MT",
			"Nebraska"=>"NE",
			"Nevada"=>"NV",
			"New Hampshire"=>"NH",
			"New Jersey"=>"NJ",
			"New Mexico"=>"NM",
			"New York"=>"NY",
			"North Carolina"=>"NC",
			"North Dakota"=>"ND",
			"Ohio"=>"OH",
			"Oklahoma"=>"OK",
			"Oregon"=>"OR",
			"Pennsylvania"=>"PA",
			"Rhode Island"=>"RI",
			"South Carolina"=>"SC",
			"South Dakota"=>"SD",
			"Tennessee"=>"TN",
			"Texas"=>"TX",
			"Utah"=>"UT",
			"Vermont"=>"VT",
			"Virginia"=>"VA",
			"Washington"=>"WA",
			"West Virginia"=>"WV",
			"Wisconsin"=>"WI",
			"Wyoming"=>"WY",
			"American Samoa"=>"AS",
			"Guam"=>"GU",
			"Northern Mariana Islands"=>"MP",
			"Puerto Rico"=>"PR",
			"Virgin Islands"=>"VI",
			"U.S. Minor Outlying Islands"=>"UM",
			"Federated States of Micronesia"=>"FM",
			"Marshall Islands"=>"MH",
			"Palau"=>"PW"
		);
		
		if (strlen($requestdata[ach_routing].$Check_Digit)!=10)
			$isvalid = false;
		else 
		{
			$d=str_split($requestdata[ach_routing].$Check_Digit,1);
			if ((3*($d[0]+$d[3]+$d[6])+7*($d[1]+$d[4]+$d[7])+($d[2]+$d[5]+$d[8]))%10==0)
				$isvalid = true;
			else
				$isvalid = false;
		}
		if ($isvalid)
		{					
			$client_code = $this->getConfigData('client_code');
			$invoice_id = $requestdata[invoice_id];
			$mode = $requestdata[mode];
			$merchant = $requestdata[merchant];
			$transaction_type  = $requestdata[transaction_type];
			$payment_type = $requestdata[payment_type];
			$tamper_proof_seal = $requestdata[tamper_proof_seal];
			$amount = $requestdata[amount];
			$name1 = $requestdata[name1];
			$name2 = $requestdata[name2];
			$company = $requestdata[company];
			$phone = $requestdata[phone];
			$fax = $requestdata[fax];
			$custom_id = $requestdata[custom_id];
			$comment = $requestdata[comment];
			$addr1 = $requestdata[addr1];
			$city = $requestdata[city];
			$state = $requestdata[state];
			$zipcode = $requestdata[zipcode];
			
			if(!is_numeric($zipcode))
				Mage::throwException($this->_wrapGatewayError('Zip code must contain only digits (0-9)'));
			if(strlen($zipcode)!=5)
				Mage::throwException($this->_wrapGatewayError('Zip code must be 5 digits in length'));
			
			$country = $requestdata[country];
			$email = $requestdata[email];	

			$ach_bank_name = $requestdata[ach_bank_name];
			$ach_check_number = $requestdata[ach_check_number];
			$ach_check_date =  date("Y-m-d");
			$ach_check_payto = $this->getConfigData('payto');
			
			$ach_extra1 = 'Order Number: ' . $requestdata[invoice_id];
			$ach_extra2 = date("Y-m-d H:m:s");
			$ach_extra3 = $this->getConfigData('support_url');
			$ach_extra4 = $this->getConfigData('support_number');
			$ach_extra5 = $this->getConfigData('message');
			$ach_extra6 = $this->getConfigData('extra6');
			
			$ach_user_name = $this->getConfigData('user_name');
			$ach_password = $this->getConfigData('password');
			$ach_teminal_number = $this->getConfigData('terminal_number');
			
			$ach_routing = $requestdata[ach_routing];
			$ach_account = $requestdata[ach_account];
			$ach_account_type = $requestdata[ach_account_type];
			$name = $requestdata[name];
			$doc_type = $requestdata[doc_type];
			
			$bank_of_first_deposit_name = $this->getConfigData('bank_of_first_deposit_name');
			$bank_of_first_deposit_routing_number = $this->getConfigData('bank_of_first_deposit_routing_number');
			$bank_of_first_deposit_account_number = $this->getConfigData('bank_of_first_deposit_account_number');
			$returning_routing_number = $this->getConfigData('return_routing_number');
			$do_not_add_virtual_endorsement = $this->getConfigData('do_not_add_virtual_endorsement');
			$non_virtual_endorsement = $this->getConfigData('non_virtual_endorsement');	

			$min_order_total = $this->getConfigData('min_order_total');	
			$max_order_total = $this->getConfigData('max_order_total');	
			$black_list = $this->getConfigData('black_list');
			
			$black_list = explode("\n", $black_list);
			$black_list = array_filter($black_list, 'trim');

			$ach_account = str_replace("-","",$ach_account);
			$ach_account = str_pad($ach_account, 17, "0", STR_PAD_LEFT);
							
			foreach ($black_list as $line) {
				$forbiden = explode("|",$line);
				$forbiden_size = sizeof($forbiden);
				if($forbiden_size >= 2)
				{
					if($forbiden[0] == $ach_routing)
					{
						if($forbiden[1] == "*")
						{
							Mage::throwException($this->_wrapGatewayError('Routing/Account number not allowed'));
						}else
						{
							$facct = str_replace("-","",$forbiden[1]);
							$facct = str_pad($facct, 17, "0", STR_PAD_LEFT);
							
							if($facct == $ach_account)
							{
								Mage::throwException($this->_wrapGatewayError('Routing/Account number not allowed'));
							}
						}
					}
				}
				else
				{
					if($forbiden[0] == $ach_routing)					
						Mage::throwException($this->_wrapGatewayError('Routing/Account number not allowed'));
				}
			} 
			
			setlocale(LC_MONETARY, 'en_US');
			if(is_numeric($min_order_total) && $amount < $min_order_total)
				Mage::throwException($this->_wrapGatewayError('Minimum order of ' . money_format('%i', $min_order_total) . ' not satisfied!'));

			if(is_numeric($max_order_total) && $amount > $max_order_total)
				Mage::throwException($this->_wrapGatewayError('Maximum order of ' . money_format('%i', $max_order_total) . ' exceeded!'));
				
			$ret = ':(';
			try {
			
				if(array_key_exists($state,$stateArray))
					$state = $stateArray[$state];
				else
					$state = '';
				
				$client = new Zend_Soap_Client($this->getConfigData('ws_url'));
				$client->setSoapVersion(SOAP_1_2);
				$ret = $client->SingleDraftData(
					array(
						'UserName'=>$ach_user_name,
						'Password'=>$ach_password,
						'ClientCode'=>$client_code,
						'ClientTerminalNo'=>$ach_teminal_number,
						'CheckBankName'=>$ach_bank_name,
						'CheckBankNameFractionalForm'=>'',
						'CheckRoutingNumber'=>$ach_routing,
						'CheckAccountNumber'=>$ach_account,
						'CheckNumber'=>$ach_check_number,
						'CheckDate'=>$ach_check_date,
						'CheckPayTo'=>$ach_check_payto,
						'IsPersonalCheck'=>$ach_account_type == 'CHECKING' ? 'True' : 'False',
						'CheckAmount'=>$amount,
						'CheckMakerLine1'=>$name1 . ' ' . $name2,
						'CheckMakerLine2'=>$addr1,
						'CheckMakerLine3'=>$city . ', ' . $state . ' ' . $zipcode,
						'CheckMakerLine4'=>'',
						'CheckExtraLine1'=>$ach_extra1,
						'CheckExtraLine2'=>$ach_extra2,
						'CheckExtraLine3'=>$ach_extra3,
						'CheckExtraLine4'=>$ach_extra4,
						'CheckExtraLine5'=>$ach_extra5,
						'CheckExtraLine6'=>$ach_extra6,
						'BankOfFirstDepositName'=>$bank_of_first_deposit_name,
						'BankOfFirstDepositRoutingNumber'=>$bank_of_first_deposit_routing_number,
						'BankOfFirstDepositAccountNumber'=>$bank_of_first_deposit_account_number,
						'ReturnRoutingNumber'=>$returning_routing_number,
						'EndorsementDate'=>$ach_check_date,
						'TestMode'=>$mode == 'TEST' ? 'True' : 'False',
						'SourceID'=>$invoice_id,
						'DoNotAddVirtualEndorsement'=>$do_not_add_virtual_endorsement,
						'NonVirtualEndorsementString'=>$non_virtual_endorsement
						)
					);
			}
			catch (Exception $e) {
				Mage::throwException($this->_wrapGatewayError($e->getMessage()));
			}
			$s = (string)$ret->SingleDraftDataResult;
			$s = str_replace('<?xml version="1.0" encoding="utf-16"?>','',$s);
			$retXml = simplexml_load_string($s);
			
			$status = (string)$retXml->StatusCode->{0}=="OK"?"APPROVED" : "ERROR";
			$message = (string)$retXml->StatusMessage->{0};
			$rid = (string)$retXml->SingleDraftRequestID->{0};			
			
			$result->setResult($status)
				->setInvoiceId($invoice_id)
				->setMessage($message)
				->setAuthCode($rid)
				->setAvs(0)
				->setRrno(0)
				->setAmount($amount)
				->setPaymentType($ach_account_type)
				->setOrderId(0)
				->setCvv2('');
				if($status == "ERROR") {
					Mage::throwException($this->_wrapGatewayError($message));					
				}	
			
		} else {
			Mage::throwException(
                Mage::helper('echeck')->__('Invalid routing number.')
            );
		}

        $debugData['result'] = $result->getData();
        $this->_debug($debugData);	
        return $result;
    }
    
	public function validate()
    {
    	$paymentInfo = $this->getInfoInstance();
    	if(strlen($paymentInfo->getCcType()))
        {
        	$paymentInfo = $this->unmapData($paymentInfo);
        }
    	 
         if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
             $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
         } else {
             $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
         }
         if (!$this->canUseForCountry($billingCountry)) {
             Mage::throwException($this->_getHelper()->__('Selected payment type is not allowed for billing country.'));
         }
         
        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',',$this->getConfigData('accounttypes'));

        $accountType = '';

		if (!in_array($info->getEcheckAccountType(), $availableTypes))
		{
            $errorCode = 'echeck_account_type';
            $errorMsg = $this->_getHelper()->__('Account type is not allowed for this payment method '. $info->getAccountType());
        }
        if($errorMsg)
        {
            Mage::throwException($errorMsg);
        }
        return $this;
    }
    
	public function getInfoInstance()
    {
        $instance = $this->getData('info_instance');
        return $instance;
    }
    
	public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $data->setEcheckBankAcctNum4(substr($data->getEcheckBankAcctNum(), -4));
        $info = $this->getInfoInstance();
        $info->setEcheckRoutingNumber($data->getEcheckRoutingNumber())
            ->setEcheckBankName($data->getEcheckBankName())
            ->setData('echeck_account_type', $data->getEcheckAccountType())
            ->setEcheckAccountName($data->getEcheckAccountName())
            ->setEcheckBankAcctNum($data->getEcheckBankAcctNum())
            ->setEcheckBankAcctNum4($data->getEcheckBankAcctNum4())
			->setEcheckCheckNumber($data->getEcheckCheckNumber())
			->setEcheckCheckDate($data->getEcheckCheckDate())
			;
        
        $this->mapData($data);
        return $this;
    }

    public function mapData($data)
    {
    	if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setCcLast4($data->getEcheckRoutingNumber())
            ->setCcNumberEnc($data->getEcheckBankName())
            ->setCcType($data->getEcheckAccountType())
            ->setCcOwner($data->getEcheckAccountName())
            ->setCcSsIssue($data->getEcheckBankAcctNum())		
            ->setCcSsOwner($data->getEcheckBankAcctNum4());
			
			$info->setAdditionalInformation('EcheckCheckNumber',$data->getEcheckCheckNumber());
			$info->setAdditionalInformation('EcheckCheckDate',$data->getEcheckCheckDate());

    }
    
	public function unmapData($info)
    {
        $info->setEcheckRoutingNumber($info->getCcLast4())
            ->setEcheckBankName($info->getCcNumberEnc())
            ->setEcheckAccountType($info->getCcType())
            ->setEcheckAccountName($info->getCcOwner())
            ->setEcheckBankAcctNum($info->getCcSsIssue())
            ->setEcheckBankAcctNum4($info->getCcSsOwner())
			->setEcheckCheckNumber($info->getAdditionalInformation('EcheckCheckNumber'))
            ->setEcheckCheckDate($info->getAdditionalInformation('EcheckCheckDate'));
			
            return $info;
    }
    
    public function prepareSave()
    {
        $info = $this->getInfoInstance();
        $info->setCcSsIssue(null);
        return $this;
    }
	
	public function isAvailable($quote = null){  
		$checkResult = new StdClass;  
	$checkResult->isAvailable = (bool)(int)$this->getConfigData('active', ($quote ? $quote->getStoreId() : null));  
	Mage::dispatchEvent('payment_method_is_active', array(  
	'result' => $checkResult,  
	'method_instance' => $this,  
	'quote' => $quote,  
	));  
	return $checkResult->isAvailable;  
	} 
    
	protected final function calcTPS(Varien_Object $payment) {
	
		$order = $payment->getOrder();
		$billing = $order->getBillingAddress();

		$hashstr = $this->getConfigData('trans_key') . $this->getConfigData('login') . 
		$payment->getTransactionType() . $payment->getAmount() . $payment->getRrno() . 
		$this->getConfigData('test_mode');
	
		return bin2hex( md5($hashstr, true) );
	}	
 
	protected function parseHeader($header, $nameVal, $pos) {
		$nameVal = ($nameVal == 'name') ? '0' : '1';
		$s = explode("?", $header);
		$t = explode("&", $s[1]);
		$value = explode("=", $t[$pos]);
		return $value[$nameVal];
	}
}
