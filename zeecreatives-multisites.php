<?php
/**
 * Plugin Name: ZeeCreatives MultiSites
 * Plugin URI: https://zeecreatives.com/multisites
 * Description: Convert WordPress into a SaaS platform with automated multi-site management.
 * Version: 1.0.0
 * Author: ZeeCreatives
 * Author URI: https://zeecreatives.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: zeecreatives-multisites
 * Domain Path: /languages
 *
 * @package ZeeCreatives_MultiSites
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'ZEECREATIVES_MULTISITES_VERSION', '1.0.0' );
define( 'ZEECREATIVES_MULTISITES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZEECREATIVES_MULTISITES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );



/**
 * The code that runs during plugin activation.
 */
function activate_zeecreatives_multisites() {
    require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites-activator.php';
    ZeeCreatives_MultiSites_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_zeecreatives_multisites() {
    require_once ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites-deactivator.php';
    ZeeCreatives_MultiSites_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_zeecreatives_multisites' );
register_deactivation_hook( __FILE__, 'deactivate_zeecreatives_multisites' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require ZEECREATIVES_MULTISITES_PLUGIN_DIR . 'includes/class-zeecreatives-multisites.php';

/**
 * Begins execution of the plugin.
 */
function run_zeecreatives_multisites() {
    $plugin = new ZeeCreatives_MultiSites();
    $plugin->run();
}
run_zeecreatives_multisites();