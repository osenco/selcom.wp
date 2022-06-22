<?php

/**
 * Plugin Name: Selcom WooCommerce Payment Gateway
 * Description: Selcom WooCommerce Payment Gateway allows you to accept payments via Selcom.
 * Version: 1.0
 * Author: Osen Concepts
 * Author URI: https://osen.co.ke/
 *
 * Requires at least: 4.6
 * Tested up to: 5.8.1
 *
 * WC requires at least: 3.5.0
 * WC tested up to: 6.5.1
 *
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Copyright 2022  Osen Concepts
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General License, version 3, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General License for more details.
 * You should have received a copy of the GNU General License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USAv
 */

 // Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
   }

// Make sure WooCommerce is active
if (
    !in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
    )
) {
    return;
}

// Load payment gateway
add_action('plugins_loaded', fn () => (require_once plugin_dir_path(__FILE__) . 'class.gateway.php'), 11);

// Register gateway with WooCommerce
add_filter( 'woocommerce_payment_gateways', fn ($gateways) => array_merge($gateways, ['WC_Selcom_Gateway']), 10, 1 );

// Add plugin action links
add_filter(
    'plugin_row_meta', fn ($links, $file) => $file === plugin_basename(__FILE__) 
    ? array_merge($links, ['<a href="https://developers.selcommobile.com">' . __('API Docs') . '</a>']) 
    : $links
, 10, 2);
