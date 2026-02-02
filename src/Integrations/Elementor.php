<?php
/**
 * Elementor class
 *
 * @package CalendarServiceAppointmentsForm\Integrations
 */

namespace CalendarServiceAppointmentsForm\Integrations;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Multisite;
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
     * Track master bookings made during this request.
     *
     * @var array<string, bool>
     */
    private $booked_master_signatures = [];

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
        add_action('elementor_pro/forms/validation', [$this, 'validate_appointment_form'], 10, 2);

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

        if (empty($appointment_date)) {
            $appointment_date = $this->get_field_value($raw_fields, 'appointment_date');
        }
        if (empty($appointment_time)) {
            $appointment_time = $this->get_field_value($raw_fields, 'appointment_time');
        }
        if (empty($appointment_date) || empty($appointment_time)) {
            $parsed = $this->parse_composite_datetime($this->extract_prop_value($raw_fields, 'time'));
            if ($parsed) {
                $appointment_date = $parsed['date'];
                $appointment_time = $parsed['time'];
            }
        }

        if (empty($appointment_date) || empty($appointment_time)) {
            $ajax_handler->add_error_message(__('Please select both date and time for your appointment.', self::TEXT_DOMAIN));
            return;
        }

        // Normalize time format
        if (strlen($appointment_time) === 5) {
            $appointment_time .= ':00';
        }

        $service_title = $this->get_service_title_from_fields($raw_fields);
        $duration_seconds = $this->get_service_duration_seconds($service_title);
        if ($duration_seconds <= 0) {
            $ajax_handler->add_error_message(__('Please select a valid service before booking.', self::TEXT_DOMAIN));
            return;
        }

        $username = $this->get_username_from_fields($raw_fields);
        $user_id = Access::resolve_enabled_user_id($username);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $field_keys = [];
            foreach ($raw_fields as $field) {
                if (is_array($field)) {
                    if (!empty($field['name'])) {
                        $field_keys[] = 'name:' . $field['name'];
                    } elseif (!empty($field['id'])) {
                        $field_keys[] = 'id:' . $field['id'];
                    }
                }
            }
            error_log('[CSA] validate_appointment_form username="' . $username . '" resolved_user_id=' . intval($user_id) . ' fields=' . implode(',', $field_keys));
        }
        if ($user_id <= 0) {
            $ajax_handler->add_error_message(__('Please select a valid user before booking.', self::TEXT_DOMAIN));
            return;
        }

        if (Multisite::is_child()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CSA] process_appointment_form child: skipping local reservation');
            }
            return;
        }

        $db = Database::get_instance();

        // Use a DB-level named lock to reduce race conditions between concurrent submissions.
        global $wpdb;
        $lock_name = 'csa_reserve_' . $appointment_date . '_' . str_replace(':', '-', $appointment_time) . '_' . $duration_seconds;
        $got_lock = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, %d)", $lock_name, 5));

        if (! $got_lock) {
            $ajax_handler->add_error_message(__('Please try again shortly, the system is checking availability.', self::TEXT_DOMAIN));
            return;
        }

        $slots = $this->build_slot_times($appointment_time, $duration_seconds);
        if (empty($slots)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            $ajax_handler->add_error_message(__('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
            return;
        }

        if (! $this->is_time_range_available($appointment_date, $slots, $db, Submissions::get_instance(), $user_id)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            $ajax_handler->add_error_message(__('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            return;
        }

        $reserved = $this->reserve_time_range($appointment_date, $slots, $db);
        $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));

        if (! $reserved) {
            $ajax_handler->add_error_message(__('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
            return;
        }

        // If we reach here, the appointment is reserved and valid. Elementor will save the submission.
    }

    /**
     * Validate appointment date/time on submit.
     *
     * @param object $record
     * @param object $ajax_handler
     * @return void
     */
    public function validate_appointment_form($record, $ajax_handler) {
        try {
            $raw_fields = $record->get('fields');
        } catch (\Throwable $e) {
            return;
        }

        $validation_payload = $this->build_validation_payload($raw_fields);
        $validation_payload = apply_filters('csa_form_validation', $validation_payload, $record);
        if (!$validation_payload->validation) {
            foreach ($validation_payload->get_error_messages() as $message) {
                $ajax_handler->add_error_message($message);
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSA] validate_appointment_form called');
        }

        $raw_fields = $this->sanitize_prefixed_props($raw_fields);
        if (method_exists($record, 'set')) {
            $record->set('fields', $raw_fields);
        }

        $appointment_date = $this->get_field_value($raw_fields, 'appointment_date');
        $appointment_time = $this->get_field_value($raw_fields, 'appointment_time');
        if (empty($appointment_date) || empty($appointment_time)) {
            $parsed = $this->parse_composite_datetime($this->extract_prop_value($raw_fields, 'time'));
            if ($parsed) {
                $appointment_date = $parsed['date'];
                $appointment_time = $parsed['time'];
            }
        }
        if (empty($appointment_date) || empty($appointment_time)) {
            $parsed = $this->extract_composite_datetime_from_fields($raw_fields);
            if ($parsed) {
                $appointment_date = $parsed['date'];
                $appointment_time = $parsed['time'];
            }
        }
        if (empty($appointment_date) || empty($appointment_time)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CSA] validate_appointment_form missing date/time');
            }
            return;
        }

        $service_title = $this->get_service_title_from_fields($raw_fields);
        $duration_seconds = $this->get_service_duration_seconds($service_title);
        if ($duration_seconds <= 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CSA] validate_appointment_form invalid duration. service=' . $service_title);
            }
            $ajax_handler->add_error_message(__('Please select a valid service before booking.', self::TEXT_DOMAIN));
            return;
        }

        $username = $this->get_username_from_fields($raw_fields);
        $user_id = Access::resolve_enabled_user_id($username);
        if ($user_id <= 0) {
            $ajax_handler->add_error_message(__('Please select a valid user before booking.', self::TEXT_DOMAIN));
            return;
        }

        if (strlen($appointment_time) === 5) {
            $appointment_time .= ':00';
        }

        if (Multisite::is_child()) {
            $normalized_time = $this->normalize_time_value($appointment_time);
            if ($normalized_time === '') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] validate_appointment_form invalid time format: ' . $appointment_time);
                }
                return;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CSA] validate_appointment_form child booking start date=' . $appointment_date . ' time=' . $normalized_time . ' service=' . $service_title . ' duration=' . $duration_seconds);
            }
            $availability = $this->check_master_time_available($appointment_date, $normalized_time, $duration_seconds, $username);
            if (is_wp_error($availability)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] validate_appointment_form master availability failed: ' . $availability->get_error_message());
                }
                $ajax_handler->add_error_message($availability->get_error_message());
                return;
            }

            $submission_data = $this->build_submission_data($raw_fields);
            if ($service_title !== '') {
                $submission_data['csa_service'] = $service_title;
            }
            if ($username !== '') {
                $submission_data['csa_user'] = $username;
            }

            $result = Multisite::book_on_master($appointment_date, $normalized_time, $service_title, $duration_seconds, $submission_data);
            if (is_wp_error($result)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CSA] validate_appointment_form master booking failed: ' . $result->get_error_message());
                }
                $ajax_handler->add_error_message($result->get_error_message());
                return;
            }

            $signature = $this->build_booking_signature($appointment_date, $normalized_time, $service_title, $duration_seconds);
            $this->booked_master_signatures[$signature] = true;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CSA] validate_appointment_form master booking succeeded');
            }
            return;
        }

        $slots = $this->build_slot_times($appointment_time, $duration_seconds);
        if (empty($slots)) {
            $ajax_handler->add_error_message(__('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            return;
        }

        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        if (! $this->is_time_range_available($appointment_date, $slots, $db, $submissions, $user_id)) {
            $ajax_handler->add_error_message(__('That date and time is not available, please select another.', self::TEXT_DOMAIN));
        }
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

        $record_payload = (object) [
            'data' => $this->build_fields_map($raw_fields),
            'raw_fields' => $raw_fields,
        ];
        do_action('csa_form_new_record', $record_payload, $record);

        try {
            $raw_fields = $record->get('fields');
        } catch (\Throwable $e) {
            return;
        }

        $raw_fields = $this->sanitize_prefixed_props($raw_fields);
        if (method_exists($record, 'set')) {
            $record->set('fields', $raw_fields);
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

        $found_any = false;
        $appointment_date = $this->get_field_value($raw_fields, 'appointment_date');
        $appointment_time = $this->get_field_value($raw_fields, 'appointment_time');

        if (!empty($appointment_date) && !empty($appointment_time)) {
            $found_any = true;
            $date = $appointment_date;
            $time = $this->normalize_time_value($appointment_time);

            $submission_data = $this->build_submission_data($raw_fields);
            $service_title = $this->get_service_title_from_fields($raw_fields);
            if ($service_title !== '') {
                $submission_data['csa_service'] = $service_title;
            }
            $username = $this->get_username_from_fields($raw_fields);
            if ($username !== '') {
                $submission_data['csa_user'] = $username;
            }
            $user_id = Access::resolve_enabled_user_id($username);

            if (Multisite::is_child()) {
                $duration_seconds = $this->get_service_duration_seconds($service_title);
                if ($duration_seconds > 0 && $time !== '') {
                    $signature = $this->build_booking_signature($date, $time, $service_title, $duration_seconds);
                    if (empty($this->booked_master_signatures[$signature])) {
                        return;
                    }
                } else {
                    return;
                }
                return;
            }

            $db = \CalendarServiceAppointmentsForm\Core\Database::get_instance();
            $insert_id = $db->insert_appointment($submission_id, $date, $time, $submission_data, $user_id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($insert_id === false) {
                    global $wpdb;
                    error_log('[CSA] insert_appointment FAILED for submission ' . var_export($submission_id, true) . ' date=' . $date . ' time=' . $time . ' error=' . $wpdb->last_error);
                } else {
                    error_log('[CSA] insert_appointment succeeded id=' . intval($insert_id) . ' for submission ' . var_export($submission_id, true) . ' date=' . $date . ' time=' . $time);
                }
            }
            return;
        }

        // Regex tolerant of formats like "December 25, 2025 - 12:00PM" or with space before AM/PM
        $regex = '/([A-Za-z]+\s+\d{1,2},\s+\d{4})\s*-\s*(\d{1,2}:\d{2}\s*(?:AM|PM|am|pm))/i';

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

                $submission_data = $this->build_submission_data($raw_fields);
                $service_title = $this->get_service_title_from_fields($raw_fields);
                if ($service_title !== '') {
                    $submission_data['csa_service'] = $service_title;
                }
                $username = $this->get_username_from_fields($raw_fields);
                if ($username !== '') {
                    $submission_data['csa_user'] = $username;
                }
                $user_id = Access::resolve_enabled_user_id($username);

                if (Multisite::is_child()) {
                    $duration_seconds = $this->get_service_duration_seconds($service_title);
                    if ($duration_seconds > 0) {
                        $signature = $this->build_booking_signature($date, $time, $service_title, $duration_seconds);
                        if (empty($this->booked_master_signatures[$signature])) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    continue;
                }

                $db = \CalendarServiceAppointmentsForm\Core\Database::get_instance();
                // Insert appointment row (store submission_data JSON)
                $insert_id = $db->insert_appointment($submission_id, $date, $time, $submission_data, $user_id);
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

    /**
     * Extract a named field value from Elementor fields.
     *
     * @param array $raw_fields
     * @param string $name
     * @return string
     */
    private function get_field_value($raw_fields, $name) {
        foreach ($raw_fields as $field) {
            if (is_array($field) && isset($field['name']) && $field['name'] === $name) {
                return is_string($field['value']) ? trim($field['value']) : '';
            }
        }
        return '';
    }

    /**
     * Get service title from supported field names or prefixed props.
     *
     * @param array $raw_fields
     * @return string
     */
    private function get_service_title_from_fields($raw_fields) {
        $service = $this->get_field_value($raw_fields, 'appointment_service');
        if ($service !== '') {
            return $service;
        }

        $service = $this->get_field_value($raw_fields, 'service');
        if ($service !== '') {
            return $service;
        }

        $service = $this->get_field_value($raw_fields, 'csa_service');
        if ($service !== '') {
            return $service;
        }

        foreach ($raw_fields as $field) {
            if (!is_array($field) || empty($field['id']) || !isset($field['value'])) {
                continue;
            }
            $id = (string) $field['id'];
            if (in_array($id, ['appointment_service', 'service', 'csa_service'], true)) {
                return is_string($field['value']) ? trim($field['value']) : '';
            }
        }

        return $this->extract_prop_value($raw_fields, 'service');
    }

    /**
     * Get username from supported field names or ids.
     *
     * @param array $raw_fields
     * @return string
     */
    private function get_username_from_fields($raw_fields) {
        $candidates = ['csa_user', 'csa_username', 'username', 'user'];
        foreach ($candidates as $candidate) {
            $value = $this->get_field_value($raw_fields, $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($raw_fields as $field) {
            if (!is_array($field) || empty($field['id']) || !isset($field['value'])) {
                continue;
            }
            $id = (string) $field['id'];
            if (in_array($id, $candidates, true)) {
                return is_string($field['value']) ? trim($field['value']) : '';
            }
        }

        $prop_value = $this->extract_prop_value($raw_fields, 'user');
        if ($prop_value !== '') {
            return $prop_value;
        }

        $request_value = $this->extract_username_from_request();
        if ($request_value !== '') {
            return $request_value;
        }

        return '';
    }

    /**
     * Extract username from the raw POSTed form fields.
     *
     * @return string
     */
    private function extract_username_from_request() {
        if (!empty($_POST['csa_user']) && is_string($_POST['csa_user'])) {
            return trim($_POST['csa_user']);
        }
        if (!empty($_POST['form_fields']) && is_array($_POST['form_fields'])) {
            $candidates = ['csa_user', 'csa_username', 'username', 'user'];
            foreach ($candidates as $candidate) {
                if (!empty($_POST['form_fields'][$candidate]) && is_string($_POST['form_fields'][$candidate])) {
                    return trim($_POST['form_fields'][$candidate]);
                }
            }
            foreach ($_POST['form_fields'] as $value) {
                if (!is_string($value)) {
                    continue;
                }
                if (stripos($value, 'csa::user') === 0 && strpos($value, '-->') !== false) {
                    $parts = explode('-->', $value, 2);
                    if (isset($parts[1])) {
                        return trim($parts[1]);
                    }
                }
            }
        }
        foreach ($_POST as $value) {
            if (!is_string($value)) {
                continue;
            }
            if (stripos($value, 'csa::user') === 0 && strpos($value, '-->') !== false) {
                $parts = explode('-->', $value, 2);
                if (isset($parts[1])) {
                    return trim($parts[1]);
                }
            }
        }
        return '';
    }

    /**
     * Extract a prefixed prop value (csa::service --> value, csa::time --> value).
     *
     * @param array $raw_fields
     * @param string $type
     * @return string
     */
    private function extract_prop_value($raw_fields, $type) {
        $prefix = 'csa::' . $type;
        foreach ($raw_fields as $field) {
            $value = '';
            if (is_array($field) && isset($field['value'])) {
                $value = trim((string) $field['value']);
            } elseif (is_string($field)) {
                $value = trim($field);
            }
            if ($value === '') {
                continue;
            }
            if (stripos($value, $prefix) === 0 && strpos($value, '-->') !== false) {
                $parts = explode('-->', $value, 2);
                if (isset($parts[1])) {
                    return trim($parts[1]);
                }
            }
        }
        return '';
    }

    /**
     * Parse a composite datetime string (e.g. "December 25, 2025 - 12:00PM").
     *
     * @param string $value
     * @return array|null
     */
    private function parse_composite_datetime($value) {
        if (!$value) {
            return null;
        }
        $regex = '/([A-Za-z]+\\s+\\d{1,2},\\s+\\d{4})\\s*-\\s*(\\d{1,2}:\\d{2}\\s*(?:AM|PM|am|pm))/i';
        if (!preg_match($regex, $value, $m)) {
            return null;
        }
        $ts = strtotime($m[1]);
        $time_ts = strtotime($m[2]);
        if ($ts === false || $time_ts === false) {
            return null;
        }
        return [
            'date' => date('Y-m-d', $ts),
            'time' => date('H:i', $time_ts),
        ];
    }

    /**
     * Parse composite datetime from any field values.
     *
     * @param array $raw_fields
     * @return array|null
     */
    private function extract_composite_datetime_from_fields($raw_fields) {
        $regex = '/([A-Za-z]+\\s+\\d{1,2},\\s+\\d{4})\\s*-\\s*(\\d{1,2}:\\d{2}\\s*(?:AM|PM|am|pm))/i';
        foreach ($raw_fields as $field) {
            $value = '';
            if (is_array($field) && isset($field['value'])) {
                $value = trim((string) $field['value']);
            } elseif (is_string($field)) {
                $value = trim($field);
            }
            if ($value === '') {
                continue;
            }
            if (!preg_match($regex, $value, $m)) {
                continue;
            }
            $ts = strtotime($m[1]);
            $time_ts = strtotime($m[2]);
            if ($ts === false || $time_ts === false) {
                continue;
            }
            return [
                'date' => date('Y-m-d', $ts),
                'time' => date('H:i', $time_ts),
            ];
        }
        return null;
    }

    /**
     * Build a keyed map of submitted fields.
     *
     * @param array $raw_fields
     * @return array
     */
    private function build_fields_map($raw_fields) {
        $out = [];
        foreach ($raw_fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = '';
            if (!empty($field['name'])) {
                $key = (string) $field['name'];
            } elseif (!empty($field['id'])) {
                $key = (string) $field['id'];
            }
            if ($key === '' || !isset($field['value'])) {
                continue;
            }
            $val = $field['value'];
            if (is_array($val)) {
                $val = implode(', ', array_filter($val, function ($v) {
                    return $v !== null && $v !== '';
                }));
            }
            $out[$key] = is_string($val) ? trim($val) : $val;
        }
        return $out;
    }

    /**
     * Build validation payload for csa_form_validation filter.
     *
     * @param array $raw_fields
     * @return object
     */
    private function build_validation_payload($raw_fields) {
        return new class($this->build_fields_map($raw_fields), $raw_fields) {
            public $validation = true;
            private $fields = [];
            private $raw_fields = [];
            private $errors = [];

            public function __construct($fields, $raw_fields) {
                $this->fields = is_array($fields) ? $fields : [];
                $this->raw_fields = is_array($raw_fields) ? $raw_fields : [];
            }

            public function __get($name) {
                if ($name === 'fields') {
                    return $this->fields;
                }
                if ($name === 'raw_fields') {
                    return $this->raw_fields;
                }
                return null;
            }

            public function add_error_message($message) {
                $msg = is_string($message) ? trim($message) : '';
                if ($msg === '') {
                    return;
                }
                $this->validation = false;
                $this->errors[] = $msg;
            }

            public function get_error_messages() {
                return $this->errors;
            }
        };
    }

    /**
     * Strip CSA prefix markers from prop values before submission handling.
     *
     * @param array $raw_fields
     * @return array
     */
    private function sanitize_prefixed_props($raw_fields) {
        $prefixes = ['csa::service', 'csa::time', 'csa::user'];
        foreach ($raw_fields as $idx => $field) {
            if (!is_array($field) || !isset($field['value'])) {
                continue;
            }
            $value = trim((string) $field['value']);
            if ($value === '' || strpos($value, '-->') === false) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                if (stripos($value, $prefix) === 0) {
                    $parts = explode('-->', $value, 2);
                    $clean = isset($parts[1]) ? trim($parts[1]) : '';
                    $raw_fields[$idx]['value'] = $clean;
                    break;
                }
            }
        }
        return $raw_fields;
    }

    /**
     * Build submission data JSON (exclude empty values).
     *
     * @param array $raw_fields
     * @return array
     */
    private function build_submission_data($raw_fields) {
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
        return $submission_data;
    }

    /**
     * Get duration seconds for a service title.
     *
     * @param string $service_title
     * @return int
     */
    private function get_service_duration_seconds($service_title) {
        if ($service_title === '') {
            return 0;
        }
        if (Multisite::is_child()) {
            $services = Multisite::fetch_master_services();
        } else {
            $db = Database::get_instance();
            $services = $db->get_services();
        }
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $title = isset($service['title']) ? (string) $service['title'] : '';
            if ($title === $service_title) {
                $duration = isset($service['duration']) ? (string) $service['duration'] : '';
                if (ctype_digit($duration)) {
                    return (int) $duration;
                }
            }
        }
        return 0;
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
     * Build slot times for a duration (30-minute increments).
     *
     * @param string $start_time
     * @param int $duration_seconds
     * @return array
     */
    private function build_slot_times($start_time, $duration_seconds) {
        $slots_needed = (int) ceil(max(0, (int) $duration_seconds) / 1800);
        if ($slots_needed <= 0) {
            return [];
        }
        $times = [];
        $start = substr($start_time, 0, 5);
        for ($i = 0; $i < $slots_needed; $i++) {
            $slot_time = $i === 0 ? $start : $this->add_minutes($start, 30 * $i);
            if (!$slot_time) {
                return [];
            }
            $times[] = $slot_time;
        }
        return $times;
    }

    /**
     * Add minutes to a HH:MM time.
     *
     * @param string $time
     * @param int $minutes
     * @return string|null
     */
    private function add_minutes($time, $minutes) {
        $dt = \DateTime::createFromFormat('H:i', $time);
        if (!$dt) {
            return null;
        }
        $dt->modify('+' . intval($minutes) . ' minutes');
        return $dt->format('H:i');
    }

    /**
     * Check if all slots are available.
     *
     * @param string $date
     * @param array $slots
     * @param Database $db
     * @param Submissions $submissions
     * @return bool
     */
    private function is_time_range_available($date, $slots, $db, $submissions, $user_id = null) {
        $weekly = $db->get_weekly_availability();
        $holiday_availability = $db->get_holiday_availability();
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date);
        $holiday_key = \CalendarServiceAppointmentsForm\Core\Holidays::get_us_holiday_key_for_date($date);
        $holiday_enabled = $holiday_key && in_array($holiday_key, $holiday_availability, true);
        $holiday_closed = $holiday_key && !$holiday_enabled;

        if ($holiday_closed) {
            return false;
        }

        $hours_set = array_fill_keys($this->get_business_hours(), true);
        foreach ($slots as $time) {
            if (!isset($hours_set[$time])) {
                return false;
            }

            $is_default_available = in_array($time, $default_hours, true);
            if (isset($overrides[$time])) {
                if ($overrides[$time] === 'allow') {
                    $is_default_available = true;
                } elseif ($overrides[$time] === 'block') {
                    $is_default_available = false;
                }
            }

            if (!$is_default_available) {
                return false;
            }

            if ($db->is_slot_blocked($date, $time) || $submissions->is_slot_booked($date, $time, $user_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reserve all slots for a range.
     *
     * @param string $date
     * @param array $slots
     * @param Database $db
     * @return bool
     */
    private function reserve_time_range($date, $slots, $db) {
        $reserved = [];
        foreach ($slots as $time) {
            $ok = $db->reserve_time_slot($date, $time);
            if (!$ok) {
                foreach ($reserved as $t) {
                    $db->unblock_time_slot($date, $t);
                }
                return false;
            }
            $reserved[] = $time;
        }
        return true;
    }

    /**
     * Normalize time value to HH:MM.
     *
     * @param string $time
     * @return string
     */
    private function normalize_time_value($time) {
        if (!is_string($time) || $time === '') {
            return '';
        }
        $time = trim($time);
        if (strlen($time) >= 5) {
            return substr($time, 0, 5);
        }
        return '';
    }

    /**
     * Build a set of available start times from a master response.
     *
     * @param array $response
     * @return array
     */
    private function extract_master_times($response) {
        $times = [];
        if (!is_array($response) || empty($response['times']) || !is_array($response['times'])) {
            return $times;
        }
        foreach ($response['times'] as $time) {
            if (is_array($time) && isset($time['value'])) {
                $value = $this->normalize_time_value((string) $time['value']);
            } else {
                $value = $this->normalize_time_value((string) $time);
            }
            if ($value !== '') {
                $times[$value] = true;
            }
        }
        return $times;
    }

    /**
     * Check master site availability for a specific start time.
     *
     * @param string $date
     * @param string $time
     * @param int $duration_seconds
     * @param string $username
     * @return bool|\WP_Error
     */
    private function check_master_time_available($date, $time, $duration_seconds, $username = '') {
        $response = Multisite::fetch_master_available_times($date, $duration_seconds, $username);
        if (is_wp_error($response)) {
            return $response;
        }
        $times = $this->extract_master_times($response);
        if (empty($times[$time])) {
            return new \WP_Error('csa_unavailable', __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
        }
        return true;
    }

    /**
     * Build a signature for master booking dedupe.
     *
     * @param string $date
     * @param string $time
     * @param string $service_title
     * @param int $duration_seconds
     * @return string
     */
    private function build_booking_signature($date, $time, $service_title, $duration_seconds) {
        return implode('|', [
            (string) $date,
            (string) $time,
            (string) $service_title,
            (string) $duration_seconds,
        ]);
    }
}
