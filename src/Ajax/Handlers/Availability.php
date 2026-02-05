<?php
/**
 * Availability-related AJAX handlers.
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax\Handlers;

use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Holidays;
use CalendarServiceAppointmentsForm\Core\Submissions;

if (!defined('ABSPATH')) {
    exit;
}

class Availability extends BaseHandler {

    /**
     * Get day details AJAX handler
     *
     * @return void
     */
    public function get_day_details() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        $prev_handler = set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
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
                if ($apt_time === $time) {
                    $matching = $apt;
                    break;
                }
            }

            $appointment_blocks = 0;
            $appointment_label = '';
            $appointment_id = '';
            $appointment_user = '';
            $appointment_submission_id = '';
            $appointment_display_time = '';
            $appointment_data = [];

            if ($matching) {
                $appointment_blocks = $this->get_slots_needed($this->get_appointment_duration_seconds($matching, $service_duration_map));
                $appointment_data = $matching;
                if (!empty($matching['label'])) {
                    $appointment_label = $matching['label'];
                } elseif (!empty($matching['name'])) {
                    $appointment_label = $matching['name'];
                }
                if (!empty($matching['appt_id'])) {
                    $appointment_id = $matching['appt_id'];
                }
                if (!empty($matching['user'])) {
                    $appointment_user = $matching['user'];
                }
                if (!empty($matching['submission_id'])) {
                    $appointment_submission_id = $matching['submission_id'];
                }
                if (!empty($matching['time'])) {
                    $appointment_display_time = $this->format_time_label($date, $matching['time']);
                }
            }

            $is_booked = $matching ? true : false;
            $is_available = $is_default_available && !$is_blocked_explicit && !$is_booked;

            $occupied_times[$time] = $is_booked || $is_blocked_explicit;

            $time_slots[] = [
                'time' => $time,
                'label' => $this->format_time_label($date, $time),
                'available' => $is_available,
                'is_default_available' => $is_default_available,
                'is_blocked_explicit' => $is_blocked_explicit,
                'is_blocked' => $is_blocked_explicit,
                'blocked' => $is_blocked_explicit,
                'is_booked' => $is_booked,
                'booked' => $is_booked,
                'appointment_blocks' => $appointment_blocks,
                'appointment_id' => $appointment_id,
                'appointment_user' => $appointment_user,
                'appointment_submission_id' => $appointment_submission_id,
                'appointment_label' => $appointment_label,
                'appointment_display_time' => $appointment_display_time,
                'appointment_data' => $appointment_data,
                'appointments' => $matching ? $appointment_data : null,
            ];
        }

        return [
            'date' => $date,
            'timezone_label' => $this->get_timezone_label(),
            'time_slots' => $time_slots,
        ];
    }

    /**
     * AJAX: get available times for a date/service/user.
     *
     * @return void
     */
    public function get_available_times() {
        if (!$this->verify_optional_nonce()) {
            wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $user_id = $this->resolve_request_user_id();
        $username = isset($_POST['user']) ? sanitize_text_field($_POST['user']) : '';

        if (empty($date) || (!$duration_seconds && empty($service))) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $is_anyone = Access::is_anyone_username($username);
        if (!$is_anyone && !$user_id) {
            wp_send_json_error(['message' => __('Invalid user', self::TEXT_DOMAIN)]);
        }

        $service_duration_map = $this->get_service_duration_map();
        if ($duration_seconds <= 0) {
            $service_key = $this->normalize_service_key($service);
            if ($service_key && isset($service_duration_map[$service_key])) {
                $duration_seconds = (int) $service_duration_map[$service_key];
            }
        }
        if ($duration_seconds <= 0) {
            wp_send_json_error(['message' => __('Unable to determine service duration for this appointment.', self::TEXT_DOMAIN)]);
        }

        if ($is_anyone) {
            $allowed_user_ids = $this->get_allowed_user_ids_for_service($service);
            $times = $this->build_available_times_anyone($date, $duration_seconds, $allowed_user_ids);
        } else {
            if ($user_id && $service !== '' && !Access::user_can_perform_service($user_id, $service)) {
                wp_send_json_success(['times' => []]);
            }
            $times = $this->build_available_times($date, $duration_seconds, $user_id);
        }

        wp_send_json_success(['times' => $times]);
    }

    /**
     * Build available times for a date.
     *
     * @param string $date
     * @param int $duration_seconds
     * @param int|null $user_id
     * @return array
     */
    public function build_available_times($date, $duration_seconds, $user_id = null) {
        $date = is_string($date) ? trim($date) : '';
        if ($date === '') {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }

        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');
        if ($date < $today) {
            return [];
        }

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

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

        $results = [];
        foreach ($hours as $time) {
            if ($this->is_time_range_available($date, $time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set)) {
                $label = $this->format_time_label($date, $time);
                $results[] = [
                    'time' => $time,
                    'value' => $time,
                    'label' => $label,
                ];
            }
        }

        return $results;
    }

    /**
     * AJAX: get available months for a user.
     *
     * @return void
     */
    public function get_available_months() {
        if (!$this->verify_optional_nonce()) {
            wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
        }
        $username = isset($_POST['user']) ? sanitize_text_field($_POST['user']) : '';
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';
        $user_id = $this->resolve_request_user_id();
        $is_anyone = Access::is_anyone_username($username);

        if (!$is_anyone && !$user_id) {
            wp_send_json_error(['message' => __('Invalid user', self::TEXT_DOMAIN)]);
        }

        $today = date('Y-m-d');
        $current_month = date('n');
        $current_year = date('Y');
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = (int) date('n', strtotime("+$i months"));
            $year = (int) date('Y', strtotime("+$i months"));
            $date_label = date('F Y', strtotime("$year-$month-01"));

            if ($is_anyone) {
                $allowed_user_ids = $this->get_allowed_user_ids_for_service($service);
                $days = $this->build_available_days_anyone(sprintf('%04d-%02d', $year, $month), 1, null, $allowed_user_ids);
            } else {
                if ($user_id && $service !== '' && !Access::user_can_perform_service($user_id, $service)) {
                    $days = [];
                } else {
                $days = $this->build_available_days(sprintf('%04d-%02d', $year, $month), 1, $user_id);
                }
            }

            if (!empty($days)) {
                $months[] = [
                    'value' => sprintf('%04d-%02d', $year, $month),
                    'label' => $date_label,
                ];
            }
        }

        wp_send_json_success(['months' => $months]);
    }

    /**
     * AJAX: get available days for a month.
     *
     * @return void
     */
    public function get_available_days() {
        if (!$this->verify_optional_nonce()) {
            wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
        }
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
        $slots_needed = isset($_POST['slots_needed']) ? intval($_POST['slots_needed']) : 1;
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $user_id = $this->resolve_request_user_id();
        $username = isset($_POST['user']) ? sanitize_text_field($_POST['user']) : '';
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';
        $is_anyone = Access::is_anyone_username($username);

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            wp_send_json_error(['message' => __('Invalid month', self::TEXT_DOMAIN)]);
        }
        if ($slots_needed < 1) {
            $slots_needed = 1;
        }

        if ($is_anyone) {
            $allowed_user_ids = $this->get_allowed_user_ids_for_service($service);
            $days = $this->build_available_days_anyone($month, $slots_needed, $duration_seconds, $allowed_user_ids);
        } else {
            if (!$user_id) {
                wp_send_json_error(['message' => __('Invalid user', self::TEXT_DOMAIN)]);
            }
            if ($service !== '' && !Access::user_can_perform_service($user_id, $service)) {
                wp_send_json_success(['days' => []]);
            }
            $days = $this->build_available_days($month, $slots_needed, $user_id);
        }

        wp_send_json_success(['days' => $days]);
    }

    /**
     * AJAX: resolve a random available user for "anyone" selection.
     *
     * @return void
     */
    public function resolve_anyone_user() {
        if (!$this->verify_optional_nonce()) {
            wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';

        if (empty($date) || empty($time) || $duration_seconds <= 0) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $allowed_user_ids = $this->get_allowed_user_ids_for_service($service);
        $available = $this->get_available_user_ids_for_slot($date, $time, $duration_seconds, $allowed_user_ids);
        if (empty($available)) {
            wp_send_json_error(['message' => __('No users are available for that time.', self::TEXT_DOMAIN)]);
        }

        $random_id = $available[array_rand($available)];
        $user = $random_id ? get_user_by('id', $random_id) : null;
        if (!$user) {
            wp_send_json_error(['message' => __('No users are available for that time.', self::TEXT_DOMAIN)]);
        }

        $full_name = Access::build_user_display_name($user);

        wp_send_json_success([
            'user_id' => $random_id,
            'username' => $user->user_login,
            'full_name' => $full_name,
        ]);
    }

    /**
     * AJAX: filter available times for "anyone" selection.
     *
     * @return void
     */
    public function filter_anyone_times() {
        if (!$this->verify_optional_nonce()) {
            wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $times = isset($_POST['times']) ? $_POST['times'] : [];
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';

        if (empty($date) || $duration_seconds <= 0) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $times = $this->normalize_times_array($times);
        if (empty($times)) {
            wp_send_json_success(['times' => []]);
        }

        $resolved = $this->resolve_anyone_times_for_date($date, $duration_seconds, $times, $service);
        $filtered = [];
        foreach ($times as $time) {
            if (isset($resolved[$time])) {
                $filtered[] = $time;
            }
        }
        if (empty($filtered)) {
            // Fall back to original times so the UI can still attempt resolution on selection.
            $filtered = $times;
        }

        wp_send_json_success(['times' => $filtered]);
    }

    /**
     * AJAX: resolve available "anyone" times to a concrete user.
     *
     * @return void
     */
    public function resolve_anyone_times() {
        if (!$this->verify_optional_nonce()) {
            wp_send_json_error(['message' => __('Invalid request.', self::TEXT_DOMAIN)]);
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $times = isset($_POST['times']) ? $_POST['times'] : [];
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';

        if (empty($date) || $duration_seconds <= 0) {
            wp_send_json_error(['message' => __('Invalid parameters', self::TEXT_DOMAIN)]);
        }

        $times = $this->normalize_times_array($times);
        if (empty($times)) {
            wp_send_json_success(['times' => []]);
        }

        $resolved = $this->resolve_anyone_times_for_date($date, $duration_seconds, $times, $service);
        $out = [];
        foreach ($times as $time) {
            $label = $this->format_time_label($date, $time);
            $entry = [
                'time' => $time,
                'value' => $time,
                'label' => $label,
            ];
            if (isset($resolved[$time])) {
                $entry['user_id'] = $resolved[$time]['user_id'];
                $entry['username'] = $resolved[$time]['username'];
                $entry['full_name'] = $resolved[$time]['full_name'];
            }
            $out[] = $entry;
        }

        wp_send_json_success(['times' => $out]);
    }

    /**
     * Resolve available "anyone" times to a concrete user for a date.
     *
     * @param string $date
     * @param int $duration_seconds
     * @param array $times
     * @return array
     */
    private function resolve_anyone_times_for_date($date, $duration_seconds, $times, $service = '', $user_ids = null) {
        $resolved = [];
        $allowed_user_ids = $user_ids !== null ? $user_ids : $this->get_allowed_user_ids_for_service($service);
        foreach ($times as $time) {
            if (is_array($time)) {
                $time = $time['time'] ?? ($time['value'] ?? '');
            }
            $time = is_string($time) ? trim($time) : '';
            if ($time === '') {
                continue;
            }
            $time = $this->normalize_time_value($time);
            if ($time === '') {
                continue;
            }
            $available = $this->get_available_user_ids_for_slot($date, $time, $duration_seconds, $allowed_user_ids);
            if (empty($available)) {
                continue;
            }
            $random_id = $available[array_rand($available)];
            $user = $random_id ? get_user_by('id', $random_id) : null;
            if (!$user) {
                continue;
            }
            $full_name = Access::build_user_display_name($user);
            $resolved[$time] = [
                'user_id' => $random_id,
                'username' => $user->user_login,
                'full_name' => $full_name,
            ];
        }
        return $resolved;
    }

    /**
     * Normalize incoming times array from POST (supports JSON or array payloads).
     *
     * @param mixed $times
     * @return array
     */
    private function normalize_times_array($times) {
        if (is_string($times)) {
            $raw = wp_unslash($times);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) && is_string($raw)) {
                $decoded = json_decode(stripslashes($raw), true);
            }
            if (is_array($decoded)) {
                $times = $decoded;
            }
        }
        if (!is_array($times)) {
            return [];
        }
        $normalized = [];
        foreach ($times as $time) {
            if (is_array($time)) {
                $time = $time['time'] ?? ($time['value'] ?? '');
            }
            $time = is_string($time) ? trim($time) : '';
            if ($time === '') {
                continue;
            }
            $time = $this->normalize_time_value($time);
            if ($time === '') {
                continue;
            }
            $normalized[] = sanitize_text_field($time);
        }
        return array_values(array_unique($normalized));
    }

    /**
     * Build available times for "anyone" selection.
     *
     * @param string $date
     * @param int $duration_seconds
     * @return array
     */
    public function build_available_times_anyone($date, $duration_seconds, $user_ids = null) {
        if ($user_ids === null) {
            $user_ids = $this->get_selectable_user_ids();
        }
        if (empty($user_ids)) {
            return [];
        }
        $date = is_string($date) ? trim($date) : '';
        if ($date === '') {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }

        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');
        if ($date < $today) {
            return [];
        }

        $duration_seconds = max(0, (int) $duration_seconds);
        $slots_needed = $this->get_slots_needed($duration_seconds);
        $times = [];
        foreach ($this->get_business_hours() as $time) {
            if ($this->is_any_user_available_for_time($date, $time, $duration_seconds, $user_ids)) {
                $label = $this->format_time_label($date, $time);
                $times[] = [
                    'time' => $time,
                    'value' => $time,
                    'label' => $label,
                ];
            }
        }

        return $times;
    }

    /**
     * Verify nonce when provided, allow missing for public endpoints.
     *
     * @return bool
     */
    private function verify_optional_nonce() {
        if (empty($_REQUEST['nonce'])) {
            return true;
        }
        return (bool) check_ajax_referer(self::NONCE_ACTION, 'nonce', false);
    }

    /**
     * Build available days for "anyone" selection.
     *
     * @param string $month
     * @param int $slots_needed
     * @return array
     */
    public function build_available_days_anyone($month, $slots_needed, $duration_seconds = null, $user_ids = null) {
        if ($user_ids === null) {
            $user_ids = $this->get_selectable_user_ids();
        }
        if (empty($user_ids)) {
            return [];
        }
        list($year, $mon) = array_map('intval', explode('-', $month));

        $tz = $this->get_timezone_object();
        $now = $tz ? new \DateTime('now', $tz) : new \DateTime();
        $today = $now ? $now->format('Y-m-d') : date('Y-m-d');

        $dt = \DateTime::createFromFormat('!Y-n', "$year-$mon");
        $days_in_month = (int)$dt->format('t');
        if ($duration_seconds === null) {
            $duration_seconds = max(0, (int) $slots_needed) * 1800;
        } else {
            $duration_seconds = max(0, (int) $duration_seconds);
        }

        $days = [];

        for ($d = 1; $d <= $days_in_month; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $mon, $d);
            if ($dateStr < $today) {
                continue;
            }

            $times = $this->build_available_times_anyone($dateStr, $duration_seconds, $user_ids);
            if (empty($times)) {
                continue;
            }
            $resolved = $this->resolve_anyone_times_for_date($dateStr, $duration_seconds, $times, '', $user_ids);
            if (empty($resolved)) {
                continue;
            }
            $days[] = [
                'value' => sprintf('%02d', $d),
                'label' => date('F j, Y', strtotime($dateStr)),
            ];
        }

        return $days;
    }

    /**
     * Get selectable user IDs (enabled users).
     *
     * @return array
     */
    private function get_selectable_user_ids() {
        $enabled_ids = Access::get_bookable_user_ids();
        return array_values(array_unique(array_filter(array_map('intval', $enabled_ids))));
    }

    /**
     * Get selectable user IDs that are available for a slot.
     *
     * @param string $date
     * @param string $time
     * @param int $duration_seconds
     * @return array
     */
    private function get_available_user_ids_for_slot($date, $time, $duration_seconds, $user_ids = null) {
        $available = [];
        if ($user_ids === null) {
            $user_ids = $this->get_selectable_user_ids();
        }
        foreach ($user_ids as $user_id) {
            if ($this->check_time_range_available($date, $time, $duration_seconds, $user_id)) {
                $available[] = $user_id;
            }
        }
        return $available;
    }

    /**
     * Check if any selectable user can take a time range.
     *
     * @param string $date
     * @param string $time
     * @param int $duration_seconds
     * @param array $user_ids
     * @return bool
     */
    private function is_any_user_available_for_time($date, $time, $duration_seconds, $user_ids) {
        if ($user_ids === null) {
            $user_ids = $this->get_selectable_user_ids();
        }
        foreach ($user_ids as $user_id) {
            if ($this->check_time_range_available($date, $time, $duration_seconds, $user_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get allowed user IDs for a service value.
     *
     * @param string $service
     * @return array
     */
    private function get_allowed_user_ids_for_service($service) {
        if (!is_string($service) || $service === '') {
            return $this->get_selectable_user_ids();
        }
        return Access::get_user_ids_for_service($service);
    }

    /**
     * Build available days for a month.
     *
     * @param string $month
     * @param int $slots_needed
     * @param int|null $user_id
     * @return array
     */
    public function build_available_days($month, $slots_needed, $user_id = null) {
        list($year, $mon) = array_map('intval', explode('-', $month));

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
            $times = $this->build_available_times($dateStr, max(0, (int) $slots_needed) * 1800, $user_id);
            if (!empty($times)) {
                $days[] = [
                    'value' => sprintf('%02d', $d),
                    'label' => date('F j, Y', strtotime($dateStr)),
                ];
            }
        }

        return $days;
    }

    /**
     * Check if a time range is available for a given user.
     *
     * @param string $date
     * @param string $start_time
     * @param int $duration_seconds
     * @param int|null $user_id
     * @return bool
     */
    public function check_time_range_available($date, $start_time, $duration_seconds, $user_id = null) {
        $date = is_string($date) ? trim($date) : '';
        if ($date === '') {
            return false;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
        $holiday_key = Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

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

        return $this->is_time_range_available($date, $start_time, $slots_needed, $default_hours, $overrides, $holiday_closed, $blocked_set, $booked_set, $hours_set);
    }

}
