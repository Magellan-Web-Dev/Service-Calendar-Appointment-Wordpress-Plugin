<?php
/**
 * Handlers class
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Holidays;
use CalendarServiceAppointmentsForm\Core\Multisite;
use CalendarServiceAppointmentsForm\Core\Submissions;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all AJAX requests
 */
class Handlers {

    /**
     * Plugin text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'calendar-service-appointments-form';

    /**
     * AJAX nonce action for admin requests
     *
     * @var string
     */
    public const NONCE_ACTION = 'csa_admin_nonce';

    /**
     * Capability required for admin actions
     *
     * @var string
     */
    public const CAPABILITY = 'manage_options';

    /**
     * @var Handlers|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Handlers
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_ajax_csa_get_day_details', [$this, 'get_day_details']);
        add_action('wp_ajax_nopriv_csa_get_day_details', [$this, 'get_day_details']);
        add_action('wp_ajax_csa_delete_appointment', [$this, 'delete_appointment']);
        add_action('wp_ajax_csa_fetch_submission_values', [$this, 'fetch_submission_values']);
        add_action('wp_ajax_csa_block_time_slot', [$this, 'block_time_slot']);
        add_action('wp_ajax_csa_unblock_time_slot', [$this, 'unblock_time_slot']);
        add_action('wp_ajax_csa_get_available_times', [$this, 'get_available_times']);
        add_action('wp_ajax_nopriv_csa_get_available_times', [$this, 'get_available_times']);
        add_action('wp_ajax_csa_get_weekly_availability', [$this, 'get_weekly_availability']);
        add_action('wp_ajax_csa_save_weekly_availability', [$this, 'save_weekly_availability']);
        add_action('wp_ajax_csa_save_holiday_availability', [$this, 'save_holiday_availability']);
        add_action('wp_ajax_csa_set_manual_override', [$this, 'set_manual_override']);
        add_action('wp_ajax_csa_save_timezone', [$this, 'save_timezone']);
        add_action('wp_ajax_csa_get_available_months', [$this, 'get_available_months']);
        add_action('wp_ajax_nopriv_csa_get_available_months', [$this, 'get_available_months']);
        add_action('wp_ajax_csa_get_available_days', [$this, 'get_available_days']);
        add_action('wp_ajax_nopriv_csa_get_available_days', [$this, 'get_available_days']);
        add_action('wp_ajax_csa_reschedule_appointment', [$this, 'reschedule_appointment']);
        add_action('wp_ajax_csa_create_custom_appointment', [$this, 'create_custom_appointment']);
    }

    /**
     * Get day details AJAX handler
     *
     * @return void
     */
    public function get_day_details() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSA] get_day_details hit. action=' . (isset($_REQUEST['action']) ? $_REQUEST['action'] : 'none'));
        }
        $prev_handler = set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] get_day_details invalid nonce.');
                }
                wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
            }

            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $user_id = $this->resolve_request_user_id(true);
            $current_user_id = get_current_user_id();
            $is_admin = current_user_can(self::CAPABILITY);
            $is_enabled_user = $current_user_id ? Access::is_user_enabled($current_user_id) : false;

            if (!$is_admin) {
                if (!$is_enabled_user || (!$user_id || (int) $user_id !== (int) $current_user_id)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            '[CSA] get_day_details unauthorized. current_user=%d resolved_user=%s enabled=%s date=%s',
                            (int) $current_user_id,
                            $user_id === null ? 'null' : (string) $user_id,
                            $is_enabled_user ? 'yes' : 'no',
                            $date
                        ));
                    }
                    wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
                }
            }

            $payload = $this->build_day_details_payload($date, $user_id);
            if (is_wp_error($payload)) {
                restore_error_handler();
                wp_send_json_error(['message' => $payload->get_error_message()]);
            }

            restore_error_handler();
            wp_send_json_success($payload);
        } catch (\Throwable $e) {
            restore_error_handler();
            $message = __('Error loading day details.', self::TEXT_DOMAIN);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message .= ' ' . $e->getMessage();
                error_log('[CSA] get_day_details exception: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => $message]);
        }
    }

    /**
     * Build day details payload (shared by AJAX + REST).
     *
     * @param string $date
     * @return array|\WP_Error
     */
    public function build_day_details_payload($date, $user_id = null) {
        $date = is_string($date) ? trim($date) : '';
        if ($date === '') {
            return new \WP_Error('csa_invalid_date', __('Invalid date', self::TEXT_DOMAIN), ['status' => 400]);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new \WP_Error('csa_invalid_date', __('Invalid date format', self::TEXT_DOMAIN), ['status' => 400]);
        }

        $db = Database::get_instance();
        $blocked_slots = $db->get_blocked_slots_for_date($date, $user_id);
        if (!is_array($blocked_slots)) {
            $blocked_slots = [];
        }

        $submissions = Submissions::get_instance();
        $appointments = $submissions->get_appointments_for_date($date, $user_id);
        if (!is_array($appointments)) {
            $appointments = [];
        }
        $service_duration_map = $this->get_service_duration_map();

        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $time_slots = [];
        $occupied_times = [];
        $hours = $this->get_business_hours();
        foreach ($hours as $time) {

            if ($holiday_closed) {
                $is_default_available = false;
            } else {
                $is_default_available = in_array($time, $default_hours, true);
                if (isset($overrides[$time])) {
                    if ($overrides[$time] === 'allow') {
                        $is_default_available = true;
                    } elseif ($overrides[$time] === 'block') {
                        $is_default_available = false;
                    }
                }
            }

            $is_blocked_explicit = false;
            foreach ($blocked_slots as $slot) {
                if (!is_array($slot) || empty($slot['block_time'])) {
                    continue;
                }
                if (substr((string) $slot['block_time'], 0, 5) == $time) {
                    $is_blocked_explicit = true;
                    break;
                }
            }

            // collect first appointment that matches this time
            $matching = null;
            foreach ($appointments as $apt) {
                if (!is_array($apt) || empty($apt['time'])) {
                    continue;
                }
                $apt_time = $this->normalize_time_value($apt['time']);
                if ($apt_time === '') {
                    continue;
                }
                if ($apt_time == $time) {
                    $duration_seconds = $this->get_appointment_duration_seconds($apt, $service_duration_map);
                    if ($duration_seconds > 0) {
                        $slots_needed = $this->get_slots_needed($duration_seconds);
                        for ($i = 1; $i < $slots_needed; $i++) {
                            $next_time = $this->add_minutes($time, 30 * $i);
                            if ($next_time) {
                                $occupied_times[$next_time] = true;
                            }
                        }
                        $apt['duration_seconds'] = $duration_seconds;
                        $apt['end_time'] = $this->add_minutes($time, 30 * $slots_needed);
                    } else {
                        $apt['duration_seconds'] = 0;
                    }
                    $matching = $apt;
                    break;
                }
            }

            if (empty($matching) && isset($occupied_times[$time])) {
                $time_slots[] = [
                    'time' => $time,
                    'is_default_available' => $is_default_available,
                    'is_blocked_explicit' => $is_blocked_explicit,
                    'is_occupied' => true,
                    'slot_duration_seconds' => 1800,
                ];
                continue;
            }

            $slot_payload = [
                'time' => $time,
                'is_default_available' => $is_default_available,
                'is_blocked_explicit' => $is_blocked_explicit,
                'is_occupied' => false,
                'slot_duration_seconds' => 1800,
            ];
            if (!empty($matching)) {
                $slot_payload['appointments'] = $matching;
            }
            $time_slots[] = $slot_payload;
        }

        // Enrich appointments with full submission fields when possible
        if (!empty($time_slots)) {
            foreach ($time_slots as &$slot) {
                if (empty($slot['appointments'])) {
                    continue;
                }
                $appt = $slot['appointments'];
                if (empty($appt['id'])) {
                    continue;
                }

                // If the appointments table stored submission_data, prefer that
                $full = [];
                if (!empty($appt['submission_data']) && is_array($appt['submission_data'])) {
                    $full = [
                        'id' => isset($appt['id']) ? $appt['id'] : null,
                        'date' => isset($appt['date']) ? $appt['date'] : null,
                        'time' => isset($appt['time']) ? $appt['time'] : null,
                        'all_data' => $appt['submission_data'],
                    ];
                }

                // Fallback to the Submissions helper to get parsed fields
                if (empty($full) || empty($full['all_data'])) {
                    $helper = $submissions->get_appointment_by_submission_id($appt['id']);
                    if (!empty($helper)) {
                        $full = array_merge($full, $helper);
                    }
                }

                // If helper didn't return all_data, attempt a direct fallback to the Elementor values table
                if ((empty($full) || empty($full['all_data'])) && !empty($appt['id'])) {
                    global $wpdb;
                    $values_table = $wpdb->prefix . Submissions::TABLE_SUBMISSION_VALUES;
                    $values_table_exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($values_table) . "'");
                    if ($values_table_exists) {
                        $vals = $wpdb->get_results($wpdb->prepare(
                            "SELECT `key`, `value` FROM {$values_table} WHERE submission_id = %d",
                            intval($appt['id'])
                        ), ARRAY_A);
                        $all = [];
                        foreach ($vals as $v) {
                            $all[$v['key']] = $v['value'];
                        }
                        if (empty($full)) {
                            $full = [];
                        }
                        $full['all_data'] = $all;
                    }
                }

                if (!empty($full)) {
                    if (isset($appt['appt_id'])) {
                        $full['appt_id'] = $appt['appt_id'];
                    }
                    if (empty($full['time'])) { $full['time'] = isset($appt['time']) ? $appt['time'] : null; }
                    if (empty($full['date'])) { $full['date'] = isset($appt['date']) ? $appt['date'] : null; }
                    // merge created_at if missing
                    if (empty($full['created_at']) && isset($appt['created_at'])) {
                        $full['created_at'] = $appt['created_at'];
                    }
                    if (!isset($full['submitted_at_unix']) && isset($appt['submitted_at_unix'])) {
                        $full['submitted_at_unix'] = $appt['submitted_at_unix'];
                    }
                    if (!empty($full['all_data']) && is_array($full['all_data'])) {
                        if (!empty($full['all_data']['csa_service'])) {
                            $full['service'] = is_string($full['all_data']['csa_service'])
                                ? $this->normalize_service_title($full['all_data']['csa_service'])
                                : $full['all_data']['csa_service'];
                        } else {
                            foreach ($full['all_data'] as $key => $val) {
                                if (stripos($key, 'service') !== false) {
                                    $full['service'] = is_string($val) ? $this->normalize_service_title($val) : $val;
                                    break;
                                }
                            }
                        }
                        if (!empty($full['all_data']['csa_custom_appointment'])) {
                            $custom_title = isset($full['all_data']['csa_custom_title']) ? $full['all_data']['csa_custom_title'] : '';
                            $custom_title = is_string($custom_title) ? trim($custom_title) : '';
                            $full['name'] = $custom_title !== '' ? $custom_title : __('Custom Appointment', self::TEXT_DOMAIN);
                        }
                    }
                    $duration_seconds = $this->get_appointment_duration_seconds($full, $service_duration_map);
                    if ($duration_seconds > 0) {
                        $slots_needed = $this->get_slots_needed($duration_seconds);
                        $end_time = $this->add_minutes(substr($full['time'], 0, 5), 30 * $slots_needed);
                        $full['duration_seconds'] = $duration_seconds;
                        $full['end_time'] = $end_time ? $end_time : null;
                    } else {
                        $full['duration_seconds'] = 0;
                    }
                    $slot['appointments'] = $full;
                }
            }
            unset($slot);
        }

        return [
            'date' => $date,
            'timezone_label' => $this->get_timezone_label(),
            'time_slots' => $time_slots,
        ];
    }

    /**
     * Delete appointment AJAX handler
     * Accepts either `appt_id` (appointment-table id) or `submission_id` + `date` + `time`.
     *
     * @return void
     */
    public function delete_appointment() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $appt_id = isset($_POST['appt_id']) ? intval($_POST['appt_id']) : 0;
        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';

        $db = Database::get_instance();

        if ($appt_id) {
            $res = $db->delete_appointment_by_id($appt_id);
        } elseif ($submission_id && $date && $time) {
            $res = $db->delete_appointment_by_submission($submission_id, $date, $time);
        } else {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        if ($res === false) {
            wp_send_json_error(['message' => __('Failed to delete appointment', self::TEXT_DOMAIN)]);
        }

        wp_send_json_success(['message' => __('Appointment deleted', self::TEXT_DOMAIN)]);
    }

    /**
     * AJAX: reschedule an appointment to a new date/time.
     *
     * @return void
     */
    public function reschedule_appointment() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $appt_id = isset($_POST['appt_id']) ? intval($_POST['appt_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';

        if (!$appt_id || empty($date) || empty($time)) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $time = strlen($time) >= 5 ? substr($time, 0, 5) : $time;

        $db = Database::get_instance();
        $appt = $db->get_appointment_by_id($appt_id);
        if (!$appt) {
            wp_send_json_error(['message' => __('Appointment not found', self::TEXT_DOMAIN)]);
        }

        $service_duration_map = $this->get_service_duration_map();
        $appointment = [
            'time' => isset($appt['appointment_time']) ? $appt['appointment_time'] : null,
            'date' => isset($appt['appointment_date']) ? $appt['appointment_date'] : null,
            'all_data' => isset($appt['submission_data']) ? $appt['submission_data'] : [],
        ];
        if (!empty($appointment['all_data']['csa_service'])) {
            $appointment['service'] = $appointment['all_data']['csa_service'];
        }
        $duration_seconds = $this->get_appointment_duration_seconds($appointment, $service_duration_map);
        if ($duration_seconds <= 0) {
            wp_send_json_error(['message' => __('Unable to determine service duration for this appointment.', self::TEXT_DOMAIN)]);
        }

        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $hours = $this->get_business_hours();
        $hours_set = array_fill_keys($hours, true);
        $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($date, $user_id));

        $appointment_user_id = isset($appt['user_id']) ? intval($appt['user_id']) : $this->resolve_request_user_id(true);
        $submissions = Submissions::get_instance();
        $appointments = $submissions->get_appointments_for_date($date, $appointment_user_id);
        if (!is_array($appointments)) {
            $appointments = [];
        }
        $filtered = [];
        foreach ($appointments as $row) {
            if (isset($row['appt_id']) && intval($row['appt_id']) === $appt_id) {
                continue;
            }
            $filtered[] = $row;
        }
        $booked_set = $this->build_booked_set($filtered, $service_duration_map);
        $slots_needed = $this->get_slots_needed($duration_seconds);
        if (!$this->is_time_range_available($date, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
            wp_send_json_error(['message' => __('That date and time is not available for this appointment.', self::TEXT_DOMAIN)]);
        }

        $updated = $db->reschedule_appointment_by_id($appt_id, $date, $time);
        if ($updated === false) {
            wp_send_json_error(['message' => __('Failed to reschedule appointment', self::TEXT_DOMAIN)]);
        }

        wp_send_json_success(['message' => __('Appointment rescheduled', self::TEXT_DOMAIN)]);
    }

    /**
     * AJAX: create a custom appointment booking.
     *
     * @return void
     */
    public function create_custom_appointment() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        if (empty($date) || empty($time) || $duration_seconds <= 0) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $time = strlen($time) >= 5 ? substr($time, 0, 5) : $time;

        $db = Database::get_instance();
        $user_id = $this->resolve_request_user_id(true);
        $submissions = Submissions::get_instance();
        $appointments = $submissions->get_appointments_for_date($date, $user_id);
        if (!is_array($appointments)) {
            $appointments = [];
        }
        $service_duration_map = $this->get_service_duration_map();
        $booked_set = $this->build_booked_set($appointments, $service_duration_map);
        $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($date, $user_id));
        $hours = $this->get_business_hours();
        $hours_set = array_fill_keys($hours, true);
        $slots_needed = $this->get_slots_needed($duration_seconds);

        if (!$this->is_time_range_open($time, $slots_needed, $blocked_set, $booked_set, $hours_set)) {
            wp_send_json_error(['message' => __('That time range is not available for a custom appointment.', self::TEXT_DOMAIN)]);
        }

        $submission_data = [
            'csa_custom_appointment' => true,
            'csa_custom_title' => $title,
            'csa_custom_duration_seconds' => $duration_seconds,
            'csa_custom_notes' => $notes,
        ];

        $insert_id = $db->insert_appointment(null, $date, $time, $submission_data, $user_id);
        if (!$insert_id) {
            wp_send_json_error(['message' => __('Failed to schedule custom appointment', self::TEXT_DOMAIN)]);
        }

        wp_send_json_success([
            'message' => __('Custom appointment scheduled', self::TEXT_DOMAIN),
            'appointment_id' => $insert_id,
        ]);
    }

    /**
     * AJAX: fetch raw submission values for a submission id
     *
     * @return void
     */
    public function fetch_submission_values() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        if (!$submission_id) {
            wp_send_json_error(['message' => __('Invalid submission id', self::TEXT_DOMAIN)]);
        }

        global $wpdb;
        $values_table = $wpdb->prefix . Submissions::TABLE_SUBMISSION_VALUES;
        $exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($values_table) . "'");
        if (!$exists) {
            wp_send_json_error(['message' => __('Elementor submission table not found', self::TEXT_DOMAIN)]);
        }

        $vals = $wpdb->get_results($wpdb->prepare(
            "SELECT `key`, `value` FROM {$values_table} WHERE submission_id = %d",
            $submission_id
        ), ARRAY_A);

        $out = [];
        foreach ($vals as $v) {
            $out[$v['key']] = $v['value'];
        }

        wp_send_json_success(['values' => $out]);
    }

    /**
     * Block time slot AJAX handler
     *
     * @return void
     */
    public function block_time_slot() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';

        if (empty($date) || empty($time)) {
            wp_send_json_error(['message' => __('Invalid date or time', self::TEXT_DOMAIN)]);
        }

        $user_id = $this->resolve_request_user_id(true);
        $db = Database::get_instance();
        $result = $db->block_time_slot($date, $time . ':00', $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Time slot blocked successfully', self::TEXT_DOMAIN)]);
        } else {
            wp_send_json_error(['message' => __('Failed to block time slot', self::TEXT_DOMAIN)]);
        }
    }

    /**
     * Unblock time slot AJAX handler
     *
     * @return void
     */
    public function unblock_time_slot() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';

        if (empty($date) || empty($time)) {
            wp_send_json_error(['message' => __('Invalid date or time', self::TEXT_DOMAIN)]);
        }

        $user_id = $this->resolve_request_user_id(true);
        $db = Database::get_instance();
        $result = $db->unblock_time_slot($date, $time . ':00', $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Time slot unblocked successfully', self::TEXT_DOMAIN)]);
        } else {
            wp_send_json_error(['message' => __('Failed to unblock time slot', self::TEXT_DOMAIN)]);
        }
    }

    /**
     * Get available times AJAX handler
     *
     * @return void
     */
    public function get_available_times() {
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $user_id = $this->resolve_request_user_id(true);

        if (empty($date)) {
            wp_send_json_error(['message' => __('Invalid date', self::TEXT_DOMAIN)]);
        }
        if (!empty($_POST['user']) && !$user_id) {
            wp_send_json_error(['message' => __('Invalid user for booking.', self::TEXT_DOMAIN)]);
        }

        if (Multisite::is_child()) {
            $username = isset($_POST['user']) ? sanitize_text_field($_POST['user']) : '';
            $response = Multisite::fetch_master_available_times($date, $duration_seconds, $username);
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => $response->get_error_message()]);
            }
            $times = isset($response['times']) && is_array($response['times']) ? $response['times'] : [];
            wp_send_json_success(['times' => $times]);
        }

        $available_times = $this->build_available_times($date, $duration_seconds, $user_id);
        wp_send_json_success(['times' => $available_times]);
    }

    /**
     * Build available times for a date.
     *
     * @param string $date
     * @param int $duration_seconds
     * @return array
     */
    public function build_available_times($date, $duration_seconds, $user_id = null) {
        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');
        if ($date < $today) {
            return [];
        }

        $db = Database::get_instance();
        $blocked_slots = $db->get_blocked_slots_for_date($date, $user_id);
        $submissions = Submissions::get_instance();
        $appointments = $submissions->get_appointments_for_date($date, $user_id);

        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $available_times = [];
        $hours = $this->get_business_hours();
        $service_duration_map = $this->get_service_duration_map();
        $hours_set = array_fill_keys($hours, true);
        $blocked_set = $this->build_time_set($blocked_slots);
        $booked_set = $this->build_booked_set($appointments, $service_duration_map);
        $slots_needed = $this->get_slots_needed($duration_seconds);
        foreach ($hours as $time) {
            if (!$this->is_time_range_available($date, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
                continue;
            }

        if ($date === $today && $now) {
            $slot_dt = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $now->getTimezone());
            if ($slot_dt) {
                $cutoff = (clone $now)->modify('+2 hours');
                if ($slot_dt < $cutoff) {
                    continue;
                }
            }
        }
            $label = $this->format_time_label($date, $time);
            $available_times[] = [
                'value' => $time,
                'label' => $label,
            ];
        }

        return $available_times;
    }

    /**
     * AJAX: get available months (next 12 months that have at least one available day)
     */
    public function get_available_months() {
        $months = [];
        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $user_id = $this->resolve_request_user_id(true);
        $slots_needed = $this->get_slots_needed($duration_seconds);
        $service_duration_map = $this->get_service_duration_map();
        if (!empty($_POST['user']) && !$user_id) {
            wp_send_json_error(['message' => __('Invalid user for booking.', self::TEXT_DOMAIN)]);
        }

        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');
        $start = $now ? clone $now : new \DateTime();
        for ($i = 0; $i < 12; $i++) {
            $m = clone $start;
            $m->modify("+{$i} months");
            $year = (int)$m->format('Y');
            $month = (int)$m->format('n');
            $has = false;
            $days_in_month = (int)$m->format('t');

            $weekly = $db->get_weekly_availability($user_id);
            $holiday_availability = $db->get_holiday_availability($user_id);

            for ($d = 1; $d <= $days_in_month; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                if ($dateStr < $today) {
                    continue;
                }
                $dow = (int)date('w', strtotime($dateStr));
                $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
                $overrides = $db->get_overrides_for_date($dateStr, $user_id);
                $holiday_key = Holidays::get_us_holiday_key_for_date($dateStr);
                $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
                $holiday_closed = $holiday_key && !$holiday_enabled;

                $hours = $this->get_business_hours();
                $hours_set = array_fill_keys($hours, true);
                $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($dateStr, $user_id));
                $booked_set = $this->build_booked_set($submissions->get_appointments_for_date($dateStr, $user_id), $service_duration_map);
                foreach ($hours as $time) {
                    if ($dateStr === $today && $now) {
                        $slot_dt = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $time, $now->getTimezone());
                        if ($slot_dt) {
                            $cutoff = (clone $now)->modify('+2 hours');
                            if ($slot_dt < $cutoff) {
                                continue;
                            }
                        }
                    }
                    if (!$this->is_time_range_available($dateStr, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
                        continue;
                    }

                    $has = true;
                    break 2;
                }
            }

            if ($has) {
                $months[] = [
                    'value' => sprintf('%04d-%02d', $year, $month),
                    'label' => $m->format('F Y'),
                ];
            }
        }

        wp_send_json_success(['months' => $months]);
    }

    /**
     * AJAX: get available days for a given month (expects `month` like YYYY-MM)
     */
    public function get_available_days() {
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $user_id = $this->resolve_request_user_id(true);
        $slots_needed = $this->get_slots_needed($duration_seconds);
        if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            wp_send_json_error(['message' => __('Invalid month', self::TEXT_DOMAIN)]);
        }
        if (!empty($_POST['user']) && !$user_id) {
            wp_send_json_error(['message' => __('Invalid user for booking.', self::TEXT_DOMAIN)]);
        }

        if (Multisite::is_child()) {
            $username = isset($_POST['user']) ? sanitize_text_field($_POST['user']) : '';
            $response = Multisite::fetch_master_available_days($month, $duration_seconds, $username);
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => $response->get_error_message()]);
            }
            $days = isset($response['days']) && is_array($response['days']) ? $response['days'] : [];
            wp_send_json_success(['days' => $days]);
        }

        $days = $this->build_available_days($month, $slots_needed, $user_id);
        wp_send_json_success(['days' => $days]);
    }

    /**
     * Build available days for a month.
     *
     * @param string $month
     * @param int $slots_needed
     * @return array
     */
    public function build_available_days($month, $slots_needed, $user_id = null) {
        list($year, $mon) = array_map('intval', explode('-', $month));
        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $service_duration_map = $this->get_service_duration_map();

        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');

        $dt = \DateTime::createFromFormat('!Y-n', "$year-$mon");
        $days_in_month = (int)$dt->format('t');

        $days = [];

        for ($d = 1; $d <= $days_in_month; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $mon, $d);
            if ($dateStr < $today) {
                continue;
            }
            $dow = (int)date('w', strtotime($dateStr));
            $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
            $overrides = $db->get_overrides_for_date($dateStr, $user_id);
            $holiday_key = Holidays::get_us_holiday_key_for_date($dateStr);
            $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
            $holiday_closed = $holiday_key && !$holiday_enabled;

            $hours = $this->get_business_hours();
            $hours_set = array_fill_keys($hours, true);
            $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($dateStr, $user_id));
            $booked_set = $this->build_booked_set($submissions->get_appointments_for_date($dateStr, $user_id), $service_duration_map);
            foreach ($hours as $time) {
                if ($dateStr === $today && $now) {
                    $slot_dt = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $time, $now->getTimezone());
                    if ($slot_dt) {
                        $cutoff = (clone $now)->modify('+2 hours');
                        if ($slot_dt < $cutoff) {
                            continue;
                        }
                    }
                }

                if (!$this->is_time_range_available($dateStr, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
                    continue;
                }

                $days[] = [
                    'value' => sprintf('%02d', $d),
                    'label' => date('F j, Y', strtotime($dateStr)),
                ];
                break;
            }
        }

        return $days;
    }

    /**
     * Check if a time range is available for a duration.
     *
     * @param string $date
     * @param string $start_time
     * @param int $duration_seconds
     * @return bool
     */
    public function check_time_range_available($date, $start_time, $duration_seconds, $user_id = null) {
        if (empty($date) || empty($start_time)) {
            return false;
        }
        $start_time = strlen($start_time) >= 5 ? substr($start_time, 0, 5) : $start_time;

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $hours = $this->get_business_hours();
        $hours_set = array_fill_keys($hours, true);
        $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($date, $user_id));
        $service_duration_map = $this->get_service_duration_map();
        $appointments = Submissions::get_instance()->get_appointments_for_date($date, $user_id);
        if (!is_array($appointments)) {
            $appointments = [];
        }
        $booked_set = $this->build_booked_set($appointments, $service_duration_map);
        $slots_needed = $this->get_slots_needed($duration_seconds);

        return $this->is_time_range_available($date, $start_time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set);
    }

    /**
     * Build slot times for a duration.
     *
     * @param string $start_time
     * @param int $duration_seconds
     * @return array
     */
    public function build_slot_times_for_duration($start_time, $duration_seconds) {
        $start_time = strlen($start_time) >= 5 ? substr($start_time, 0, 5) : $start_time;
        $slots_needed = $this->get_slots_needed($duration_seconds);
        $slots = [];
        for ($i = 0; $i < $slots_needed; $i++) {
            $slot_time = $i === 0 ? $start_time : $this->add_minutes($start_time, 30 * $i);
            if ($slot_time) {
                $slots[] = $slot_time;
            }
        }
        return $slots;
    }

    /**
     * AJAX: get weekly availability
     */
    public function get_weekly_availability() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $user_id = $this->resolve_request_user_id(true);
        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability($user_id);
        wp_send_json_success(['weekly' => $weekly]);
    }

    /**
     * AJAX: save weekly availability
     */
    public function save_weekly_availability() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $payload = isset($_POST['weekly']) ? wp_unslash($_POST['weekly']) : '';
        if (empty($payload)) {
            wp_send_json_error(['message' => __('Invalid data', self::TEXT_DOMAIN)]);
        }

        $weekly = json_decode(stripslashes($payload), true);
        if (!is_array($weekly)) {
            wp_send_json_error(['message' => __('Invalid format', self::TEXT_DOMAIN)]);
        }

        $user_id = $this->resolve_request_user_id(true);
        $db = Database::get_instance();
        $ok = $db->save_weekly_availability($weekly, $user_id);
        if ($ok) {
            wp_send_json_success(['message' => __('Weekly availability saved', self::TEXT_DOMAIN)]);
        }

        wp_send_json_error(['message' => __('Failed to save availability', self::TEXT_DOMAIN)]);
    }

    /**
     * AJAX: save timezone selection
     */
    public function save_timezone() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $timezone = isset($_POST['timezone']) ? sanitize_text_field($_POST['timezone']) : '';
        if (empty($timezone)) {
            wp_send_json_error(['message' => __('Invalid time zone', self::TEXT_DOMAIN)]);
        }

        $labels = $this->get_timezone_labels();
        if (!isset($labels[$timezone])) {
            wp_send_json_error(['message' => __('Unsupported time zone', self::TEXT_DOMAIN)]);
        }

        $db = Database::get_instance();
        $ok = $db->save_timezone($timezone);
        if ($ok) {
            wp_send_json_success(['message' => __('Time zone saved', self::TEXT_DOMAIN)]);
        }

        wp_send_json_error(['message' => __('Failed to save time zone', self::TEXT_DOMAIN)]);
    }

    /**
     * AJAX: save holiday availability
     */
    public function save_holiday_availability() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $payload = isset($_POST['holidays']) ? wp_unslash($_POST['holidays']) : '';
        if (empty($payload)) {
            wp_send_json_error(['message' => __('Invalid data', self::TEXT_DOMAIN)]);
        }

        $holidays = json_decode(stripslashes($payload), true);
        if (!is_array($holidays)) {
            wp_send_json_error(['message' => __('Invalid format', self::TEXT_DOMAIN)]);
        }

        $user_id = $this->resolve_request_user_id(true);
        $db = Database::get_instance();
        $ok = $db->save_holiday_availability($holidays, $user_id);
        if ($ok) {
            wp_send_json_success(['message' => __('Holiday availability saved', self::TEXT_DOMAIN)]);
        }

        wp_send_json_error(['message' => __('Failed to save holiday availability', self::TEXT_DOMAIN)]);
    }

    /**
     * AJAX: set manual override for a specific date/time
     */
    public function set_manual_override() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (empty($date) || empty($time) || empty($status)) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $user_id = $this->resolve_request_user_id(true);
        $db = Database::get_instance();
        $ok = $db->set_manual_override($date, $time, $status, $user_id);
        if ($ok) {
            wp_send_json_success(['message' => __('Override saved', self::TEXT_DOMAIN)]);
        }

        wp_send_json_error(['message' => __('Failed to save override', self::TEXT_DOMAIN)]);
    }

    /**
     * Get business hours
     *
     * @return array Array of time strings.
     */
    private function get_business_hours() {
        return [
            '06:00', '06:30', '07:00', '07:30',
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
            '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
            '17:00', '17:30',
        ];
    }

    /**
     * Get timezone label for responses.
     *
     * @return string
     */
    private function get_timezone_label() {
        $timezone = $this->get_timezone_string();
        $labels = $this->get_timezone_labels();
        return isset($labels[$timezone]) ? $labels[$timezone] : $timezone;
    }

    /**
     * Get supported timezone labels.
     *
     * @return array
     */
    private function get_timezone_labels() {
        return [
            'America/New_York' => 'Eastern (America/New York)',
            'America/Chicago' => 'Central (America/Chicago)',
            'America/Denver' => 'Mountain (America/Denver)',
            'America/Phoenix' => 'Arizona (America/Phoenix)',
            'America/Los_Angeles' => 'Pacific (America/Los Angeles)',
            'America/Anchorage' => 'Alaska (America/Anchorage)',
            'Pacific/Honolulu' => 'Hawaii (Pacific/Honolulu)',
        ];
    }

    /**
     * Get timezone string.
     *
     * @return string
     */
    private function get_timezone_string() {
        $db = Database::get_instance();
        return $db->get_timezone_string();
    }

    /**
     * Get timezone object or null.
     *
     * @return \DateTimeZone|null
     */
    private function get_timezone_object() {
        try {
            return new \DateTimeZone($this->get_timezone_string());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format a time label for a given date in selected timezone.
     *
     * @param string $date
     * @param string $time
     * @return string
     */
    private function format_time_label($date, $time) {
        $tz = $this->get_timezone_object();
        $time = strlen($time) === 5 ? $time . ':00' : $time;
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $tz ?: new \DateTimeZone('UTC'));
        if (!$dt) {
            return date('g:i A', strtotime($time));
        }
        return $dt->format('g:i A');
    }

    /**
     * Build a set of HH:MM times from an array of slots.
     *
     * @param array $slots
     * @param string $key
     * @return array
     */
    private function build_time_set($slots, $key = 'block_time') {
        $set = [];
        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot[$key])) {
                continue;
            }
            $time = substr($slot[$key], 0, 5);
            $set[$time] = true;
        }
        return $set;
    }

    /**
     * Build a set of booked times expanded by service duration.
     *
     * @param array $appointments
     * @param array $service_duration_map
     * @return array
     */
    private function build_booked_set($appointments, $service_duration_map) {
        $set = [];
        foreach ($appointments as $appt) {
            if (!is_array($appt) || empty($appt['time'])) {
                continue;
            }
            $start = $this->normalize_time_value($appt['time']);
            if ($start === '') {
                continue;
            }
            $duration_seconds = $this->get_appointment_duration_seconds($appt, $service_duration_map);
            $slots_needed = $this->get_slots_needed($duration_seconds);
            $slots_to_block = $duration_seconds > 0 ? $slots_needed : 0;
            for ($i = 0; $i < $slots_to_block; $i++) {
                $slot_time = $i === 0 ? $start : $this->add_minutes($start, 30 * $i);
                if ($slot_time) {
                    $set[$slot_time] = true;
                }
            }
        }
        return $set;
    }

    /**
     * Build a service title => duration seconds map.
     *
     * @return array
     */
    private function get_service_duration_map() {
        $db = Database::get_instance();
        $services = $db->get_services();
        $map = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $title = isset($service['title']) ? (string) $service['title'] : '';
            $title = $this->normalize_service_key($title);
            $duration = isset($service['duration']) ? (string) $service['duration'] : '';
            if ($title !== '' && ctype_digit($duration)) {
                $map[$title] = (int) $duration;
            }
        }
        return $map;
    }

    /**
     * Determine appointment duration from appointment data.
     *
     * @param array $appointment
     * @param array $service_duration_map
     * @return int
     */
    private function get_appointment_duration_seconds($appointment, $service_duration_map) {
        if (isset($appointment['duration_seconds']) && is_numeric($appointment['duration_seconds'])) {
            return (int) $appointment['duration_seconds'];
        }
        if (!empty($appointment['all_data']) && is_array($appointment['all_data'])) {
            if (isset($appointment['all_data']['csa_custom_duration_seconds']) && is_numeric($appointment['all_data']['csa_custom_duration_seconds'])) {
                return (int) $appointment['all_data']['csa_custom_duration_seconds'];
            }
        }
        if (!empty($appointment['service'])) {
            $service_key = $this->normalize_service_key($appointment['service']);
            if ($service_key !== '' && isset($service_duration_map[$service_key])) {
                return (int) $service_duration_map[$service_key];
            }
        }
        if (!empty($appointment['all_data']) && is_array($appointment['all_data'])) {
            if (!empty($appointment['all_data']['csa_service'])) {
                $service_key = $this->normalize_service_key($appointment['all_data']['csa_service']);
                if ($service_key !== '' && isset($service_duration_map[$service_key])) {
                    return (int) $service_duration_map[$service_key];
                }
            }
            foreach ($appointment['all_data'] as $key => $val) {
                if (!is_string($val)) {
                    continue;
                }
                if (stripos((string) $key, 'service') !== false) {
                    $service_key = $this->normalize_service_key($val);
                    if ($service_key !== '' && isset($service_duration_map[$service_key])) {
                        return (int) $service_duration_map[$service_key];
                    }
                }
                $service_title = $this->extract_service_from_value($val);
                if ($service_title !== '') {
                    $service_key = $this->normalize_service_key($service_title);
                    if ($service_key !== '' && isset($service_duration_map[$service_key])) {
                        return (int) $service_duration_map[$service_key];
                    }
                }
            }
        }
        return 0;
    }

    /**
     * Normalize a service title for lookup.
     *
     * @param string $value
     * @return string
     */
    private function normalize_service_title($value) {
        if (!is_string($value)) {
            return '';
        }
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }
        if (stripos($clean, 'csa::service') === 0 && strpos($clean, '-->') !== false) {
            $parts = explode('-->', $clean, 2);
            if (isset($parts[1])) {
                $clean = trim($parts[1]);
            }
        }
        if (strpos($clean, '-->') !== false) {
            $parts = explode('-->', $clean, 2);
            if (isset($parts[1])) {
                $clean = trim($parts[1]);
            }
        }
        return $clean;
    }

    /**
     * Normalize a service title for matching against the duration map.
     *
     * @param string $value
     * @return string
     */
    private function normalize_service_key($value) {
        $clean = $this->normalize_service_title($value);
        if ($clean === '') {
            return '';
        }
        $clean = strtolower($clean);
        $clean = preg_replace('/\\s+/', ' ', $clean);
        return trim($clean);
    }

    /**
     * Extract a service title from a stored value when present.
     *
     * @param string $value
     * @return string
     */
    private function extract_service_from_value($value) {
        if (!is_string($value)) {
            return '';
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (stripos($trimmed, 'csa::service') === 0 && strpos($trimmed, '-->') !== false) {
            $parts = explode('-->', $trimmed, 2);
            if (isset($parts[1])) {
                return trim($parts[1]);
            }
        }
        return '';
    }

    /**
     * Get number of 30-minute slots needed for a duration.
     *
     * @param int $duration_seconds
     * @return int
     */
    private function get_slots_needed($duration_seconds) {
        $duration_seconds = max(0, (int) $duration_seconds);
        if ($duration_seconds <= 0) {
            return 1;
        }
        return (int) ceil($duration_seconds / 1800);
    }

    /**
     * Add minutes to a HH:MM time.
     *
     * @param string $time
     * @param int $minutes
     * @return string|null
     */
    private function add_minutes($time, $minutes) {
        $dt = \DateTime::createFromFormat('H:i', $time);
        if (!$dt) {
            return null;
        }
        $dt->modify('+' . intval($minutes) . ' minutes');
        return $dt->format('H:i');
    }

    /**
     * Normalize a time string to H:i.
     *
     * @param string $time
     * @return string
     */
    private function normalize_time_value($time) {
        if (!is_string($time)) {
            return '';
        }
        $trimmed = trim($time);
        if ($trimmed === '') {
            return '';
        }
        if (strlen($trimmed) >= 5 && preg_match('/^\\d{1,2}:\\d{2}/', $trimmed)) {
            return substr($trimmed, 0, 5);
        }
        $ts = strtotime($trimmed);
        if ($ts === false) {
            return '';
        }
        return date('H:i', $ts);
    }

    /**
     * Check if a time range is available for duration.
     *
     * @param string $date
     * @param string $start_time
     * @param int $slots_needed
     * @param array $default_hours
     * @param array $overrides
     * @param bool $holiday_closed
     * @param array $blocked_set
     * @param array $booked_set
     * @param array $hours_set
     * @return bool
     */
    private function is_time_range_available($date, $start_time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set) {
        if ($holiday_closed) {
            return false;
        }

        for ($i = 0; $i < $slots_needed; $i++) {
            $slot_time = $i === 0 ? $start_time : $this->add_minutes($start_time, 30 * $i);
            if (!$slot_time || !isset($hours_set[$slot_time])) {
                return false;
            }

            $is_default_available = in_array($slot_time, $default_hours, true);
            if (isset($overrides[$slot_time])) {
                if ($overrides[$slot_time] === 'allow') {
                    $is_default_available = true;
                } elseif ($overrides[$slot_time] === 'block') {
                    $is_default_available = false;
                }
            }

            if (!$is_default_available) {
                return false;
            }

            if (isset($blocked_set[$slot_time]) || isset($booked_set[$slot_time])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a time range is open based on hours, booked, and blocked sets.
     *
     * @param string $start_time
     * @param int $slots_needed
     * @param array $blocked_set
     * @param array $booked_set
     * @param array $hours_set
     * @return bool
     */
    private function is_time_range_open($start_time, $slots_needed, $blocked_set, $booked_set, $hours_set) {
        for ($i = 0; $i < $slots_needed; $i++) {
            $slot_time = $i === 0 ? $start_time : $this->add_minutes($start_time, 30 * $i);
            if (!$slot_time || !isset($hours_set[$slot_time])) {
                return false;
            }
            if (isset($blocked_set[$slot_time]) || isset($booked_set[$slot_time])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve user id for requests (admin can override with user_id).
     *
     * @param bool $allow_admin_override
     * @return int|null
     */
    private function resolve_request_user_id($allow_admin_override = false) {
        if (!empty($_POST['user'])) {
            $user_id = Access::resolve_enabled_user_id(sanitize_text_field($_POST['user']));
            return $user_id ?: null;
        }
        if (!empty($_POST['username'])) {
            $user_id = Access::resolve_enabled_user_id(sanitize_text_field($_POST['username']));
            return $user_id ?: null;
        }
        if ($allow_admin_override && current_user_can(self::CAPABILITY) && !empty($_POST['user_id'])) {
            $candidate = intval($_POST['user_id']);
            return $candidate > 0 ? $candidate : null;
        }
        if (is_user_logged_in()) {
            $current = wp_get_current_user();
            return $current ? intval($current->ID) : null;
        }
        return null;
    }
}
