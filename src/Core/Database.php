<?php
/**
 * Database class
 *
 * @package CalendarServiceAppointmentsForm\Core
 */

namespace CalendarServiceAppointmentsForm\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for blocked time slots
 */
class Database {

    /**
     * Option name for weekly availability
     *
     * @var string
     */
    public const OPTION_WEEKLY_AVAILABILITY = 'csa_weekly_availability';

    /**
     * Option name for manual overrides of availability
     *
     * @var string
     */
    public const OPTION_MANUAL_OVERRIDES = 'csa_manual_overrides';

    /**
     * Option name for holiday availability
     *
     * @var string
     */
    public const OPTION_HOLIDAY_AVAILABILITY = 'csa_holiday_availability';

    /**
     * Option name for services list
     *
     * @var string
     */
    public const OPTION_SERVICES = 'csa_services';

    /**
     * Option name for selected timezone
     *
     * @var string
     */
    public const OPTION_TIMEZONE = 'csa_timezone';

    /**
     * Option flag indicating stored times are UTC
     *
     * @var string
     */
    public const OPTION_TIMES_ARE_UTC = 'csa_times_are_utc';

    /**
     * Table name for blocked time slots
     *
     * @var string
     */
    public const TABLE_BLOCKED_SLOTS = 'csa_blocked_slots';

    /**
     * Table name for appointments
     *
     * @var string
     */
    public const TABLE_APPOINTMENTS = 'csa_appointments';

    /**
     * @var Database|null
     */
    private static $instance = null;

    /**
     * @var string
     */
    private $table_name;

    /**
     * @var string
     */
    private $appointments_table;

    /**
     * Get singleton instance
     *
     * @return Database
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_BLOCKED_SLOTS;
        $this->appointments_table = $wpdb->prefix . self::TABLE_APPOINTMENTS;
    }

    /**
     * Normalize user id for scoped settings.
     *
     * @param int|null $user_id
     * @return int
     */
    private function normalize_user_id($user_id) {
        $user_id = $user_id !== null ? intval($user_id) : 0;
        if ($user_id > 0) {
            return $user_id;
        }
        return Access::get_default_admin_id();
    }

    /**
     * Build a per-user option key.
     *
     * @param string $base
     * @param int $user_id
     * @return string
     */
    private function get_user_option_key($base, $user_id) {
        return $base . '_user_' . intval($user_id);
    }

    /**
     * Get a user-scoped option, migrating legacy global options if present.
     *
     * @param string $base
     * @param int $user_id
     * @return mixed|null
     */
    private function get_user_scoped_option($base, $user_id) {
        $key = $this->get_user_option_key($base, $user_id);
        $opt = get_option($key, null);
        if ($opt !== null) {
            return $opt;
        }

        $legacy = get_option($base, null);
        if ($legacy !== null) {
            update_option($key, $legacy);
            return $legacy;
        }

        return null;
    }

    /**
     * Save a user-scoped option.
     *
     * @param string $base
     * @param int $user_id
     * @param mixed $value
     * @return bool
     */
    private function save_user_scoped_option($base, $user_id, $value) {
        $key = $this->get_user_option_key($base, $user_id);
        return update_option($key, $value);
    }

    /**
     * Get weekly availability from options
     *
     * @return array Weekday (0-6) => array of hour strings (HH:MM)
     */
    public function get_weekly_availability($user_id = null) {
        $user_id = $this->normalize_user_id($user_id);
        $opt = $this->get_user_scoped_option(self::OPTION_WEEKLY_AVAILABILITY, $user_id);
        if ($opt === null) {
            // default: Mon-Fri at predefined 30-minute increments
            $default = [];
            for ($d = 0; $d <= 6; $d++) {
                $default[$d] = [];
            }
            $default_times = [
                '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
                '11:00', '11:30', '13:00', '13:30', '14:00', '14:30',
                '15:00', '15:30', '16:00', '16:30',
            ];
            for ($d = 1; $d <= 5; $d++) {
                $default[$d] = $default_times;
            }
            $this->save_user_scoped_option(self::OPTION_WEEKLY_AVAILABILITY, $user_id, $default);
            return $default;
        }

        return (array) $opt;
    }

    /**
     * Save weekly availability to options
     *
     * @param array $availability
     * @return bool
     */
    public function save_weekly_availability($availability, $user_id = null) {
        $user_id = $this->normalize_user_id($user_id);
        return $this->save_user_scoped_option(self::OPTION_WEEKLY_AVAILABILITY, $user_id, $availability);
    }

    /**
     * Get holiday availability from options.
     *
     * @return array Array of enabled holiday keys.
     */
    public function get_holiday_availability($user_id = null) {
        $user_id = $this->normalize_user_id($user_id);
        $opt = $this->get_user_scoped_option(self::OPTION_HOLIDAY_AVAILABILITY, $user_id);
        if ($opt === null) {
            $opt = [];
            $this->save_user_scoped_option(self::OPTION_HOLIDAY_AVAILABILITY, $user_id, $opt);
        }
        return is_array($opt) ? $opt : [];
    }

