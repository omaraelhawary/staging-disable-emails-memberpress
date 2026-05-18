<?php
/**
 * Options, defaults, migration, and module flags.
 *
 * @package StagingSafeModeForMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration storage for staging safe mode.
 */
class SDEM_Config {

    const OPTION_NAME = 'staging_disable_emails_memberpress_enabled';

    const CONFIG_OPTION = 'staging_disable_emails_memberpress_config';

    const LEGACY_OPTION_NAME = 'mepr_disable_emails_staging_enabled';

    const DT_FLAG_OPTION = 'staging_mepr_dt_deactivated_by_sdem';

    /** @deprecated 1.3.5 Migrated into STAGING_NOTICES_SENT_KEY. */
    const STAGING_NOTICE_SENT_KEY = 'sdem_staging_detection_notice_sent_for';

    const STAGING_NOTICES_SENT_KEY = 'sdem_staging_notices_sent';

    /**
     * @var array|null
     */
    private $cache = null;

    /**
     * @return array{enabled:bool,emails:bool,reminders:bool,gateways:bool,developer_tools:bool,notify_staging_detection:bool,force_nonproduction:bool}
     */
    public function get() {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $defaults = array(
            'enabled'                  => false,
            'emails'                   => true,
            'reminders'                => true,
            'gateways'                 => false,
            'developer_tools'          => false,
            'notify_staging_detection' => true,
            'force_nonproduction'      => false,
        );

        $stored = get_option(self::CONFIG_OPTION, null);
        if (!is_array($stored)) {
            $stored = array();
        }

        $legacy_present = (get_option(self::OPTION_NAME, null) !== null || get_option(self::LEGACY_OPTION_NAME, null) !== null);
        if ($stored === array() && $legacy_present) {
            $legacy = (bool) get_option(self::OPTION_NAME, (bool) get_option(self::LEGACY_OPTION_NAME, false));
            $stored = array(
                'enabled'                  => $legacy,
                'emails'                   => $legacy,
                'reminders'                => false,
                'gateways'                 => false,
                'developer_tools'          => false,
                'notify_staging_detection' => true,
                'force_nonproduction'      => false,
            );
            update_option(self::CONFIG_OPTION, $stored, false);
        }

        $this->cache = array_merge($defaults, array_intersect_key($stored, $defaults));
        return $this->cache;
    }

    /**
     * @param array $config Config subset.
     */
    public function save(array $config) {
        $defaults = array(
            'enabled'                  => false,
            'emails'                   => true,
            'reminders'                => true,
            'gateways'                 => false,
            'developer_tools'          => false,
            'notify_staging_detection' => true,
            'force_nonproduction'      => false,
        );
        $clean = array_merge($defaults, array_intersect_key($config, $defaults));
        update_option(self::CONFIG_OPTION, $clean, false);
        update_option(self::OPTION_NAME, !empty($clean['enabled']), false);
        $this->cache = $clean;
    }

    /**
     * @param mixed $value Raw posted value.
     *
     * @return array
     */
    public function sanitize_posted($value) {
        if (!is_array($value)) {
            return $this->get();
        }

        return array(
            'enabled'                  => !empty($value['enabled']),
            'emails'                   => !empty($value['emails']),
            'reminders'                => !empty($value['reminders']),
            'gateways'                 => !empty($value['gateways']),
            'developer_tools'          => !empty($value['developer_tools']),
            'notify_staging_detection' => !empty($value['notify_staging_detection']),
            'force_nonproduction'      => !empty($value['force_nonproduction']),
        );
    }

    /**
     * @return bool
     */
    public function is_master_enabled() {
        $c = $this->get();
        return !empty($c['enabled']);
    }

    /**
     * @return bool
     */
    public function module_emails() {
        $c = $this->get();
        return !empty($c['emails']);
    }

    /**
     * @return bool
     */
    public function module_reminders() {
        $c = $this->get();
        return !empty($c['reminders']);
    }

    /**
     * @return bool
     */
    public function module_gateways() {
        $c = $this->get();
        return !empty($c['gateways']);
    }

    /**
     * @return bool
     */
    public function module_developer_tools() {
        $c = $this->get();
        return !empty($c['developer_tools']);
    }

    /**
     * @return bool
     */
    public function should_notify_staging_detection() {
        $c = $this->get();
        return !empty($c['notify_staging_detection']);
    }

    /**
     * User override: treat install as non-production for safeguards regardless of WP / URL detection.
     *
     * @return bool
     */
    public function is_force_nonproduction() {
        $c = $this->get();
        return !empty($c['force_nonproduction']);
    }

    /**
     * MemberPress mail safeguards active (master + emails module).
     *
     * @return bool
     */
    public function is_email_guard_enabled() {
        return $this->is_master_enabled() && $this->module_emails();
    }
}
