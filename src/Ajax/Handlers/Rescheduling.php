<?php
/**
 * Rescheduling-related AJAX handlers.
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax\Handlers;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Holidays;
use CalendarServiceAppointmentsForm\Core\Submissions;

if (!defined('ABSPATH')) {
    exit;
}

class Rescheduling extends BaseHandler {

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

        $appointment_user_id = isset($appt['user_id']) ? intval($appt['user_id']) : $this->resolve_request_user_id(true);

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

        $weekly = $db->get_weekly_availability($appointment_user_id);
        $holiday_availability = $db->get_holiday_availability($appointment_user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $appointment_user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $hours = $this->get_business_hours();
        $hours_set = array_fill_keys($hours, true);
        $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($date, $appointment_user_id));

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

        // If rescheduling within the same date, ignore the appointment's current blocked slots.
        $current_date = isset($appointment['date']) ? (string) $appointment['date'] : '';
        if ($current_date === $date) {
            $current_start = isset($appointment['time']) ? (string) $appointment['time'] : '';
            $current_start = $this->normalize_time_value($current_start);
            if ($current_start !== '') {
                $current_slots = $this->build_slot_times_for_duration($current_start, $duration_seconds);
                foreach ($current_slots as $slot_time) {
                    unset($blocked_set[$slot_time]);
                }
            }
        }

        if (!$this->is_time_range_available($date, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
            wp_send_json_error(['message' => __('That date and time is not available for this appointment.', self::TEXT_DOMAIN)]);
        }

        $updated = $db->reschedule_appointment_by_id($appt_id, $date, $time);
        if ($updated === false) {
            wp_send_json_error(['message' => __('Failed to reschedule appointment', self::TEXT_DOMAIN)]);
        }

        $reschedule_payload = [
            'appt_id' => $appt_id,
            'user_id' => $appointment_user_id,
            'user' => $appointment_user_id ? get_user_by('id', $appointment_user_id) : null,
            'submission_data' => $appointment['all_data'],
            'old_date' => $appointment['date'],
            'old_time' => $appointment['time'],
            'new_date' => $date,
            'new_time' => $time,
        ];
        /**
         * Fires after an appointment is rescheduled from the admin calendar.
         *
         * @param array $reschedule_payload {
         *     @type int $appt_id Appointment row ID.
         *     @type int $user_id User ID the appointment is for.
         *     @type \WP_User|null $user User object for the appointment.
         *     @type array $submission_data Original form submission data.
         *     @type string $old_date Previous appointment date (Y-m-d).
         *     @type string $old_time Previous appointment time (HH:MM).
         *     @type string $new_date Rescheduled appointment date (Y-m-d).
         *     @type string $new_time Rescheduled appointment time (HH:MM).
         * }
         */
        do_action('csa_appointment_reschedule', $reschedule_payload);

        wp_send_json_success(['message' => __('Appointment rescheduled', self::TEXT_DOMAIN)]);
    }
}
