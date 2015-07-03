<?php



class Test extends WP_UnitTestCase
{

    var $ID;

    var $post;

    var $site;

    function setUp()
    {
        $this->ID = wp_insert_post(array(
            'post_content' => 'test'
        ));
        $this->post = get_post($this->ID);
        $this->site = 'testsite';
    }

    function test_new_post()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $remote_post = get_post($remote_id);
        $this->assertEquals($this->post->post_content, $remote_post->post_content);
    }
    
    function test_update_post()
    {
        $master = new Master();
    
        $remote_id = $master->push($this->post, $this->site);
        
        wp_update_post(array('ID' => $this->ID, 'post_content' => 'updated'));
        $updated_post = get_post($this->ID);
        $this->assertEquals('updated', $updated_post->post_content);
        $master->push($updated_post, $this->site);
        
        $remote_post = get_post($remote_id);
        $this->assertEquals('updated', $remote_post->post_content);
    }
    
    

    function test_taxonomy()
    {
        $master = new Master();
        $taxonomy = 'post_tag';
        
        $result = wp_set_post_terms($this->ID, 'test_term', $taxonomy);
        
        $remote_id = $master->push($this->post, $this->site);
        $local_terms = wp_get_post_terms($this->ID, $taxonomy);
        $this->assertEquals($local_terms, wp_get_post_terms($remote_id, $taxonomy));
    }

    function test_thumbnail()
    {
        // $filename should be the path to a file in the upload directory.
        $filename = __DIR__ . '/wp.jpg';
        
        // The ID of the post this attachment is for.
        $parent_post_id = $this->post->ID;
        
        // Check the type of file. We'll use this as the 'post_mime_type'.
        $filetype = wp_check_filetype(basename($filename), null);
        
        // Get the path to the upload directory.
        $wp_upload_dir = wp_upload_dir();
        
        // Prepare an array of post data for the attachment.
        $attachment = array(
            'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert the attachment.
        $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);
        
        // Generate the metadata for the attachment, and update the database record.
        $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($this->post, $attach_id);
        
        $master = new Master();
       
        $remote_id = $master->push($this->post, $this->site);
        $remote_thumb_id = get_post_thumbnail_id($remote_id);
        $this->assertEquals(get_post($attach_id)->post_title, get_post($remote_thumb_id)->post_title);
    }
    
    function test_gallery()
    {
        $master = new Master();
    
        $remote_id = $master->push($this->post, $this->site);
    
        $test_id = wp_insert_post(array(
            'post_content' => "[gallery ids=\"$this->ID\"]"
        ));
        
        $test_post = get_post($test_id);
        $remote_test_id = $master->push($test_post, $this->site);
        $this->assertEquals("[gallery ids=\"$remote_id\"]", get_post($remote_test_id)->post_content);
        
    }

    function test_new_custom_field()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $meta_key = 'meta_tk_test';
        add_post_meta($this->ID, $meta_key, 'test');
        
        $master->update_custom_fields($this->site, $this->ID, $remote_id);
        
        $this->assertEquals(get_post_meta($this->ID, $meta_key), get_post_meta($remote_id, $meta_key));
    }

    function test_update_custom_field()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $meta_key = 'meta_tk_test';
        add_post_meta($this->ID, $meta_key, 'test');
        
        $master->update_custom_fields($this->site, $this->ID, $remote_id);
        
        update_post_meta($this->ID, $meta_key, 'test2');
        $master->update_custom_fields($this->site, $this->ID, $remote_id);
        
        $this->assertEquals(get_post_meta($this->ID, $meta_key), get_post_meta($remote_id, $meta_key));
    }

    function test_same_custom_field()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $meta_key = 'meta_tk_test';
        add_post_meta($this->ID, $meta_key, 'test');
        
        $master->update_custom_fields($this->site, $this->ID, $remote_id);
        $master->update_custom_fields($this->site, $this->ID, $remote_id);
        
        $this->assertEquals(2, $master->client->calls);
    }

    function test_get_site_list()
    {
        $site_opt[0]['name'] = 'site1';
        $site_opt[0]['enabled'] = 1;
        $site_opt[1]['name'] = 'site2';
        $site_opt[1]['enabled'] = 1;
        update_option(SITE_OPT, $site_opt);
        
        $post_opt['custom1']['site1'] = 1;
        $post_opt['custom1']['site2'] = 1;
        $post_opt['custom2']['site2'] = 1;
        update_option(POST_OPT, $post_opt);
        
        $ID = wp_insert_post(array(
            'post_content' => 'test',
            'post_type' => 'custom1'
        ));
        
        $post = get_post($ID);
        
        $master = new Master();
        $site_list = $master->get_site_list($post);
        $this->assertEquals(array(
            'site1',
            'site2'
        ), $site_list);
    }

    function test_publish_post()
    {
        $site_opt[0]['name'] = 'site1';
        $site_opt[0]['enabled'] = 1;
        update_option(SITE_OPT, $site_opt);
        $post_opt['post']['site1'] = 1;
        update_option(POST_OPT, $post_opt);
        
        $master = new Master();
        $master->publish_post($this->ID, $this->post);
        
        $mapping = get_post_meta($this->ID, MAPPING_META_KEY, true);
        $remote_id = $mapping['site1'];
        $remote_post = get_post($remote_id);
        $this->assertEquals($this->post->post_content, $remote_post->post_content);
    }
    
    
}
