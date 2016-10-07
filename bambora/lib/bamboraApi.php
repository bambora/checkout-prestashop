<?php
include('bamboraModels.php');
include('bamboraEndpoints.php');

class BamboraApi
{    
    private $apiKey = "";

    function __construct($apiKey = "")
    {
        $this->apiKey = $apiKey;
    }

    //Helpers
    public static function getPropertyOfServiceResult($result, $objectName ,$propertyName)
    {      
        $json = json_decode($result, true);
        $val =  $json[$objectName][$propertyName];
        return $val; 
    }

    public function convertJSonResultToArray($result, $elementName)
    {
        $json = json_decode($result, true);
        $transaction = $json[$elementName];
        $res = array();            
         
        if ($transaction == null)
            return null;

        $properties = array_keys($transaction);

        foreach($properties as $attr )
        {
            $res[$attr] =  $transaction[$attr];
        }
        return $res;   
    }
    
    //API Methodes

    public function getcheckoutresponse($bamboracheckoutrequest)
    {   
        $serviceUrl = BamboraendpointConfig::getCheckoutEndpoint().'/checkout' ;

        $jsonData = json_encode($bamboracheckoutrequest);
        $expresscheckoutresponse = $this ->_callRestService($serviceUrl, $jsonData, "POST");

        return $expresscheckoutresponse;
    }

    public function getcheckoutpaymentwindowjs()
    {
        $url = BamboraendpointConfig::getCheckoutAssets().'/paymentwindow-v1.min.js';

        return $url;   
    }

    public function capture($transactionid, $amount, $currency)
	{          
        $serviceUrl = BamboraendpointConfig::getTransactionEndpoint().'/transactions/'.  sprintf('%.0F',$transactionid) . '/capture';             

        $data = array();
        $data["amount"] = $amount;
        $data["currency"] = $currency;
        
        $jsonData = json_encode($data);
        
        $result = $this->_callRestService($serviceUrl, $jsonData, "POST");
        return $result;
        
	}

    public function credit($transactionid, $amount, $currency)
	{         
        $serviceUrl = BamboraendpointConfig::getTransactionEndpoint().'/transactions/'.  sprintf('%.0F',$transactionid) . '/credit';             

        $data = array();
        $data["amount"] = $amount;
        $data["currency"] = $currency; 

        $jsonData = json_encode($data);
        
        $result = $this->_callRestService($serviceUrl, $jsonData, "POST");
        return $result;        
	}

    public function delete($transactionid)
	{                  
        $serviceUrl = BamboraendpointConfig::getTransactionEndpoint().'/transactions/'.  sprintf('%.0F',$transactionid) . '/delete';             
        
        $result = $this->_callRestService($serviceUrl, null, "POST");
        return $result;    
    }

    public function gettransaction($transactionid)
	{ 
        $serviceUrl = BamboraendpointConfig::getMerchantEndpoint().'/transactions/'. sprintf('%.0F',$transactionid);                         
        
        $result = $this->_callRestService($serviceUrl, null, "GET");
        return json_decode($result,true);    
	}
    
    public function gettransactionoperations($transactionid)
    {           
        $serviceUrl = BamboraendpointConfig::getMerchantEndpoint().'/transactions/'. sprintf('%.0F',$transactionid) .'/transactionoperations';             

        $result = $this->_callRestService($serviceUrl, null, "GET");
        return json_decode($result,true);    
    }

    public function getPaymentTypes($currency, $amount)
    {   
        $serviceUrl = BamboraendpointConfig::getMerchantEndpoint().'/paymenttypes?currency='. $currency .'&amount='.$amount;

        
        $result = $this->_callRestService($serviceUrl, null, "GET");
        return $result;        
    }


    public function getAvaliablePaymentcardidsForMerchant($currency, $amount)
    {
        $res = array();
        $serviceRes = $this -> getPaymentTypes($currency, $amount);

        $availablePaymentTypesResjson = json_decode($serviceRes, true);
        if ($availablePaymentTypesResjson['meta']['result'] == true)
        {
            foreach($availablePaymentTypesResjson['paymentcollections'] as $payment )
            {                  
                foreach($payment['paymentgroups'] as $card)
                {                         
                    //enshure unique id:
                    $cardname = $card['id'];
                    $res[$cardname] = $card['id'];              
                }
            }

            ksort($res);
        }
        return $res;               
    }


    private function _callRestService($serviceUrl,  $jsonData, $postOrGet)
    {    
        $headers = array(
            'Content-Type: application/json',
            'Content-Length: '.strlen(@$jsonData),
            'Accept: application/json',
            'Authorization: '.$this->apiKey,
            'X-EPay-System: ' . $this->getModuleHeaderInfo()
        );
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST,$postOrGet);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($curl, CURLOPT_URL, $serviceUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($curl);
        return $result;        
    }

    private function getModuleHeaderInfo() 
    {
        $bamboraVersion = Bambora::MODULE_VERSION;
        $prestashopVersion = _PS_VERSION_;
        $result = 'Magento/' . $prestashopVersion . ' Module/' . $bamboraVersion;
        return $result;

    }
}
