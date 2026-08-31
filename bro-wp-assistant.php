<?php
/**
 * Plugin Name: BRO WP Assistant
 * Description: Secure REST API bridge for BRO Assistant with WordPress and WooCommerce control.
 * Version: 0.3.0
 * Author: BRO
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BRO_WPA_VERSION', '0.3.0');
define('BRO_WPA_DIR', plugin_dir_path(__FILE__));

require_once BRO_WPA_DIR . 'class-auth.php';
require_once BRO_WPA_DIR . 'class-rest-api.php';

add_action('plugins_loaded', function () {
    BRO_WPA_Auth::ensure_api_key();
    BRO_WPA_REST_API::init();
});
