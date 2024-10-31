<?php
/**
 * Plugin Name: Phanes Payment Gateway
 * Plugin URI: https://givephuck.com
 * Description: Accept Cryptocurrency Payment in your WooCommerce with Tron or PHUCKS| More Tron Ecosystem Payment Methods Coming Soon! Earn $250 in $PHUCKS for accepting our Token as a Payment Method.
 * Version: 3.1
 * Author: Phanes
 * Author URI: #
 */
if(!defined('ABSPATH')) { exit; }

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;


include(plugin_dir_path( __FILE__ ) . 'config.php');
include(plugin_dir_path( __FILE__ ) . 'includes/functions.php');
include(plugin_dir_path( __FILE__ ) . 'includes/hooks.php'); //general hooks
if (is_admin()) include(plugin_dir_path( __FILE__ ) . 'includes/admin/admin.php');
if (!is_admin()) include(plugin_dir_path( __FILE__ ) . 'includes/front/front.php');

register_activation_hook( __FILE__, 'phuck_payment_gateway_activated');
register_deactivation_hook( __FILE__, 'phuck_payment_gateway_deactivated');
