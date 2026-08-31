<?php

if (!defined('ABSPATH')) { exit; }

final class BRO_WPA_REST_API {
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
    }

    public static function register_routes() {
        register_rest_route('bro-assistant/v1', '/status', array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'status'),'permission_callback'=>'__return_true'));
        foreach (array('key','site','plugins') as $route) {
            register_rest_route('bro-assistant/v1','/'.$route,array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,$route),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        }
        register_rest_route('bro-assistant/v1','/posts',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'posts'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/post',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'create_post'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/post/(?P<id>\d+)',array('methods'=>WP_REST_Server::EDITABLE,'callback'=>array(__CLASS__,'update_post'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/post/(?P<id>\d+)',array('methods'=>WP_REST_Server::DELETABLE,'callback'=>array(__CLASS__,'delete_post'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));

        register_rest_route('bro-assistant/v1','/products',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'products'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'create_product'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product/(?P<id>\d+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'get_product'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product/(?P<id>\d+)',array('methods'=>WP_REST_Server::EDITABLE,'callback'=>array(__CLASS__,'update_product'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product/(?P<id>\d+)',array('methods'=>WP_REST_Server::DELETABLE,'callback'=>array(__CLASS__,'delete_product'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product-categories',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'product_categories'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product-category',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'create_product_category'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product-category/(?P<id>\d+)',array('methods'=>WP_REST_Server::EDITABLE,'callback'=>array(__CLASS__,'update_product_category'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
        register_rest_route('bro-assistant/v1','/product-category/(?P<id>\d+)',array('methods'=>WP_REST_Server::DELETABLE,'callback'=>array(__CLASS__,'delete_product_category'),'permission_callback'=>array('BRO_WPA_Auth','permission_check')));
    }

    private static function woo() { return class_exists('WooCommerce') && function_exists('wc_get_product') ? true : new WP_Error('bro_woocommerce_required','WooCommerce is required.',array('status'=>503)); }
    private static function product_data($p) {
        $cats=array(); foreach((array)$p->get_category_ids() as $id){$t=get_term($id,'product_cat');if($t&&!is_wp_error($t))$cats[]=array('id'=>(int)$id,'name'=>$t->name,'slug'=>$t->slug);}
        $tags=array(); foreach((array)$p->get_tag_ids() as $id){$t=get_term($id,'product_tag');if($t&&!is_wp_error($t))$tags[]=array('id'=>(int)$id,'name'=>$t->name,'slug'=>$t->slug);}
        $gallery=array(); foreach((array)$p->get_gallery_image_ids() as $id)$gallery[]=array('id'=>(int)$id,'url'=>wp_get_attachment_url($id));
        $image=$p->get_image_id();
        return array('id'=>$p->get_id(),'name'=>$p->get_name(),'slug'=>$p->get_slug(),'type'=>$p->get_type(),'status'=>$p->get_status(),'description'=>$p->get_description(),'short_description'=>$p->get_short_description(),'sku'=>$p->get_sku(),'price'=>$p->get_price(),'regular_price'=>$p->get_regular_price(),'sale_price'=>$p->get_sale_price(),'stock_status'=>$p->get_stock_status(),'manage_stock'=>$p->get_manage_stock(),'stock_quantity'=>$p->get_stock_quantity(),'weight'=>$p->get_weight(),'virtual'=>$p->get_virtual(),'downloadable'=>$p->get_downloadable(),'featured'=>$p->get_featured(),'catalog_visibility'=>$p->get_catalog_visibility(),'categories'=>$cats,'tags'=>$tags,'image'=>$image?array('id'=>(int)$image,'url'=>wp_get_attachment_url($image)):null,'gallery'=>$gallery,'url'=>get_permalink($p->get_id()));
    }
    private static function apply_product($p,$x) {
        if(array_key_exists('name',$x))$p->set_name(sanitize_text_field($x['name']));
        if(array_key_exists('slug',$x))$p->set_slug(sanitize_title($x['slug']));
        if(array_key_exists('description',$x))$p->set_description(wp_kses_post($x['description']));
        if(array_key_exists('short_description',$x))$p->set_short_description(wp_kses_post($x['short_description']));
        if(array_key_exists('sku',$x))$p->set_sku(sanitize_text_field($x['sku']));
        if(array_key_exists('regular_price',$x))$p->set_regular_price(wc_format_decimal($x['regular_price']));
        if(array_key_exists('sale_price',$x))$p->set_sale_price($x['sale_price']===''?'':wc_format_decimal($x['sale_price']));
        if(array_key_exists('stock_status',$x))$p->set_stock_status(sanitize_key($x['stock_status']));
        if(array_key_exists('manage_stock',$x))$p->set_manage_stock((bool)$x['manage_stock']);
        if(array_key_exists('stock_quantity',$x))$p->set_stock_quantity($x['stock_quantity']===''||$x['stock_quantity']===null?null:wc_stock_amount($x['stock_quantity']));
        if(array_key_exists('weight',$x))$p->set_weight(wc_format_decimal($x['weight']));
        if(array_key_exists('virtual',$x))$p->set_virtual((bool)$x['virtual']);
        if(array_key_exists('downloadable',$x))$p->set_downloadable((bool)$x['downloadable']);
        if(array_key_exists('featured',$x))$p->set_featured((bool)$x['featured']);
        if(array_key_exists('catalog_visibility',$x))$p->set_catalog_visibility(sanitize_key($x['catalog_visibility']));
        if(array_key_exists('status',$x))$p->set_status(sanitize_key($x['status']));
        if(isset($x['category_ids'])&&is_array($x['category_ids']))$p->set_category_ids(array_map('absint',$x['category_ids']));
        if(isset($x['tag_ids'])&&is_array($x['tag_ids']))$p->set_tag_ids(array_map('absint',$x['tag_ids']));
        if(array_key_exists('image_id',$x))$p->set_image_id(absint($x['image_id']));
        if(isset($x['gallery_image_ids'])&&is_array($x['gallery_image_ids']))$p->set_gallery_image_ids(array_map('absint',$x['gallery_image_ids']));
        return $p;
    }
    public static function status(){return rest_ensure_response(array('ok'=>true,'plugin'=>'BRO WP Assistant','version'=>BRO_WPA_VERSION,'wordpress'=>get_bloginfo('version'),'woocommerce'=>class_exists('WooCommerce'),'authenticated_control'=>true,'product_control'=>class_exists('WooCommerce')));}
    public static function key(){return rest_ensure_response(array('ok'=>true,'api_key'=>BRO_WPA_Auth::ensure_api_key()));}
    public static function site(){global $wpdb;return rest_ensure_response(array('name'=>get_bloginfo('name'),'description'=>get_bloginfo('description'),'url'=>home_url('/'),'admin_url'=>admin_url(),'wp_version'=>get_bloginfo('version'),'php_version'=>PHP_VERSION,'multisite'=>is_multisite(),'locale'=>get_locale(),'timezone'=>wp_timezone_string(),'db_prefix'=>$wpdb->prefix,'woocommerce'=>class_exists('WooCommerce')));}
    public static function plugins(){require_once ABSPATH.'wp-admin/includes/plugin.php';$all=get_plugins();$active=(array)get_option('active_plugins',array());$items=array();foreach($all as $file=>$data)$items[]=array('file'=>$file,'name'=>$data['Name'],'version'=>$data['Version'],'active'=>in_array($file,$active,true),'network_active'=>is_plugin_active_for_network($file));return rest_ensure_response($items);}
    public static function posts(WP_REST_Request $r){$type=sanitize_key($r->get_param('post_type')?:'post');$n=min(max((int)$r->get_param('per_page'),1),100);$q=new WP_Query(array('post_type'=>$type,'post_status'=>'any','posts_per_page'=>$n));$a=array();foreach($q->posts as $p)$a[]=array('id'=>$p->ID,'type'=>$p->post_type,'status'=>$p->post_status,'title'=>get_the_title($p),'url'=>get_permalink($p));return rest_ensure_response(array('items'=>$a,'total'=>(int)$q->found_posts));}
    public static function create_post(WP_REST_Request $r){$x=$r->get_json_params();$id=wp_insert_post(wp_slash(array('post_type'=>isset($x['post_type'])?sanitize_key($x['post_type']):'post','post_status'=>isset($x['post_status'])?sanitize_key($x['post_status']):'draft','post_title'=>isset($x['title'])?sanitize_text_field($x['title']):'','post_content'=>isset($x['content'])?wp_kses_post($x['content']):'')),true);if(is_wp_error($id))return $id;return new WP_REST_Response(array('ok'=>true,'id'=>$id),201);}
    public static function update_post(WP_REST_Request $r){$id=absint($r['id']);if(!get_post($id))return new WP_Error('bro_not_found','Post not found.',array('status'=>404));$x=$r->get_json_params();$p=array('ID'=>$id);if(array_key_exists('title',$x))$p['post_title']=sanitize_text_field($x['title']);if(array_key_exists('content',$x))$p['post_content']=wp_kses_post($x['content']);if(array_key_exists('post_status',$x))$p['post_status']=sanitize_key($x['post_status']);$v=wp_update_post(wp_slash($p),true);if(is_wp_error($v))return $v;return rest_ensure_response(array('ok'=>true,'id'=>$v));}
    public static function delete_post(WP_REST_Request $r){$id=absint($r['id']);if(!wp_delete_post($id,false))return new WP_Error('bro_delete_failed','Unable to delete post.',array('status'=>400));return rest_ensure_response(array('ok'=>true,'id'=>$id,'trashed'=>true));}
    public static function products(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$n=min(max((int)($r->get_param('per_page')?:20),1),100);$args=array('limit'=>$n,'page'=>max((int)($r->get_param('page')?:1),1),'paginate'=>true,'status'=>$r->get_param('status')?:'any');if($r->get_param('search'))$args['search']=sanitize_text_field($r->get_param('search'));$q=wc_get_products($args);$a=array();foreach($q->products as $p)$a[]=self::product_data($p);return rest_ensure_response(array('items'=>$a,'total'=>(int)$q->total,'pages'=>(int)$q->max_num_pages));}
    public static function get_product(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$p=wc_get_product(absint($r['id']));if(!$p)return new WP_Error('bro_product_not_found','Product not found.',array('status'=>404));return rest_ensure_response(self::product_data($p));}
    public static function create_product(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$x=$r->get_json_params();$type=isset($x['type'])?sanitize_key($x['type']):'simple';if($type==='simple')$p=new WC_Product_Simple();elseif($type==='external')$p=new WC_Product_External();else return new WP_Error('bro_product_type','Only simple and external products are supported by this endpoint.',array('status'=>400));self::apply_product($p,$x);if($p->is_type('external')){if(isset($x['product_url']))$p->set_product_url(esc_url_raw($x['product_url']));if(isset($x['button_text']))$p->set_button_text(sanitize_text_field($x['button_text']));}try{$id=$p->save();}catch(Exception $e){return new WP_Error('bro_product_create_failed',$e->getMessage(),array('status'=>400));}return new WP_REST_Response(array('ok'=>true,'product'=>self::product_data(wc_get_product($id))),201);}
    public static function update_product(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$p=wc_get_product(absint($r['id']));if(!$p)return new WP_Error('bro_product_not_found','Product not found.',array('status'=>404));$x=$r->get_json_params();self::apply_product($p,$x);if($p->is_type('external')){if(isset($x['product_url']))$p->set_product_url(esc_url_raw($x['product_url']));if(isset($x['button_text']))$p->set_button_text(sanitize_text_field($x['button_text']));}try{$id=$p->save();}catch(Exception $e){return new WP_Error('bro_product_update_failed',$e->getMessage(),array('status'=>400));}return rest_ensure_response(array('ok'=>true,'product'=>self::product_data(wc_get_product($id))));}
    public static function delete_product(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$p=wc_get_product(absint($r['id']));if(!$p)return new WP_Error('bro_product_not_found','Product not found.',array('status'=>404));$force=!empty($r->get_param('force'));try{$v=$p->delete($force);}catch(Exception $e){return new WP_Error('bro_product_delete_failed',$e->getMessage(),array('status'=>400));}return rest_ensure_response(array('ok'=>(bool)$v,'id'=>(int)$r['id'],'deleted'=>$force,'trashed'=>!$force));}
    public static function product_categories(){ $ok=self::woo();if(is_wp_error($ok))return $ok;$ts=get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false));if(is_wp_error($ts))return $ts;$a=array();foreach($ts as $t)$a[]=array('id'=>(int)$t->term_id,'name'=>$t->name,'slug'=>$t->slug,'parent'=>(int)$t->parent,'count'=>(int)$t->count);return rest_ensure_response($a);}
    public static function create_product_category(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$x=$r->get_json_params();$name=isset($x['name'])?sanitize_text_field($x['name']):'';if($name==='')return new WP_Error('bro_category_name_required','Category name is required.',array('status'=>400));$v=wp_insert_term($name,'product_cat',array('slug'=>isset($x['slug'])?sanitize_title($x['slug']):'','parent'=>isset($x['parent'])?absint($x['parent']):0,'description'=>isset($x['description'])?wp_kses_post($x['description']):''));if(is_wp_error($v))return $v;return new WP_REST_Response(array('ok'=>true,'id'=>(int)$v['term_id']),201);}
    public static function update_product_category(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$x=$r->get_json_params();$a=array();if(array_key_exists('name',$x))$a['name']=sanitize_text_field($x['name']);if(array_key_exists('slug',$x))$a['slug']=sanitize_title($x['slug']);if(array_key_exists('parent',$x))$a['parent']=absint($x['parent']);if(array_key_exists('description',$x))$a['description']=wp_kses_post($x['description']);$v=wp_update_term(absint($r['id']),'product_cat',$a);if(is_wp_error($v))return $v;return rest_ensure_response(array('ok'=>true,'id'=>(int)$r['id']));}
    public static function delete_product_category(WP_REST_Request $r){$ok=self::woo();if(is_wp_error($ok))return $ok;$id=absint($r['id']);$v=wp_delete_term($id,'product_cat');if(is_wp_error($v))return $v;if(!$v)return new WP_Error('bro_category_delete_failed','Category could not be deleted.',array('status'=>400));return rest_ensure_response(array('ok'=>true,'id'=>$id,'deleted'=>true));}
    public static function admin_menu(){add_management_page('BRO WP Assistant','BRO Assistant','manage_options','bro-wp-assistant',array(__CLASS__,'admin_page'));}
    public static function admin_page(){if(!current_user_can('manage_options'))wp_die('Administrator permission required.');$key=BRO_WPA_Auth::ensure_api_key();?><div class="wrap"><h1>BRO WP Assistant</h1><p>Authenticated control bridge is active.</p><table class="widefat" style="max-width:900px"><tr><th>Version</th><td><?php echo esc_html(BRO_WPA_VERSION); ?></td></tr><tr><th>REST Base</th><td><?php echo esc_html(rest_url('bro-assistant/v1/')); ?></td></tr><tr><th>API Key</th><td><code><?php echo esc_html($key); ?></code></td></tr></table><p><strong>Keep this API key private.</strong></p></div><?php }
}
