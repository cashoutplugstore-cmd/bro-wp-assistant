<?php
/**
 * Plugin Name: BRO WP Assistant
 * Description: Secure REST API bridge for BRO Assistant.
 * Version: 0.1.0
 * Author: BRO
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BRO_WPA_VERSION', '0.1.0');
define('BRO_WPA_DIR', plugin_dir_path(__FILE__));

require_once BRO_WPA_DIR . 'includes/class-auth.php';
require_once BRO_WPA_DIR . 'includes/class-rest-api.php';

add_action('plugins_loaded', function () {
    BRO_WPA_REST_API::init();
});
