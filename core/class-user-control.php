<?php
/**
 * User Control – restricts trial access to specific users/roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SG_MailSmart_Trials_User_Control {

    /**
     * Check whether a user is allowed to use trials.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function user_allowed( int $user_id ): bool {
        $settings      = get_option( 'mailsmart_trial_settings', SG_MailSmart_Trials_Activator::default_settings() );
        $allowed_roles = $settings['allowed_roles'] ?? array( 'administrator' );

        // Empty means all roles allowed.
        if ( empty( $allowed_roles ) ) {
            return true;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        $user_roles = $user->roles;

        foreach ( $allowed_roles as $role ) {
            if ( in_array( $role, $user_roles, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether current user can manage trials (admin only).
     *
     * @return bool
     */
    public static function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }
}
