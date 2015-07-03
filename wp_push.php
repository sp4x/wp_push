<?php
/**
 Plugin Name: WP Push
 Plugin URI:
 Description:
 Author: Vincenzo Pirrone
 Version: 1.0
 Author URI: 
 */

defined("MAPPING_META_KEY") || define("MAPPING_META_KEY", 'meta_master_mapping');

defined("POST_OPT") || define("POST_OPT", 'master_plugin_posts_options');
defined("SITE_OPT") || define("SITE_OPT", 'master_plugin_sites_options');

$types = get_post_types(array(
    '_builtin' => false
));
array_unshift($types, 'post');

require_once plugin_dir_path(__FILE__) . '/settings.php';
require_once plugin_dir_path(__FILE__) . '/src/Master.php';

$master = new Master();

if (! defined("WP_TESTS_DOMAIN")) {
    
    foreach ($types as $post_type) {
        add_action("publish_$post_type", array(
            $master,
            'post_to_slaves'
        ), 10, 2);
    }
    
    add_action('updated_post_meta', array(
        $master,
        'update_post_meta'
    ), 10, 2);
    
    add_action('added_post_meta', array(
        $master,
        'update_post_meta'
    ), 10, 2);
    
}