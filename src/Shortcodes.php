<?php
/**
 * Shortcodes for appointment field
 *
 * @package CalendarServiceAppointmentsForm
 */

namespace CalendarServiceAppointmentsForm;

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
    public const SHORTCODE_TAG = 'elementor_appointment_field';

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
        add_shortcode(self::SHORTCODE_TAG, array(__CLASS__, 'render_appointment_field'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Enqueue frontend scripts for the shortcode
     *
     * @return void
     */
    public static function enqueue_scripts() {
        wp_enqueue_script(self::SCRIPT_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/appointment-shortcode.js', array(), CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
        wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');
        wp_localize_script(self::SCRIPT_HANDLE, self::JS_OBJECT, array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    /**
     * Render shortcode HTML for appointment field UI
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_appointment_field($atts = array()) {
        // Accept attributes: label (string), prop (hidden field key)
        $atts = shortcode_atts(array(
            'label' => '',
            'prop' => '',
        ), $atts, self::SHORTCODE_TAG);

        $label = sanitize_text_field($atts['label']);
        $prop = sanitize_text_field($atts['prop']);

        return AppointmentField::render(array(
            'text_domain' => self::TEXT_DOMAIN,
            'label' => $label,
            'prop' => $prop,
        ));
    }
}

// Shortcodes are initialized from the main plugin bootstrap to avoid
// accidental double-registration via autoload. See Plugin::init().
