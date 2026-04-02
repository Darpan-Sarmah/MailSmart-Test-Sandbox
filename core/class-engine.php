<?php
/**
 * Main plugin orchestrator – singleton that wires all components.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Engine {

    /** @var self|null */
    private static $instance = null;

    /**
     * Singleton accessor.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor – registers all subsystems.
     */
    private function __construct() {
        $this->boot_core();
        $this->boot_features();
        $this->boot_admin();
        $this->boot_rest();
        $this->boot_hooks();
    }

    /* ─── Core ──────────────────────────────────────────────────────── */

    private function boot_core(): void {
        SG_MailSmart_Trials_License_Injector::register();
        SG_MailSmart_Trials_Cron::register();
    }

    /* ─── Features ──────────────────────────────────────────────────── */

    private function boot_features(): void {
        SG_MailSmart_Trials_Trial::register();
        SG_MailSmart_Trials_Sandbox::register();
        SG_MailSmart_Trials_Demo::register();
        SG_MailSmart_Trials_Feature_Gate::register();
        SG_MailSmart_Trials_Usage_Tracker::register();
    }

    /* ─── Admin ─────────────────────────────────────────────────────── */

    private function boot_admin(): void {
        if ( is_admin() ) {
            SG_MailSmart_Trials_Admin::register();
        }
    }

    /* ─── REST ──────────────────────────────────────────────────────── */

    private function boot_rest(): void {
        add_action( 'rest_api_init', array( 'SG_MailSmart_Trials_REST_Controller', 'register_routes' ) );
    }

    /* ─── Extensibility Hooks ───────────────────────────────────────── */

    private function boot_hooks(): void {
        /**
         * Fires after the Trials Engine is fully loaded.
         *
         * @param self $engine The engine instance.
         */
        do_action( 'mailsmart_trials_loaded', $this );
    }

    /** Prevent cloning. */
    private function __clone() {}

    /** Prevent unserialization. */
    public function __wakeup(): void {
        throw new \LogicException( 'Cannot unserialize singleton.' );
    }
}
