<?php
/**
 * Trial data value object – stored per-user in wp_options.
 *
 * Option key: mailsmart_trial_data  (serialized array keyed by user_id)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Data {

    /** @var string */
    const OPTION_KEY = 'mailsmart_trial_data';

    /** @var string */
    const HISTORY_KEY = 'mailsmart_trial_history';

    /**
     * Get trial data for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string,mixed>|null
     */
    public static function get( int $user_id ): ?array {
        $all = get_option( self::OPTION_KEY, array() );
        return isset( $all[ $user_id ] ) ? $all[ $user_id ] : null;
    }

    /**
     * Save trial data for a user.
     *
     * @param int                $user_id WordPress user ID.
     * @param array<string,mixed> $data   Trial data fields.
     */
    public static function save( int $user_id, array $data ): void {
        $all              = get_option( self::OPTION_KEY, array() );
        $all[ $user_id ]  = $data;
        update_option( self::OPTION_KEY, $all, false );
    }

    /**
     * Delete trial data for a user.
     *
     * @param int $user_id WordPress user ID.
     */
    public static function delete( int $user_id ): void {
        $all = get_option( self::OPTION_KEY, array() );
        unset( $all[ $user_id ] );
        update_option( self::OPTION_KEY, $all, false );
    }

    /**
     * Get all trial records.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(): array {
        return get_option( self::OPTION_KEY, array() );
    }

    /**
     * Build a fresh trial data structure.
     *
     * @param int    $user_id  WordPress user ID.
     * @param string $mode     trial | sandbox | demo
     * @param array<string,mixed> $settings Plugin settings.
     * @return array<string,mixed>
     */
    public static function create_record( int $user_id, string $mode, array $settings ): array {
        $now      = time();
        $duration = self::compute_duration_seconds( $settings );

        return array(
            'user_id'           => $user_id,
            'started_at'        => $now,
            'expires_at'        => $now + $duration,
            'remaining_seconds' => $duration,
            'paused_at'         => 0,
            'usage_limits'      => array(
                'emails'     => absint( $settings['usage_limit_emails'] ?? 100 ),
                'ai'         => absint( $settings['usage_limit_ai'] ?? 20 ),
                'automation' => absint( $settings['usage_limit_auto'] ?? 10 ),
            ),
            'usage_consumed'    => array(
                'emails'     => 0,
                'ai'         => 0,
                'automation' => 0,
            ),
            'enabled_features'  => $settings['enabled_features'] ?? array( 'ai', 'automation', 'analytics', 'templates' ),
            'is_active'         => true,
            'mode'              => sanitize_key( $mode ),
            'trial_type'        => sanitize_key( $settings['trial_type'] ?? 'time' ),
            'grace_period'      => absint( $settings['trial_grace_period'] ?? 24 ) * HOUR_IN_SECONDS,
        );
    }

    /**
     * Convert configured duration to seconds.
     *
     * @param array<string,mixed> $settings Plugin settings.
     * @return int
     */
    public static function compute_duration_seconds( array $settings ): int {
        $amount = max( 1, absint( $settings['trial_duration'] ?? 7 ) );
        $unit   = $settings['trial_duration_unit'] ?? 'days';

        switch ( $unit ) {
            case 'minutes':
                return $amount * MINUTE_IN_SECONDS;
            case 'hours':
                return $amount * HOUR_IN_SECONDS;
            case 'days':
            default:
                return $amount * DAY_IN_SECONDS;
        }
    }

    /**
     * Record activation in history (anti-abuse).
     *
     * @param int    $user_id WordPress user ID.
     * @param string $mode    trial | sandbox | demo
     */
    public static function record_activation( int $user_id, string $mode ): void {
        $history = get_option( self::HISTORY_KEY, array() );
        if ( ! isset( $history[ $user_id ] ) ) {
            $history[ $user_id ] = array();
        }
        $history[ $user_id ][] = array(
            'mode'       => $mode,
            'started_at' => time(),
            'ip'         => self::get_client_ip(),
            'domain'     => wp_parse_url( home_url(), PHP_URL_HOST ),
        );
        update_option( self::HISTORY_KEY, $history, false );
    }

    /**
     * Get activation count for a user.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $mode    trial | sandbox | demo
     * @return int
     */
    public static function get_activation_count( int $user_id, string $mode ): int {
        $history = get_option( self::HISTORY_KEY, array() );
        if ( ! isset( $history[ $user_id ] ) ) {
            return 0;
        }
        $count = 0;
        foreach ( $history[ $user_id ] as $entry ) {
            if ( $entry['mode'] === $mode ) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private static function get_client_ip(): string {
        $ip = '';
        if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && '' !== $_SERVER['HTTP_X_FORWARDED_FOR'] ) {
            $parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip    = trim( $parts[0] );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) && '' !== $_SERVER['REMOTE_ADDR'] ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return $ip;
    }
}
