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
use CalendarServiceAppointmentsForm\Views\Admin\ServicesPage;

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
     * Admin menu slug for the services page
     *
     * @var string
     */
    public const SERVICES_MENU_SLUG = 'csa-services';

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
    public const LABEL_APPOINTMENT_CALENDAR = 'Service Appointments Calendar';

    /**
     * Services page title label
     *
     * @var string
     */
    public const LABEL_SERVICES = 'Services';

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
        add_action('admin_menu', [$this, 'add_calendar_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_csa_save_services', [$this, 'handle_save_services']);
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
            [$this, 'render_calendar_page'],
            'dashicons-calendar-alt',
            25
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Calendar', self::TEXT_DOMAIN),
            __('Calendar', self::TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_calendar_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __(self::LABEL_SERVICES, self::TEXT_DOMAIN),
            __(self::LABEL_SERVICES, self::TEXT_DOMAIN),
            'manage_options',
            self::SERVICES_MENU_SLUG,
            [$this, 'render_services_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        $calendar_hooks = [
            'toplevel_page_' . self::MENU_SLUG,
            self::MENU_SLUG . '_page_' . self::MENU_SLUG,
        ];

        if (in_array($hook, $calendar_hooks, true)) {
            wp_enqueue_style(self::SCRIPT_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/css/admin-calendar.css', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION);
            wp_enqueue_script(self::SCRIPT_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/admin-calendar.js', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
            wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');

            $db = Database::get_instance();
            $weekly = $db->get_weekly_availability();
            $holiday_availability = $db->get_holiday_availability();
            $holiday_list = Holidays::get_us_holidays_for_year((int) date('Y'));
            $timezone = $db->get_timezone_string();

            wp_localize_script(self::SCRIPT_HANDLE, 'csaAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('csa_admin_nonce'),
                'weekly_availability' => $weekly,
                'holiday_availability' => $holiday_availability,
                'holiday_list' => $holiday_list,
                'hours' => $this->get_business_hours(),
                'timezone' => $timezone,
                'timezone_label' => $this->get_timezone_label($timezone),
            ]);

            return;
        }

        $is_services_page = ($hook === self::MENU_SLUG . '_page_' . self::SERVICES_MENU_SLUG)
            || (isset($_GET['page']) && $_GET['page'] === self::SERVICES_MENU_SLUG)
            || (is_string($hook) && strpos($hook, self::SERVICES_MENU_SLUG) !== false);

        if ($is_services_page) {
            wp_enqueue_style('csa-admin-services', CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/css/admin-services.css', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION);
            wp_enqueue_script('csa-admin-services', CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/admin-services.js', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
        }
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
        $tz = new \DateTimeZone($this->get_timezone_string());
        $min = new \DateTime('now', $tz);
        $min->modify('-3 months');
        $requested = \DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%02d-01', $current_year, $current_month), $tz);
        if ($requested < $min) {
            $current_month = (int) $min->format('n');
            $current_year = (int) $min->format('Y');
        }

        $calendar_cells = $this->build_calendar_cells($current_month, $current_year);
        $month_label = date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year));

        $timezone = $this->get_timezone_string();

        CalendarPage::render([
            'text_domain' => self::TEXT_DOMAIN,
            'label' => self::LABEL_APPOINTMENT_CALENDAR,
            'month_label' => $month_label,
            'calendar_cells' => $calendar_cells,
            'month' => $current_month,
            'year' => $current_year,
            'timezone' => $timezone,
            'timezone_label' => $this->get_timezone_label($timezone),
            'timezone_options' => $this->get_timezone_options(),
        ]);
    }

    /**
     * Render services page
     *
     * @return void
     */
    public function render_services_page() {
        $db = Database::get_instance();
        $services = $db->get_services();
        $saved = isset($_GET['csa_services_saved']) ? (int) $_GET['csa_services_saved'] : 0;

        ServicesPage::render([
            'text_domain' => self::TEXT_DOMAIN,
            'label' => self::LABEL_SERVICES,
            'services' => $services,
            'duration_options' => $this->get_service_duration_options(),
            'saved' => ($saved === 1),
        ]);
    }

    /**
     * Handle services form submission
     *
     * @return void
     */
    public function handle_save_services() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', self::TEXT_DOMAIN));
        }

        check_admin_referer('csa_save_services', 'csa_services_nonce');

        $services = [];
        $posted = isset($_POST['services']) && is_array($_POST['services']) ? $_POST['services'] : [];
        $duration_options = $this->get_service_duration_options();

        foreach ($posted as $service) {
            if (!is_array($service)) {
                continue;
            }

            $title = isset($service['title']) ? sanitize_text_field($service['title']) : '';
            $sub_heading = isset($service['sub_heading']) ? sanitize_text_field($service['sub_heading']) : '';
            $duration = isset($service['duration']) ? sanitize_text_field($service['duration']) : '';
            $description = isset($service['description']) ? sanitize_textarea_field($service['description']) : '';

            if ($duration !== '') {
                if (array_key_exists($duration, $duration_options)) {
                    $duration = (string) $duration;
                } else {
                    $legacy_key = array_search($duration, $duration_options, true);
                    if ($legacy_key !== false) {
                        $duration = (string) $legacy_key;
                    } else {
                        $duration = '';
                    }
                }
            }

            $has_content = ($title !== '' || $sub_heading !== '' || $duration !== '' || $description !== '');
            if (!$has_content) {
                continue;
            }

            $services[] = [
                'title' => $title,
                'sub_heading' => $sub_heading,
                'duration' => $duration,
                'description' => $description,
            ];
        }

        $db = Database::get_instance();
        $db->save_services($services);

        $redirect = add_query_arg(
            [
                'page' => self::SERVICES_MENU_SLUG,
                'csa_services_saved' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
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
        $today = $this->get_today_date();

        $blocked_by_date = [];
        foreach ($blocked_slots as $slot) {
            $date = $slot['block_date'];
            if (!isset($blocked_by_date[$date])) {
                $blocked_by_date[$date] = 0;
            }
            $blocked_by_date[$date]++;
        }

        $appointments_by_date = [];
        foreach ($appointments as $appointment) {
            $date = $appointment['date'];
            if (!isset($appointments_by_date[$date])) {
                $appointments_by_date[$date] = 0;
            }
            $appointments_by_date[$date]++;
        }

        $weeks = [];
        $day = 1;

        for ($row = 0; $row < 6; $row++) {
            $week = [];
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
                    $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
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
                    $is_today = ($date == $today);

                    $classes = ['csa-calendar-day'];
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

                    $week[] = [
                        'date' => $date,
                        'day' => $day,
                        'classes' => $classes,
                        'appointment_count' => $appointment_count,
                        'blocked_count' => $blocked_count,
                        'holiday_closed' => $holiday_closed,
                    ];
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
        return [
            '06:00', '06:30', '07:00', '07:30',
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
            '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
            '17:00', '17:30',
        ];
    }

    /**
     * Get service duration options
     *
     * @return array
     */
    private function get_service_duration_options() {
        return [
            '900' => '15 minutes',
            '1800' => '30 minutes',
            '2700' => '45 minutes',
            '3600' => '1 hour',
            '4500' => '1 hour and 15 minutes',
            '5400' => '1 hour and 30 minutes',
            '6300' => '1 hour and 45 minutes',
            '7200' => '2 hours',
            '8100' => '2 hours and 15 minutes',
            '9000' => '2 hours and 30 minutes',
            '9900' => '2 hours and 45 minutes',
            '10800' => '3 hours',
            '11700' => '3 hours and 15 minutes',
            '12600' => '3 hours and 30 minutes',
            '13500' => '3 hours and 45 minutes',
            '14400' => '4 hours',
        ];
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

    /**
     * Get timezone options for admin selector.
     *
     * @return array
     */
    private function get_timezone_options() {
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
     * Get selected timezone label.
     *
     * @param string $timezone
     * @return string
     */
    private function get_timezone_label($timezone) {
        $options = $this->get_timezone_options();
        if (isset($options[$timezone])) {
            return $options[$timezone];
        }
        return str_replace('_', ' ', $timezone);
    }

    /**
     * Get selected timezone string.
     *
     * @return string
     */
    private function get_timezone_string() {
        $db = Database::get_instance();
        $timezone = $db->get_timezone_string();
        $options = $this->get_timezone_options();
        if (!isset($options[$timezone])) {
            return 'America/New_York';
        }
        return $timezone;
    }

    /**
     * Get today's date in selected timezone.
     *
     * @return string
     */
    private function get_today_date() {
        $tz = new \DateTimeZone($this->get_timezone_string());
        $now = new \DateTime('now', $tz);
        return $now->format('Y-m-d');
    }
}
