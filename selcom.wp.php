<?php

/**
 * Plugin Name: Selcom WooCommerce Payment Gateway
 * Description: Selcom WooCommerce Payment Gateway allows you to accept payments via Selcom.
 * Version: 1.0
 * Author: Osen Concepts
 * Author URI: https://osen.co.ke
 * License: GPL2
 *
 */

// Make sure WooCommerce is active
if (
    !in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
    )
) {
    return;
}

add_action('plugins_loaded', function () {require_once plugin_dir_path(__FILE__) . 'class.gateway.php';}, 11);
add_filter( 'woocommerce_payment_gateways', fn ($gateways) => array_merge($gateways, ['WC_Gateway_Selcom']), 10, 1 );
add_filter(
    'plugin_row_meta', fn ($links, $file) => $file == plugin_basename(__FILE__) 
    ? array_merge($links, ['<a href="https://developers.selcommobile.com">' . __('API Docs') . '</a>']) 
    : $links
, 10, 2);
