=== ZeeCreatives MultiSites ===
Contributors: zeecreatives
Donate link: https://zeecreatives.com/donate
Tags: multisite, SaaS, subdomain, database management, uploads directory
Requires at least: 5.8
Tested up to: 6.7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert WordPress into a SaaS platform with automated multi-site management.

== Description ==

**ZeeCreatives MultiSites** transforms WordPress into a powerful SaaS platform by automating the creation and management of subdomain-based multisites. The plugin provides:

- Separate databases for each subdomain to enhance security and performance.
- Individual upload directories for seamless file management.
- REST API for programmatic site creation and management.
- WordPress Multisite compatibility with support for default themes and plugins.

Whether you're building a SaaS solution, managing multiple clients, or hosting subdomain-based websites, this plugin simplifies the process and offers complete automation.

= Features =
- Dynamic database creation for each subdomain.
- Automated upload directory setup.
- REST API endpoints for creating and managing sites.
- Supports custom themes and plugin activation for each site.
- Fully compatible with WordPress Multisite.

== Installation ==

1. Download the plugin and extract the contents to your `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Make sure your server supports WordPress Multisite and subdomain setups.
4. Configure the plugin settings to match your hosting environment.

== Frequently Asked Questions ==

= Does this plugin work with subdirectories instead of subdomains? =
No, this plugin is designed specifically for subdomain-based multisite setups.

= Can I use this plugin for existing multisites? =
Yes, but the plugin is optimized for new multisite installations where each subdomain gets a separate database.

= What are the server requirements for this plugin? =
- PHP 7.4 or higher
- MySQL 5.7 or MariaDB 10.3 or higher
- WordPress 5.8 or higher

== Screenshots ==

1. **REST API Demo** - Programmatically create new subdomain-based sites.
2. **Database Setup** - Automatic separate database creation for each subdomain.
3. **Custom Uploads Directory** - Unique file management for each subdomain.

== Changelog ==

= 1.0.0 =
* Initial release.
* Automated subdomain creation with separate databases.
* REST API integration for creating and managing sites.
* Custom upload directories per site.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.

== License ==

This plugin is licensed under the GPLv2 (or later). See https://www.gnu.org/licenses/gpl-2.0.html for details.
