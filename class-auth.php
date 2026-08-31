<?php

if (!defined('ABSPATH')) {
    exit;
}

final class BRO_WPA_Auth {

    public static function permission_check() {

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'bro_forbidden',
                'Administrator permission required.',
                array('status' => 403)
            );
        }

        return true;
    }
}
