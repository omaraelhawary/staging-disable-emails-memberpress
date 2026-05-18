<?php
/**
 * Admin notification emails for staging detection, force override, and safe mode enable.
 *
 * @package StagingSafeModeForMemberPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends at-most-once-per-site-url notices for each qualifying event.
 */
class SDEM_Staging_Notifier {

    const TRIGGER_DETECTION = 'detection';

    const TRIGGER_FORCE = 'force';

    const TRIGGER_ENABLED = 'enabled';

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
        add_action('admin_init', array($this, 'maybe_send_notification'), 5);
        add_action('sdem_config_saved', array($this, 'maybe_send_notification'), 10);
    }

    /**
     * Send a combined notice when any pending trigger applies for this site URL.
     */
    public function maybe_send_notification() {
        if (!$this->config->should_notify_staging_detection()) {
            return;
        }

        if (!apply_filters('staging_disable_emails_memberpress_send_staging_detection_email', true)) {
            return;
        }

        if (!current_user_can($this->env->get_menu_capability())) {
            return;
        }

        $site_key      = md5(home_url());
        $sent_triggers = $this->get_sent_triggers($site_key);
        $pending       = $this->get_pending_triggers($sent_triggers, $site_key);
        if (empty($pending)) {
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

        $subject = $this->build_email_subject($pending);
        $parts   = $this->build_email_parts($pending);

        $content_type_filter = static function () {
            return 'text/html';
        };
        add_filter('wp_mail_content_type', $content_type_filter);

        $mail_sent = wp_mail($recipients, $subject, $parts['html']);

        remove_filter('wp_mail_content_type', $content_type_filter);

        if ($mail_sent) {
            $this->mark_triggers_sent($pending, $site_key, $sent_triggers);
        }
    }

    /**
     * @param string $site_key MD5 of home_url().
     *
     * @return array<string, string>
     */
    private function get_sent_triggers($site_key) {
        $sent = get_option(SDEM_Config::STAGING_NOTICES_SENT_KEY, array());
        if (!is_array($sent)) {
            $sent = array();
        }

        $legacy = get_option(SDEM_Config::STAGING_NOTICE_SENT_KEY, '');
        if ($legacy === $site_key && ($sent[self::TRIGGER_DETECTION] ?? '') !== $site_key) {
            $sent[self::TRIGGER_DETECTION] = $site_key;
        }

        return $sent;
    }

    /**
     * @param array<string, string> $sent     Already-sent map.
     * @param string                $site_key MD5 of home_url().
     *
     * @return string[]
     */
    private function get_pending_triggers(array $sent, $site_key) {
        $pending = array();

        if ($this->env->is_nonproduction_detected() && ($sent[self::TRIGGER_DETECTION] ?? '') !== $site_key) {
            $pending[] = self::TRIGGER_DETECTION;
        }

        if ($this->config->is_force_nonproduction() && ($sent[self::TRIGGER_FORCE] ?? '') !== $site_key) {
            $pending[] = self::TRIGGER_FORCE;
        }

        if ($this->env->is_staging()
            && $this->config->is_master_enabled()
            && ($sent[self::TRIGGER_ENABLED] ?? '') !== $site_key) {
            $pending[] = self::TRIGGER_ENABLED;
        }

        return $pending;
    }

    /**
     * @param string[]              $pending  Triggers being sent.
     * @param string                $site_key MD5 of home_url().
     * @param array<string, string> $sent     Existing sent map.
     */
    private function mark_triggers_sent(array $pending, $site_key, array $sent) {
        foreach ($pending as $trigger) {
            $sent[ $trigger ] = $site_key;
        }
        update_option(SDEM_Config::STAGING_NOTICES_SENT_KEY, $sent, false);

        if (in_array(self::TRIGGER_DETECTION, $pending, true)) {
            update_option(SDEM_Config::STAGING_NOTICE_SENT_KEY, $site_key, false);
        }
    }

    /**
     * @param string[] $pending Triggers included in this send.
     *
     * @return string
     */
    private function build_email_subject(array $pending) {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = home_url();
        }

        $subject = sprintf(
            /* translators: 1: site title, 2: site hostname or URL */
            __('[%1$s] MemberPress staging safe mode — review settings (%2$s)', 'staging-safe-mode-for-memberpress'),
            wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
            $host
        );

        return (string) apply_filters('staging_disable_emails_memberpress_staging_detection_email_subject', $subject, $pending);
    }

    /**
     * @param string[] $pending Triggers included in this send.
     *
     * @return array{plain:string,html:string}
     */
    private function build_email_parts(array $pending) {
        $settings_url = admin_url('admin.php?page=staging-safe-mode-for-memberpress');
        $sections     = array();

        $sections[] = array(
            'heading' => '',
            'lines'   => array(
                __('Staging Safe Mode for MemberPress is active on this WordPress site and needs your attention.', 'staging-safe-mode-for-memberpress'),
                __('This message was sent with WordPress wp_mail and is not blocked by the plugin. Only MemberPress-related mail is affected when the email safeguard is on.', 'staging-safe-mode-for-memberpress'),
            ),
        );

        $sections[] = array(
            'heading' => __('Site address', 'staging-safe-mode-for-memberpress'),
            'lines'   => array(home_url()),
        );

        $why_lines = array();
        if (in_array(self::TRIGGER_DETECTION, $pending, true)) {
            $reasons = $this->env->get_nonproduction_detection_reasons();
            if (!empty($reasons)) {
                foreach ($reasons as $reason) {
                    $why_lines[] = sprintf(
                        /* translators: %s: detection reason */
                        __('Non-production detected: %s', 'staging-safe-mode-for-memberpress'),
                        $reason
                    );
                }
            } else {
                $why_lines[] = __('Non-production environment was detected automatically', 'staging-safe-mode-for-memberpress');
            }
        }

        if (in_array(self::TRIGGER_FORCE, $pending, true)) {
            $why_lines[] = __('You enabled "Force treat this site as non-production" on the staging safe mode settings screen', 'staging-safe-mode-for-memberpress');
        }

        if (in_array(self::TRIGGER_ENABLED, $pending, true)) {
            $why_lines[] = __('You turned on "Turn on MemberPress safeguards on non-production environments only"', 'staging-safe-mode-for-memberpress');
        }

        $sections[] = array(
            'heading' => __('Why you are receiving this', 'staging-safe-mode-for-memberpress'),
            'lines'   => $why_lines,
            'list'    => true,
        );

        $status_lines = array();
        if ($this->config->is_master_enabled()) {
            $status_lines[] = __('Enabled on this site', 'staging-safe-mode-for-memberpress');
        } else {
            $status_lines[] = __('Not enabled yet — turn it on before members receive mail or live charges from this clone', 'staging-safe-mode-for-memberpress');
        }

        if ($this->config->is_force_nonproduction() && !in_array(self::TRIGGER_FORCE, $pending, true)) {
            $status_lines[] = __('Force non-production override is on', 'staging-safe-mode-for-memberpress');
        }

        $sections[] = array(
            'heading' => __('Staging safe mode status', 'staging-safe-mode-for-memberpress'),
            'lines'   => $status_lines,
            'list'    => true,
        );

        if ($this->config->is_master_enabled()) {
            $sections[] = array(
                'heading' => __('Safeguards configured (when safe mode is on)', 'staging-safe-mode-for-memberpress'),
                'lines'   => $this->get_safeguard_status_lines(),
                'list'    => true,
            );
        }

        if ($this->config->is_email_guard_enabled()) {
            $sections[] = array(
                'heading' => '',
                'lines'   => array(
                    __('MemberPress receipts, reminders, and other plugin mail will not send while "Block MemberPress-related email" is on. Uncheck that option on the settings screen if you need to test MemberPress email on this environment.', 'staging-safe-mode-for-memberpress'),
                ),
            );
        }

        $sections[] = array(
            'heading' => __('Recommended next step', 'staging-safe-mode-for-memberpress'),
            'lines'   => array(
                __('Open MemberPress → Staging safe mode and confirm safeguards match how you use this environment.', 'staging-safe-mode-for-memberpress'),
                $settings_url,
            ),
        );

        $sections[] = array(
            'heading' => __('About this message', 'staging-safe-mode-for-memberpress'),
            'lines'   => array(
                __('You may receive up to three one-time emails per site address: when non-production is first detected, when you first enable the force override, and when you first turn on safe mode. Disable under Notifications on the settings screen.', 'staging-safe-mode-for-memberpress'),
            ),
        );

        $plain = $this->render_email_plain($sections);
        $html  = $this->render_email_html($sections);

        return array(
            'plain' => (string) apply_filters('staging_disable_emails_memberpress_staging_detection_email_body', $plain, $pending),
            'html'  => (string) apply_filters('staging_disable_emails_memberpress_staging_detection_email_body_html', $html, $pending),
        );
    }

    /**
     * @param array<int, array{heading:string,lines:string[],list?:bool,link?:string}> $sections
     *
     * @return string
     */
    private function render_email_plain(array $sections) {
        $blocks = array();

        foreach ($sections as $section) {
            $chunk = array();
            if ($section['heading'] !== '') {
                $chunk[] = $section['heading'];
            }
            foreach ($section['lines'] as $line) {
                $chunk[] = !empty($section['list']) ? '  - ' . $line : $line;
            }
            $blocks[] = implode("\n", $chunk);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param array<int, array{heading:string,lines:string[],list?:bool,link?:string}> $sections
     *
     * @return string
     */
    private function render_email_html(array $sections) {
        $charset = apply_filters('wp_mail_charset', get_bloginfo('charset'));
        $parts   = array();

        foreach ($sections as $section) {
            $block = '';
            if ($section['heading'] !== '') {
                $block .= '<h2 style="margin:1.25em 0 0.5em;font-size:16px;color:#1d2327;">' . esc_html($section['heading']) . '</h2>';
            }

            if (!empty($section['list'])) {
                $block .= '<ul style="margin:0 0 1em;padding-left:1.25em;">';
                foreach ($section['lines'] as $line) {
                    $block .= '<li style="margin:0.25em 0;">' . esc_html($line) . '</li>';
                }
                $block .= '</ul>';
            } else {
                foreach ($section['lines'] as $line) {
                    if (filter_var($line, FILTER_VALIDATE_URL)) {
                        $block .= '<p style="margin:0 0 1em;"><a href="' . esc_url($line) . '">' . esc_html($line) . '</a></p>';
                        continue;
                    }
                    $block .= '<p style="margin:0 0 1em;line-height:1.5;">' . esc_html($line) . '</p>';
                }
            }
            $parts[] = $block;
        }

        $body = implode('', $parts);

        return '<!DOCTYPE html><html><head><meta charset="' . esc_attr($charset) . '"></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:14px;color:#1d2327;max-width:36em;margin:0;padding:1em;">' . $body . '</body></html>';
    }

    /**
     * @return string[]
     */
    private function get_safeguard_status_lines() {
        $items = array(
            array(
                $this->config->is_email_guard_enabled(),
                __('Block MemberPress-related email', 'staging-safe-mode-for-memberpress'),
            ),
            array(
                $this->config->is_master_enabled() && $this->config->module_reminders(),
                __('Pause reminder crons and emails', 'staging-safe-mode-for-memberpress'),
            ),
            array(
                $this->config->is_master_enabled() && $this->config->module_gateways(),
                __('Bias payment gateways toward test / sandbox at runtime', 'staging-safe-mode-for-memberpress'),
            ),
            array(
                $this->config->is_master_enabled() && $this->config->module_developer_tools(),
                __('Deactivate MemberPress Developer Tools add-on', 'staging-safe-mode-for-memberpress'),
            ),
        );

        $lines = array();
        foreach ($items as $item) {
            $active = $item[0];
            $label  = $item[1];
            $state  = $active
                ? __('on', 'staging-safe-mode-for-memberpress')
                : __('off', 'staging-safe-mode-for-memberpress');
            $lines[] = sprintf('%s — %s', $label, $state);
        }

        return $lines;
    }
}
