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

    /**
     * This is the folder name we ALWAYS want under wp-content/plugins/
     */
    private const DESIRED_FOLDER = 'calendar-service-appointments-form';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_update']);
        add_filter('plugins_api', [self::class, 'plugins_api'], 10, 3);

        /**
         * Most reliable way to control the final install directory name.
         */
        add_filter('upgrader_package_options', [self::class, 'force_destination_folder'], 10, 1);

        /**
         * Extra safety: if something still installs into a versioned folder,
         * normalize it after install.
         */
        add_filter('upgrader_post_install', [self::class, 'normalize_folder_after_install'], 10, 3);
    }

    public static function check_for_update($transient) {
        if (empty($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = self::get_latest_release();
        if (!$release) {
            return $transient;
        }

        $current = defined('CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION')
            ? CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION
            : '0.0.0';

        if (version_compare($release['version'], $current, '>')) {
            $plugin_basename = self::plugin_basename();

            $transient->response[$plugin_basename] = (object) [
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_basename,
                'new_version' => $release['version'],
                'url'         => $release['html_url'],
                'package'     => $release['zip_url'],
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
            'name'          => 'Calendar Service Appointments Form',
            'slug'          => self::PLUGIN_SLUG,
            'version'       => $release['version'],
            'author'        => 'Chris Paschall',
            'homepage'      => $release['html_url'],
            'download_link' => $release['zip_url'],
            'sections'      => [
                'description' => 'A complete service appointment booking system with calendar interface and Elementor integration.',
            ],
            'banners'       => [],
        ];
    }

    private static function get_latest_release(): ?array {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
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
            'tag'     => $tag,
            'version' => $version,
            'zip_url' => sprintf(self::ZIP_URL, rawurlencode($tag)),
            'html_url' => isset($data['html_url']) ? (string) $data['html_url'] : '',
        ];

        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);

        return $release;
    }

    /**
     * Returns the "plugin basename" WordPress expects for update matching.
     * Example: calendar-service-appointments-form/calendar-service-appointments-form.php
     */
    private static function plugin_basename(): string {
        // This is more stable than building from constants that might be wrong during upgrade.
        return self::DESIRED_FOLDER . '/' . self::PLUGIN_FILE;
    }

    /**
     * Ensures WordPress installs into:
     * wp-content/plugins/calendar-service-appointments-form/
     *
     * This is the most important fix.
     */
    public static function force_destination_folder(array $options): array {
        // Only affect plugin updates.
        if (empty($options['hook_extra']['type']) || $options['hook_extra']['type'] !== 'plugin') {
            return $options;
        }

        // Only affect updates (not installs).
        if (empty($options['hook_extra']['action']) || $options['hook_extra']['action'] !== 'update') {
            return $options;
        }

        // Only affect THIS plugin.
        if (empty($options['hook_extra']['plugin']) || $options['hook_extra']['plugin'] !== self::plugin_basename()) {
            return $options;
        }

        // Force destination to wp-content/plugins/
        $options['destination'] = WP_PLUGIN_DIR;

        // Force the folder name under plugins/
        $options['destination_name'] = self::DESIRED_FOLDER;

        // Replace existing plugin folder contents cleanly
        $options['clear_destination'] = true;

        return $options;
    }

    /**
     * Safety net: if WordPress still ends up installing under a GitHub-generated folder,
     * move it back to the desired folder.
     */
    public static function normalize_folder_after_install($response, $hook_extra, $result) {
        if (empty($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
            return $response;
        }

        if (empty($hook_extra['action']) || $hook_extra['action'] !== 'update') {
            return $response;
        }

        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== self::plugin_basename()) {
            return $response;
        }

        if (empty($result['destination'])) {
            return $response;
        }

        $desired_dir = trailingslashit(WP_PLUGIN_DIR) . self::DESIRED_FOLDER;
        $installed_dir = rtrim((string) $result['destination'], '/\\');

        // If WP already installed it into the correct folder, nothing to do.
        if (wp_normalize_path($installed_dir) === wp_normalize_path($desired_dir)) {
            return $response;
        }

        // If for some reason destination is wrong, attempt to move.
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if (!$wp_filesystem) {
            return $response;
        }

        // Only move if the installed directory actually contains our plugin file.
        $installed_plugin_file = trailingslashit($installed_dir) . self::PLUGIN_FILE;
        if (!$wp_filesystem->exists($installed_plugin_file)) {
            return $response;
        }

        // Remove existing desired dir if it exists.
        if ($wp_filesystem->is_dir($desired_dir)) {
            $wp_filesystem->delete($desired_dir, true);
        }

        // Move to desired directory.
        $wp_filesystem->move($installed_dir, $desired_dir, true);

        // Ensure active plugins list is correct.
        $old_basename = $hook_extra['plugin']; // should already be desired basename
        $new_basename = self::plugin_basename();

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

    public static function normalize_plugin_folder_on_load(): void {
        $desired_folder = self::DESIRED_FOLDER;
        $plugin_file = self::PLUGIN_FILE;

        $plugins_dir = WP_PLUGIN_DIR;
        $desired_dir = $plugins_dir . '/' . $desired_folder;

        if (is_dir($desired_dir) && file_exists($desired_dir . '/' . $plugin_file)) {
            return;
        }

        $candidates = glob($plugins_dir . '/*/' . $plugin_file);
        if (empty($candidates)) {
            return;
        }

        $current_dir = dirname($candidates[0]);
        $current_folder = basename($current_dir);

        if ($current_folder === $desired_folder) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        if (!$wp_filesystem) {
            return;
        }

        if ($wp_filesystem->is_dir($desired_dir)) {
            $wp_filesystem->delete($desired_dir, true);
        }

        $wp_filesystem->move($current_dir, $desired_dir, true);

        $old_basename = $current_folder . '/' . $plugin_file;
        $new_basename = $desired_folder . '/' . $plugin_file;

        $active_plugins = get_option('active_plugins', []);
        if (is_array($active_plugins)) {
            $idx = array_search($old_basename, $active_plugins, true);
            if ($idx !== false) {
                $active_plugins[$idx] = $new_basename;
                update_option('active_plugins', array_values($active_plugins));
            }
        }

        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }
    }
}
