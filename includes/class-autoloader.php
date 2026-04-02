<?php
/**
 * PSR-4-style autoloader for SG MailSmart Trials Engine.
 *
 * Maps class prefixes to directories:
 *   SG_MailSmart_Trials_  → core/, includes/, features/, admin/, rest/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_Autoloader {

    /**
     * Class-to-file map for explicit resolution.
     *
     * @var array<string,string>
     */
    private static $class_map = array(
        /* ── Includes ──────────────────────────────────── */
        'SG_MailSmart_Trials_Activator'          => 'includes/class-activator.php',

        /* ── Core ──────────────────────────────────────── */
        'SG_MailSmart_Trials_Engine'             => 'core/class-engine.php',
        'SG_MailSmart_Trials_Data'               => 'core/class-data.php',
        'SG_MailSmart_Trials_Manager'            => 'core/class-manager.php',
        'SG_MailSmart_Trials_State'              => 'core/class-state.php',
        'SG_MailSmart_Trials_License_Injector'   => 'core/class-license-injector.php',
        'SG_MailSmart_Trials_Cron'               => 'core/class-cron.php',
        'SG_MailSmart_Trials_User_Control'       => 'core/class-user-control.php',
        'SG_MailSmart_Trials_Anti_Abuse'         => 'core/class-anti-abuse.php',

        /* ── Features ──────────────────────────────────── */
        'SG_MailSmart_Trials_Trial'              => 'features/class-trial.php',
        'SG_MailSmart_Trials_Sandbox'            => 'features/class-sandbox.php',
        'SG_MailSmart_Trials_Demo'               => 'features/class-demo.php',
        'SG_MailSmart_Trials_Feature_Gate'       => 'features/class-feature-gate.php',
        'SG_MailSmart_Trials_Usage_Tracker'      => 'features/class-usage-tracker.php',

        /* ── Admin ─────────────────────────────────────── */
        'SG_MailSmart_Trials_Admin'              => 'admin/class-admin.php',

        /* ── REST ──────────────────────────────────────── */
        'SG_MailSmart_Trials_REST_Controller'    => 'rest/class-rest-controller.php',
    );

    /**
     * Register the autoloader.
     */
    public static function register(): void {
        spl_autoload_register( array( __CLASS__, 'load' ) );
    }

    /**
     * Autoload callback.
     *
     * @param string $class Fully-qualified class name.
     */
    public static function load( string $class ): void {
        if ( isset( self::$class_map[ $class ] ) ) {
            $file = MAILSMART_TRIALS_PLUGIN_DIR . self::$class_map[ $class ];
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}
