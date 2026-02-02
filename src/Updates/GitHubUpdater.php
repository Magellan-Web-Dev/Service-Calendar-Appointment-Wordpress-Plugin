<?php
/**
 * GitHub updater integration.
 *
 * @package CalendarServiceAppointmentsForm\Updates
 */

namespace CalendarServiceAppointmentsForm\Updates;

if (!defined('ABSPATH')) {
    exit;
}

class GitHubUpdater {
    private const REPO = 'Magellan-Web-Dev/Service-Calendar-Appointment-Wordpress-Plugin';
    private const API_URL = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
    private const ZIP_URL = 'https://github.com/' . self::REPO . '/archive/refs/tags/%s.zip';
    private const CACHE_KEY = 'csa_github_release';
    private const CACHE_TTL = 43200;
    private const PLUGIN_SLUG = 'calendar-service-appointments';
    private const PLUGIN_FILE = 'calendar-service-appointments-form.php';
    private const DESIRED_FOLDER = 'calendar-service-appointments-form';
    private const LEGACY_PREFIX = 'Service-Calendar-Appointment-Wordpress-Plugin-';

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_update']);
        add_filter('plugins_api', [self::class, 'plugins_api'], 10, 3);
        add_filter('upgrader_source_selection', [self::class, 'fix_source_directory'], 10, 4);
        add_filter('upgrader_post_install', [self::class, 'maybe_rename_after_update'], 10, 3);
    }

    public static function check_for_update($transient) {
        if (empty($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = self::get_latest_release();
        if (!$release) {
            return $transient;
        }

        $current = defined('CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION') ? CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION : '0.0.0';
        if (version_compare($release['version'], $current, '>')) {
            $plugin_basename = plugin_basename(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . self::PLUGIN_FILE);
            $transient->response[$plugin_basename] = (object) [
                'slug' => self::PLUGIN_SLUG,
                'plugin' => $plugin_basename,
                'new_version' => $release['version'],
                'url' => $release['html_url'],
                'package' => $release['zip_url'],
            ];
        }

        return $transient;
    }

    public static function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }

        $release = self::get_latest_release();
        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'Calendar Service Appointments Form',
            'slug' => self::PLUGIN_SLUG,
            'version' => $release['version'],
            'author' => 'Chris Paschall',
            'homepage' => $release['html_url'],
            'download_link' => $release['zip_url'],
            'sections' => [
                'description' => 'A complete service appointment booking system with calendar interface and Elementor integration.',
            ],
            'banners' => [],
        ];
    }

    private static function get_latest_release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/Calendar-Service-Appointments',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

        $tag = (string) $data['tag_name'];
        $version = ltrim($tag, 'v');

        $release = [
            'tag' => $tag,
            'version' => $version,
            'zip_url' => sprintf(self::ZIP_URL, rawurlencode($tag)),
            'html_url' => isset($data['html_url']) ? (string) $data['html_url'] : '',
        ];

        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);

        return $release;
    }

    /**
     * Ensure the extracted folder name matches the installed plugin directory.
     *
     * @param string $source
     * @param string $remote_source
     * @param object $upgrader
     * @param array $hook_extra
     * @return string
     */
    public static function fix_source_directory($source, $remote_source, $upgrader, $hook_extra) {
        if (empty($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
            return $source;
        }
        if (empty($hook_extra['action']) || $hook_extra['action'] !== 'update') {
            return $source;
        }
        if (empty($hook_extra['plugin'])) {
            return $source;
        }

        $plugin_basename = plugin_basename(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . self::PLUGIN_FILE);
        if ($hook_extra['plugin'] !== $plugin_basename) {
            return $source;
        }

        $installed_folder = dirname($hook_extra['plugin']);
        if ($installed_folder === '.' || $installed_folder === '') {
            return $source;
        }
        if (strpos($installed_folder, self::LEGACY_PREFIX) === 0) {
            $installed_folder = self::DESIRED_FOLDER;
        }

        $source = trailingslashit($source);
        $desired = trailingslashit(trailingslashit(dirname($source)) . $installed_folder);
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if (!$wp_filesystem) {
            return $source;
        }

        $plugin_root = self::locate_plugin_root($source, $wp_filesystem);
        if ($plugin_root) {
            $source = trailingslashit($plugin_root);
        }

        if ($source === $desired) {
            return $source;
        }

        if ($wp_filesystem->is_dir($desired)) {
            if (!$wp_filesystem->delete($desired, true)) {
                return $source;
            }
        }

        $moved = $wp_filesystem->move($source, $desired, true);
        return $moved ? $desired : $source;
    }

    /**
     * Rename legacy versioned folders to the desired plugin folder after update.
     *
     * @param mixed $response
     * @param array $hook_extra
     * @param array $result
     * @return mixed
     */
    public static function maybe_rename_after_update($response, $hook_extra, $result) {
        if (empty($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
            return $response;
        }
        if (empty($hook_extra['action']) || $hook_extra['action'] !== 'update') {
            return $response;
        }
        if (empty($hook_extra['plugin'])) {
            return $response;
        }

        $plugin_basename = plugin_basename(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . self::PLUGIN_FILE);
        if ($hook_extra['plugin'] !== $plugin_basename) {
            return $response;
        }

        $current_dir = trailingslashit(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR);
        $current_basename = basename($current_dir);
        if ($current_basename === self::DESIRED_FOLDER) {
            return $response;
        }
        if (strpos($current_basename, self::LEGACY_PREFIX) !== 0) {
            return $response;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if (!$wp_filesystem) {
            return $response;
        }

        $target_dir = trailingslashit(WP_PLUGIN_DIR . '/' . self::DESIRED_FOLDER);
        if ($wp_filesystem->is_dir($target_dir)) {
            if (!$wp_filesystem->delete($target_dir, true)) {
                return $response;
            }
        }

        if (!$wp_filesystem->move($current_dir, $target_dir, true)) {
            return $response;
        }

        $old_basename = $hook_extra['plugin'];
        $new_basename = self::DESIRED_FOLDER . '/' . self::PLUGIN_FILE;

        $active_plugins = get_option('active_plugins', []);
        if (is_array($active_plugins)) {
            $index = array_search($old_basename, $active_plugins, true);
            if ($index !== false) {
                $active_plugins[$index] = $new_basename;
                update_option('active_plugins', array_values($active_plugins));
            }
        }

        if (is_multisite()) {
            $network_active = get_site_option('active_sitewide_plugins', []);
            if (is_array($network_active) && isset($network_active[$old_basename])) {
                $network_active[$new_basename] = $network_active[$old_basename];
                unset($network_active[$old_basename]);
                update_site_option('active_sitewide_plugins', $network_active);
            }
        }

        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }

        return $response;
    }

    /**
     * Find the actual plugin root inside the extracted zip folder.
     *
     * @param string $source
     * @param object $wp_filesystem
     * @return string|null
     */
    private static function locate_plugin_root($source, $wp_filesystem) {
        $candidate = trailingslashit($source) . self::PLUGIN_FILE;
        if ($wp_filesystem->exists($candidate)) {
            return $source;
        }

        $entries = $wp_filesystem->dirlist($source, false, false);
        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $name => $entry) {
            if (empty($entry['type']) || $entry['type'] !== 'd') {
                continue;
            }

            $child = trailingslashit($source) . $name;
            $child_candidate = trailingslashit($child) . self::PLUGIN_FILE;
            if ($wp_filesystem->exists($child_candidate)) {
                return $child;
            }
        }

        return null;
    }
}
