<?php
/**
 * Staging / non-production detection.
 *
 * @package StagingSafeModeForMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Environment helpers.
 */
class SDEM_Environment {

    /**
     * @var SDEM_Config
     */
    private $config;

    /**
     * @param SDEM_Config $config Plugin config (for optional force-non-production override).
     */
    public function __construct(SDEM_Config $config) {
        $this->config = $config;
    }

    /**
     * True when safeguards should treat this install as non-production.
     *
     * @return bool
     */
    public function is_staging() {
        if ($this->config->is_force_nonproduction()) {
            return true;
        }

        return $this->is_nonproduction_detected();
    }

    /**
     * Staging / local / dev from WordPress constants, site URL heuristics, or filters only
     * (does not include the plugin’s “force non-production” setting).
     *
     * @return bool
     */
    public function is_nonproduction_detected() {
        return !empty($this->get_nonproduction_detection_reasons());
    }

    /**
     * Human-readable signals that automatic detection treats this install as non-production.
     *
     * @return string[]
     */
    public function get_nonproduction_detection_reasons() {
        $reasons = array();

        if (defined('WP_ENVIRONMENT_TYPE')) {
            $env = strtolower((string) WP_ENVIRONMENT_TYPE);
            if (in_array($env, array('staging', 'local', 'development'), true)) {
                $reasons[] = sprintf(
                    /* translators: %s: WP_ENVIRONMENT_TYPE value */
                    __('WordPress environment type is "%s" (WP_ENVIRONMENT_TYPE)', 'staging-safe-mode-for-memberpress'),
                    $env
                );
            }
        }

        if (defined('WP_ENV')) {
            $wp_env = strtolower((string) WP_ENV);
            if (in_array($wp_env, array('staging', 'stage', 'local', 'dev', 'development'), true)) {
                $reasons[] = sprintf(
                    /* translators: %s: WP_ENV value */
                    __('WP_ENV is "%s"', 'staging-safe-mode-for-memberpress'),
                    $wp_env
                );
            }
        }

        $site_url           = home_url();
        $staging_indicators = array('staging', 'stage', '.test', '.local', 'localhost');
        foreach ($staging_indicators as $indicator) {
            if (stripos($site_url, $indicator) !== false) {
                $reasons[] = sprintf(
                    /* translators: %s: substring matched in the site URL */
                    __('Site URL contains "%s"', 'staging-safe-mode-for-memberpress'),
                    $indicator
                );
            }
        }

        // MemberPress / ecosystem compatibility filter name (not prefixed by this plugin).
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $is_staging = apply_filters('mepr_disable_emails_is_staging', false);
        if ((bool) apply_filters('staging_disable_emails_memberpress_is_staging', $is_staging)) {
            $reasons[] = __('A custom filter marked this install as non-production', 'staging-safe-mode-for-memberpress');
        }

        return $reasons;
    }

    /**
     * Capability for MemberPress admin menu and this plugin’s screens.
     *
     * @return string
     */
    public function get_menu_capability() {
        if (class_exists('MeprUtils', false) && is_callable(array('MeprUtils', 'get_mepr_admin_capability'))) {
            return MeprUtils::get_mepr_admin_capability();
        }

        return 'manage_options';
    }
}
