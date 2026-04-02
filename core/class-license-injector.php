<?php
/**
 * License Injector – hooks into mailsmart_license_active filter.
 *
 * Rule: NEVER override a valid real license; only inject when license is inactive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_License_Injector {

    /** @var bool Whether the hook has been registered. */
    private static $registered = false;

    /**
     * Register the license injection filter.
     */
    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        // Priority 999 = run very late so we don't override earlier real-license checks.
        add_filter( 'mailsmart_license_active', array( __CLASS__, 'inject' ), 999, 2 );
        self::$registered = true;
    }

    /**
     * Inject trial/sandbox/demo license status.
     *
     * @param bool   $is_active       Current license status from Lite/Pro.
     * @param object $license_manager The Lite license manager instance (may be absent).
     * @return bool
     */
    public static function inject( $is_active, $license_manager = null ): bool {
        // CRITICAL: Never override a real valid license.
        if ( $is_active ) {
            return true;
        }

        // Check if any trial/sandbox/demo is active for the current user.
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return $is_active;
        }

        return SG_MailSmart_Trials_Manager::is_active( $user_id );
    }
}
