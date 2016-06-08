<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/
class payu_easyplus
{
	var $code, $title, $description, $enabled;

    function payu_easyplus()
   	{
        global $HTTP_GET_VARS, $PHP_SELF, $order, $payment;

        $this->signature = 'payu|payu_easyplus|1.0';
        $this->api_version = 'ONE_ZERO';

        $this->code = 'payu_easyplus';
        $this->title = MODULE_PAYMENT_PAYU_EASYPLUS_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_PAYU_EASYPLUS_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_PAYU_EASYPLUS_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_PAYU_EASYPLUS_SORT_ORDER') ?
        	MODULE_PAYMENT_PAYU_EASYPLUS_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_PAYU_EASYPLUS_STATUS')
        	&& (MODULE_PAYMENT_PAYU_EASYPLUS_STATUS == 'True') ? true : false;
        $this->order_status = defined('MODULE_PAYMENT_PAYU_EASYPLUS_ORDER_STATUS_ID')
        	&& ((int)MODULE_PAYMENT_PAYU_EASYPLUS_ORDER_STATUS_ID > 0) ?
        		(int)MODULE_PAYMENT_PAYU_EASYPLUS_ORDER_STATUS_ID : 0;

        if (defined('MODULE_PAYMENT_PAYU_EASYPLUS_STATUS')) {
           	if (MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER == 'Sandbox') {
                $this->title .= ' [Sandbox]';
                $this->public_title .= ' (Sandbox)';
            }

            $this->description .= $this->getTestLinkInfo();
        }

        if (!extension_loaded('soap')) {
            $this->description = '<div class="secWarning">' . MODULE_PAYMENT_PAYU_EASYPLUS_ERROR_ADMIN_SOAP . '</div>' . $this->description;

            $this->enabled = false;
        }

        if ($this->enabled === true) {
            if (!tep_not_null(MODULE_PAYMENT_PAYU_EASYPLUS_SAFE_KEY)
            	|| !tep_not_null(MODULE_PAYMENT_PAYU_EASYPLUS_API_USERNAME)
            	|| !tep_not_null(MODULE_PAYMENT_PAYU_EASYPLUS_API_PASSWORD)
            	|| !tep_not_null(MODULE_PAYMENT_PAYU_EASYPLUS_MERCHANT_REFERENCE)
            ) {
            	$this->description = '<div class="secWarning">' . MODULE_PAYMENT_PAYU_EASYPLUS_ERROR_ADMIN_CONFIGURATION . '</div>' . $this->description;

                $this->enabled = false;
            }
        }

        if ($this->enabled === true) {
            if (isset($order) && is_object($order)) {
                $this->update_status();
            }
    	}

		// When changing the shipping address due to no shipping rates being available, head straight to the checkout confirmation page
      	if (defined('FILENAME_CHECKOUT_PAYMENT')
      		&& (basename($PHP_SELF) == FILENAME_CHECKOUT_PAYMENT)
      		&& tep_session_is_registered('pep_right_turn')
      	) {
        	tep_session_unregister('pep_right_turn');

	        if (
	        	tep_session_is_registered('payment')
	        	&& ($payment == $this->code)
	        ) {
	        	tep_redirect(tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'));
	        }
    	}

        if (defined('FILENAME_MODULES')
        	&& ($PHP_SELF == FILENAME_MODULES)
        	&& isset($HTTP_GET_VARS['action'])
        	&& ($HTTP_GET_VARS['action'] == 'install')
        	&& isset($HTTP_GET_VARS['subaction'])
        	&& ($HTTP_GET_VARS['subaction'] == 'conntest')
        ) {
        	echo $this->getTestConnectionResult();
        	exit;
        }
    }

    function update_status()
    {
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_PAYU_EASYPLUS_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYU_EASYPLUS_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        return array(
        	'id' => $this->code,
            'module' => $this->public_title
        );
    }

    function pre_confirmation_check()
    {
        global $pep_reference, $messageStack, $order;
        
        if (!tep_session_is_registered('pep_reference')) {
            tep_redirect(tep_href_link('ext/modules/payment/payu/redirect.php', '', 'SSL'));
        }

        $response = $this->getPayUTransaction($pep_reference);

        if ($response->getVar('successful') !== true && $response->getVar('transaction_state') != 'SUCCESSFUL') {
            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, 'error_message=' . stripslashes($response->getVar('result_message')), 'SSL'));
        }

