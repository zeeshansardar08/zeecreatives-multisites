<?php
class ZeeCreatives_MultiSites_Admin
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Hook for adding admin menu
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

        // Hook for handling AJAX requests
        add_action('wp_ajax_zeecreatives_create_site', array($this, 'ajax_create_site'));

        // Hook for enqueuing styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets($hook)
    {
        // Ensure styles/scripts load only on the admin page for this plugin
        if ($hook === 'toplevel_page_zeecreatives-multisites') {
            $this->enqueue_styles();
            $this->enqueue_scripts();
        }
    }

    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'assets/css/zeecreatives-multisites-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'assets/js/zeecreatives-multisites-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        // Pass AJAX URL and nonce to the script
        wp_localize_script($this->plugin_name, 'zeecreatives_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zeecreatives_create_site_nonce'),
        ));
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'ZeeCreatives MultiSites',
            'ZeeCreatives MultiSites',
            'manage_options',
            'zeecreatives-multisites',
            array($this, 'display_plugin_admin_page'),
            'dashicons-networking',
            81
        );
    }

    public function display_plugin_admin_page()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/admin-page.php';
        zeecreatives_multisites_admin_page();
    }

    public function ajax_create_site()
    {
        check_ajax_referer('zeecreatives_create_site_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }

        parse_str($_POST['formData'], $form_data);

        $site_url = sanitize_text_field($form_data['site_url']);
        $site_name = sanitize_text_field($form_data['site_name']);
        $admin_email = sanitize_email($form_data['admin_email']);
        $admin_password = sanitize_text_field($form_data['admin_password']);
        $current_user_password = sanitize_text_field($form_data['current_user_password']);
        $current_user = wp_get_current_user();

        // Get Token
        $token_response = wp_remote_post(rest_url('zeecreatives/v1/get-token'), array(
            'body' => array(
                'username' => $current_user->user_login,
                'password' => $current_user_password,
            ),
        ));

        if (is_wp_error($token_response)) {
            wp_send_json_error(array('message' => 'Failed to retrieve token: ' . $token_response->get_error_message()));
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_data['token'])) {
            wp_send_json_error(array('message' => 'Invalid token response.'));
        }

        $token = $token_data['token'];

        // Create Site
        $site_response = wp_remote_post(rest_url('zeecreatives/v1/create-site'), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'site_url' => $site_url,
                'site_name' => $site_name,
                'admin_email' => $admin_email,
                'admin_password' => $admin_password,
            )),
        ));

        if (is_wp_error($site_response)) {
            wp_send_json_error(array('message' => 'Failed to create site: ' . $site_response->get_error_message()));
        }

        $site_data = json_decode(wp_remote_retrieve_body($site_response), true);
        // error_log('Site Creation Response: ' . print_r($site_data, true)); // Log full response for debugging

        if (!isset($site_data['site_id'])) {
            wp_send_json_error(array('message' => 'Site creation failed. Response: ' . print_r($site_data, true)));
        }

        wp_send_json_success(array(
            'message' => 'Site created successfully!',
            'site_id' => $site_data['site_id'],
        ));
    }
}
