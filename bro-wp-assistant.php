<?php
/**
 * Plugin Name: BRO WP Assistant
 * Description: Secure REST API bridge for BRO Assistant.
 * Version: 0.1.1
 * Author: BRO
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BRO_WPA_VERSION', '0.1.1');
define('BRO_WPA_DIR', plugin_dir_path(__FILE__));

// These classes currently live in the plugin root.
require_once BRO_WPA_DIR . 'class-auth.php';
require_once BRO_WPA_DIR . 'class-rest-api.php';

add_action('plugins_loaded', function () {
    BRO_WPA_REST_API::init();
});
