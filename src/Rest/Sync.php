<?php
/**
 * REST API endpoints for multisite sync
 *
 * @package CalendarServiceAppointmentsForm\Rest
 */

namespace CalendarServiceAppointmentsForm\Rest;

use CalendarServiceAppointmentsForm\Ajax\Handlers;
use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Multisite;

if (!defined('ABSPATH')) {
    exit;
}

class Sync {

    private const REST_NAMESPACE = 'csa/v1';

    private const ROUTE_SERVICES = '/sync/services';
    private const ROUTE_AVAILABLE_TIMES = '/sync/available-times';
    private const ROUTE_AVAILABLE_DAYS = '/sync/available-days';
    private const ROUTE_BOOK = '/sync/book';

    /**
     * @var Sync|null
     */
    private static $instance = null;

    /**
     * @return Sync
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, self::ROUTE_SERVICES, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_services'],
            'permission_callback' => [$this, 'authorize_request'],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::ROUTE_AVAILABLE_TIMES, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_available_times'],
            'permission_callback' => [$this, 'authorize_request'],
            'args' => [
                'date' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'duration_seconds' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'user' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::ROUTE_AVAILABLE_DAYS, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_available_days'],
            'permission_callback' => [$this, 'authorize_request'],
            'args' => [
                'month' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'duration_seconds' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'user' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::ROUTE_BOOK, [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'book_appointment'],
            'permission_callback' => [$this, 'authorize_request'],
            'args' => [
                'date' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'time' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'service' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'duration_seconds' => [
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
                'submission_data' => [
                    'required' => false,
                ],
            ],
        ]);
    }

    /**
     * Authorize sync request.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function authorize_request($request) {
        if (!Multisite::is_master()) {
            return new \WP_Error('csa_sync_disabled', __('Sync is not enabled on this site.', 'calendar-service-appointments-form'), ['status' => 404]);
        }

        $expected = Multisite::ensure_master_key();
        if ($expected === '') {
            return new \WP_Error('csa_sync_missing_key', __('Sync key is missing.', 'calendar-service-appointments-form'), ['status' => 403]);
        }

        $provided = $request->get_header('X-CSA-KEY');
        if (!$provided) {
            $provided = $request->get_param('csa_key');
        }

        if (!$provided || $provided !== $expected) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return new \WP_Error('csa_sync_invalid_key', __('Invalid API key.', 'calendar-service-appointments-form'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Return services list.
     *
     * @return \WP_REST_Response
     */
    public function get_services() {
        $services = Database::get_instance()->get_services();
        return rest_ensure_response(['services' => is_array($services) ? $services : []]);
    }

