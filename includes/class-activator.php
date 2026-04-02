<?php
/**
 * Activation and deactivation routines.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        // Set default options if they don't exist.
        if ( false === get_option( 'mailsmart_trial_settings' ) ) {
            update_option( 'mailsmart_trial_settings', self::default_settings(), false );
        }

        // Schedule cron events.
        if ( ! wp_next_scheduled( 'mailsmart_trials_check_expiry' ) ) {
            wp_schedule_event( time(), 'hourly', 'mailsmart_trials_check_expiry' );
        }

        // Flush rewrite rules for REST endpoints.
        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'mailsmart_trials_check_expiry' );
    }

    /**
     * Default plugin settings.
     *
     * @return array<string,mixed>
     */
    public static function default_settings(): array {
        return array(
            'trial_enabled'        => false,
            'trial_duration'       => 7,
            'trial_duration_unit'  => 'days', // days | hours | minutes
            'trial_grace_period'   => 24,     // hours
            'usage_limit_emails'   => 100,
            'usage_limit_ai'       => 20,
            'usage_limit_auto'     => 10,
            'trial_type'           => 'time', // time | usage | feature | hybrid
            'hybrid_logic'         => 'first', // first (whichever limit hit first)
            'enabled_features'     => array( 'ai', 'automation', 'analytics', 'templates' ),
            'sandbox_enabled'      => false,
            'demo_enabled'         => false,
            'max_reactivations'    => 1,
            'allowed_roles'        => array( 'administrator' ),
        );
    }
}
