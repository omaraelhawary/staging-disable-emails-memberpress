<?php
/**
 * One-time admin email when non-production is detected (per site URL hash).
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends a single notification email after staging detection.
 */
class SDEM_Staging_Notifier {

    /**
     * @var SDEM_Config
     */
    private $config;

    /**
     * @var SDEM_Environment
     */
    private $env;

    /**
     * @param SDEM_Config       $config Config.
     * @param SDEM_Environment $env    Environment.
     */
    public function __construct(SDEM_Config $config, SDEM_Environment $env) {
        $this->config = $config;
        $this->env    = $env;
    }

    /**
     * Register hooks.
     */
    public function init() {
        add_action('admin_init', array($this, 'maybe_send_staging_detection_email'), 5);
    }

    /**
     * Send once per home URL when non-production is detected and the option is enabled.
     */
    public function maybe_send_staging_detection_email() {
        if ($this->config->is_force_nonproduction()) {
            return;
        }

        if (!$this->env->is_staging()) {
            return;
        }

        if (!$this->config->should_notify_staging_detection()) {
            return;
        }

        if (!apply_filters('staging_disable_emails_memberpress_send_staging_detection_email', true)) {
            return;
        }

        if (!current_user_can($this->env->get_menu_capability())) {
            return;
        }

        $site_key = md5(home_url());
        $sent_for = get_option(SDEM_Config::STAGING_NOTICE_SENT_KEY, '');
        if ($sent_for === $site_key) {
            return;
        }

        $recipients = apply_filters(
            'staging_disable_emails_memberpress_staging_detection_email_recipients',
            array(get_option('admin_email'))
        );
        if (!is_array($recipients) || empty($recipients)) {
            return;
        }

        $recipients = array_filter(array_map('sanitize_email', $recipients));
        if (empty($recipients)) {
            return;
        }

        $settings_url = admin_url('admin.php?page=staging-disable-emails-memberpress');
        $subject      = sprintf(
            /* translators: %s: site title */
            __('[%s] Non-production environment detected', 'staging-disable-emails-memberpress'),
            wp_specialchars_decode(get_option('blogname'), ENT_QUOTES)
        );

        $lines = array(
            sprintf(
                /* translators: %s: home URL */
                __('WordPress reports this site as non-production (staging, local, or similar): %s', 'staging-disable-emails-memberpress'),
                home_url()
            ),
            '',
            sprintf(
                /* translators: %s: URL to plugin settings */
                __('Review MemberPress staging safe mode settings: %s', 'staging-disable-emails-memberpress'),
                $settings_url
            ),
            '',
            __('This message is sent at most once per site address when an administrator visits the dashboard.', 'staging-disable-emails-memberpress'),
        );

        $body = implode("\n", $lines);

        $sent = wp_mail($recipients, $subject, $body);
        if ($sent) {
            update_option(SDEM_Config::STAGING_NOTICE_SENT_KEY, $site_key, false);
        }
    }
}