    /**
     * Save holiday availability to options.
     *
     * @param array $availability
     * @return bool
     */
    public function save_holiday_availability($availability, $user_id = null) {
        $user_id = $this->normalize_user_id($user_id);
        return $this->save_user_scoped_option(self::OPTION_HOLIDAY_AVAILABILITY, $user_id, $availability);
    }

    /**
     * Get services list from options.
     *
     * @return array
     */
    public function get_services() {
        $opt = get_option(self::OPTION_SERVICES, []);
        if (!is_array($opt)) {
            return [];
        }
        return $opt;
    }

    /**
     * Save services list to options.
     *
     * @param array $services
     * @return bool
     */
    public function save_services($services) {
        return update_option(self::OPTION_SERVICES, $services);
    }

    /**
     * Get selected timezone string.
     *
     * @return string
     */
    public function get_timezone_string() {
        $tz = get_option(self::OPTION_TIMEZONE, '');
        if (!$tz) {
            $tz = 'America/New_York';
        }
        if (preg_match('/^[+-]\d{2}:\d{2}$/', $tz)) {
            return 'America/New_York';
        }
        try {
            new \DateTimeZone($tz);
            return $tz;
        } catch (\Exception $e) {
            return 'America/New_York';
        }
    }

    /**
     * Save selected timezone string.
     *
     * @param string $timezone
     * @return bool
     */
    public function save_timezone($timezone) {
        return update_option(self::OPTION_TIMEZONE, $timezone);
    }

