# SG MailSmart Trials Engine — Complete Guide

A comprehensive reference for the **SG MailSmart Trials Engine** WordPress plugin, covering every feature, how things work under the hood, configuration options, and troubleshooting.

---

## Table of Contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Plugin Architecture](#plugin-architecture)
5. [Features](#features)
   - [Trial Mode](#trial-mode)
   - [Sandbox Mode](#sandbox-mode)
   - [Demo Mode](#demo-mode)
   - [Feature Gating](#feature-gating)
   - [Usage Tracking](#usage-tracking)
   - [License Injection](#license-injection)
   - [Anti-Abuse Protection](#anti-abuse-protection)
   - [User / Role Control](#user--role-control)
   - [Cron Jobs (Auto-Expiry & Pro Status)](#cron-jobs)
6. [Admin Panel](#admin-panel)
   - [Trial Settings Tab](#trial-settings-tab)
   - [Sandbox Tab](#sandbox-tab)
   - [Demo Mode Tab](#demo-mode-tab)
   - [Dashboard Tab](#dashboard-tab)
7. [REST API](#rest-api)
8. [Settings Reference](#settings-reference)
9. [Hook Reference](#hook-reference)
10. [How It All Works Together](#how-it-all-works-together)
11. [Troubleshooting](#troubleshooting)
12. [FAQ](#faq)

---

## Overview

The **SG MailSmart Trials Engine** is a companion plugin for the SG MailSmart AI ecosystem (Lite & Pro). It provides a complete trial, sandbox, and demo management system that lets administrators:

- **Offer time-limited trials** of Pro features to users.
- **Run sandboxed testing** for internal QA without affecting production.
- **Showcase the platform** with pre-filled demo data (templates, analytics, automations).

The plugin integrates seamlessly with SG MailSmart AI Lite and Pro through WordPress filters and actions, injecting a "virtual license" for trial users so they can experience Pro-level features without an actual license key.

---

## Requirements

| Requirement   | Minimum Version |
|---------------|-----------------|
| WordPress     | 5.8+            |
| PHP           | 7.4+            |
| MySQL/MariaDB | 5.6 / 10.0+     |
| SG MailSmart AI Lite | 1.0.0+  |

**Production mode note:** In non-debug environments (`WP_DEBUG` is `false`), the plugin only loads if you define the following constant in `wp-config.php`:

```php
define( 'MAILSMART_TRIALS_ENABLED', true );
```

When `WP_DEBUG` is `true`, the plugin loads automatically.

---

## Installation

1. Upload the `sg-mailsmart-trials-engine` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **MailSmart Trials** in the admin sidebar.
4. Configure settings under the **Trial Settings** tab.
5. Enable Sandbox or Demo modes from their respective tabs.

---

## Plugin Architecture

The plugin follows a modular, singleton-based architecture:

```
sg-mailsmart-trials-engine.php    ← Entry point
├── includes/
│   ├── class-autoloader.php      ← PSR-4 autoloader with class map
│   └── class-activator.php       ← Activation/deactivation hooks & default settings
├── core/
│   ├── class-engine.php          ← Singleton orchestrator (boots all subsystems)
│   ├── class-manager.php         ← Trial lifecycle: start / stop / status
│   ├── class-state.php           ← State machine: active → paused → expired
│   ├── class-data.php            ← Data persistence (wp_options)
│   ├── class-cron.php            ← Scheduled expiry checks & Pro status monitoring
│   ├── class-anti-abuse.php      ← Reactivation limits & domain locking
│   ├── class-user-control.php    ← Role-based access control
│   └── class-license-injector.php← Injects virtual license for trial users
├── features/
│   ├── class-trial.php           ← Standard trial mode
│   ├── class-sandbox.php         ← Sandbox mode for internal testing
│   ├── class-demo.php            ← Demo mode with fake data
│   ├── class-feature-gate.php    ← Enforces feature & usage limits
│   └── class-usage-tracker.php   ← Tracks email, AI, and automation usage
├── admin/
│   ├── class-admin.php           ← Admin page with tabs, forms, AJAX handlers
│   ├── js/admin.js               ← Client-side interactions
│   └── css/admin.css             ← Admin panel styles
└── rest/
    └── class-rest-controller.php ← Full REST API for programmatic control
```

### Boot Sequence

1. WordPress loads the plugin file (`sg-mailsmart-trials-engine.php`).
2. The safety guard checks if the plugin should be active (debug mode or `MAILSMART_TRIALS_ENABLED`).
3. The autoloader is registered.
4. On the `plugins_loaded` hook (priority 20, after Lite and Pro), the Engine singleton is created.
5. The Engine constructor calls:
   - `boot_core()` — License Injector, Cron
   - `boot_features()` — Trial, Sandbox, Demo, Feature Gate, Usage Tracker
   - `boot_admin()` — Admin panel (only in admin context)
   - `boot_rest()` — REST API endpoints
   - `boot_hooks()` — Fires `mailsmart_trials_loaded` action

---

## Features

### Trial Mode

Standard time-limited or usage-limited trial for end users.

**How it works:**
1. An admin starts a trial for a selected user from the admin panel or via REST API.
2. A trial record is created and stored in `wp_options` under the key `mailsmart_trial_data`.
3. The record tracks: start time, expiration, usage consumed, enabled features, and mode.
4. The License Injector hooks into `mailsmart_license_active` to make Lite/Pro treat the user as licensed.
5. Feature Gate enforces usage limits and feature restrictions during the trial.
6. When the trial expires (by time or usage), access is revoked automatically.

**Trial types:**
- **Time-based** — Expires after a set duration (minutes, hours, or days).
- **Usage-based** — Expires when usage limits are hit (emails sent, AI generations, automations run).
- **Feature-based** — Time-based with restricted feature set.
- **Hybrid** — Expires when *either* the time limit or usage limit is reached first.

### Sandbox Mode

Admin-controlled, per-user testing environment that pauses automatically when the Pro plugin is deactivated.

**How it works:**
1. Admin enables sandbox mode from the **Sandbox** tab (check "Allow sandbox mode for internal testing").
2. Admin saves settings from the **Trial Settings** tab.
3. Admin selects a user and clicks **Start Sandbox**.
4. The sandbox uses the same trial infrastructure but with `mode: 'sandbox'`.
5. If the SG MailSmart Pro plugin becomes inactive, the sandbox automatically **pauses** (timer stops).
6. When Pro is reactivated, the sandbox **resumes** with the remaining time intact.

**Key behaviors:**
- Sandbox must be explicitly enabled in settings before it can be started.
- Sandbox respects the same anti-abuse reactivation limits as trials.
- Pausing preserves the remaining time; it does not consume it.

### Demo Mode

Showcasing mode with pre-filled templates, fake analytics, and simulated automations.

**How it works:**
1. Admin enables demo mode from the **Demo Mode** tab (check "Enable demo mode for showcasing").
2. Admin saves settings from the **Trial Settings** tab.
3. Admin clicks **Load Demo Data** to populate fake data into `wp_options`.
4. Admin selects a user and clicks **Start Demo**.
5. Demo mode injects fake data into dashboard filters.

**Demo data includes:**
- **3 email templates:** Welcome Email, Monthly Newsletter, Abandoned Cart — each with full HTML.
- **Fake analytics:** 1,247 emails sent, 45.1% open rate, 15.2% click rate, 1.8% bounce rate, plus 30 days of daily stats.
- **2 automations:** Welcome Series (89 runs) and Cart Recovery (156 runs).

**Demo hooks registered:**
- `mailsmart_log_stats` (priority 50) — Injects demo statistics.
- `mailsmart_dashboard_stats` (priority 50) — Injects demo dashboard data.

**Key behaviors:**
- Demo mode is always allowed for reactivation (no anti-abuse limit).
- Demo trials default to 30 days, time-based.
- Demo data is stored in `wp_options` under `mailsmart_demo_data`.

### Feature Gating

Enforces trial feature restrictions and usage limits in real time.

**Hooked filters:**
| Filter | Priority | Purpose |
|--------|----------|---------|
| `mailsmart_email_content` | 5 | Validates trial before email delivery |
| `mailsmart_mailer` | 5 | Blocks email sending when usage limit is reached |
| `mailsmart_log_stats` | 5 | Adds trial metadata to stats |
| `mailsmart_logging_enabled` | 5 | Forces logging on during trials |

**Feature check:** Use `SG_MailSmart_Trials_Feature_Gate::is_feature_enabled( 'ai' )` to check if a specific feature (ai, automation, analytics, templates) is enabled for the current trial user.

### Usage Tracking

Automatically tracks email sends, AI generations, and automation executions.

**Tracked events:**
| Event | Hook | Key |
|-------|------|-----|
| Email sent | `mailsmart_after_send` | `emails` |
| AI generation | `mailsmart_pro_ai_generated` | `ai` |
| Automation executed | `mailsmart_pro_automation_executed` | `automation` |

When a usage limit is exceeded in `usage` or `hybrid` trial types, the trial is automatically expired.

### License Injection

The License Injector hooks into the `mailsmart_license_active` filter at **priority 999** (very late) to inject a "virtual license" for trial/sandbox/demo users.

**Critical rule:** It **never** overrides a real valid license. If `$is_active` is already `true`, it returns `true` without any changes. It only activates the virtual license when no real license is present.

### Anti-Abuse Protection

Prevents trial reactivation abuse:

- **Reactivation limit:** Configurable maximum activations per user per mode (default: 1). Once reached, the user cannot start another trial/sandbox of the same mode.
- **Demo exception:** Demo mode is exempt from reactivation limits.
- **Domain locking (optional):** When `MAILSMART_TRIALS_DOMAIN_LOCK` is defined as `true`, trials are locked to the domain where they were first activated. Enable it in `wp-config.php`:

```php
define( 'MAILSMART_TRIALS_DOMAIN_LOCK', true );
```

### User / Role Control

Restricts which WordPress users can start trials:

- **Allowed roles:** Only users with roles listed in `allowed_roles` can start a trial (default: `administrator`).
- **Empty list:** If `allowed_roles` is empty, all roles are allowed.
- **Admin management:** Only users with `manage_options` capability can manage trials from the admin panel.

### Cron Jobs

Two automated processes run in the background:

1. **Expiry check** (`mailsmart_trials_check_expiry`) — Runs hourly. Iterates all active trial records and expires any that are no longer valid.
2. **Pro status monitor** (`admin_init`) — On every admin page load, checks if the SG MailSmart Pro plugin is active. If Pro is deactivated, sandbox trials are paused. When Pro is reactivated, sandbox trials resume.

---

## Admin Panel

The admin page is located under **MailSmart Trials** in the WordPress admin sidebar. It requires `manage_options` capability (typically administrators only).

### Trial Settings Tab

The main configuration form. All settings are saved at once.

| Setting | Description | Default |
|---------|-------------|---------|
| Enable Trial System | Master switch for the trial system | Off |
| Trial Type | time, usage, feature, or hybrid | time |
| Trial Duration | Duration value + unit (minutes/hours/days) | 7 days |
| Grace Period | Extra hours after expiry before access revoked | 24 hours |
| Usage Limits | Max emails, AI generations, automations (0 = unlimited) | 100 / 20 / 10 |
| Enabled Features | Which Pro features to enable during trial | All (ai, automation, analytics, templates) |
| Max Reactivations | How many times a user can reactivate | 1 |
| Allowed Roles | WordPress roles that can use trials | administrator |

**Important:** The sandbox and demo enable checkboxes live on their own tabs but are saved together with these settings when you click **Save Settings**.

### Sandbox Tab

Controls for sandbox mode:

- **Enable Sandbox** checkbox — Must be checked and settings saved before sandbox can be started.
- **Select User** dropdown — Choose which user to start the sandbox for.
- **Start Sandbox / Stop Sandbox** buttons — Start or stop the sandbox for the selected user.
- **Timer** — Displays remaining time in `HH:MM:SS` format.

### Demo Mode Tab

Controls for demo mode:

- **Enable Demo** checkbox — Must be checked and settings saved before demo can be started.
- **Load Demo Data** button — Populates pre-filled templates, analytics, and automations into the database.
- **Select User** dropdown — Choose which user to start demo for.
- **Start Demo** button — Activates demo mode for the selected user.

### Dashboard Tab

Real-time view of all active trials, sandboxes, and demos:

| Column | Description |
|--------|-------------|
| User | Display name and email |
| Mode | trial / sandbox / demo (color-coded badge) |
| Type | time / usage / feature / hybrid |
| Remaining | Countdown timer |
| Usage | Progress bars for email/AI/automation limits |
| Status | Active or Paused badge |
| Actions | Stop button |

---

## REST API

All endpoints are under the namespace `mailsmart-trial/v1`. All require `manage_options` capability (admin only).

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/start` | Start a trial, sandbox, or demo |
| `GET` | `/status` | Get trial status for a user |
| `POST` | `/stop` | Stop an active trial |
| `GET` | `/settings` | Get current plugin settings |
| `POST` | `/settings` | Update plugin settings |
| `GET` | `/active-trials` | List all active trials |

### POST /start

**Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `user_id` | integer | No | current user | WordPress user ID |
| `mode` | string | No | `trial` | `trial`, `sandbox`, or `demo` |
| `duration` | integer | No | from settings | Custom duration |
| `duration_unit` | string | No | from settings | `minutes`, `hours`, or `days` |
| `trial_type` | string | No | from settings | `time`, `usage`, `feature`, or `hybrid` |
| `features` | array | No | from settings | List of enabled feature keys |

**Example:**
```bash
curl -X POST \
  https://yoursite.com/wp-json/mailsmart-trial/v1/start \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "mode": "sandbox"}'
```

### GET /status

**Parameters:**
| Parameter | Type | Required | Default |
|-----------|------|----------|---------|
| `user_id` | integer | No | current user |

**Response:**
```json
{
  "success": true,
  "data": {
    "active": true,
    "mode": "sandbox",
    "trial_type": "time",
    "started_at": 1711929600,
    "expires_at": 1712534400,
    "remaining_seconds": 518400,
    "paused": false,
    "usage_limits": { "emails": 100, "ai": 20, "automation": 10 },
    "usage_consumed": { "emails": 5, "ai": 0, "automation": 0 },
    "enabled_features": ["ai", "automation", "analytics", "templates"]
  }
}
```

### POST /stop

**Parameters:**
| Parameter | Type | Required |
|-----------|------|----------|
| `user_id` | integer | Yes |

### POST /settings

Accepts a JSON body with any settings keys from the [Settings Reference](#settings-reference). Only provided keys are updated; others remain unchanged.

---

## Settings Reference

All settings are stored in `wp_options` under the key `mailsmart_trial_settings`.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `trial_enabled` | bool | `false` | Master switch for the trial system |
| `trial_duration` | int | `7` | Duration amount |
| `trial_duration_unit` | string | `days` | `minutes`, `hours`, or `days` |
| `trial_grace_period` | int | `24` | Grace period in hours |
| `usage_limit_emails` | int | `100` | Max emails during trial (0 = unlimited) |
| `usage_limit_ai` | int | `20` | Max AI generations (0 = unlimited) |
| `usage_limit_auto` | int | `10` | Max automations (0 = unlimited) |
| `trial_type` | string | `time` | `time`, `usage`, `feature`, or `hybrid` |
| `hybrid_logic` | string | `first` | Hybrid expiry logic (first limit hit) |
| `enabled_features` | array | `['ai','automation','analytics','templates']` | Active feature keys |
| `sandbox_enabled` | bool | `false` | Allow sandbox mode |
| `demo_enabled` | bool | `false` | Allow demo mode |
| `max_reactivations` | int | `1` | Max activations per user per mode |
| `allowed_roles` | array | `['administrator']` | Roles that can use trials |

---

## Hook Reference

### Actions

| Hook | When Fired | Parameters |
|------|-----------|------------|
| `mailsmart_trials_loaded` | After the engine is fully initialized | `$engine` (Engine instance) |
| `mailsmart_trial_started` | When a trial/sandbox/demo is started | `$user_id`, `$mode`, `$record` |
| `mailsmart_trial_expired` | When a trial expires (time/usage/manual) | `$user_id`, `$record` |
| `mailsmart_trial_paused` | When a sandbox is paused (Pro deactivated) | `$user_id`, `$record` |
| `mailsmart_trial_resumed` | When a sandbox is resumed (Pro reactivated) | `$user_id`, `$record` |
| `mailsmart_trial_usage_incremented` | When a usage counter increases | `$key`, `$user_id`, `$record` |

### Filters

| Filter | Purpose | Parameters |
|--------|---------|------------|
| `mailsmart_license_active` | Inject virtual license for trial users | `$is_active`, `$license_manager` |
| `mailsmart_trial_active` | Override whether a trial is considered active | `$active`, `$user_id`, `$status` |
| `mailsmart_trial_limits` | Modify trial usage limits | `$limits`, `$user_id` |

---

## How It All Works Together

### Starting a Trial (Full Flow)

```
Admin Panel → "Start Sandbox" button click
  ↓
JavaScript (admin.js) → AJAX POST to admin-ajax.php
  action: mailsmart_trials_start, mode: sandbox, user_id: X
  ↓
Admin AJAX Handler (class-admin.php → ajax_start_trial)
  → Validates nonce and admin capability
  → Routes to SG_MailSmart_Trials_Sandbox::start()
  ↓
Sandbox::start (class-sandbox.php)
  → Reads settings, checks sandbox_enabled = true
  → Delegates to Manager::start()
  ↓
Manager::start (class-manager.php)
  → Validates mode
  → Checks no existing active trial for user
  → Anti-abuse check (reactivation limit)
  → User control check (allowed roles)
  → Creates trial record via Data::create_record()
  → Saves record to wp_options
  → Records activation in history
  → Fires 'mailsmart_trial_started' action
  ↓
Response returned → Dashboard refreshes
```

### License Injection (How Trial Users Get Pro Access)

```
SG MailSmart Lite/Pro checks license:
  apply_filters('mailsmart_license_active', false, $license_manager)
  ↓
License Injector (priority 999):
  → If $is_active is already true → return true (real license wins)
  → If $is_active is false → check if current user has active trial
  → If trial is active → return true (virtual license granted)
  → Otherwise → return false
```

### Auto-Expiry Flow

```
WP-Cron (hourly) → mailsmart_trials_check_expiry
  ↓
Cron::check_expiry()
  → Loads all trial records
  → For each active record, checks State::is_valid()
  → If invalid (time expired or usage exceeded) → State::expire()
    → Marks is_active = false
    → Fires 'mailsmart_trial_expired' action
```

### Sandbox Pause/Resume

```
Admin page load → admin_init hook
  ↓
Cron::check_pro_status()
  → Checks if SG MailSmart Pro plugin is active
  → For each active sandbox:
    → If Pro inactive AND not paused → State::pause()
      → Saves remaining time, sets paused_at timestamp
    → If Pro active AND paused → State::resume()
      → Recalculates expires_at from remaining time
```

---

## Troubleshooting

### "Sandbox mode is not enabled" / "Demo mode is not enabled"

**Cause:** The sandbox or demo enable checkboxes were not properly saved.

**Solution:**
1. Go to **MailSmart Trials** → **Sandbox** tab and check "Allow sandbox mode for internal testing".
2. Go to **Demo Mode** tab and check "Enable demo mode for showcasing".
3. Go to the **Trial Settings** tab and click **Save Settings** — this saves all settings including the sandbox/demo toggles.
4. Now try starting sandbox or demo again.

### Plugin not loading in production

**Cause:** The plugin has a safety guard that prevents it from loading when `WP_DEBUG` is `false`.

**Solution:** Add this to your `wp-config.php`:
```php
define( 'MAILSMART_TRIALS_ENABLED', true );
```

### "A trial is already active for this user"

**Cause:** Each user can only have one active trial/sandbox/demo at a time.

**Solution:** Stop the existing trial first (from the Dashboard tab or via the REST API), then start a new one.

### "Maximum N activation(s) reached for this mode"

**Cause:** The user has reached the reactivation limit.

**Solution:**
- Increase `max_reactivations` in the Trial Settings tab.
- Or use demo mode, which has no reactivation limit.

### "This user is not allowed to start a trial"

**Cause:** The user's WordPress role is not in the `allowed_roles` list.

**Solution:** Add their role to the **Allowed Roles** checkboxes in Trial Settings.

### Sandbox auto-pauses immediately

**Cause:** The SG MailSmart Pro plugin is not active. Sandbox mode automatically pauses when Pro is deactivated.

**Solution:** Activate the SG MailSmart Pro plugin. The sandbox will resume automatically.

### Demo data not showing on dashboard

**Cause:** Demo data was not loaded, or demo mode was not started for the current user.

**Solution:**
1. Click **Load Demo Data** on the Demo Mode tab.
2. Select a user and click **Start Demo**.
3. The demo data is injected via filters — it appears on the main MailSmart dashboard, not on the Trials dashboard.

### Timer shows `--:--:--`

**Cause:** No active sandbox is running for the selected user.

**Solution:** Start a sandbox first. The timer updates only after a successful start.

---

## FAQ

### Can I run sandbox and demo at the same time for different users?

Yes. Each user has their own independent trial record. User A can be in sandbox mode while User B is in demo mode.

### Can the same user have a sandbox and trial simultaneously?

No. Each user can only have one active trial/sandbox/demo at a time. Stop the current one before starting a different mode.

### Does deactivating the Trials Engine plugin delete trial data?

No. Deactivation only clears the cron schedule. All trial records and settings are preserved in `wp_options`.

### How is the grace period calculated?

The grace period (in hours) is added to the `expires_at` timestamp. During the grace period, the trial is still considered valid, giving users extra time after nominal expiry.

### Can I control trials programmatically?

Yes, via the REST API or by calling the PHP classes directly:

```php
// Start a trial
$result = SG_MailSmart_Trials_Sandbox::start( $user_id );

// Check if active
$is_active = SG_MailSmart_Trials_Manager::is_active( $user_id );

// Get status
$status = SG_MailSmart_Trials_Manager::status( $user_id );

// Stop a trial
SG_MailSmart_Trials_Manager::stop( $user_id );
```

### What data is stored and where?

| Option Key | Contents |
|------------|----------|
| `mailsmart_trial_settings` | Plugin configuration |
| `mailsmart_trial_data` | Active/expired trial records (keyed by user ID) |
| `mailsmart_trial_history` | Activation history for anti-abuse tracking |
| `mailsmart_demo_data` | Demo templates, analytics, and automations |
