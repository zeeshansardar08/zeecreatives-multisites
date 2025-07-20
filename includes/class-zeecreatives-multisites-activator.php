<?php
class ZeeCreatives_MultiSites_Activator {
    public static function activate() {
        // Check if WordPress is running in Multisite mode
        if ( ! is_multisite() ) {
            // Display notice that WordPress needs to be converted to Multisite
            add_action( 'admin_notices', array( 'ZeeCreatives_MultiSites_Activator', 'multisite_notice' ) );
        }

        // Create the database table for storing site credentials
        self::create_credentials_table();
    }

    public static function multisite_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e( 'ZeeCreatives MultiSites requires WordPress Multisite to be enabled. Please convert your WordPress installation to Multisite.', 'zeecreatives-multisites' ); ?></p>
        </div>
        <?php
    }

    private static function create_credentials_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zeecreatives_site_db_credentials';
        
        // Check if the table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                site_id bigint(20) NOT NULL,
                db_name varchar(255) NOT NULL,
                db_user varchar(255) NOT NULL,
                db_password varchar(255) NOT NULL,
                db_host varchar(255) NOT NULL,
                PRIMARY KEY  (site_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}