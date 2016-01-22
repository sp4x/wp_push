<?php
require_once (ABSPATH . WPINC . '/class-IXR.php');
require_once (ABSPATH . WPINC . '/class-wp-xmlrpc-server.php');

interface wp_api_client
{

    function xmlrpc_get_remote_custom_fields($site, $remote_id);

    function xmlrpc_new_post($site, $content);
    
    function xmlrpc_new_term($site, $wp_term);

    function xmlrpc_edit_post($site, $id, $content_struct);

    function xmlrpc_update_attachment_meta($site, $id, $wp_attachment_metadata, $wp_attached_file);

    function xmlrpc_upload_file($site, $id);
    
    function xmlrpc_update_term_meta($site, $remote_id, $meta_key, $_meta_value);
    
    function xmlrpc_add_term_meta($site, $remote_id, $metadata);
}

class xmlrpc_test_client implements wp_api_client
{

    var $calls = 0;

    function xmlrpc_get_remote_custom_fields($site, $remote_id)
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

    function xmlrpc_new_post($site, $content)
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
    
    function xmlrpc_new_term($site, $wp_term)
    {
        $this->calls ++;
        $content = array("name" => $wp_term->name . "-test", "slug" => $wp_term->slug . "-test", "description" => $wp_term->description, "taxonomy" => $wp_term->taxonomy);
        $wp_xmlrpc_server = new wp_xmlrpc_server();
        $args = array(
            1,
            'admin',
            'password',
            $content
        );
        $response = $wp_xmlrpc_server->wp_newTerm($args);
        return (int) $response;
    }

    function xmlrpc_edit_post($site, $id, $content_struct)
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
    
    function xmlrpc_edit_term($site, $remote_id, $wp_term)
    {
        $this->calls ++;
        $content = array("name" => $wp_term->name . "-test", "slug" => $wp_term->slug . "-test", "description" => $wp_term->description, "taxonomy" => $wp_term->taxonomy);
        $wp_xmlrpc_server = new wp_xmlrpc_server();
        $args = array(
            1,
            'admin',
            'password',
            $remote_id,
            $content
        );
        $response = $wp_xmlrpc_server->wp_editTerm($args);
        return (int) $response;
    }

    function xmlrpc_update_attachment_meta($site, $id, $wp_attachment_metadata, $wp_attached_file)
    {
        update_post_meta($id, '_wp_attachment_metadata', $wp_attachment_metadata);
        update_post_meta($id, '_wp_attached_file', $wp_attached_file);
    }

    function xmlrpc_upload_file($site, $id)
    {
        throw new Exception("Not implemented");
    }
    
    function xmlrpc_update_term_meta($site, $remote_id, $meta_key, $_meta_value) 
    {
        update_term_meta($remote_id, $meta_key, $_meta_value);
    }
    
    function xmlrpc_add_term_meta($site, $term_id, $metadata) 
    {
        foreach ($metadata as $meta_key => $meta_value) {
            if (array_key_exists(0, $meta_value) && count($meta_value) == 1) {
                $meta_value = $meta_value[0];
            }
            add_term_meta($term_id, $meta_key, $meta_value);
        }
    }
}

class xmlrpc_client implements wp_api_client
{

    function xmlrpc_get_remote_custom_fields($site, $remote_id)
    {
        $client = $this->get_client($site);
        $credentials = $this->get_credentials($site);
        $success = $client->query('wp.getPost', 1, $credentials['username'], $credentials['password'], $remote_id, array(
            'custom_fields'
        ));
        if (! $success) {
            $this->handle_error($client);
        }
        $remote = $client->getResponse();
        $remote = $remote['custom_fields'];
        return $remote;
    }

    private function get_credentials($site)
    {
        $site_opt = get_option(SITE_OPT);
        foreach ($site_opt as $entry) {
            if ($entry['name'] == $site) {
                return $entry;
            }
        }
        throw new Exception("$site not configured!");
    }

    private function get_client($site)
    {
        return new IXR_Client("http://$site/xmlrpc.php");
    }

    private function handle_error($client)
    {
        throw new Exception($client->getErrorMessage());
    }

    function xmlrpc_new_post($site, $content)
    {
        $client = $this->get_client($site);
        $credentials = $this->get_credentials($site);
        $success = $client->query('wp.newPost', 1, $credentials['username'], $credentials['password'], $content);
        if (! $success) {
            $this->handle_error($client);
        }
        return (int) $client->getResponse();
    }
    
    function xmlrpc_new_term($site, $wp_term)
    {
        $client = $this->get_client($site);
        $credentials = $this->get_credentials($site);
        $content = array("name" => $wp_term->name, "slug" => $wp_term->slug, "description" => $wp_term->description, "taxonomy" => $wp_term->taxonomy);
        $success = $client->query('wp.newTerm', 1, $credentials['username'], $credentials['password'], $content);
        if (! $success) {
            $this->handle_error($client);
        }
        return (int) $client->getResponse();
    }

    function xmlrpc_edit_post($site, $id, $content_struct)
    {
        $client = $this->get_client($site);
        $credentials = $this->get_credentials($site);
        $success = $client->query('wp.editPost', 1, $credentials['username'], $credentials['password'], $id, $content_struct);
        if (! $success) {
            $this->handle_error($client);
        }
        
        return (int) $client->getResponse();
    }
    
    function xmlrpc_edit_term($site, $remote_id, $wp_term)
    {
        $client = $this->get_client($site);
        $credentials = $this->get_credentials($site);
        $content = array("name" => $wp_term->name, "slug" => $wp_term->slug, "description" => $wp_term->description, "taxonomy" => $wp_term->taxonomy);
        $success = $client->query('wp.editTerm', 1, $credentials['username'], $credentials['password'], $remote_id, $content);
        if (! $success) {
            $this->handle_error($client);
        }
        return (int) $client->getResponse();
    }

    function xmlrpc_update_attachment_meta($site, $id, $wp_attachment_metadata, $wp_attached_file)
    {
        $client = $this->get_client($site);
        $credentials = $this->get_credentials($site);
        $success = $client->query('tk.editAttachmentMeta', 1, $credentials['username'], $credentials['password'], $id, $wp_attachment_metadata, $wp_attached_file);
        if (! $success) {
            $this->handle_error($client);
        }
    }

    function xmlrpc_upload_file($site, $id)
    {
        throw new Exception("Not implemented");
    }
    
    function xmlrpc_update_term_meta($site, $remote_id, $meta_key, $_meta_value)
    {
        throw new Exception("Not implemented");
    }
    
    function xmlrpc_add_term_meta($site, $term_id, $metadata) {
        throw new Exception("Not implemented");
    }
    
}