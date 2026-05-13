<?php
/**
 * Staging / non-production detection.
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Environment helpers.
 */
class SDEM_Environment {

    /**
     * @return bool
     */
    public function is_staging() {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            $env = strtolower((string) WP_ENVIRONMENT_TYPE);
            if (in_array($env, array('staging', 'local', 'development'), true)) {
                return true;
            }
        }

        if (defined('WP_ENV') && in_array(strtolower((string) WP_ENV), array('staging', 'stage', 'local', 'dev', 'development'), true)) {
            return true;
        }

        $site_url = home_url();
        $staging_indicators = array('staging', 'stage', '.test', '.local', 'localhost');
        foreach ($staging_indicators as $indicator) {
            if (stripos($site_url, $indicator) !== false) {
                return true;
            }
        }

        $is_staging = apply_filters('mepr_disable_emails_is_staging', false);
        return (bool) apply_filters('staging_disable_emails_memberpress_is_staging', $is_staging);
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
