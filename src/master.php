<?php
require_once __DIR__ . '/client.php';
require_once __DIR__ . '/media.php';

class Master
{

    var $client;

    var $media_manager;

    public function __construct($client = null, $media_manager = null)
    {
        if ($client) {
            $this->client = $client;
        } else {
            $this->client = defined("WP_TESTS_DOMAIN") ? new xmlrpc_test_client() : new xmlrpc_client();
        }
        
        if ($media_manager) {
            $this->media_manager = $media_manager;
        } else {
            $this->media_manager = new NoUploadMediaManager($this->client);
        }
    }

    public function push($post, $site)
    {
        $mapping = get_post_meta($post->ID, MAPPING_META_KEY, true);
        if (isset($mapping[$site])) {
            $content = array();
            $content['post_title'] = $post->post_title;
            $content['post_content'] = $post->post_content;
            $content['post_excerpt'] = $post->post_excerpt;
            $content['post_status'] = $post->post_status;
            $content['post_date'] = new IXR_Date(strtotime($post->post_date));
        } else {
            $content = get_object_vars($post);
            unset($content['post_date_gmt']);
        }
        
        $content['terms'] = $this->get_terms($post, $site);
        
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $remote_thumb_id = $this->media_manager->get_or_push_thumbnail($thumbnail_id, $site);
            $content['post_thumbnail'] = $remote_thumb_id;
        }
        
        $content['post_content'] = $this->filter_gallery_shortcode($content['post_content'], $site);
        
