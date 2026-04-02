<?php
/**
 * Anti-Abuse – prevents trial reactivation abuse.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Anti_Abuse {

    /**
     * Check whether a user can activate a new trial.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $mode    trial | sandbox | demo
     * @return true|WP_Error
     */
    public static function can_activate( int $user_id, string $mode ) {
        $settings         = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        $max_reactivations = absint( $settings['max_reactivations'] ?? 1 );

        // Demo mode is always allowed (no reactivation limit).
        if ( 'demo' === $mode ) {
            return true;
        }

        $count = SG_MailSmart_Trials_Data::get_activation_count( $user_id, $mode );

        if ( $count >= $max_reactivations ) {
            return new WP_Error(
                'reactivation_limit',
                sprintf(
                    /* translators: %d: maximum allowed activations */
                    __( 'Maximum %d activation(s) reached for this mode.', 'sg-mailsmart-trials' ),
                    $max_reactivations
                ),
                array( 'status' => 403 )
            );
        }

        // Domain lock check (placeholder for advanced anti-abuse).
        $domain_check = self::check_domain( $user_id );
        if ( is_wp_error( $domain_check ) ) {
            return $domain_check;
        }

        return true;
    }

    /**
     * Domain binding check placeholder.
     *
     * @param int $user_id WordPress user ID.
     * @return true|WP_Error
     */
    private static function check_domain( int $user_id ) {
        // Check if domain locking is enabled via constant.
        if ( ! defined( 'MAILSMART_TRIALS_DOMAIN_LOCK' ) || ! MAILSMART_TRIALS_DOMAIN_LOCK ) {
            return true;
        }

        $history       = get_option( SG_MailSmart_Trials_Data::HISTORY_KEY, array() );
        $user_history  = $history[ $user_id ] ?? array();
        $current_domain = wp_parse_url( home_url(), PHP_URL_HOST );

        foreach ( $user_history as $entry ) {
            if ( ! empty( $entry['domain'] ) && $entry['domain'] !== $current_domain ) {
                return new WP_Error(
                    'domain_mismatch',
                    __( 'Trial cannot be activated on a different domain.', 'sg-mailsmart-trials' ),
                    array( 'status' => 403 )
                );
            }
        }

        return true;
    }
}
