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
		$this->method_title       = __('Selcom Gateway', 'woocommerce');
		$this->method_description = __('Allows payments through Selcom Gateway. Use webhook: '.home_url("?wc-api=WC_Gateway_Selcom"), 'woocommerce');
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
		$this->vendor       = $this->get_option('vendor');
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
				'title'   => __('Enable/Disable', 'woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable Selcom Gateway', 'woocommerce'),
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => __('Title', 'woocommerce'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
				'default'     => __('Selcom Gateway', 'woocommerce'),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __('Description', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
				'default'     => __('Pay via Selcom Gateway', 'woocommerce'),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __('Instructions', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
				'default'     => __('Pay via Selcom Gateway', 'woocommerce'),
				'desc_tip'    => true,
			),
			'vendor'       => array(
				'title'       => __('Vendor', 'woocommerce'),
				'type'        => 'text',
				'description' => __('Vendor ID provided by Selcom.', 'woocommerce'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'api_key'      => array(
				'title'       => __('API Key', 'woocommerce'),
				'type'        => 'text',
				'description' => __('This is the API Key provided by Selcom.', 'woocommerce'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'api_secret'   => array(
				'title'       => __('API Secret', 'woocommerce'),
				'type'        => 'text',
				'description' => __('This is the API Secret provided by Selcom.', 'woocommerce'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'api_url'      => array(
				'title'       => __('API URL', 'woocommerce'),
				'type'        => 'text',
				'description' => __('This is the API URL provided by Selcom.', 'woocommerce'),
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
				'type'        => 'tel',
				'id'          => 'selcom-phone',
				'class'       => array('form-row-wide'),
				'label'       => __('Phone', 'woocommerce'),
				'placeholder' => __('Phone', 'woocommerce'),
				'required'    => true,
			)
		);

		echo <<<JS
<script>
jQuery(document).ready(function($) {
	const checkoutPhone = $('#billing_phone').val();
	$('#selcom-phone').val(checkoutPhone);

	$('#billing_phone').on('input', function() {
		$('#selcom-phone').val($(this).val());
	});
});
</script>
JS;
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

	public function send_api_request($json, $digest, $signed_fields, $timestamp, $endpoint = '/checkout/wallet-payment')
	{
		$url           = $this->api_url . $endpoint;
		$authorization = base64_encode($this->api_key);
		$headers       = array(
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
			// "utilityref"  => $order->get_id(),
			// "transid"     => $order->get_order_key(),
			"amount"      => round($order->get_total()),
			"vendor"      => $this->get_option('vendor'),
			"order_id"    => $order->get_id(),
			"buyer_email" => $order->get_billing_email(),
			"buyer_name"  => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
			"buyer_phone" => $phone,
			"currency"    => "TZS",
			"no_of_items" => WC()->cart->cart_contents_count,
			"webhook" => base64_encode(home_url("?wc-api=WC_Gateway_Selcom")),
			// 'buyer_remarks' => $order->get_customer_note()
		);

		$signed_fields = implode(',', array_keys($request));
		$digest        = $this->compute_signature($request, $signed_fields, $timestamp, $this->api_secret);

		return $this->send_api_request(
			wp_json_encode($request),
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
		$phone        = "255" . substr(sanitize_text_field(trim($_POST['phone'])), -9);
		$order        = wc_get_order($order_id);
		$timestamp    = date('c');
		$create_order = $this->create_order($order, $phone, $timestamp);

		if ($create_order && isset($create_order['result'])) {
			if ($create_order['result'] === 'SUCCESS') {
				$request = array(
					"order_id" 	=> $order_id,
					"transid"  	=> $order->get_order_key(),
					"msisdn"	=> $phone,
					"webhook" 	=> home_url("?wc-api=WC_Gateway_Selcom")
				);

				$signed_fields = implode(',', array_keys($request));
				$digest        = $this->compute_signature($request, $signed_fields, $timestamp);
				$response      = $this->send_api_request(
					wp_json_encode($request),
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
					wc_add_notice(__('Payment error: ', 'woocommerce') . $response['message'], 'error');
					return array(
						'result'   => 'fail',
						'redirect' => $this->get_return_url($order),
					);
				}
			} else {
				wc_add_notice($create_order['message'], 'error');
			}
		} else {
			wc_add_notice(__('An error occurred. Please try again', 'woocommerce'), 'error');
		}

		return array(
			'result'   => 'fail',
			'redirect' => $order->get_cancel_order_url(),
		);
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
		$input = file_get_contents('php://input');
		$data = json_decode($input, true);
		
		if (isset($data['result']) && $data['result'] === 'SUCCESS') {
			$order = wc_get_order($data['utilityref'] ?? $data['order_id']);

			if ($order && $order->get_status() === 'pending') {
				$order->payment_complete($data['transid']);
				$order->add_order_note(__('Payment received.', 'woocommerce'));
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