    /**
     * Ensure existing stored times are migrated to UTC once.
     *
     * @return void
     */
    public function maybe_migrate_times_to_utc() {
        $done = get_option(self::OPTION_TIMES_ARE_UTC, '');
        if ($done === '1') {
            return;
        }

        $timezone = $this->get_timezone_string();
        $tz = $this->get_timezone_object($timezone);
        $utc = new \DateTimeZone('UTC');

        global $wpdb;

        // Migrate blocked slots
        if ($this->does_table_exist($this->table_name)) {
            $rows = $wpdb->get_results("SELECT id, block_date, block_time FROM {$this->table_name}", ARRAY_A);
            foreach ($rows as $row) {
                $local = $this->build_datetime($row['block_date'], $row['block_time'], $tz);
                if (!$local) {
                    continue;
                }
                $utc_dt = $local->setTimezone($utc);
                $wpdb->update(
                    $this->table_name,
                    [
                        'block_date' => $utc_dt->format('Y-m-d'),
                        'block_time' => $utc_dt->format('H:i:s'),
                    ],
                    ['id' => intval($row['id'])],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }

        // Migrate appointments table
        if ($this->does_table_exist($this->appointments_table)) {
            $rows = $wpdb->get_results("SELECT id, appointment_date, appointment_time FROM {$this->appointments_table}", ARRAY_A);
            foreach ($rows as $row) {
                $local = $this->build_datetime($row['appointment_date'], $row['appointment_time'], $tz);
                if (!$local) {
                    continue;
                }
                $utc_dt = $local->setTimezone($utc);
                $wpdb->update(
                    $this->appointments_table,
                    [
                        'appointment_date' => $utc_dt->format('Y-m-d'),
                        'appointment_time' => $utc_dt->format('H:i:s'),
                    ],
                    ['id' => intval($row['id'])],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }

        update_option(self::OPTION_TIMES_ARE_UTC, '1');
    }

    /**
     * Get manual overrides for availability (per-date)
     *
     * @return array date => [ 'HH:MM' => 'allow'|'block' ]
     */
    public function get_manual_overrides($user_id = null) {
        $user_id = $this->normalize_user_id($user_id);
        $opt = $this->get_user_scoped_option(self::OPTION_MANUAL_OVERRIDES, $user_id);
        if ($opt === null) {
            $opt = [];
            $this->save_user_scoped_option(self::OPTION_MANUAL_OVERRIDES, $user_id, $opt);
        }
        return is_array($opt) ? $opt : [];
    }

    /**
     * Get overrides for a specific date
     *
     * @param string $date
     * @return array
     */
    public function get_overrides_for_date($date, $user_id = null) {
        $all = $this->get_manual_overrides($user_id);
        return isset($all[$date]) ? $all[$date] : [];
    }

    /**
     * Set or remove a manual override for a date/time
     *
     * @param string $date
     * @param string $time
     * @param string $status 'allow' or 'block' or 'remove'
     * @return bool
     */
    public function set_manual_override($date, $time, $status, $user_id = null) {
        $all = $this->get_manual_overrides($user_id);
        if (!isset($all[$date])) {
            $all[$date] = [];
        }
        if ($status === 'remove') {
            if (isset($all[$date][$time])) {
                unset($all[$date][$time]);
            }
            if (empty($all[$date])) {
                unset($all[$date]);
            }
        } else {
            $all[$date][$time] = $status;
        }

        $user_id = $this->normalize_user_id($user_id);
        return $this->save_user_scoped_option(self::OPTION_MANUAL_OVERRIDES, $user_id, $all);
    }

    /**
     * Try to reserve a time slot by inserting into blocked slots table.
     * This is used as a simple reservation mechanism to avoid double-booking.
     *
     * @param string $date Date in Y-m-d format.
     * @param string $time Time in H:i:s format (or H:i).
     * @return bool True if reservation succeeded, false if already reserved.
     */
    public function reserve_time_slot($date, $time, $user_id = null) {
        global $wpdb;

        $this->ensure_blocked_slots_user_column();
        $utc = $this->convert_local_to_utc($date, $time);
        $user_id = $this->normalize_user_id($user_id);

        $result = $wpdb->insert(
            $this->table_name,
            [
                'block_date' => $utc['date'],
                'block_time' => $utc['time'],
                'user_id' => $user_id,
            ],
            ['%s', '%s', '%d']
        );

        if ($result === false) {
            // likely duplicate or error
            return false;
        }

        return true;
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLOCKED_SLOTS;
        $charset_collate = $wpdb->get_charset_collate();

        // Create blocked slots table
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            block_date date NOT NULL,
            block_time time NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY date_time_user (block_date, block_time, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Create tables
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $blocked_table_esc = esc_sql($table_name);
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$blocked_table_esc} LIKE 'user_id'");
        if (empty($col)) {
            $alter = "ALTER TABLE {$table_name} ADD COLUMN user_id BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter);
            $wpdb->query("ALTER TABLE {$table_name} ADD KEY user_id (user_id)");
        }

        $has_old_index = $wpdb->get_results("SHOW INDEX FROM {$blocked_table_esc} WHERE Key_name = 'date_time'");
        if (!empty($has_old_index)) {
            $wpdb->query("ALTER TABLE {$table_name} DROP INDEX date_time");
        }
        $has_new_index = $wpdb->get_results("SHOW INDEX FROM {$blocked_table_esc} WHERE Key_name = 'date_time_user'");
        if (empty($has_new_index)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD UNIQUE KEY date_time_user (block_date, block_time, user_id)");
        }

        $default_admin = Access::get_default_admin_id();
        if ($default_admin > 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET user_id = %d WHERE user_id IS NULL OR user_id = 0",
                $default_admin
            ));
        }

        // Create appointments table to store parsed Elementor submissions
        $appt_table = $wpdb->prefix . self::TABLE_APPOINTMENTS;
        $sql2 = "CREATE TABLE IF NOT EXISTS $appt_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            appointment_date date NOT NULL,
            appointment_time time NOT NULL,
            submission_data longtext DEFAULT NULL,
            submitted_at_unix bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY submission_date_time (submission_id, appointment_date, appointment_time),
            KEY user_id (user_id)
        ) $charset_collate;";

        dbDelta($sql2);

        // Ensure submission_data column exists (adds column for older installs)
        $appt_table_esc = esc_sql($appt_table);
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$appt_table_esc} LIKE 'submission_data'");
        if (empty($col)) {
            $alter = "ALTER TABLE {$appt_table} ADD COLUMN submission_data LONGTEXT DEFAULT NULL";
            $wpdb->query($alter);
        }

        $col = $wpdb->get_results("SHOW COLUMNS FROM {$appt_table_esc} LIKE 'user_id'");
        if (empty($col)) {
            $alter = "ALTER TABLE {$appt_table} ADD COLUMN user_id BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter);
            $wpdb->query("ALTER TABLE {$appt_table} ADD KEY user_id (user_id)");
        }

        // Ensure submitted_at_unix column exists (adds column for older installs)
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$appt_table_esc} LIKE 'submitted_at_unix'");
        if (empty($col)) {
            $alter = "ALTER TABLE {$appt_table} ADD COLUMN submitted_at_unix BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter);
        }
    }

    /**
     * Insert a parsed appointment record (from Elementor submission)
     * @param int|null $submission_id
     * @param string $date Y-m-d
     * @param string $time H:i or H:i:s
     * @param int|null $user_id
     * @return int|false Insert ID or false
     */
    public function insert_appointment($submission_id, $date, $time, $submission_data = null, $user_id = null) {
        global $wpdb;

        $utc = $this->convert_local_to_utc($date, $time);
        $user_id = $user_id !== null ? intval($user_id) : null;

        $data = [
            'submission_id' => $submission_id ? intval($submission_id) : null,
            'user_id' => $user_id,
            'appointment_date' => $utc['date'],
            'appointment_time' => $utc['time'],
            'submitted_at_unix' => time(),
        ];

        if (!empty($submission_data)) {
            if (is_array($submission_data)) {
                $json = wp_json_encode($submission_data);
            } else {
                $json = (string) $submission_data;
            }
            $data['submission_data'] = $json;
        }

        $formats = ['%d', '%d', '%s', '%s', '%d'];
        if (isset($data['submission_data'])) { $formats[] = '%s'; }
        // Ensure appointments table exists (create on-demand if necessary)
        if (!$this->does_table_exist($this->appointments_table)) {
            self::create_tables();
        }

        // Ensure submission_data column exists when we plan to insert it (fix for older installs)
        if (isset($data['submission_data']) && !$this->table_has_column($this->appointments_table, 'submission_data')) {
            $alter_sql = "ALTER TABLE {$this->appointments_table} ADD COLUMN submission_data LONGTEXT DEFAULT NULL";
            $wpdb->query($alter_sql);
        }
        if (!$this->table_has_column($this->appointments_table, 'user_id')) {
            $alter_sql = "ALTER TABLE {$this->appointments_table} ADD COLUMN user_id BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter_sql);
            $wpdb->query("ALTER TABLE {$this->appointments_table} ADD KEY user_id (user_id)");
        }
        if (!$this->table_has_column($this->appointments_table, 'submitted_at_unix')) {
            $alter_sql = "ALTER TABLE {$this->appointments_table} ADD COLUMN submitted_at_unix BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter_sql);
        }

        $res = $wpdb->insert($this->appointments_table, $data, $formats);
        if ($res === false) {
            // Attempt to update existing row for this submission/date/time
            if ($submission_id) {
                $updated = $wpdb->update(
                    $this->appointments_table,
                    [
                        'submission_data' => isset($data['submission_data']) ? $data['submission_data'] : null,
                        'user_id' => $user_id,
                    ],
                    [
                        'submission_id' => intval($submission_id),
                        'appointment_date' => $date,
                        'appointment_time' => $time,
                    ],
                    ['%s', '%d'],
                    ['%d','%s','%s']
                );
                if ($updated !== false) {
                    // fetch id
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$this->appointments_table} WHERE submission_id = %d AND appointment_date = %s AND appointment_time = %s",
                        intval($submission_id), $date, $time
                    ), ARRAY_A);
                    return $row ? intval($row['id']) : false;
                }
            }
            return false;
        }
        return $wpdb->insert_id;
    }

    /**
     * Get appointments from the appointments table for a month
     * @param int $year
     * @param int $month
     * @return array
     */
    public function get_appointments_for_month_from_table($year, $month, $user_id = null) {
        global $wpdb;
        $range = $this->get_utc_range_for_local_month($year, $month);
        $start_date = $range['start'];
        $end_date = $range['end'];
        if (!$this->does_table_exist($this->appointments_table)) {
            return [];
        }

        $has_submission_data = $this->table_has_column($this->appointments_table, 'submission_data');
        $has_submitted_at = $this->table_has_column($this->appointments_table, 'submitted_at_unix');
        $has_user_id = $this->table_has_column($this->appointments_table, 'user_id');
        $user_id = $user_id !== null ? intval($user_id) : null;
        $user_clause = '';
        $user_params = [];
        if ($has_user_id && $user_id) {
            $user_clause = ' AND user_id = %d';
            $user_params[] = $user_id;
        }
        // If submission_data column exists, include it; otherwise select without it
        if ($has_submission_data) {
            $select = "SELECT id, submission_id, " . ($has_user_id ? "user_id, " : "") . "appointment_date, appointment_time, submission_data, " . ($has_submitted_at ? "submitted_at_unix, " : "") . "created_at FROM {$this->appointments_table}
                WHERE CONCAT(appointment_date, ' ', appointment_time) BETWEEN %s AND %s{$user_clause}
                ORDER BY appointment_date, appointment_time";
            $params = array_merge([$start_date, $end_date], $user_params);
            $results = $wpdb->get_results($wpdb->prepare($select, ...$params), ARRAY_A);

            // decode submission_data JSON into array
            foreach ($results as &$r) {
                $local = $this->convert_utc_to_local($r['appointment_date'], $r['appointment_time']);
                $r['appointment_date'] = $local['date'];
                $r['appointment_time'] = $local['time'];
                if (!empty($r['submission_data'])) {
                    $decoded = json_decode($r['submission_data'], true);
                    $r['submission_data'] = $decoded !== null ? $decoded : $r['submission_data'];
                } else {
                    $r['submission_data'] = [];
                }
                if (!isset($r['submitted_at_unix'])) {
                    $r['submitted_at_unix'] = null;
                }
            }
        } else {
            $select = "SELECT id, submission_id, " . ($has_user_id ? "user_id, " : "") . "appointment_date, appointment_time, " . ($has_submitted_at ? "submitted_at_unix, " : "") . "created_at FROM {$this->appointments_table}
                WHERE CONCAT(appointment_date, ' ', appointment_time) BETWEEN %s AND %s{$user_clause}
                ORDER BY appointment_date, appointment_time";
            $params = array_merge([$start_date, $end_date], $user_params);
            $results = $wpdb->get_results($wpdb->prepare($select, ...$params), ARRAY_A);
            // ensure submission_data key exists for compatibility
            foreach ($results as &$r) {
                $local = $this->convert_utc_to_local($r['appointment_date'], $r['appointment_time']);
                $r['appointment_date'] = $local['date'];
                $r['appointment_time'] = $local['time'];
                $r['submission_data'] = [];
                if (!isset($r['submitted_at_unix'])) {
                    $r['submitted_at_unix'] = null;
                }
            }
        }

        return $results;
    }

    /**
     * Get appointments for a specific date from table
     * @param string $date
     * @return array
     */
    public function get_appointments_for_date_from_table($date, $user_id = null) {
        global $wpdb;
        if (!$this->does_table_exist($this->appointments_table)) {
            return [];
        }

        $range = $this->get_utc_range_for_local_date($date);

        $has_submission_data = $this->table_has_column($this->appointments_table, 'submission_data');
        $has_submitted_at = $this->table_has_column($this->appointments_table, 'submitted_at_unix');
        $has_user_id = $this->table_has_column($this->appointments_table, 'user_id');
        $user_id = $user_id !== null ? intval($user_id) : null;
        $user_clause = '';
        $user_params = [];
        if ($has_user_id && $user_id) {
            $user_clause = ' AND user_id = %d';
            $user_params[] = $user_id;
        }
        if ($has_submission_data) {
            $select = "SELECT id, submission_id, " . ($has_user_id ? "user_id, " : "") . "appointment_date, appointment_time, submission_data, " . ($has_submitted_at ? "submitted_at_unix, " : "") . "created_at FROM {$this->appointments_table}
                WHERE CONCAT(appointment_date, ' ', appointment_time) BETWEEN %s AND %s{$user_clause}
                ORDER BY appointment_date, appointment_time";
            $params = array_merge([$range['start'], $range['end']], $user_params);
            $results = $wpdb->get_results($wpdb->prepare($select, ...$params), ARRAY_A);

            foreach ($results as &$r) {
                $local = $this->convert_utc_to_local($r['appointment_date'], $r['appointment_time']);
                $r['appointment_date'] = $local['date'];
                $r['appointment_time'] = $local['time'];
                if (!empty($r['submission_data'])) {
                    $decoded = json_decode($r['submission_data'], true);
                    $r['submission_data'] = $decoded !== null ? $decoded : $r['submission_data'];
                } else {
                    $r['submission_data'] = [];
                }
                if (!isset($r['submitted_at_unix'])) {
                    $r['submitted_at_unix'] = null;
                }
            }
        } else {
            $select = "SELECT id, submission_id, " . ($has_user_id ? "user_id, " : "") . "appointment_date, appointment_time, " . ($has_submitted_at ? "submitted_at_unix, " : "") . "created_at FROM {$this->appointments_table}
                WHERE CONCAT(appointment_date, ' ', appointment_time) BETWEEN %s AND %s{$user_clause}
                ORDER BY appointment_date, appointment_time";
            $params = array_merge([$range['start'], $range['end']], $user_params);
            $results = $wpdb->get_results($wpdb->prepare($select, ...$params), ARRAY_A);
            foreach ($results as &$r) {
                $local = $this->convert_utc_to_local($r['appointment_date'], $r['appointment_time']);
                $r['appointment_date'] = $local['date'];
                $r['appointment_time'] = $local['time'];
                $r['submission_data'] = [];
                if (!isset($r['submitted_at_unix'])) {
                    $r['submitted_at_unix'] = null;
                }
            }
        }

        return $results;
    }

    /**
     * Delete an appointment record by its appointment-table id
     *
     * @param int $id
     * @return int|false Number of rows deleted or false
     */
    public function delete_appointment_by_id($id) {
        global $wpdb;
        if (!$this->does_table_exist($this->appointments_table)) {
            return false;
        }

        return $wpdb->delete($this->appointments_table, ['id' => intval($id)], ['%d']);
    }

    /**
     * Get a single appointment row by appointment-table id.
     *
     * @param int $id
     * @return array|null
     */
    public function get_appointment_by_id($id) {
        global $wpdb;
        if (!$this->does_table_exist($this->appointments_table)) {
            return null;
        }
        $has_submission_data = $this->table_has_column($this->appointments_table, 'submission_data');
        $has_submitted_at = $this->table_has_column($this->appointments_table, 'submitted_at_unix');
        $has_user_id = $this->table_has_column($this->appointments_table, 'user_id');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, submission_id, " . ($has_user_id ? "user_id, " : "") . "appointment_date, appointment_time, " . ($has_submission_data ? "submission_data, " : "") . ($has_submitted_at ? "submitted_at_unix, " : "") . "created_at
            FROM {$this->appointments_table} WHERE id = %d",
            intval($id)
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $local = $this->convert_utc_to_local($row['appointment_date'], $row['appointment_time']);
        $row['appointment_date'] = $local['date'];
        $row['appointment_time'] = $local['time'];
        if ($has_submission_data) {
            if (!empty($row['submission_data'])) {
                $decoded = json_decode($row['submission_data'], true);
                $row['submission_data'] = $decoded !== null ? $decoded : $row['submission_data'];
            } else {
                $row['submission_data'] = [];
            }
        } else {
            $row['submission_data'] = [];
        }
        if (!$has_submitted_at) {
            $row['submitted_at_unix'] = null;
        }
        return $row;
    }

    /**
     * Update an appointment's date/time by appointment-table id.
     *
     * @param int $id
     * @param string $date
     * @param string $time
     * @return int|false
     */
    public function reschedule_appointment_by_id($id, $date, $time) {
        global $wpdb;
        if (!$this->does_table_exist($this->appointments_table)) {
            return false;
        }
        $utc = $this->convert_local_to_utc($date, $time);
        return $wpdb->update(
            $this->appointments_table,
            [
                'appointment_date' => $utc['date'],
                'appointment_time' => $utc['time'],
            ],
            ['id' => intval($id)],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Delete appointment records by submission id + date + time match
     * Useful when the appointment-table id is not available.
     *
     * @param int $submission_id
     * @param string $date Y-m-d
     * @param string $time H:i or H:i:s
     * @return int|false Number of rows deleted or false
     */
    public function delete_appointment_by_submission($submission_id, $date, $time) {
        global $wpdb;
        $utc = $this->convert_local_to_utc($date, $time);
        if (!$this->does_table_exist($this->appointments_table)) {
            return false;
        }

        return $wpdb->delete(
            $this->appointments_table,
            [
                'submission_id' => intval($submission_id),
                'appointment_date' => $utc['date'],
                'appointment_time' => $utc['time'],
            ],
            ['%d','%s','%s']
        );
    }

    /**
     * Delete appointments older than N months (based on appointment date/time).
     *
     * @param int $months
     * @return int|false Number of rows deleted or false
     */
    public function delete_appointments_older_than_months($months = 3) {
        global $wpdb;
        if (!$this->does_table_exist($this->appointments_table)) {
            return 0;
        }

        $threshold = gmdate('Y-m-d H:i:s', strtotime("-{$months} months"));
        $sql = $wpdb->prepare(
            "DELETE FROM {$this->appointments_table}
                WHERE CONCAT(appointment_date, ' ', appointment_time) < %s",
            $threshold
        );
        return $wpdb->query($sql);
    }

    /**
     * Backfill submitted_at_unix from created_at using the WordPress timezone.
     *
     * @return void
     */
    public function maybe_backfill_submitted_at_unix() {
        global $wpdb;
        if (!$this->does_table_exist($this->appointments_table)) {
            return;
        }
        if (!$this->table_has_column($this->appointments_table, 'submitted_at_unix')) {
            $alter_sql = "ALTER TABLE {$this->appointments_table} ADD COLUMN submitted_at_unix BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter_sql);
        }

        $rows = $wpdb->get_results(
            "SELECT id, created_at, submitted_at_unix FROM {$this->appointments_table}
            WHERE submitted_at_unix IS NULL OR submitted_at_unix = 0",
            ARRAY_A
        );
        if (empty($rows)) {
            return;
        }

        $tz = $this->get_wp_timezone();
        foreach ($rows as $row) {
            if (empty($row['created_at'])) {
                continue;
            }
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['created_at'], $tz);
            if (!$dt) {
                continue;
            }
            $unix = $dt->getTimestamp();
            $wpdb->update(
                $this->appointments_table,
                ['submitted_at_unix' => $unix],
                ['id' => intval($row['id'])],
                ['%d'],
                ['%d']
            );
        }
    }

    /**
     * Backfill user_id for existing appointments.
     *
     * @param int $default_user_id
     * @return void
     */
    public function maybe_backfill_user_id($default_user_id) {
        global $wpdb;
        $default_user_id = intval($default_user_id);
        if ($default_user_id <= 0) {
            return;
        }
        if (!$this->does_table_exist($this->appointments_table)) {
            return;
        }
        if (!$this->table_has_column($this->appointments_table, 'user_id')) {
            $alter_sql = "ALTER TABLE {$this->appointments_table} ADD COLUMN user_id BIGINT(20) DEFAULT NULL";
            $wpdb->query($alter_sql);
            $wpdb->query("ALTER TABLE {$this->appointments_table} ADD KEY user_id (user_id)");
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->appointments_table}
                SET user_id = %d
                WHERE user_id IS NULL OR user_id = 0",
                $default_user_id
            )
        );
    }

    /**
     * Get WordPress timezone with fallback to UTC offset.
     *
     * @return \DateTimeZone
     */
    private function get_wp_timezone() {
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            return new \DateTimeZone($timezone_string);
        }
        $offset = (float) get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs($offset - $hours) * 60;
        $sign = $offset >= 0 ? '+' : '-';
        $tz = sprintf('UTC%s%02d:%02d', $sign, abs($hours), $minutes);
        return new \DateTimeZone($tz);
    }

    /**
     * Check whether a table exists in the current database
     *
     * @param string $table_name
     * @return bool
     */
    private function does_table_exist($table_name) {
        global $wpdb;
        $escaped = esc_sql($table_name);
        $res = $wpdb->get_var("SHOW TABLES LIKE '{$escaped}'");
        return (bool) $res;
    }

    /**
     * Check whether a specific column exists in a table
     *
     * @param string $table_name
     * @param string $column
     * @return bool
     */
    private function table_has_column($table_name, $column) {
        global $wpdb;
        $t = esc_sql($table_name);
        $c = esc_sql($column);
        $res = $wpdb->get_results("SHOW COLUMNS FROM {$t} LIKE '{$c}'");
        return !empty($res);
    }

    /**
     * Ensure blocked slots table has user_id column and indexes.
     *
     * @return void
     */
    private function ensure_blocked_slots_user_column() {
        global $wpdb;
        if (!$this->does_table_exist($this->table_name)) {
            self::create_tables();
        }
        if (!$this->does_table_exist($this->table_name)) {
            return;
        }
        if ($this->table_has_column($this->table_name, 'user_id')) {
            return;
        }

        $table = esc_sql($this->table_name);
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN user_id BIGINT(20) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} ADD KEY user_id (user_id)");

        $has_old_index = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'date_time'");
        if (!empty($has_old_index)) {
            $wpdb->query("ALTER TABLE {$table} DROP INDEX date_time");
        }
        $has_new_index = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'date_time_user'");
        if (empty($has_new_index)) {
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY date_time_user (block_date, block_time, user_id)");
        }

        $default_admin = Access::get_default_admin_id();
        if ($default_admin > 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET user_id = %d WHERE user_id IS NULL OR user_id = 0",
                $default_admin
            ));
        }
    }

    /**
     * Block a time slot
     *
     * @param string $date Date in Y-m-d format.
     * @param string $time Time in H:i:s format.
     * @return int|false The number of rows inserted, or false on error.
     */
    public function block_time_slot($date, $time, $user_id = null) {
        global $wpdb;
        $this->ensure_blocked_slots_user_column();
        $utc = $this->convert_local_to_utc($date, $time);
        $user_id = $this->normalize_user_id($user_id);

        return $wpdb->insert(
            $this->table_name,
            [
                'block_date' => $utc['date'],
                'block_time' => $utc['time'],
                'user_id' => $user_id,
            ],
            ['%s', '%s', '%d']
        );
    }

    /**
     * Unblock a time slot
     *
     * @param string $date Date in Y-m-d format.
     * @param string $time Time in H:i:s format.
     * @return int|false The number of rows deleted, or false on error.
     */
    public function unblock_time_slot($date, $time, $user_id = null) {
        global $wpdb;
        $this->ensure_blocked_slots_user_column();
        $utc = $this->convert_local_to_utc($date, $time);
        $user_id = $this->normalize_user_id($user_id);

        return $wpdb->delete(
            $this->table_name,
            [
                'block_date' => $utc['date'],
                'block_time' => $utc['time'],
                'user_id' => $user_id,
            ],
            ['%s', '%s', '%d']
        );
    }

    /**
     * Check if a time slot is blocked
     *
     * @param string $date Date in Y-m-d format.
     * @param string $time Time in H:i:s format.
     * @return bool True if blocked, false otherwise.
     */
    public function is_slot_blocked($date, $time, $user_id = null) {
        global $wpdb;
        $this->ensure_blocked_slots_user_column();
        $utc = $this->convert_local_to_utc($date, $time);
        $user_id = $this->normalize_user_id($user_id);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE block_date = %s AND block_time = %s AND user_id = %d",
            $utc['date'],
            $utc['time'],
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Get blocked slots for a month
     *
     * @param int $year Year.
     * @param int $month Month.
     * @return array Array of blocked slots.
     */
    public function get_blocked_slots_for_month($year, $month, $user_id = null) {
        global $wpdb;
        $this->ensure_blocked_slots_user_column();
        $user_id = $this->normalize_user_id($user_id);

        $range = $this->get_utc_range_for_local_month($year, $month);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT block_date, block_time FROM {$this->table_name}
            WHERE CONCAT(block_date, ' ', block_time) BETWEEN %s AND %s AND user_id = %d
            ORDER BY block_date, block_time",
            $range['start'],
            $range['end'],
            $user_id
        ), ARRAY_A);

        $out = [];
        foreach ($results as $row) {
            $local = $this->convert_utc_to_local($row['block_date'], $row['block_time']);
            $out[] = [
                'block_date' => $local['date'],
                'block_time' => $local['time'],
            ];
        }

        return $out;
    }

    /**
     * Get blocked slots for a specific date
     *
     * @param string $date Date in Y-m-d format.
     * @return array Array of blocked slots.
     */
    public function get_blocked_slots_for_date($date, $user_id = null) {
        global $wpdb;
        $this->ensure_blocked_slots_user_column();
        $user_id = $this->normalize_user_id($user_id);

        $range = $this->get_utc_range_for_local_date($date);
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT block_date, block_time FROM {$this->table_name}
            WHERE CONCAT(block_date, ' ', block_time) BETWEEN %s AND %s AND user_id = %d
            ORDER BY block_date, block_time",
            $range['start'],
            $range['end'],
            $user_id
        ), ARRAY_A);

        $out = [];
        foreach ($results as $row) {
            $local = $this->convert_utc_to_local($row['block_date'], $row['block_time']);
            $out[] = [
                'block_date' => $local['date'],
                'block_time' => $local['time'],
            ];
        }

        return $out;
    }

    /**
     * Convert local date/time into UTC date/time strings.
     *
     * @param string $date Y-m-d
     * @param string $time H:i or H:i:s
     * @return array{date:string,time:string}
     */
    private function convert_local_to_utc($date, $time) {
        $tz = $this->get_timezone_object($this->get_timezone_string());
        $local = $this->build_datetime($date, $time, $tz);
        if (!$local) {
            return ['date' => $date, 'time' => $this->normalize_time($time)];
        }
        $utc = $local->setTimezone(new \DateTimeZone('UTC'));
        return [
            'date' => $utc->format('Y-m-d'),
            'time' => $utc->format('H:i:s'),
        ];
    }

    /**
     * Convert UTC date/time into local date/time strings.
     *
     * @param string $date Y-m-d
     * @param string $time H:i or H:i:s
     * @return array{date:string,time:string}
     */
    private function convert_utc_to_local($date, $time) {
        $utc = $this->build_datetime($date, $time, new \DateTimeZone('UTC'));
        if (!$utc) {
            return ['date' => $date, 'time' => $this->normalize_time($time)];
        }
        $local = $utc->setTimezone($this->get_timezone_object($this->get_timezone_string()));
        return [
            'date' => $local->format('Y-m-d'),
            'time' => $local->format('H:i:s'),
        ];
    }

    /**
     * Build a DateTimeImmutable from date/time.
     *
     * @param string $date
     * @param string $time
     * @param \DateTimeZone $tz
     * @return \DateTimeImmutable|null
     */
    private function build_datetime($date, $time, $tz) {
        $time = $this->normalize_time($time);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $tz);
        if ($dt instanceof \DateTimeImmutable) {
            return $dt;
        }
        return null;
    }

    /**
     * Normalize time to H:i:s.
     *
     * @param string $time
     * @return string
     */
    private function normalize_time($time) {
        if (strlen($time) === 5) {
            return $time . ':00';
        }
        return $time;
    }

    /**
     * Get timezone object with fallback to UTC.
     *
     * @param string $timezone
     * @return \DateTimeZone
     */
    private function get_timezone_object($timezone) {
        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            return new \DateTimeZone('UTC');
        }
    }

    /**
     * Get UTC range for a local date (00:00:00 - 23:59:59).
     *
     * @param string $date
     * @return array{start:string,end:string}
     */
    private function get_utc_range_for_local_date($date) {
        $tz = $this->get_timezone_object($this->get_timezone_string());
        $start_local = $this->build_datetime($date, '00:00:00', $tz);
        $end_local = $this->build_datetime($date, '23:59:59', $tz);
        $utc = new \DateTimeZone('UTC');

        if (!$start_local || !$end_local) {
            return ['start' => $date . ' 00:00:00', 'end' => $date . ' 23:59:59'];
        }

        return [
            'start' => $start_local->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end' => $end_local->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get UTC range for a local month.
     *
     * @param int $year
     * @param int $month
     * @return array{start:string,end:string}
     */
    private function get_utc_range_for_local_month($year, $month) {
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        $start = $this->get_utc_range_for_local_date($start_date);
        $end = $this->get_utc_range_for_local_date($end_date);
        return [
            'start' => $start['start'],
            'end' => $end['end'],
        ];
    }
}
