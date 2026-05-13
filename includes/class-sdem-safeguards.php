<?php
/**
 * MemberPress safeguards: email interception, reminders, gateways, Developer Tools.
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers runtime filters and actions for safe mode.
 */
class SDEM_Safeguards {

    /**
     * @var SDEM_Config
     */
    private $config;

    /**
     * @var SDEM_Environment
     */
    private $env;

    /**
     * @var bool|null
     */
    private $debug_log_active = null;

    /**
     * @var string[]
     */
    private static $reminder_trigger_events = array(
        'sub-expires',
        'sub-renews',
        'cc-expires',
        'member-signup',
        'signup-abandoned',
        'sub-trial-ends',
    );

    /**
     * @param SDEM_Config       $config Config.
     * @param SDEM_Environment $env    Environment.
     */
    public function __construct(SDEM_Config $config, SDEM_Environment $env) {
        $this->config = $config;
        $this->env    = $env;
    }

    /**
     * Register hooks (call after plugins may define MEPR_OPTIONS_SLUG).
     */
    public function init() {
        add_action('plugins_loaded', array($this, 'maybe_deactivate_developer_tools'), 20);

        if (!$this->env->is_staging()) {
            add_action('admin_init', array($this, 'maybe_reactivate_developer_tools_after_module_off'), 30);
            return;
        }

        if (!$this->config->is_master_enabled()) {
            add_action('admin_init', array($this, 'maybe_reactivate_developer_tools_after_module_off'), 30);
            return;
        }

        if ($this->config->module_emails()) {
            add_filter('mepr_wp_mail_recipients', array($this, 'disable_mepr_emails'), 10, 4);
            add_filter('pre_wp_mail', array($this, 'disable_memberpress_wp_mail'), 10, 2);
        }

        if ($this->config->module_reminders()) {
            add_filter('pre_option_mepr_disable_reminder_crons', array($this, 'force_reminder_crons_disabled'), 10, 3);
            foreach (self::$reminder_trigger_events as $event) {
                add_filter("mepr_{$event}_reminder_disable", array($this, 'force_reminder_email_disabled'), 999, 5);
            }
        }

        if ($this->config->module_gateways() && defined('MEPR_OPTIONS_SLUG')) {
            add_filter('option_' . MEPR_OPTIONS_SLUG, array($this, 'filter_mepr_options_gateways_test_mode'), 999);
        }
    }

    /**
     * @param bool|string $pre     Short-circuit value.
     * @param string      $option  Option name.
     * @param mixed       $default Default.
     *
     * @return string
     */
    public function force_reminder_crons_disabled($pre, $option, $default) {
        unset($pre, $option, $default);
        return '1';
    }

    /**
     * @param bool $disable_email Prior value.
     *
     * @return bool
     */
    public function force_reminder_email_disabled($disable_email) {
        unset($disable_email);
        return true;
    }

    /**
     * @param mixed $options Mepr options array.
     *
     * @return mixed
     */
    public function filter_mepr_options_gateways_test_mode($options) {
        if (!is_array($options)) {
            return $options;
        }

        if (!empty($options['integrations']) && is_array($options['integrations'])) {
            foreach ($options['integrations'] as $id => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $options['integrations'][$id] = $this->apply_gateway_staging_flags($row);
            }
        }

        if (!empty($options['legacy_integrations']) && is_array($options['legacy_integrations'])) {
            foreach ($options['legacy_integrations'] as $id => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $options['legacy_integrations'][$id] = $this->apply_gateway_staging_flags($row);
            }
        }

        return $options;
    }

    /**
     * @param array $integration Gateway row.
     *
     * @return array
     */
    private function apply_gateway_staging_flags(array $integration) {
        if (empty($integration['gateway'])) {
            return $integration;
        }

        $g = $integration['gateway'];

        switch ($g) {
            case 'MeprStripeGateway':
            case 'MeprAuthorizeGateway':
            case 'MeprAuthorizeProfileGateway':
                $integration['test_mode'] = true;
                break;
            case 'MeprPayPalGateway':
            case 'MeprPayPalStandardGateway':
            case 'MeprPayPalProGateway':
            case 'MeprPayPalVaultingGateway':
                $integration['sandbox'] = true;
                break;
            case 'MeprSquarePaymentsGateway':
                $integration['sandbox'] = true;
                break;
            default:
                break;
        }

        return $integration;
    }

    /**
     * @param array  $recipients Recipients.
     * @param string $subject    Subject.
     * @param string $message    Body.
     * @param string $headers    Headers.
     *
     * @return array
     */
    public function disable_mepr_emails($recipients, $subject, $message, $headers) {
        if (!$this->config->is_email_guard_enabled()) {
            return $recipients;
        }

        $this->log_suppressed(
            'mepr_wp_mail_recipients',
            is_string($subject) ? $subject : ''
        );

        return array();
    }

    /**
     * @param null|bool|WP_Error $return Prior short-circuit.
     * @param array|null         $atts   wp_mail args.
     *
     * @return null|bool|WP_Error
     */
    public function disable_memberpress_wp_mail($return, $atts = null) {
        if ($return !== null) {
            return $return;
        }

        if (!$this->config->is_email_guard_enabled()) {
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
     * @param string $file Absolute path.
     *
     * @return string core|addon or empty.
     */
    private function get_memberpress_plugin_origin($file) {
        $file = wp_normalize_path($file);
        $plugins_dir = wp_normalize_path(WP_PLUGIN_DIR);
        $quoted_plugins = preg_quote($plugins_dir, '#');

        if (preg_match('#^' . $quoted_plugins . '/memberpress/#', $file)) {
            return 'core';
        }

        if (preg_match('#^' . $quoted_plugins . '/memberpress-[^/]+/#', $file)) {
            return 'addon';
        }

        return '';
    }

    /**
     * @param array $trace Backtrace frame.
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
     * @param string $channel Filter channel.
     * @param string $subject Subject.
     * @param string $origin  core|addon.
     * @param string $file    File path.
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

    /**
     * Deactivate Developer Tools on non-production when that module is on.
     */
    public function maybe_deactivate_developer_tools() {
        if (!$this->env->is_staging() || !$this->config->is_master_enabled() || !$this->config->module_developer_tools()) {
            return;
        }

        if (!function_exists('is_plugin_active') || !function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $rel = 'memberpress-developer-tools/main.php';
        if (!is_plugin_active($rel)) {
            return;
        }

        deactivate_plugins($rel, true);
        update_option(SDEM_Config::DT_FLAG_OPTION, '1', false);
    }

    /**
     * Reactivate Developer Tools when safe mode no longer applies.
     */
    public function maybe_reactivate_developer_tools_after_module_off() {
        if (get_option(SDEM_Config::DT_FLAG_OPTION, '') !== '1') {
            return;
        }

        if ($this->env->is_staging() && $this->config->is_master_enabled() && $this->config->module_developer_tools()) {
            return;
        }

        if (!function_exists('is_plugin_active') || !function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $rel = 'memberpress-developer-tools/main.php';
        if (!is_plugin_active($rel)) {
            activate_plugin($rel);
        }

        delete_option(SDEM_Config::DT_FLAG_OPTION);
    }
}