        $content = apply_filters('push_content', $content, $post, $site);
        if (isset($mapping[$site])) {
            $remote_id = $mapping[$site];
            $this->client->xmlrpc_edit_post($site, $remote_id, $content);
        } else {
            $remote_id = $this->client->xmlrpc_new_post($site, $content);
            $mapping[$site] = $remote_id;
            $mapping = apply_filters('update_mapping', $mapping, $post, $site);
            update_post_meta($post->ID, MAPPING_META_KEY, $mapping);
        }
        return $remote_id;
    }

    private function filter_gallery_shortcode($post_content, $site)
    {
        $gallery_shortcode_regex = '/\[gallery ids="([\d,]+)"\]/';
        if (preg_match($gallery_shortcode_regex, $post_content, $matches)) {
            $images = preg_split('/,/', $matches[1]);
            $remote_images = array();
            foreach ($images as $gallery_thumbnail_id) {
                $remote_images[] = $this->media_manager->get_or_push_thumbnail($gallery_thumbnail_id, $site);
            }
            $ids = implode(',', $remote_images);
            $replacement = "[gallery ids=\"$ids\"]";
            $post_content = preg_replace($gallery_shortcode_regex, $replacement, $post_content);
        }
        return $post_content;
    }

    /**
     */
    private function get_terms($post, $site)
    {
        $terms = wp_get_post_terms($post->ID);
        $remote_terms = array();
        foreach ($terms as $term) {
            $mapping = get_term_meta($term->term_id, MAPPING_META_KEY, true);
            if (! isset($mapping[$site])) {
                $remote_id = $this->client->xmlrpc_new_term($site, $term);
                $metadata = get_term_meta($term->term_id);
                $this->client->xmlrpc_add_term_meta($site, $remote_id, $metadata);
                $mapping[$site] = $remote_id;
                update_term_meta($term->term_id, MAPPING_META_KEY, $mapping);
                $remote_terms[$term->taxonomy][] = $remote_id;
            }
        }
        return $remote_terms;
    }

    /**
     */
    public function update_custom_fields($site, $id, $remote_id)
    {
        $custom_fields = $this->get_custom_fields($site, $id, $remote_id);
        if ($custom_fields) {
            $this->client->xmlrpc_edit_post($site, $remote_id, array(
                'custom_fields' => $custom_fields
            ));
        }
        do_action('update_custom_fields', $site, $id, $remote_id);
    }

    private function get_custom_fields($site, $id, $remote_id)
    {
        $custom_fields = array();
        $local = $this->get_local_custom_fields($id);
        $local = array_filter($local, array(
            $this,
            'skip_mapping'
        ));
        $local = array_filter($local, array(
            $this,
            'skip_private_keys'
        ));
        $local = apply_filters('sync_custom_fields', $local, $site);
        
        if (empty($local))
            return false;
        
        $remote = $this->client->xmlrpc_get_remote_custom_fields($site, $remote_id);
        
        foreach ($local as $item) {
            unset($item['id']);
            $index = $this->search_field_in_array($item['key'], $remote);
            if (isset($remote[$index])) {
                $remote_item = $remote[$index];
                if ($item['value'] != $remote_item['value']) {
                    $custom_fields[] = array_merge($remote_item, $item);
                }
            } else {
                $custom_fields[] = $item;
            }
        }
        return $custom_fields;
    }

    private function skip_mapping($item)
    {
        $meta_key = is_array($item) ? $item['key'] : $item;
        return $meta_key != MAPPING_META_KEY;
    }

    private function skip_private_keys($item)
    {
        $meta_key = is_array($item) ? $item['key'] : $item;
        return $meta_key == '_wp_attachment_metadata' || substr($meta_key, 0, 1) != "_";
    }

    private function item_to_update($item, $remote_item)
    {
        if ($item['value'] != $remote_item['value']) {
            return array_merge($remote_item, $item);
        }
    }

    private function search_field_in_array($meta_key, $remote)
    {
        foreach ($remote as $index => $remote_item) {
            if ($remote_item['key'] == $meta_key) {
                return $index;
            }
        }
        return - 1;
    }

    private function entry_enabled($entry)
    {
        return isset($entry['enabled']) && $entry['enabled'];
    }

    private function get_entry_name($entry)
    {
        return $entry['name'];
    }

    public function get_site_list($post)
    {
        $site_opt = get_option(SITE_OPT);
        $post_opt = get_option(POST_OPT);
        $mapping = get_post_meta($post->ID, MAPPING_META_KEY, true);
        
        if (! $site_opt || ! $post_opt)
            return array();
        
        if (! isset($post_opt[$post->post_type]) && ! $mapping)
            return array();
        
        $target_sites = array();
        $destinations = array_filter($site_opt, array(
            $this,
            'entry_enabled'
        ));
        $sites = array_map(array(
            $this,
            'get_entry_name'
        ), $destinations);
        
        if (isset($post_opt[$post->post_type])) {
            $enabled_targets = array_keys(array_filter($post_opt[$post->post_type]));
            $target_sites = array_merge($target_sites, $enabled_targets);
        }
        
        if ($mapping) {
            $target_sites = array_merge($target_sites, array_keys($mapping));
        }
        
        $target_sites = apply_filters('target_sites', $target_sites, $post);
        return array_intersect($target_sites, $sites);
    }

    public function publish_post($ID, $post = null)
    {
        if ($post == null) {
            $post = get_post($ID);
        }
        $site_list = $this->get_site_list($post);
        foreach ($site_list as $site) {
            try {
                $remote_id = $this->push($post, $site);
                $this->update_custom_fields($site, $ID, $remote_id);
            } catch (Exception $e) {
                $message = "Errore aggiornamento $site, controlla i log";
                $this->handle_exception($e, $message);
            }
        }
    }

    private function handle_exception($e, $message)
    {
        $errors = get_option(ERRORS);
        $errors[] = $message;
        update_option(ERRORS, $errors);
        error_log($e);
    }

    public function update_post_meta($meta_id, $post_id, $meta_key, $meta_value)
    {
        $update = $this->skip_mapping($meta_key) && $this->skip_private_keys($meta_key);
        if ($update) {
            $mapping = get_post_meta($post_id, MAPPING_META_KEY, true);
            if ($mapping) {
                $this->update_custom_fields_for_mapping($post_id, $mapping);
            }
        }
        $this->media_manager->update_post_meta($meta_id, $post_id, $meta_key, $meta_value);
    }

    private function update_custom_fields_for_mapping($post_id, $mapping)
    {
        foreach ($mapping as $site => $remote_id) {
            try {
                $this->update_custom_fields($site, $post_id, $remote_id);
            } catch (Exception $e) {
                $message = "Errore aggiornamento custom fields su $site, controlla i log";
                $this->handle_exception($e, $message);
            }
        }
    }

    private function get_local_custom_fields($id)
    {
        $local_server = new wp_xmlrpc_server();
        $fields = $local_server->get_custom_fields($id);
        $callback = array(
            $this,
            'unserialize_custom_field_arrays'
        );
        return array_map($callback, $fields);
    }

    private function unserialize_custom_field_arrays($item)
    {
        $value = $item['value'];
        if (substr($value, 0, 2) == "a:") {
            $item['value'] = unserialize($value);
        }
        return $item;
    }

    public function update_term($term_id, $tt_id, $taxonomy)
    {
        $mapping = get_term_meta($term_id, MAPPING_META_KEY, true);
        $wp_term = get_term($term_id, $taxonomy);
        if ($mapping) {
            foreach ($mapping as $site => $remote_id) {
                $this->client->xmlrpc_edit_term($site, $remote_id, $wp_term);
            }
        }
    }

    public function update_term_meta($meta_id, $term_id, $meta_key, $_meta_value)
    {
        $update = $this->skip_mapping($meta_key) && $this->skip_private_keys($meta_key);
        if ($update) {
            $mapping = get_term_meta($term_id, MAPPING_META_KEY, true);
            if ($mapping) {
                foreach ($mapping as $site => $remote_id) {
                    $this->client->xmlrpc_update_term_meta($site, $remote_id, $meta_key, $_meta_value);
                }
            }
        }
    }
}