        if (tep_session_is_registered('pep_order_total_check')) {
            $messageStack->add(
            	'checkout_confirmation',
            	'<span id="PayUNotice">' . MODULE_PAYMENT_PAYU_EASYPLUS_NOTICE_CHECKOUT_CONFIRMATION . '</span>
            	<script>
            		$("#PayUNotice")
            		.parent()
            		.css({backgroundColor: "#fcf8e3", border: "1px #faedd0 solid", color: "#a67d57", padding: "5px" });
            	</script>', 'payu'
            );
        }

        $order->info['payment_method'] = '<img src="images/payu_logo.png" border="0" alt="PayU secure payments" style="padding: 3px;" />';
    }

    function confirmation()
    {
        global $comments;

        if (!isset($comments)) {
            $comments = null;
        }

        $confirmation = false;

        if (empty($comments)) {
            $confirmation = array(
            	'fields' => array(
            		array(
            			'title' => MODULE_PAYMENT_PAYU_EASYPLUS_TEXT_COMMENTS,
                        'field' => tep_draw_textarea_field('pepcomments', 'soft', '60', '5', $comments)
                	)
          		)
           	);
        }

        return $confirmation;
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        global $order, $pep_reference, $pep_order_total_check, $comments;

        if (!tep_session_is_registered('pep_reference')) {
            tep_redirect(tep_href_link('ext/modules/payment/payu/redirect.php', '', 'SSL'));
        }

        $response = $this->getPayUTransaction($pep_reference);
        
        if ($response->getVar('successful') === true && $response->getVar('transaction_state') == 'SUCCESSFUL') {
        	if (($response->getVar('amount') != $this->format_raw($order->info['total']))
            	&& !tep_session_is_registered('pep_order_total_check')
           	) {
                tep_session_register('pep_order_total_check');
                $pep_order_total_check = true;

                tep_redirect(tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'));
            }
        } else {
            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, 'error_message=' . stripslashes($response->getVar
                ('result_message')), 'SSL'));
        }

        if (tep_session_is_registered('pep_order_total_check')) {
            tep_session_unregister('pep_order_total_check');
        }

        if (empty($comments)) {
            if (tep_not_null($response->getVar('result_message'))
            ) {
                $comments = tep_db_prepare_input($response->getVar('result_message'));

                $order->info['comments'] = $comments;
            }
        }

        if (($response->getVar('successful') !== true)) {

            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, 'error_message=' . stripslashes($response->getVar('result_message')), 'SSL'));
        }
    }

    function after_process()
    {
        global $pep_reference, $insert_id;

        $response = $this->getPayUTransaction($pep_reference);
        
        $pp_result =
        	'PayU Reference: ' . tep_output_string_protected($response->getVar('payu_reference')) . "\n"
            . 'Gateway Reference: ' . tep_output_string_protected($response->getVar('gateway_reference')) . "\n"
        	. 'Payment Status: ' . tep_output_string_protected($response->getVar('transaction_state')) . "\n"
        	. 'Payment Type: ' . tep_output_string_protected($response->getVar('transaction_type')) . "\n"
        	. 'Response Message: ' . tep_output_string_protected($response->getVar('result_message')) . "\n"
        	. 'Response Code: ' . tep_output_string_protected($response->getVar('result_code'));

        $sql_data_array = array(
        	'orders_id' => $insert_id,
           	'orders_status_id' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTIONS_ORDER_STATUS_ID,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => $pp_result
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        tep_session_unregister('pep_reference');
    }

    function get_error()
    {
        return false;
    }

    function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYU_EASYPLUS_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }

        return $this->_check;
    }

    function install($parameter = null)
    {
        $params = $this->getParams();

    	if (isset($parameter)) {
            if (isset($params[$parameter])) {
                $params = array($parameter => $params[$parameter]);
            } else {
                $params = array();
            }
        }

        foreach ($params as $key => $data) {
        	$sql_data_array = array(
        		'configuration_title' => $data['title'],
                'configuration_key' => $key,
                'configuration_value' => (isset($data['value']) ? $data['value'] : ''),
                'configuration_description' => $data['desc'],
                'configuration_group_id' => '6',
                'sort_order' => '0',
              	'date_added' => 'now()'
            );

            if (isset($data['set_func'])) {
                $sql_data_array['set_function'] = $data['set_func'];
            }

            if (isset($data['use_func'])) {
                $sql_data_array['use_function'] = $data['use_func'];
            }

           	tep_db_perform(TABLE_CONFIGURATION, $sql_data_array);
        }
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        $keys = array_keys($this->getParams());

        if ($this->check()) {
        	foreach ($keys as $key) {
                if (!defined($key)) {
                    $this->install($key);
                }
            }
        }

        return $keys;
    }

    function getParams()
    {
        if (!defined('MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTIONS_ORDER_STATUS_ID')) {
            $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'PayU [Transactions]' limit 1");

            if (tep_db_num_rows($check_query) < 1) {
                $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
                $status = tep_db_fetch_array($status_query);

                $status_id = $status['status_id']+1;

                $languages = tep_get_languages();

                foreach ($languages as $lang) {
                	tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', 'PayU [Transactions]')");
                }

                $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
                if (tep_db_num_rows($flags_query) == 1) {
                    tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where
                     orders_status_id = '" . $status_id . "'");
                }
            } else {
                $check = tep_db_fetch_array($check_query);

                $status_id = $check['orders_status_id'];
            }
        } else {
            $status_id = MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTIONS_ORDER_STATUS_ID;
        }

        $params = array(
        	'MODULE_PAYMENT_PAYU_EASYPLUS_STATUS' => array(
	            'title' => 'Enable PayU Easy Plus Checkout',
	            'desc' => 'Do you want to accept PayU Easy Plus payments?',
	            'value' => 'True',
	            'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_SAFE_KEY' => array(
            	'title' => 'Safe Key',
                'desc' => 'The safe key for the merchant store',
                'value' => '{CE62CE80-0EFD-4035-87C1-8824C5C46E7F}',
            ),
           	'MODULE_PAYMENT_PAYU_EASYPLUS_API_USERNAME' => array(
           		'title' => 'API Username',
                'desc' => 'The username to use for the PayU API service.',
                'value' => '100032',
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_API_PASSWORD' => array(
            	'title' => 'API Password',
                'desc' => 'The password to use for the PayU API service.',
                'value' => 'PypWWegU',
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_MERCHANT_REFERENCE' => array(
            	'title' => 'Merchant reference',
                'desc' => 'The merchant reference for this store',
                'value' => 'osCommerce_dev_store'
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_PAGE_STYLE' => array(
            	'title' => 'Page Style',
            	'desc' => 'The page style to use for the checkout flow',
            	'value' => 'responsive',
                'set_func' => 'tep_cfg_select_option(array(\'responsive\', \'web\', \'mobi\'), '
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_METHOD' => array(
            	'title' => 'Transaction Method',
                'desc' => 'The processing method to use for each transaction.',
                'value' => 'PAYMENT',
                'set_func' => 'tep_cfg_select_option(array(\'RESERVE\', \'PAYMENT\'), '
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_ORDER_STATUS_ID' => array(
            	'title' => 'Set Order Status',
               	'desc' => 'Set the status of orders made with this payment module to this value',
                'value' => '2',
                'use_func' => 'tep_get_order_status_name',
              	'set_func' => 'tep_cfg_pull_down_order_statuses('
            ),
           	'MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTIONS_ORDER_STATUS_ID' => array(
           		'title' => 'PayU Transactions Order Status Level',
                'desc' => 'Include PayU transaction information in this order status level.',
                'value' => $status_id,
                'use_func' => 'tep_get_order_status_name',
                'set_func' => 'tep_cfg_pull_down_order_statuses('
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_ZONE' => array(
            	'title' => 'Payment Zone',
               	'desc' => 'If a zone is selected, only enable this payment method for that zone.',
                'value' => '0',
                'use_func' => 'tep_get_zone_class_title',
               	'set_func' => 'tep_cfg_pull_down_zone_classes('
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER' => array(
            	'title' => 'Transaction Server',
                'desc' => 'Use the live or testing (sandbox) gateway server to process transactions?',
               	'value' => 'Sandbox',
                'set_func' => 'tep_cfg_select_option(array(\'Live\', \'Sandbox\'), '
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_SECURE3D' => array(
            	'title' => 'Secure 3d authentication',
               	'desc' => 'Enable secure 3d for credit cards payments?',
                'value' => 'True',
                'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_PAYMENT_METHODS' => array(
            	'title' => 'Payment methods',
               	'desc' => 'Select supported payment methods.',
               	'value' => 'CREDITCARD',
               	'set_func' => 'tep_cfg_select_option(array(\'CREDITCARD\'), '
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_PAYMENT_CURRENCY' => array(
            	'title' => 'Payment current',
               	'desc' => 'Select supported payment currency.',
               	'value' => 'ZAR',
               	'set_func' => 'tep_cfg_select_option(array(\'ZAR\', \'NGN\'), '
            ),
          	'MODULE_PAYMENT_PAYU_EASYPLUS_DEBUG_EMAIL' => array(
          		'title' => 'Debug E-Mail Address',
               	'desc' => 'All parameters of an invalid transaction will be sent to this email address.',
               	'value' => STORE_OWNER_EMAIL_ADDRESS,
            ),
            'MODULE_PAYMENT_PAYU_EASYPLUS_SORT_ORDER' => array(
            	'title' => 'Sort order of display',
                'desc' => 'Sort order of display. Lowest is displayed first.',
                'value' => '0'
            )
        );

        return $params;
   	}

	// format prices without currency formatting
    function format_raw($number, $currency_code = '', $currency_value = '')
    {
        global $currencies, $currency;

        if (empty($currency_code) || !$currencies->is_set($currency_code)) {
            $currency_code = $currency;
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    function setPayUTransaction($parameters)
    {
        if (!class_exists('PayUEasyPlusApi')) {
            if (file_exists(DIR_FS_CATALOG . 'ext/modules/payment/payu/payuapi.php'))
            require(DIR_FS_CATALOG . 'ext/modules/payment/payu/payuapi.php');
        }
        $payuEasyPlusApi = new PayuEasyPlusApi();
        $response_array = $payuEasyPlusApi
            ->setParameter($parameters)
            ->preparePaymentRequest()
            ->postPaymentRequest();

        if (($response_array['return']['successful'] != true)) {
            $this->sendDebugEmail($response_array);
        }

        return $response_array;
    }

    function getPayUTransaction($token)
    {
        if (!class_exists('PayUEasyPlusApi')) {
            if (file_exists(DIR_FS_CATALOG . 'ext/modules/payment/payu/payuapi.php'))
                require(DIR_FS_CATALOG . 'ext/modules/payment/payu/payuapi.php');
        }
        $payuEasyPlusApi = new PayUEasyPlusApi();
        $payuEasyPlusApi->setPayUReference($token);

        $response = $payuEasyPlusApi->getTransactionInfo();

        if (!$response->getVar('successful')) {
            $this->sendDebugEmail($response);
        }

        return $response;
    }

    function getProductType($id, $attributes)
    {
        foreach ($attributes as $a) {
            $virtual_check_query = tep_db_query("select pad.products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad where pa.products_id = '" . (int)$id . "' and pa.options_values_id = '" . (int)$a['value_id'] . "' and pa.products_attributes_id = pad.products_attributes_id limit 1");

            if (tep_db_num_rows($virtual_check_query) == 1) {
                return 'Digital';
            }
        }

        return 'Physical';
    }

    function sendDebugEmail($response)
    {
        global $HTTP_POST_VARS, $HTTP_GET_VARS;

        if (tep_not_null(MODULE_PAYMENT_PAYU_EASYPLUS_DEBUG_EMAIL)) {
            $email_body = '';

            if (!empty($response)) {
                $email_body .= 'RESPONSE:' . "\n\n" . print_r((array)$response, true) . "\n\n";
            }

            if (!empty($HTTP_POST_VARS)) {
                $email_body .= '$HTTP_POST_VARS:' . "\n\n" . print_r($HTTP_POST_VARS, true) . "\n\n";
            }

            if (!empty($HTTP_GET_VARS)) {
                $email_body .= '$HTTP_GET_VARS:' . "\n\n" . print_r($HTTP_GET_VARS, true) . "\n\n";
            }

            if (!empty($email_body)) {
                tep_mail('', MODULE_PAYMENT_PAYU_EASYPLUS_DEBUG_EMAIL, 'PayU Easy Plus secure payments Debug E-Mail', trim($email_body), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
            }
        }
    }

    function getTestLinkInfo()
    {
        $dialog_title = MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_TITLE;
        $dialog_button_close = MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_BUTTON_CLOSE;
        $dialog_success = MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_SUCCESS;
        $dialog_failed = MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_FAILED;
        $dialog_error = MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_ERROR;
        $dialog_connection_time = MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_TIME;

        $test_url = tep_href_link(
        	FILENAME_MODULES, 'set=payment&module=' . $this->code . '&action=install&subaction=conntest'
        );

        $js = <<<EOD
<script type="text/javascript">
$(function() {
  $('#tcdprogressbar').progressbar({
    value: false
  });
});

function openTestConnectionDialog() {
  var d = $('<div>').html($('#testConnectionDialog').html()).dialog({
    modal: true,
    title: '{$dialog_title}',
    buttons: {
      '{$dialog_button_close}': function () {
        $(this).dialog('destroy');
      }
    }
  });

  var timeStart = new Date().getTime();

  $.ajax({
    url: '{$test_url}'
  }).done(function(data) {
    if ( data == '1' ) {
      d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: green;">{$dialog_success}</p>');
    } else {
      d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: red;">{$dialog_failed}</p>');
    }
  }).fail(function() {
    d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: red;">{$dialog_error}</p>');
  }).always(function() {
    var timeEnd = new Date().getTime();
    var timeTook = new Date(0, 0, 0, 0, 0, 0, timeEnd-timeStart);

    d.find('#testConnectionDialogProgress').append('<p>{$dialog_connection_time} ' + timeTook.getSeconds() + '.' + timeTook.getMilliseconds() + 's</p>');
  });
}
</script>
EOD;

        $info = '<p><img src="images/icons/locked.gif" border="0">&nbsp;<a href="javascript:openTestConnectionDialog();" style="text-decoration: underline; font-weight: bold;">' . MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_LINK_TITLE . '</a></p>' .
              '<div id="testConnectionDialog" style="display: none;"><p>';

        if (MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER == 'Live') {
            $info .= 'Live Server:<br />https://secure.payu.co.za/rpp.do';
        } else {
            $info .= 'Sandbox Server:<br />https://staging.payu.co.za/rpp.do';
        }

        $info .= '</p><div id="testConnectionDialogProgress"><p>' . MODULE_PAYMENT_PAYU_EASYPLUS_DIALOG_CONNECTION_GENERAL_TEXT . '</p><div id="tcdprogressbar"></div></div></div>' . $js;

        return $info;
    }

    function getTestConnectionResult()
    {
        $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $header  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $header .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $header .= '<wsse:Username>'.MODULE_PAYMENT_PAYU_EASYPLUS_API_USERNAME.'</wsse:Username>';
        $header .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' .MODULE_PAYMENT_PAYU_EASYPLUS_API_PASSWORD .'</wsse:Password>';
        $header .= '</wsse:UsernameToken>';
        $header .= '</wsse:Security>';

        if (MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER == 'Live') {
            $soapUrl = 'https://secure.payu.co.za/service/PayUAPI';
        } else {
            $soapUrl = 'https://staging.payu.co.za/service/PayUAPI';
        }

        $soapWsdlUrl = $soapUrl . '?wsdl';
        $headerbody = new \SoapVar($header, XSD_ANYXML, null, null, null);
        $soapHeader = new \SOAPHeader($ns, 'Security', $headerbody, true);

        $soapClient = new \SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
        $soapClient->__setSoapHeaders($soapHeader);

        $params = array(
            'Api' => $this->api_version,
            'Safekey' => MODULE_PAYMENT_PAYU_EASYPLUS_SAFE_KEY,
            'TransactionType' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_METHOD,
            'AdditionalInformation' => array(
                'merchantReference' => MODULE_PAYMENT_PAYU_EASYPLUS_MERCHANT_REFERENCE,
                'supportedPaymentMethods' => 'CREDITCARD',
                'demoMode' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER == 'Live' ? 'false' : 'true',
                'secure3d' => MODULE_PAYMENT_PAYU_EASYPLUS_SECURE3D ? 'true' : 'false',
                'returnUrl' => 'http://www.example.com/redirect.php',
                'cancelUrl' => 'http://www.example.com/cancel.php',
                'redirectChannel' => MODULE_PAYMENT_PAYU_EASYPLUS_PAGE_STYLE
            ),
            'Basket' => array(
        	    'currencyCode' => MODULE_PAYMENT_PAYU_EASYPLUS_PAYMENT_CURRENCY,
                'amountInCents' => '55000',
                'description' => 'Test connection'
            ),
            'Customer' => array(
                'firstName' => 'John',
                'lastName' => 'Soap',
                'email' => 'test@example.com',
                'mobile' => '0744445555'
            )
        );

        $response = $soapClient->setTransaction($params);
        $response_array = json_decode(json_encode($response), true);

        if (($response_array['return']['successful'] === true)) {
            return 1;
        }

        return -1;
    }
}
?>