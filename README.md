# Staging Disable Emails for MemberPress

A WordPress plugin that stops MemberPress from emailing real customers when you clone production to staging — without breaking 2FA, password resets, or other WordPress mail.

## The Problem

Cloning production to staging clones the entire database: active subscriptions, pending renewals, customer email addresses. WordPress cron keeps running. MemberPress keeps doing its job. Renewal notices, receipts, and expiration warnings get sent from staging to real customers, all pointing at the wrong site.

The staging site doesn't know it's staging. This plugin tells it.

## What It Does

Blocks MemberPress emails (and only MemberPress emails) when the site is detected as staging and the feature is enabled:

- All core MemberPress emails — welcome, receipts, renewals, expiration reminders
- MemberPress Corporate — sub-account signup and welcome
- MemberPress Courses — course and lesson completion
- MemberPress Gifting, Downloads, and other addons that use the core email system or send through `wp_mail()` directly

Everything else — 2FA codes, password resets, plugin notifications, admin alerts — passes through untouched.

## How It Works

The plugin hooks into two filters:

- `mepr_wp_mail_recipients` — returns an empty recipient list for core MemberPress emails (and addons that use the core email system), so MemberPress's send loop has nothing to process.
- `pre_wp_mail` — short-circuits sends for addons that bypass the core system and call `wp_mail()` directly with their own templates.

Emails are blocked only when **both** conditions are met:

1. The "Disable MemberPress Emails" setting is enabled.
2. The site is detected as a staging environment.

This is intentional. Detection alone false-positives on dev domains that happen to contain `stage`. A setting alone defeats the point — someone has to remember to flip it after every clone. Together, they fail safe.

## Staging Detection

A site is treated as staging if any of the following match:

| Method | Trigger |
|---|---|
| `WP_ENVIRONMENT_TYPE` constant (WP 5.5+) | Set to `staging` |
| `WP_ENV` constant | Set to `staging`, `stage`, `dev`, or `development` |
| Site URL contains | `staging`, `stage`, `.test`, `.local`, or `localhost` |
| Custom filter | Returns `true` from `staging_disable_emails_memberpress_is_staging` |

### Custom Detection

For non-standard staging conventions, override detection with a filter:

```php
add_filter( 'staging_disable_emails_memberpress_is_staging', function ( $is_staging ) {
    return strpos( $_SERVER['HTTP_HOST'], 'staging.yoursite.com' ) !== false;
} );
```

## Setup

1. Upload the plugin folder to `/wp-content/plugins/` and activate it through the Plugins menu.
2. Go to **Settings → Staging Disable Emails**.
3. Confirm the **Environment Status** banner shows the site is detected as staging (red) or production (green).
4. Check **Disable MemberPress Emails** and save.

The setting is stored in the database, so it persists through clone-to-staging operations. Enable it once on staging and it stays enabled the next time the database is refreshed from production — no checklist required.

## Requirements

- WordPress 5.0+
- MemberPress installed and active

## Changelog

### 1.0.0

- Initial release
- Blocks all MemberPress emails on staging — core plus Corporate, Courses, Gifting, Downloads, and other addons
- Admin settings page with environment status indicator
- Multi-method staging detection: `WP_ENVIRONMENT_TYPE`, `WP_ENV`, URL patterns
- Custom detection via `staging_disable_emails_memberpress_is_staging` filter
