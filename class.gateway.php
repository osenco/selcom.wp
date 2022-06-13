<?php

/**
 * Selcom Payment Gateway
 *
 * Provides an Selcom Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Gateway_Selcom
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Osen Concepts
 */
class WC_Selcom_Gateway extends WC_Payment_Gateway
{
 /**
  * Constructor for the gateway.
  *
  * @return void
  */
 public function __construct()
 {
  $this->id                 = 'selcom';
  $this->icon               = apply_filters('woocommerce_selcom_gateway_icon', plugins_url('selcom.png', __FILE__));
  $this->has_fields         = true;
  $this->method_title       = __('Selcom Gateway', 'rcpro');
  $this->method_description = __('Allows payments through Selcom Gateway.', 'rcpro');
  $this->supports           = array(
   'products',
   'refunds',
  );

  // Load the settings.
  $this->init_form_fields();
  $this->init_settings();

  // Define user set variables.
  $this->title        = $this->get_option('title');
  $this->description  = $this->get_option('description');
  $this->instructions = $this->get_option('instructions', $this->description);
  $this->vendor     = $this->get_option('vendor_id');
  $this->api_key      = $this->get_option('api_key');
  $this->api_secret   = $this->get_option('api_secret');
  $this->api_url      = $this->get_option('api_url');

  // Actions.
  add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
  add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
  add_action('woocommerce_api_' . $this->id, array($this, 'process_webhook'));
  add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
 }

