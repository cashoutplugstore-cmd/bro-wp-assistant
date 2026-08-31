<?php

if (!defined('ABSPATH')) {
    exit;
}

final class BRO_WPA_REST_API {

    public static function init() {
        add_action(
            'rest_api_init',
            array(__CLASS__, 'register_routes')
        );
    }

    public static function register_routes() {

        register_rest_route(
            'bro-assistant/v1',
            '/status',
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'status'),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function status() {

        return rest_ensure_response(
            array(
                'ok' => true,
                'plugin' => 'BRO WP Assistant',
                'version' => BRO_WPA_VERSION,
                'wordpress' => get_bloginfo('version'),
                'woocommerce' => class_exists('WooCommerce'),
            )
        );
    }
}
