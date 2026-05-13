# Staging Disable Emails for MemberPress

WordPress plugin for **MemberPress staging safe mode**: block MemberPress-related mail, pause reminders, bias gateways toward test/sandbox at runtime, optionally unload Developer Tools, optionally **force** non-production when automatic detection still says production, and optionally notify admins when non-production is detected — without turning off normal WordPress email (2FA, password reset, etc.).

**Version:** 1.3.1  
**Requires:** WordPress 5.0+, MemberPress (active) for the settings screen and MemberPress hooks.

---

## The problem

Cloning production to staging copies the database: real member emails, subscriptions, and crons. MemberPress keeps sending receipts, reminders, and renewal notices from the wrong host unless you reconfigure everything by hand.

## What this plugin does

Configure everything under **MemberPress → Staging safe mode**. When the site is treated as **non-production** (automatic detection or the **Force treat this site as non-production** override) and **Enable safe mode** is on, you can toggle:

| Safeguard | Behavior |
|-----------|----------|
| **Emails** | Clears recipients on `mepr_wp_mail_recipients` and short-circuits `wp_mail` when the stack shows MemberPress core or `memberpress-*` add-ons (heuristic). Other plugins’ mail is unchanged. |
| **Reminders** | Forces `mepr_disable_reminder_crons` via `pre_option_*` and sets MemberPress `mepr_{event}_reminder_disable` filters so reminder emails do not send. |
| **Gateways** | Filters `option_mepr_options` so supported gateways get `test_mode` / `sandbox` at **read** time only (no DB write). PayPal Commerce / Connect is not forced; use MemberPress + PayPal sandbox docs. |
| **Developer Tools** | Deactivates `memberpress-developer-tools/main.php` while this module is on; reactivates when you turn it off or leave non-production / disable safe mode (tracks a small flag option). |
| **Notifications** | Optional: email site admin **once per `home_url()` hash** the first time a capable user hits `admin_init` when automatic detection sees non-production (uses `wp_mail`, not MemberPress). Skipped if you only use the **force** override (no “detected” email). |

