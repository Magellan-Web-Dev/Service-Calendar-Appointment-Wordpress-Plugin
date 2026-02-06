<?php
declare(strict_types=1);
/**
 * Booking-related AJAX handlers.
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax\Handlers;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Submissions;

if (!defined('ABSPATH')) {
    exit;
}

class Booking extends BaseHandler {

    /**
     * Delete appointment AJAX handler
     * Accepts either `appt_id` (appointment-table id) or `submission_id` + `date` + `time`.
     *
     * @return void
     */
    public function delete_appointment(): void {
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
     * AJAX: create a custom appointment booking.
     *
     * @return void
     */
    public function create_custom_appointment(): void {
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
        $hours = $this->get_business_hours();
        $hours_set = array_fill_keys($hours, true);
        $slots_needed = $this->get_slots_needed($duration_seconds);

        // Use a DB-level named lock to reduce race conditions between concurrent submissions.
        global $wpdb;
        $lock_name = 'csa_reserve_custom_' . $date . '_' . str_replace(':', '-', $time) . '_' . $duration_seconds . '_' . $user_id;
        $got_lock = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, %d)", $lock_name, 5));
        if (! $got_lock) {
            wp_send_json_error(['message' => __('Please try again shortly, the system is checking availability.', self::TEXT_DOMAIN)]);
        }

        $appointments = $submissions->get_appointments_for_date($date, $user_id);
        if (!is_array($appointments)) {
            $appointments = [];
        }
        $service_duration_map = $this->get_service_duration_map();
        $booked_set = $this->build_booked_set($appointments, $service_duration_map);
        $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($date, $user_id));

        if (!$this->is_time_range_open($time, $slots_needed, $blocked_set, $booked_set, $hours_set)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            wp_send_json_error(['message' => __('That time range is not available for a custom appointment.', self::TEXT_DOMAIN)]);
        }

        $slots = $this->build_slot_times_for_duration($time, $duration_seconds);
        if (empty($slots)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            wp_send_json_error(['message' => __('That time range is not available for a custom appointment.', self::TEXT_DOMAIN)]);
        }

        $reserved = [];
        foreach ($slots as $slot_time) {
            $ok = $db->reserve_time_slot($date, $slot_time, $user_id);
            if (!$ok) {
                foreach ($reserved as $t) {
                    $db->unblock_time_slot($date, $t, $user_id);
                }
                $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
                wp_send_json_error(['message' => __('That time range is not available for a custom appointment.', self::TEXT_DOMAIN)]);
            }
            $reserved[] = $slot_time;
        }

        $submission_data = [
            'csa_custom_appointment' => true,
            'csa_custom_title' => $title,
            'csa_custom_duration_seconds' => $duration_seconds,
            'csa_custom_notes' => $notes,
        ];

        $insert_id = $db->insert_appointment(null, $date, $time, $submission_data, $user_id);
        if (!$insert_id) {
            foreach ($reserved as $t) {
                $db->unblock_time_slot($date, $t, $user_id);
            }
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            wp_send_json_error(['message' => __('Failed to schedule custom appointment', self::TEXT_DOMAIN)]);
        }

        $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));

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
    public function fetch_submission_values(): void {
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
    public function block_time_slot(): void {
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
    public function unblock_time_slot(): void {
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
     * AJAX: get weekly availability
     */
    public function get_weekly_availability(): void {
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
    public function save_weekly_availability(): void {
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
    public function save_timezone(): void {
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
    public function save_holiday_availability(): void {
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
    public function set_manual_override(): void {
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
}
