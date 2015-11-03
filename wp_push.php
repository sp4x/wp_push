<?php
defined("MAPPING_META_KEY") || define("MAPPING_META_KEY", 'meta_master_mapping');

defined("POST_OPT") || define("POST_OPT", 'master_plugin_posts_options');
defined("SITE_OPT") || define("SITE_OPT", 'master_plugin_sites_options');
defined("ERRORS") || define("ERRORS", 'master_plugin_erros');

require_once plugin_dir_path(__FILE__) . '/src/Master.php';
require_once plugin_dir_path(__FILE__) . '/settings.php';

$types = array();

add_action('init', 'register_types', 11);

if (! defined("WP_TESTS_DOMAIN")) {
    
    add_action('init', 'register_actions', 12);
}

function register_actions()
{
    global $types;
    $master = new Master();
    foreach ($types as $post_type) {
        add_action("publish_$post_type", array(
            $master,
            'publish_post'
        ), 10, 2);
    }
    
    add_action('edit_attachment', array(
        $master,
        'publish_post'
    ));
    
    add_action('updated_post_meta', array(
        $master,
        'update_post_meta'
    ), 10, 4);
    
    add_action('added_post_meta', array(
        $master,
        'update_post_meta'
    ), 10, 4);
    
    add_action('admin_notices', 'display_errors');
    
    add_action('post_submitbox_misc_actions', 'show_mappings');
}

function display_errors()
{
    $errors = get_option(ERRORS);
    if ($errors) {
        array_map('render_notice', $errors);
        delete_option(ERRORS);
    }
}

function show_mappings()
{
    $id = get_the_ID();
    $mapping = get_post_meta($id, MAPPING_META_KEY, true);
    if (! $mapping)
        return;
    render_mapping_section($mapping);
}

function render_mapping_section($mapping)
{
    ?>
<div class="misc-pub-section">
	<p><?php   _e( 'Mapped on' )  ?></p>
	<ul style="padding-left: 15px;">
       <?php render_mapping_list($mapping)?>
       </ul>
</div>
<?php
}

function render_mapping_list($mapping)
{
    foreach ($mapping as $site => $remote_id) {
        $url = "http://$site/?p=$remote_id";
        echo "<li><a target=\"_blank\" href=\"$url\">$site</a></li>";
    }
}

function render_notice($message)
{
    ?>
<div class="error">
	<p><?php   _e( $message )  ?></p>
</div><?php
}

function clear_actions()
{
    global $types;
    remove_all_actions('added_post_meta');
    remove_all_actions('updated_post_meta');
    foreach ($types as $post_type) {
        remove_all_actions("publish_$post_type");
    }
}

function register_types()
{
    global $types;
    $types = get_post_types(array(
        '_builtin' => false
    ));
    array_unshift($types, 'post');
}

