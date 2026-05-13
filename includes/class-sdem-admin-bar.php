<?php
/**
 * Admin bar badge for active safe mode.
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin bar integration.
 */
class SDEM_Admin_Bar {

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
        add_action('admin_bar_menu', array($this, 'add_badge'), 100);
        add_action('admin_head', array($this, 'print_styles'), 99);
        add_action('wp_head', array($this, 'print_styles'), 99);
    }

    /**
     * @param WP_Admin_Bar $wp_admin_bar Admin bar.
     */
    public function add_badge($wp_admin_bar) {
        if (!is_admin_bar_showing() || !$this->env->is_staging() || !$this->config->is_master_enabled()) {
            return;
        }

        if (!current_user_can($this->env->get_menu_capability())) {
            return;
        }

        if (!apply_filters('staging_disable_emails_memberpress_show_admin_bar_badge', true)) {
            return;
        }

        $modules = array();
        if ($this->config->module_emails()) {
            $modules[] = __('emails', 'staging-disable-emails-memberpress');
        }
        if ($this->config->module_reminders()) {
            $modules[] = __('reminders', 'staging-disable-emails-memberpress');
        }
        if ($this->config->module_gateways()) {
            $modules[] = __('gateways', 'staging-disable-emails-memberpress');
        }
        if ($this->config->module_developer_tools()) {
            $modules[] = __('dev tools', 'staging-disable-emails-memberpress');
        }

        $tooltip = __('MemberPress staging safe mode is active.', 'staging-disable-emails-memberpress');
        if (!empty($modules)) {
            $tooltip .= ' ' . sprintf(
                /* translators: %s: comma-separated list of active safeguard names */
                __('Active: %s.', 'staging-disable-emails-memberpress'),
                implode(', ', $modules)
            );
        }

        $wp_admin_bar->add_node(
            array(
                'id'     => 'sdem-safe-mode',
                'parent' => 'top-secondary',
                'title'  => esc_html__('MP Safe Mode', 'staging-disable-emails-memberpress'),
                'href'   => admin_url('admin.php?page=staging-disable-emails-memberpress'),
                'meta'   => array(
                    'title' => esc_attr($tooltip),
                    'class' => 'sdem-safe-mode-badge',
                ),
            )
        );
    }

    /**
     * Inline styles for the badge.
     */
    public function print_styles() {
        if (!is_admin_bar_showing() || !$this->env->is_staging() || !$this->config->is_master_enabled()) {
            return;
        }

        if (!is_user_logged_in() || !current_user_can($this->env->get_menu_capability())) {
            return;
        }

        if (!apply_filters('staging_disable_emails_memberpress_show_admin_bar_badge', true)) {
            return;
        }
        ?>
        <style id="sdem-safe-mode-admin-bar">
            #wpadminbar #wp-admin-bar-sdem-safe-mode > .ab-item,
            #wpadminbar #wp-admin-bar-sdem-safe-mode > .ab-item:hover,
            #wpadminbar #wp-admin-bar-sdem-safe-mode > .ab-item:focus {
                background: #b32d2e;
                color: #fff;
            }
            #wpadminbar #wp-admin-bar-sdem-safe-mode > .ab-item .ab-label {
                color: #fff;
                font-weight: 600;
            }
        </style>
        <?php
    }
}