Official MemberPress staging guidance: [How to create a staging site with MemberPress](https://memberpress.com/docs/how-to-create-a-staging-site-with-memberpress/).

---

## Installation

1. Copy the plugin folder into `wp-content/plugins/` (or install via ZIP in **Plugins → Add New**).
2. Activate **Staging Disable Emails for MemberPress**.
3. Ensure **MemberPress** is active (the settings UI is a **MemberPress** submenu).

There is **no** entry under **Settings**; everything lives under **MemberPress → Staging safe mode**.

---

## Quick setup

1. Open **MemberPress → Staging safe mode**.
2. Check **Environment** (non-production vs production). If WordPress still shows **Production** but this install is really a clone, enable **Force treat this site as non-production** (see [Staging detection](#staging-detection)).
3. Enable **Enable safe mode**, choose **Safeguards** and **Notifications**, then **Save**.

Settings are stored in the database and survive cloning; re-check after each pull from production if your workflow resets options.

---

## When safeguards actually run

Most behavior runs only when **both** are true:

1. **Non-production** — automatic detection **or** the **Force treat this site as non-production** checkbox (see [Staging detection](#staging-detection)).
2. **Enable safe mode** — master switch on the settings page.

The **Emails** safeguard additionally requires its checkbox to be on. Same pattern: each safeguard has its own checkbox under **Safeguards**.

---

## How it works (technical)

### Email blocking

- **`mepr_wp_mail_recipients`** — returns an empty recipient list so MemberPress core mail does not send.
- **`pre_wp_mail`** — returns a truthy short-circuit when the backtrace shows a file under `wp-content/plugins/memberpress/` or `wp-content/plugins/memberpress-*/` and the frame looks email-related (classes/functions containing Email, Utils, wp_mail, send, email).

### Reminders

- **`pre_option_mepr_disable_reminder_crons`** — returns a truthy value so MemberPress skips scheduling reminder crons.
- **`mepr_{trigger_event}_reminder_disable`** — for each reminder event (e.g. `mepr_sub-expires_reminder_disable`), returns `true` to skip sending.

### Gateways

- **`option_mepr_options`** — merges test/sandbox flags into `integrations` and `legacy_integrations` rows for supported gateway class names (Stripe, legacy PayPal family, Square, Authorize.Net).

### Developer Tools

- **`deactivate_plugins( 'memberpress-developer-tools/main.php' )`** when conditions match; restoration uses **`activate_plugin`** when safe mode / module / environment no longer applies.

---

## Staging detection

Internally, **“staging” / non-production** means the same gate used for safeguards and the admin bar: `SDEM_Environment::is_staging()`.

### Automatic detection

A site counts as non-production **without** the plugin override if **any** of these match:

| Method | Trigger |
|--------|---------|
| `WP_ENVIRONMENT_TYPE` | `staging`, `local`, or `development` |
| `WP_ENV` | `staging`, `stage`, `local`, `dev`, or `development` |
| Site URL (`home_url()`) | Contains `staging`, `stage`, `.test`, `.local`, or `localhost` (case-insensitive substring) |
| Legacy filter | `mepr_disable_emails_is_staging` returns true |
| Primary filter | `staging_disable_emails_memberpress_is_staging` returns true |

### Force override (settings UI)

If automatic detection still shows **Production** (for example a clone on a URL without staging hints and `WP_ENVIRONMENT_TYPE` set to `production`), check **Force treat this site as non-production** under **Enable safe mode** and save. That sets `force_nonproduction` in config and makes `is_staging()` return true regardless of the table above.

- Use only on real staging/dev clones, or when you accept that emails, reminders, gateway test mode, and Developer Tools handling will run on that URL.
- Turn the override **off** before the same WordPress install serves a live production domain.
- The one-time “non-production detected” notification email is **not** sent when only the force option applies (the copy is meant for automatic detection).

### Custom detection

```php
add_filter( 'staging_disable_emails_memberpress_is_staging', function ( $is_staging ) {
    return $is_staging || ( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'staging.example.com' ) !== false );
}, 10, 1 );
```

---

## Options (database)

| Option | Purpose |
|--------|---------|
| `staging_disable_emails_memberpress_config` | Serialized array: `enabled`, `emails`, `reminders`, `gateways`, `developer_tools`, `notify_staging_detection`, `force_nonproduction`. |
| `staging_disable_emails_memberpress_enabled` | Boolean mirror of master `enabled` (for older integrations that read this key). |
| `mepr_disable_emails_staging_enabled` | Legacy; migrated into config on first read if present. |
| `staging_mepr_dt_deactivated_by_sdem` | Set to `1` when this plugin deactivated Developer Tools so it can restore on toggle-off. |
| `sdem_staging_detection_notice_sent_for` | MD5 of `home_url()` after the one-time staging notification email sends; delete to allow another send on the same URL (testing). |

---

## Filters (developers)

| Filter | Default | Purpose |
|--------|---------|---------|
| `staging_disable_emails_memberpress_is_staging` | — | Mark site as non-production. |
| `mepr_disable_emails_is_staging` | — | Legacy alias read before the filter above. |
| `staging_disable_emails_memberpress_show_admin_bar_badge` | `true` | Hide the **MP Safe Mode** admin bar item when `false`. |
| `staging_disable_emails_memberpress_send_staging_detection_email` | `true` | Disable the one-time admin email when `false`. |
| `staging_disable_emails_memberpress_staging_detection_email_recipients` | `[ get_option( 'admin_email' ) ]` | Override recipient list (array of emails). |
| `staging_disable_emails_memberpress_log_suppressed` | `false` | When `true`, logs suppressed MemberPress-related sends to the PHP error log (with email debug constant below). |

---

## Constants (`wp-config.php`)

```php
// When true, suppressed MemberPress-related email attempts are written to the PHP error log
// (in addition to the filter `staging_disable_emails_memberpress_log_suppressed`).
define( 'STAGING_DISABLE_MEPR_EMAILS_DEBUG', true );
```

---

## Admin bar

When `is_staging()` is true (automatic detection **or** force override) **and** safe mode is on, users with the same capability as the MemberPress admin menu see **MP Safe Mode** (red style) linking to **MemberPress → Staging safe mode**.

---

## Code layout

| Path | Role |
|------|------|
| `staging-disable-emails-memberpress.php` | Bootstrap: `SDEM_*` constants, loads `includes/`, starts `SDEM_Plugin`. |
| `includes/class-sdem-plugin.php` | Singleton wiring: config, environment, notifier, admin, admin bar, safeguards. |
| `includes/class-sdem-config.php` | Option names, defaults, migration, getters for each module. |
| `includes/class-sdem-environment.php` | `is_staging()`, `is_nonproduction_detected()` (automatic path only), `get_menu_capability()`. |
| `includes/class-sdem-safeguards.php` | All MemberPress-facing runtime hooks. |
| `includes/class-sdem-admin.php` | Submenu + settings form + `register_setting`. |
| `includes/class-sdem-admin-bar.php` | Admin bar node + inline CSS. |
| `includes/class-sdem-staging-notifier.php` | One-time `wp_mail` on staging detection. |
| `includes/index.php` | Silence direct directory access. |

---

## Changelog

### 1.3.1

- **Force non-production:** optional setting to treat the install as non-production when automatic detection still reports production (admin UI + `force_nonproduction` in config). One-time staging detection email is skipped when only this override is used.

### 1.3.0

- Refactored into `includes/` (`SDEM_*` classes).
- **Notifications:** optional one-time admin email when non-production is detected (per `home_url()` hash); option `sdem_staging_detection_notice_sent_for`.
- README expanded (options, filters, hooks, layout).

### 1.2.2

- Settings moved to **MemberPress → Staging safe mode** (removed **Settings** submenu).
- Admin bar link updated to `admin.php?page=…`; capability aligned with `MeprUtils::get_mepr_admin_capability()` when available.

### 1.2.1

- Admin bar **MP Safe Mode** badge when safe mode is active on non-production.

### 1.1.0

- Broader non-production detection (`local`, `development`, URL/core paths for `pre_wp_mail`).
- Settings checklist + doc links; optional debug logging for suppressed sends.

### 1.0.0

- Initial release: MemberPress email blocking on staging with a settings UI and staging heuristics.

---

## License

GPL-2.0-or-later (same as WordPress). See `License` in the main plugin file header.

## Support

Plugin header lists author URI and plugin URI. [MemberPress documentation](https://memberpress.com/docs/) and [support](https://memberpress.com/account/support/) cover the core product; use your own channels for this add-on.
