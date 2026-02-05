<?php
/**
 * User access helpers
 *
 * @package CalendarServiceAppointmentsForm\Core
 */

namespace CalendarServiceAppointmentsForm\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Access {

    public const OPTION_ENABLED_USERS = 'csa_enabled_users';
    public const ANYONE_USERNAME = '__anyone__';

    /**
     * Get enabled user IDs.
     *
     * @return array
     */
    public static function get_enabled_user_ids() {
        $ids = get_option(self::OPTION_ENABLED_USERS, []);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * Check if a user is enabled (admins always enabled).
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_user_enabled($user_id) {
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return false;
        }
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        if (user_can($user, 'manage_options')) {
            return true;
        }
        return in_array($user_id, self::get_enabled_user_ids(), true);
    }

    /**
     * Resolve an enabled user ID from username.
     *
     * @param string $username
     * @return int
     */
    public static function resolve_enabled_user_id($username) {
        $username = is_string($username) ? trim($username) : '';
        if ($username === '') {
            return 0;
        }
        if ($username === self::ANYONE_USERNAME) {
            return 0;
        }
        $user = get_user_by('login', $username);
        if (!$user) {
            return 0;
        }
        return self::is_user_enabled($user->ID) ? intval($user->ID) : 0;
    }

    public static function is_anyone_username($username) {
        if (!is_string($username)) {
            return false;
        }
        $val = trim($username);
        if ($val === self::ANYONE_USERNAME) {
            return true;
        }
        return strtolower($val) === 'anyone';
    }

    /**
     * Resolve enabled user id from a display/full name.
     *
     * @param string $name
     * @return int
     */
    public static function resolve_enabled_user_id_by_name($name) {
        $name = self::normalize_user_name($name);
        if ($name === '') {
            return 0;
        }

        $enabled_ids = self::get_enabled_user_ids();
        $admins = get_users([
            'role' => 'administrator',
            'fields' => ['ID'],
        ]);
        foreach ($admins as $admin) {
            $enabled_ids[] = intval($admin->ID);
        }
        $enabled_ids = array_values(array_unique(array_filter(array_map('intval', $enabled_ids))));
        if (empty($enabled_ids)) {
            return 0;
        }

        $candidates = get_users([
            'include' => $enabled_ids,
            'fields' => ['ID', 'display_name', 'first_name', 'last_name', 'user_login'],
        ]);
        $matches = [];
        foreach ($candidates as $user) {
            $wp_user = $user;
            if (!isset($user->display_name) || !isset($user->user_login)) {
                $wp_user = get_user_by('id', $user->ID ?? 0);
            }
            if (!$wp_user) {
                continue;
            }

            $display = self::normalize_user_name($wp_user->display_name ?? '');
            $first = self::normalize_user_name($wp_user->first_name ?? '');
            $last = self::normalize_user_name($wp_user->last_name ?? '');
            $full = trim($first . ' ' . $last);
            $rev = trim($last . ' ' . $first);
            $login = self::normalize_user_name($wp_user->user_login ?? '');

            if ($name === $display || ($full !== '' && $name === $full) || ($rev !== '' && $name === $rev) || $name === $login) {
                $matches[] = intval($wp_user->ID ?? 0);
            }
        }

        $matches = array_values(array_unique(array_filter($matches)));
        if (count($matches) === 1) {
            return $matches[0];
        }
        return 0;
    }

    /**
     * Build a display name for a user.
     *
     * @param \WP_User $user
     * @return string
     */
    public static function build_user_display_name($user) {
        if (!$user) {
            return '';
        }
        $display = trim($user->first_name . ' ' . $user->last_name);
        if ($display !== '') {
            return $display;
        }
        $display = trim((string) $user->display_name);
        if ($display !== '') {
            return $display;
        }
        return trim((string) $user->user_login);
    }

    /**
     * Normalize a name for comparisons.
     *
     * @param string $value
     * @return string
     */
    private static function normalize_user_name($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s+/', ' ', $value);
        return strtolower($value);
    }

    /**
     * Get a default admin user ID for backfill.
     *
     * @return int
     */
    public static function get_default_admin_id() {
        $admins = get_users([
            'role' => 'administrator',
            'orderby' => 'ID',
            'order' => 'ASC',
            'number' => 1,
            'fields' => ['ID'],
        ]);
        if (!empty($admins) && !empty($admins[0]->ID)) {
            return intval($admins[0]->ID);
        }
        return 0;
    }

    /**
     * Save enabled users list.
     *
     * @param array $user_ids
     * @return void
     */
    public static function save_enabled_user_ids($user_ids) {
        if (!is_array($user_ids)) {
            $user_ids = [];
        }
        $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
        update_option(self::OPTION_ENABLED_USERS, $user_ids);
    }
}
