<?php
/**
 * State machine for trial / sandbox / demo lifecycle.
 *
 * States: inactive → active → paused → active → expired
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_State {

    /**
     * Check if a trial record is currently valid (considering time, usage, grace).
     *
     * @param array<string,mixed> $record Trial data record.
     * @return bool
     */
    public static function is_valid( array $record ): bool {
        if ( empty( $record ) || empty( $record['is_active'] ) ) {
            return false;
        }

        $trial_type = $record['trial_type'] ?? 'time';
        $now        = time();

        switch ( $trial_type ) {
            case 'usage':
                return self::check_usage( $record );

            case 'feature':
                return self::check_time( $record, $now );

            case 'hybrid':
                // Both time AND usage must still be within limits.
                // "first" = whichever limit is hit first invalidates.
                return self::check_time( $record, $now ) && self::check_usage( $record );

            case 'time':
            default:
                return self::check_time( $record, $now );
        }
    }

    /**
     * Check time-based validity including grace period.
     *
     * @param array<string,mixed> $record Trial record.
     * @param int                  $now    Current timestamp.
     * @return bool
     */
    public static function check_time( array $record, int $now ): bool {
        if ( ! empty( $record['paused_at'] ) ) {
            // Paused trials don't expire until resumed.
            return true;
        }
        $grace   = absint( $record['grace_period'] ?? 0 );
        $expires = absint( $record['expires_at'] ?? 0 );
        return $now <= ( $expires + $grace );
    }

    /**
     * Check usage-based limits.
     *
     * @param array<string,mixed> $record Trial record.
     * @return bool
     */
    public static function check_usage( array $record ): bool {
        $limits   = $record['usage_limits'] ?? array();
        $consumed = $record['usage_consumed'] ?? array();

        foreach ( $limits as $key => $limit ) {
            if ( $limit > 0 && ( $consumed[ $key ] ?? 0 ) >= $limit ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Pause a trial (e.g., when Pro plugin becomes inactive).
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function pause( int $user_id ): bool {
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( ! $record || ! $record['is_active'] || ! empty( $record['paused_at'] ) ) {
            return false;
        }

        $now                        = time();
        $record['paused_at']        = $now;
        $record['remaining_seconds'] = max( 0, $record['expires_at'] - $now );

        SG_MailSmart_Trials_Data::save( $user_id, $record );

        /**
         * Fires when a trial is paused.
         *
         * @param int   $user_id The user whose trial was paused.
         * @param array $record  The updated trial record.
         */
        do_action( 'mailsmart_trial_paused', $user_id, $record );

        return true;
    }

    /**
     * Resume a paused trial.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function resume( int $user_id ): bool {
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( ! $record || ! $record['is_active'] || empty( $record['paused_at'] ) ) {
            return false;
        }

        $remaining             = max( 0, absint( $record['remaining_seconds'] ) );
        $record['expires_at']  = time() + $remaining;
        $record['paused_at']   = 0;

        SG_MailSmart_Trials_Data::save( $user_id, $record );

        /**
         * Fires when a trial is resumed.
         *
         * @param int   $user_id The user whose trial was resumed.
         * @param array $record  The updated trial record.
         */
        do_action( 'mailsmart_trial_resumed', $user_id, $record );

        return true;
    }

    /**
     * Expire a trial.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function expire( int $user_id ): bool {
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( ! $record ) {
            return false;
        }

        $record['is_active'] = false;
        $record['expired_at'] = time();

        SG_MailSmart_Trials_Data::save( $user_id, $record );

        /**
         * Fires when a trial expires.
         *
         * @param int   $user_id The user whose trial expired.
         * @param array $record  The expired trial record.
         */
        do_action( 'mailsmart_trial_expired', $user_id, $record );

        return true;
    }

    /**
     * Get remaining seconds for a record.
     *
     * @param array<string,mixed> $record Trial record.
     * @return int
     */
    public static function remaining_seconds( array $record ): int {
        if ( ! empty( $record['paused_at'] ) ) {
            return max( 0, absint( $record['remaining_seconds'] ) );
        }
        return max( 0, absint( $record['expires_at'] ) - time() );
    }
}
