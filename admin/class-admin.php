<?php
/**
 * Admin Panel for SG MailSmart Trials Engine.
 *
 * Sections: Trial Settings, Sandbox Controls, Demo Mode, Status Dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Admin {

    /**
     * Register admin hooks.
     */
    public static function register(): void {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_mailsmart_trials_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_mailsmart_trials_start', array( __CLASS__, 'ajax_start_trial' ) );
        add_action( 'wp_ajax_mailsmart_trials_stop', array( __CLASS__, 'ajax_stop_trial' ) );
        add_action( 'wp_ajax_mailsmart_trials_load_demo', array( __CLASS__, 'ajax_load_demo' ) );
    }

    /**
     * Register the admin menu page.
     */
    public static function register_menu(): void {
        add_menu_page(
            __( 'MailSmart Trials', 'sg-mailsmart-trials' ),
            __( 'MailSmart Trials', 'sg-mailsmart-trials' ),
            'manage_options',
            'mailsmart-trials',
            array( __CLASS__, 'render_page' ),
            'dashicons-shield',
            81
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_mailsmart-trials' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'mailsmart-trials-admin',
            MAILSMART_TRIALS_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            MAILSMART_TRIALS_VERSION
        );

        wp_enqueue_script(
            'mailsmart-trials-admin',
            MAILSMART_TRIALS_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            MAILSMART_TRIALS_VERSION,
            true
        );

        $settings = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        $trials   = SG_MailSmart_Trials_Manager::get_active_trials();

        $active_list = array();
        foreach ( $trials as $uid => $rec ) {
            $u = get_userdata( (int) $uid );
            $active_list[] = array(
                'user_id'      => (int) $uid,
                'display_name' => $u ? $u->display_name : __( 'Unknown', 'sg-mailsmart-trials' ),
                'email'        => $u ? $u->user_email : '',
                'mode'         => $rec['mode'] ?? 'trial',
                'trial_type'   => $rec['trial_type'] ?? 'time',
                'remaining'    => SG_MailSmart_Trials_State::remaining_seconds( $rec ),
                'usage'        => $rec['usage_consumed'] ?? array(),
                'limits'       => $rec['usage_limits'] ?? array(),
                'paused'       => ! empty( $rec['paused_at'] ),
                'started_at'   => $rec['started_at'] ?? 0,
            );
        }

        wp_localize_script( 'mailsmart-trials-admin', 'mailsmartTrials', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'restUrl'   => rest_url( 'mailsmart-trial/v1/' ),
            'nonce'     => wp_create_nonce( 'mailsmart_trials_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'settings'  => $settings,
            'trials'    => $active_list,
            'users'     => self::get_users_list(),
            'roles'     => self::get_roles_list(),
            'i18n'      => array(
                'saved'         => __( 'Settings saved.', 'sg-mailsmart-trials' ),
                'started'       => __( 'Trial started.', 'sg-mailsmart-trials' ),
                'stopped'       => __( 'Trial stopped.', 'sg-mailsmart-trials' ),
                'demoLoaded'    => __( 'Demo data loaded.', 'sg-mailsmart-trials' ),
                'confirmStop'   => __( 'Are you sure you want to stop this trial?', 'sg-mailsmart-trials' ),
                'error'         => __( 'An error occurred.', 'sg-mailsmart-trials' ),
                'noTrials'      => __( 'No active trials.', 'sg-mailsmart-trials' ),
            ),
        ) );
    }

    /**
     * Render the admin page.
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'sg-mailsmart-trials' ) );
        }

        $settings = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        ?>
        <div class="wrap mailsmart-trials-wrap">
            <h1><?php esc_html_e( 'SG MailSmart Trials Engine', 'sg-mailsmart-trials' ); ?></h1>

            <div class="mailsmart-trials-notice" id="mailsmart-trials-notice" style="display:none;"></div>

            <!-- Tab navigation -->
            <nav class="nav-tab-wrapper mailsmart-trials-tabs">
                <a href="#trial-settings" class="nav-tab nav-tab-active" data-tab="trial-settings"><?php esc_html_e( 'Trial Settings', 'sg-mailsmart-trials' ); ?></a>
                <a href="#sandbox-controls" class="nav-tab" data-tab="sandbox-controls"><?php esc_html_e( 'Sandbox', 'sg-mailsmart-trials' ); ?></a>
                <a href="#demo-mode" class="nav-tab" data-tab="demo-mode"><?php esc_html_e( 'Demo Mode', 'sg-mailsmart-trials' ); ?></a>
                <a href="#status-dashboard" class="nav-tab" data-tab="status-dashboard"><?php esc_html_e( 'Dashboard', 'sg-mailsmart-trials' ); ?></a>
            </nav>

            <!-- Trial Settings Tab -->
            <div class="mailsmart-trials-tab-content" id="tab-trial-settings">
                <form id="mailsmart-trials-settings-form">
                    <?php wp_nonce_field( 'mailsmart_trials_nonce', 'mailsmart_trials_nonce_field' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Trial System', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="trial_enabled" value="1" <?php checked( ! empty( $settings['trial_enabled'] ) ); ?> />
                                    <?php esc_html_e( 'Enable trial mode for users', 'sg-mailsmart-trials' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Trial Type', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <select name="trial_type" id="trial-type-select">
                                    <option value="time" <?php selected( $settings['trial_type'] ?? 'time', 'time' ); ?>><?php esc_html_e( 'Time-Based', 'sg-mailsmart-trials' ); ?></option>
                                    <option value="usage" <?php selected( $settings['trial_type'] ?? 'time', 'usage' ); ?>><?php esc_html_e( 'Usage-Based', 'sg-mailsmart-trials' ); ?></option>
                                    <option value="feature" <?php selected( $settings['trial_type'] ?? 'time', 'feature' ); ?>><?php esc_html_e( 'Feature-Based', 'sg-mailsmart-trials' ); ?></option>
                                    <option value="hybrid" <?php selected( $settings['trial_type'] ?? 'time', 'hybrid' ); ?>><?php esc_html_e( 'Hybrid (Time + Usage)', 'sg-mailsmart-trials' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="trial-time-row">
                            <th scope="row"><?php esc_html_e( 'Trial Duration', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <input type="number" name="trial_duration" value="<?php echo esc_attr( $settings['trial_duration'] ?? 7 ); ?>" min="1" class="small-text" />
                                <select name="trial_duration_unit">
                                    <option value="minutes" <?php selected( $settings['trial_duration_unit'] ?? 'days', 'minutes' ); ?>><?php esc_html_e( 'Minutes', 'sg-mailsmart-trials' ); ?></option>
                                    <option value="hours" <?php selected( $settings['trial_duration_unit'] ?? 'days', 'hours' ); ?>><?php esc_html_e( 'Hours', 'sg-mailsmart-trials' ); ?></option>
                                    <option value="days" <?php selected( $settings['trial_duration_unit'] ?? 'days', 'days' ); ?>><?php esc_html_e( 'Days', 'sg-mailsmart-trials' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="trial-time-row">
                            <th scope="row"><?php esc_html_e( 'Grace Period (hours)', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <input type="number" name="trial_grace_period" value="<?php echo esc_attr( $settings['trial_grace_period'] ?? 24 ); ?>" min="0" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Extra time after trial expiry before access is revoked.', 'sg-mailsmart-trials' ); ?></p>
                            </td>
                        </tr>
                        <tr class="trial-usage-row">
                            <th scope="row"><?php esc_html_e( 'Usage Limits', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <label><?php esc_html_e( 'Emails:', 'sg-mailsmart-trials' ); ?>
                                    <input type="number" name="usage_limit_emails" value="<?php echo esc_attr( $settings['usage_limit_emails'] ?? 100 ); ?>" min="0" class="small-text" />
                                </label><br>
                                <label><?php esc_html_e( 'AI Generations:', 'sg-mailsmart-trials' ); ?>
                                    <input type="number" name="usage_limit_ai" value="<?php echo esc_attr( $settings['usage_limit_ai'] ?? 20 ); ?>" min="0" class="small-text" />
                                </label><br>
                                <label><?php esc_html_e( 'Automations:', 'sg-mailsmart-trials' ); ?>
                                    <input type="number" name="usage_limit_auto" value="<?php echo esc_attr( $settings['usage_limit_auto'] ?? 10 ); ?>" min="0" class="small-text" />
                                </label>
                                <p class="description"><?php esc_html_e( 'Set to 0 for unlimited.', 'sg-mailsmart-trials' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enabled Features', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <?php
                                $features = $settings['enabled_features'] ?? array();
                                $all_features = array(
                                    'ai'         => __( 'AI Generation', 'sg-mailsmart-trials' ),
                                    'automation' => __( 'Automation', 'sg-mailsmart-trials' ),
                                    'analytics'  => __( 'Analytics', 'sg-mailsmart-trials' ),
                                    'templates'  => __( 'Templates', 'sg-mailsmart-trials' ),
                                );
                                foreach ( $all_features as $key => $label ) :
                                ?>
                                    <label>
                                        <input type="checkbox" name="enabled_features[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $features, true ) ); ?> />
                                        <?php echo esc_html( $label ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Max Reactivations', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <input type="number" name="max_reactivations" value="<?php echo esc_attr( $settings['max_reactivations'] ?? 1 ); ?>" min="1" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Maximum number of times a user can activate a trial.', 'sg-mailsmart-trials' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Allowed Roles', 'sg-mailsmart-trials' ); ?></th>
                            <td>
                                <?php
                                $allowed = $settings['allowed_roles'] ?? array( 'administrator' );
                                $wp_roles = wp_roles()->get_names();
                                foreach ( $wp_roles as $role_key => $role_name ) :
                                ?>
                                    <label>
                                        <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $allowed, true ) ); ?> />
                                        <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'sg-mailsmart-trials' ); ?></button>
                    </p>
                </form>
            </div>

            <!-- Sandbox Tab -->
            <div class="mailsmart-trials-tab-content" id="tab-sandbox-controls" style="display:none;">
                <h2><?php esc_html_e( 'Sandbox Controls', 'sg-mailsmart-trials' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Sandbox', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="sandbox-enabled" <?php checked( ! empty( $settings['sandbox_enabled'] ) ); ?> />
                                <?php esc_html_e( 'Allow sandbox mode for internal testing', 'sg-mailsmart-trials' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Select User', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <select id="sandbox-user-select">
                                <option value=""><?php esc_html_e( '— Select User —', 'sg-mailsmart-trials' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Actions', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <button type="button" id="sandbox-start" class="button button-primary"><?php esc_html_e( 'Start Sandbox', 'sg-mailsmart-trials' ); ?></button>
                            <button type="button" id="sandbox-stop" class="button"><?php esc_html_e( 'Stop Sandbox', 'sg-mailsmart-trials' ); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Timer', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <div id="sandbox-timer" class="mailsmart-timer">--:--:--</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Demo Mode Tab -->
            <div class="mailsmart-trials-tab-content" id="tab-demo-mode" style="display:none;">
                <h2><?php esc_html_e( 'Demo Mode', 'sg-mailsmart-trials' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Demo', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="demo-enabled" <?php checked( ! empty( $settings['demo_enabled'] ) ); ?> />
                                <?php esc_html_e( 'Enable demo mode for showcasing', 'sg-mailsmart-trials' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Demo Data', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <button type="button" id="demo-load" class="button button-primary"><?php esc_html_e( 'Load Demo Data', 'sg-mailsmart-trials' ); ?></button>
                            <p class="description"><?php esc_html_e( 'Loads pre-filled templates, fake analytics, and simulated automations.', 'sg-mailsmart-trials' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Start Demo', 'sg-mailsmart-trials' ); ?></th>
                        <td>
                            <select id="demo-user-select">
                                <option value=""><?php esc_html_e( '— Select User —', 'sg-mailsmart-trials' ); ?></option>
                            </select>
                            <button type="button" id="demo-start" class="button"><?php esc_html_e( 'Start Demo', 'sg-mailsmart-trials' ); ?></button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Status Dashboard Tab -->
            <div class="mailsmart-trials-tab-content" id="tab-status-dashboard" style="display:none;">
                <h2><?php esc_html_e( 'Active Trials Dashboard', 'sg-mailsmart-trials' ); ?></h2>
                <table class="wp-list-table widefat fixed striped" id="trials-dashboard-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'sg-mailsmart-trials' ); ?></th>
                            <th><?php esc_html_e( 'Mode', 'sg-mailsmart-trials' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'sg-mailsmart-trials' ); ?></th>
                            <th><?php esc_html_e( 'Remaining', 'sg-mailsmart-trials' ); ?></th>
                            <th><?php esc_html_e( 'Usage', 'sg-mailsmart-trials' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'sg-mailsmart-trials' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'sg-mailsmart-trials' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="trials-dashboard-body">
                        <tr class="no-items"><td colspan="7"><?php esc_html_e( 'No active trials.', 'sg-mailsmart-trials' ); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /* ─── AJAX Handlers ─────────────────────────────────────────── */

    /**
     * Save settings via AJAX.
     */
    public static function ajax_save_settings(): void {
        check_ajax_referer( 'mailsmart_trials_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'sg-mailsmart-trials' ) ), 403 );
        }

        $input   = array();
        $current = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );

        // Sanitize each field.
        $input['trial_enabled']       = ! empty( $_POST['trial_enabled'] );
        $input['trial_duration']      = isset( $_POST['trial_duration'] ) ? max( 1, absint( $_POST['trial_duration'] ) ) : $current['trial_duration'];
        $input['trial_duration_unit'] = isset( $_POST['trial_duration_unit'] ) && in_array( sanitize_key( $_POST['trial_duration_unit'] ), array( 'minutes', 'hours', 'days' ), true )
            ? sanitize_key( $_POST['trial_duration_unit'] )
            : $current['trial_duration_unit'];
        $input['trial_grace_period']  = isset( $_POST['trial_grace_period'] ) ? absint( $_POST['trial_grace_period'] ) : $current['trial_grace_period'];
        $input['usage_limit_emails']  = isset( $_POST['usage_limit_emails'] ) ? absint( $_POST['usage_limit_emails'] ) : $current['usage_limit_emails'];
        $input['usage_limit_ai']      = isset( $_POST['usage_limit_ai'] ) ? absint( $_POST['usage_limit_ai'] ) : $current['usage_limit_ai'];
        $input['usage_limit_auto']    = isset( $_POST['usage_limit_auto'] ) ? absint( $_POST['usage_limit_auto'] ) : $current['usage_limit_auto'];
        $input['trial_type']          = isset( $_POST['trial_type'] ) && in_array( sanitize_key( $_POST['trial_type'] ), array( 'time', 'usage', 'feature', 'hybrid' ), true )
            ? sanitize_key( $_POST['trial_type'] )
            : $current['trial_type'];
        $input['hybrid_logic']        = 'first';
        $input['sandbox_enabled']     = ! empty( $_POST['sandbox_enabled'] );
        $input['demo_enabled']        = ! empty( $_POST['demo_enabled'] );
        $input['max_reactivations']   = isset( $_POST['max_reactivations'] ) ? max( 1, absint( $_POST['max_reactivations'] ) ) : $current['max_reactivations'];

        // Features.
        if ( isset( $_POST['enabled_features'] ) && is_array( $_POST['enabled_features'] ) ) {
            $valid = array( 'ai', 'automation', 'analytics', 'templates' );
            $input['enabled_features'] = array_values( array_intersect( array_map( 'sanitize_key', $_POST['enabled_features'] ), $valid ) );
        } else {
            $input['enabled_features'] = array();
        }

        // Roles.
        if ( isset( $_POST['allowed_roles'] ) && is_array( $_POST['allowed_roles'] ) ) {
            $input['allowed_roles'] = array_map( 'sanitize_key', $_POST['allowed_roles'] );
        } else {
            $input['allowed_roles'] = array();
        }

        update_option( 'mailsmart_trial_settings', $input, false );

        wp_send_json_success( array( 'message' => __( 'Settings saved.', 'sg-mailsmart-trials' ) ) );
    }

    /**
     * Start trial via AJAX.
     */
    public static function ajax_start_trial(): void {
        check_ajax_referer( 'mailsmart_trials_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'sg-mailsmart-trials' ) ), 403 );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $mode    = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'trial';

        if ( 0 === $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Please select a user.', 'sg-mailsmart-trials' ) ) );
        }

        switch ( $mode ) {
            case 'sandbox':
                $result = SG_MailSmart_Trials_Sandbox::start( $user_id );
                break;
            case 'demo':
                $result = SG_MailSmart_Trials_Demo::start( $user_id );
                break;
            default:
                $result = SG_MailSmart_Trials_Trial::start( $user_id );
                break;
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Trial started successfully.', 'sg-mailsmart-trials' ),
            'data'    => $result,
        ) );
    }

    /**
     * Stop trial via AJAX.
     */
    public static function ajax_stop_trial(): void {
        check_ajax_referer( 'mailsmart_trials_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'sg-mailsmart-trials' ) ), 403 );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

        if ( 0 === $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Please select a user.', 'sg-mailsmart-trials' ) ) );
        }

        $result = SG_MailSmart_Trials_Manager::stop( $user_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Trial stopped.', 'sg-mailsmart-trials' ) ) );
    }

    /**
     * Load demo data via AJAX.
     */
    public static function ajax_load_demo(): void {
        check_ajax_referer( 'mailsmart_trials_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'sg-mailsmart-trials' ) ), 403 );
        }

        SG_MailSmart_Trials_Demo::load_demo_data();

        wp_send_json_success( array( 'message' => __( 'Demo data loaded.', 'sg-mailsmart-trials' ) ) );
    }

    /* ─── Helpers ───────────────────────────────────────────────── */

    /**
     * Get a simple list of users for dropdown.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function get_users_list(): array {
        $users  = get_users( array( 'number' => 100, 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
        $output = array();
        foreach ( $users as $u ) {
            $output[] = array(
                'id'    => (int) $u->ID,
                'name'  => $u->display_name,
                'email' => $u->user_email,
            );
        }
        return $output;
    }

    /**
     * Get available WordPress roles.
     *
     * @return array<string,string>
     */
    private static function get_roles_list(): array {
        $roles = wp_roles()->get_names();
        $list  = array();
        foreach ( $roles as $key => $name ) {
            $list[ $key ] = translate_user_role( $name );
        }
        return $list;
    }
}
