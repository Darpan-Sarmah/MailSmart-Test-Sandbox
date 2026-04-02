<?php
/**
 * Plugin Name: SG MailSmart Trials Engine
 * Plugin URI:  https://sgmailsmart.com/trials-engine
 * Description: Sandbox, Trial and Demo system for SG MailSmart AI Lite & Pro plugins. Provides time-based, usage-based, feature-based and hybrid trial modes with full admin controls.
 * Version:     1.0.0
 * Author:      SG MailSmart
 * Author URI:  https://sgmailsmart.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sg-mailsmart-trials
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ─── Development Safety Guard ─────────────────────────────────────────── */
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
    if ( ! defined( 'MAILSMART_TRIALS_ENABLED' ) || ! MAILSMART_TRIALS_ENABLED ) {
        return; // Plugin inactive in production unless explicitly enabled
    }
}

/* ─── Constants ────────────────────────────────────────────────────────── */
define( 'MAILSMART_TRIALS_VERSION', '1.0.0' );
define( 'MAILSMART_TRIALS_PLUGIN_FILE', __FILE__ );
define( 'MAILSMART_TRIALS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAILSMART_TRIALS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MAILSMART_TRIALS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/* ─── Autoloader ───────────────────────────────────────────────────────── */
require_once MAILSMART_TRIALS_PLUGIN_DIR . 'includes/class-autoloader.php';
SG_MailSmart_Trials_Autoloader::register();

/* ─── Activation / Deactivation ────────────────────────────────────────── */
register_activation_hook( __FILE__, array( 'SG_MailSmart_Trials_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SG_MailSmart_Trials_Activator', 'deactivate' ) );

/* ─── Bootstrap ────────────────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    SG_MailSmart_Trials_Engine::instance();
}, 20 ); // After Lite (10) and Pro (1→10) are loaded
