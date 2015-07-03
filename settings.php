<?php

/**
 * This function introduces the plugin options into the 'Appearance' menu and into a top-level 
 * 'master plugin' menu.
 */
function master_example_plugin_menu()
{
    add_plugins_page('master plugin', // The title to be displayed in the browser window for this page.
    'master plugin', // The text to be displayed for this menu item
    'administrator', // Which type of users can see this menu item
    'master_plugin_options', // The unique ID - that is, the slug - for this menu item
    'master_plugin_display'); // The name of the function to call when rendering this menu's page
    
    add_menu_page('master plugin', // The value used to populate the browser's title bar when the menu page is active
    'master plugin', // The text of the menu in the administrator's sidebar
    'administrator', // What roles are able to access the menu
    'master_plugin_menu', // The ID used to bind submenu items to this menu
    'master_plugin_display'); // The callback function used to render this menu

}



add_action('admin_menu', 'master_example_plugin_menu');

/**
 * Renders a simple page to display for the plugin menu defined above.
 */
function master_plugin_display()
{
?>
    <!-- Create a header in the default WordPress 'wrap' container -->
    <div class="wrap">
    	<h2><?php _e( 'master plugin Options', 'master' ); ?></h2>
    		<?php settings_errors(); ?>
    		<form method="post" action="options.php">
    	    <?php
                settings_fields('master_plugin_sites_options');
                do_settings_sections('master_plugin_sites_options');
                submit_button();
            ?>
    		</form>
    		
    		<form method="post" action="options.php">
    		<?php 
        		settings_fields('master_plugin_posts_options');
        		do_settings_sections('master_plugin_posts_options');
        		submit_button();
    		?>
    		</form>
    </div>
    <!-- /.wrap -->
<?php
}
// end master_plugin_display

/*
 * ------------------------------------------------------------------------ *
 * Setting Registration
 * ------------------------------------------------------------------------
 */


function master_plugin_default_sites_options()
{
    $defaults = array( array('url' => 'www.edilone.it', 'enabled' => 1, 'username' => 'redazione', 'password' => 'password') );
    
    return apply_filters('master_plugin_default_sites_options', $defaults);
}

function master_plugin_default_posts_options()
{
    $defaults = array( array() );

    return apply_filters('master_plugin_default_posts_options', $defaults);
}

/**
 * Initializes the plugin's display options page by registering the Sections,
 * Fields, and Settings.
 *
 * This function is registered with the 'admin_init' hook.
 */
function master_initialize_plugin_options()
{
    
    // If the plugin options don't exist, create them.
    if (false == get_option('master_plugin_sites_options')) {
        add_option('master_plugin_sites_options',  master_plugin_default_sites_options());
    } // end if
      
    // First, we register a section. This is necessary since all future options must belong to a
    add_settings_section('master_sites_section', // ID used to identify this section and with which to register options
        __('Opzioni Siti'), // Title to be displayed on the administration page
        'master_sites_options_callback', // Callback used to render the description of the section
        'master_plugin_sites_options'); // Page on which to add this section of options
                                       
    add_settings_field('master_sites', // ID used to identify the field throughout the plugin
        __('Siti'), // The label to the left of the option interface element
        'master_sites_callback', // The name of the function responsible for rendering the option interface
        'master_plugin_sites_options', // The page on which this option will be displayed
        'master_sites_section');
    
    // If the plugin options don't exist, create them.
    if (false == get_option('master_plugin_posts_options')) {
        add_option('master_plugin_posts_options',  master_plugin_default_posts_options());
    } // end if
    
    // First, we register a section. This is necessary since all future options must belong to a
    add_settings_section('master_posts_section', // ID used to identify this section and with which to register options
        __('Opzioni Post'), // Title to be displayed on the administration page
        'master_posts_options_callback', // Callback used to render the description of the section
        'master_plugin_posts_options'); // Page on which to add this section of options
     
    foreach ($types as $post_type) {
        add_settings_field($post_type, // ID used to identify the field throughout the plugin
            __($post_type), // The label to the left of the option interface element
            'master_posts_callback', // The name of the function responsible for rendering the option interface
            'master_plugin_posts_options', // The page on which this option will be displayed
            'master_posts_section',
            array('post_type' => $post_type)
        );
    }
    
    // Finally, we register the fields with WordPress
    register_setting('master_plugin_sites_options', 'master_plugin_sites_options', 'sanitize_sites_options');
    register_setting('master_plugin_posts_options', 'master_plugin_posts_options');
    
    
    
} // end master_initialize_plugin_options



