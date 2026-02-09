<?php
declare(strict_types=1);
/**
 * Shared appointment helper methods for AJAX handlers.
 *
 * @package CalendarServiceAppointmentsForm\Ajax\Concerns
 */

namespace CalendarServiceAppointmentsForm\Ajax\Concerns;

use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

trait AppointmentHelpers {

    /**
     * Get business hours
     *
     * @return array Array of time strings.
     */
    protected function get_business_hours(): array {
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
    protected function get_timezone_label(): string {
        $timezone = $this->get_timezone_string();
        $labels = $this->get_timezone_labels();
        return isset($labels[$timezone]) ? $labels[$timezone] : $timezone;
    }

    /**
     * Get supported timezone labels.
     *
     * @return array
     */
    protected function get_timezone_labels(): array {
        return [
            'America/New_York' => 'Eastern',
            'America/Chicago' => 'Central',
            'America/Denver' => 'Mountain',
            'America/Phoenix' => 'Arizona',
            'America/Los_Angeles' => 'Pacific',
            'America/Anchorage' => 'Alaska',
            'Pacific/Honolulu' => 'Hawaii',
        ];
    }

    /**
     * Get timezone string.
     *
     * @return string
     */
    protected function get_timezone_string(): string {
        $db = Database::get_instance();
        return $db->get_timezone_string();
    }

    /**
     * Get timezone object or null.
     *
     * @return \DateTimeZone|null
     */
    protected function get_timezone_object(): ?\DateTimeZone {
        try {
            return new \DateTimeZone($this->get_timezone_string());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check whether a date/time is at least N hours from now in the configured timezone.
     *
     * @param string $date
     * @param string $time
     * @param int $min_hours
     * @return bool
     */
    protected function is_time_after_min_lead(string $date, string $time, int $min_hours = 3): bool {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '' || $min_hours <= 0) {
            return false;
        }
        if (strlen($time) === 5) {
            $time .= ':00';
        }
        $tz = $this->get_timezone_object() ?: new \DateTimeZone('UTC');
        $slot = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $tz);
        if (!$slot) {
            return false;
        }
        $now = new \DateTimeImmutable('now', $tz);
        $min = $now->modify('+' . $min_hours . ' hours');
        if (!$min) {
            return false;
        }
        return $slot >= $min;
    }

    /**
     * Format a time label for a given date in selected timezone.
     *
     * @param string $date
     * @param string $time
     * @return string
     */
    protected function format_time_label(string $date, string $time): string {
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
    protected function build_time_set(array $slots, string $key = 'block_time'): array {
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
    protected function build_booked_set(array $appointments, array $service_duration_map): array {
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
    protected function get_service_duration_map(): array {
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
    protected function get_appointment_duration_seconds(array $appointment, array $service_duration_map): int {
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
    protected function normalize_service_title(mixed $value): string {
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
    protected function normalize_service_key(mixed $value): string {
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
    protected function extract_service_from_value(mixed $value): string {
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
    protected function get_slots_needed(int $duration_seconds): int {
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
    protected function add_minutes(string $time, int $minutes): ?string {
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
    protected function normalize_time_value(mixed $time): string {
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
     * Build slot times for a given duration and start time.
     *
     * @param string $start_time
     * @param int $duration_seconds
     * @return array
     */
    public function build_slot_times_for_duration(string $start_time, int $duration_seconds): array {
        $start_time = $this->normalize_time_value($start_time);
        if ($start_time === '') {
            return [];
        }
        $slots_needed = $this->get_slots_needed($duration_seconds);
        if ($slots_needed < 1) {
            return [];
        }
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
    protected function is_time_range_available(string $date, string $start_time, int $slots_needed, array $default_hours, array $overrides, bool $holiday_closed, array $blocked_set, array $booked_set, array $hours_set): bool {
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
    protected function is_time_range_open(string $start_time, int $slots_needed, array $blocked_set, array $booked_set, array $hours_set): bool {
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
    protected function resolve_request_user_id(bool $allow_admin_override = false): ?int {
        if (!empty($_POST['user'])) {
            $user_id = Access::resolve_enabled_user_id(sanitize_text_field($_POST['user']));
            return $user_id ?: null;
        }
        if (!empty($_POST['username'])) {
            $user_id = Access::resolve_enabled_user_id(sanitize_text_field($_POST['username']));
            return $user_id ?: null;
        }
        if ($allow_admin_override && method_exists($this, 'get_capability') && current_user_can($this->get_capability()) && !empty($_POST['user_id'])) {
            $candidate = intval($_POST['user_id']);
            return $candidate > 0 ? $candidate : null;
        }
        if (is_user_logged_in()) {
            $current = wp_get_current_user();
            return $current ? intval($current->ID) : null;
        }
        return null;
    }

    /**
     * Release blocked slots created by a custom appointment reservation.
     *
     * @param array $appointment
     * @param int|null $fallback_user_id
     * @return void
     */
    protected function release_custom_reservations(array $appointment, ?int $fallback_user_id = null): void {
        $all_data = null;
        if (isset($appointment['submission_data']) && is_array($appointment['submission_data'])) {
            $all_data = $appointment['submission_data'];
        } elseif (isset($appointment['all_data']) && is_array($appointment['all_data'])) {
            $all_data = $appointment['all_data'];
        }
        if (!$all_data || empty($all_data['csa_custom_appointment'])) {
            return;
        }

        $date = (string) ($appointment['appointment_date'] ?? $appointment['date'] ?? '');
        $time = (string) ($appointment['appointment_time'] ?? $appointment['time'] ?? '');
        if ($date === '' || $time === '') {
            return;
        }

        $service_duration_map = $this->get_service_duration_map();
        $appt = [
            'time' => $time,
            'date' => $date,
            'all_data' => $all_data,
        ];
        if (!empty($all_data['csa_service'])) {
            $appt['service'] = $all_data['csa_service'];
        }
        $duration_seconds = $this->get_appointment_duration_seconds($appt, $service_duration_map);
        if ($duration_seconds <= 0) {
            return;
        }

        $slots = $this->build_slot_times_for_duration($time, $duration_seconds);
        if (empty($slots)) {
            return;
        }

        $user_id = isset($appointment['user_id']) ? intval($appointment['user_id']) : $fallback_user_id;
        if (!$user_id) {
            $user_id = $this->resolve_request_user_id(true);
        }
        $db = Database::get_instance();
        foreach ($slots as $slot) {
            $db->unblock_time_slot($date, $slot, $user_id);
        }
    }
}
