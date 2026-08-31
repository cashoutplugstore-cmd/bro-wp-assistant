<?php

if (!defined('ABSPATH')) {
    exit;
}

final class BRO_WPA_REST_API {

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
    }

    public static function register_routes() {
        register_rest_route('bro-assistant/v1', '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'status'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('bro-assistant/v1', '/key', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'key'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
        ));

        register_rest_route('bro-assistant/v1', '/site', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'site'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
        ));

        register_rest_route('bro-assistant/v1', '/plugins', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'plugins'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
        ));

        register_rest_route('bro-assistant/v1', '/posts', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'posts'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
            'args' => array(
                'post_type' => array('default' => 'post', 'sanitize_callback' => 'sanitize_key'),
                'per_page' => array('default' => 20, 'sanitize_callback' => 'absint'),
            ),
        ));

        register_rest_route('bro-assistant/v1', '/post', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'create_post'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
        ));

        register_rest_route('bro-assistant/v1', '/post/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'update_post'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
        ));

        register_rest_route('bro-assistant/v1', '/post/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_post'),
            'permission_callback' => array('BRO_WPA_Auth', 'permission_check'),
        ));
    }

    public static function status() {
        return rest_ensure_response(array(
            'ok' => true,
            'plugin' => 'BRO WP Assistant',
            'version' => BRO_WPA_VERSION,
            'wordpress' => get_bloginfo('version'),
            'woocommerce' => class_exists('WooCommerce'),
            'authenticated_control' => true,
        ));
    }

    public static function key() {
        return rest_ensure_response(array(
            'ok' => true,
            'api_key' => BRO_WPA_Auth::ensure_api_key(),
        ));
    }

    public static function site() {
        global $wpdb;
        return rest_ensure_response(array(
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url('/'),
            'admin_url' => admin_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'multisite' => is_multisite(),
            'locale' => get_locale(),
            'timezone' => wp_timezone_string(),
            'db_prefix' => $wpdb->prefix,
            'woocommerce' => class_exists('WooCommerce'),
        ));
    }

    public static function plugins() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all = get_plugins();
        $active = (array) get_option('active_plugins', array());
        $items = array();
        foreach ($all as $file => $data) {
            $items[] = array(
                'file' => $file,
                'name' => $data['Name'],
                'version' => $data['Version'],
                'active' => in_array($file, $active, true),
                'network_active' => is_plugin_active_for_network($file),
            );
        }
        return rest_ensure_response($items);
    }

    public static function posts(WP_REST_Request $request) {
        $type = $request->get_param('post_type');
        $per_page = min(max((int) $request->get_param('per_page'), 1), 100);
        $query = new WP_Query(array(
            'post_type' => $type,
            'post_status' => 'any',
            'posts_per_page' => $per_page,
            'no_found_rows' => false,
        ));
        $items = array();
        foreach ($query->posts as $post) {
            $items[] = array(
                'id' => $post->ID,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'date' => $post->post_date_gmt,
                'author' => (int) $post->post_author,
            );
        }
        return rest_ensure_response(array(
            'items' => $items,
            'total' => (int) $query->found_posts,
        ));
    }

    public static function create_post(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $post = array(
            'post_type' => isset($params['post_type']) ? sanitize_key($params['post_type']) : 'post',
            'post_status' => isset($params['post_status']) ? sanitize_key($params['post_status']) : 'draft',
            'post_title' => isset($params['title']) ? sanitize_text_field($params['title']) : '',
            'post_content' => isset($params['content']) ? wp_kses_post($params['content']) : '',
        );
        if (isset($params['excerpt'])) {
            $post['post_excerpt'] = wp_kses_post($params['excerpt']);
        }
        $id = wp_insert_post(wp_slash($post), true);
        if (is_wp_error($id)) {
            return $id;
        }
        return new WP_REST_Response(array('ok' => true, 'id' => $id), 201);
    }

    public static function update_post(WP_REST_Request $request) {
        $id = absint($request['id']);
        if (!get_post($id)) {
            return new WP_Error('bro_not_found', 'Post not found.', array('status' => 404));
        }
        $params = $request->get_json_params();
        $post = array('ID' => $id);
        if (array_key_exists('title', $params)) {
            $post['post_title'] = sanitize_text_field($params['title']);
        }
        if (array_key_exists('content', $params)) {
            $post['post_content'] = wp_kses_post($params['content']);
        }
        if (array_key_exists('excerpt', $params)) {
            $post['post_excerpt'] = wp_kses_post($params['excerpt']);
        }
        if (array_key_exists('post_status', $params)) {
            $post['post_status'] = sanitize_key($params['post_status']);
        }
        $result = wp_update_post(wp_slash($post), true);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('ok' => true, 'id' => $result));
    }

    public static function delete_post(WP_REST_Request $request) {
        $id = absint($request['id']);
        $deleted = wp_delete_post($id, false);
        if (!$deleted) {
            return new WP_Error('bro_delete_failed', 'Unable to delete post.', array('status' => 400));
        }
        return rest_ensure_response(array('ok' => true, 'id' => $id, 'trashed' => true));
    }

    public static function admin_menu() {
        add_management_page(
            'BRO WP Assistant',
            'BRO Assistant',
            'manage_options',
            'bro-wp-assistant',
            array(__CLASS__, 'admin_page')
        );
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Administrator permission required.');
        }
        $key = BRO_WPA_Auth::ensure_api_key();
        ?>
        <div class="wrap">
            <h1>BRO WP Assistant</h1>
            <p>Authenticated control bridge is active.</p>
            <table class="widefat" style="max-width:900px">
                <tr><th>Version</th><td><?php echo esc_html(BRO_WPA_VERSION); ?></td></tr>
                <tr><th>REST Base</th><td><?php echo esc_html(rest_url('bro-assistant/v1/')); ?></td></tr>
                <tr><th>API Key</th><td><code><?php echo esc_html($key); ?></code></td></tr>
            </table>
            <p><strong>Keep this API key private.</strong> It grants the same control level as an administrator to authenticated BRO requests.</p>
        </div>
        <?php
    }
}
