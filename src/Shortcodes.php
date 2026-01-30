<?php
/**
 * Shortcodes for appointment field
 *
 * @package CalendarServiceAppointmentsForm
 */

namespace CalendarServiceAppointmentsForm;

use CalendarServiceAppointmentsForm\Core\Database;
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
        wp_localize_script(self::SCRIPT_HANDLE, self::JS_OBJECT, [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Render shortcode HTML for appointment field UI
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_appointment_field($atts = []) {
        // Accept attributes: type (services|time), label (string), elementor_prop (hidden field id)
        $atts = shortcode_atts([
            'type' => 'time',
            'label' => '',
            'elementor_prop' => '',
        ], $atts, self::SHORTCODE_TAG);

        $type = sanitize_text_field($atts['type']);
        $label = sanitize_text_field($atts['label']);
        $elementor_prop = sanitize_text_field($atts['elementor_prop']);
        if ($type !== 'services' && $type !== 'time') {
            $type = 'time';
        }

        $services = [];
        if ($type === 'services') {
            $db = Database::get_instance();
            $stored = $db->get_services();
            $labels = self::get_service_duration_labels();
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
                $services[] = [
                    'title' => $title,
                    'sub_heading' => $sub_heading,
                    'description' => $description,
                    'duration_seconds' => $duration_seconds,
                    'duration_label' => $duration_label,
                ];
            }
        }

        return AppointmentField::render([
            'text_domain' => self::TEXT_DOMAIN,
            'type' => $type,
            'label' => $label,
            'elementor_prop' => $elementor_prop,
            'services' => $services,
        ]);
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
