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
        $user = get_user_by('login', $username);
        if (!$user) {
            return 0;
        }
        return self::is_user_enabled($user->ID) ? intval($user->ID) : 0;
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
