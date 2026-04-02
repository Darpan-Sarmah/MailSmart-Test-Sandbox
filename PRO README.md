# SG MailSmart Pro — Complete Developer Guide

**Version:** 1.0.0  
**Requires Lite:** SG MailSmart AI Lite 1.0.0+  
**Requires PHP:** 7.4+  
**Requires WordPress:** 5.8+

---

## Table of Contents

1. [Introduction & Architecture Philosophy](#1-introduction--architecture-philosophy)
2. [Prerequisites & Environment Setup](#2-prerequisites--environment-setup)
3. [Pro Plugin File Structure](#3-pro-plugin-file-structure)
4. [Main Bootstrap File](#4-main-bootstrap-file)
5. [Dependency & License Verification](#5-dependency--license-verification)
6. [License System Integration](#6-license-system-integration)
7. [The Service Container](#7-the-service-container)
8. [The `mailsmart_loaded` Entry Point](#8-the-mailsmart_loaded-entry-point)
9. [Complete Hook & Filter Reference](#9-complete-hook--filter-reference)
10. [Email Engine Extension](#10-email-engine-extension)
11. [SMTP Provider System](#11-smtp-provider-system)
12. [Logging System Extension](#12-logging-system-extension)
13. [REST API Extension](#13-rest-api-extension)
14. [Admin UI Extension](#14-admin-ui-extension)
15. [Database Extension](#15-database-extension)
16. [Feature Implementation Blueprints](#16-feature-implementation-blueprints)
17. [Global Function API Reference](#17-global-function-api-reference)
18. [Class & Constant Reference](#18-class--constant-reference)
19. [WordPress Options Reference](#19-wordpress-options-reference)
20. [Automatic Update Integration](#20-automatic-update-integration)
21. [Security Checklist](#21-security-checklist)
22. [Testing & Debugging](#22-testing--debugging)
23. [Release & Deployment Checklist](#23-release--deployment-checklist)

---

## 1. Introduction & Architecture Philosophy

### 1.1 What is the Core + Add-on Model?

SG MailSmart is built on a **Core + Add-on architecture**. The Lite plugin (`sg-mailsmart-ai-lite`) ships as the free engine and is always required. All Pro functionality lives in a separate plugin (`sg-mailsmart-pro`) that depends on the Lite plugin to be present and active.

This model gives you:

- **Zero code duplication.** The email engine, logger, database, REST API, and license system are owned entirely by Lite. Pro never reimplements them — it extends them.
- **Clean separation of concerns.** Lite is WordPress.org-safe (no premium code). Pro is distributed through your own server.
- **Upgrade safety.** Users can deactivate Pro without losing their logs, settings, or license data.
- **A single extension surface.** Every Pro feature is wired through documented hooks, filters, and the service container. No monkey-patching, no file modifications.

### 1.2 Extension Mechanisms (in order of preference)

| Mechanism | When to use |
|---|---|
| `mailsmart_loaded` action | One-time bootstrap: rebinding services, registering hooks |
| `apply_filters('mailsmart_*')` | Modifying data in-flight (email content, log entries, stats) |
| `do_action('mailsmart_*')` | Reacting to events (post-send analytics, audit logging) |
| Container rebind | Swapping an entire service (custom mailer driver) |
| REST route registration | Adding new API endpoints for Pro features |
| `mailsmart_admin_menu_pages` filter | Adding Pro admin pages |
| `mailsmart_schema_tables` filter | Registering additional database tables |

### 1.3 The Golden Rule

> **The Pro plugin MUST NOT modify any Lite plugin file, ever.**  
> All extension points are stable, versioned contracts. If you need a hook that doesn't exist, open an issue — never edit Lite directly.

---

## 2. Prerequisites & Environment Setup

### 2.1 Required Software

```text
PHP            7.4 or higher (8.x recommended)
WordPress      5.8 or higher
MySQL/MariaDB  5.6 / 10.0 or higher
Composer       2.x (optional, for autoloading during development)
WP-CLI         2.x (recommended for scaffolding and testing)
```

### 2.2 Local Development Setup

```bash
# Install Lite plugin (required dependency)
wp plugin install sg-mailsmart-ai-lite --activate

# Clone Pro into plugins directory
cd wp-content/plugins/
git clone https://your-repo.com/sg-mailsmart-pro.git

# Activate Pro
wp plugin activate sg-mailsmart-pro

# Verify both are active
wp plugin list --status=active | grep mailsmart
```

### 2.3 Recommended `wp-config.php` for Development

```php
// Enable debug logging (required for SMTP debug output from Lite)
define( 'WP_DEBUG',         true );
define( 'WP_DEBUG_LOG',     true );
define( 'WP_DEBUG_DISPLAY', false );

// Force the license mock so you never need a real license server during dev
define( 'MAILSMART_LICENSE_MOCK', true );

// Optional: point to a local/staging license API
define( 'MAILSMART_LICENSE_API_URL', 'https://staging.sgmailsmart.com/wp-json/mailsmart-license/v1' );
```

### 2.4 Verifying the Integration

After activating both plugins, open your browser console on the SG MailSmart admin page. You should see the `window.MailSmartAdmin` object and `window.mailsmartData` present. From PHP you can verify with:

```php
// In any admin page or WP-CLI command:
var_dump( function_exists('SG_MailSmart') );  // bool(true)
var_dump( mailsmart_is_license_active() );     // bool(false) — until activated
```

---

## 3. Pro Plugin File Structure

The following structure is the recommended layout for the Pro plugin. It mirrors the Lite plugin's conventions so developers can navigate both codebases with the same mental model.

```
sg-mailsmart-pro/
│
├── sg-mailsmart-pro.php               ← Main bootstrap file
├── uninstall.php                      ← Pro-specific data cleanup
├── index.php                          ← Security silence
├── README.txt                         ← WordPress.org-style readme (for your own docs)
│
├── includes/
│   ├── index.php
│   ├── class-mailsmart-pro.php        ← Pro core class (singleton)
│   ├── class-mailsmart-pro-loader.php ← Wraps Lite's loader or registers own hooks
│   └── class-mailsmart-pro-updater.php← Update checker (WordPress Updates API)
│
├── features/
│   ├── index.php
│   │
│   ├── ai/
│   │   ├── index.php
│   │   ├── class-mailsmart-ai-generator.php
│   │   ├── class-mailsmart-ai-provider.php       ← Abstract
│   │   ├── providers/
│   │   │   ├── class-mailsmart-openai-provider.php
│   │   │   └── class-mailsmart-anthropic-provider.php
│   │   └── rest/
│   │       └── class-mailsmart-rest-ai-controller.php
│   │
│   ├── automation/
│   │   ├── index.php
│   │   ├── class-mailsmart-automation-engine.php
│   │   ├── class-mailsmart-automation-rule.php
│   │   ├── class-mailsmart-automation-trigger.php
│   │   ├── triggers/
│   │   │   ├── class-mailsmart-trigger-user-register.php
│   │   │   └── class-mailsmart-trigger-woocommerce.php
│   │   └── rest/
│   │       └── class-mailsmart-rest-automation-controller.php
│   │
│   ├── analytics/
│   │   ├── index.php
│   │   ├── class-mailsmart-analytics-engine.php
│   │   └── rest/
│   │       └── class-mailsmart-rest-analytics-controller.php
│   │
│   └── templates/
│       ├── index.php
│       ├── class-mailsmart-template-engine.php
│       └── rest/
│           └── class-mailsmart-rest-templates-controller.php
│
├── integrations/
│   ├── index.php
│   ├── class-mailsmart-pro-sendgrid-provider.php
│   ├── class-mailsmart-pro-ses-provider.php
│   ├── class-mailsmart-pro-mailgun-provider.php
│   └── class-mailsmart-pro-postmark-provider.php
│
├── database/
│   ├── index.php
│   ├── class-mailsmart-pro-schema.php   ← Registers additional tables
│   └── class-mailsmart-pro-migrator.php
│
├── admin/
│   ├── index.php
│   ├── class-mailsmart-pro-admin.php
│   ├── css/
│   │   └── mailsmart-pro-admin.css
│   └── js/
│       └── mailsmart-pro-admin.js       ← Extends the Lite SPA
│
├── rest/
│   ├── index.php
│   └── class-mailsmart-pro-rest-settings-controller.php
│
└── languages/
    └── sg-mailsmart-pro.pot
```

---

## 4. Main Bootstrap File

This is the entire main plugin file. It is intentionally minimal — all logic belongs in `SG_MailSmart_Pro`.

```php
<?php
/**
 * Plugin Name:       SG MailSmart Pro
 * Plugin URI:        https://sgmailsmart.com/pro
 * Description:       Pro add-on for SG MailSmart AI Lite — unlocks AI email generation,
 *                    automation, analytics, advanced templates, and premium SMTP providers.
 * Version:           1.0.0
 * Author:            SG MailSmart
 * Author URI:        https://sgmailsmart.com
 * License:           Proprietary
 * Text Domain:       sg-mailsmart-pro
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package SG_MailSmart_Pro
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'MAILSMART_PRO_VERSION',       '1.0.0' );
define( 'MAILSMART_PRO_PLUGIN_FILE',   __FILE__ );
define( 'MAILSMART_PRO_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'MAILSMART_PRO_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'MAILSMART_PRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// Dependency check — Lite must be active before we do anything else.
// We hook early (plugins_loaded at priority 1) so we can deactivate cleanly
// if Lite is missing, without causing any PHP fatal errors.
// ---------------------------------------------------------------------------

add_action( 'plugins_loaded', 'sg_mailsmart_pro_check_dependency', 1 );

function sg_mailsmart_pro_check_dependency(): void {
    if ( ! function_exists( 'SG_MailSmart' ) ) {
        add_action( 'admin_notices', 'sg_mailsmart_pro_dependency_notice' );
        add_action( 'admin_init',    'sg_mailsmart_pro_deactivate_self' );
        return;
    }

    // Lite is present. Load the Pro autoloader and boot.
    require_once MAILSMART_PRO_PLUGIN_DIR . 'includes/class-mailsmart-pro.php';
    SG_MailSmart_Pro::instance();
}

function sg_mailsmart_pro_dependency_notice(): void {
    $message = sprintf(
        /* translators: 1: Pro plugin name, 2: Lite plugin name */
        __(
            '<strong>%1$s</strong> requires <strong>%2$s</strong> to be installed and activated.',
            'sg-mailsmart-pro'
        ),
        'SG MailSmart Pro',
        'SG MailSmart AI Lite'
    );

    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        wp_kses_post( $message )
    );
}

function sg_mailsmart_pro_deactivate_self(): void {
    deactivate_plugins( MAILSMART_PRO_PLUGIN_BASENAME );
}
```

---

## 5. Dependency & License Verification

### 5.1 Checking for Lite

Never call `SG_MailSmart()` without first confirming Lite is active. The safest pattern:

```php
// Simple function-exists guard (cheapest check):
if ( ! function_exists( 'SG_MailSmart' ) ) {
    return;
}

// Version-aware guard (when you need a minimum Lite version):
if ( ! defined( 'MAILSMART_VERSION' ) || version_compare( MAILSMART_VERSION, '1.0.0', '<' ) ) {
    // Show an admin notice and bail.
    return;
}
```

### 5.2 Available Constants from Lite

Once Lite is active, the following constants are always available:

```php
MAILSMART_VERSION            // '1.0.0'
MAILSMART_DB_VERSION         // '1.0.0'
MAILSMART_PLUGIN_FILE        // Absolute path to sg-mailsmart-ai-lite.php
MAILSMART_PLUGIN_DIR         // Absolute path with trailing slash
MAILSMART_PLUGIN_URL         // Public URL with trailing slash
MAILSMART_PLUGIN_BASENAME    // 'sg-mailsmart-ai-lite/sg-mailsmart-ai-lite.php'
MAILSMART_REST_NAMESPACE     // 'mailsmart/v1'
```

### 5.3 License Gate Pattern

Every Pro feature **must** check the license before executing. Use the global helper:

```php
// Cheapest check — reads from cached wp_options:
if ( ! mailsmart_is_license_active() ) {
    return; // Silently bail — the UI already shows the upgrade prompt.
}

// For REST endpoints — return a proper error:
if ( ! mailsmart_is_license_active() ) {
    return new WP_Error(
        'pro_license_required',
        __( 'An active SG MailSmart Pro license is required.', 'sg-mailsmart-pro' ),
        [ 'status' => 402 ]
    );
}

// For admin pages — redirect to license page:
if ( ! mailsmart_is_license_active() ) {
    wp_safe_redirect( admin_url( 'admin.php?page=sg-mailsmart-license' ) );
    exit;
}
```

### 5.4 Adding Extra License Validation

The `mailsmart_license_active` filter is your hook to add domain-binding, feature-flag checks, or any custom logic on top of the core status check:

```php
add_filter( 'mailsmart_license_active', 'mailsmart_pro_validate_license', 10, 2 );

function mailsmart_pro_validate_license( bool $is_active, SG_MailSmart_License_Manager $manager ): bool {
    if ( ! $is_active ) {
        return false; // Already invalid — no point checking further.
    }

    // Example: check the expiry date manually (core already checks this,
    // but you could add a grace period here):
    $expiry = $manager->get_expiry();
    if ( $expiry && strtotime( $expiry ) < time() ) {
        return false;
    }

    // Example: domain binding check (compare stored activation domain
    // against the current site URL):
    $activation_domain = get_option( 'mailsmart_pro_activation_domain', '' );
    if ( $activation_domain && parse_url( home_url(), PHP_URL_HOST ) !== $activation_domain ) {
        return false;
    }

    return true;
}
```

---

## 6. License System Integration

### 6.1 How the License System Works

The entire license system lives in Lite. Pro **never** stores license keys independently. Here is the full data flow:

```
User enters key in Admin UI
        ↓
POST /wp-json/mailsmart/v1/license/activate
        ↓
SG_MailSmart_REST_License_Controller::activate()
        ↓
SG_MailSmart_License_Manager::activate( $key )
        ↓
HTTP POST → https://sgmailsmart.com/wp-json/mailsmart-license/v1/activate
  Body: { license_key, site_url, product_id: 'sg-mailsmart-pro' }
  Response: { status: 'valid', expiry: 'YYYY-MM-DD' }
        ↓
Stores in wp_options:
  mailsmart_license_key        = 'XXXX-YYYY-ZZZZ-WWWW'
  mailsmart_license_status     = 'active'
  mailsmart_license_expiry     = '2026-12-31'
  mailsmart_license_last_check = 1704067200
        ↓
Fires: do_action('mailsmart_license_activated', $key, $expiry)
```

### 6.2 License API Endpoints

When you build your license server, it must respond to these three actions via `POST`:

**`/activate`**
```json
Request:  { "license_key": "...", "site_url": "https://...", "product_id": "sg-mailsmart-pro" }
Response: { "status": "valid",   "expiry": "2026-12-31", "activations_left": 4 }
Response: { "status": "invalid", "message": "License key not found." }
Response: { "status": "expired", "expiry": "2020-12-31" }
```

**`/deactivate`**
```json
Request:  { "license_key": "...", "site_url": "https://...", "product_id": "sg-mailsmart-pro" }
Response: { "status": "deactivated", "message": "Activation removed." }
```

**`/check`**
```json
Request:  { "license_key": "...", "site_url": "https://...", "product_id": "sg-mailsmart-pro" }
Response: { "status": "valid",   "expiry": "2026-12-31" }
Response: { "status": "expired", "expiry": "2020-12-31" }
Response: { "status": "invalid", "message": "Key has been revoked." }
```

### 6.3 Pointing to Your License Server

Set the server URL via constant (in `wp-config.php`) or at runtime via filter:

```php
// Via constant (set in wp-config.php or Pro's bootstrap):
define( 'MAILSMART_LICENSE_API_URL', 'https://sgmailsmart.com/wp-json/mailsmart-license/v1' );

// Via filter (more flexible — can be environment-aware):
add_filter( 'mailsmart_license_api_url', function( string $url ): string {
    return 'https://sgmailsmart.com/wp-json/mailsmart-license/v1';
});
```

### 6.4 Reacting to License Events

```php
// Fires after successful activation:
add_action( 'mailsmart_license_activated', function( string $key, string $expiry ): void {
    // Store activation domain for binding:
    update_option( 'mailsmart_pro_activation_domain', parse_url( home_url(), PHP_URL_HOST ), false );
    // Log activation to your own analytics:
    mailsmart_log_debug( 'Pro license activated. Expiry: ' . $expiry, 'pro' );
}, 10, 2 );

// Fires after deactivation:
add_action( 'mailsmart_license_deactivated', function(): void {
    delete_option( 'mailsmart_pro_activation_domain' );
    // Disable any Pro cron events:
    wp_clear_scheduled_hook( 'mailsmart_pro_daily_check' );
});
```

### 6.5 Suppressing the Mock for Testing

The Lite plugin's built-in mock API (which validates any 20+ character key as "valid") is ideal during development. Disable it in production by simply not setting the `MAILSMART_LICENSE_MOCK` constant and ensuring `MAILSMART_LICENSE_API_URL` points to your live server.

```php
// To force mock mode regardless of environment (dev only):
define( 'MAILSMART_LICENSE_MOCK', true );

// To intercept ALL API calls with a custom response (unit testing):
add_filter( 'mailsmart_license_api_response', function( $response, string $action, array $params ) {
    // Return an array to short-circuit the HTTP call entirely.
    if ( $action === 'activate' ) {
        return [ 'status' => 'valid', 'expiry' => '2099-12-31' ];
    }
    return $response; // null = use the real API
}, 10, 3 );
```

---

## 7. The Service Container

Lite uses a lightweight dependency injection container. Pro can read from it, add services to it, and rebind existing services.

### 7.1 Accessing the Container

```php
$container = SG_MailSmart()->container();
```

### 7.2 Reading Existing Services

```php
// Retrieve the logger (the same instance used by the mailer and REST controllers):
$logger = SG_MailSmart()->container()->make( 'logger' );

// Retrieve the mailer:
$mailer = SG_MailSmart()->mailer(); // shorthand for container()->make('mailer')

// Retrieve the license manager:
$lm = SG_MailSmart()->license_manager();

// All registered service identifiers:
$ids = SG_MailSmart()->container()->bound();
// Returns: ['i18n', 'schema', 'migrator', 'logger', 'smtp_mailer', 'mailer',
//           'license_manager', 'rest.settings', 'rest.logs', 'rest.email',
//           'rest.license', 'admin']
```

### 7.3 Registering Pro Services

Register your own services inside the `mailsmart_loaded` action (before `loader->run()` commits hooks):

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {
    $c = $plugin->container();

    // Singleton — one instance for the lifetime of the request:
    $c->singleton( 'pro.ai',        fn( $c ) => new MailSmart_Pro_AI_Generator( $c->make('logger') ) );
    $c->singleton( 'pro.automation',fn( $c ) => new MailSmart_Pro_Automation_Engine( $c->make('logger') ) );
    $c->singleton( 'pro.analytics', fn( $c ) => new MailSmart_Pro_Analytics_Engine() );

    // REST controllers (Pro-specific endpoints):
    $c->singleton( 'rest.pro.ai',        fn( $c ) => new MailSmart_Pro_REST_AI_Controller( $c->make('pro.ai') ) );
    $c->singleton( 'rest.pro.automation',fn( $c ) => new MailSmart_Pro_REST_Automation_Controller( $c->make('pro.automation') ) );
});
```

### 7.4 Rebinding Core Services

To replace an entire Lite service with a Pro implementation:

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {
    // Replace the default wp_mail mailer with a SendGrid API driver:
    $plugin->container()->singleton( 'mailer', function( $c ) {
        return new MailSmart_Pro_SendGrid_Mailer( $c->make('logger') );
    });

    // Replace the license manager with an extended version:
    $plugin->container()->singleton( 'license_manager', function( $c ) {
        return new MailSmart_Pro_License_Manager();
    });
});
```

> **Important:** Rebind services inside `mailsmart_loaded` only — this fires after Lite registers its bindings but before `loader->run()` commits WordPress hooks. Rebinding after that point will have no effect on already-registered callbacks.

---

## 8. The `mailsmart_loaded` Entry Point

`mailsmart_loaded` is the single most important hook for Pro. It fires:
- After all Lite services are registered
- After Lite defines all its WordPress hooks (but before they are committed)
- Before `SG_MailSmart_Loader::run()` executes `add_action()`/`add_filter()` for each registered hook

This means inside `mailsmart_loaded` you can:
1. Rebind services
2. Add new hooks via the loader
3. Register Pro REST routes
4. Register Pro admin menu pages
5. Extend the database schema

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {

    // --- 1. Verify license before enabling Pro features ---
    // Note: do NOT gate the entire callback on license status.
    // Services and hooks for license-gated features should register normally;
    // the license check should happen at runtime (inside the callback itself).
    // This prevents a chicken-and-egg problem where services that manage
    // the license itself fail to load.

    // --- 2. Register Pro services in the container ---
    $plugin->container()->singleton( 'pro.ai', fn( $c ) => new MailSmart_Pro_AI_Generator() );

    // --- 3. Add hooks via the Lite loader ---
    $plugin->loader()->add_action( 'rest_api_init', new MailSmart_Pro_REST_AI_Controller(), 'register_routes' );
    $plugin->loader()->add_filter( 'mailsmart_admin_menu_pages', null, function( array $pages ): array {
        $pages[] = [
            'slug'       => 'sg-mailsmart-pro-ai',
            'title'      => __( 'AI Generator', 'sg-mailsmart-pro' ),
            'menu_title' => __( '✨ AI Generator', 'sg-mailsmart-pro' ),
            'tab'        => 'ai_pro',
            'is_pro'     => false, // set false because Pro is now active
        ];
        return $pages;
    });

    // --- 4. Register for automatic updates ---
    mailsmart_register_for_updates(
        'sg-mailsmart-pro/sg-mailsmart-pro.php',
        MAILSMART_PRO_VERSION,
        'https://sgmailsmart.com/wp-json/mailsmart-updates/v1/sg-mailsmart-pro.json'
    );

}, 10, 1 );
```

---

## 9. Complete Hook & Filter Reference

This is the **authoritative** reference of every hook Lite exposes. None of these will be removed in a minor version update — they are the stable public API.

### 9.1 Actions

#### `mailsmart_loaded`

**When:** After all Lite services are registered, before `loader->run()`.  
**Purpose:** The primary Pro bootstrap entry point.

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {
    // Bootstrap Pro here
}, 10, 1 );
```

| Param | Type | Description |
|---|---|---|
| `$plugin` | `SG_MailSmart_Plugin` | The plugin singleton instance |

---

#### `mailsmart_before_send`

**When:** Inside `SG_MailSmart_Mailer::send()`, before the email is dispatched.  
**Purpose:** Inspect the email (read-only — the DTO is immutable). Use `mailsmart_email_content` to modify it.

```php
add_action( 'mailsmart_before_send', function( SG_MailSmart_Email $email ): void {
    mailsmart_log_debug( 'Sending to: ' . print_r( $email->get_to(), true ), 'pro' );
}, 10, 1 );
```

| Param | Type | Description |
|---|---|---|
| `$email` | `SG_MailSmart_Email` | Immutable email DTO |

---

#### `mailsmart_after_send`

**When:** Inside `SG_MailSmart_Mailer::send()`, after the email attempt completes.  
**Purpose:** Analytics, webhook notifications, failure alerts.

```php
add_action( 'mailsmart_after_send', function( array $result, SG_MailSmart_Email $email ): void {
    if ( ! $result['success'] ) {
        // Fire a Pro alert or increment a failure counter
        MailSmart_Pro_Alerts::handle_send_failure( $email, $result['error'] );
    }
}, 10, 2 );
```

| Param | Type | Description |
|---|---|---|
| `$result` | `array` | `['success', 'status', 'email', 'error', 'log_id']` |
| `$email` | `SG_MailSmart_Email` | The email that was sent |

---

#### `mailsmart_smtp_configured`

**When:** Inside `SG_MailSmart_SMTP_Mailer::configure()`, after PHPMailer is fully configured.  
**Purpose:** Apply extra PHPMailer settings (OAuth2, custom certificates, XOAUTH2 tokens).

```php
add_action( 'mailsmart_smtp_configured', function(
    \PHPMailer\PHPMailer\PHPMailer $phpmailer,
    array $settings
): void {
    // Example: inject OAuth2 token for Gmail
    $phpmailer->AuthType = 'XOAUTH2';
    $phpmailer->setOAuth( new MailSmart_Pro_OAuth2_Provider( $settings ) );
}, 10, 2 );
```

| Param | Type | Description |
|---|---|---|
| `$phpmailer` | `PHPMailer` | Fully configured PHPMailer instance |
| `$settings` | `array` | The applied SMTP settings array |

---

#### `mailsmart_license_activated`

**When:** After a license is successfully activated.  
**Purpose:** Store domain binding, fire analytics events, enable Pro cron jobs.

```php
add_action( 'mailsmart_license_activated', function( string $key, string $expiry ): void {
    update_option( 'mailsmart_pro_activation_domain', parse_url( home_url(), PHP_URL_HOST ), false );
    wp_schedule_event( time(), 'daily', 'mailsmart_pro_license_sync' );
}, 10, 2 );
```

| Param | Type | Description |
|---|---|---|
| `$key` | `string` | The raw (unmasked) license key |
| `$expiry` | `string` | Expiry date in `YYYY-MM-DD` format |

---

#### `mailsmart_license_deactivated`

**When:** After a license is deactivated and local data is cleared.  
**Purpose:** Clean up Pro-specific options, cancel cron events.

```php
add_action( 'mailsmart_license_deactivated', function(): void {
    delete_option( 'mailsmart_pro_activation_domain' );
    wp_clear_scheduled_hook( 'mailsmart_pro_license_sync' );
});
```

---

#### `mailsmart_logs_purged`

**When:** After old log entries are deleted by the retention purge.  
**Purpose:** Sync purge events to external analytics or audit logs.

```php
add_action( 'mailsmart_logs_purged', function( int $deleted, int $retention_days ): void {
    mailsmart_log_debug( "Purged {$deleted} log entries (>{$retention_days} days old)", 'pro' );
}, 10, 2 );
```

---

#### `mailsmart_logs_cleared`

**When:** After all logs are deleted via the REST API (bulk clear).

```php
add_action( 'mailsmart_logs_cleared', function( WP_REST_Request $request ): void {
    // Notify analytics that logs were manually cleared
});
```

---

#### `mailsmart_log_deleted`

**When:** After a single log entry is deleted via the REST API.

```php
add_action( 'mailsmart_log_deleted', function( int $id, WP_REST_Request $request ): void {
    // Sync deletion to external logging system
}, 10, 2 );
```

---

#### `mailsmart_settings_saved`

**When:** After settings are successfully saved via `POST /mailsmart/v1/settings`.  
**Purpose:** Invalidate caches, trigger re-sync, notify external services.

```php
add_action( 'mailsmart_settings_saved', function( array $body, WP_REST_Request $request ): void {
    if ( isset( $body['smtp'] ) ) {
        // SMTP changed — invalidate Pro's SMTP test cache
        delete_transient( 'mailsmart_pro_smtp_test_result' );
    }
}, 10, 2 );
```

---

#### `mailsmart_settings_save`

**When:** Inside `SG_MailSmart_REST_Settings_Controller::update_settings()`, during the save process.  
**Purpose:** Save Pro-specific settings groups from the request body.

```php
add_action( 'mailsmart_settings_save', function( array $body, array &$errors, WP_REST_Request $request ): void {
    if ( ! array_key_exists( 'pro', $body ) || ! is_array( $body['pro'] ) ) {
        return;
    }

    $sanitised = MailSmart_Pro_Settings::sanitize( $body['pro'] );
    if ( is_wp_error( $sanitised ) ) {
        $errors[] = $sanitised->get_error_message();
        return;
    }

    update_option( 'mailsmart_pro_settings', $sanitised, false );
}, 10, 3 );
```

> Note: `$errors` is passed by reference via `do_action_ref_array()` — append to it to block the save.

---

#### `mailsmart_admin_page_hook`

**When:** Just before the admin app shell HTML is output.  
**Purpose:** Output extra data attributes, hidden fields, or inline scripts.

```php
add_action( 'mailsmart_admin_page_hook', function(): void {
    $pro_config = wp_json_encode( MailSmart_Pro_Admin::get_js_config() );
    echo '<script>window.mailsmartProConfig = ' . wp_kses_post( $pro_config ) . ';</script>';
});
```

---

#### `mailsmart_deactivated`

**When:** When the **Lite** plugin is deactivated.  
**Purpose:** Clean up Pro temporary data that depends on Lite being active.

```php
add_action( 'mailsmart_deactivated', function(): void {
    // This fires because Pro registered this hook via the Lite loader.
    // The Pro plugin itself will be auto-deactivated by WordPress shortly after.
});
```

---

### 9.2 Filters

#### `mailsmart_email_content`

**When:** Inside `SG_MailSmart_Mailer::send()`, after `mailsmart_before_send`.  
**Purpose:** **AI content injection**, template wrapping, signature appending, unsubscribe footer.

```php
add_filter( 'mailsmart_email_content', function( string $content, SG_MailSmart_Email $email ): string {
    if ( ! mailsmart_is_license_active() ) {
        return $content;
    }

    // Wrap in Pro HTML template:
    return MailSmart_Pro_Template_Engine::wrap( $content, $email );
}, 10, 2 );
```

| Param | Type | Description |
|---|---|---|
| `$content` | `string` | The raw message body (HTML or plain text) |
| `$email` | `SG_MailSmart_Email` | The email DTO (immutable reference) |

**Return:** Modified message body string.

---

#### `mailsmart_mailer`

**When:** Inside `SG_MailSmart_Mailer::send()`, just before dispatching.  
**Purpose:** **Swap the entire send callable** to use a different delivery driver (SendGrid API, SES SDK, etc.).

```php
add_filter( 'mailsmart_mailer', function( callable $default_send_fn, SG_MailSmart_Email $email ): callable {
    if ( ! mailsmart_is_license_active() ) {
        return $default_send_fn; // Fall back to wp_mail
    }

    $provider = MailSmart_Pro_Provider_Registry::get_active_provider();
    if ( ! $provider || ! $provider->is_configured() ) {
        return $default_send_fn;
    }

    // Return a new callable that uses the Pro provider:
    return function( SG_MailSmart_Email $email ) use ( $provider ): bool {
        return $provider->send( $email );
    };
}, 10, 2 );
```

| Param | Type | Description |
|---|---|---|
| `$send_fn` | `callable` | Default `wp_mail` wrapper callable |
| `$email` | `SG_MailSmart_Email` | The email about to be sent |

**Return:** A callable with signature `function(SG_MailSmart_Email): bool`.

---

#### `mailsmart_smtp_settings`

**When:** Inside `SG_MailSmart_SMTP_Mailer::configure()`, before settings are applied to PHPMailer.  
**Purpose:** Modify SMTP settings at runtime (e.g., swap credentials per-email based on recipient domain).

```php
add_filter( 'mailsmart_smtp_settings', function( array $settings, $phpmailer ): array {
    // Example: use a different SMTP account for transactional vs. marketing emails:
    $current_type = MailSmart_Pro_Email_Router::get_current_type();
    if ( $current_type === 'marketing' ) {
        $settings['host']     = get_option( 'mailsmart_pro_marketing_smtp_host' );
        $settings['username'] = get_option( 'mailsmart_pro_marketing_smtp_user' );
        $settings['password'] = get_option( 'mailsmart_pro_marketing_smtp_pass' );
    }
    return $settings;
}, 10, 2 );
```

---

#### `mailsmart_log_entry`

**When:** Inside `SG_MailSmart_Logger::log()`, before the row is inserted.  
**Purpose:** Append custom columns, modify values, or suppress specific log entries.

```php
add_filter( 'mailsmart_log_entry', function( $data, SG_MailSmart_Email $email ) {
    if ( false === $data ) {
        return false; // Already suppressed by another filter — pass through.
    }

    // Add a Pro-specific column (requires extending the table schema):
    $data['email_type'] = MailSmart_Pro_Email_Tagger::classify( $email );

    // Suppress logging for internal system emails:
    $to = is_array( $email->get_to() ) ? $email->get_to()[0] : $email->get_to();
    if ( str_ends_with( $to, '@system.internal' ) ) {
        return false; // Returning false suppresses this log entry entirely.
    }

    return $data;
}, 10, 2 );
```

| Return | Effect |
|---|---|
| `array` | Modified log data — will be inserted |
| `false` | Suppresses the log entry entirely |

---

#### `mailsmart_log_stats`

**When:** Inside `SG_MailSmart_Logger::get_stats()`.  
**Purpose:** Append Pro stats (open rate, click rate, bounce rate) to the dashboard payload.

```php
add_filter( 'mailsmart_log_stats', function( array $stats, int $days ): array {
    if ( ! mailsmart_is_license_active() ) {
        return $stats;
    }

    $pro_stats = MailSmart_Pro_Analytics_Engine::get_stats( $days );
    return array_merge( $stats, $pro_stats );
}, 10, 2 );
```

---

#### `mailsmart_dashboard_stats`

**When:** Inside `SG_MailSmart_REST_Logs_Controller::get_dashboard_stats()`.  
**Purpose:** Extend the REST payload returned by `GET /dashboard`.

```php
add_filter( 'mailsmart_dashboard_stats', function( array $payload, WP_REST_Request $request ): array {
    $payload['open_rate']  = MailSmart_Pro_Analytics_Engine::get_open_rate();
    $payload['click_rate'] = MailSmart_Pro_Analytics_Engine::get_click_rate();
    $payload['pro_active'] = true;
    return $payload;
}, 10, 2 );
```

---

#### `mailsmart_rest_settings_response`

**When:** Inside `SG_MailSmart_REST_Settings_Controller::get_settings()`.  
**Purpose:** Append Pro settings groups to the `GET /settings` REST response.

```php
add_filter( 'mailsmart_rest_settings_response', function( array $data, WP_REST_Request $request ): array {
    $pro_settings = get_option( 'mailsmart_pro_settings', [] );
    // Mask sensitive fields before returning:
    if ( ! empty( $pro_settings['api_key'] ) ) {
        $pro_settings['api_key'] = '••••••••';
    }
    $data['pro'] = $pro_settings;
    return $data;
}, 10, 2 );
```

---

#### `mailsmart_settings_schema`

**When:** Inside `SG_MailSmart_REST_Settings_Controller::get_public_item_schema()`.  
**Purpose:** Document Pro settings fields in the REST API schema.

```php
add_filter( 'mailsmart_settings_schema', function( array $schema ): array {
    $schema['properties']['pro'] = [
        'type'        => 'object',
        'description' => 'SG MailSmart Pro settings.',
        'properties'  => [
            'ai_provider' => [ 'type' => 'string', 'enum' => ['openai', 'anthropic'] ],
            'api_key'     => [ 'type' => 'string',  'description' => 'AI API key (write-only, returned masked).' ],
        ],
    ];
    return $schema;
});
```

---

#### `mailsmart_before_save_smtp_settings`

**When:** Just before SMTP settings are written to `wp_options`.  
**Purpose:** Final transformation or validation of SMTP data before storage.

```php
add_filter( 'mailsmart_before_save_smtp_settings', function( array $sanitised, array $input ): array {
    // Example: encrypt the password before storage
    if ( ! empty( $sanitised['password'] ) ) {
        $sanitised['password'] = MailSmart_Pro_Encryption::encrypt( $sanitised['password'] );
    }
    return $sanitised;
}, 10, 2 );
```

---

#### `mailsmart_before_save_general_settings`

**When:** Just before general settings are written to `wp_options`.

```php
add_filter( 'mailsmart_before_save_general_settings', function( array $sanitised, array $input ): array {
    return $sanitised;
}, 10, 2 );
```

---

#### `mailsmart_rest_permission`

**When:** Inside `SG_MailSmart_REST_Controller::admin_permissions_check()`.  
**Purpose:** Implement role-based access control beyond the standard `manage_options` check.

```php
add_filter( 'mailsmart_rest_permission', function( $can_access, WP_REST_Request $request ) {
    // Allow email managers (custom capability) to access log endpoints:
    $route = $request->get_route();
    if ( str_contains( $route, '/logs' ) && current_user_can( 'mailsmart_view_logs' ) ) {
        return true;
    }
    return $can_access;
}, 10, 2 );
```

---

#### `mailsmart_admin_menu_pages`

**When:** Inside `SG_MailSmart_Admin::get_sub_pages()`.  
**Purpose:** Add Pro sub-pages to the admin sidebar.

```php
add_filter( 'mailsmart_admin_menu_pages', function( array $pages ): array {
    if ( ! mailsmart_is_license_active() ) {
        return $pages; // Keep the free teasers
    }

    // Replace the teaser pages with real Pro pages:
    foreach ( $pages as &$page ) {
        if ( $page['slug'] === 'sg-mailsmart-ai' ) {
            $page['menu_title'] = __( '✨ AI Generator', 'sg-mailsmart-pro' );
            $page['is_pro']     = false; // Remove the lock
        }
        if ( $page['slug'] === 'sg-mailsmart-automation' ) {
            $page['menu_title'] = __( '⚡ Automation', 'sg-mailsmart-pro' );
            $page['is_pro']     = false;
        }
    }

    return $pages;
}, 10, 1 );
```

---

#### `mailsmart_admin_js_data`

**When:** Inside `SG_MailSmart_Admin::enqueue_assets()`.  
**Purpose:** Inject Pro configuration, feature flags, and i18n strings into the admin SPA.

```php
add_filter( 'mailsmart_admin_js_data', function( array $data, string $hook_suffix ): array {
    $data['proFeatures'] = [
        'aiGenerator' => mailsmart_is_license_active(),
        'automation'  => mailsmart_is_license_active(),
        'templates'   => mailsmart_is_license_active(),
        'analytics'   => mailsmart_is_license_active(),
    ];

    $data['proRestUrl'] = rest_url( 'mailsmart-pro/v1' );

    $data['i18n'] = array_merge( $data['i18n'], [
        'ai_generate_btn'   => __( 'Generate with AI', 'sg-mailsmart-pro' ),
        'ai_generating'     => __( 'Generating…',      'sg-mailsmart-pro' ),
        'analytics_title'   => __( 'Analytics',         'sg-mailsmart-pro' ),
    ]);

    return $data;
}, 10, 2 );
```

---

#### `mailsmart_schema_tables`

**When:** Inside `SG_MailSmart_Schema::get_table_definitions()`.  
**Purpose:** Register additional database tables for Pro features.

```php
add_filter( 'mailsmart_schema_tables', function( array $definitions ): array {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $definitions['automation_rules'] = [
        'version' => '1.0.0',
        'sql'     => "CREATE TABLE {$wpdb->prefix}mailsmart_automation_rules (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(200)        NOT NULL DEFAULT '',
  trigger_event VARCHAR(100)        NOT NULL DEFAULT '',
  conditions    LONGTEXT            NOT NULL,
  actions       LONGTEXT            NOT NULL,
  status        VARCHAR(20)         NOT NULL DEFAULT 'active',
  created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY trigger_event (trigger_event)
) ENGINE=InnoDB {$charset_collate};",
    ];

    return $definitions;
});
```

---

#### `mailsmart_logging_enabled`

**When:** Inside `SG_MailSmart_Logger::is_logging_enabled()`.  
**Purpose:** Conditionally disable logging (e.g., on high-volume transactional sending, staging sites).

```php
add_filter( 'mailsmart_logging_enabled', function( bool $enabled ): bool {
    // Disable logging during bulk send operations to avoid table locks:
    if ( defined( 'MAILSMART_PRO_BULK_SEND' ) && MAILSMART_PRO_BULK_SEND ) {
        return false;
    }
    return $enabled;
});
```

---

#### `mailsmart_manager_capability`

**When:** Inside `mailsmart_current_user_can_manage()`.  
**Purpose:** Change the WordPress capability required to access plugin management.

```php
add_filter( 'mailsmart_manager_capability', function( string $cap ): string {
    return 'manage_mailsmart'; // Custom capability
});
```

---

#### `mailsmart_license_active`

**When:** Inside `SG_MailSmart_License_Manager::is_active()`.  
**Purpose:** The final gate for Pro feature access — apply custom validation logic.

```php
add_filter( 'mailsmart_license_active', function( bool $is_active, SG_MailSmart_License_Manager $lm ): bool {
    if ( ! $is_active ) {
        return false;
    }
    // Add a grace period of 3 days past expiry:
    $expiry = $lm->get_expiry();
    if ( $expiry ) {
        $grace_period_end = strtotime( $expiry . ' + 3 days' );
        if ( time() > $grace_period_end ) {
            return false;
        }
    }
    return true;
}, 10, 2 );
```

---

#### `mailsmart_license_api_response`

**When:** At the start of `SG_MailSmart_License_Manager::call_api()`.  
**Purpose:** Short-circuit all API calls — for unit tests, staging environments, or custom validation.

```php
add_filter( 'mailsmart_license_api_response', function( $response, string $action, array $params ) {
    // Return null to use the real API (default behaviour):
    if ( ! defined( 'MAILSMART_LICENSE_MOCK' ) ) {
        return null;
    }
    // Return an array to skip the HTTP call entirely:
    return [ 'status' => 'valid', 'expiry' => '2099-12-31' ];
}, 10, 3 );
```

---

#### `mailsmart_license_api_url`

**When:** Inside `SG_MailSmart_License_Manager::call_api()`.  
**Purpose:** Point to a different license server (staging, self-hosted).

```php
add_filter( 'mailsmart_license_api_url', function( string $url ): string {
    return 'https://your-license-server.com/wp-json/license/v1';
});
```

---

#### `mailsmart_provider_settings_before_save`

**When:** Inside `SG_MailSmart_SMTP_Provider::save_settings()`.  
**Purpose:** Modify provider settings before they are persisted.

---

#### `mailsmart_registered_updatable_plugins`

**When:** Inside `mailsmart_register_for_updates()`.  
**Purpose:** Read the list of plugins registered for MailSmart-managed updates.

---

## 10. Email Engine Extension

### 10.1 Wrapping with an HTML Template

```php
// features/templates/class-mailsmart-template-engine.php

class MailSmart_Pro_Template_Engine {

    public static function init(): void {
        add_filter( 'mailsmart_email_content', [ self::class, 'apply_template' ], 10, 2 );
    }

    public static function apply_template( string $content, SG_MailSmart_Email $email ): string {
        if ( ! mailsmart_is_license_active() ) {
            return $content;
        }

        // Don't wrap plain-text emails:
        if ( $email->get_content_type() === SG_MailSmart_Email::CONTENT_TYPE_PLAIN ) {
            return $content;
        }

        $template_id = apply_filters( 'mailsmart_pro_active_template_id', null, $email );
        if ( ! $template_id ) {
            return $content; // No template assigned — send raw.
        }

        $template = self::get_template( $template_id );
        if ( ! $template ) {
            return $content;
        }

        // Replace the {{content}} placeholder in the template:
        return str_replace( '{{content}}', $content, $template );
    }

    private static function get_template( int $id ): ?string {
        global $wpdb;
        $table = $wpdb->prefix . 'mailsmart_templates';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_var( $wpdb->prepare( "SELECT html FROM {$table} WHERE id = %d", $id ) );
    }
}
```

### 10.2 Building a Custom Delivery Driver (e.g., SendGrid)

```php
// integrations/class-mailsmart-pro-sendgrid-provider.php

class MailSmart_Pro_SendGrid_Provider extends SG_MailSmart_SMTP_Provider {

    public function get_slug(): string        { return 'sendgrid'; }
    public function get_name(): string        { return 'SendGrid'; }
    public function get_description(): string { return 'Send via the SendGrid Web API v3.'; }
    public function get_option_key(): string  { return 'mailsmart_pro_sendgrid_settings'; }
    public function get_capabilities(): int   { return self::CAPABILITY_API | self::CAPABILITY_ATTACHMENTS; }

    public function get_default_settings(): array {
        return [
            'api_key'    => '',
            'from_email' => '',
            'from_name'  => '',
        ];
    }

    public function is_configured(): bool {
        return ! empty( $this->settings['api_key'] );
    }

    public function send( SG_MailSmart_Email $email ): bool {
        $this->clear_last_error();

        $api_key = $this->get_setting( 'api_key' );

        $payload = [
            'personalizations' => [[
                'to' => $this->normalise_recipients( $email->get_to() ),
            ]],
            'from'    => [ 'email' => $email->get_from_email(), 'name' => $email->get_from_name() ],
            'subject' => $email->get_subject(),
            'content' => [[
                'type'  => $email->get_content_type(),
                'value' => $email->get_message(),
            ]],
        ];

        $response = wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ]);

        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 202 ) {
            $body            = json_decode( wp_remote_retrieve_body( $response ), true );
            $this->last_error = 'SendGrid API returned HTTP ' . $code . ': '
                . ( $body['errors'][0]['message'] ?? 'Unknown error' );
            return false;
        }

        return true;
    }

    private function normalise_recipients( $to ): array {
        $to = is_array( $to ) ? $to : [ $to ];
        return array_map( fn( $address ) => [ 'email' => $address ], $to );
    }
}
```

### 10.3 Wiring the Custom Driver

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {
    // Register the SendGrid provider as the active mailer:
    add_filter( 'mailsmart_mailer', function( callable $default, SG_MailSmart_Email $email ) use ( $plugin ): callable {
        if ( ! mailsmart_is_license_active() ) {
            return $default;
        }

        $provider = new MailSmart_Pro_SendGrid_Provider();
        if ( ! $provider->is_configured() ) {
            return $default; // Graceful fallback to wp_mail
        }

        return function( SG_MailSmart_Email $email ) use ( $provider ): bool {
            return $provider->send( $email );
        };
    }, 10, 2 );
});
```

---

## 11. SMTP Provider System

The `SG_MailSmart_SMTP_Provider` abstract class defines the contract all Pro SMTP providers must implement. Here are the abstract methods you **must** implement:

| Method | Returns | Purpose |
|---|---|---|
| `get_slug()` | `string` | Unique machine-readable identifier (e.g., `'sendgrid'`) |
| `get_name()` | `string` | Human-readable display name |
| `get_description()` | `string` | Short subtitle for the provider card |
| `get_option_key()` | `string` | `wp_options` key for this provider's settings |
| `get_default_settings()` | `array` | Default values (merged with stored settings on construction) |
| `is_configured()` | `bool` | True when required fields (API key, host) are populated |
| `get_capabilities()` | `int` | Bitmask of `CAPABILITY_*` constants |

### Available Capability Constants

```php
SG_MailSmart_SMTP_Provider::CAPABILITY_SMTP        // 1  — Configures PHPMailer SMTP transport
SG_MailSmart_SMTP_Provider::CAPABILITY_API         // 2  — Uses a direct HTTP API (bypasses PHPMailer)
SG_MailSmart_SMTP_Provider::CAPABILITY_OAUTH       // 4  — Supports OAuth2 authentication
SG_MailSmart_SMTP_Provider::CAPABILITY_ATTACHMENTS // 8  — Supports attachments via API
```

### Overridable Methods (optional)

| Method | Default | Override when |
|---|---|---|
| `configure_phpmailer($phpmailer)` | No-op | Provider uses SMTP transport |
| `send($email)` | Returns false | Provider uses HTTP API |
| `test_connection()` | Returns `true` | Provider has a testable ping endpoint |
| `get_settings_fields()` | Empty array | To render dynamic settings fields in the admin UI |

---

## 12. Logging System Extension

### 12.1 Adding Custom Columns

To add a custom column (e.g., `email_type`) to the log table:

**Step 1 — Register the table schema extension:**

```php
add_filter( 'mailsmart_schema_tables', function( array $definitions ): array {
    // If you are extending the existing email_logs table rather than adding a new one,
    // your migration must ALTER the table. dbDelta handles ADD COLUMN idempotently.
    // The cleanest approach is to add your column in your own migration class.
    return $definitions;
});
```

**Step 2 — Run a migration to add the column:**

```php
// features/database/class-mailsmart-pro-migrator.php

class MailSmart_Pro_Migrator {

    public static function run(): void {
        $db_version = get_option( 'mailsmart_pro_db_version', '0' );

        if ( version_compare( $db_version, '1.0.0', '<' ) ) {
            self::migrate_1_0_0();
            update_option( 'mailsmart_pro_db_version', '1.0.0', false );
        }
    }

    private static function migrate_1_0_0(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mailsmart_email_logs';

        // dbDelta-safe: check column existence before adding
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$table}` LIKE 'email_type'"
        );

        if ( empty( $column_exists ) ) {
            $wpdb->query(
                "ALTER TABLE `{$table}` ADD COLUMN `email_type` VARCHAR(50) NOT NULL DEFAULT 'transactional' AFTER `mailer`"
            );
        }
    }
}
```

**Step 3 — Call the migrator on plugin activation:**

```php
register_activation_hook( MAILSMART_PRO_PLUGIN_FILE, function(): void {
    MailSmart_Pro_Migrator::run();
});
```

**Step 4 — Populate the column via the filter:**

```php
add_filter( 'mailsmart_log_entry', function( $data, SG_MailSmart_Email $email ) {
    if ( false === $data ) return false;
    $data['email_type'] = MailSmart_Pro_Email_Tagger::classify( $email );
    return $data;
}, 10, 2 );
```

### 12.2 Querying the Logger Directly

```php
$logger = SG_MailSmart()->logger();

// Paginated query:
$result = $logger->get_logs([
    'page'     => 1,
    'per_page' => 50,
    'status'   => 'failed',        // 'sent' | 'failed' | 'pending' | ''
    'search'   => 'user@example',  // matches recipient + subject
    'orderby'  => 'created_at',    // id | created_at | recipient | subject | status | mailer
    'order'    => 'DESC',
]);

// $result = [
//   'items'       => [ [...], [...] ],
//   'total'       => 42,
//   'page'        => 1,
//   'per_page'    => 50,
//   'total_pages' => 1,
// ]

// Single log entry (includes full message body):
$log = $logger->get_log( 42 );

// Statistics (for a custom dashboard widget):
$stats = $logger->get_stats( 30 ); // Last 30 days
// $stats = [ 'total' => 1200, 'sent' => 1150, 'failed' => 50, 'success_rate' => 95.8, ... ]

// Manual log write (for Pro-initiated sends that bypass the core mailer):
$log_id = $logger->log( $email, SG_MailSmart_Logger::STATUS_SENT, null, 'sendgrid' );
```

### 12.3 Retention & Purging

```php
// Trigger a manual purge (respects the configured retention days):
$deleted_count = SG_MailSmart()->logger()->purge_old_logs();

// Schedule automatic purging (register in mailsmart_loaded):
if ( ! wp_next_scheduled( 'mailsmart_pro_purge_logs' ) ) {
    wp_schedule_event( time(), 'daily', 'mailsmart_pro_purge_logs' );
}
add_action( 'mailsmart_pro_purge_logs', function(): void {
    SG_MailSmart()->logger()->purge_old_logs();
});
```

---

## 13. REST API Extension

### 13.1 Registering Pro-Specific Endpoints

Pro uses its own REST namespace (`mailsmart-pro/v1`) to avoid conflicts with Lite's `mailsmart/v1` namespace.

```php
// rest/class-mailsmart-pro-rest-ai-controller.php

class MailSmart_Pro_REST_AI_Controller extends SG_MailSmart_REST_Controller {

    // Override the namespace to use the Pro-specific one:
    protected string $namespace = 'mailsmart-pro/v1';
    protected string $rest_base = 'ai';

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/generate', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'generate_content' ],
                'permission_callback' => [ $this, 'admin_permissions_check' ],
                'args'                => [
                    'prompt' => [
                        'required'          => true,
                        'type'              => 'string',
                        'minLength'         => 10,
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                    'type' => [
                        'required'          => false,
                        'type'              => 'string',
                        'enum'              => [ 'subject', 'body', 'full' ],
                        'default'           => 'full',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);
    }

    public function generate_content( WP_REST_Request $request ): WP_REST_Response {
        if ( ! mailsmart_is_license_active() ) {
            return $this->error_response( 'pro_license_required', __( 'An active Pro license is required.', 'sg-mailsmart-pro' ), 402 );
        }

        $prompt = $request->get_param( 'prompt' );
        $type   = $request->get_param( 'type' );

        $generator = SG_MailSmart()->container()->make( 'pro.ai' );
        $result    = $generator->generate( $prompt, $type );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        return $this->success_response( [ 'content' => $result ] );
    }
}
```

**Register it in `mailsmart_loaded`:**

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {
    $plugin->loader()->add_action(
        'rest_api_init',
        new MailSmart_Pro_REST_AI_Controller(),
        'register_routes'
    );
});
```

### 13.2 All REST Response Methods (from base controller)

These are inherited by all Pro controllers that extend `SG_MailSmart_REST_Controller`:

```php
// Success response:
$this->success_response( $data, $meta = [], $message = '', $status_code = 200 );

// Error response:
$this->error_response( $code, $message, $status_code = 400, $extra = [] );

// Shorthand aliases:
$this->success( $data, $status_code = 200 );
$this->error( $code, $message, $status_code = 400, $extra = [] );
$this->not_found( $message = '' );
$this->validation_error( $message, $errors = [] );
$this->paginated( $items, $total, $page, $per_page, $total_pages );

// Permission callback (already handles manage_options + mailsmart_rest_permission filter):
$this->admin_permissions_check( WP_REST_Request $request );
```

---

## 14. Admin UI Extension

### 14.1 Architecture

The Lite admin UI is a vanilla JavaScript SPA housed in `admin/js/mailsmart-admin.js`. It exposes `window.MailSmartAdmin` globally for Pro to extend.

Pro injects its own JavaScript file **after** the Lite script, then extends the SPA using the `mailsmart_admin_js_data` filter for data and `mailsmart_admin_page_hook` action for inline scripts.

### 14.2 Loading Pro Assets

```php
// In your Pro admin class, hook into admin_enqueue_scripts:
add_action( 'admin_enqueue_scripts', function( string $hook_suffix ): void {
    // Only load on MailSmart pages (check for the 'sg-mailsmart' string in the hook):
    if ( false === strpos( $hook_suffix, 'sg-mailsmart' ) ) {
        return;
    }

    wp_enqueue_script(
        'sg-mailsmart-pro-admin',
        MAILSMART_PRO_PLUGIN_URL . 'admin/js/mailsmart-pro-admin.js',
        [ 'sg-mailsmart-admin' ], // Depends on the Lite admin script
        MAILSMART_PRO_VERSION,
        true  // Load in footer
    );

    wp_enqueue_style(
        'sg-mailsmart-pro-admin',
        MAILSMART_PRO_PLUGIN_URL . 'admin/css/mailsmart-pro-admin.css',
        [ 'sg-mailsmart-admin' ],
        MAILSMART_PRO_VERSION
    );
}, 20 ); // Priority 20 — after Lite enqueues at default priority 10
```

### 14.3 Extending the SPA

The Lite SPA exposes `window.MailSmartAdmin`. Pro's JS extends it:

```javascript
// admin/js/mailsmart-pro-admin.js

(function () {
    "use strict";

    // Guard — wait for the Lite SPA to be available
    if (typeof window.MailSmartAdmin === "undefined") {
        console.error("[SG MailSmart Pro] MailSmartAdmin not found. Is Lite active?");
        return;
    }

    const MailSmart = window.MailSmartAdmin;
    const ProConfig = window.mailsmartProConfig || {};

    // =========================================================================
    // Extend the Router — add Pro tab handlers
    // =========================================================================

    const originalNavigate = MailSmart.Router.navigate.bind(MailSmart.Router);

    MailSmart.Router.navigate = function (tab, silent) {
        const proPages = {
            ai_pro:         MailSmartPro.AI.render,
            automation_pro: MailSmartPro.Automation.render,
            analytics:      MailSmartPro.Analytics.render,
            templates:      MailSmartPro.Templates.render,
        };

        if (proPages[tab]) {
            MailSmart.Router.currentTab = tab;
            document.querySelectorAll(".ms-nav-link").forEach(function (el) {
                el.classList.toggle("is-active", el.dataset.tab === tab);
            });
            const content = document.getElementById("ms-content");
            if (content) {
                content.innerHTML = MailSmart.Templates.pageLoader();
                setTimeout(function () { proPages[tab](); }, silent ? 0 : 80);
            }
            return;
        }

        // Fall through to Lite's handler for core tabs:
        originalNavigate(tab, silent);
    };

    // =========================================================================
    // Pro API namespace
    // =========================================================================

    const MailSmartPro = {
        config: ProConfig,

        Api: {
            url: function (path) {
                const base = (window.mailsmartData.proRestUrl || "").replace(/\/$/, "");
                return base + "/" + path.replace(/^\//, "");
            },
            post: function (path, body) {
                return MailSmart.Api.request("POST", path, body, { useProUrl: true });
            },
        },

        AI: {
            render: async function () {
                const content = document.getElementById("ms-content");
                if (!content) return;
                content.innerHTML = "<p>AI Generator coming soon...</p>";
            },
        },

        Automation: {
            render: async function () {
                const content = document.getElementById("ms-content");
                if (!content) return;
                content.innerHTML = "<p>Automation Builder coming soon...</p>";
            },
        },

        Analytics: {
            render: async function () {
                // Fetch Pro analytics from REST and render
            },
        },

        Templates: {
            render: async function () {
                // Render the template builder
            },
        },
    };

    // Expose Pro namespace globally for debugging
    window.MailSmartPro = MailSmartPro;

}());
```

---

## 15. Database Extension

### 15.1 Recommended Pro Tables

Here is the full set of tables recommended for the Pro feature set:

```sql
-- AI Generation History
{prefix}mailsmart_ai_history (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt       TEXT NOT NULL,
  result       LONGTEXT NOT NULL,
  provider     VARCHAR(50) NOT NULL DEFAULT 'openai',
  tokens_used  INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY provider (provider),
  KEY created_at (created_at)
)

-- Automation Rules
{prefix}mailsmart_automation_rules (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200) NOT NULL,
  trigger_event VARCHAR(100) NOT NULL,
  conditions    LONGTEXT NOT NULL,   -- JSON
  actions       LONGTEXT NOT NULL,   -- JSON
  status        VARCHAR(20) NOT NULL DEFAULT 'active',
  run_count     INT UNSIGNED NOT NULL DEFAULT 0,
  last_run_at   DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY status (status),
  KEY trigger_event (trigger_event)
)

-- Automation Queue
{prefix}mailsmart_automation_queue (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_id      BIGINT UNSIGNED NOT NULL,
  recipient    VARCHAR(320) NOT NULL,
  payload      LONGTEXT NOT NULL,   -- JSON
  status       VARCHAR(20) NOT NULL DEFAULT 'pending',
  scheduled_at DATETIME NOT NULL,
  sent_at      DATETIME DEFAULT NULL,
  error        TEXT DEFAULT NULL,
  KEY rule_id (rule_id),
  KEY status (status),
  KEY scheduled_at (scheduled_at)
)

-- Email Templates
{prefix}mailsmart_templates (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(200) NOT NULL,
  category   VARCHAR(100) NOT NULL DEFAULT 'general',
  html       LONGTEXT NOT NULL,
  thumbnail  VARCHAR(500) DEFAULT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY category (category)
)

-- Analytics Events (open/click tracking)
{prefix}mailsmart_analytics_events (
  id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id   BIGINT UNSIGNED NOT NULL,  -- FK to mailsmart_email_logs.id
  event    VARCHAR(50) NOT NULL,       -- 'open' | 'click' | 'bounce' | 'unsubscribe'
  url      VARCHAR(2083) DEFAULT NULL, -- For click events
  ip       VARCHAR(45) DEFAULT NULL,
  ua       VARCHAR(500) DEFAULT NULL,
  fired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY log_id (log_id),
  KEY event (event),
  KEY fired_at (fired_at)
)
```

### 15.2 Complete Schema Registration

```php
add_filter( 'mailsmart_schema_tables', function( array $defs ): array {
    global $wpdb;
    $cc = $wpdb->get_charset_collate();
    $p  = $wpdb->prefix;

    $defs['templates'] = [
        'version' => '1.0.0',
        'sql'     => "CREATE TABLE {$p}mailsmart_templates (
  id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(200)        NOT NULL DEFAULT '',
  category   VARCHAR(100)        NOT NULL DEFAULT 'general',
  html       LONGTEXT            NOT NULL,
  thumbnail  VARCHAR(500)                 DEFAULT NULL,
  is_default TINYINT(1)          NOT NULL DEFAULT 0,
  created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY category (category)
) ENGINE=InnoDB {$cc};",
    ];

    $defs['automation_rules'] = [
        'version' => '1.0.0',
        'sql'     => "CREATE TABLE {$p}mailsmart_automation_rules (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(200)        NOT NULL DEFAULT '',
  trigger_event VARCHAR(100)        NOT NULL DEFAULT '',
  conditions    LONGTEXT            NOT NULL,
  actions       LONGTEXT            NOT NULL,
  status        VARCHAR(20)         NOT NULL DEFAULT 'active',
  run_count     INT(11) UNSIGNED    NOT NULL DEFAULT 0,
  last_run_at   DATETIME                     DEFAULT NULL,
  created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY trigger_event (trigger_event)
) ENGINE=InnoDB {$cc};",
    ];

    return $defs;
});
```

---

## 16. Feature Implementation Blueprints

### 16.1 AI Email Generator

**Goal:** Add a `POST /mailsmart-pro/v1/ai/generate` endpoint that calls an AI API and returns generated email content.

**Integration points used:**
- `mailsmart_email_content` filter — auto-apply AI suggestions during send
- `mailsmart_admin_js_data` filter — inject AI provider config to SPA
- New REST controller `MailSmart_Pro_REST_AI_Controller`

**Skeleton:**

```php
class MailSmart_Pro_AI_Generator {

    private string $provider;
    private string $api_key;

    public function __construct() {
        $settings       = get_option( 'mailsmart_pro_ai_settings', [] );
        $this->provider = $settings['provider'] ?? 'openai';
        $this->api_key  = $settings['api_key']  ?? '';
    }

    public function generate( string $prompt, string $type = 'full' ): string|WP_Error {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'AI API key is not configured.', 'sg-mailsmart-pro' ) );
        }

        return match( $this->provider ) {
            'openai'    => $this->call_openai( $prompt, $type ),
            'anthropic' => $this->call_anthropic( $prompt, $type ),
            default     => new WP_Error( 'unknown_provider', 'Unknown AI provider: ' . $this->provider ),
        };
    }

    private function call_openai( string $prompt, string $type ): string|WP_Error {
        $system_prompt = match( $type ) {
            'subject' => 'You are an expert email copywriter. Generate a compelling email subject line.',
            'body'    => 'You are an expert email copywriter. Generate an engaging email body in HTML.',
            default   => 'You are an expert email copywriter. Generate a complete email with subject and HTML body.',
        };

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'model'    => 'gpt-4o-mini',
                'messages' => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user',   'content' => $prompt ],
                ],
                'max_tokens' => 1000,
            ]),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['choices'][0]['message']['content'] ?? new WP_Error( 'parse_error', 'Could not parse OpenAI response.' );
    }
}
```

---

### 16.2 Email Automation Engine

**Goal:** Trigger emails automatically based on WordPress events.

**Integration points used:**
- Custom DB table `mailsmart_automation_rules`
- Custom DB table `mailsmart_automation_queue`
- WP-Cron for processing the queue
- `mailsmart_after_send` action for tracking automation sends

**Key concepts:**

```php
// 1. A Trigger listens for a WordPress event and creates a queue entry.
class MailSmart_Pro_Trigger_User_Register {

    public function register(): void {
        add_action( 'user_register', [ $this, 'handle' ], 10, 2 );
    }

    public function handle( int $user_id, array $userdata ): void {
        if ( ! mailsmart_is_license_active() ) {
            return;
        }

        $rules = MailSmart_Pro_Automation_Engine::get_rules_for_trigger( 'user_register' );

        foreach ( $rules as $rule ) {
            MailSmart_Pro_Automation_Queue::enqueue(
                rule_id:   $rule['id'],
                recipient: $userdata['user_email'],
                payload:   [ 'user_id' => $user_id, 'username' => $userdata['user_login'] ],
                delay:     (int) ( $rule['delay_seconds'] ?? 0 ),
            );
        }
    }
}

// 2. The queue processor runs via WP-Cron and sends pending emails.
class MailSmart_Pro_Automation_Processor {

    public static function process(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mailsmart_automation_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $pending = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s LIMIT 50",
                current_time( 'mysql', true )
            ),
            ARRAY_A
        );

        foreach ( $pending as $item ) {
            $rule    = MailSmart_Pro_Automation_Engine::get_rule( (int) $item['rule_id'] );
            $payload = json_decode( $item['payload'], true );

            // Build the email from the rule's action definition
            $email = MailSmart_Pro_Automation_Engine::build_email( $rule, $item['recipient'], $payload );

            // Send through the core mailer (all hooks and logging apply automatically)
            $result = mailsmart_send_email( $email );

            // Update queue status
            $wpdb->update(
                $table,
                [
                    'status'  => $result['success'] ? 'sent' : 'failed',
                    'sent_at' => current_time( 'mysql', true ),
                    'error'   => $result['error'],
                ],
                [ 'id' => $item['id'] ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
        }
    }
}
```

---

### 16.3 Analytics Dashboard

**Goal:** Track email open rates and click rates using tracking pixels and redirect links.

**Integration points used:**
- `mailsmart_email_content` filter — inject tracking pixel + wrapped links
- `mailsmart_log_stats` filter — append open/click rates to dashboard
- `mailsmart_dashboard_stats` filter — surface in REST API
- Custom DB table `mailsmart_analytics_events`

```php
class MailSmart_Pro_Analytics_Engine {

    public static function init(): void {
        add_filter( 'mailsmart_email_content', [ self::class, 'inject_tracking' ], 20, 2 );
        add_filter( 'mailsmart_log_stats',     [ self::class, 'append_stats' ],    10, 2 );

        // Public tracking endpoint (no auth required):
        add_action( 'rest_api_init', function(): void {
            register_rest_route( 'mailsmart-pro/v1', '/track/open/(?P<log_id>[\d]+)', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'handle_open' ],
                'permission_callback' => '__return_true', // Public!
            ]);
        });
    }

    public static function inject_tracking( string $content, SG_MailSmart_Email $email ): string {
        if ( ! mailsmart_is_license_active() ) {
            return $content;
        }
        // The log_id is not available at this point — you need a different approach:
        // Use a unique tracking token stored as a transient, resolved on open.
        $token   = wp_generate_uuid4();
        $pixel   = '<img src="' . esc_url( rest_url( "mailsmart-pro/v1/track/open/{$token}" ) ) . '" width="1" height="1" style="display:none;" alt="" />';
        set_transient( 'mailsmart_track_' . $token, true, 7 * DAY_IN_SECONDS );
        return $content . $pixel;
    }

    public static function handle_open( WP_REST_Request $request ): void {
        $log_id = absint( $request->get_param( 'log_id' ) );
        // Record the open event, then return a 1x1 transparent GIF:
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'mailsmart_analytics_events',
            [ 'log_id' => $log_id, 'event' => 'open', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '' ],
            [ '%d', '%s', '%s' ]
        );
        header( 'Content-Type: image/gif' );
        echo base64_decode( 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
        exit;
    }

    public static function append_stats( array $stats, int $days ): array {
        // Query analytics table and merge in open/click rates
        $stats['open_rate']  = self::get_open_rate( $days );
        $stats['click_rate'] = self::get_click_rate( $days );
        return $stats;
    }

    private static function get_open_rate( int $days ): float {
        global $wpdb;
        $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $events_table = $wpdb->prefix . 'mailsmart_analytics_events';
        $logs_table   = $wpdb->prefix . 'mailsmart_email_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $opens = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT log_id) FROM {$events_table} WHERE event = 'open' AND fired_at >= %s",
            $since
        ));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $sent  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'sent' AND created_at >= %s",
            $since
        ));
        return $sent > 0 ? round( ( $opens / $sent ) * 100, 1 ) : 0.0;
    }

    private static function get_click_rate( int $days ): float {
        // Similar implementation for click events
        return 0.0;
    }
}
```

---

## 17. Global Function API Reference

These functions are defined in `utils/functions.php` and are available anywhere after Lite is loaded.

### License Functions

| Function | Returns | Description |
|---|---|---|
| `mailsmart_is_license_active()` | `bool` | **Primary Pro gate.** True when license is active and not expired. Passes through `mailsmart_license_active` filter. |
| `mailsmart_get_license_status()` | `string` | Raw status string: `'active'` \| `'inactive'` \| `'expired'` \| `'invalid'` |
| `mailsmart_get_license_expiry()` | `string` | Expiry date in `YYYY-MM-DD` format, or `''` |
| `mailsmart_get_license_key(bool $masked = true)` | `string` | Returns masked key (default) or raw key. **Never output the raw key to the browser.** |

### Email Functions

| Function | Returns | Description |
|---|---|---|
| `mailsmart_send_email($email_data)` | `array` | Send via the core engine. Accepts `SG_MailSmart_Email` DTO or array. Returns `['success', 'status', 'email', 'error', 'log_id']` |
| `mailsmart_send_test_email(string $to, string $subject)` | `array` | Send a formatted test email |

### Settings Functions

| Function | Returns | Description |
|---|---|---|
| `mailsmart_get_smtp_settings()` | `array` | Full SMTP settings merged with defaults |
| `mailsmart_get_general_settings()` | `array` | General settings merged with defaults |
| `mailsmart_is_smtp_active()` | `bool` | True when SMTP is enabled AND a host is configured |
| `mailsmart_is_logging_enabled()` | `bool` | True when logging is enabled (passes through filter) |

### URL & Path Functions

| Function | Returns | Description |
|---|---|---|
| `mailsmart_admin_url(string $tab)` | `string` | Escaped URL to a specific admin tab |
| `mailsmart_asset_url(string $path)` | `string` | Escaped public URL to a Lite asset file |
| `mailsmart_path(string $path)` | `string` | Filesystem path relative to the Lite plugin root |
| `mailsmart_rest_url(string $endpoint)` | `string` | Full REST URL for a Lite endpoint |

### Utility Functions

| Function | Returns | Description |
|---|---|---|
| `mailsmart_format_datetime(string $mysql_utc)` | `string` | Formats UTC datetime using site timezone + date format |
| `mailsmart_status_label(string $status)` | `string` | Human-readable label for a delivery status |
| `mailsmart_truncate(string $string, int $length, string $ellipsis)` | `string` | Safe mb_substr with ellipsis |
| `mailsmart_current_user_can_manage()` | `bool` | Respects `mailsmart_manager_capability` filter |
| `mailsmart_log_debug($message, string $context)` | `void` | Writes to PHP error log when `WP_DEBUG` + `WP_DEBUG_LOG` are on |
| `mailsmart_is_debug()` | `bool` | True when `WP_DEBUG` is defined and true |

### Update System

| Function | Returns | Description |
|---|---|---|
| `mailsmart_register_for_updates(string $slug, string $version, string $info_url)` | `void` | Register Pro for MailSmart-managed auto-updates (see §20) |

---

## 18. Class & Constant Reference

### Core Classes (from Lite)

| Class | Location | Purpose |
|---|---|---|
| `SG_MailSmart_Plugin` | `includes/class-mailsmart-plugin.php` | Singleton bootstrapper |
| `SG_MailSmart_Container` | `includes/class-mailsmart-container.php` | Service container |
| `SG_MailSmart_Loader` | `includes/class-mailsmart-loader.php` | Hook queue |
| `SG_MailSmart_Email` | `emails/class-mailsmart-email.php` | Immutable email DTO |
| `SG_MailSmart_Mailer` | `emails/class-mailsmart-mailer.php` | Email dispatch engine |
| `SG_MailSmart_SMTP_Mailer` | `emails/class-mailsmart-smtp-mailer.php` | PHPMailer SMTP configurator |
| `SG_MailSmart_Logger` | `logs/class-mailsmart-logger.php` | Log read/write |
| `SG_MailSmart_License_Manager` | `includes/class-mailsmart-license-manager.php` | License state |
| `SG_MailSmart_Schema` | `database/class-mailsmart-schema.php` | Table definitions |
| `SG_MailSmart_Migrator` | `database/class-mailsmart-migrator.php` | Migration runner |
| `SG_MailSmart_SMTP_Provider` | `integrations/class-mailsmart-smtp-provider.php` | Abstract SMTP provider |
| `SG_MailSmart_REST_Controller` | `rest/class-mailsmart-rest-controller.php` | Base REST controller |
| `SG_MailSmart_Sanitizer` | `utils/class-mailsmart-sanitizer.php` | Input sanitisation helpers |
| `SG_MailSmart_Admin` | `admin/class-mailsmart-admin.php` | Admin page + assets |

### SG_MailSmart_Plugin — Public Methods

```php
SG_MailSmart_Plugin::instance()                    // Returns the singleton
SG_MailSmart()->container(): SG_MailSmart_Container
SG_MailSmart()->loader(): SG_MailSmart_Loader
SG_MailSmart()->mailer(): SG_MailSmart_Mailer
SG_MailSmart()->logger(): SG_MailSmart_Logger
SG_MailSmart()->license_manager(): SG_MailSmart_License_Manager
SG_MailSmart()->version(): string
```

### SG_MailSmart_Email — Public API

```php
// Static factories:
SG_MailSmart_Email::create(): self
SG_MailSmart_Email::from_array( array $data ): self

// Immutable mutation (each returns a NEW instance):
$email->with_to( $to )                           // string|string[]
$email->with_subject( string $subject )
$email->with_message( string $message )
$email->with_from( string $email, string $name )
$email->with_reply_to( string $reply_to )
$email->with_extra_headers( array $headers )     // ['Header-Name' => 'value']
$email->with_attachments( array $paths )
$email->with_content_type( string $type )        // 'text/html' | 'text/plain'
$email->with_charset( string $charset )          // 'UTF-8'

// Getters:
$email->get_to()              // string|string[]
$email->get_subject()         // string
$email->get_message()         // string
$email->get_from_email()      // string (falls back to admin_email)
$email->get_from_name()       // string (falls back to blogname)
$email->get_reply_to()        // string
$email->get_extra_headers()   // array<string, string>
$email->get_attachments()     // string[]
$email->get_content_type()    // string
$email->get_charset()         // string
$email->is_valid()            // bool
$email->get_validation_errors() // string[]
$email->build_headers()       // string[] (format for wp_mail)
$email->to_array()            // Full associative array
$email->to_wp_mail_args()     // [to, subject, message, headers, attachments]

// Constants:
SG_MailSmart_Email::CONTENT_TYPE_HTML   // 'text/html'
SG_MailSmart_Email::CONTENT_TYPE_PLAIN  // 'text/plain'
SG_MailSmart_Email::CHARSET_UTF8        // 'UTF-8'
```

### SG_MailSmart_License_Manager — Public API

```php
// Status constants:
SG_MailSmart_License_Manager::STATUS_ACTIVE    // 'active'
SG_MailSmart_License_Manager::STATUS_INACTIVE  // 'inactive'
SG_MailSmart_License_Manager::STATUS_EXPIRED   // 'expired'
SG_MailSmart_License_Manager::STATUS_INVALID   // 'invalid'

// Option key constants:
SG_MailSmart_License_Manager::OPTION_KEY        // 'mailsmart_license_key'
SG_MailSmart_License_Manager::OPTION_STATUS     // 'mailsmart_license_status'
SG_MailSmart_License_Manager::OPTION_EXPIRY     // 'mailsmart_license_expiry'
SG_MailSmart_License_Manager::OPTION_LAST_CHECK // 'mailsmart_license_last_check'

// Methods:
$lm->activate( string $key ): array           // ['success', 'status', 'message', 'expiry']
$lm->deactivate(): array                      // ['success', 'message']
$lm->check_status( bool $force = false ): array
$lm->get_key( bool $masked = true ): string
$lm->get_status(): string
$lm->get_expiry(): string
$lm->get_last_check(): int
$lm->is_active(): bool                        // passes through mailsmart_license_active filter
$lm->is_expired(): bool
$lm->has_key(): bool
$lm->get_all_data(): array
```

### SG_MailSmart_Logger — Public API

```php
// Status constants:
SG_MailSmart_Logger::STATUS_PENDING  // 'pending'
SG_MailSmart_Logger::STATUS_SENT     // 'sent'
SG_MailSmart_Logger::STATUS_FAILED   // 'failed'

// Write:
$logger->log( SG_MailSmart_Email $email, string $status, ?string $error_message, string $mailer ): int|false
$logger->mark_sent( int $log_id ): bool
$logger->mark_failed( int $log_id, string $error_message ): bool
$logger->delete_log( int $log_id ): bool
$logger->delete_all_logs(): bool
$logger->purge_old_logs(): int

// Read:
$logger->get_logs( array $args ): array     // See §12.2 for args
$logger->get_log( int $log_id ): ?array
$logger->get_stats( int $days = 7 ): array
```

### SG_MailSmart_Container — Public API

```php
$c->bind( string $id, callable $resolver ): void       // Factory (new instance each call)
$c->singleton( string $id, callable $resolver ): void  // Cached after first resolution
$c->instance( string $id, $object ): void              // Register pre-built object
$c->make( string $id ): mixed                          // Resolve service
$c->has( string $id ): bool
$c->forget( string $id ): void                         // Remove binding + cached instance
$c->bound(): array                                     // List all registered IDs
```

---

## 19. WordPress Options Reference

### Lite Options

| Option Name | Type | Default | Autoload | Description |
|---|---|---|---|---|
| `mailsmart_smtp_settings` | `array` | See activator | yes | SMTP configuration |
| `mailsmart_general_settings` | `array` | See activator | yes | Logging, retention |
| `mailsmart_db_version` | `string` | `'0'` | no | Installed schema version |
| `mailsmart_activated_at` | `int` | activation time | yes | Plugin activation timestamp |
| `mailsmart_license_key` | `string` | `''` | no | Raw license key |
| `mailsmart_license_status` | `string` | `'inactive'` | no | License status |
| `mailsmart_license_expiry` | `string` | `''` | no | Expiry date (YYYY-MM-DD) |
| `mailsmart_license_last_check` | `int` | `0` | no | Unix timestamp of last API check |

### SMTP Settings Array Keys

```php
get_option('mailsmart_smtp_settings') = [
    'enabled'    => false,   // bool
    'host'       => '',      // string
    'port'       => 587,     // int
    'encryption' => 'tls',  // 'tls' | 'ssl' | 'none'
    'auth'       => true,    // bool
    'username'   => '',      // string
    'password'   => '',      // string (stored as-is)
    'from_email' => '',      // string
    'from_name'  => '',      // string
]
```

### General Settings Array Keys

```php
get_option('mailsmart_general_settings') = [
    'logging_enabled'    => true,  // bool
    'log_retention_days' => 30,    // int (1-365)
]
```

### Recommended Pro Options

```php
// Store Pro settings separately from Lite settings:
add_option('mailsmart_pro_settings', [
    'ai_provider'        => 'openai',  // 'openai' | 'anthropic'
    'ai_api_key'         => '',        // string (never logged, never exposed via REST unmasked)
    'active_smtp_provider' => 'smtp',  // 'smtp' | 'sendgrid' | 'ses' | 'mailgun' | 'postmark'
    'analytics_enabled'  => true,      // bool
    'templates_enabled'  => true,      // bool
    'automation_enabled' => true,      // bool
], '', 'no'); // autoload = 'no'
```

---

## 20. Automatic Update Integration

### 20.1 How the Updater Works

Lite provides a registration stub (`mailsmart_register_for_updates()`) that the full updater implementation will read via the `mailsmart_registered_updatable_plugins` filter. When the updater is built (in Lite 1.1.0), it will:

1. Listen on `pre_set_site_transient_update_plugins`
2. Read the registered plugins list
3. POST to each plugin's `$info_url` with the stored license key
4. Parse the response and inject update metadata into WordPress's update transient

### 20.2 Your Update Info Endpoint

Your server must respond to `GET {info_url}` with:

```json
{
    "name":         "SG MailSmart Pro",
    "slug":         "sg-mailsmart-pro",
    "version":      "1.1.0",
    "requires":     "5.8",
    "tested":       "6.5",
    "requires_php": "7.4",
    "author":       "SG MailSmart",
    "homepage":     "https://sgmailsmart.com/pro",
    "download_url": "https://sgmailsmart.com/downloads/sg-mailsmart-pro-1.1.0.zip",
    "sections": {
        "description": "Pro add-on for SG MailSmart AI Lite.",
        "changelog":   "<h4>1.1.0</h4><ul><li>Added SendGrid integration.</li></ul>"
    },
    "banners": {
        "low":  "https://sgmailsmart.com/assets/banner-772x250.jpg",
        "high": "https://sgmailsmart.com/assets/banner-1544x500.jpg"
    }
}
```

The `download_url` must require a valid license key as a query parameter:
```
https://sgmailsmart.com/downloads/sg-mailsmart-pro.zip?license_key={key}&site_url={site}
```

### 20.3 Registration Call

```php
// In your Pro plugin's mailsmart_loaded callback:
mailsmart_register_for_updates(
    'sg-mailsmart-pro/sg-mailsmart-pro.php',
    MAILSMART_PRO_VERSION,
    'https://sgmailsmart.com/wp-json/mailsmart-updates/v1/sg-mailsmart-pro.json'
);
```

---

## 21. Security Checklist

### Input Sanitisation

Use `SG_MailSmart_Sanitizer` static methods for all Pro input:

```php
SG_MailSmart_Sanitizer::email( $raw )                     // Sanitise + validate email
SG_MailSmart_Sanitizer::email_list( $raw )                // Array of valid emails
SG_MailSmart_Sanitizer::hostname( $raw )                  // Strip protocol + trailing path
SG_MailSmart_Sanitizer::port( $raw, $default = 587 )      // Clamp to 1-65535
SG_MailSmart_Sanitizer::smtp_encryption( $raw )           // 'tls' | 'ssl' | 'none'
SG_MailSmart_Sanitizer::text( $raw )                      // sanitize_text_field()
SG_MailSmart_Sanitizer::textarea( $raw )                  // sanitize_textarea_field()
SG_MailSmart_Sanitizer::html_email_body( $raw )           // wp_kses_post()
SG_MailSmart_Sanitizer::email_subject( $raw )             // Strip tags + limit to 998 chars
SG_MailSmart_Sanitizer::integer( $raw, $min, $max, $def ) // Clamp to range
SG_MailSmart_Sanitizer::boolean( $raw )                   // filter_var FILTER_VALIDATE_BOOLEAN
SG_MailSmart_Sanitizer::allowlist( $raw, $allowed, $def ) // Strict enum validation
SG_MailSmart_Sanitizer::url( $raw )                       // esc_url_raw + FILTER_VALIDATE_URL
SG_MailSmart_Sanitizer::search_query( $raw, $max = 200 )  // Safe LIKE search string
```

### Critical Security Rules

1. **Never store raw API keys unencrypted in log files or transients.** Store only in `wp_options` with `autoload=no`.

2. **Never expose the raw license key via REST API or JavaScript.** Only return masked versions.

3. **Gate every Pro REST endpoint with the license check:**
   ```php
   public function my_endpoint( WP_REST_Request $request ) {
       if ( ! mailsmart_is_license_active() ) {
           return $this->error_response( 'pro_license_required', '...', 402 );
       }
       // ...
   }
   ```

4. **All Pro REST endpoints must inherit from `SG_MailSmart_REST_Controller`** which provides the `admin_permissions_check()` method with nonce verification, `manage_options` capability check, and the `mailsmart_rest_permission` filter.

5. **Escape all output.** Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` everywhere.

6. **Use `wp_nonce_field()` and `check_admin_referer()` for any admin-side HTML forms.** The REST API handles nonces via `X-WP-Nonce` automatically.

7. **Validate all `absint()` IDs before database queries:**
   ```php
   $id = absint( $request->get_param('id') );
   if ( $id <= 0 ) {
       return $this->error_response('invalid_id', '...', 400 );
   }
   ```

8. **Use `$wpdb->prepare()` for all direct SQL.** Never interpolate user data into queries.

9. **Direct DB queries must be suppressed with the appropriate phpcs ignore comment and justified.** Use `$wpdb->get_results()`, not `$wpdb->query()`, for SELECT statements.

10. **Prevent direct file access** at the top of every PHP file:
    ```php
    defined( 'ABSPATH' ) || exit;
    ```

---

## 22. Testing & Debugging

### 22.1 Manual Testing Checklist

**License Flow:**
- [ ] Enter key shorter than 20 chars → should fail with "invalid"
- [ ] Enter key containing "INVALID" → should fail with "invalid"
- [ ] Enter key containing "EXPIRED" → should show "expired" status
- [ ] Enter valid 20+ char key → should activate with 1-year expiry
- [ ] Deactivate → status should return to "inactive"
- [ ] Re-check license → should re-validate against API

**Email Engine:**
- [ ] Send with SMTP disabled → wp_mail used, logged as "sent" or "failed"
- [ ] Send with SMTP enabled → PHPMailer SMTP used, correct host/port
- [ ] Send with invalid email → REST returns 400, no log entry
- [ ] `mailsmart_email_content` filter modifies body
- [ ] `mailsmart_mailer` filter can swap send callable

**Logging:**
- [ ] Successful send → log entry with status "sent"
- [ ] Failed send → log entry with status "failed" + error message
- [ ] `mailsmart_log_entry` returning `false` → no log entry created
- [ ] Log purge respects retention days

### 22.2 WP-CLI Commands for Testing

```bash
# Verify Lite services are available
wp eval "var_dump( SG_MailSmart()->version() );"

# Check license status
wp eval "var_dump( mailsmart_get_license_status() );"

# Force activate a test key (using mock)
wp eval "define('MAILSMART_LICENSE_MOCK', true); \$r = SG_MailSmart()->license_manager()->activate('AAAABBBBCCCCDDDDEEEE'); var_dump(\$r);"

# Send a test email
wp eval "var_dump( mailsmart_send_test_email('test@example.com') );"

# Query logs
wp eval "var_dump( SG_MailSmart()->logger()->get_stats(7) );"

# Verify all registered services
wp eval "var_dump( SG_MailSmart()->container()->bound() );"
```

### 22.3 Debug Logging

All debug output from Lite goes to PHP's error log when `WP_DEBUG=true` and `WP_DEBUG_LOG=true`:

```php
// Write Pro-specific debug messages:
mailsmart_log_debug( 'My debug message', 'pro' );
// Output: [SG MailSmart/pro] My debug message

mailsmart_log_debug( [ 'key' => 'value' ], 'pro.ai' );
// Output: [SG MailSmart/pro.ai] Array ( [key] => value )
```

SMTP debug output appears in `wp-content/debug.log` as:
```
[SG MailSmart SMTP Debug L2] Connecting to smtp.gmail.com:587...
[SG MailSmart SMTP Debug L2] EHLO ...
```

### 22.4 Testing the Container

```php
add_action( 'mailsmart_loaded', function( SG_MailSmart_Plugin $plugin ): void {
    // Test that a Pro service is correctly bound:
    assert( $plugin->container()->has('pro.ai'), 'AI service should be registered' );

    // Test that rebinding works:
    $plugin->container()->singleton('test.service', fn() => new stdClass());
    $instance1 = $plugin->container()->make('test.service');
    $instance2 = $plugin->container()->make('test.service');
    assert( $instance1 === $instance2, 'Singleton should return same instance' );
    $plugin->container()->forget('test.service');
}, PHP_INT_MAX ); // Very high priority so all other mailsmart_loaded callbacks have run
```

---

## 23. Release & Deployment Checklist

### Pre-Release

- [ ] All PHP files start with `defined('ABSPATH') || exit;`
- [ ] No `var_dump()`, `print_r()`, `error_log()` calls that aren't gated by `WP_DEBUG`
- [ ] All direct DB queries use `$wpdb->prepare()`
- [ ] All user-facing strings are wrapped in `__()` or `_e()` with `sg-mailsmart-pro` text domain
- [ ] All REST endpoints return `WP_REST_Response` (not raw arrays)
- [ ] Pro plugin verified to **deactivate cleanly** if Lite is deactivated
- [ ] Pro plugin verified to **activate cleanly** with a fresh Lite install (no existing data)
- [ ] `uninstall.php` removes ALL Pro-specific options and tables
- [ ] License check present on every Pro feature before it executes
- [ ] `readme.txt` changelog updated with this version's changes
- [ ] Version constant `MAILSMART_PRO_VERSION` bumped
- [ ] `mailsmart_register_for_updates()` called with the correct `$info_url`

### License Server

- [ ] `/activate` endpoint live and returns correct response structure
- [ ] `/deactivate` endpoint decrements activation count
- [ ] `/check` endpoint validates active licenses correctly
- [ ] Expired licenses return `status: 'expired'` (not `'invalid'`)
- [ ] Rate limiting in place (max 10 requests per site per hour)
- [ ] HTTPS required (reject HTTP requests)

### Distribution

- [ ] Plugin ZIP does not include `.git/`, `node_modules/`, `tests/`, `*.lock` files
- [ ] ZIP filename follows format: `sg-mailsmart-pro-{version}.zip`
- [ ] Download endpoint requires valid license key + site URL in query string
- [ ] Update info JSON endpoint returns correct `download_url` with signed URL or token

### Post-Release

- [ ] Verify update notification appears on test sites running the previous version
- [ ] Verify the update can be installed via WordPress admin → Plugins → Update
- [ ] Verify SMTP connections work end-to-end on a clean install
- [ ] Monitor license server error logs for unexpected patterns

---

## Appendix A: Complete `SG_MailSmart_REST_Controller` Method Signatures

```php
abstract public function register_routes(): void;

public function admin_permissions_check( WP_REST_Request $request ): bool|WP_Error;

protected function success_response(
    mixed $data = null,
    array $meta = [],
    string $message = '',
    int $status_code = 200
): WP_REST_Response;

protected function error_response(
    string $code,
    string $message,
    int $status_code = 400,
    array $extra = []
): WP_REST_Response;

// Aliases:
protected function success( mixed $data = null, int $status_code = 200 ): WP_REST_Response;
protected function error( string $code, string $message, int $status_code = 400, array $extra = [] ): WP_REST_Response;
protected function not_found( string $message = '' ): WP_REST_Response;
protected function validation_error( string $message, array $errors = [] ): WP_REST_Response;
protected function paginated( array $items, int $total, int $page, int $per_page, int $total_pages ): WP_REST_Response;

// Input helpers:
protected function get_param_string( WP_REST_Request $request, string $param, string $default = '' ): string;
protected function get_param_int( WP_REST_Request $request, string $param, int $default = 0 ): int;
protected function get_param_bool( WP_REST_Request $request, string $param, bool $default = false ): bool;
protected function get_param_email( WP_REST_Request $request, string $param ): string;
protected function get_param_html( WP_REST_Request $request, string $param, string $default = '' ): string;
```

---

## Appendix B: REST API Endpoint Quick Reference

### Lite Endpoints (mailsmart/v1)

| Method | Path | Description |
|---|---|---|
| `GET` | `/settings` | Get all settings (SMTP, general). Password masked. |
| `POST` | `/settings` | Update settings. Partial update supported. |
| `GET` | `/logs` | Paginated log list. Supports `page`, `per_page`, `status`, `search`, `orderby`, `order`. |
| `GET` | `/logs/{id}` | Single log entry with full message body. |
| `DELETE` | `/logs` | Delete ALL log entries (TRUNCATE). |
| `DELETE` | `/logs/{id}` | Delete a single log entry. |
| `GET` | `/dashboard` | Aggregate stats. Supports `?days=7`. |
| `POST` | `/email/send` | Send an email. Body: `{to, subject, message, from_email?, from_name?, reply_to?, content_type?}`. |
| `POST` | `/email/test` | Send a test email. Body: `{to?, subject?}`. |
| `GET` | `/license/status` | Current license state. Optional `?force=true` to bypass cache. |
| `POST` | `/license/activate` | Activate a license. Body: `{license_key}`. |
| `POST` | `/license/deactivate` | Deactivate the current license. |

### Pro Endpoints (mailsmart-pro/v1) — To Be Implemented

| Method | Path | Description |
|---|---|---|
| `POST` | `/ai/generate` | Generate email content. Body: `{prompt, type}`. |
| `GET` | `/ai/history` | Paginated generation history. |
| `GET` | `/templates` | List email templates. |
| `POST` | `/templates` | Create a template. |
| `PUT` | `/templates/{id}` | Update a template. |
| `DELETE` | `/templates/{id}` | Delete a template. |
| `GET` | `/automation/rules` | List automation rules. |
| `POST` | `/automation/rules` | Create a rule. |
| `PUT` | `/automation/rules/{id}` | Update a rule. |
| `DELETE` | `/automation/rules/{id}` | Delete a rule. |
| `GET` | `/analytics/overview` | Analytics overview. |
| `GET` | `/analytics/events` | Raw event log. |
| `GET` | `/track/open/{token}` | Open tracking pixel (public). |
| `GET` | `/track/click/{token}` | Click redirect (public). |

---

*This document is the complete specification for the SG MailSmart Pro add-on. Every hook listed is a stable, versioned contract. Breaking changes to any listed hook will be treated as a major version bump in the Lite plugin and announced with a minimum 60-day deprecation notice.*

*Last updated: SG MailSmart AI Lite v1.0.0*