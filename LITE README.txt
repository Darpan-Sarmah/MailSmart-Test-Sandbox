=== SG MailSmart AI Lite ===
Contributors: sgmailsmart
Tags: email, smtp, mail, logging, debugging
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Production-grade email engine with SMTP configuration, email logging, debugging tools, and a REST API — the free core of the SG MailSmart Pro ecosystem.

== Description ==

**SG MailSmart AI Lite** is a production-ready WordPress email management plugin built for developers, agencies, and site owners who need reliable email delivery, full visibility into outgoing mail, and a clean REST API to power custom integrations.

This free plugin is the **core engine** in a Core + Add-on architecture. It is designed from the ground up to support future Pro extensions without requiring any changes to core code.

= Core Features (Free) =

**SMTP Configuration**
* Manual SMTP setup with support for TLS, SSL, and unauthenticated connections
* Works with any SMTP provider: Gmail, SendGrid, Mailgun, Amazon SES, and more
* Sender identity override (From Name / From Email)
* Pluggable transport layer — Pro add-ons can replace the entire delivery driver

**Email Logging**
* Logs every outgoing `wp_mail()` call to a custom indexed database table
* Captures: recipient, subject, headers, status, error message, mailer driver, and timestamp
* Configurable retention period (1–365 days)
* Enable / disable logging independently of SMTP

**Email Debugging**
* Detects delivery failures automatically via `wp_mail_failed`
* Stores detailed error messages for every failed send attempt
* Full log detail view including message body preview
* SMTP debug output to PHP error log when `WP_DEBUG` is enabled

**REST API (No AJAX)**
All plugin functionality is exposed via the WordPress REST API under `/wp-json/mailsmart/v1/`:

* `GET  /settings` — retrieve current plugin settings
* `POST /settings` — update SMTP and general settings
* `GET  /logs` — paginated, filterable, searchable log list
* `GET  /logs/{id}` — full detail for a single log entry
* `DELETE /logs` — bulk delete all log entries
* `DELETE /logs/{id}` — delete a single log entry
* `POST /email/send` — send an email through the plugin engine
* `POST /email/test` — send a test email to verify configuration
* `GET  /dashboard` — aggregate delivery statistics

**Modern Admin UI**
* Clean, REST-driven single-page application
* Dashboard with delivery statistics (last 7 days)
* SMTP settings form with live toggle controls
* Searchable, filterable, paginated email log table
* Test email page with success/failure feedback
* Pro feature teasers for AI Generator and Automation

**Developer-First Architecture**
* OOP with dependency injection via a lightweight service container
* Extensibility hooks at every critical point (see Hook Reference below)
* Pro add-ons can swap the mailer, extend the schema, add REST routes, and inject admin pages — all without modifying plugin files

= Pro Features (Coming Soon) =

* **AI Email Generator** — Generate subject lines and body copy using AI
* **Email Automation** — Drip sequences and trigger-based workflows
* **Advanced Templates** — Drag-and-drop visual email builder
* **Analytics Dashboard** — Open rates, click rates, bounce tracking
* **Additional SMTP Providers** — Native integrations for SES, SendGrid, Mailgun, Postmark

= Hook Reference =

**Actions**
* `mailsmart_before_send` — fires before every email send attempt
* `mailsmart_after_send` — fires after every email send attempt (with result)
* `mailsmart_loaded` — fires after all services are registered (Pro add-on entry point)
* `mailsmart_smtp_configured` — fires after PHPMailer is configured for SMTP
* `mailsmart_settings_saved` — fires after settings are saved via REST
* `mailsmart_logs_purged` — fires after old log entries are purged
* `mailsmart_deactivated` — fires on plugin deactivation

**Filters**
* `mailsmart_mailer` — swap the send callable (replace wp_mail with a custom driver)
* `mailsmart_email_content` — modify email body before delivery (AI injection point)
* `mailsmart_smtp_settings` — modify SMTP settings before they are applied to PHPMailer
* `mailsmart_log_entry` — modify or suppress a log entry before it is written
* `mailsmart_log_stats` — extend the dashboard statistics payload
* `mailsmart_rest_permission` — custom REST API access control
* `mailsmart_admin_menu_pages` — add extra admin sub-pages
* `mailsmart_admin_js_data` — inject extra data into the admin SPA
* `mailsmart_schema_tables` — register additional database tables
* `mailsmart_logging_enabled` — conditionally disable logging at runtime
* `mailsmart_manager_capability` — change the required management capability
* `mailsmart_dashboard_stats` — extend the dashboard stats REST response

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* MySQL 5.6 / MariaDB 10.0 or higher