 /**
  * Initialise Gateway Settings Form Fields.
  *
  * @return void
  */
 public function init_form_fields()
 {
  $this->form_fields = array(
   'enabled'      => array(
    'title'   => __('Enable/Disable', 'rcpro'),
    'type'    => 'checkbox',
    'label'   => __('Enable Selcom Gateway', 'rcpro'),
    'default' => 'yes',
   ),
   'title'        => array(
    'title'       => __('Title', 'rcpro'),
    'type'        => 'text',
    'description' => __('This controls the title which the user sees during checkout.', 'rcpro'),
    'default'     => __('Selcom Gateway', 'rcpro'),
    'desc_tip'    => true,
   ),
   'description'  => array(
    'title'       => __('Description', 'rcpro'),
    'type'        => 'textarea',
    'description' => __('This controls the description which the user sees during checkout.', 'rcpro'),
    'default'     => __('Pay via Selcom Gateway', 'rcpro'),
    'desc_tip'    => true,
   ),
   'instructions' => array(
    'title'       => __('Instructions', 'rcpro'),
    'type'        => 'textarea',
    'description' => __('Instructions that will be added to the thank you page.', 'rcpro'),
    'default'     => __('Pay via Selcom Gateway', 'rcpro'),
    'desc_tip'    => true,
   ),
   'vendor'     => array(
    'title'       => __('Vendor', 'rcpro'),
    'type'        => 'text',
    'description' => __('Vendor ID provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
   'api_key'      => array(
    'title'       => __('API Key', 'rcpro'),
    'type'        => 'text',
    'description' => __('This is the API Key provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
   'api_secret'   => array(
    'title'       => __('API Secret', 'rcpro'),
    'type'        => 'text',
    'description' => __('This is the API Secret provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
   'api_url'      => array(
    'title'       => __('API URL', 'rcpro'),
    'type'        => 'text',
    'description' => __('This is the API URL provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
  );
 }

 public function payment_fields()
 {
  $description = $this->get_description();
  if ($description) {
   echo wpautop(wptexturize($description));
  }

  woocommerce_form_field(
   'phone',
   array(
    'type'        => 'text',
    'class'       => array('form-row-wide'),
    'label'       => __('Phone', 'rcpro'),
    'placeholder' => __('Phone', 'rcpro'),
    'required'    => true,
   )
  );
 }

 public function compute_signature($parameters, $signed_fields, $request_timestamp)
 {
  $fields_order = explode(',', $signed_fields);
  $sign_data    = "timestamp=$request_timestamp";

  foreach ($fields_order as $key) {
   $sign_data .= "&$key=" . $parameters[$key];
  }

  return base64_encode(hash_hmac('sha256', $sign_data, $this->api_secret, true));
 }

 public function send_api_request($json, $authorization, $digest, $signed_fields, $timestamp, $endpoint = '')
 {
  $url     = $this->api_url . $endpoint;
  $headers = array(
   "Content-type"   => 'application/json;charset="utf-8"',
   "Accept"         => "application/json",
   "Cache-Control:" => " no-cache",
   "Authorization"  => "SELCOM $authorization",
   "Digest-Method"  => "HS256",
   "Digest"         => $digest,
   "Timestamp"      => $timestamp,
   "Signed-Fields"  => $signed_fields,
  );

  $result = wp_remote_post($url, array(
   'headers' => $headers,
   'body'    => $json,
  ));

  return is_wp_error($result)
  ? array('result' => 'FAIL', 'message' => $result->get_error_message())
  : json_decode($result['body'], true);
 }

 public function create_order(WC_Order $order, $phone, $timestamp)
 {
  $request = array(
   "utilityref"  => $order->get_id(),
   "transid"     => $order->get_order_key(),
   "amount"      => round($order->get_total()),
   "vendor"      => $this->vendor,
   "order_id"    => $order->get_id(),
   "buyer_email" => $order->get_billing_email(),
   "buyer_name"  => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
   "buyer_phone" => $phone,
   "currency"    => "TZS",
   "no_of_items" => WC()->cart->cart_contents_count,
  );

  $authorization = base64_encode($this->api_key);
  $signed_fields = implode(',', array_keys($request));
  $digest        = $this->compute_signature($request, $signed_fields, $timestamp, $this->api_secret);

  return $this->send_api_request(
   wp_json_encode($request),
   $authorization,
   $digest,
   $signed_fields,
   $timestamp,
   '/checkout/create-order-minimal'
  );
 }

 /**
  * Process the payment and return the result.
  *
  * @param int $order_id
  * @return array
  */
 public function process_payment($order_id)
 {
  $phone        = "255" . substr(sanitize_text_field(trim($_POST['phone'])), 1);
  $order        = wc_get_order($order_id);
  $timestamp    = date('c');
  $create_order = $this->create_order($order, $phone, $timestamp);

  if ($create_order && isset($create_order['result'])) {
   if ($create_order['result'] === 'SUCCESS') {
    $authorization = base64_encode($this->api_key);
    $request       = array(
     "order_id" => $order_id,
     "transid"  => $order->get_order_key(),
     "amount"   => round($order->get_total()),
    );

    $signed_fields = implode(',', array_keys($request));
    $digest        = $this->compute_signature($request, $signed_fields, $timestamp);
    $response      = $this->send_api_request(
     wp_json_encode($request),
     $authorization,
     $digest,
     $signed_fields,
     $timestamp
    );

    if (isset($response['result']) && $response['result'] === 'SUCCESS') {
     // Remove cart
     WC()->cart->empty_cart();

     // Reduce stock
     wc_reduce_stock_levels($order_id);

     if ($response['payment_status'] === 'COMPLETE') {
      $order->payment_complete($response['transid']);
     }

     // Return thankyou redirect
     return array(
      'result'   => 'success',
      'redirect' => $this->get_return_url($order),
     );
    } else {
     wc_add_notice(__('Payment error: ', 'rcpro') . $response['message'], 'error');
     return array(
      'result'   => 'fail',
      'redirect' => $this->get_return_url($order),
     );
    }
   } else {
    wc_add_notice($create_order['message'], 'error');
    return array(
     'result'   => 'fail',
     'redirect' => $order->get_cancel_order_url(),
    );
   }
  }
 }

 /**
  * Output for the order received page.
  */
 public function thankyou_page()
 {
  if ($this->instructions) {
   echo wpautop(wptexturize($this->instructions));
  }
 }

 /**
  * Process webhook response with IPN
  *
  * @return array
  */
 public function process_webhook()
 {
  $data = json_decode(file_get_contents('php://input'), true);
  if (isset($data['result']) && $data['result'] === 'SUCCESS') {
   $order = wc_get_order($data['utilityref']);

   if ($order && $order->get_status() === 'pending') {
    $order->payment_complete($data['transid']);
    $order->add_order_note(__('Payment received.', 'rcpro'));
   }
  }

  return array(
   'result'   => 'success',
   'order_id' => $data['utilityref'],
  );
 }

 /**
  * Add content to the WC emails.
  *
  * @access public
  * @param WC_Order $order
  * @param bool $sent_to_admin
  * @param bool $plain_text
  */
 public function email_instructions($order, $sent_to_admin, $plain_text = false)
 {

  if ($this->instructions && !$sent_to_admin && 'Selcom' === $order->payment_method && $order->has_status('on-hold')) {
   echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
  }
 }
}
