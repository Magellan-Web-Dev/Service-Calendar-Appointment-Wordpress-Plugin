<?php
/**
 * Handlers class
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Holidays;
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
    }

    /**
     * Get day details AJAX handler
     *
     * @return void
     */
    public function get_day_details() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (empty($date)) {
            wp_send_json_error(['message' => __('Invalid date', self::TEXT_DOMAIN)]);
        }

        $db = Database::get_instance();
        $blocked_slots = $db->get_blocked_slots_for_date($date);

        $submissions = Submissions::get_instance();
        $appointments = $submissions->get_appointments_for_date($date);

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability();
        $holiday_availability = $db->get_holiday_availability();
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $time_slots = [];
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
                if (substr($slot['block_time'], 0, 5) == $time) {
                    $is_blocked_explicit = true;
                    break;
                }
            }

            // collect all appointments that match this time (may be multiple)
            $matching = [];
            foreach ($appointments as $apt) {
                if (substr($apt['time'], 0, 5) == $time) {
                    $matching[] = $apt;
                }
            }

            $time_slots[] = [
                'time' => $time,
                'is_default_available' => $is_default_available,
                'is_blocked_explicit' => $is_blocked_explicit,
                // plural appointments array; keep single 'appointment' for backward compatibility
                'appointments' => $matching,
                'appointment' => count($matching) === 1 ? $matching[0] : null,
            ];
        }

        // Enrich appointments with full submission fields when possible
        if (!empty($time_slots)) {
            foreach ($time_slots as &$slot) {
                if (empty($slot['appointments'])) continue;

                foreach ($slot['appointments'] as &$appt) {
                    if (empty($appt['id'])) continue;

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
                        $appt = $full;
                    }
                }
                unset($appt);
            }
            unset($slot);
        }

        wp_send_json_success([
            'date' => $date,
            'timezone_label' => $this->get_timezone_label(),
            'time_slots' => $time_slots,
        ]);
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

        $db = Database::get_instance();
        $result = $db->block_time_slot($date, $time . ':00');

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

        $db = Database::get_instance();
        $result = $db->unblock_time_slot($date, $time . ':00');

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

        if (empty($date)) {
            wp_send_json_error(['message' => __('Invalid date', self::TEXT_DOMAIN)]);
        }

        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');
        if ($date < $today) {
            wp_send_json_success(['times' => []]);
        }

        $db = Database::get_instance();
        $blocked_slots = $db->get_blocked_slots_for_date($date);
        $submissions = Submissions::get_instance();
        $appointments = $submissions->get_appointments_for_date($date);

        $weekly = $db->get_weekly_availability();
        $holiday_availability = $db->get_holiday_availability();
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        $available_times = [];
        $hours = $this->get_business_hours();
        $hours_set = array_fill_keys($hours, true);
        $blocked_set = $this->build_time_set($blocked_slots);
        $booked_set = $this->build_time_set($appointments, 'time');
        $slots_needed = $this->get_slots_needed($duration_seconds);
        foreach ($hours as $time) {
            if (!$this->is_time_range_available($date, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
                continue;
            }

            if ($date === $today && $now) {
                $slot_dt = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $now->getTimezone());
                if ($slot_dt && $slot_dt <= $now) {
                    continue;
                }
            }
            $label = $this->format_time_label($date, $time);
            $available_times[] = [
                'value' => $time,
                'label' => $label,
            ];
        }

        wp_send_json_success(['times' => $available_times]);
    }

    /**
     * AJAX: get available months (next 12 months that have at least one available day)
     */
    public function get_available_months() {
        $months = [];
        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $slots_needed = $this->get_slots_needed($duration_seconds);

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

            $weekly = $db->get_weekly_availability();
            $holiday_availability = $db->get_holiday_availability();

            for ($d = 1; $d <= $days_in_month; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                if ($dateStr < $today) {
                    continue;
                }
                $dow = (int)date('w', strtotime($dateStr));
                $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
                $overrides = $db->get_overrides_for_date($dateStr);
                $holiday_key = Holidays::get_us_holiday_key_for_date($dateStr);
                $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
                $holiday_closed = $holiday_key && !$holiday_enabled;

                $hours = $this->get_business_hours();
                $hours_set = array_fill_keys($hours, true);
                $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($dateStr));
                $booked_set = $this->build_time_set($submissions->get_appointments_for_date($dateStr), 'time');
                foreach ($hours as $time) {
                    if ($dateStr === $today && $now) {
                        $slot_dt = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $time, $now->getTimezone());
                        if ($slot_dt && $slot_dt <= $now) {
                            continue;
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
        $slots_needed = $this->get_slots_needed($duration_seconds);
        if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            wp_send_json_error(['message' => __('Invalid month', self::TEXT_DOMAIN)]);
        }

        list($year, $mon) = array_map('intval', explode('-', $month));
        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        $weekly = $db->get_weekly_availability();
        $holiday_availability = $db->get_holiday_availability();

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
            $overrides = $db->get_overrides_for_date($dateStr);
            $holiday_key = Holidays::get_us_holiday_key_for_date($dateStr);
            $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
            $holiday_closed = $holiday_key && !$holiday_enabled;

            $hours = $this->get_business_hours();
            $hours_set = array_fill_keys($hours, true);
            $blocked_set = $this->build_time_set($db->get_blocked_slots_for_date($dateStr));
            $booked_set = $this->build_time_set($submissions->get_appointments_for_date($dateStr), 'time');
            foreach ($hours as $time) {
                if ($dateStr === $today && $now) {
                    $slot_dt = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $time, $now->getTimezone());
                    if ($slot_dt && $slot_dt <= $now) {
                        continue;
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

        wp_send_json_success(['days' => $days]);
    }

    /**
     * AJAX: get weekly availability
     */
    public function get_weekly_availability() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', self::TEXT_DOMAIN)]);
        }

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability();
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

        $db = Database::get_instance();
        $ok = $db->save_weekly_availability($weekly);
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

        $db = Database::get_instance();
        $ok = $db->save_holiday_availability($holidays);
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

        $db = Database::get_instance();
        $ok = $db->set_manual_override($date, $time, $status);
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
}
