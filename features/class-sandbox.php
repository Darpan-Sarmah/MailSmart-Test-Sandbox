<?php
/**
 * Sandbox mode – admin-controlled, user-specific, pauses when Pro inactive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Sandbox {

    /**
     * Register sandbox hooks.
     */
    public static function register(): void {
        // Nothing to register at this level; sandbox leverages core Manager/State.
    }

    /**
     * Start sandbox for a user.
     *
     * @param int                  $user_id   WordPress user ID.
     * @param array<string,mixed>  $overrides Optional setting overrides.
     * @return array<string,mixed>|WP_Error
     */
    public static function start( int $user_id, array $overrides = array() ) {
        $settings = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        if ( empty( $settings['sandbox_enabled'] ) ) {
            return new WP_Error( 'sandbox_disabled', __( 'Sandbox mode is not enabled.', 'sg-mailsmart-trials' ) );
        }

        return SG_MailSmart_Trials_Manager::start( $user_id, 'sandbox', $overrides );
    }

    /**
     * Stop sandbox for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return bool|WP_Error
     */
    public static function stop( int $user_id ) {
        return SG_MailSmart_Trials_Manager::stop( $user_id );
    }

    /**
     * Check if a user is currently in sandbox mode.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function is_sandbox( int $user_id ): bool {
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        return $record
            && 'sandbox' === ( $record['mode'] ?? '' )
            && ! empty( $record['is_active'] )
            && SG_MailSmart_Trials_State::is_valid( $record );
    }
}
