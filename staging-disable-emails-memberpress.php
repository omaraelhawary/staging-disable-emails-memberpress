<?php
/**
 * Plugin Name: Staging Disable Emails for MemberPress
 * Plugin URI: https://github.com/omaraelhawary/
 * Description: Disables all MemberPress emails (including Courses and Corporate) on staging environments while allowing other emails (like 2FA) to work normally.
 * Version: 1.0.0
 * Author: Omar ElHawary
 * Author URI: https://github.com/omaraelhawary/
 * Text Domain: staging-disable-emails-memberpress
 * Domain Path: /i18n
 *
 * @package StagingDisableEmailsMemberPress
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
class StagingDisableEmailsMemberPress {

    /**
     * Option name for storing the enabled/disabled setting.
     */
    const OPTION_NAME = 'staging_disable_emails_memberpress_enabled';

    /**
     * Legacy option name (for migration from previous plugin slug).
     */
    const LEGACY_OPTION_NAME = 'mepr_disable_emails_staging_enabled';
    
    /**
     * Initialize the plugin.
     */
    public function __construct() {
        // Add admin menu and settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Only run if enabled and on staging environments
        if ($this->is_enabled() && $this->is_staging()) {
            // Hook into MemberPress email system to prevent emails from being sent
            add_filter('mepr_wp_mail_recipients', array($this, 'disable_mepr_emails'), 10, 4);
            
            // Hook into WordPress wp_mail to catch MemberPress addon emails
            // Many addons (Courses, Gifting, Downloads, etc.) use their own email systems
            // that bypass MeprUtils::wp_mail(), so we intercept them here
            // Use pre_wp_mail filter (WordPress 5.7+) to short-circuit email sending
            add_filter('pre_wp_mail', array($this, 'disable_courses_emails'), 10, 1);
        }
    }
    
    /**
     * Check if the feature is enabled.
     * Migrates from legacy option if present.
     *
     * @return bool True if enabled, false otherwise.
     */
    private function is_enabled() {
        $value = get_option(self::OPTION_NAME, null);
        if ($value === null) {
            $legacy = get_option(self::LEGACY_OPTION_NAME, null);
            if ($legacy !== null) {
                update_option(self::OPTION_NAME, (bool) $legacy);
                $value = (bool) $legacy;
            } else {
                $value = false;
            }
        }
        return (bool) $value;
    }
    
    /**
     * Add admin menu item.
     */
    public function add_admin_menu() {
        add_options_page(
            __('Staging Disable Emails for MemberPress', 'staging-disable-emails-memberpress'),
            __('Staging Disable Emails', 'staging-disable-emails-memberpress'),
            'manage_options',
            'staging-disable-emails-memberpress',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting(
            'staging_disable_emails_memberpress_settings',
            self::OPTION_NAME,
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );
    }
    
    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['submit']) && check_admin_referer('staging_disable_emails_memberpress_settings')) {
            $enabled = isset($_POST[self::OPTION_NAME]) ? true : false;
            update_option(self::OPTION_NAME, $enabled);
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'staging-disable-emails-memberpress') . '</p></div>';
        }

        $enabled = $this->is_enabled();
        $is_staging = $this->is_staging();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Staging Disable Emails for MemberPress', 'staging-disable-emails-memberpress'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('staging_disable_emails_memberpress_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Disable MemberPress Emails', 'staging-disable-emails-memberpress'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>" value="1" <?php checked($enabled, true); ?>>
                                <?php echo esc_html__('Enable to disable all MemberPress emails on staging environments', 'staging-disable-emails-memberpress'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('When enabled, all MemberPress emails (including core, Courses, Corporate, Gifting, Downloads, and all other addons) will be blocked on staging environments. Other WordPress emails (like 2FA, password resets, etc.) will continue to work normally.', 'staging-disable-emails-memberpress'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Environment Status', 'staging-disable-emails-memberpress'); ?>
                        </th>
                        <td>
                            <?php if ($is_staging): ?>
                                <span style="color: #d63638; font-weight: bold;"><?php echo esc_html__('Staging Environment Detected', 'staging-disable-emails-memberpress'); ?></span>
                                <p class="description">
                                    <?php echo esc_html__('This site has been detected as a staging environment. MemberPress emails will be disabled when the option above is enabled.', 'staging-disable-emails-memberpress'); ?>
                                </p>
                            <?php else: ?>
                                <span style="color: #00a32a; font-weight: bold;"><?php echo esc_html__('Production Environment', 'staging-disable-emails-memberpress'); ?></span>
                                <p class="description">
                                    <?php echo esc_html__('This site does not appear to be a staging environment. The plugin will not disable emails here.', 'staging-disable-emails-memberpress'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Check if we're on a staging environment.
     * 
     * Supports multiple detection methods:
     * 1. WP_ENVIRONMENT_TYPE constant (WordPress 5.5+)
     * 2. WP_ENV constant
     * 3. URL contains 'staging' or 'stage'
     * 4. Custom filter for additional detection methods
     * 
     * @return bool True if staging, false otherwise.
     */
    private function is_staging() {
        // Check WordPress environment type (WordPress 5.5+)
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging') {
            return true;
        }
        
        // Check WP_ENV constant
        if (defined('WP_ENV') && in_array(strtolower(WP_ENV), array('staging', 'stage', 'dev', 'development'))) {
            return true;
        }
        
        // Check URL for staging indicators
        $site_url = home_url();
        $staging_indicators = array('staging', 'stage', '.test', '.local', 'localhost');
        foreach ($staging_indicators as $indicator) {
            if (stripos($site_url, $indicator) !== false) {
                return true;
            }
        }
        
        // Allow custom detection via filter (legacy filter supported for backward compatibility)
        $is_staging = apply_filters('mepr_disable_emails_is_staging', false);
        return apply_filters('staging_disable_emails_memberpress_is_staging', $is_staging);
    }
    
    /**
     * Disable MemberPress emails by returning empty recipients array.
     * 
     * This filter is called for all MemberPress emails, but not for other
     * WordPress emails that use wp_mail() directly (like 2FA plugins).
     * 
     * @param array  $recipients Array of email recipients.
     * @param string $subject    Email subject.
     * @param string $message    Email message.
     * @param string $headers    Email headers.
     * 
     * @return array Empty array to prevent sending, or original recipients if not enabled.
     */
    public function disable_mepr_emails($recipients, $subject, $message, $headers) {
        // Only disable if the feature is enabled
        if (!$this->is_enabled()) {
            return $recipients;
        }
        
        // Return empty array to prevent MemberPress emails from being sent
        // This only affects emails sent through MemberPress's email system
        return array();
    }
    
    /**
     * Disable all MemberPress addon emails by checking if email originates from any MemberPress addon.
     * 
     * Many MemberPress addons (Courses, Gifting, Downloads, etc.) use their own email systems
     * that call wp_mail() directly, bypassing the core MeprUtils::wp_mail() filter.
     * This function detects emails from any memberpress-* plugin and blocks them.
     * 
     * @param null|bool|WP_Error $return Short-circuit return value. If null, continue sending.
     * 
     * @return null|bool|WP_Error Return true to short-circuit, null to continue.
     */
    public function disable_courses_emails($return) {
        // If already short-circuited, don't interfere
        if ($return !== null) {
            return $return;
        }
        
        // Only disable if the feature is enabled
        if (!$this->is_enabled()) {
            return null;
        }
        
        // Check the call stack to see if this email is coming from any MemberPress addon
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        foreach ($backtrace as $trace) {
            // Check if the call is from any MemberPress addon (memberpress-* plugins)
            if (isset($trace['file'])) {
                $file = $trace['file'];
                
                // Check if file is from a memberpress-* plugin directory
                // This catches: memberpress-courses, memberpress-gifting, memberpress-downloads, etc.
                if (preg_match('/\/memberpress-[^\/]+\//', $file)) {
                    // Check if it's from an email-related class or function
                    if (isset($trace['class']) && 
                        (stripos($trace['class'], 'Email') !== false || 
                         stripos($trace['class'], 'Utils') !== false)) {
                        // This is a MemberPress addon email - block it by returning true
                        return true;
                    }
                    
                    // Also check function names for email-related functions
                    if (isset($trace['function']) && 
                        (stripos($trace['function'], 'wp_mail') !== false ||
                         stripos($trace['function'], 'send') !== false ||
                         stripos($trace['function'], 'email') !== false)) {
                        // This is likely a MemberPress addon email - block it
                        return true;
                    }
                }
            }
        }
        
        // Not a MemberPress addon email, allow it through
        return null;
    }
}

// Initialize the plugin
new StagingDisableEmailsMemberPress();
