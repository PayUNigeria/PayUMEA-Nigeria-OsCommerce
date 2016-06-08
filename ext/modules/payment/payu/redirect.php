<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/

chdir('../../../../');
require('includes/application_top.php');

require(DIR_WS_LANGUAGES . $language . '/modules/payment/payu_easyplus.php');
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_ACCOUNT);

if (!class_exists('PayUEasyPlusApi')) {
  if (file_exists('ext/modules/payment/payu/payuapi.php'))
    require('ext/modules/payment/payu/payuapi.php');
}

// initialize variables if the customer is not logged in
if (!tep_session_is_registered('customer_id')) {
  $customer_id = 0;
  $customer_default_address_id = 0;
}

require_once('includes/modules/payment/payu_easyplus.php');
$payu_easyplus = new payu_easyplus();

if (!$payu_easyplus->check() || !$payu_easyplus->enabled) {
  tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
}

if (!tep_session_is_registered('sendto')) {
  if ( tep_session_is_registered('customer_id') ) {
    $sendto = $customer_default_address_id;
  } else {
    $country = tep_get_countries(STORE_COUNTRY, true);

    $sendto = array(
      'firstname' => '',
      'lastname' => '',
      'company' => '',
      'street_address' => '',
      'suburb' => '',
      'postcode' => '',
      'city' => '',
      'zone_id' => STORE_ZONE,
      'zone_name' => tep_get_zone_name(STORE_COUNTRY, STORE_ZONE, ''),
      'country_id' => STORE_COUNTRY,
      'country_name' => $country['countries_name'],
      'country_iso_code_2' => $country['countries_iso_code_2'],
      'country_iso_code_3' => $country['countries_iso_code_3'],
      'address_format_id' => tep_get_address_format_id(STORE_COUNTRY)
    );
  }
}

if (!tep_session_is_registered('billto')) {
  $billto = $sendto;
}

// register a random ID in the session to check throughout the checkout procedure
// against alterations in the shopping cart contents
if (!tep_session_is_registered('cartID')) 
  tep_session_register('cartID');

$cartID = $cart->cartID;

