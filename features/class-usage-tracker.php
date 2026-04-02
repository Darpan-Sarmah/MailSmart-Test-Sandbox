<?php
/**
 * Usage Tracker – hooks into Lite/Pro actions to track emails, AI, automations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Usage_Tracker {

    /**
     * Register usage-tracking hooks.
     */
    public static function register(): void {
        // Track emails sent.
        add_action( 'mailsmart_after_send', array( __CLASS__, 'track_email' ), 10, 2 );

        // Track AI generations (Pro hook).
        add_action( 'mailsmart_pro_ai_generated', array( __CLASS__, 'track_ai' ), 10, 1 );

        // Track automation executions (Pro hook).
        add_action( 'mailsmart_pro_automation_executed', array( __CLASS__, 'track_automation' ), 10, 1 );

        // Fallback: track via REST endpoints if Pro hooks aren't available.
        add_filter( 'mailsmart_email_content', array( __CLASS__, 'pre_send_check' ), 1, 2 );
    }

    /**
     * Track an email send event.
     *
     * @param mixed $result Send result.
     * @param mixed $email  Email DTO.
     */
    public static function track_email( $result = null, $email = null ): void {
        self::increment( 'emails' );
    }

    /**
     * Track an AI generation event.
     *
     * @param mixed $generation AI generation data.
     */
    public static function track_ai( $generation = null ): void {
        self::increment( 'ai' );
    }

    /**
     * Track an automation execution event.
     *
     * @param mixed $execution Automation data.
     */
    public static function track_automation( $execution = null ): void {
        self::increment( 'automation' );
    }

    /**
     * Pre-send check: verify usage limits before email goes through pipeline.
     *
     * @param string $message Email content.
     * @param mixed  $email   Email DTO.
     * @return string
     */
    public static function pre_send_check( string $message, $email = null ): string {
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return $message;
        }

        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( ! $record || empty( $record['is_active'] ) ) {
            return $message;
        }

        $type = $record['trial_type'] ?? 'time';
        if ( ! in_array( $type, array( 'usage', 'hybrid' ), true ) ) {
            return $message;
        }

        // Check if usage limit exceeded – if so, expire the trial.
        if ( ! SG_MailSmart_Trials_State::check_usage( $record ) ) {
            SG_MailSmart_Trials_State::expire( $user_id );
        }

        return $message;
    }

    /**
     * Increment a usage counter for the current user.
     *
     * @param string $key Usage key (emails, ai, automation).
     */
    private static function increment( string $key ): void {
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return;
        }

        $record = SG_MailSmart_Trials_Data::get( $user_id );
        if ( ! $record || empty( $record['is_active'] ) ) {
            return;
        }

        if ( ! isset( $record['usage_consumed'][ $key ] ) ) {
            $record['usage_consumed'][ $key ] = 0;
        }

        $record['usage_consumed'][ $key ]++;
        SG_MailSmart_Trials_Data::save( $user_id, $record );

        // Check if any usage limit is now exceeded.
        $type = $record['trial_type'] ?? 'time';
        if ( in_array( $type, array( 'usage', 'hybrid' ), true ) ) {
            if ( ! SG_MailSmart_Trials_State::check_usage( $record ) ) {
                SG_MailSmart_Trials_State::expire( $user_id );
            }
        }

        /**
         * Fires when trial usage is incremented.
         *
         * @param string $key     The usage key incremented.
         * @param int    $user_id The user ID.
         * @param array  $record  Updated trial record.
         */
        do_action( 'mailsmart_trial_usage_incremented', $key, $user_id, $record );
    }
}
