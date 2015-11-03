<?php
require_once (ABSPATH . WPINC . '/class-IXR.php');
require_once (ABSPATH . WPINC . '/class-wp-xmlrpc-server.php');

interface xmlrpc_client_interface
{

    function xmlrpc_get_remote_custom_fields($site, $remote_id);

    function xmlrpc_new_post($site, $content);

    function xmlrpc_edit_post($site, $id, $content_struct);

    function xmlrpc_update_attachment_meta($site, $id, $wp_attachment_metadata, $wp_attached_file);

    function xmlrpc_upload_file($site, $id);
}

class xmlrpc_test_client implements xmlrpc_client_interface
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

    function xmlrpc_update_attachment_meta($site, $id, $wp_attachment_metadata, $wp_attached_file)
    {
        update_post_meta($id, '_wp_attachment_metadata', $wp_attachment_metadata);
        update_post_meta($id, '_wp_attached_file', $wp_attached_file);
    }

    function xmlrpc_upload_file($site, $id)
    {
        throw new Exception("Not implemented");
    }
}

class xmlrpc_client implements xmlrpc_client_interface
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
}