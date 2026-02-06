<?php
declare(strict_types=1);
/**
 * Multisite sync settings and helpers
 *
 * @package CalendarServiceAppointmentsForm\Core
 */

namespace CalendarServiceAppointmentsForm\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Multisite {

    public const MODE_STANDALONE = 'standalone';
    public const MODE_MASTER = 'master';
    public const MODE_CHILD = 'child';

    public const OPTION_MODE = 'csa_multisite_mode';
    public const OPTION_MASTER_URL = 'csa_multisite_master_url';
    public const OPTION_API_KEY = 'csa_multisite_api_key';

    /**
     * Get current mode.
     *
     * @return string
     */
    public static function get_mode(): string {
        $mode = get_option(self::OPTION_MODE, self::MODE_STANDALONE);
        if (!in_array($mode, [self::MODE_STANDALONE, self::MODE_MASTER, self::MODE_CHILD], true)) {
            return self::MODE_STANDALONE;
        }
        return $mode;
    }

    /**
     * @return bool
     */
    public static function is_master(): bool {
        return self::get_mode() === self::MODE_MASTER;
    }

    /**
     * @return bool
     */
    public static function is_child(): bool {
        return self::get_mode() === self::MODE_CHILD;
    }

    /**
     * Get stored master URL (child mode).
     *
     * @return string
     */
    public static function get_master_url(): string {
        $url = (string) get_option(self::OPTION_MASTER_URL, '');
        return trim($url);
    }

    /**
     * Get API key (master key or child stored key).
     *
     * @return string
     */
    public static function get_api_key(): string {
        $key = (string) get_option(self::OPTION_API_KEY, '');
        return trim($key);
    }

    /**
     * Ensure master key exists.
     *
     * @return string
     */
    public static function ensure_master_key(): string {
        $key = self::get_api_key();
        if ($key !== '') {
            return $key;
        }

        $key = wp_generate_password(40, false, false);
        update_option(self::OPTION_API_KEY, $key);
        return $key;
    }

    /**
     * Save settings.
     *
     * @param string $mode
     * @param string $master_url
     * @param string $api_key
     * @param bool $regenerate_key
     * @return void
     */
    public static function save_settings(string $mode, string $master_url, string $api_key, bool $regenerate_key = false): void {
        $mode = in_array($mode, [self::MODE_STANDALONE, self::MODE_MASTER, self::MODE_CHILD], true)
            ? $mode
            : self::MODE_STANDALONE;

        update_option(self::OPTION_MODE, $mode);

        if ($mode === self::MODE_MASTER) {
            if ($regenerate_key) {
                update_option(self::OPTION_API_KEY, wp_generate_password(40, false, false));
            } else {
                self::ensure_master_key();
            }
            update_option(self::OPTION_MASTER_URL, '');
            return;
        }

        if ($mode === self::MODE_CHILD) {
            update_option(self::OPTION_MASTER_URL, $master_url);
            update_option(self::OPTION_API_KEY, $api_key);
            return;
        }

        // Standalone
        update_option(self::OPTION_MASTER_URL, '');
        update_option(self::OPTION_API_KEY, '');
    }

    /**
     * Build an API URL for the master site.
     *
     * @param string $path
     * @param array $query
     * @return string
     */
    public static function build_master_url(string $path, array $query = []): string {
        $base = rtrim(self::get_master_url(), '/');
        if ($base === '') {
            return '';
        }
        $url = $base . '/wp-json' . $path;
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }
        return $url;
    }

    /**
     * Make a request to master.
     *
     * @param string $path
     * @param array $query
     * @param array $body
     * @param string $method
     * @return array|\WP_Error
     */
    public static function request_master(string $path, array $query = [], array $body = [], string $method = 'GET'): array|\WP_Error {
        $url = self::build_master_url($path, $query);
        if ($url === '') {
            return new \WP_Error('csa_multisite_missing_url', __('Master site URL is not configured.', 'calendar-service-appointments-form'));
        }

        $headers = [];
        $key = self::get_api_key();
        if ($key !== '') {
            $headers['X-CSA-KEY'] = $key;
            if (strtoupper($method) === 'GET') {
                $query = is_array($query) ? $query : [];
                if (!isset($query['csa_key'])) {
                    $query['csa_key'] = $key;
                }
                $url = add_query_arg($query, $url);
            }
        }

        $args = [
            'timeout' => 12,
            'headers' => $headers,
        ];

        $method = strtoupper($method);
        if ($method === 'POST') {
            $body = is_array($body) ? $body : [];
            if ($key !== '' && !isset($body['csa_key'])) {
                $body['csa_key'] = $key;
            }
            $args['body'] = $body;
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : __('Request failed.', 'calendar-service-appointments-form');
            return new \WP_Error('csa_multisite_request_failed', $message, ['status' => $code]);
        }

        if (!is_array($data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return new \WP_Error('csa_multisite_invalid_response', __('Invalid response from master.', 'calendar-service-appointments-form'));
        }

        return $data;
    }

    /**
     * Fetch services from master.
     *
     * @return array
     */
    public static function fetch_master_services(): array {
        $data = self::request_master('/csa/v1/sync/services');
        if (is_wp_error($data)) {
            return [];
        }
        return isset($data['services']) && is_array($data['services']) ? $data['services'] : [];
    }

    /**
     * Fetch available times from master.
     *
     * @param string $date
     * @param int $duration_seconds
     * @return array|\WP_Error
     */
    public static function fetch_master_available_times(string $date, int $duration_seconds, string $username = ''): array|\WP_Error {
        return self::request_master('/csa/v1/sync/available-times', [
            'date' => $date,
            'duration_seconds' => (int) $duration_seconds,
            'user' => $username,
        ]);
    }

    /**
     * Fetch available days from master.
     *
     * @param string $month
     * @param int $duration_seconds
     * @return array|\WP_Error
     */
    public static function fetch_master_available_days(string $month, int $duration_seconds, string $username = ''): array|\WP_Error {
        return self::request_master('/csa/v1/sync/available-days', [
            'month' => $month,
            'duration_seconds' => (int) $duration_seconds,
            'user' => $username,
        ]);
    }

    /**
     * Book an appointment on master.
     *
     * @param string $date
     * @param string $time
     * @param string $service_title
     * @param int $duration_seconds
     * @param array $submission_data
     * @return array|\WP_Error
     */
    public static function book_on_master(string $date, string $time, string $service_title, int $duration_seconds, array $submission_data = []): array|\WP_Error {
        $payload = [
            'date' => $date,
            'time' => $time,
            'service' => $service_title,
            'duration_seconds' => (int) $duration_seconds,
        ];
        if (!empty($submission_data)) {
            $payload['submission_data'] = wp_json_encode($submission_data);
        }

        return self::request_master('/csa/v1/sync/book', [], $payload, 'POST');
    }
}
