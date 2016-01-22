<?php

class Test extends WP_UnitTestCase
{

    var $ID;

    var $post;

    var $site;
    

    function setUp()
    {
        parent::setUp();
        $this->ID = wp_insert_post(array(
            'post_content' => 'test'
        ));
        $this->post = get_post($this->ID);
        $this->site = 'testsite';
        
        $site_opt[0]['name'] = 'site1';
        $site_opt[0]['enabled'] = 1;
        $site_opt[1]['name'] = 'site2';
        $site_opt[1]['enabled'] = 1;
        update_option(SITE_OPT, $site_opt);
        
        $post_opt['post']['site1'] = 1;
        $post_opt['custom1']['site1'] = 1;
        $post_opt['custom1']['site2'] = 1;
        $post_opt['custom2']['site2'] = 1;
        update_option(POST_OPT, $post_opt);
    }

    function test_new_post_is_created_in_remote_wp()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $remote_post = get_post($remote_id);
        $this->assertEquals($this->post->post_content, $remote_post->post_content);
    }

    function test_synced_post_is_updated_in_remote_wp()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        wp_update_post(array(
            'ID' => $this->ID,
            'post_content' => 'updated'
        ));
        $updated_post = get_post($this->ID);
        $this->assertEquals('updated', $updated_post->post_content);
        $master->push($updated_post, $this->site);
        
        $remote_post = get_post($remote_id);
        $this->assertEquals('updated', $remote_post->post_content);
    }

    function test_post_terms_are_pushed_to_remote_wp()
    {
        $master = new Master();
        $taxonomy = 'post_tag';
        
        $result = wp_set_post_terms($this->ID, 'test_term', $taxonomy);
        
        $remote_id = $master->push($this->post, $this->site);
        $local_terms = wp_get_post_terms($this->ID, $taxonomy);
        $remote_terms = wp_get_post_terms($remote_id, $taxonomy);
        
        $this->assertEquals(count($local_terms), count($remote_terms));
        $this->assertNotEquals($local_terms[0]->term_id, $remote_terms[0]->term_id);
    }
    
    function test_term_description_is_pushed_to_remote_wp()
    {
        $master = new Master();
        $taxonomy = 'post_tag';
        $term = wp_insert_term('test_term', $taxonomy, array('description' => 'test description'));
        
        if (is_wp_error($term)) {
            var_dump($term);
            throw new Exception("Error in wp_insert_term");
        }
    
        $result = wp_set_post_terms($this->ID, array($term['term_id']), $taxonomy);
    
        $remote_id = $master->push($this->post, $this->site);
        
        
        $local_terms = wp_get_post_terms($this->ID, $taxonomy);
        $remote_terms = wp_get_post_terms($remote_id, $taxonomy);
        $this->assertEquals('test description', $local_terms[0]->description);
        $this->assertEquals('test description', $remote_terms[0]->description);
    }
    
    function test_synced_term_is_updated()
    {
        $master = new Master();
        $taxonomy = 'post_tag';
        $term = wp_insert_term('test_term', $taxonomy, array('description' => 'test description'));
    
        if (is_wp_error($term)) {
            var_dump($term);
            throw new Exception("Error in wp_insert_term");
        }
    
        $term_id = $term['term_id'];
        $tt_id = $term['term_taxonomy_id'];
        $result = wp_set_post_terms($this->ID, array($term_id), $taxonomy);
    
        $remote_id = $master->push($this->post, $this->site);
        
        wp_update_term($term_id, $taxonomy, array("description" => "updated desc"));
        $master->update_term($term_id, $tt_id, $taxonomy);
    
    
        $local_terms = wp_get_post_terms($this->ID, $taxonomy);
        $remote_terms = wp_get_post_terms($remote_id, $taxonomy);
        $this->assertEquals('updated desc', $local_terms[0]->description);
        $this->assertEquals('updated desc', $remote_terms[0]->description);
    }

    function test_post_thumbnail_is_pushed_to_remote_wp()
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

    function test_remote_post_gallery_uses_remote_images_ids()
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

    function test_new_post_meta_is_pushed_to_remote_wp()
    {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $meta_key = 'meta_tk_test';
        add_post_meta($this->ID, $meta_key, 'test');
        
        $master->update_custom_fields($this->site, $this->ID, $remote_id);
        
        $this->assertEquals(get_post_meta($this->ID, $meta_key), get_post_meta($remote_id, $meta_key));
    }

    function test_updated_post_meta_is_pushed_to_remote_wp()
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

    function test_updated_post_meta__with_array_value_is_pushed_to_remote_wp()
    {
        $meta_key = 'meta_tk_test';
        $master = new Master();
        $value = array(
            "first" => "is first",
            "second" => "is second"
        );
        add_post_meta($this->ID, $meta_key, $value);
        $master->publish_post($this->ID, $this->post);
        
        $mapping = get_post_meta($this->ID, MAPPING_META_KEY, true);
        $remote_id = $mapping['site1'];
        $remote_meta = get_post_meta($remote_id, $meta_key, true);
        
        add_action('updated_post_meta', array(
            $master,
            'update_post_meta'
        ), 10, 4);
        
        
        $remote_meta = get_post_meta($remote_id, $meta_key, true);
        $this->assertEquals("is second", $remote_meta["second"]);
        clear_actions();
    }


    function test_retrieve_remote_site_list_based_on_post_type()
    {
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

    function test_post_is_pushed_to_remote_wp_on_post_published_hook()
    {
        $master = new Master();
        $master->publish_post($this->ID, $this->post);
        
        $mapping = get_post_meta($this->ID, MAPPING_META_KEY, true);
        $remote_id = $mapping['site1'];
        $remote_post = get_post($remote_id);
        $this->assertEquals($this->post->post_content, $remote_post->post_content);
    }
    
    function test_local_and_remote_post_have_the_same_post_date() {
        $master = new Master();
        
        $remote_id = $master->push($this->post, $this->site);
        
        $remote_post = get_post($remote_id);
        $this->assertEquals($this->post->post_date, $remote_post->post_date);
    }
    
    function test_term_meta_is_pushed_to_remote_wp()
    {
        $master = new Master();
        $taxonomy = 'post_tag';
        $term = wp_insert_term('test_term', $taxonomy, array('description' => 'test description'));
        
        if (is_wp_error($term)) {
            var_dump($term);
            throw new Exception("Error in wp_insert_term");
        }
    
        $term_id = $term['term_id'];
        
        $result = wp_set_post_terms($this->ID, array($term_id), $taxonomy);
    
        $master->push($this->post, $this->site);
        
        $mapping = get_term_meta($term_id, MAPPING_META_KEY, true);
        $remote_id = $mapping[$this->site];
        $meta_id = update_term_meta($term_id, "test_meta_key", "dummy");
        $master->update_term_meta($meta_id, $term_id, "test_meta_key", "dummy");
        
        $this->assertEquals("dummy", get_term_meta($remote_id, "test_meta_key", true));
        
    }
    
    function test_existing_term_meta_is_pushed_when_new_term_is_created_on_remote_wp() {
        $master = new Master();
        $taxonomy = 'post_tag';
        $term = wp_insert_term('test_term', $taxonomy, array('description' => 'test description'));
        $term_id = $term['term_id'];
        $meta_id = update_term_meta($term_id, "test_meta_key", "dummy");
        
        if (is_wp_error($term)) {
            var_dump($term);
            throw new Exception("Error in wp_insert_term");
        }
        
        
        
        $result = wp_set_post_terms($this->ID, array($term_id), $taxonomy);
        
        $master->push($this->post, $this->site);
        
        $mapping = get_term_meta($term_id, MAPPING_META_KEY, true);
        $remote_id = $mapping[$this->site];
        
        
        $this->assertEquals("dummy", get_term_meta($remote_id, "test_meta_key", true));
    }
    
    
    
}
