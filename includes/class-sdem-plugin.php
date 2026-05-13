<?php
/**
 * Loads includes and boots subsystems.
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin bootstrap.
 */
class SDEM_Plugin {

    /**
     * @var SDEM_Plugin|null
     */
    private static $instance = null;

    /**
     * @return SDEM_Plugin
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $config = new SDEM_Config();
        $env    = new SDEM_Environment();

        $notifier = new SDEM_Staging_Notifier($config, $env);
        $notifier->init();

        $admin = new SDEM_Admin($config, $env);
        $admin->init();

        $bar = new SDEM_Admin_Bar($config, $env);
        $bar->init();

        $safeguards = new SDEM_Safeguards($config, $env);
        $safeguards->init();
    }
}
