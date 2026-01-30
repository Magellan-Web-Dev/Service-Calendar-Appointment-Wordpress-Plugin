<?php
/**
 * Submissions class
 *
 * @package CalendarServiceAppointmentsForm\Core
 */

namespace CalendarServiceAppointmentsForm\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Elementor submissions and appointment parsing
 */
class Submissions {

    /**
     * Elementor submissions table suffix
     *
     * @var string
     */
    public const TABLE_SUBMISSIONS = 'e_submissions';

    /**
     * Elementor submission values table suffix
     *
     * @var string
     */
    public const TABLE_SUBMISSION_VALUES = 'e_submission_values';

    /**
     * Submission field key for appointment date
     *
     * @var string
     */
    public const FIELD_APPOINTMENT_DATE = 'appointment_date';

    /**
     * Submission field key for appointment time
     *
     * @var string
     */
    public const FIELD_APPOINTMENT_TIME = 'appointment_time';

    /**
     * Singleton instance
     *
     * @var Submissions|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Submissions
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get appointments for a specific date (Y-m-d)
     * Returns array of appointment items with keys: id, appt_id (plugin row id), date, time, name, email, phone, status, created_at, all_data
     *
     * @param string $date
     * @return array
     */
    public function get_appointments_for_date($date) {
        global $wpdb;

        $db = Database::get_instance();

        // Prefer plugin appointments table when present
        $rows = $db->get_appointments_for_date_from_table($date);
        if (!empty($rows)) {
            $out = array();
            foreach ($rows as $r) {
                $submission_id = isset($r['submission_id']) && $r['submission_id'] ? intval($r['submission_id']) : null;
                $appt = array(
                    'id' => $submission_id,
                    'appt_id' => isset($r['id']) ? intval($r['id']) : null,
                    'date' => $r['appointment_date'],
                    'time' => $r['appointment_time'],
                    'status' => isset($r['status']) ? $r['status'] : 'booked',
                    'created_at' => isset($r['created_at']) ? $r['created_at'] : null,
                    'all_data' => isset($r['submission_data']) ? $r['submission_data'] : array(),
                );

                if ($submission_id) {
                    $full = $this->get_appointment_by_submission_id($submission_id);
                    if ($full) {
                        // prefer helper all_data/created_at when available
                        if (!empty($full['created_at'])) { $appt['created_at'] = $full['created_at']; }
                        if (!empty($full['all_data'])) { $appt['all_data'] = $full['all_data']; }
                    }
                }

                $out[] = $appt;
            }
            return $out;
        }

        // Fallback: query Elementor submission values for this date
        $values_table = $wpdb->prefix . self::TABLE_SUBMISSION_VALUES;

        // If Elementor submission values table doesn't exist, bail early
        $exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($values_table) . "'");
        if (!$exists) {
            return array();
        }

