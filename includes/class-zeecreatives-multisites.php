<?php
class ZeeCreatives_MultiSites
{
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct()
    {
        $this->version = ZEECREATIVES_MULTISITES_VERSION;
        $this->plugin_name = 'zeecreatives-multisites';
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    private function load_dependencies()
    {
        require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites-loader.php';
        require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites-i18n.php';
        require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'admin/class-zeecreatives-multisites-admin.php';
        require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'public/class-zeecreatives-multisites-public.php';
        require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites-api.php';

        $this->loader = new ZeeCreatives_MultiSites_Loader();
    }

    private function set_locale()
    {
        $plugin_i18n = new ZeeCreatives_MultiSites_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new ZeeCreatives_MultiSites_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    private function define_public_hooks()
    {
        $plugin_public = new ZeeCreatives_MultiSites_Public($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    private function define_api_hooks()
    {
        $plugin_api = new ZeeCreatives_MultiSites_API();
        $this->loader->add_action('rest_api_init', $plugin_api, 'register_routes');

        // Add hooks for database switching
        // $this->loader->add_action('init', $this, 'handle_database_switching');
        // $this->loader->add_filter('request', $this, 'handle_database_switching');
        // $this->loader->add_action('muplugins_loaded', $this, 'handle_database_switching');

    }

    public function run()
    {
        $this->loader->run();
        $this->setup_multisite();
        $this->setup_database_switching();
        $this->setup_upload_directory();
    }

    private function setup_multisite()
    {
        if (!is_multisite()) {
            // Convert to multisite if not already
            // This is a placeholder - actual conversion requires manual steps
            error_log('WordPress needs to be converted to Multisite manually.');
        }
    }

    private function setup_database_switching() {
        add_action('muplugins_loaded', array($this, 'handle_database_switching'), 0);
    }

    public function handle_database_switching() {
        if (is_multisite()) {
            $site_id = get_current_blog_id();
            
            if ($site_id == 1) {
                error_log("Main site detected, no database switch needed.");
                return;
            }

            $db_details = $this->get_site_db_details($site_id);

            if ($db_details) {
                try {
                    $this->switch_db($db_details);
                } catch (Exception $e) {
                    error_log("Database switching failed for site ID {$site_id}: " . $e->getMessage());
                }
            } else {
                error_log("No database credentials found for site ID {$site_id}.");
            }
        }
    }

    private function get_site_db_details($site_id)
    {
        global $wpdb;
        $credentials = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zeecreatives_site_db_credentials WHERE site_id = %d",
                $site_id
            ),
            ARRAY_A
        );
        return $credentials ? $credentials : false;
    }

    private function switch_db($db_details) {
        global $wpdb;

        if (empty($db_details['db_name']) || empty($db_details['db_user']) || empty($db_details['db_password']) || empty($db_details['db_host'])) {
            throw new Exception("Incomplete database credentials for database: " . json_encode($db_details));
        }

        $new_wpdb = new wpdb(
            $db_details['db_user'],
            $db_details['db_password'],
            $db_details['db_name'],
            $db_details['db_host']
        );

        if (!$new_wpdb->db_connect()) {
            throw new Exception("Failed to connect to the database: {$new_wpdb->last_error}");
        }

        // Set prefix
        $prefix = 'wp_';
        $new_wpdb->set_prefix($prefix);

        // Reassign the `$wpdb` global with the new instance
        $wpdb = $new_wpdb;

        // Test the connection by querying a known table
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}options'")) {
            throw new Exception("Database connection failed: options table not found.");
        }

        error_log("Successfully switched to database for site ID {$db_details['site_id']} with prefix {$prefix}.");
    }


    private function setup_upload_directory()
    {
        add_filter('upload_dir', array($this, 'custom_upload_dir'));
    }

    public function custom_upload_dir($uploads)
    {
        $site_id = get_current_blog_id();
        $uploads['basedir'] = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
        $uploads['baseurl'] = content_url() . '/uploads/sites/' . $site_id;
        $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
        $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
        return $uploads;
    }

    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    public function get_loader()
    {
        return $this->loader;
    }

    public function get_version()
    {
        return $this->version;
    }
}

