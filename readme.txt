=== Staging Safe Mode for MemberPress ===
Contributors: omaraelhawary
Tags: memberpress, staging, email, sandbox, reminders
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MemberPress staging safe mode: limit MemberPress mail, reminders, and gateway behavior on non-production without disabling normal WordPress email.

== Description ==

Staging and cloned MemberPress sites often still send real member email and renewal traffic. This plugin adds a **MemberPress → Staging safe mode** screen so you can enable safeguards when the site is treated as non-production: optional MemberPress-related mail blocking, reminder pauses, read-time gateway test/sandbox bias, optional Developer Tools deactivation, optional force non-production override, and an optional one-time admin notice when staging is detected.

Normal WordPress email (for example password resets and other plugins) is not turned off by the email safeguard; only MemberPress-originated mail paths targeted by this plugin are affected.

Official MemberPress staging guidance: [How to create a staging site with MemberPress](https://memberpress.com/docs/how-to-create-a-staging-site-with-memberpress/).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/staging-safe-mode-for-memberpress` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Open **MemberPress → Staging safe mode** to configure safeguards (MemberPress must be active for that menu).

== Frequently Asked Questions ==

= Does this stop all email on the site? =

No. The email-related safeguard targets MemberPress-related sends as implemented in this plugin; general `wp_mail` from WordPress core and other plugins is unchanged unless it matches the MemberPress stack heuristic described in the plugin settings and documentation.

= Do I need MemberPress? =

Yes, for the admin UI and MemberPress-specific hooks. The plugin expects MemberPress to be active for the staging safe mode screen.

== Changelog ==

= 1.3.5 =
* Notifications: email when non-production is detected, when force non-production is first enabled, or when safe mode is first turned on (up to three one-time emails per site URL). Sends after saving settings or on admin visits.

= 1.3.4 =
* Renamed plugin to **Staging Safe Mode for MemberPress**; directory / main file slug `staging-safe-mode-for-memberpress`; text domain updated. Settings screen URL is now `admin.php?page=staging-safe-mode-for-memberpress`. Database option keys and `apply_filters` hook names are unchanged for compatibility.

= 1.3.3 =
* Distribution ZIP is built without hidden/dot files (for example `.gitignore` is not bundled). Maintainer build instructions live in the `scripts/` directory on GitHub and are not included in the plugin archive.

= 1.3.2 =
* Maintenance and compatibility updates.
