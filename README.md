# Staging Disable Emails for MemberPress

A WordPress plugin that disables all MemberPress emails on staging environments while allowing other emails (like 2FA) to work normally.

## Description

This plugin specifically targets MemberPress emails by hooking into:
- The `mepr_wp_mail_recipients` filter for core MemberPress emails (including Corporate and other addons that use the core system)
- The `pre_wp_mail` filter for MemberPress addon emails that use their own email systems

When on a staging environment, it prevents MemberPress emails from being sent, including:
- All core MemberPress emails (welcome, receipts, reminders, etc.)
- MemberPress Corporate emails (sub-account signup, welcome, etc.)
- MemberPress Courses emails (course completion, lesson completion, etc.)
- MemberPress Gifting emails
- MemberPress Downloads emails
- All other MemberPress addon emails

**Important:** This plugin only affects emails sent through MemberPress's email systems. Other WordPress emails that use `wp_mail()` directly (such as 2FA plugins, password reset emails, etc.) will continue to work normally.

## Staging Detection

The plugin automatically detects staging environments using multiple methods:

1. **WP_ENVIRONMENT_TYPE** constant (WordPress 5.5+) - Checks if set to `'staging'`
2. **WP_ENV** constant - Checks if set to `'staging'`, `'stage'`, `'dev'`, or `'development'`
3. **URL detection** - Checks if the site URL contains: `staging`, `stage`, `.test`, `.local`, or `localhost`
4. **Custom filter** - Allows custom detection via `staging_disable_emails_memberpress_is_staging` filter

## Custom Staging Detection

If you need custom staging detection logic, you can use the filter:

```php
add_filter('staging_disable_emails_memberpress_is_staging', function($is_staging) {
    // Add your custom detection logic here
    // Return true if staging, false otherwise
    return strpos($_SERVER['HTTP_HOST'], 'staging.yoursite.com') !== false;
});
```

## Installation

1. Upload the plugin folder (e.g. `memberpress-disable-emails-staging`) to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress

## How to Activate the Option

After installing and activating the plugin, follow these steps to enable the email blocking feature:

1. **Navigate to Settings**: In your WordPress admin dashboard, go to **Settings > Staging Disable Emails**
2. **Enable the Feature**: Check the checkbox next to "Disable MemberPress Emails"
3. **Verify Environment**: Check the "Environment Status" section to confirm your site is detected as a staging environment
4. **Save Changes**: Click the "Save Changes" button at the bottom of the page

Once enabled, all MemberPress emails will be blocked on staging environments. The setting will persist even when you clone your live site to staging, so you only need to enable it once.

## How It Works

The plugin hooks into MemberPress's email system at the `mepr_wp_mail_recipients` filter. When this filter returns an empty array, MemberPress's email sending loop has no recipients to process, so no emails are sent.

This approach is superior to disabling all WordPress emails because:
- It only affects MemberPress emails
- Other plugins' emails (2FA, password resets, etc.) continue to work
- It can be easily enabled/disabled via the settings page
- It works immediately after cloning live to staging (just enable the setting)

## Settings

The plugin adds a settings page at **Settings > Staging Disable Emails** where you can:

- **Enable/Disable**: Toggle the feature on or off with a simple checkbox
  - Check the box to enable email blocking on staging
  - Uncheck the box to allow MemberPress emails to be sent normally
- **Environment Status**: See whether the site is detected as staging or production
  - Shows "Staging Environment Detected" in red if staging is detected
  - Shows "Production Environment" in green if not detected as staging
- The setting is stored in the database, so it persists when you clone from live to staging

**Note**: The plugin will only block emails when both conditions are met:
1. The option is enabled (checkbox is checked)
2. The site is detected as a staging environment

## Requirements

- WordPress 5.0+
- MemberPress plugin installed and active

## Changelog

### 1.0.0
- Initial release
- Disables all MemberPress emails (core, Courses, Corporate, Gifting, Downloads, and all addons) on staging environments
- Enable/disable setting with admin settings page
- Environment status indicator
- Supports multiple staging detection methods
- Allows custom staging detection via filter
