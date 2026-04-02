<?php
/**
 * Trial Manager – orchestrates start / stop / status for trials, sandbox, demo.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Manager {

    /**
     * Start a new trial / sandbox / demo for a user.
     *
     * @param int    $user_id  WordPress user ID.
     * @param string $mode     trial | sandbox | demo
     * @param array<string,mixed> $overrides Optional setting overrides.
     * @return array<string,mixed>|WP_Error Trial record on success.
     */
    public static function start( int $user_id, string $mode = 'trial', array $overrides = array() ) {
        // Validate mode.
        if ( ! in_array( $mode, array( 'trial', 'sandbox', 'demo' ), true ) ) {
            return new WP_Error( 'invalid_mode', __( 'Invalid trial mode.', 'sg-mailsmart-trials' ) );
        }

        // Check if already active.
        $existing = SG_MailSmart_Trials_Data::get( $user_id );
        if ( $existing && ! empty( $existing['is_active'] ) ) {
            return new WP_Error( 'already_active', __( 'A trial is already active for this user.', 'sg-mailsmart-trials' ) );
        }

        // Anti-abuse check.
        $abuse_check = SG_MailSmart_Trials_Anti_Abuse::can_activate( $user_id, $mode );
        if ( is_wp_error( $abuse_check ) ) {
            return $abuse_check;
        }

        // User control check.
        if ( ! SG_MailSmart_Trials_User_Control::user_allowed( $user_id ) ) {
            return new WP_Error( 'user_not_allowed', __( 'This user is not allowed to start a trial.', 'sg-mailsmart-trials' ) );
        }

        // Build settings with overrides.
        $settings = wp_parse_args( $overrides, get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() ) );

        // Create and save record.
        $record = SG_MailSmart_Trials_Data::create_record( $user_id, $mode, $settings );
        SG_MailSmart_Trials_Data::save( $user_id, $record );
        SG_MailSmart_Trials_Data::record_activation( $user_id, $mode );

        /**
         * Fires when a trial is started.
         *
         * @param int    $user_id The user ID.
         * @param string $mode    The trial mode.
         * @param array  $record  The trial record.
         */
        do_action( 'mailsmart_trial_started', $user_id, $mode, $record );

        return $record;
    }

    /**
     * Stop an active trial for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return bool|WP_Error
     */
    public static function stop( int $user_id ) {
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( ! $record || empty( $record['is_active'] ) ) {
            return new WP_Error( 'not_active', __( 'No active trial found for this user.', 'sg-mailsmart-trials' ) );
        }

        return SG_MailSmart_Trials_State::expire( $user_id );
    }

    /**
     * Get current trial status for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string,mixed>
     */
    public static function status( int $user_id ): array {
        $record = SG_MailSmart_Trials_Data::get( $user_id );

        if ( ! $record ) {
            return array(
                'active'    => false,
                'mode'      => 'none',
                'remaining' => 0,
                'usage'     => array(),
                'features'  => array(),
            );
        }

        // Runtime validation – auto-expire if invalid.
        if ( $record['is_active'] && ! SG_MailSmart_Trials_State::is_valid( $record ) ) {
            SG_MailSmart_Trials_State::expire( $user_id );
            $record = SG_MailSmart_Trials_Data::get( $user_id );
        }

        $is_active = ! empty( $record['is_active'] ) && SG_MailSmart_Trials_State::is_valid( $record );

        return array(
            'active'            => $is_active,
            'mode'              => $record['mode'] ?? 'none',
            'trial_type'        => $record['trial_type'] ?? 'time',
            'started_at'        => $record['started_at'] ?? 0,
            'expires_at'        => $record['expires_at'] ?? 0,
            'remaining_seconds' => SG_MailSmart_Trials_State::remaining_seconds( $record ),
            'paused'            => ! empty( $record['paused_at'] ),
            'usage_limits'      => $record['usage_limits'] ?? array(),
            'usage_consumed'    => $record['usage_consumed'] ?? array(),
            'enabled_features'  => $record['enabled_features'] ?? array(),
        );
    }

    /**
     * Check whether any trial / sandbox / demo is currently valid for a user.
     *
     * @param int $user_id WordPress user ID (0 = current user).
     * @return bool
     */
    public static function is_active( int $user_id = 0 ): bool {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( 0 === $user_id ) {
            return false;
        }

        $status = self::status( $user_id );

        /**
         * Filters whether the trial is considered active.
         *
         * @param bool  $active  Current active status.
         * @param int   $user_id The user ID.
         * @param array $status  Full status array.
         */
        return (bool) apply_filters( 'mailsmart_trial_active', $status['active'], $user_id, $status );
    }

    /**
     * Get all active trial records.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_active_trials(): array {
        $all    = SG_MailSmart_Trials_Data::get_all();
        $active = array();

        foreach ( $all as $user_id => $record ) {
            if ( ! empty( $record['is_active'] ) && SG_MailSmart_Trials_State::is_valid( $record ) ) {
                $active[ $user_id ] = $record;
            }
        }

        return $active;
    }
}
