<?php
declare(strict_types=1);
/**
 * REST API endpoint for day details
 *
 * @package CalendarServiceAppointmentsForm\Rest
 */

namespace CalendarServiceAppointmentsForm\Rest;

use CalendarServiceAppointmentsForm\Ajax\Handlers\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers REST routes for appointment day details.
 */
class DayDetails {

    /**
     * REST namespace
     *
     * @var string
     */
    private const REST_NAMESPACE = 'csa/v1';

    /**
     * Route base
     *
     * @var string
     */
    private const ROUTE_DAY_DETAILS = '/day-details';
    private const ROUTE_MONTH_DETAILS = '/month-details';

    /**
     * @var DayDetails|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return DayDetails
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::ROUTE_DAY_DETAILS, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_day_details'],
            'permission_callback' => [$this, 'can_view_day_details'],
            'args' => [
                'date' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_date_param'],
                ],
                'include_appointments' => [
                    'required' => false,
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'include_submission_data' => [
                    'required' => false,
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::ROUTE_MONTH_DETAILS, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_month_details'],
            'permission_callback' => [$this, 'can_view_day_details'],
            'args' => [
                'month' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_month_param'],
                ],
                'include_time_slots' => [
                    'required' => false,
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'include_appointments' => [
                    'required' => false,
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'include_submission_data' => [
                    'required' => false,
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);
    }

    /**
     * Ensure the current user can access the endpoint.
     *
     * @return bool
     */
    public function can_view_day_details(): bool {
        return true;
    }

    /**
     * Validate date parameter.
     *
     * @param mixed $param
     * @return bool
     */
    public function validate_date_param(mixed $param): bool {
        if (!is_string($param)) {
            return false;
        }
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
    }

    /**
     * Validate month parameter.
     *
     * @param mixed $param
     * @return bool
     */
    public function validate_month_param(mixed $param): bool {
        if (!is_string($param)) {
            return false;
        }
        return (bool) preg_match('/^\d{4}-\d{2}$/', $param);
    }

    /**
     * REST callback for day details.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_day_details(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $date = $request->get_param('date');
        $include_appointments = (bool) $request->get_param('include_appointments');
        $include_submission_data = (bool) $request->get_param('include_submission_data');
        $can_view_sensitive = current_user_can(Handlers::CAPABILITY);

        if (!$can_view_sensitive) {
            $include_submission_data = false;
        }

        if ($include_submission_data && !$include_appointments) {
            $include_appointments = true;
        }

        try {
            $payload = Handlers::get_instance()->build_day_details_payload($date);
        } catch (\Throwable $e) {
            return new \WP_Error(
                'csa_rest_day_details_error',
                __('Error loading day details.', 'calendar-service-appointments-form'),
                ['status' => 500]
            );
        }

        if (is_wp_error($payload)) {
            return $payload;
        }

        $payload['time_slots'] = $this->decorate_time_slots(
            $payload['time_slots'],
            $include_appointments,
            $include_submission_data
        );

        $payload['available_slots'] = $this->collect_slots_by_status($payload['time_slots'], 'available');
        $payload['booked_slots'] = $this->collect_slots_by_status($payload['time_slots'], 'booked');

        return rest_ensure_response($payload);
    }

    /**
     * REST callback for month details.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_month_details(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $month = $request->get_param('month');
        $include_time_slots = (bool) $request->get_param('include_time_slots');
        $include_appointments = (bool) $request->get_param('include_appointments');
        $include_submission_data = (bool) $request->get_param('include_submission_data');
        $can_view_sensitive = current_user_can(Handlers::CAPABILITY);

        if (!$can_view_sensitive) {
            $include_submission_data = false;
        }

        if ($include_submission_data && !$include_appointments) {
            $include_appointments = true;
        }

        $dt = \DateTime::createFromFormat('!Y-m', $month);
        if (!$dt) {
            return new \WP_Error('csa_invalid_month', __('Invalid month format', 'calendar-service-appointments-form'), ['status' => 400]);
        }

        $days_in_month = (int) $dt->format('t');
        $year = (int) $dt->format('Y');
        $mon = (int) $dt->format('m');

        $days = [];
        $timezone_label = null;

        for ($d = 1; $d <= $days_in_month; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $mon, $d);
            $payload = Handlers::get_instance()->build_day_details_payload($date);
            if (is_wp_error($payload)) {
                continue;
            }
            if ($timezone_label === null && isset($payload['timezone_label'])) {
                $timezone_label = $payload['timezone_label'];
            }

            $payload['time_slots'] = $this->decorate_time_slots(
                $payload['time_slots'],
                $include_appointments,
                $include_submission_data
            );

            $available_slots = $this->collect_slots_by_status($payload['time_slots'], 'available');
            $booked_slots = $this->collect_slots_by_status($payload['time_slots'], 'booked');

            $day_payload = [
                'date' => $date,
                'available_slots' => $available_slots,
                'booked_slots' => $booked_slots,
                'available_count' => count($available_slots),
                'booked_count' => count($booked_slots),
            ];

            if ($include_time_slots) {
                $day_payload['time_slots'] = $payload['time_slots'];
            }

            $days[] = $day_payload;
        }

        return rest_ensure_response([
            'month' => $month,
            'timezone_label' => $timezone_label,
            'days' => $days,
        ]);
    }

    /**
     * Decorate time slots with status and optional appointment data.
     *
     * @param array $time_slots
     * @param bool $include_appointments
     * @param bool $include_submission_data
     * @return array
     */
    private function decorate_time_slots(array $time_slots, bool $include_appointments, bool $include_submission_data): array {
        if (!is_array($time_slots)) {
            return [];
        }

        foreach ($time_slots as &$slot) {
            if (!is_array($slot)) {
                continue;
            }

            $has_appointment = !empty($slot['appointments']);
            if ($has_appointment || !empty($slot['is_occupied'])) {
                $slot['status'] = 'booked';
            } elseif (!empty($slot['is_blocked_explicit'])) {
                $slot['status'] = 'blocked';
            } elseif (!empty($slot['is_default_available'])) {
                $slot['status'] = 'available';
            } else {
                $slot['status'] = 'unavailable';
            }

            if (!$include_appointments) {
                unset($slot['appointments']);
                continue;
            }

            if (!empty($slot['appointments']) && !$include_submission_data) {
                $slot['appointments'] = $this->sanitize_appointment($slot['appointments']);
            }
        }
        unset($slot);

        return $time_slots;
    }

    /**
     * Collect slot times by status.
     *
     * @param array $time_slots
     * @param string $status
     * @return array
     */
    private function collect_slots_by_status(array $time_slots, string $status): array {
        $out = [];
        foreach ($time_slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            if (!isset($slot['status']) || $slot['status'] !== $status) {
                continue;
            }
            if (!empty($slot['time'])) {
                $out[] = $slot['time'];
            }
        }
        return $out;
    }

    /**
     * Strip appointment payload to non-sensitive fields.
     *
     * @param array $appointment
     * @return array
     */
    private function sanitize_appointment(array $appointment): array {
        if (!is_array($appointment)) {
            return [];
        }

        $allowed = [
            'id' => true,
            'appt_id' => true,
            'date' => true,
            'time' => true,
            'status' => true,
            'service' => true,
            'duration_seconds' => true,
            'end_time' => true,
            'created_at' => true,
            'submitted_at_unix' => true,
        ];

        return array_intersect_key($appointment, $allowed);
    }
}
