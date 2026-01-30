<?php
/**
 * Elementor class
 *
 * @package CalendarServiceAppointmentsForm\Integrations
 */

namespace CalendarServiceAppointmentsForm\Integrations;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Submissions;
use CalendarServiceAppointmentsForm\Integrations\Fields\Appointment;
use CalendarServiceAppointmentsForm\Integrations\Fields\AppointmentDate;
use CalendarServiceAppointmentsForm\Integrations\Fields\AppointmentTime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Elementor form integration
 */
class Elementor {

    /**
     * Plugin text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'calendar-service-appointments-form';

    /**
     * Frontend script/style handle
     *
     * @var string
     */
    public const FRONTEND_HANDLE = 'csa-frontend';

    /**
     * @var Elementor|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Elementor
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
        // Only initialize processing integration; shortcode handles the editor UI now
        add_action('elementor_pro/init', [$this, 'init_elementor_integration']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        // shortcode script enqueue handled via enqueue_frontend_scripts

        // Listen for saved submissions to persist composite appointment strings
        add_action('elementor_pro/forms/new_record', [$this, 'handle_new_record']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSA] Elementor::__construct called');
        }
    }

    /**
     * Attempt to register fields on several hooks as a fallback
     */
    public function maybe_register_fields() {
        // attempt registration when possible

        // If Elementor Pro forms module is available, try to register via Module instance
        if (class_exists('\ElementorPro\Modules\Forms\Module')) {
            try {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] maybe_register_fields: Module class exists');
                }
                $module = \ElementorPro\Modules\Forms\Module::instance();
                if (is_object($module)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[CSA] maybe_register_fields: Module::instance returned object');
                    }
                    // Prefer calling our registration routine which uses add_field_type
                    $this->register_appointment_fields($module);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[CSA] maybe_register_fields: Module::instance did not return object');
                    }
                }
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] maybe_register_fields error: ' . $e->getMessage());
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CSA] maybe_register_fields: Module class does not exist yet');
            }
        }
    }

    /**
     * Initialize Elementor integration
     *
     * @return void
     */
    public function init_elementor_integration() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSA] init_elementor_integration called (processing only)');
        }
        // Only hook into form processing to validate appointment fields submitted via shortcode
        add_action('elementor_pro/forms/process', [$this, 'process_appointment_form'], 10, 2);
    }

    /**
     * Register appointment fields
     *
     * @param object $form_module Elementor form module.
     * @return void
     */
    public function register_appointment_fields($form_module) {
        // register field types with Elementor Pro forms module
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSA] register_appointment_fields called');
            error_log('[CSA] form_module class: ' . (is_object($form_module) ? get_class($form_module) : 'not-object'));
        }
        // Register legacy separate date/time fields (kept for compatibility)
        $form_module->add_field_type(AppointmentDate::TYPE, AppointmentDate::class);
        $form_module->add_field_type(AppointmentTime::TYPE, AppointmentTime::class);
        // Register combined Appointment field for the editor
        $form_module->add_field_type(Appointment::TYPE, Appointment::class);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSA] registered field types: ' . AppointmentDate::TYPE . ', ' . AppointmentTime::TYPE . ', ' . Appointment::TYPE);
        }
    }

    

    /**
     * Enqueue frontend scripts
     *
     * @return void
     */
    public function enqueue_frontend_scripts() {
        if (\Elementor\Plugin::$instance->preview->is_preview_mode()) {
            $this->enqueue_scripts();
        }

        if (is_singular()) {
            global $post;
            if (has_shortcode($post->post_content, 'elementor-template') ||
                strpos($post->post_content, 'elementor') !== false) {
                $this->enqueue_scripts();
            }
        }
    }

    /**
     * Enqueue scripts and styles
     *
     * @return void
     */
    private function enqueue_scripts() {
        wp_enqueue_style(self::FRONTEND_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/css/frontend.css', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION);
        wp_enqueue_script(self::FRONTEND_HANDLE, CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/frontend-booking.js', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
        wp_script_add_data(self::FRONTEND_HANDLE, 'type', 'module');

        wp_localize_script(self::FRONTEND_HANDLE, 'csaFrontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('csa_frontend_nonce'),
        ]);
    }

    /**
     * Enqueue editor scripts for Elementor editor to register field type in UI
     */
    public function enqueue_editor_scripts() {
        // Load only in Elementor editor
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }

        wp_enqueue_script('csa-elementor-editor', CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL . 'assets/js/elementor-editor.js', [], CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION, true);
        wp_script_add_data('csa-elementor-editor', 'type', 'module');
    }

    /**
     * Process appointment form submission - validates availability
     *
     * @param object $record Form record.
     * @param object $ajax_handler AJAX handler.
     * @return void
     */
    public function process_appointment_form($record, $ajax_handler) {
        $raw_fields = $record->get('fields');

        $has_appointment_fields = false;
        $appointment_date = '';
        $appointment_time = '';

        // Detect appointment values either by custom field types or by known field names
        foreach ($raw_fields as $field) {
            if (isset($field['type']) && $field['type'] === AppointmentDate::TYPE) {
                $has_appointment_fields = true;
                $appointment_date = $field['value'];
            }
            if (isset($field['type']) && $field['type'] === AppointmentTime::TYPE) {
                $appointment_time = $field['value'];
            }
            if (isset($field['name']) && $field['name'] === 'appointment_date') {
                $has_appointment_fields = true;
                $appointment_date = $field['value'];
            }
            if (isset($field['name']) && $field['name'] === 'appointment_time') {
                $appointment_time = $field['value'];
            }
        }

        if (!$has_appointment_fields) {
            return;
        }

        if (empty($appointment_date) || empty($appointment_time)) {
            $ajax_handler->add_error_message(__('Please select both date and time for your appointment.', self::TEXT_DOMAIN));
            return;
        }

        // Normalize time format
        if (strlen($appointment_time) === 5) {
            $appointment_time .= ':00';
        }

        $db = Database::get_instance();

        // Use a DB-level named lock to reduce race conditions between concurrent submissions.
        global $wpdb;
        $lock_name = 'csa_reserve_' . $appointment_date . '_' . str_replace(':', '-', $appointment_time);
        $got_lock = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, %d)", $lock_name, 5));

        if (! $got_lock) {
            $ajax_handler->add_error_message(__('Please try again shortly, the system is checking availability.', self::TEXT_DOMAIN));
            return;
        }

        // Re-check under lock
        if ($db->is_slot_blocked($appointment_date, $appointment_time)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            $ajax_handler->add_error_message(__('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
            return;
        }

        $submissions = Submissions::get_instance();
        if ($submissions->is_slot_booked($appointment_date, $appointment_time)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            $ajax_handler->add_error_message(__('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
            return;
        }

        // Attempt to reserve (insert a blocked slot row). If insert fails, someone else reserved it.
        $reserved = $db->reserve_time_slot($appointment_date, $appointment_time);
        // Release the lock regardless
        $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));

        if (! $reserved) {
            $ajax_handler->add_error_message(__('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
            return;
        }

        // If we reach here, the appointment is reserved and valid. Elementor will save the submission.
    }

    /**
     * Handle Elementor saved submission record and persist composite appointment strings
     * into a dedicated appointments table for reliable dashboard display.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Record $record
     * @return void
     */
    public function handle_new_record($record) {
        if (!class_exists('\CalendarServiceAppointmentsForm\Core\Database')) {
            return;
        }

        try {
            $raw_fields = $record->get('fields');
        } catch (\Throwable $e) {
            return;
        }

        // Attempt to obtain submission ID
        $submission_id = null;
        if (method_exists($record, 'get_id')) {
            $submission_id = $record->get_id();
        } elseif (is_array($record->get('meta'))) {
            $meta = $record->get('meta');
            if (isset($meta['id'])) {
                $submission_id = intval($meta['id']);
            }
        } elseif ($record->get('id')) {
            $submission_id = intval($record->get('id'));
        }

        $db = \CalendarServiceAppointmentsForm\Core\Database::get_instance();

        // Regex tolerant of formats like "December 25, 2025 - 12:00PM" or with space before AM/PM
        $regex = '/([A-Za-z]+\s+\d{1,2},\s+\d{4})\s*-\s*(\d{1,2}:\d{2}\s*(?:AM|PM|am|pm))/i';

        $found_any = false;
        foreach ($raw_fields as $field) {
            $value = '';
            if (is_array($field) && isset($field['value'])) {
                $value = trim($field['value']);
            } elseif (is_string($field)) {
                $value = trim($field);
            }

            if (empty($value)) {
                continue;
            }

            if (preg_match($regex, $value, $m)) {
                $date_part = $m[1];
                $time_part = $m[2];

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] handle_new_record matched value: ' . $value . ' (submission_id=' . var_export($submission_id, true) . ')');
                }

                $found_any = true;

                $ts = strtotime($date_part);
                if ($ts === false) {
                    continue;
                }
                $date = date('Y-m-d', $ts);

                $time_ts = strtotime($time_part);
                if ($time_ts === false) {
                    // try removing spaces
                    $time_ts = strtotime(str_replace(' ', '', $time_part));
                    if ($time_ts === false) {
                        continue;
                    }
                }
                $time = date('H:i', $time_ts);

                // Build submission data JSON (exclude empty values)
                $submission_data = [];
                foreach ($raw_fields as $field) {
                    $key = null;
                    $val = null;
                    if (is_array($field)) {
                        if (!empty($field['name'])) {
                            $key = $field['name'];
                        } elseif (!empty($field['id'])) {
                            $key = $field['id'];
                        }
                        if (isset($field['value'])) { $val = $field['value']; }
                    } elseif (is_string($field)) {
                        // no key available, skip
                        continue;
                    }

                    if ($key === null) { continue; }
                    if (is_array($val)) {
                        $flat = implode(', ', array_filter($val, function($v){ return $v !== null && $v !== ''; }));
                        $val = $flat;
                    }
                    $val = is_string($val) ? trim($val) : $val;
                    if ($val === '' || $val === null) { continue; }
                    $submission_data[$key] = $val;
                }

                // Insert appointment row (store submission_data JSON)
                $insert_id = $db->insert_appointment($submission_id, $date, $time, $submission_data);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if ($insert_id === false) {
                        global $wpdb;
                        error_log('[CSA] insert_appointment FAILED for submission ' . var_export($submission_id, true) . ' date=' . $date . ' time=' . $time . ' error=' . $wpdb->last_error);
                    } else {
                        error_log('[CSA] insert_appointment succeeded id=' . intval($insert_id) . ' for submission ' . var_export($submission_id, true) . ' date=' . $date . ' time=' . $time);
                    }
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG && ! $found_any) {
            error_log('[CSA] handle_new_record found no composite appointment values for submission ' . var_export($submission_id, true));
        }
    }
}
