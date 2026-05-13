<?php
/**
 * MemberPress submenu and settings screen.
 *
 * @package StagingDisableEmailsMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin UI.
 */
class SDEM_Admin {

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
        add_action('admin_menu', array($this, 'add_menu'), 100);
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * MemberPress submenu.
     */
    public function add_menu() {
        if (!class_exists('MeprUtils', false)) {
            return;
        }

        add_submenu_page(
            'memberpress',
            __('Staging safe mode', 'staging-disable-emails-memberpress'),
            __('Staging safe mode', 'staging-disable-emails-memberpress'),
            $this->env->get_menu_capability(),
            'staging-disable-emails-memberpress',
            array($this, 'render_page')
        );
    }

    /**
     * Register for Settings API (optional integrations).
     */
    public function register_settings() {
        register_setting(
            'staging_disable_emails_memberpress_settings',
            SDEM_Config::CONFIG_OPTION,
            array(
                'type'              => 'object',
                'sanitize_callback' => array($this->config, 'sanitize_posted'),
                'default'           => array(),
            )
        );
    }

    /**
     * Render settings page.
     */
    public function render_page() {
        if (!current_user_can($this->env->get_menu_capability())) {
            return;
        }

        if (isset($_POST['submit']) && check_admin_referer('staging_disable_emails_memberpress_settings')) {
            $posted = isset($_POST[SDEM_Config::CONFIG_OPTION]) && is_array($_POST[SDEM_Config::CONFIG_OPTION])
                ? wp_unslash($_POST[SDEM_Config::CONFIG_OPTION])
                : array();
            $this->config->save($this->config->sanitize_posted($posted));
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'staging-disable-emails-memberpress') . '</p></div>';
        }

        $c = $this->config->get();
        $is_staging = $this->env->is_staging();
        $staging_doc = 'https://memberpress.com/docs/how-to-create-a-staging-site-with-memberpress/';
        $stop_all_doc = 'https://memberpress.com/docs/how-to-stop-emails-from-sending-from-your-staging-site/';
        $pfx = SDEM_Config::CONFIG_OPTION;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('MemberPress staging safe mode', 'staging-disable-emails-memberpress'); ?></h1>

            <div class="card" style="max-width: 52rem; margin-bottom: 1.25rem;">
                <h2 class="title" style="margin-top: 0;"><?php echo esc_html__('Official guidance', 'staging-disable-emails-memberpress'); ?></h2>
                <p class="description" style="margin-top: 0;">
                    <?php echo esc_html__('Use this plugin together with MemberPress docs for cloning, gateways, and webhooks.', 'staging-disable-emails-memberpress'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 1.25em;">
                    <li>
                        <?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: %s: MemberPress staging documentation URL */
                                __('<a href="%s">How to create a staging site with MemberPress</a> (gateways, connection data, test mode).', 'staging-disable-emails-memberpress'),
                                esc_url($staging_doc)
                            )
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: %s: URL */
                                __('<a href="%s">Stop all emails from staging</a> if you need to block WordPress-wide mail.', 'staging-disable-emails-memberpress'),
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
                            <?php echo esc_html__('Enable safe mode', 'staging-disable-emails-memberpress'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($pfx); ?>[enabled]" value="1" <?php checked(!empty($c['enabled']), true); ?>>
                                <?php echo esc_html__('Turn on MemberPress safeguards on non-production environments only', 'staging-disable-emails-memberpress'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Safeguards', 'staging-disable-emails-memberpress'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($pfx); ?>[emails]" value="1" <?php checked(!empty($c['emails']), true); ?>>
                                    <?php echo esc_html__('Block MemberPress-related email (core + add-ons that use MemberPress mail paths)', 'staging-disable-emails-memberpress'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($pfx); ?>[reminders]" value="1" <?php checked(!empty($c['reminders']), true); ?>>
                                    <?php echo esc_html__('Pause reminder crons and block reminder emails (matches disabling reminder processing)', 'staging-disable-emails-memberpress'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($pfx); ?>[gateways]" value="1" <?php checked(!empty($c['gateways']), true); ?>>
                                    <?php echo esc_html__('Force test / sandbox flags in MemberPress payment settings at runtime (does not edit the database)', 'staging-disable-emails-memberpress'); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Supports Stripe, Square, legacy PayPal gateways, Authorize.Net. PayPal Commerce uses Connect and its own live/test detection—use a sandbox connection per MemberPress docs.', 'staging-disable-emails-memberpress'); ?>
                                </p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($pfx); ?>[developer_tools]" value="1" <?php checked(!empty($c['developer_tools']), true); ?>>
                                    <?php echo esc_html__('Deactivate the MemberPress Developer Tools add-on while safe mode is on (reactivates when you turn this off or disable safe mode)', 'staging-disable-emails-memberpress'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notifications', 'staging-disable-emails-memberpress'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($pfx); ?>[notify_staging_detection]" value="1" <?php checked(!empty($c['notify_staging_detection']), true); ?>>
                                <?php echo esc_html__('Email site admin once when a non-production environment is first detected (per site URL)', 'staging-disable-emails-memberpress'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Uses WordPress wp_mail to the admin email (or addresses you filter in code). Independent of MemberPress email blocking.', 'staging-disable-emails-memberpress'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Environment', 'staging-disable-emails-memberpress'); ?>
                        </th>
                        <td>
                            <?php if ($is_staging) : ?>
                                <span style="color: #d63638; font-weight: bold;"><?php echo esc_html__('Non-production detected', 'staging-disable-emails-memberpress'); ?></span>
                                <p class="description">
                                    <?php echo esc_html__('Safe mode runs here when enabled (staging / local / development URL or constants, or your custom filter).', 'staging-disable-emails-memberpress'); ?>
                                </p>
                            <?php else : ?>
                                <span style="color: #00a32a; font-weight: bold;"><?php echo esc_html__('Production', 'staging-disable-emails-memberpress'); ?></span>
                                <p class="description">
                                    <?php echo esc_html__('Safeguards do not run on this environment.', 'staging-disable-emails-memberpress'); ?>
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
}
