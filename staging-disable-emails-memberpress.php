<?php
/**
 * Plugin Name: Staging Disable Emails for MemberPress
 * Plugin URI: https://github.com/omaraelhawary/
 * Description: Staging / local safeguards for MemberPress: emails, reminders, gateway test & sandbox flags at runtime, optional Developer Tools deactivation, optional force non-production override, and one-time staging detection email.
 * Version: 1.3.3
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Omar ElHawary
 * Author URI: https://github.com/omaraelhawary/
 * Text Domain: staging-disable-emails-memberpress
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SDEM_VERSION', '1.3.3');
define('SDEM_PLUGIN_FILE', __FILE__);
define('SDEM_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-config.php';
require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-environment.php';
require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-staging-notifier.php';
require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-safeguards.php';
require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-admin-bar.php';
require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-admin.php';
require_once SDEM_PLUGIN_DIR . 'includes/class-sdem-plugin.php';

SDEM_Plugin::instance();
