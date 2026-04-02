<?php
/**
 * Demo mode – pre-filled templates, fake analytics, simulated activity.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Demo {

    /** @var string Option key for demo data. */
    const DEMO_DATA_KEY = 'mailsmart_demo_data';

    /**
     * Register demo hooks.
     */
    public static function register(): void {
        $settings = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        if ( ! empty( $settings['demo_enabled'] ) ) {
            add_filter( 'mailsmart_log_stats', array( __CLASS__, 'inject_demo_stats' ), 50, 2 );
            add_filter( 'mailsmart_dashboard_stats', array( __CLASS__, 'inject_demo_dashboard' ), 50, 2 );
        }
    }

    /**
     * Start demo mode for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string,mixed>|WP_Error
     */
    public static function start( int $user_id ) {
        $settings = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        if ( empty( $settings['demo_enabled'] ) ) {
            return new WP_Error( 'demo_disabled', __( 'Demo mode is not enabled.', 'sg-mailsmart-trials' ) );
        }

        // Load demo data.
        self::load_demo_data();

        return SG_MailSmart_Trials_Manager::start( $user_id, 'demo', array(
            'trial_duration'      => 30,
            'trial_duration_unit' => 'days',
            'trial_type'          => 'time',
        ) );
    }

    /**
     * Load demo templates and fake data into wp_options.
     */
    public static function load_demo_data(): void {
        $demo_data = array(
            'templates' => array(
                array(
                    'name'     => 'Welcome Email',
                    'category' => 'onboarding',
                    'html'     => '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px"><h1 style="color:#2563eb">Welcome to Our Platform!</h1><p>We\'re excited to have you on board. Here\'s what you can do:</p><ul><li>Send AI-powered emails</li><li>Set up automations</li><li>Track analytics</li></ul><p>Get started now!</p></div>',
                ),
                array(
                    'name'     => 'Monthly Newsletter',
                    'category' => 'marketing',
                    'html'     => '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px"><h1 style="color:#059669">Monthly Updates</h1><p>Here are this month\'s highlights:</p><h3>🚀 New Features</h3><p>AI-powered subject line generation is now available.</p><h3>📊 Your Stats</h3><p>You sent 150 emails with a 45% open rate.</p></div>',
                ),
                array(
                    'name'     => 'Abandoned Cart',
                    'category' => 'automation',
                    'html'     => '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px"><h1 style="color:#dc2626">You left something behind!</h1><p>Your cart is waiting for you. Complete your purchase today and enjoy free shipping.</p><a href="#" style="display:inline-block;background:#dc2626;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none">Complete Purchase</a></div>',
                ),
            ),
            'analytics' => array(
                'total_sent'      => 1247,
                'total_opened'    => 562,
                'total_clicked'   => 189,
                'total_bounced'   => 23,
                'open_rate'       => 45.1,
                'click_rate'      => 15.2,
                'bounce_rate'     => 1.8,
                'daily_stats'     => self::generate_daily_stats( 30 ),
            ),
            'automations' => array(
                array(
                    'name'        => 'Welcome Series',
                    'trigger'     => 'user_register',
                    'status'      => 'active',
                    'run_count'   => 89,
                    'last_run_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
                ),
                array(
                    'name'        => 'Cart Recovery',
                    'trigger'     => 'woocommerce',
                    'status'      => 'active',
                    'run_count'   => 156,
                    'last_run_at' => gmdate( 'Y-m-d H:i:s', time() - 7200 ),
                ),
            ),
            'loaded_at' => time(),
        );

        update_option( self::DEMO_DATA_KEY, $demo_data, false );
    }

    /**
     * Generate fake daily statistics for N days.
     *
     * @param int $days Number of days.
     * @return array<int,array<string,mixed>>
     */
    private static function generate_daily_stats( int $days ): array {
        $stats = array();
        for ( $i = $days; $i >= 0; $i-- ) {
            $date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $sent     = wp_rand( 20, 80 );
            $opened   = (int) round( $sent * ( wp_rand( 30, 60 ) / 100 ) );
            $clicked  = (int) round( $opened * ( wp_rand( 10, 35 ) / 100 ) );
            $stats[] = array(
                'date'    => $date,
                'sent'    => $sent,
                'opened'  => $opened,
                'clicked' => $clicked,
                'bounced' => wp_rand( 0, 3 ),
            );
        }
        return $stats;
    }

    /**
     * Inject demo stats into mailsmart_log_stats filter.
     *
     * @param array<string,mixed> $stats Existing stats.
     * @param int                  $days  Number of days.
     * @return array<string,mixed>
     */
    public static function inject_demo_stats( array $stats, $days = 30 ): array {
        if ( ! self::is_demo_active() ) {
            return $stats;
        }

        $demo = get_option( self::DEMO_DATA_KEY, array() );
        if ( empty( $demo['analytics'] ) ) {
            return $stats;
        }

        return array_merge( $stats, array(
            'demo_mode'     => true,
            'demo_stats'    => $demo['analytics'],
        ) );
    }

    /**
     * Inject demo dashboard data.
     *
     * @param array<string,mixed> $payload Existing payload.
     * @param WP_REST_Request      $request REST request.
     * @return array<string,mixed>
     */
    public static function inject_demo_dashboard( $payload, $request = null ): array {
        if ( ! is_array( $payload ) ) {
            $payload = array();
        }

        if ( ! self::is_demo_active() ) {
            return $payload;
        }

        $demo = get_option( self::DEMO_DATA_KEY, array() );

        $payload['demo_mode']        = true;
        $payload['demo_templates']   = $demo['templates'] ?? array();
        $payload['demo_automations'] = $demo['automations'] ?? array();
        $payload['demo_analytics']   = $demo['analytics'] ?? array();

        return $payload;
    }

    /**
     * Remove demo data from options.
     */
    public static function cleanup(): void {
        delete_option( self::DEMO_DATA_KEY );
    }

    /**
     * Check if any user is currently in demo mode.
     *
     * @return bool
     */
    private static function is_demo_active(): bool {
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return false;
        }
        $record = SG_MailSmart_Trials_Data::get( $user_id );
        return $record
            && 'demo' === ( $record['mode'] ?? '' )
            && ! empty( $record['is_active'] );
    }
}
