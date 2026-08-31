<?php

if (!defined('ABSPATH')) {
    exit;
}

final class BRO_WPA_Auth {

    public static function permission_check(WP_REST_Request $request = null) {
        // Normal WordPress administrators can use the bridge from wp-admin.
        if (current_user_can('manage_options')) {
            return true;
        }

        // Remote BRO Assistant requests authenticate with the site-specific API key.
        $provided = '';
        if ($request instanceof WP_REST_Request) {
            $provided = (string) $request->get_header('X-BRO-Key');
            if ($provided === '') {
                $provided = (string) $request->get_param('bro_key');
            }
        }

        $stored = (string) get_option('bro_wpa_api_key', '');
        if ($stored !== '' && $provided !== '' && hash_equals($stored, $provided)) {
            return true;
        }

        return new WP_Error(
            'bro_forbidden',
            'Administrator permission required or valid BRO API key required.',
            array('status' => 403)
        );
    }

    public static function ensure_api_key() {
        $key = (string) get_option('bro_wpa_api_key', '');
        if ($key === '') {
            $key = wp_generate_password(48, false, false);
            update_option('bro_wpa_api_key', $key, false);
        }
        return $key;
    }
}
