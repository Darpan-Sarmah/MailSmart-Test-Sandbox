<?php
/**
 * REST API Controller for the Trials Engine.
 *
 * Endpoints:
 *   POST /mailsmart-trial/v1/start
 *   GET  /mailsmart-trial/v1/status
 *   POST /mailsmart-trial/v1/stop
 *   GET  /mailsmart-trial/v1/settings
 *   POST /mailsmart-trial/v1/settings
 *   GET  /mailsmart-trial/v1/active-trials
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_REST_Controller {

    /** @var string */
    const NAMESPACE = 'mailsmart-trial/v1';

    /**
     * Register all REST routes.
     */
    public static function register_routes(): void {
        // Start trial.
        register_rest_route(
            self::NAMESPACE,
            '/start',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'start_trial' ),
                'permission_callback' => array( __CLASS__, 'admin_permission' ),
                'args'                => self::start_args(),
            )
        );

        // Status.
        register_rest_route(
            self::NAMESPACE,
            '/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_status' ),
                'permission_callback' => array( __CLASS__, 'admin_permission' ),
                'args'                => array(
                    'user_id' => array(
                        'type'              => 'integer',
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Stop.
        register_rest_route(
            self::NAMESPACE,
            '/stop',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'stop_trial' ),
                'permission_callback' => array( __CLASS__, 'admin_permission' ),
                'args'                => array(
                    'user_id' => array(
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Get settings.
        register_rest_route(
            self::NAMESPACE,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_settings' ),
                    'permission_callback' => array( __CLASS__, 'admin_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'save_settings' ),
                    'permission_callback' => array( __CLASS__, 'admin_permission' ),
                    'args'                => self::settings_args(),
                ),
            )
        );

        // Active trials list.
        register_rest_route(
            self::NAMESPACE,
            '/active-trials',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'active_trials' ),
                'permission_callback' => array( __CLASS__, 'admin_permission' ),
            )
        );
    }

    /* ─── Callbacks ─────────────────────────────────────────────── */

    /**
     * Start a trial / sandbox / demo.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function start_trial( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user_id' ) );
        $mode    = sanitize_key( $request->get_param( 'mode' ) );

        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        $overrides = array();

        // Allow per-request overrides.
        $duration      = $request->get_param( 'duration' );
        $duration_unit = $request->get_param( 'duration_unit' );
        $trial_type    = $request->get_param( 'trial_type' );
        $features      = $request->get_param( 'features' );

        if ( null !== $duration ) {
            $overrides['trial_duration'] = absint( $duration );
        }
        if ( null !== $duration_unit ) {
            $overrides['trial_duration_unit'] = sanitize_key( $duration_unit );
        }
        if ( null !== $trial_type ) {
            $overrides['trial_type'] = sanitize_key( $trial_type );
        }
        if ( null !== $features && is_array( $features ) ) {
            $overrides['enabled_features'] = array_map( 'sanitize_key', $features );
        }

        switch ( $mode ) {
            case 'sandbox':
                $result = SG_MailSmart_Trials_Sandbox::start( $user_id, $overrides );
                break;
            case 'demo':
                $result = SG_MailSmart_Trials_Demo::start( $user_id );
                break;
            case 'trial':
            default:
                $result = SG_MailSmart_Trials_Trial::start( $user_id, $overrides );
                break;
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $result,
            ),
            201
        );
    }

    /**
     * Get trial status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_status( WP_REST_Request $request ): WP_REST_Response {
        $user_id = absint( $request->get_param( 'user_id' ) );
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        $status = SG_MailSmart_Trials_Manager::status( $user_id );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $status,
            ),
            200
        );
    }

    /**
     * Stop an active trial.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function stop_trial( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user_id' ) );

        $result = SG_MailSmart_Trials_Manager::stop( $user_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Trial stopped successfully.', 'sg-mailsmart-trials' ),
            ),
            200
        );
    }

    /**
     * Get plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_settings( WP_REST_Request $request ): WP_REST_Response {
        $settings = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $settings,
            ),
            200
        );
    }

    /**
     * Save plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
        $current  = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        $body     = $request->get_json_params();
        $sanitized = self::sanitize_settings( $body, $current );

        update_option( 'mailsmart_trial_settings', $sanitized, false );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $sanitized,
            ),
            200
        );
    }

    /**
     * Get all active trials.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function active_trials( WP_REST_Request $request ): WP_REST_Response {
        $trials = SG_MailSmart_Trials_Manager::get_active_trials();
        $output = array();

        foreach ( $trials as $user_id => $record ) {
            $user = get_userdata( (int) $user_id );
            $output[] = array(
                'user_id'           => (int) $user_id,
                'display_name'      => $user ? $user->display_name : __( 'Unknown', 'sg-mailsmart-trials' ),
                'email'             => $user ? $user->user_email : '',
                'mode'              => $record['mode'] ?? 'trial',
                'trial_type'        => $record['trial_type'] ?? 'time',
                'remaining_seconds' => SG_MailSmart_Trials_State::remaining_seconds( $record ),
                'usage_consumed'    => $record['usage_consumed'] ?? array(),
                'usage_limits'      => $record['usage_limits'] ?? array(),
                'paused'            => ! empty( $record['paused_at'] ),
                'started_at'        => $record['started_at'] ?? 0,
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $output,
            ),
            200
        );
    }

    /* ─── Permissions ───────────────────────────────────────────── */

    /**
     * Only admins can manage trials.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public static function admin_permission( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to manage trials.', 'sg-mailsmart-trials' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /* ─── Argument Schemas ──────────────────────────────────────── */

    /**
     * Args for the /start endpoint.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function start_args(): array {
        return array(
            'user_id'       => array(
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
            'mode'          => array(
                'type'              => 'string',
                'default'           => 'trial',
                'enum'              => array( 'trial', 'sandbox', 'demo' ),
                'sanitize_callback' => 'sanitize_key',
            ),
            'duration'      => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'duration_unit' => array(
                'type'              => 'string',
                'enum'              => array( 'minutes', 'hours', 'days' ),
                'sanitize_callback' => 'sanitize_key',
            ),
            'trial_type'    => array(
                'type'              => 'string',
                'enum'              => array( 'time', 'usage', 'feature', 'hybrid' ),
                'sanitize_callback' => 'sanitize_key',
            ),
            'features'      => array(
                'type'  => 'array',
                'items' => array( 'type' => 'string' ),
            ),
        );
    }

    /**
     * Args for the settings endpoint.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function settings_args(): array {
        return array(
            'trial_enabled'       => array( 'type' => 'boolean' ),
            'trial_duration'      => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'trial_duration_unit' => array( 'type' => 'string', 'enum' => array( 'minutes', 'hours', 'days' ) ),
            'trial_grace_period'  => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'usage_limit_emails'  => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'usage_limit_ai'      => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'usage_limit_auto'    => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'trial_type'          => array( 'type' => 'string', 'enum' => array( 'time', 'usage', 'feature', 'hybrid' ) ),
            'hybrid_logic'        => array( 'type' => 'string', 'enum' => array( 'first' ) ),
            'enabled_features'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
            'sandbox_enabled'     => array( 'type' => 'boolean' ),
            'demo_enabled'        => array( 'type' => 'boolean' ),
            'max_reactivations'   => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'allowed_roles'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
        );
    }

    /**
     * Sanitize settings array.
     *
     * @param array<string,mixed> $input   Raw input.
     * @param array<string,mixed> $current Current settings.
     * @return array<string,mixed>
     */
    private static function sanitize_settings( array $input, array $current ): array {
        $defaults = SG_MailSmart_Trials_Activator::default_settings();
        $merged   = wp_parse_args( $current, $defaults );

        if ( isset( $input['trial_enabled'] ) ) {
            $merged['trial_enabled'] = (bool) $input['trial_enabled'];
        }
        if ( isset( $input['trial_duration'] ) ) {
            $merged['trial_duration'] = max( 1, absint( $input['trial_duration'] ) );
        }
        if ( isset( $input['trial_duration_unit'] ) && in_array( $input['trial_duration_unit'], array( 'minutes', 'hours', 'days' ), true ) ) {
            $merged['trial_duration_unit'] = sanitize_key( $input['trial_duration_unit'] );
        }
        if ( isset( $input['trial_grace_period'] ) ) {
            $merged['trial_grace_period'] = absint( $input['trial_grace_period'] );
        }
        if ( isset( $input['usage_limit_emails'] ) ) {
            $merged['usage_limit_emails'] = absint( $input['usage_limit_emails'] );
        }
        if ( isset( $input['usage_limit_ai'] ) ) {
            $merged['usage_limit_ai'] = absint( $input['usage_limit_ai'] );
        }
        if ( isset( $input['usage_limit_auto'] ) ) {
            $merged['usage_limit_auto'] = absint( $input['usage_limit_auto'] );
        }
        if ( isset( $input['trial_type'] ) && in_array( $input['trial_type'], array( 'time', 'usage', 'feature', 'hybrid' ), true ) ) {
            $merged['trial_type'] = sanitize_key( $input['trial_type'] );
        }
        if ( isset( $input['hybrid_logic'] ) && in_array( $input['hybrid_logic'], array( 'first' ), true ) ) {
            $merged['hybrid_logic'] = sanitize_key( $input['hybrid_logic'] );
        }
        if ( isset( $input['enabled_features'] ) && is_array( $input['enabled_features'] ) ) {
            $valid    = array( 'ai', 'automation', 'analytics', 'templates' );
            $merged['enabled_features'] = array_values( array_intersect( array_map( 'sanitize_key', $input['enabled_features'] ), $valid ) );
        }
        if ( isset( $input['sandbox_enabled'] ) ) {
            $merged['sandbox_enabled'] = (bool) $input['sandbox_enabled'];
        }
        if ( isset( $input['demo_enabled'] ) ) {
            $merged['demo_enabled'] = (bool) $input['demo_enabled'];
        }
        if ( isset( $input['max_reactivations'] ) ) {
            $merged['max_reactivations'] = max( 1, absint( $input['max_reactivations'] ) );
        }
        if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
            $merged['allowed_roles'] = array_map( 'sanitize_key', $input['allowed_roles'] );
        }

        return $merged;
    }
}
