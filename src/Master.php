<?php

class Master
{

    var $calls;

    function __construct()
    {
        $calls = 0;
    }

    public function push($post, $site)
    {
        $mapping = get_post_meta($post->ID, 'meta_master_mappping', true);
        if (isset($mapping[$site])) {
            $content = array();
            $content['post_title'] = $post->post_title;
            $content['post_content'] = $post->post_content;
            $content['post_excerpt'] = $post->post_excerpt;
            $content['post_status'] = $post->post_status;
        } else {
            $content = get_object_vars($post);
            unset($content['post_author']);
        }
        
        $content['terms_names'] = $this->get_terms_names($post);
        
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $remote_thumb_id = $this->get_or_push_thumbnail($thumbnail_id, $site);
            $content['post_thumbnail'] = $remote_thumb_id;
        }
        
        $content['post_content'] = $this->filter_gallery_shortcode($content['post_content'], $site);
        
        $content = apply_filters('push_content', $content, $post, $site);
        if (isset($mapping[$site])) {
            $remote_id = $mapping[$site];
            $this->xmlrpc_edit_post($site, $remote_id, $content);
        } else {
            $remote_id = $this->xmlrpc_new_post($site, $content);
            $mapping[$site] = $remote_id;
            $mapping = apply_filters('update_mapping', $mapping, $post, $site);
            update_post_meta($post->ID, 'meta_master_mappping', $mapping);
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
                $remote_images[] = $this->get_or_push_thumbnail($gallery_thumbnail_id, $site);
            }
            $ids = implode(',', $remote_images);
            $replacement = "[gallery ids=\"$ids\"]";
            $post_content = preg_replace($gallery_shortcode_regex, $replacement, $post_content);
        }
        return $post_content;
    }

    function get_or_push_thumbnail($thumbnail_id, $site)
    {
        $image = get_post($thumbnail_id);
        $mapping = get_post_meta($thumbnail_id, 'meta_master_mappping', true);
        if ($mapping && isset($mapping[$site])) {
            return $mapping[$site];
        }
        $remote_id = $this->push($image, $site);
        $wp_attachment_metadata = get_post_meta($thumbnail_id, '_wp_attachment_metadata', true);
        $wp_attached_file = get_post_meta($thumbnail_id, '_wp_attached_file', true);
        $this->xmlrpc_update_attachment_meta($remote_id, $wp_attachment_metadata, $wp_attached_file);
        return $remote_id;
    }

    /**
     */
    private function get_terms_names($post)
    {
        $terms_names = array();
        $tax_list = get_object_taxonomies($post->post_type);
        foreach ($tax_list as $tax) {
            $terms_names[$tax] = wp_get_post_terms($post->ID, $tax, array(
                "fields" => "names"
            ));
        }
        return $terms_names;
    }

    /**
     */
    public function update_custom_fields($site, $id, $remote_id)
    {
        $custom_fields = $this->get_custom_fields($site, $id, $remote_id);
        if ($custom_fields) {
            $this->xmlrpc_edit_post($site, $remote_id, array(
                'custom_fields' => $custom_fields
            ));
        }
    }

    private function get_custom_fields($site, $id, $remote_id)
    {
        $custom_fields = array();
        $local = $this->get_local_custom_fields($id);
        
        if (empty($local))
            return false;
        
        $remote = $this->get_remote_custom_fields($site, $remote_id);
        
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

    private function get_remote_custom_fields($site, $remote_id)
    {
        $wp_xmlrpc_server = new wp_xmlrpc_server();
        $args = array(
            1,
            'admin',
            'password',
            $remote_id,
            array(
                'custom_fields'
            )
        );
        $remote = $wp_xmlrpc_server->wp_getPost($args);
        $remote = $remote['custom_fields'];
        return $remote;
    }

    private function get_local_custom_fields($id)
    {
        $wp_xmlrpc_server = new wp_xmlrpc_server();
        $args = array(
            1,
            'admin',
            'password',
            $id,
            array(
                'custom_fields'
            )
        );
        $local = $wp_xmlrpc_server->wp_getPost($args);
        $local = $local['custom_fields'];
        $local = array_filter($local, array(
            $this,
            'update_needed'
        ));
        return $local;
    }

    private function update_needed($item)
    {
        $meta_key = $item['key'];
        return strstr($meta_key, 'meta_tk') || $meta_key == '_wp_attachment_metadata';
    }

    private function xmlrpc_new_post($site, $content)
    {
        $this->calls ++;
        $wp_xmlrpc_server = new wp_xmlrpc_server();
        $args = array(
            1,
            'admin',
            'password',
            $content
        );
        return (int) $wp_xmlrpc_server->wp_newPost($args);
    }

    private function xmlrpc_edit_post($site, $id, $content_struct)
    {
        $this->calls ++;
        $wp_xmlrpc_server = new wp_xmlrpc_server();
        $args = array(
            1,
            'admin',
            'password',
            $id,
            $content_struct
        );
        
        return (int) $wp_xmlrpc_server->wp_editPost($args);
    }

    private function xmlrpc_update_attachment_meta($id, $wp_attachment_metadata, $wp_attached_file)
    {
        update_post_meta($id, '_wp_attachment_metadata', $wp_attachment_metadata);
        update_post_meta($id, '_wp_attached_file', $wp_attached_file);
    }

    private function entry_enabled($entry)
    {
        return isset($entry['enabled']) && $entry['enabled'];
    }

    private function get_entry_name($entry)
    {
        return $entry['name'];
    }

    function get_site_list($post)
    {
        $site_opt = get_option('master_plugin_sites_options');
        $post_opt = get_option('master_plugin_posts_options');
        $destinations = array_filter($site_opt, array(
            $this,
            'entry_enabled'
        ));
        $sites = array_map(array(
            $this,
            'get_entry_name'
        ), $destinations);
        $target_sites = array_keys(array_filter($post_opt[$post->post_type]));
        $target_sites = apply_filters('target_sites', $target_sites, $post);
        return array_intersect($target_sites, $sites);
    }

    function publish_post($ID, $post)
    {
        $site_list = $this->get_site_list($post);
        foreach ($site_list as $site) {
            $remote_id = $this->push($post, $site);
            $this->update_custom_fields($site, $ID, $remote_id);
        }
    }

    function update_post_meta($meta_id, $post_id)
    {
        $mapping = get_post_meta($post_id, 'meta_master_mappping', true);
        if ($mapping) {
            foreach ($mapping as $site => $remote_id) {
                $this->update_custom_fields($site, $post_id, $remote_id);
            }
        }
    }
}
