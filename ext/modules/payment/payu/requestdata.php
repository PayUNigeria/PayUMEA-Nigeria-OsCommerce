<?php
/**
 * Created by PhpStorm.
 * User: netcraft
 * Date: 4/8/16
 * Time: 10:12 PM
 */
class PayURequestData
{
    private $payuInterface = null;

    public function setPayuInterface($api)
    {
        $this->payuInterface = $api;
    }

    public function loadRequestData() {
        $api = $this->payuInterface;
        $paymentMethod = $api->getPaymentMethod();

        if($paymentMethod->check() && $paymentMethod->enabled) {
            $data = array(
                'Api' => $api->getApiVersion(),
                'Safekey' => trim($api->getSafeKey()),
                'TransactionType' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_METHOD,
                'AdditionalInformation' => [
                    'merchantReference' => MODULE_PAYMENT_PAYU_EASYPLUS_MERCHANT_REFERENCE,
                    'supportedPaymentMethods' => MODULE_PAYMENT_PAYU_EASYPLUS_PAYMENT_METHODS,
                    'demoMode' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER == 'Live' ? 'false' : 'true',
                    'secure3d' => MODULE_PAYMENT_PAYU_EASYPLUS_SECURE3D ? 'true' : 'false',
                    'returnUrl' => tep_href_link('ext/modules/payment/payu/redirect.php', 'osC_Action=retrieve',
                        'SSL', true, false),
                    'cancelUrl' => tep_href_link('ext/modules/payment/payu/redirect.php', 'osC_Action=cancel', 'SSL', true, false),
                    'redirectChannel' => MODULE_PAYMENT_PAYU_EASYPLUS_PAGE_STYLE
                ],
                //Customer details
                'Customer' => $api->getParameter()['Customer'],
                // Cart details
                'Basket' => $api->getParameter()['Basket'],
                // Custom data
                'CustomFields' => $api->getParameter()['CustomFields']
            );
            
            PayuEasyPlusApi::setPayURequestData($data);
        } else {
            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
        }
    }

    public function getVar($var) {
        $this->load();
        return $this->{'_' . $var};
    }

    public function setVar($var, $val) {
        $this->{'_' . $var} = $val;
    }
}