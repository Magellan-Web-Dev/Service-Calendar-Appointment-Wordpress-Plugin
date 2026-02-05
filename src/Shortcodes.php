<?php
/**
 * Shortcodes for appointment field
 *
 * @package CalendarServiceAppointmentsForm
 */

namespace CalendarServiceAppointmentsForm;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Multisite;
use CalendarServiceAppointmentsForm\Views\Shortcodes\AppointmentField;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles shortcode registration and rendering for appointment fields
 */
class Shortcodes {

    /**
     * Plugin text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'calendar-service-appointments-form';

    /**
     * Shortcode tag for the appointment field
     *
     * @var string
     */
    public const SHORTCODE_TAG = 'csa_appointment_field';

    /**
     * Frontend script handle for the shortcode
     *
     * @var string
     */
    public const SCRIPT_HANDLE = 'csa-appointment-shortcode';

    /**
     * Frontend JS object name for localized data
     *
     * @var string
     */
    public const JS_OBJECT = 'csaAppointment';

    /**
     * Register shortcode and enqueue hooks
     *
     * @return void
     */
    public static function init() {
        // Register shortcode tailored for Elementor usage
        add_shortcode(self::SHORTCODE_TAG, [__CLASS__, 'render_appointment_field']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Enqueue frontend scripts for the shortcode
     *
     * @return void
     */
    public static function enqueue_scripts() {
        wp_enqueue_style(self::SCRIPT_HANDLE . '-styles', CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/css/frontend.css', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION);
        wp_enqueue_script(self::SCRIPT_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/appointment-shortcode.js', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
        wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');
        $user_services = [];
        $bookable_ids = Access::get_bookable_user_ids();
        if (!empty($bookable_ids)) {
            $user_rows = get_users([
                'include' => $bookable_ids,
                'fields' => ['ID', 'user_login'],
            ]);
            foreach ($user_rows as $user) {
                $login = isset($user->user_login) ? (string) $user->user_login : '';
                if ($login === '') {
                    continue;
                }
                $user_services[$login] = Access::get_allowed_service_slugs_for_user($user->ID);
            }
        }
        wp_localize_script(self::SCRIPT_HANDLE, self::JS_OBJECT, [
            'ajax_url' => admin_url('admin-ajax.php'),
            'user_services' => $user_services,
        ]);
    }

    /**
     * Render shortcode HTML for appointment field UI
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_appointment_field($atts = []) {
        // Accept attributes: type (service_select|service|time|user|user_select|user_anyone|user_anyone_only), label (string), elementor_prop (hidden field id), field_prop (target field id)
        $atts = shortcode_atts([
            'type' => 'time',
            'label' => '',
            'elementor_prop' => '',
            'field_prop' => '',
            'user' => '',
            'service' => '',
        ], $atts, self::SHORTCODE_TAG);

        $type = sanitize_text_field($atts['type']);
        $include_anyone = false;
        $auto_anyone = false;
        $hide_user_list = false;
        $label = sanitize_text_field($atts['label']);
        $elementor_prop = sanitize_text_field($atts['elementor_prop']);
        $field_prop = sanitize_text_field($atts['field_prop']);
        $username = sanitize_text_field($atts['user']);
        $service_slug = sanitize_title($atts['service']);

        $raw_type = $type;
        if ($type === 'services') {
            $type = 'service_select';
        }
        if ($type === 'user_anyone') {
            $type = 'user_select';
            $include_anyone = true;
        }
        if ($type === 'user_anyone_only') {
            $type = 'user_select';
            $include_anyone = true;
            $auto_anyone = true;
            $hide_user_list = true;
        }
        if (!in_array($type, ['service_select', 'service', 'time', 'user', 'user_select'], true)) {
            return '<div class="csa-appointment-error">' . esc_html__('Invalid type attribute for appointment field shortcode.', self::TEXT_DOMAIN) . '</div>';
        }
        if ($type === 'user') {
            if ($username === '') {
                return '<div class="csa-appointment-error">' . esc_html__('A valid user attribute (username) is required for this shortcode.', self::TEXT_DOMAIN) . '</div>';
            }
            if ($username !== Access::ANYONE_USERNAME && !Access::resolve_enabled_user_id($username)) {
                return '<div class="csa-appointment-error">' . esc_html__('The specified user is not enabled to use this booking form.', self::TEXT_DOMAIN) . '</div>';
            }
        }

        $services = [];
        $service = [];
        $users = [];
        $user_full_name = '';
        $anyone_value = '';
        if ($type === 'service_select' || $type === 'service') {
            $services = self::get_services_for_shortcode();
            if ($type === 'service') {
                if ($service_slug === '') {
                    return '<div class="csa-appointment-error">' . esc_html__('A valid service attribute (slug) is required for this shortcode.', self::TEXT_DOMAIN) . '</div>';
                }
                foreach ($services as $entry) {
                    if (!empty($entry['slug']) && $entry['slug'] === $service_slug) {
                        $service = $entry;
                        break;
                    }
                }
                if (empty($service)) {
                    return '<div class="csa-appointment-error">' . esc_html__('The specified service slug does not match any configured services.', self::TEXT_DOMAIN) . '</div>';
                }
            }
        }

        if ($type === 'user_select') {
            $enabled_ids = Access::get_bookable_user_ids();
            $enabled_ids = array_values(array_unique(array_filter(array_map('intval', $enabled_ids))));
            if (!empty($enabled_ids)) {
                $user_rows = get_users([
                    'include' => $enabled_ids,
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                ]);
                foreach ($user_rows as $user) {
                    $display = trim($user->first_name . ' ' . $user->last_name);
                    if ($display === '') {
                        $display = $user->display_name;
                    }
                    $users[] = [
                        'username' => $user->user_login,
                        'label' => $display,
                        'full_name' => $display,
                    ];
                }
            }
            if (count($users) === 1) {
                $type = 'user';
                $username = $users[0]['username'];
                $user_full_name = $users[0]['full_name'] ?? ($users[0]['label'] ?? '');
                $hide_user_list = true;
                $include_anyone = false;
                $auto_anyone = false;
            }
            if ($include_anyone && (count($users) > 1 || $auto_anyone)) {
                $anyone_value = Access::ANYONE_USERNAME;
                array_unshift($users, [
                    'username' => $anyone_value,
                    'label' => esc_html__('Anyone', self::TEXT_DOMAIN),
                    'full_name' => esc_html__('Anyone', self::TEXT_DOMAIN),
                ]);
            }
        }

        if ($type === 'user' && $username !== '') {
            if ($username !== Access::ANYONE_USERNAME && !Access::resolve_enabled_user_id($username)) {
                return '<div class="csa-appointment-error">' . esc_html__('The specified user is not enabled to use this booking form.', self::TEXT_DOMAIN) . '</div>';
            }
            if ($username !== Access::ANYONE_USERNAME && $user_full_name === '') {
                $wp_user = get_user_by('login', $username);
                if ($wp_user) {
                    $user_full_name = Access::build_user_display_name($wp_user);
                }
            }
        }

        return AppointmentField::render([
            'text_domain' => self::TEXT_DOMAIN,
            'type' => $type,
            'label' => $label,
            'elementor_prop' => $elementor_prop,
            'field_prop' => $field_prop,
            'services' => $services,
            'service' => $service,
            'user' => $username,
            'user_full_name' => $user_full_name,
            'users' => $users,
            'anyone_value' => $anyone_value,
            'auto_anyone' => $auto_anyone,
            'hide_user_list' => $hide_user_list,
        ]);
    }

    /**
     * Fetch services for shortcode rendering.
     *
     * @return array
     */
    private static function get_services_for_shortcode() {
        if (Multisite::is_child()) {
            $stored = Multisite::fetch_master_services();
        } else {
            $db = Database::get_instance();
            $stored = $db->get_services();
        }
        $labels = self::get_service_duration_labels();
        $services = [];

        foreach ($stored as $service) {
            if (!is_array($service)) {
                continue;
            }
            $title = isset($service['title']) ? sanitize_text_field($service['title']) : '';
            if ($title === '') {
                continue;
            }
            $sub_heading = isset($service['sub_heading']) ? sanitize_text_field($service['sub_heading']) : '';
            $description = isset($service['description']) ? sanitize_textarea_field($service['description']) : '';
            $duration_raw = isset($service['duration']) ? (string) $service['duration'] : '';
            $duration_seconds = ctype_digit($duration_raw) ? (int) $duration_raw : 0;
            $duration_label = '';
            if ($duration_seconds > 0 && isset($labels[$duration_raw])) {
                $duration_label = $labels[$duration_raw];
            } elseif ($duration_raw !== '') {
                $duration_label = $duration_raw;
            }
            $slug = '';
            if (!empty($service['slug'])) {
                $slug = sanitize_title($service['slug']);
            }
            if ($slug === '') {
                $slug = sanitize_title($title);
            }

            $services[] = [
                'title' => $title,
                'slug' => $slug,
                'sub_heading' => $sub_heading,
                'description' => $description,
                'duration_seconds' => $duration_seconds,
                'duration_label' => $duration_label,
            ];
        }

        return $services;
    }

    /**
     * Map duration seconds to labels.
     *
     * @return array
     */
    private static function get_service_duration_labels() {
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
}

// Shortcodes are initialized from the main plugin bootstrap to avoid
// accidental double-registration via autoload. See Plugin::init().
