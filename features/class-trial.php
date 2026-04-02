<?php
/**
 * Trial feature – time-based, usage-based, feature-based, and hybrid trials.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Trial {

    /**
     * Register trial hooks.
     */
    public static function register(): void {
        // Expose trial limits filter.
        add_filter( 'mailsmart_trial_limits', array( __CLASS__, 'filter_limits' ), 10, 2 );
    }

    /**
     * Start a trial for a specific user.
     *
     * @param int                  $user_id   WordPress user ID.
     * @param array<string,mixed>  $overrides Optional setting overrides.
     * @return array<string,mixed>|WP_Error
     */
    public static function start( int $user_id, array $overrides = array() ) {
        return SG_MailSmart_Trials_Manager::start( $user_id, 'trial', $overrides );
    }

    /**
     * Stop a trial.
     *
     * @param int $user_id WordPress user ID.
     * @return bool|WP_Error
     */
    public static function stop( int $user_id ) {
        return SG_MailSmart_Trials_Manager::stop( $user_id );
    }

    /**
     * Filter trial limits – allows external modification.
     *
     * @param array<string,int> $limits  Current limits.
     * @param int               $user_id WordPress user ID.
     * @return array<string,int>
     */
    public static function filter_limits( array $limits, int $user_id ): array {
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( $record && ! empty( $record['is_active'] ) ) {
            return $record['usage_limits'] ?? $limits;
        }
        return $limits;
    }
}
