<?php
/**
 * Calendar class
 *
 * @package CalendarServiceAppointmentsForm\Admin
 */

namespace CalendarServiceAppointmentsForm\Admin;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Holidays;
use CalendarServiceAppointmentsForm\Core\Submissions;
use CalendarServiceAppointmentsForm\Views\Admin\CalendarPage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the admin calendar interface
 */
class Calendar {

    /**
     * Plugin text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'calendar-service-appointments-form';

    /**
     * Admin menu slug for the calendar page
     *
     * @var string
     */
    public const MENU_SLUG = 'csa-calendar';

    /**
     * Admin script/style handle
     *
     * @var string
     */
    public const SCRIPT_HANDLE = 'csa-admin-calendar';

    /**
     * Calendar page title label
     *
     * @var string
     */
    public const LABEL_APPOINTMENT_CALENDAR = 'Service Appointment Calendar';

    /**
     * @var Calendar|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Calendar
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
        add_action('admin_menu', array($this, 'add_calendar_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add calendar page to admin menu
     *
     * @return void
     */
    public function add_calendar_page() {
        add_menu_page(
            __(self::LABEL_APPOINTMENT_CALENDAR, self::TEXT_DOMAIN),
            __('Service Appointments', self::TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_calendar_page'),
            'dashicons-calendar-alt',
            25
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(self::SCRIPT_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/css/admin-calendar.css', array(), CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION);
        wp_enqueue_script(self::SCRIPT_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/admin-calendar.js', array(), CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
        wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability();
        $holiday_availability = $db->get_holiday_availability();
        $holiday_list = Holidays::get_us_holidays_for_year((int) date('Y'));

        wp_localize_script(self::SCRIPT_HANDLE, 'csaAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('csa_admin_nonce'),
            'weekly_availability' => $weekly,
            'holiday_availability' => $holiday_availability,
            'holiday_list' => $holiday_list,
            'hours' => $this->get_business_hours(),
        ));
    }

    /**
     * Render calendar page
     *
     * @return void
     */
    public function render_calendar_page() {
        $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        // Prevent navigating to months older than 3 months ago
        $min = new \DateTime();
        $min->modify('-3 months');
        $requested = \DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%02d-01', $current_year, $current_month));
        if ($requested < $min) {
            $current_month = (int) $min->format('n');
            $current_year = (int) $min->format('Y');
        }

        $calendar_cells = $this->build_calendar_cells($current_month, $current_year);
        $month_label = date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year));

        CalendarPage::render(array(
            'text_domain' => self::TEXT_DOMAIN,
            'label' => self::LABEL_APPOINTMENT_CALENDAR,
            'month_label' => $month_label,
            'calendar_cells' => $calendar_cells,
            'month' => $current_month,
            'year' => $current_year,
        ));
    }

    /**
     * Build calendar grid view data.
     *
     * @param int $month Month number.
     * @param int $year Year number.
     * @return array
     */
    private function build_calendar_cells($month, $year) {
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day);
        $day_of_week = date('w', $first_day);

        $db = Database::get_instance();
        $weekly = $db->get_weekly_availability();
        $blocked_slots = $db->get_blocked_slots_for_month($year, $month);
        $holiday_availability = $db->get_holiday_availability();

        $appointments = $this->get_appointments_for_month($year, $month);

        $blocked_by_date = array();
        foreach ($blocked_slots as $slot) {
            $date = $slot['block_date'];
            if (!isset($blocked_by_date[$date])) {
                $blocked_by_date[$date] = 0;
            }
            $blocked_by_date[$date]++;
        }

        $appointments_by_date = array();
        foreach ($appointments as $appointment) {
            $date = $appointment['date'];
            if (!isset($appointments_by_date[$date])) {
                $appointments_by_date[$date] = 0;
            }
            $appointments_by_date[$date]++;
        }

        $weeks = array();
        $day = 1;

        for ($row = 0; $row < 6; $row++) {
            $week = array();
            for ($col = 0; $col < 7; $col++) {
                if ($row == 0 && $col < $day_of_week) {
                    $week[] = null;
                } elseif ($day > $days_in_month) {
                    $week[] = null;
                } else {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $holiday_key = Holidays::get_us_holiday_key_for_date($date);
                    $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
                    $holiday_closed = $holiday_key && !$holiday_enabled;
                    $dow = $col; // 0=Sun .. 6=Sat
                    $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : array();
                    $overrides_for_date = $db->get_overrides_for_date($date);
                    if ($holiday_closed) {
                        $has_available = false;
                    } else {
                        $has_available = !empty($default_hours);
                        foreach ($overrides_for_date as $t => $s) {
                            if ($s === 'allow') {
                                $has_available = true;
                                break;
                            }
                        }
                    }
                    $is_today = ($date == date('Y-m-d'));

                    $classes = array('csa-calendar-day');
                    if (!$has_available) {
                        $classes[] = 'weekend';
                    }
                    if ($holiday_closed) {
                        $classes[] = 'holiday-closed';
                    }
                    if ($is_today) {
                        $classes[] = 'today';
                    }

                    $blocked_count = isset($blocked_by_date[$date]) ? $blocked_by_date[$date] : 0;
                    $appointment_count = isset($appointments_by_date[$date]) ? $appointments_by_date[$date] : 0;

                    $week[] = array(
                        'date' => $date,
                        'day' => $day,
                        'classes' => $classes,
                        'appointment_count' => $appointment_count,
                        'blocked_count' => $blocked_count,
                        'holiday_closed' => $holiday_closed,
                    );
                    $day++;
                }
            }

            $weeks[] = $week;

            if ($day > $days_in_month) {
                break;
            }
        }

        return $weeks;
    }

    /**
     * Get business hours (30-minute increments).
     *
     * @return array
     */
    private function get_business_hours() {
        return array(
            '06:00', '06:30', '07:00', '07:30',
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
            '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
            '17:00', '17:30',
        );
    }

    /**
     * Get appointments for a specific month
     *
     * @param int $year Year.
     * @param int $month Month.
     * @return array Array of appointments.
     */
    private function get_appointments_for_month($year, $month) {
        $submissions = Submissions::get_instance();
        return $submissions->get_appointments_for_month($year, $month);
    }
}