= Privacy =

This plugin stores email metadata (recipient address, subject, delivery status) in a custom database table on your own server. No data is sent to external servers by the free version. The data is used solely for email delivery debugging and monitoring within your WordPress installation.

== Installation ==

1. Upload the `sg-mailsmart-ai-lite` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **SG MailSmart** in the admin sidebar
4. Configure your SMTP settings under **SMTP Settings**
5. Send a test email under **Test Email** to verify your configuration

= Configuration via Code =

You can send emails through the plugin engine programmatically:

    // Send an email using the plugin's mailer engine (with Pro hooks)
    $result = mailsmart_send_email([
        'to'      => 'user@example.com',
        'subject' => 'Hello!',
        'message' => '<p>Your message here.</p>',
    ]);

    if ( $result['success'] ) {
        // Email was delivered. $result['log_id'] contains the log entry ID.
    } else {
        // $result['error'] contains the error message.
    }

= Pro Add-on Entry Point =

    add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ) {
        // Re-bind the mailer to a custom driver.
        $plugin->container()->singleton( 'mailer', function( $c ) {
            return new My_Custom_Mailer( $c->make('logger') );
        });

        // Add an extra REST route.
        $plugin->loader()->add_action( 'rest_api_init', $my_controller, 'register_routes' );
    });

== Frequently Asked Questions ==

= Does this plugin work with Gmail? =

Yes. Use `smtp.gmail.com` on port 587 with TLS encryption. When your Google account has 2-Step Verification enabled, you must generate an **App Password** from your Google Account security settings and use that instead of your regular password.

= Will this plugin intercept all emails sent by WordPress and other plugins? =

SMTP configuration via this plugin applies to all emails sent through WordPress's `wp_mail()` function, which is used by WordPress core, WooCommerce, Contact Form 7, Gravity Forms, and most other plugins. Logging also captures all `wp_mail()` calls.

= Is the password stored securely? =

The SMTP password is stored in `wp_options` like most WordPress plugin credentials. It is not encrypted at the application layer (encryption at rest depends on your server / database configuration). We recommend using strong filesystem and database permissions, keeping WordPress and PHP up to date, and using HTTPS for your admin area.

= Can Pro add-ons replace the email delivery driver? =

Yes. Use the `mailsmart_mailer` filter or re-bind the `mailer` service in the container on the `mailsmart_loaded` action hook. The returned callable receives an `SG_MailSmart_Email` DTO and must return a `bool`.

= What happens to log data when I deactivate the plugin? =

Deactivation does **not** delete any data. Your log entries and settings are preserved so they are available when you reactivate. To permanently remove all plugin data, uninstall the plugin (Delete) from the WordPress plugins screen — this triggers `uninstall.php` which removes all tables and options.

= Is this plugin multisite compatible? =

Basic functionality works on multisite. Each site in the network maintains its own settings and log table (using the site's database prefix). Full multisite network management features are planned for a future Pro release.

== Screenshots ==

1. **Dashboard** — Delivery statistics at a glance with SMTP status indicator.
2. **SMTP Settings** — Full SMTP configuration form with toggle controls and encryption options.
3. **Email Logs** — Searchable, filterable, paginated email delivery log table.
4. **Log Detail** — Full detail view for a single log entry including error messages.
5. **Test Email** — Send a test email and see instant success/failure feedback.
6. **Pro Teasers** — Upsell modal for AI Generator and Automation Pro features.

== Changelog ==

= 1.0.0 =
* Initial release.
* SMTP configuration with TLS/SSL/None encryption support.
* Email logging to a custom indexed database table.
* REST API: settings, logs, send, test email, dashboard stats.
* Admin SPA: Dashboard, SMTP Settings, Email Logs, Test Email.
* Pro teaser pages: AI Generator, Automation.
* Pluggable mailer architecture with Pro hook points.
* Versioned database migration system.
* Full uninstall cleanup.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade required.