function sanitize_sites_options($sites) {
    foreach (array_keys($sites) as $i) {
        if (empty($sites[$i]['url'])) {
            unset($sites[$i]);
        }
    }
    return array_values($sites);
}

add_action('admin_init', 'master_initialize_plugin_options');

function master_sites_options_callback()
{
    echo '<p>' . __('Inserisci i dati relativi agli slave') . '</p>';
}

function master_posts_options_callback()
{
    echo '<p>' . __('Inserisci i dati relativi ai post') . '</p>';
}

function master_sites_callback()
{
    $sites = get_option('master_plugin_sites_options');
    $i = 0;
?>
<table class="widefat">
	<thead>
		<tr valign="top">
			<th scope="column" class="manage-column check-column"></th>
			<th scope="column" class="manage-column"><?php _e( 'Address'); ?></th>
			<th scope="column" class="manage-column"><?php _e( 'Username'); ?></th>
			<th scope="column" class="manage-column"><?php _e( 'Password' ); ?></th>
			<th scope="column" class="manage-column"><?php _e( 'Status' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($sites as $site): ?>
		<tr>
			<td><input type="checkbox" name="master_plugin_sites_options[<?php _e($i); ?>][enabled]" value="1"
			     <?php  checked( 1, isset( $site['enabled'] ) ? 1 : 0 ) ?> /></td>
			<td><input type="text" name="master_plugin_sites_options[<?php _e($i); ?>][url]" value="<?php echo $site['url'];  ?>" ></td>
			<td><input type="text" name="master_plugin_sites_options[<?php _e($i); ?>][username]" value="<?php echo $site['username'];  ?>" ></td>
			<td><input type="text" name="master_plugin_sites_options[<?php _e($i); ?>][password]" value="<?php echo $site['password'];  ?>" ></td>
			<td><label>OK</label></td>
		</tr>
    <?php $i++; ?>
	<?php endforeach; ?>
	   <tr>
			<td><input type="checkbox" name="master_plugin_sites_options[<?php _e($i); ?>][enabled]" value="1" /></td>
			<td><input type="text" name="master_plugin_sites_options[<?php _e($i); ?>][url]" ></td>
			<td><input type="text" name="master_plugin_sites_options[<?php _e($i); ?>][username]"  ></td>
			<td><input type="text" name="master_plugin_sites_options[<?php _e($i); ?>][password]" ></td>
			<td><label>OK</label></td>
		</tr>
	</tbody>
</table>
<?php
}

function master_posts_callback($args) {
    $post_type = $args['post_type'];
    $sites = get_option('master_plugin_sites_options');
    $rules = get_option('master_plugin_posts_options');
    ?>
        <fieldset>
         <?php foreach ($sites as $site): ?>
            <?php if (isset($site['enabled']) && $site['enabled']):?>
                <?php $url = $site['url']; ?>
                <label>
                    <input type="checkbox" value="1" name="<?php _e("master_plugin_posts_options[$post_type][$url]") ?>" <?php checked(1, isset($rules[$post_type][$url]) ? 1 : 0 ) ?> />
                    <?php _e($url) ?>
                </label><br/>
            <?php endif; ?>
          <?php endforeach;?>
        </fieldset>
    <?php 
}