<?php
/**
 * Plugin Name: Staging Disable Emails for MemberPress
 * Plugin URI: https://github.com/omaraelhawary/
 * Description: Disables all MemberPress emails (including Courses and Corporate) on staging environments while allowing other emails (like 2FA) to work normally.
 * Version: 1.1.0
 * Author: Omar ElHawary
 * Author URI: https://github.com/omaraelhawary/
 * Text Domain: staging-disable-emails-memberpress
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
     * Whether debug logging for suppressed emails is active this request.
     *
     * @var bool|null
     */
    private $debug_log_active = null;

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
            add_filter('pre_wp_mail', array($this, 'disable_memberpress_wp_mail'), 10, 2);
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
        $staging_doc = 'https://memberpress.com/docs/how-to-create-a-staging-site-with-memberpress/';
        $stop_all_doc = 'https://memberpress.com/docs/how-to-stop-emails-from-sending-from-your-staging-site/';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Staging Disable Emails for MemberPress', 'staging-disable-emails-memberpress'); ?></h1>

            <div class="card" style="max-width: 52rem; margin-bottom: 1.25rem;">
                <h2 class="title" style="margin-top: 0;"><?php echo esc_html__('MemberPress staging checklist', 'staging-disable-emails-memberpress'); ?></h2>
                <p class="description" style="margin-top: 0;">
                    <?php echo esc_html__('This plugin blocks MemberPress-related email only. Complete the official staging steps so payments, webhooks, and integrations stay safe.', 'staging-disable-emails-memberpress'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 1.25em;">
                    <li>
                        <?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: %s: MemberPress staging documentation URL */
                                __('Follow <a href="%s">How to create a staging site with MemberPress</a> for gateways (Stripe, PayPal, Square), clearing connection data, and test mode.', 'staging-disable-emails-memberpress'),
                                esc_url($staging_doc)
                            )
                        );
                        ?>
                    </li>
                    <li><?php echo esc_html__('On staging, disable or review MemberPress Reminders and turn off marketing add-ons (MailChimp, ActiveCampaign, Drip, etc.) so list/API actions do not run against production lists.', 'staging-disable-emails-memberpress'); ?></li>
                    <li><?php echo esc_html__('Disable the MemberPress Developer Tools add-on on staging if it can trigger webhooks or automations you do not want against live services.', 'staging-disable-emails-memberpress'); ?></li>
                    <li>
                        <?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: %s: URL to MemberPress article about stopping all staging emails */
                                __('To block every outgoing email from WordPress (not just MemberPress), see <a href="%s">stop emails from your staging site</a>.', 'staging-disable-emails-memberpress'),
                                esc_url($stop_all_doc)
                            )
                        );
                        ?>
                    </li>
                </ul>
            </div>

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
                                <span style="color: #d63638; font-weight: bold;"><?php echo esc_html__('Non-production environment detected', 'staging-disable-emails-memberpress'); ?></span>
                                <p class="description">
                                    <?php echo esc_html__('Staging, local, or development is detected (URL, WP_ENVIRONMENT_TYPE, WP_ENV, or custom filter). MemberPress emails will be disabled when the option above is enabled.', 'staging-disable-emails-memberpress'); ?>
                                </p>
                            <?php else: ?>
                                <span style="color: #00a32a; font-weight: bold;"><?php echo esc_html__('Production environment', 'staging-disable-emails-memberpress'); ?></span>
                                <p class="description">
                                    <?php echo esc_html__('This site is treated as production. The plugin will not disable MemberPress emails here.', 'staging-disable-emails-memberpress'); ?>
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
     * 1. WP_ENVIRONMENT_TYPE constant (WordPress 5.5+): staging, local
     * 2. WP_ENV constant (staging, local, dev, etc.)
     * 3. URL contains common dev/staging host markers
     * 4. Custom filter for additional detection methods
     * 
     * @return bool True if staging, false otherwise.
     */
    private function is_staging() {
        // Check WordPress environment type (WordPress 5.5+)
        if (defined('WP_ENVIRONMENT_TYPE')) {
            $env = strtolower((string) WP_ENVIRONMENT_TYPE);
            if (in_array($env, array('staging', 'local', 'development'), true)) {
                return true;
            }
        }

        // Check WP_ENV constant
        if (defined('WP_ENV') && in_array(strtolower((string) WP_ENV), array('staging', 'stage', 'local', 'dev', 'development'), true)) {
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

        $this->log_suppressed(
            'mepr_wp_mail_recipients',
            is_string($subject) ? $subject : ''
        );

        // Return empty array to prevent MemberPress emails from being sent
        // This only affects emails sent through MemberPress's email system
        return array();
    }

    /**
     * Short-circuit wp_mail when the call stack shows MemberPress core or add-ons.
     *
     * @param null|bool|WP_Error $return Prior short-circuit value.
     * @param array|null         $atts  wp_mail arguments (WordPress 5.7+).
     *
     * @return null|bool|WP_Error
     */
    public function disable_memberpress_wp_mail($return, $atts = null) {
        if ($return !== null) {
            return $return;
        }

        if (!$this->is_enabled()) {
            return null;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);

        foreach ($backtrace as $trace) {
            if (empty($trace['file'])) {
                continue;
            }

            $origin = $this->get_memberpress_plugin_origin($trace['file']);
            if ($origin === '') {
                continue;
            }

            if ($this->trace_frame_suggests_email_send($trace)) {
                $subject = '';
                if (is_array($atts) && isset($atts['subject']) && is_string($atts['subject'])) {
                    $subject = $atts['subject'];
                }
                $this->log_suppressed('pre_wp_mail', $subject, $origin, $trace['file']);
                return true;
            }
        }

        return null;
    }

    /**
     * Resolve whether a file path belongs to core MemberPress or a memberpress-* add-on.
     *
     * @param string $file Absolute file path from backtrace.
     *
     * @return string 'core', 'addon', or empty string.
     */
    private function get_memberpress_plugin_origin($file) {
        $file = wp_normalize_path($file);
        $plugins_dir = wp_normalize_path(WP_PLUGIN_DIR);
        $quoted_plugins = preg_quote($plugins_dir, '#');

        // Core plugin directory: wp-content/plugins/memberpress/ (not memberpress-courses, etc.)
        if (preg_match('#^' . $quoted_plugins . '/memberpress/#', $file)) {
            return 'core';
        }

        // Add-ons: wp-content/plugins/memberpress-{name}/
        if (preg_match('#^' . $quoted_plugins . '/memberpress-[^/]+/#', $file)) {
            return 'addon';
        }

        return '';
    }

    /**
     * Heuristic: stack frame is likely part of sending mail.
     *
     * @param array $trace Single frame from debug_backtrace().
     *
     * @return bool
     */
    private function trace_frame_suggests_email_send(array $trace) {
        if (!empty($trace['class'])) {
            $class = (string) $trace['class'];
            if (stripos($class, 'Email') !== false || stripos($class, 'Utils') !== false) {
                return true;
            }
        }
        if (!empty($trace['function'])) {
            $fn = (string) $trace['function'];
            if (stripos($fn, 'wp_mail') !== false
                || stripos($fn, 'send') !== false
                || stripos($fn, 'email') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether to write suppressed-email lines to the PHP error log.
     *
     * Enable with: add_filter('staging_disable_emails_memberpress_log_suppressed', '__return_true');
     * Or define STAGING_DISABLE_MEPR_EMAILS_DEBUG as true in wp-config.php.
     *
     * @return bool
     */
    private function should_log_suppressed() {
        if ($this->debug_log_active !== null) {
            return $this->debug_log_active;
        }

        $active = (defined('STAGING_DISABLE_MEPR_EMAILS_DEBUG') && STAGING_DISABLE_MEPR_EMAILS_DEBUG);
        if (!$active) {
            $active = (bool) apply_filters('staging_disable_emails_memberpress_log_suppressed', false);
        }

        $this->debug_log_active = $active;
        return $this->debug_log_active;
    }

    /**
     * Log a blocked send when debug logging is enabled.
     *
     * @param string $channel Filter channel identifier.
     * @param string $subject Email subject if known.
     * @param string $origin  core|addon.
     * @param string $file    Source file from backtrace (optional).
     */
    private function log_suppressed($channel, $subject, $origin = '', $file = '') {
        if (!$this->should_log_suppressed()) {
            return;
        }

        $line = sprintf(
            '[staging-disable-emails-memberpress] Suppressed (%s)%s subject=%s',
            $channel,
            $origin !== '' ? ' origin=' . $origin : '',
            $subject !== '' ? $subject : '(empty)'
        );
        if ($file !== '') {
            $line .= ' file=' . $file;
        }

        error_log($line);
    }
}

// Initialize the plugin
new StagingDisableEmailsMemberPress();
