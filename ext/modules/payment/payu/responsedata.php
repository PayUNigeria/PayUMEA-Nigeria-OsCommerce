<?php
/**
 * Created by PhpStorm.
 * User: netcraft
 * Date: 4/8/16
 * Time: 10:12 PM
 */
class PayUResponseData implements \IteratorAggregate
{
	private $payuInterface = null;
	private $data = '';

	public $successful = false;
	public $transaction_state = '';
	public $transaction_type = '';
	public $result_code = '';
	public $result_message = '';
	public $payu_reference = '';
	public $amount = '';
	public $currency_code = '';
	public $transaction_id = '';
	public $gateway_reference = '';

    public function setPayUInterface($api)
    {
        $this->payuInterface = $api;
        $this->data = $api->getPaymentInfo();
    }

    public function load() {

		if (!empty($this->data)) {
			$data = $this->data;
            
			// payment information
			$this->successful = $data['return']['successful'];
			$this->transaction_state = $data['return']['transactionState'];
			$this->transaction_type = $data['return']['transactionType'];
			$this->result_code = $data['return']['resultCode'];
			$this->result_message = $data['return']['resultMessage'];
			$this->payu_reference = $data['return']['payUReference'];
			$this->amount = (float)($data['return']['paymentMethodsUsed']['amountInCents'] / 100);
			$this->currency_code = $data['return']['basket']['currencyCode'];
			$this->gateway_reference = $data['return']['paymentMethodsUsed']['gatewayReference'];
		}
        return $this;
	}

	public function getVar($var) {
		$this->load();
		return $this->{$var};
	}

	public function setVar($var, $val) {
		$this->{$var} = $val;
	}

	public function getIterator() {
		return new \ArrayIterator($this);
	}
}
