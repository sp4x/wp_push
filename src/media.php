<?php

interface MediaManagerInterface
{

    function get_or_push_thumbnail($thumbnail_id, $site);
    function update_post_meta($meta_id, $post_id, $meta_key, $meta_value);
}

class NoUploadMediaManager implements MediaManagerInterface
{

    var $client;

    function __construct($client)
    {
        $this->client = $client;
    }

    function get_or_push_thumbnail($thumbnail_id, $site)
    {
        $master = new Master($this->client);
        $image = get_post($thumbnail_id);
        $mapping = get_post_meta($thumbnail_id, MAPPING_META_KEY, true);
        if ($mapping && isset($mapping[$site])) {
            $remote_id = $mapping[$site];
        } else {
            $remote_id = $master->push($image, $site);
        }
        $this->update_attachment_metadata($site, $thumbnail_id, $remote_id);

        return $remote_id;
    }
    
    
    function update_attachment_metadata($site, $thumbnail_id, $remote_id)
    {
        $wp_attachment_metadata = get_post_meta($thumbnail_id, '_wp_attachment_metadata', true);
        $wp_attached_file = get_post_meta($thumbnail_id, '_wp_attached_file', true);
        $this->client->xmlrpc_update_attachment_meta($site, $remote_id, $wp_attachment_metadata, $wp_attached_file);
    }
    
    function update_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
        if ($meta_key == '_wp_attachment_metadata' || $meta_key == '_wp_attached_file') {
            add_action('update_custom_fields', array($this, 'update_attachment_metadata'), 10, 3);
        }
    }

}

class MediaManager implements MediaManagerInterface
{

    var $client;

    function __construct($client)
    {
        $this->client = $client;
    }

    function get_or_push_thumbnail($thumbnail_id, $site)
    {
        $image = get_post($thumbnail_id);
        $mapping = get_post_meta($thumbnail_id, MAPPING_META_KEY, true);
        if ($mapping && isset($mapping[$site])) {
            return $mapping[$site];
        }
        $remote_id = $this->client->xmlrpc_upload_file($site, $thumbnail_id);
        $mapping[$site] = $remote_id;
        $mapping = apply_filters('update_mapping', $mapping, $image, $site);
        update_post_meta($thumbnail_id, MAPPING_META_KEY, $mapping);
        return $remote_id;
    }
    
    function update_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
        throw new Exception("Not implemented");
    }
}