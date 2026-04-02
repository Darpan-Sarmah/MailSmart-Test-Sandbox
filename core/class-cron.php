<?php
/**
 * Cron handler – checks for expired trials and pauses when Pro is inactive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Cron {

    /**
     * Register cron hooks.
     */
    public static function register(): void {
        add_action( 'mailsmart_trials_check_expiry', array( __CLASS__, 'check_expiry' ) );
        add_action( 'admin_init', array( __CLASS__, 'check_pro_status' ) );
    }

    /**
     * Iterate all active trials and expire those that are no longer valid.
     */
    public static function check_expiry(): void {
        $all = SG_MailSmart_Trials_Data::get_all();

        foreach ( $all as $user_id => $record ) {
            if ( empty( $record['is_active'] ) ) {
                continue;
            }

            if ( ! SG_MailSmart_Trials_State::is_valid( $record ) ) {
                SG_MailSmart_Trials_State::expire( (int) $user_id );
            }
        }
    }

    /**
     * Auto-pause sandbox/trial when Pro plugin is inactive.
     * Auto-resume when Pro plugin is reactivated.
     */
    public static function check_pro_status(): void {
        $pro_active = self::is_pro_plugin_active();
        $all        = SG_MailSmart_Trials_Data::get_all();

        foreach ( $all as $user_id => $record ) {
            if ( empty( $record['is_active'] ) ) {
                continue;
            }

            $mode = $record['mode'] ?? '';

            // Sandbox pauses when Pro is inactive.
            if ( 'sandbox' === $mode ) {
                if ( ! $pro_active && empty( $record['paused_at'] ) ) {
                    SG_MailSmart_Trials_State::pause( (int) $user_id );
                } elseif ( $pro_active && ! empty( $record['paused_at'] ) ) {
                    SG_MailSmart_Trials_State::resume( (int) $user_id );
                }
            }
        }
    }

    /**
     * Check if the SG MailSmart Pro plugin is active.
     *
     * @return bool
     */
    private static function is_pro_plugin_active(): bool {
        if ( defined( 'MAILSMART_PRO_VERSION' ) ) {
            return true;
        }
        return function_exists( 'is_plugin_active' ) &&
               is_plugin_active( 'sg-mailsmart-pro/sg-mailsmart-pro.php' );
    }
}