    /**
     * Return available times for a date.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_available_times($request) {
        $date = $request->get_param('date');
        $duration = (int) $request->get_param('duration_seconds');
        $username = $request->get_param('user');
        if (!$this->is_valid_date($date)) {
            return new \WP_Error('csa_invalid_date', __('Invalid date', 'calendar-service-appointments-form'), ['status' => 400]);
        }
        if ($duration <= 0) {
            return new \WP_Error('csa_invalid_duration', __('Invalid duration', 'calendar-service-appointments-form'), ['status' => 400]);
        }

        if (Access::is_anyone_username($username)) {
            $times = Handlers::get_instance()->build_available_times_anyone($date, $duration);
            return rest_ensure_response(['times' => $times]);
        }

        $user_id = $username ? Access::resolve_enabled_user_id($username) : null;
        $times = Handlers::get_instance()->build_available_times($date, $duration, $user_id);
        return rest_ensure_response(['times' => $times]);
    }

    /**
     * Return available days for a month.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_available_days($request) {
        $month = $request->get_param('month');
        $duration = (int) $request->get_param('duration_seconds');
        $username = $request->get_param('user');
        if (!$this->is_valid_month($month)) {
            return new \WP_Error('csa_invalid_month', __('Invalid month', 'calendar-service-appointments-form'), ['status' => 400]);
        }
        if ($duration <= 0) {
            return new \WP_Error('csa_invalid_duration', __('Invalid duration', 'calendar-service-appointments-form'), ['status' => 400]);
        }

        $slots_needed = $duration > 0 ? (int) ceil($duration / 1800) : 1;
        if (Access::is_anyone_username($username)) {
            $days = Handlers::get_instance()->build_available_days_anyone($month, $slots_needed);
            return rest_ensure_response(['days' => $days]);
        }
        $user_id = $username ? Access::resolve_enabled_user_id($username) : null;
        $days = Handlers::get_instance()->build_available_days($month, $slots_needed, $user_id);
        return rest_ensure_response(['days' => $days]);
    }

    /**
     * Book an appointment from a child site.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function book_appointment($request) {
        $date = $request->get_param('date');
        $time = $request->get_param('time');
        $service = $request->get_param('service');
        $duration = (int) $request->get_param('duration_seconds');

        if (!$this->is_valid_date($date)) {
            return new \WP_Error('csa_invalid_date', __('Invalid date', 'calendar-service-appointments-form'), ['status' => 400]);
        }
        if (!is_string($time) || $time === '') {
            return new \WP_Error('csa_invalid_time', __('Invalid time', 'calendar-service-appointments-form'), ['status' => 400]);
        }
        $time = strlen($time) >= 5 ? substr($time, 0, 5) : $time;

        if ($duration <= 0) {
            $duration = $this->get_duration_for_service($service);
        }
        if ($duration <= 0) {
            return new \WP_Error('csa_invalid_duration', __('Invalid service duration.', 'calendar-service-appointments-form'), ['status' => 400]);
        }

        $submission_data = $request->get_param('submission_data');
        if (is_string($submission_data)) {
            $decoded = json_decode($submission_data, true);
            if (is_array($decoded)) {
                $submission_data = $decoded;
            }
        }
        if (!is_array($submission_data)) {
            $submission_data = [];
        }
        if ($service && !isset($submission_data['csa_service'])) {
            $submission_data['csa_service'] = $service;
        }
        if ($duration > 0 && !isset($submission_data['csa_custom_duration_seconds'])) {
            $submission_data['csa_custom_duration_seconds'] = $duration;
        }

        $handler = Handlers::get_instance();
        $user_id = $this->resolve_user_id_from_submission($submission_data);
        if (!$handler->check_time_range_available($date, $time, $duration, $user_id)) {
            return new \WP_Error('csa_unavailable', __('That date and time is not available.', 'calendar-service-appointments-form'), ['status' => 400]);
        }

        $slots = $handler->build_slot_times_for_duration($time, $duration);
        if (empty($slots)) {
            return new \WP_Error('csa_unavailable', __('That date and time is not available.', 'calendar-service-appointments-form'), ['status' => 400]);
        }

        $db = Database::get_instance();
        $reserved = [];
        foreach ($slots as $slot) {
            $ok = $db->reserve_time_slot($date, $slot, $user_id);
            if (!$ok) {
                foreach ($reserved as $r) {
                    $db->unblock_time_slot($date, $r, $user_id);
                }
                return new \WP_Error('csa_unavailable', __('That date and time is not available.', 'calendar-service-appointments-form'), ['status' => 400]);
            }
            $reserved[] = $slot;
        }

        $insert_id = $db->insert_appointment(null, $date, $time, $submission_data, $user_id);
        if (!$insert_id) {
            foreach ($reserved as $r) {
                $db->unblock_time_slot($date, $r, $user_id);
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                global $wpdb;
            }
            return new \WP_Error('csa_booking_failed', __('Failed to store appointment.', 'calendar-service-appointments-form'), ['status' => 500]);
        }

        foreach ($reserved as $r) {
            $db->unblock_time_slot($date, $r, $user_id);
        }

        return rest_ensure_response([
            'appointment_id' => $insert_id,
            'date' => $date,
            'time' => $time,
        ]);
    }

    private function resolve_user_id_from_submission($submission_data) {
        if (is_array($submission_data)) {
            if (!empty($submission_data['csa_user']) && !Access::is_anyone_username($submission_data['csa_user'])) {
                return Access::resolve_enabled_user_id($submission_data['csa_user']);
            }
            if (!empty($submission_data['csa_username']) && !Access::is_anyone_username($submission_data['csa_username'])) {
                return Access::resolve_enabled_user_id($submission_data['csa_username']);
            }
        }
        return Access::get_default_admin_id();
    }

    private function is_valid_date($date) {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    private function is_valid_month($month) {
        return is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month);
    }

    private function get_duration_for_service($service) {
        if (!is_string($service) || $service === '') {
            return 0;
        }
        $services = Database::get_instance()->get_services();
        $needle = $this->normalize_service_key($service);
        foreach ($services as $svc) {
            if (!is_array($svc)) {
                continue;
            }
            $title = isset($svc['title']) ? (string) $svc['title'] : '';
            $duration = isset($svc['duration']) ? (string) $svc['duration'] : '';
            if ($title === '' || $duration === '') {
                continue;
            }
            if ($this->normalize_service_key($title) === $needle && ctype_digit($duration)) {
                return (int) $duration;
            }
        }
        return 0;
    }

    private function normalize_service_key($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