        $submission_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sv.submission_id
            FROM {$values_table} sv
            WHERE sv.key = %s
            AND sv.value = %s",
            self::FIELD_APPOINTMENT_DATE,
            $date
        ));

        // Also consider composite strings that include the formatted date
        $formatted = date('F j, Y', strtotime($date));
        $composite_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sv.submission_id
            FROM {$values_table} sv
            WHERE sv.value LIKE %s",
            '%' . $wpdb->esc_like($formatted) . '%'
        ));

        $submission_ids = array_unique(array_merge((array) $submission_ids, (array) $composite_ids));
        if (empty($submission_ids)) {
            return array();
        }

        $out = array();
        foreach ($submission_ids as $sid) {
            $appt = $this->get_appointment_by_submission_id($sid);
            if ($appt) {
                $out[] = $appt;
            }
        }

        return $out;
    }

    /**
     * Get appointments for a month (year, month)
     * Returns array of appointments (same shape as get_appointments_for_date)
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    public function get_appointments_for_month($year, $month) {
        global $wpdb;

        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));

        $db = Database::get_instance();

        // Prefer plugin appointments table when rows exist for this month
        $rows = $db->get_appointments_for_month_from_table($year, $month);
        if (!empty($rows)) {
            $out = array();
            foreach ($rows as $r) {
                $submission_id = isset($r['submission_id']) && $r['submission_id'] ? intval($r['submission_id']) : null;
                $appt = array(
                    'id' => $submission_id,
                    'appt_id' => isset($r['id']) ? intval($r['id']) : null,
                    'date' => $r['appointment_date'],
                    'time' => $r['appointment_time'],
                    'status' => isset($r['status']) ? $r['status'] : 'booked',
                    'created_at' => isset($r['created_at']) ? $r['created_at'] : null,
                    'all_data' => isset($r['submission_data']) ? $r['submission_data'] : array(),
                );

                if ($submission_id) {
                    $full = $this->get_appointment_by_submission_id($submission_id);
                    if ($full) {
                        if (!empty($full['created_at'])) { $appt['created_at'] = $full['created_at']; }
                        if (!empty($full['all_data'])) { $appt['all_data'] = $full['all_data']; }
                    }
                }

                $out[] = $appt;
            }
            return $out;
        }

        // Otherwise derive from Elementor submission values in the month range
        $values_table = $wpdb->prefix . self::TABLE_SUBMISSION_VALUES;

        // If Elementor submission values table doesn't exist, bail early
        $exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($values_table) . "'");
        if (!$exists) {
            return array();
        }

        $submission_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sv.submission_id
            FROM {$values_table} sv
            WHERE sv.key = %s
            AND sv.value BETWEEN %s AND %s",
            self::FIELD_APPOINTMENT_DATE,
            $start_date,
            $end_date
        ));

        // Also include possible composite strings for the month
        $month_name = date('F', mktime(0,0,0,$month,1,$year));
        $like = '%' . $wpdb->esc_like($month_name . ' ' . $year) . '%';
        $composite_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sv.submission_id
            FROM {$values_table} sv
            WHERE sv.value LIKE %s",
            $like
        ));

        $submission_ids = array_unique(array_merge((array) $submission_ids, (array) $composite_ids));
        if (empty($submission_ids)) {
            return array();
        }

        $out = array();
        foreach ($submission_ids as $sid) {
            $appt = $this->get_appointment_by_submission_id($sid);
            if ($appt) {
                $out[] = $appt;
            }
        }

        return $out;
    }

    /**
     * Check if a time slot is booked
     *
     * @param string $date Y-m-d
     * @param string $time H:i or H:i:s
     * @return bool
     */
    public function is_slot_booked($date, $time) {
        global $wpdb;

        // normalize time to H:i:s
        if (strlen($time) === 5) { $cmp_time = $time . ':00'; } else { $cmp_time = $time; }

        $db = Database::get_instance();
        $rows = $db->get_appointments_for_date_from_table($date);
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $t = $r['appointment_time'];
                if (strlen($t) === 5) { $t .= ':00'; }
                if ($t === $cmp_time) {
                    return true;
                }
            }
            return false;
        }

        // Fallback: search Elementor submissions that claim this date
        $values_table = $wpdb->prefix . self::TABLE_SUBMISSION_VALUES;
        // If Elementor submission values table doesn't exist, slot cannot be booked via Elementor
        $exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($values_table) . "'");
        if (!$exists) { return false; }
        $submission_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sv.submission_id
            FROM {$values_table} sv
            WHERE sv.key = %s AND sv.value = %s",
            self::FIELD_APPOINTMENT_DATE,
            $date
        ));

        $formatted = date('F j, Y', strtotime($date));
        $composite_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sv.submission_id
            FROM {$values_table} sv
            WHERE sv.value LIKE %s",
            '%' . $wpdb->esc_like($formatted) . '%'
        ));

        $submission_ids = array_unique(array_merge((array) $submission_ids, (array) $composite_ids));
        if (empty($submission_ids)) { return false; }

        foreach ($submission_ids as $sid) {
            $appt = $this->get_appointment_by_submission_id($sid);
            if (!$appt || empty($appt['time'])) { continue; }
            $t = $appt['time'];
            if (strlen($t) === 5) { $t .= ':00'; }
            if ($appt['date'] === $date && $t === $cmp_time) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build Elementor submission edit URL
     *
     * @param int $submission_id
     * @return string
     */
    public function get_submission_edit_url($submission_id) {
        return admin_url('admin.php?page=e-form-submissions&action=view&id=' . intval($submission_id));
    }

    /**
     * Retrieve parsed appointment and submission fields for a given Elementor submission id
     * Returns null if no appointment values found
     *
     * @param int $submission_id
     * @return array|null
     */
    public function get_appointment_by_submission_id($submission_id) {
        global $wpdb;

        $submission_id = intval($submission_id);
        if ($submission_id <= 0) { return null; }

        $submissions_table = $wpdb->prefix . self::TABLE_SUBMISSIONS;
        $values_table = $wpdb->prefix . self::TABLE_SUBMISSION_VALUES;

        // If Elementor values table missing, return null
        $exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($values_table) . "'");
        if (!$exists) { return null; }

        $rows = $wpdb->get_results($wpdb->prepare("SELECT `key`, `value` FROM {$values_table} WHERE submission_id = %d", $submission_id), ARRAY_A);
        if (empty($rows)) { return null; }

        $data = array();
        foreach ($rows as $r) { $data[$r['key']] = $r['value']; }

        // Try to ensure we have appointment_date and appointment_time
        if (empty($data[self::FIELD_APPOINTMENT_DATE]) || empty($data[self::FIELD_APPOINTMENT_TIME])) {
            // search values for common composite patterns
            foreach ($data as $k => $v) {
                if (!is_string($v)) { continue; }
                $text = trim($v);
                // e.g. "December 23, 2025 - 1:00PM" or "December 23, 2025 - 13:00"
                if (preg_match('/([A-Za-z]+\s+\d{1,2},\s+\d{4})\s*[-–]\s*(\d{1,2}:\d{2}\s*(?:AM|PM|am|pm)?)/', $text, $m)) {
                    $date_part = $m[1];
                    $time_part_raw = $m[2];
                    $ts = strtotime($date_part);
                    if ($ts !== false) {
                        $time_ts = strtotime($time_part_raw);
                        if ($time_ts !== false) {
                            $data[self::FIELD_APPOINTMENT_DATE] = date('Y-m-d', $ts);
                            $data[self::FIELD_APPOINTMENT_TIME] = date('H:i', $time_ts);
                            break;
                        }
                    }
                }
            }
        }

        if (empty($data[self::FIELD_APPOINTMENT_DATE]) || empty($data[self::FIELD_APPOINTMENT_TIME])) {
            // no appointment info
            return null;
        }

        // pull created_at if available
        $created_at = null;
        $sub_row = $wpdb->get_row($wpdb->prepare("SELECT created_at FROM {$submissions_table} WHERE id = %d", $submission_id), ARRAY_A);
        if ($sub_row && isset($sub_row['created_at'])) { $created_at = $sub_row['created_at']; }

        return array(
            'id' => $submission_id,
            'date' => $data[self::FIELD_APPOINTMENT_DATE],
            'time' => $data[self::FIELD_APPOINTMENT_TIME],
            'status' => isset($data['status']) ? $data['status'] : 'pending',
            'created_at' => $created_at,
            'all_data' => $data,
        );
    }
}
