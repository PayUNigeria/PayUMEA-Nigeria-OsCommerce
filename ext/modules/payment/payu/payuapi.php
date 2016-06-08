<?php
/**
 * Created by PhpStorm.
 * User: netcraft
 * Date: 4/8/16
 * Time: 10:11 PM
 */
class PayUEasyPlusApi
{
    protected $cart;
    protected $order;
    protected $total;
    protected static $requestData;
    protected $responseData;
    protected $paymentInfo;
    protected $payu_easyplus;
    protected $params;

    protected static $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private static $soapClient = null;

    // @var string The base sandbox URL for the PayU API endpoint.
    protected static $sandboxUrl = 'https://staging.payu.co.za/service/PayUAPI';
    protected static $sandboxCheckoutUrl = 'https://staging.payu.co.za/rpp.do';

    // @var string The base live URL for the PayU API endpoint.
    protected static $liveUrl = 'https://secure.payu.co.za/service/PayUAPI';
    protected static $liveCheckoutUrl = 'https://secure.payu.co.za/rpp.do';

    // @var string The PayU safe key to be used for requests.
    protected $safeKey;

    // @var string|null The version of the PayU API to use for requests.
    protected static $apiVersion = 'ONE_ZERO';

    protected static $username = '';

    protected static $password = '';

    protected $merchantRef = '';

    protected $payuReference = '';

    protected static $soapUrl = '';
    protected static $checkoutUrl = '';

    function PayuEasyPlusApi() {
        $this->init();
    }

    /**
     * @return string The safe key used for requests.
     */
    public function getSafeKey()
    {
        return $this->safeKey;
    }

    /**
     * Sets the safe key to be used for requests.
     *
     * @param string $safeKey
     */
    public function setSafeKey($safeKey)
    {
        $this->safeKey = $safeKey;

        return $this;
    }

    /**
     * @return string The API version used for requests. null if we're using the
     *    latest version.
     */
    public static function getApiVersion()
    {
        return self::$apiVersion;
    }

    /**
     * @return string The soap user used for requests.
     */
    public static function getUsername()
    {
        return self::$username;
    }

    /**
     * Sets the soap username to be used for requests.
     *
     * @param string $username
     */
    public static function setUsername($username)
    {
        self::$username = $username;
    }

    /**
     * @return string The soap password used for requests.
     */
    public static function getPassword()
    {
        return self::$password;
    }

    /**
     * Sets the soap password to be used for requests.
     *
     * @param string $password
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    /**
     * @return string The merchant reference to identify captured payments..
     */
    public function getMerchantReference()
    {
        return $this->reference;
    }

    /**
     * Sets the merchant reference to identify captured payments.
     *
     * @param string $reference
     */
    public function setMerchantReference($reference)
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return string The reference from PayU.
     */
    public function getPayUReference()
    {
        return $this->payuReference;
    }

    /**
     * Sets the PayU reference.
     *
     * @param string $reference
     */
    public function setPayUReference($reference)
    {
        $this->payuReference = $reference;

        return $this;
    }

    /**
     * @return string The soap wsdl endpoint to send requests.
     */
    public static function getSoapEndpoint()
    {
        return self::$soapUrl;
    }

    /**
     * @return string The redirect payment page url to be used for requests.
     */
    public static function getCheckoutUrl()
    {
        return self::$checkoutUrl;
    }

    public function getPaymentMethod()
    {
        return $this->payu_easyplus;
    }

    public function getPaymentInfo()
    {
        return $this->paymentInfo;
    }

    /**
     * Sets the redirect payment page url to be used for requests.
     *
     * @param string $gateway
     */
    public static function setGatewayEndpoint($gateway)
    {
        if($gateway == 'Sandbox') {
            self::$soapUrl = self::$sandboxUrl;
            self::$checkoutUrl = self::$sandboxCheckoutUrl;
        } else {
            self::$soapUrl = self::$liveUrl;
            self::$checkoutUrl = self::$liveCheckoutUrl;
        }
    }

    public function getTransactionInfo()
    {
        require_once(DIR_FS_CATALOG . 'ext/modules/payment/payu/responsedata.php');

        $data = array();
        $data['Api'] = self::getApiVersion();
        $data['Safekey'] = $this->getSafeKey();
        $data['AdditionalInformation']['payUReference'] = $this->getPayUReference();
        
        $result = self::getSoapSingleton()->getTransaction($data);
        $this->paymentInfo = json_decode(json_encode($result), true);

        $responseData = new PayUResponseData();
        $responseData->setPayuInterface($this);
        return $responseData->load();
    }

    public static function setPayURequestData($txn_data) 
    {
        self::$requestData = $txn_data;
    }

    public static function getPayURequestData () 
    {
        return self::$requestData;
    }     

    public function setParameter($params)
    {
        $this->params = $params;

        return $this;
    }

    public function getParameter()
    {
        return $this->params;
    }

    public function postPaymentRequest()
    {
        $data = self::getPayURequestData();
        $response = self::getSoapSingleton()->setTransaction($data);
        
        return json_decode(json_encode($response), true);
    }

    public function preparePaymentRequest() 
    {
        require_once(DIR_FS_CATALOG . 'ext/modules/payment/payu/requestdata.php');

        $requestData = new PayURequestData();
        $requestData->setPayuInterface($this);
        $requestData->loadRequestData();

        return $this;
    }

    private static function getSoapHeader()
    {
        $header  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $header .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $header .= '<wsse:Username>'.self::getUsername().'</wsse:Username>';
        $header .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.self::getPassword().'</wsse:Password>';
        $header .= '</wsse:UsernameToken>';
        $header .= '</wsse:Security>';

        return $header;
    }
    private static function getSoapSingleton()
    {
        if(is_null(self::$soapClient))
        {
            $header = self::getSoapHeader();
            $soapWsdlUrl = self::getSoapEndpoint().'?wsdl';
            self::$soapUrl = $soapWsdlUrl;

            $headerbody = new \SoapVar($header, XSD_ANYXML, null, null, null);
            $soapHeader = new \SOAPHeader(self::$ns, 'Security', $headerbody, true);

            self::$soapClient = new \SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
            self::$soapClient->__setSoapHeaders($soapHeader);
        }
        return self::$soapClient;
    }

    public function init()
    {
        require_once(DIR_WS_MODULES . 'payment/payu_easyplus.php');
        $payu_easyplus = new payu_easyplus();

        if($payu_easyplus->check() && $payu_easyplus->enabled) {
            $this->setSafeKey(trim(MODULE_PAYMENT_PAYU_EASYPLUS_SAFE_KEY));
            $this->setUsername(trim(MODULE_PAYMENT_PAYU_EASYPLUS_API_USERNAME));
            $this->setPassword(trim(MODULE_PAYMENT_PAYU_EASYPLUS_API_PASSWORD));
            $this->setGatewayEndpoint(MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER);
        } else {
            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
        }
        $this->payu_easyplus = $payu_easyplus;
    }
}