switch ($HTTP_GET_VARS['osC_Action']) {
  case 'cancel':
    tep_session_unregister('pep_reference');

    if (empty($sendto['firstname']) && empty($sendto['lastname']) && empty($sendto['street_address'])) {
      tep_session_unregister('sendto');
    }

    if (empty($billto['firstname']) && empty($billto['lastname']) && empty($billto['street_address'])) {
      tep_session_unregister('billto');
    }

    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
    break;

  case 'retrieve':
    // if there is nothing in the customers cart, redirect them to the shopping cart page
    if ($cart->count_contents() < 1) {
      tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
    }

    $response = $payu_easyplus->getPayUTransaction($HTTP_GET_VARS['PayUReference']);
    
      if (($response->getVar('successful') === true) 
          && $response->getVar('transaction_state') == 'SUCCESSFUL'
      ) {

        if (!tep_session_is_registered('payment'))
            tep_session_register('payment');

        $payment = $payu_easyplus->code;

        if (!tep_session_is_registered('pep_reference'))
            tep_session_register('pep_reference');

        $pep_reference = $response->getVar('payu_reference');

        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'));
      } else {
        $messageStack->add_session('header', stripslashes($response->getVar('result_message')), 'error');

        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
      }

      break;

  default:
    // if there is nothing in the customers cart, redirect them to the shopping cart page
      if ($cart->count_contents() < 1) {
        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
      }

      include(DIR_WS_CLASSES . 'order.php');
      $order = new order;
      $payuEasyPlusApi = new PayUEasyPlusApi();

      $quotes_array = array();

      if ($cart->get_content_type() != 'virtual') {
        $total_weight = $cart->show_weight();
        $total_count = $cart->count_contents();

      // load all enabled shipping modules
        include(DIR_WS_CLASSES . 'shipping.php');
        $shipping_modules = new shipping;

        $free_shipping = false;

        if ( defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') ) {
          $pass = false;

          switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
            case 'national':
              if ($order->delivery['country_id'] == STORE_COUNTRY) {
                $pass = true;
              }
              break;

            case 'international':
              if ($order->delivery['country_id'] != STORE_COUNTRY) {
                $pass = true;
              }
              break;

            case 'both':
              $pass = true;
              break;
          }

          if ( ($pass == true) && ($order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) ) {
            $free_shipping = true;

            include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
          }
        }

        if ( (tep_count_shipping_modules() > 0) || ($free_shipping == true) ) {
          if ($free_shipping == true) {
            $quotes_array[] = array('id' => 'free_free',
                                    'name' => FREE_SHIPPING_TITLE,
                                    'label' => '',
                                    'cost' => '0.00',
                                    'tax' => '0');
          } else {
        // get all available shipping quotes
            $quotes = $shipping_modules->quote();

            foreach ($quotes as $quote) {
              if (!isset($quote['error'])) {
                foreach ($quote['methods'] as $rate) {
                  $quotes_array[] = array('id' => $quote['id'] . '_' . $rate['id'],
                                          'name' => $quote['module'],
                                          'label' => $rate['title'],
                                          'cost' => $rate['cost'],
                                          'tax' => $quote['tax']);
                }
              }
            }
          }
        } else {
          if ( defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False') ) {
            tep_session_unregister('shipping');

            $messageStack->add_session('checkout_address', MODULE_PAYMENT_PAYU_EASYPLUS_ERROR_NO_SHIPPING_AVAILABLE_TO_SHIPPING_ADDRESS);

            tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL'));
          }
        }
      }

      $counter = 0;
      $cheapest_rate = null;
      $expensive_rate = 0;
      $cheapest_counter = $counter;
      $default_shipping = null;

      foreach ($quotes_array as $quote) {
        $shipping_rate = $payu_easyplus->format_raw($quote['cost'] + tep_calculate_tax($quote['cost'], $quote['tax']));

        $item_params['shipping_option_name' . $counter] = trim($quote['name'] . ' ' . $quote['label']);
        $item_params['shipping_option_amount' . $counter] = $shipping_rate;
        $item_params['shipping_option_isdefault' . $counter] = 'false';

        if (is_null($cheapest_rate) || ($shipping_rate < $cheapest_rate)) {
          $cheapest_rate = $shipping_rate;
          $cheapest_counter = $counter;
        }

        if ($shipping_rate > $expensive_rate) {
          $expensive_rate = $shipping_rate;
        }

        if (tep_session_is_registered('shipping') && ($shipping['id'] == $quote['id'])) {
          $default_shipping = $counter;
        }

        $counter++;
      }

      if (!is_null($default_shipping)) {
        $cheapest_rate = $item_params['shipping_option_amount' . $default_shipping];
        $cheapest_counter = $default_shipping;
      } else {
        if ( !empty($quotes_array) ) {
          $shipping = array(
              'id' => $quotes_array[$cheapest_counter]['id'], 
              'title' => $item_params['shipping_option_name' . $cheapest_counter],
              'cost' => $payu_easyplus->format_raw($quotes_array[$cheapest_counter]['cost']));

          $default_shipping = $cheapest_counter;
        } else {
          $shipping = false;
        }

        if (!tep_session_is_registered('shipping') ) {
          tep_session_register('shipping');
        }
      }

      // set shipping for order total calculations; shipping in $item_params includes taxes
      if (!is_null($default_shipping)) {
        $order->info['shipping_method'] = $item_params['shipping_option_name' . $default_shipping];
        $order->info['shipping_cost'] = $item_params['shipping_option_amount' . $default_shipping];

        $order->info['total'] = $order->info['subtotal'] + $order->info['shipping_cost'];

        if ( DISPLAY_PRICE_WITH_TAX == 'false' ) {
          $order->info['total'] += $order->info['tax'];
        }
      }

      if (!is_null($cheapest_rate)) {
        $item_params['insurance_option_offered'] = 'false';
        $item_params['shipping_option_isdefault' . $cheapest_counter] = 'true';
      }

      include(DIR_WS_CLASSES . 'order_total.php');
      $order_total_modules = new order_total;
      $order_totals = $order_total_modules->process();

      // Remove shipping tax from total that was added again in ot_shipping
      if (DISPLAY_PRICE_WITH_TAX == 'true') 
          $order->info['shipping_cost'] = $order->info['shipping_cost'] / (1.0 + ($quotes_array[$default_shipping]['tax'] / 100));
      $module = substr($shipping['id'], 0, strpos($shipping['id'], '_'));
      $order->info['tax'] -= tep_calculate_tax($order->info['shipping_cost'], $quotes_array[$default_shipping]['tax']);
      $order->info['tax_groups'][tep_get_tax_description($module->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])] -= tep_calculate_tax($order->info['shipping_cost'], $quotes_array[$default_shipping]['tax']);
      $order->info['total'] -= tep_calculate_tax($order->info['shipping_cost'], $quotes_array[$default_shipping]['tax']);

      $description = '';
      if(is_array($order->products)) {
          foreach ($order->products as $product) {
              $description .= $product['name'] . ', ';
          }
      }
      $params['Basket'] = array(
          'currencyCode' => $order->info['currency'],
          'amountInCents' => $payu_easyplus->format_raw($order->info['total'] * 100),
          'description' => MODULE_PAYMENT_PAYU_EASYPLUS_DESCRIPTION . $description
      );

      if (tep_not_null($order->customer)) {
          $params['Customer'] = array(
              'firstName' => $order->customer['firstname'],
              'lastName' => $order->customer['lastname'],
              'email' => $order->customer['email_address'],
              'mobile' => $order->customer['telephone'],
              'regionalId' => 'regionalId_' . $order->delivery['country']['iso_code_2']
          );
      }

      $response_array = $payu_easyplus->setPayUTransaction($params);
    
    if (($response_array['return']['successful'] === true)) {
      tep_redirect($payuEasyPlusApi->getCheckoutUrl() . '?PayUReference=' . $response_array['return']['payUReference']);
    } else {
      tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, 'error_message=' . stripslashes($response_array['return']['returnMessage']), 'SSL'));
    }
    break;
}

tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));

require(DIR_WS_INCLUDES . 'application_bottom.php');
?>