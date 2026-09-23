<?php

class AuthMiddleware
{
    public static function handle()
    {
        require_auth();
        self::ensureActiveAccount();
    }

    /** Log out if the user was deactivated after their session started. */
    private static function ensureActiveAccount()
    {
        $userId = auth_id();
        if (!$userId) {
            return;
        }
        $row = db()->fetch(
            'SELECT id, first_name, last_name, email, role, initials, is_active, azure_oid
             FROM users WHERE id = ?',
            [$userId]
        );
        if (!$row || empty($row['is_active'])) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            flash('error', 'Your account is no longer active.');
            redirect(url('login'));
        }
        $_SESSION['user'] = array_merge(isset($_SESSION['user']) ? $_SESSION['user'] : [], $row);
    }
}
