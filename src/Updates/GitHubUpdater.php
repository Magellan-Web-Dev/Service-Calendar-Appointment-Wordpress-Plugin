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

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_update']);
        add_filter('plugins_api', [self::class, 'plugins_api'], 10, 3);
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
}
