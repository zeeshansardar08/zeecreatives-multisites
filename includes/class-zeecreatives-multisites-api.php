<?php
require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites-jwt.php';

class ZeeCreatives_MultiSites_API
{
    public function register_routes()
    {
        register_rest_route('zeecreatives/v1', '/create-site', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_site'),
            'permission_callback' => array($this, 'create_site_permissions_check'),
            'args' => array(
                'site_url' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'admin_email' => array(
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                ),
                'admin_password' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'site_name' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route('zeecreatives/v1', '/get-token', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_token'),
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
    }

    public function create_site_permissions_check($request)
    {
        $token = $request->get_header('Authorization');
        if (empty($token)) {
            return false;
        }

        $token = str_replace('Bearer ', '', $token);
        $user_id = ZeeCreatives_MultiSites_JWT::validate_token($token);

        return $user_id && user_can($user_id, 'manage_options');
    }

    public function get_token($request)
    {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Invalid credentials', array('status' => 401));
        }

        $token = ZeeCreatives_MultiSites_JWT::generate_token($user->ID);

        return new WP_REST_Response(array('token' => $token), 200);
    }

    public function create_site($request)
    {
        $site_url = $request->get_param('site_url');
        $admin_email = $request->get_param('admin_email');
        $admin_password = $request->get_param('admin_password');
        $site_name = $request->get_param('site_name') ?: $site_url;

        if (!$this->validate_inputs($site_url, $admin_email, $admin_password)) {
            return new WP_Error('invalid_input', 'Invalid input parameters', array('status' => 400));
        }

        // Create new database and user
        $site_id = $this->create_wp_site($site_url, $site_name, $admin_email, $admin_password);

        if (is_wp_error($site_id)) {
            return $site_id;
        }

        // $this->setup_new_site($site_id);

        return new WP_REST_Response(array(
            'site_id' => $site_id,
            'message' => 'Site created successfully',
        ), 200);
    }

    private function validate_inputs($site_url, $admin_email, $admin_password)
    {
        if (!preg_match('/^[a-z0-9-]+$/', $site_url)) {
            return false;
        }
        if (!is_email($admin_email)) {
            return false;
        }
        if (strlen($admin_password) < 8) {
            return false;
        }
        return true;
    }

    private function create_wp_site($site_url, $site_name, $admin_email, $admin_password)
    {
        global $wpdb;

        $domain = $site_url . '.' . DOMAIN_CURRENT_SITE;
        $path = '/';
        $db_name = 'wp_' . $site_url . '_' . uniqid();
        $db_user = 'user_' . $site_url . '_' . uniqid();
        $db_password = wp_generate_password(24, true, true);
        $db_host = DB_HOST;

        // Create new database using wpdb
        $wpdb->query("CREATE DATABASE `$db_name`");
        $wpdb->query($wpdb->prepare(
            "CREATE USER %s@%s IDENTIFIED BY %s",
            $db_user,
            $db_host,
            $db_password
        ));
        $wpdb->query($wpdb->prepare(
            "GRANT ALL PRIVILEGES ON `$db_name`.* TO %s@%s",
            $db_user,
            $db_host
        ));
        $wpdb->query("FLUSH PRIVILEGES");

        // Switch to the new database
        $new_wpdb = new wpdb($db_user, $db_password, $db_name, $db_host);
        $new_wpdb->set_prefix('wp_');

        // Import WordPress default tables into the new database
        // $this->import_wordpress_tables($new_wpdb, $domain);

        $this->initialize_site_database($new_wpdb, $site_url, $admin_email, $admin_password);
        $this->activate_defaults($new_wpdb);


        // Setup multisite with new db
        $site_id = $this->setup_new_blog_in_multisite($domain, $path, $site_name);

        // $site_id = wpmu_create_blog($domain, $path, $site_name, get_current_user_id());

        if (is_wp_error($site_id)) {
            return $site_id;
        }
        $this->create_sitemeta_table_and_data($new_wpdb, $site_id, NEW_DB_PREFIX);
        // Store database credentials
        $wpdb->insert(
            $wpdb->prefix . 'zeecreatives_site_db_credentials',
            array(
                'site_id' => $site_id,
                'db_name' => $db_name,
                'db_user' => $db_user,
                'db_password' => $db_password,
                'db_host' => $db_host
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        // Create the admin user
        $user_id = wpmu_create_user($site_url, $admin_password, $admin_email);
        if (!$user_id) {
            return new WP_Error('user_creation_failed', 'Failed to create admin user');
        }
        add_user_to_blog($site_id, $user_id, 'administrator');


        return $site_id;
    }

    private function setup_new_blog_in_multisite($domain, $path, $site_name, )
    {
        global $wpdb;

        $subdomains_table = $wpdb->prefix . 'zeecreatives_site_db_credentials'; 

        // Fetch the highest site_id from the subdomains table
        $max_site_id = $wpdb->get_var("SELECT MAX(site_id) FROM $subdomains_table");

        // Dynamically generate the new site ID by adding 1 to the max_site_id
        $new_site_id = $max_site_id + 1;

        // Prepare the data for the new site in wp_blogs table
        $blog_data = array(
            'domain' => $domain,
            'path' => $path,
            'site_id' => $new_site_id,  // Use the dynamically generated site ID
            'public' => 1,
            'archived' => 0,
            'mature' => 0,
            'spam' => 0,
            'deleted' => 0,
            'lang_id' => 0,
            'registered' => current_time('mysql')
        );

        // Insert the new site entry into the wp_blogs table
        $blogs_table = $wpdb->prefix . 'blogs';
        $wpdb->insert($blogs_table, $blog_data);

        // Fetch the last inserted blog ID (site_id) from the wp_blogs table
        $site_id = $wpdb->insert_id;

        // Also add an entry in the wp_site table (required for multisite as network site)
        $insert_data = array(
            'id' => $site_id,
            'domain' => $domain,
            'path' => $path
        );
        $site_table = $wpdb->prefix . 'site';
        $wpdb->insert($site_table, $insert_data);
        $network_id = $wpdb->insert_id;

        // Return the dynamically created site ID
        return $new_site_id;
    }


    private function initialize_site_database($new_db, $site_url, $admin_email, $admin_password)
    {
        $domain = $site_url . '.' . DOMAIN_CURRENT_SITE;
        // Create all default WordPress tables
        $this->create_default_tables($new_db);
        // Insert necessary options
        $new_db->insert(NEW_DB_PREFIX . 'options', [
            'option_name' => 'siteurl',
            'option_value' => 'http://' . $domain,
            'autoload' => 'yes',
        ]);
        $new_db->insert(NEW_DB_PREFIX . 'options', [
            'option_name' => 'home',
            'option_value' => 'http://' . $domain,
            'autoload' => 'yes',
        ]);


        // Create admin user
        $admin_password = wp_hash_password($admin_password); // Set a secure password
        $new_db->insert(NEW_DB_PREFIX . 'users', [
            'user_login' => $admin_email,
            'user_pass' => $admin_password,
            'user_nicename' => 'Admin',
            'user_email' => $admin_email,
            'user_registered' => current_time('mysql'),
            'display_name' => 'Admin',
        ]);

        $admin_id = $new_db->insert_id;

        $new_db->insert(NEW_DB_PREFIX . 'usermeta', [
            'user_id' => $admin_id,
            'meta_key' => 'wp_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);
        $new_db->insert(NEW_DB_PREFIX . 'usermeta', [
            'user_id' => $admin_id,
            'meta_key' => 'wp_user_level',
            'meta_value' => 10,
        ]);
    }

    private function activate_defaults($new_db)
    {
        // Activate Akismet and Hello Dolly
        $plugins = ['akismet/akismet.php', 'hello.php'];
    
        // Retrieve the current active plugins
        $current_active_plugins = get_option('active_plugins', []);
    
        // Merge the current active plugins with the new ones
        $new_active_plugins = array_merge($current_active_plugins, $plugins);
    
        // Update the active_plugins option
        $new_db->replace(NEW_DB_PREFIX . 'options', [
            'option_name' => 'active_plugins',
            'option_value' => serialize(array_unique($new_active_plugins)), // Remove duplicates
        ]);
    
        // Set default theme to Twenty Twenty-Five
        $new_db->replace(NEW_DB_PREFIX . 'options', [
            'option_name' => 'template',
            'option_value' => 'twentytwentyfive',
        ]);
    
        $new_db->replace(NEW_DB_PREFIX . 'options', [
            'option_name' => 'stylesheet',
            'option_value' => 'twentytwentyfive',
        ]);
    
        // Optionally, update the Appearance and Plugins menus in wp_options
        $new_db->replace(NEW_DB_PREFIX . 'options', [
            'option_name' => 'menu_plugins',
            'option_value' => '1',
        ]);
    
        $new_db->replace(NEW_DB_PREFIX . 'options', [
            'option_name' => 'menu_appearance',
            'option_value' => '1',
        ]);
    }
    


    private function create_default_tables($new_db)
    {
        $charset_collate = $new_db->get_charset_collate();

        $sql = [
            "CREATE TABLE IF NOT EXISTS `wp_options` (
          `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `option_name` varchar(191) NOT NULL DEFAULT '',
          `option_value` longtext,
          `autoload` varchar(20) NOT NULL DEFAULT 'yes',
          PRIMARY KEY (`option_id`),
          UNIQUE KEY `option_name` (`option_name`)
        ) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_usermeta` (
          `umeta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
          `meta_key` varchar(255) DEFAULT NULL,
          `meta_value` longtext,
          PRIMARY KEY (`umeta_id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_users` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) NOT NULL DEFAULT '',
  `user_pass` varchar(255) NOT NULL DEFAULT '',
  `user_nicename` varchar(50) NOT NULL DEFAULT '',
  `display_name` varchar(250) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_url` varchar(100) DEFAULT NULL,
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) DEFAULT NULL,
  `user_status` int(11) NOT NULL DEFAULT '0',
  `deleted` varchar(1) NOT NULL DEFAULT '0',
  `role` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `user_login_unique` (`user_login`),
  KEY `user_nicename` (`user_nicename`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_posts` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content` longtext NOT NULL,
  `post_title` text NOT NULL,
  `post_excerpt` text NOT NULL,
  `post_status` varchar(20) NOT NULL DEFAULT 'publish',
  `comment_status` varchar(20) NOT NULL DEFAULT 'open',
  `ping_status` varchar(20) NOT NULL DEFAULT 'open',
  `post_password` varchar(20) NOT NULL DEFAULT '',
  `post_name` varchar(200) NOT NULL DEFAULT '',
  `to_ping` text,
  `pinged` text,
  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext,
  `post_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
  `guid` varchar(255) NOT NULL DEFAULT '',
  `menu_order` int(11) NOT NULL DEFAULT '0',
  `post_type` varchar(20) NOT NULL DEFAULT 'post',
  `post_mime_type` varchar(100) DEFAULT NULL,
  `comment_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `post_name` (`post_name`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_postmeta` (
  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`meta_id`),
  KEY `post_id` (`post_id`),
  KEY `meta_key` (`meta_key`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_comments` (
  `comment_ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_post_ID` bigint(20) unsigned NOT NULL DEFAULT '0',
  `comment_author` tinytext NOT NULL,
  `comment_author_email` varchar(100) NOT NULL DEFAULT '',
  `comment_author_url` varchar(200) NOT NULL DEFAULT '',
  `comment_author_IP` varchar(100) NOT NULL DEFAULT '',
  `comment_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_content` text NOT NULL,
  `comment_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
  `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `comment_approved` varchar(20) NOT NULL DEFAULT '1',
  `comment_agent` varchar(255) DEFAULT NULL,
  `comment_type` varchar(20) NOT NULL DEFAULT '',
  `comment_author_meta` longtext,
  PRIMARY KEY (`comment_ID`),
  KEY `comment_post_ID` (`comment_post_ID`),
  KEY `comment_approved_date` (`comment_approved`,`comment_date`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_commentmeta` (
  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`meta_id`),
  KEY `comment_id` (`comment_id`),
  KEY `meta_key` (`meta_key`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_links` (
  `link_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `link_url` varchar(255) NOT NULL DEFAULT '',
  `link_name` varchar(255) NOT NULL DEFAULT '',
  `link_image` varchar(255) DEFAULT NULL,
  `link_target` varchar(25) NOT NULL DEFAULT '',
  `link_description` varchar(255) DEFAULT NULL,
  `link_visible` varchar(20) NOT NULL DEFAULT 'Y',
  `link_owner` bigint(20) unsigned NOT NULL DEFAULT '1',
  `link_category` bigint(20) unsigned NOT NULL DEFAULT '0',
  `link_rating` int(11) NOT NULL DEFAULT '0',
  `link_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `link_rel` varchar(255) DEFAULT NULL,
  `link_notes` mediumtext,
  `link_rss` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`link_id`),
  KEY `link_visible` (`link_visible`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_term_taxonomy` (
  `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint(20) unsigned NOT NULL,
  `taxonomy` varchar(32) NOT NULL,
  `description` longtext,
  `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
  `count` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_terms` (
  `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `term_group` bigint(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_id`),
  UNIQUE KEY `slug_group` (`slug`,`term_group`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_term_relationships` (
  `object_id` bigint(20) unsigned NOT NULL,
  `term_taxonomy_id` bigint(20) unsigned NOT NULL,
  `term_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `wp_term_meta` (
  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint(20) unsigned NOT NULL,
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`meta_id`),
  KEY `term_id` (`term_id`),
  KEY `meta_key` (`meta_key`)
) ENGINE=InnoDB $charset_collate;",

        ];

        foreach ($sql as $query) {
            if ($new_db->query($query) === false) {
                error_log("Failed to execute query: " . $query . " - Error: " . $new_db->last_error);
            }
        }
    }

  Private  function create_sitemeta_table_and_data($wpdb, $new_site_id, $new_db_prefix) {
    
        // SQL to create wp_sitemeta table
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS `{$new_db_prefix}sitemeta` (
              `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `site_id` bigint(20) unsigned NOT NULL DEFAULT '0',
              `meta_key` varchar(255) DEFAULT NULL,
              `meta_value` longtext,
              PRIMARY KEY (`meta_id`),
              KEY `meta_key` (`meta_key`(191)),
              KEY `site_id` (`site_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    
        // Execute table creation
        $wpdb->query($create_table_sql);
    
        // Insert initial data into sitemeta
        $wpdb->insert("{$new_db_prefix}sitemeta", [
            'site_id'    => $new_site_id,
            'meta_key'   => 'add_new_users',
            'meta_value' => '1',
        ]);
    
        $wpdb->insert("{$new_db_prefix}sitemeta", [
            'site_id'    => $new_site_id,
            'meta_key'   => '_site_transient_wp_theme_files_patterns-e1fbc22a4b773adcaf177951611c3644',
            'meta_value' => '',
        ]);
    
        $wpdb->insert("{$new_db_prefix}sitemeta", [
            'site_id'    => $new_site_id,
            'meta_key'   => '_site_transient_timeout_wp_theme_files_patterns-e1fbc22a4b773adcaf177951611c3644',
            'meta_value' => '',
        ]);
    }
    

}
