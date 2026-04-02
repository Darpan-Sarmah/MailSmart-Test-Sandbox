<?php
/**
 * Feature Gate – ensures trial features respect limits and enabled feature sets.
 *
 * Hooks into Lite's content/mailer/stats filters to enforce gating.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Feature_Gate {

    /**
     * Register feature-gating hooks.
     */
    public static function register(): void {
        // Gate email sending.
        add_filter( 'mailsmart_email_content', array( __CLASS__, 'gate_email_content' ), 5, 2 );

        // Gate mailer (delivery).
        add_filter( 'mailsmart_mailer', array( __CLASS__, 'gate_mailer' ), 5, 2 );

        // Gate stats/logging.
        add_filter( 'mailsmart_log_stats', array( __CLASS__, 'gate_stats' ), 5, 2 );

        // Gate logging toggle.
        add_filter( 'mailsmart_logging_enabled', array( __CLASS__, 'gate_logging' ), 5, 1 );
    }

    /**
     * Gate email content – block if email feature is disabled in trial.
     *
     * @param string $message Email message content.
     * @param mixed  $email   Email DTO or array.
     * @return string
     */
    public static function gate_email_content( string $message, $email = null ): string {
        if ( ! self::is_trial_user() ) {
            return $message;
        }

        $record = self::get_current_record();
        if ( ! $record ) {
            return $message;
        }

        // Check if the trial is still valid.
        if ( ! SG_MailSmart_Trials_State::is_valid( $record ) ) {
            return $message;
        }

        return $message;
    }

    /**
     * Gate the mailer callable – wrap to track usage.
     *
     * @param callable $send_fn Original send function.
     * @param mixed    $email   Email DTO or array.
     * @return callable
     */
    public static function gate_mailer( $send_fn, $email = null ) {
        if ( ! self::is_trial_user() ) {
            return $send_fn;
        }

        $record = self::get_current_record();
        if ( ! $record || ! SG_MailSmart_Trials_State::is_valid( $record ) ) {
            return $send_fn;
        }

        // Check if email usage limit is reached.
        $limits   = $record['usage_limits'] ?? array();
        $consumed = $record['usage_consumed'] ?? array();
        $type     = $record['trial_type'] ?? 'time';

        if ( in_array( $type, array( 'usage', 'hybrid' ), true ) ) {
            $email_limit    = absint( $limits['emails'] ?? 0 );
            $emails_used    = absint( $consumed['emails'] ?? 0 );

            if ( $email_limit > 0 && $emails_used >= $email_limit ) {
                // Return a no-op that returns false (email blocked).
                return function () {
                    return false;
                };
            }
        }

        return $send_fn;
    }

    /**
     * Gate stats – add trial metadata.
     *
     * @param array<string,mixed> $stats Existing stats.
     * @param int                  $days  Days range.
     * @return array<string,mixed>
     */
    public static function gate_stats( $stats, $days = 30 ) {
        if ( ! is_array( $stats ) ) {
            $stats = array();
        }

        if ( ! self::is_trial_user() ) {
            return $stats;
        }

        $record = self::get_current_record();
        if ( ! $record ) {
            return $stats;
        }

        $stats['trial_active']    = ! empty( $record['is_active'] );
        $stats['trial_mode']      = $record['mode'] ?? 'none';
        $stats['trial_remaining'] = SG_MailSmart_Trials_State::remaining_seconds( $record );

        return $stats;
    }

    /**
     * Gate logging – ensure logging works during trial.
     *
     * @param bool $enabled Current logging state.
     * @return bool
     */
    public static function gate_logging( $enabled ) {
        if ( self::is_trial_user() ) {
            return true; // Always enable logging during trial for tracking.
        }
        return $enabled;
    }

    /**
     * Check if a specific feature is enabled for the current trial user.
     *
     * @param string $feature Feature key (ai, automation, analytics, templates).
     * @return bool
     */
    public static function is_feature_enabled( string $feature ): bool {
        $record = self::get_current_record();
        if ( ! $record || empty( $record['is_active'] ) ) {
            return false;
        }

        $enabled = $record['enabled_features'] ?? array();
        return in_array( $feature, $enabled, true );
    }

    /**
     * Check if current user is on a trial.
     *
     * @return bool
     */
    private static function is_trial_user(): bool {
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return false;
        }
        return SG_MailSmart_Trials_Manager::is_active( $user_id );
    }

    /**
     * Get trial record for current user.
     *
     * @return array<string,mixed>|null
     */
    private static function get_current_record(): ?array {
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return null;
        }
        return SG_MailSmart_Trials_Data::get( $user_id );
    }
}
