<?php
/**
 * Elementor class
 *
 * @package CalendarServiceAppointmentsForm\Integrations\Elementor
 */

namespace CalendarServiceAppointmentsForm\Integrations\Elementor;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Core\Access;
use CalendarServiceAppointmentsForm\Core\Multisite;
use CalendarServiceAppointmentsForm\Core\Submissions;
use CalendarServiceAppointmentsForm\Integrations\Elementor\Fields\Appointment;
use CalendarServiceAppointmentsForm\Integrations\Elementor\Fields\AppointmentDate;
use CalendarServiceAppointmentsForm\Integrations\Elementor\Fields\AppointmentTime;

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
        add_action('elementor_pro/forms/fields/register', [$this, 'register_appointment_fields']);
        add_action('elementor_pro/init', [$this, 'init_elementor_integration']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        // shortcode script enqueue handled via enqueue_frontend_scripts

        // Listen for saved submissions to persist composite appointment strings
        add_action('elementor_pro/forms/new_record', [$this, 'handle_new_record']);
        add_action('elementor_pro/forms/validation', [$this, 'validate_appointment_form'], 10, 2);

        if (defined('WP_DEBUG') && WP_DEBUG) {
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
                }
                $module = \ElementorPro\Modules\Forms\Module::instance();
                if (is_object($module)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                    }
                    // Prefer calling our registration routine which uses add_field_type
                    $this->register_appointment_fields($module);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                    }
                }
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
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
        }
        // Register legacy separate date/time fields (kept for compatibility)
        if (!is_object($form_module) || !method_exists($form_module, 'add_field_type')) {
            return;
        }

        $form_module->add_field_type(AppointmentDate::TYPE, AppointmentDate::class);
        $form_module->add_field_type(AppointmentTime::TYPE, AppointmentTime::class);
        // Register combined Appointment field for the editor
        $form_module->add_field_type(Appointment::TYPE, Appointment::class);
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
    }

    

    /**
     * Enqueue frontend scripts
     *
     * @return void
     */
    public function enqueue_frontend_scripts() {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

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
        $raw_fields_original = $record->get('fields');
        $raw_fields = $this->sanitize_prefixed_props($raw_fields_original);
        if (method_exists($record, 'set')) {
            $record->set('fields', $raw_fields);
        }

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
            $this->add_blocking_error($record, $ajax_handler, __('Please select both date and time for your appointment.', self::TEXT_DOMAIN));
            return;
        }

        // Normalize time format
        if (strlen($appointment_time) === 5) {
            $appointment_time .= ':00';
        }

        $service_title = $this->get_service_title_from_fields($raw_fields);
        $duration_seconds = $this->get_service_duration_seconds($service_title);
        if ($duration_seconds <= 0) {
            $this->add_blocking_error($record, $ajax_handler, __('Please select a valid service before booking.', self::TEXT_DOMAIN));
            return;
        }

        $username = $this->get_user_from_fields($raw_fields_original ?: $raw_fields);
        $resolved = $this->resolve_booking_user($appointment_date, $appointment_time, $duration_seconds, $username);
        if (is_wp_error($resolved)) {
            $this->add_blocking_error($record, $ajax_handler, $resolved->get_error_message());
            return;
        }
        $user_id = isset($resolved['user_id']) ? $resolved['user_id'] : null;
        if (!$user_id) {
            $this->add_blocking_error($record, $ajax_handler, __('Please select a valid user before booking.', self::TEXT_DOMAIN));
            return;
        }
        if (!Access::user_can_perform_service($user_id, $service_title)) {
            $this->add_blocking_error($record, $ajax_handler, __('That user cannot perform the selected service.', self::TEXT_DOMAIN));
            return;
        }
        if (!empty($resolved['username'])) {
            $raw_fields = $this->apply_user_to_fields($raw_fields, $resolved['username'], $resolved['display_name'] ?? '');
            $raw_fields = $this->sanitize_prefixed_props($raw_fields);
            if (method_exists($record, 'set')) {
                $record->set('fields', $raw_fields);
            }
        }

        if (Multisite::is_child()) {
            $normalized_time = $this->normalize_time_value($appointment_time);
            if ($normalized_time === '') {
                $this->add_blocking_error($record, $ajax_handler, __('Please select both date and time for your appointment.', self::TEXT_DOMAIN));
                return;
            }

            $submission_data = $this->build_submission_data($raw_fields);
            if ($service_title !== '' && !isset($submission_data['csa_service'])) {
                $submission_data['csa_service'] = $service_title;
            }
            if ($duration_seconds > 0 && !isset($submission_data['csa_custom_duration_seconds'])) {
                $submission_data['csa_custom_duration_seconds'] = $duration_seconds;
            }

            $booked = $this->book_on_master_site($appointment_date, $normalized_time, $service_title, $duration_seconds, $submission_data);
            if (is_wp_error($booked)) {
                $this->add_blocking_error($record, $ajax_handler, $booked->get_error_message());
                return;
            }

            return;
        }

        $db = Database::get_instance();

        // Use a DB-level named lock to reduce race conditions between concurrent submissions.
        global $wpdb;
        $lock_name = 'csa_reserve_' . $appointment_date . '_' . str_replace(':', '-', $appointment_time) . '_' . $duration_seconds;
        $got_lock = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, %d)", $lock_name, 5));

        if (! $got_lock) {
            $this->add_blocking_error($record, $ajax_handler, __('Please try again shortly, the system is checking availability.', self::TEXT_DOMAIN));
            return;
        }

        $slots = $this->build_slot_times($appointment_time, $duration_seconds);
        if (empty($slots)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            $this->add_blocking_error($record, $ajax_handler, __('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
            return;
        }

        if (! $this->is_time_range_available($appointment_date, $slots, $db, Submissions::get_instance(), $user_id)) {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            $this->add_blocking_error($record, $ajax_handler, __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            return;
        }

        $reserved = $this->reserve_time_range($appointment_date, $slots, $db, $user_id);
        $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));

        if (! $reserved) {
            $this->add_blocking_error($record, $ajax_handler, __('This current time slot has already been taken, please select another.', self::TEXT_DOMAIN));
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
        $raw_fields_original = null;
        try {
            $raw_fields_original = $record->get('fields');
        } catch (\Throwable $e) {
            return;
        }

        $raw_fields = $this->sanitize_prefixed_props($raw_fields_original);
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
            return;
        }

        $service_title = $this->get_service_title_from_fields($raw_fields);
        $duration_seconds = $this->get_service_duration_seconds($service_title);
        if ($duration_seconds <= 0) {
            $this->add_blocking_error($record, $ajax_handler, __('Please select a valid service before booking.', self::TEXT_DOMAIN));
            return;
        }

        $username = $this->get_user_from_fields($raw_fields_original ?: $raw_fields);
        if ($username === '') {
            $this->add_blocking_error($record, $ajax_handler, __('Please select a valid user before booking.', self::TEXT_DOMAIN));
            return;
        }
        if (Access::is_anyone_username($username)) {
            $any = $this->has_any_available_user($appointment_date, $appointment_time, $duration_seconds);
            if (!$any) {
                $this->add_blocking_error($record, $ajax_handler, __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
                return;
            }
        }

        if (strlen($appointment_time) === 5) {
            $appointment_time .= ':00';
        }

        if (Multisite::is_child()) {
            $normalized_time = $this->normalize_time_value($appointment_time);
            if ($normalized_time === '') {
                return;
            }

            $availability = $this->check_master_time_available($appointment_date, $normalized_time, $duration_seconds);
            if (is_wp_error($availability)) {
                $this->add_blocking_error($record, $ajax_handler, $availability->get_error_message());
            }
            return;
        }

        $slots = $this->build_slot_times($appointment_time, $duration_seconds);
        if (empty($slots)) {
            $this->add_blocking_error($record, $ajax_handler, __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            return;
        }

        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        $user_id = null;
        if (!empty($username) && !Access::is_anyone_username($username)) {
            $user_id = Access::resolve_enabled_user_id($username);
            if (!$user_id) {
                $user_id = Access::resolve_enabled_user_id_by_name($username);
            }
            if (!$user_id) {
                $this->add_blocking_error($record, $ajax_handler, __('Invalid user for booking.', self::TEXT_DOMAIN));
                return;
            }
            if (!Access::user_can_perform_service($user_id, $service_title)) {
                $this->add_blocking_error($record, $ajax_handler, __('That user cannot perform the selected service.', self::TEXT_DOMAIN));
                return;
            }
        }
        if (! $this->is_time_range_available($appointment_date, $slots, $db, $submissions, $user_id)) {
            $this->add_blocking_error($record, $ajax_handler, __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            return;
        }
        if ($user_id && $submissions->is_slot_booked($appointment_date, $appointment_time, $user_id)) {
            $this->add_blocking_error($record, $ajax_handler, __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
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

        $raw_fields_original = null;
        try {
            $raw_fields_original = $record->get('fields');
        } catch (\Throwable $e) {
            return;
        }

        $raw_fields = $this->sanitize_prefixed_props($raw_fields_original);
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

        $db = \CalendarServiceAppointmentsForm\Core\Database::get_instance();

        $found_any = false;
        $appointment_date = $this->get_field_value($raw_fields, 'appointment_date');
        $appointment_time = $this->get_field_value($raw_fields, 'appointment_time');

        if (!empty($appointment_date) && !empty($appointment_time)) {
            $found_any = true;
            $date = $appointment_date;
            $time = strlen($appointment_time) === 5 ? $appointment_time : substr($appointment_time, 0, 5);

            $submission_data = $this->build_submission_data($raw_fields);
            $service_title = $this->get_service_title_from_fields($raw_fields);
            if ($service_title !== '') {
                $submission_data['csa_service'] = $service_title;
                $duration_seconds = $this->get_service_duration_seconds($service_title);
                if ($duration_seconds > 0) {
                    $submission_data['csa_custom_duration_seconds'] = $duration_seconds;
                }
            }
            $username_raw = $this->get_user_from_fields($raw_fields_original ?: $raw_fields);
            if ($username_raw !== '' && empty($submission_data['csa_username'])) {
                $submission_data['csa_username'] = $username_raw;
            }
            $user_id = $this->resolve_user_id_from_submission($submission_data);
            if (!$user_id) {
                return;
            }
            if ($duration_seconds > 0) {
                $slots = $this->build_slot_times($time, $duration_seconds);
                if (empty($slots) || ! $this->is_time_range_available($date, $slots, Database::get_instance(), Submissions::get_instance(), $user_id)) {
                    return;
                }
            }
            $insert_id = $db->insert_appointment($submission_id, $date, $time, $submission_data, $user_id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($insert_id === false) {
                    global $wpdb;
                } else {
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
                    $duration_seconds = $this->get_service_duration_seconds($service_title);
                    if ($duration_seconds > 0) {
                        $submission_data['csa_custom_duration_seconds'] = $duration_seconds;
                    }
                }
                $username_raw = $this->get_user_from_fields($raw_fields_original ?: $raw_fields);
                if ($username_raw !== '' && empty($submission_data['csa_username'])) {
                    $submission_data['csa_username'] = $username_raw;
                }

                // Insert appointment row (store submission_data JSON)
                $user_id = $this->resolve_user_id_from_submission($submission_data);
                if (!$user_id) {
                    return;
                }
                if ($duration_seconds > 0) {
                    $slots = $this->build_slot_times($time, $duration_seconds);
                    if (empty($slots) || ! $this->is_time_range_available($date, $slots, Database::get_instance(), Submissions::get_instance(), $user_id)) {
                        return;
                    }
                }
                $insert_id = $db->insert_appointment($submission_id, $date, $time, $submission_data, $user_id);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if ($insert_id === false) {
                        global $wpdb;
                    } else {
                    }
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG && ! $found_any) {
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

        return $this->extract_prop_value($raw_fields, 'service');
    }

    /**
     * Get username from supported field names or prefixed props.
     *
     * @param array $raw_fields
     * @return string
     */
    private function get_user_from_fields($raw_fields) {
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'csa_username'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'csa_user'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'csa_user_select'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'form_fields[csa_username]'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'form_fields[csa_user]'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'form_fields[csa_user_select]'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->normalize_username_value($this->get_field_value($raw_fields, 'user'));
        if ($user !== '') {
            return $user;
        }
        $user = $this->extract_prop_value($raw_fields, 'user');
        if ($user !== '') {
            return $user;
        }
        return '';
    }

    /**
     * Normalize a username value that may include CSA composite prefixes.
     *
     * @param string $value
     * @return string
     */
    private function normalize_username_value($value) {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (stripos($value, 'csa::user') === 0 && strpos($value, '-->') !== false) {
            $parts = array_map('trim', explode('-->', $value));
            if (isset($parts[1]) && $parts[1] !== '') {
                return $parts[1];
            }
        }
        return $value;
    }

    /**
     * Apply a resolved username back into submission fields.
     *
     * @param array $raw_fields
     * @param string $username
     * @return array
     */
    private function apply_user_to_fields($raw_fields, $username, $display_name = '') {
        if ($username === '') {
            return $raw_fields;
        }
        $display_name = $display_name !== '' ? $display_name : $username;
        $composite = 'csa::user --> ' . $username . ' --> ' . $display_name;
        foreach ($raw_fields as $idx => $field) {
            if (!is_array($field)) {
                continue;
            }
            $field_name = isset($field['name']) ? (string) $field['name'] : '';
            $field_id = isset($field['id']) ? (string) $field['id'] : '';
            if (!empty($field['name'])) {
                $name = $field['name'];
                if ($name === 'csa_user' || $name === 'csa_username' || $name === 'form_fields[csa_user]') {
                    $raw_fields[$idx]['value'] = $username;
                    continue;
                }
                if (strpos($name, 'csa-field-') === 0) {
                    $raw_fields[$idx]['value'] = $composite;
                    continue;
                }
            }
            if ($field_id && strpos($field_id, 'csa-field-') === 0) {
                $raw_fields[$idx]['value'] = $composite;
                continue;
            }
            if (isset($field['value']) && is_string($field['value'])) {
                $val = trim($field['value']);
                if (stripos($val, 'csa::user') === 0 && strpos($val, '-->') !== false) {
                    $raw_fields[$idx]['value'] = $composite;
                } elseif ($val === Access::ANYONE_USERNAME) {
                    if ($field_name !== '' && (strpos($field_name, 'csa-field-') === 0)) {
                        $raw_fields[$idx]['value'] = $composite;
                    } elseif ($field_id !== '' && (strpos($field_id, 'csa-field-') === 0)) {
                        $raw_fields[$idx]['value'] = $composite;
                    } else {
                        $raw_fields[$idx]['value'] = $username;
                    }
                }
            }
        }
        return $raw_fields;
    }

    /**
     * Resolve booking user for a submission (supports "anyone").
     *
     * @param string $date
     * @param string $time
     * @param int $duration_seconds
     * @param string $username
     * @return array|\WP_Error
     */
    private function resolve_booking_user($date, $time, $duration_seconds, $username) {
        $username = is_string($username) ? trim($username) : '';
        if ($username === '') {
            return ['user_id' => null, 'username' => ''];
        }
        if (Access::is_anyone_username($username)) {
            $available = $this->get_available_user_ids_for_slot($date, $time, $duration_seconds);
            if (empty($available)) {
                return new \WP_Error('csa_unavailable', __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            }
            $idx = random_int(0, count($available) - 1);
            $user_id = $available[$idx];
            $user = get_user_by('id', $user_id);
            $login = $user ? $user->user_login : '';
            if ($login === '') {
                return new \WP_Error('csa_unavailable', __('That date and time is not available, please select another.', self::TEXT_DOMAIN));
            }
            return [
                'user_id' => $user_id,
                'username' => $login,
                'display_name' => $user ? Access::build_user_display_name($user) : $login,
            ];
        }

        $user_id = Access::resolve_enabled_user_id($username);
        if (!$user_id) {
            $user_id = Access::resolve_enabled_user_id_by_name($username);
        }
        if (!$user_id) {
            return new \WP_Error('csa_invalid_user', __('Invalid user for booking.', self::TEXT_DOMAIN));
        }
        $user = get_user_by('id', $user_id);
        $login = $user ? $user->user_login : $username;
        return [
            'user_id' => $user_id,
            'username' => $login,
            'display_name' => $user ? Access::build_user_display_name($user) : $username,
        ];
    }

    /**
     * Check if any selectable user is available for the given slot.
     *
     * @param string $date
     * @param string $time
     * @param int $duration_seconds
     * @return bool
     */
    private function has_any_available_user($date, $time, $duration_seconds) {
        $available = $this->get_available_user_ids_for_slot($date, $time, $duration_seconds);
        return !empty($available);
    }

    /**
     * Get selectable user IDs that are available for a slot.
     *
     * @param string $date
     * @param string $time
     * @param int $duration_seconds
     * @return array
     */
    private function get_available_user_ids_for_slot($date, $time, $duration_seconds) {
        $slots = $this->build_slot_times($time, $duration_seconds);
        if (empty($slots)) {
            return [];
        }
        $db = Database::get_instance();
        $submissions = Submissions::get_instance();
        $available = [];
        foreach ($this->get_selectable_user_ids() as $user_id) {
            if ($this->is_time_range_available($date, $slots, $db, $submissions, $user_id)) {
                $available[] = $user_id;
            }
        }
        return $available;
    }

    /**
     * Get selectable user IDs (enabled users).
     *
     * @return array
     */
    private function get_selectable_user_ids() {
        $enabled_ids = Access::get_bookable_user_ids();
        return array_values(array_unique(array_filter(array_map('intval', $enabled_ids))));
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
                $parts = array_map('trim', explode('-->', $value));
                if ($type === 'user' && isset($parts[1])) {
                    return trim($parts[1]);
                }
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
                    if ($prefix === 'csa::user') {
                        $parts = array_map('trim', explode('-->', $value));
                        $clean = isset($parts[2]) && $parts[2] !== '' ? $parts[2] : (isset($parts[1]) ? $parts[1] : '');
                    } else {
                        $parts = explode('-->', $value, 2);
                        $clean = isset($parts[1]) ? trim($parts[1]) : '';
                    }
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
     * Resolve user id from submission data (if available).
     *
     * @param array $submission_data
     * @return int|null
     */
    private function resolve_user_id_from_submission($submission_data) {
        if (!is_array($submission_data)) {
            return null;
        }
        if (!empty($submission_data['csa_username']) && !Access::is_anyone_username($submission_data['csa_username'])) {
            $user_id = Access::resolve_enabled_user_id($submission_data['csa_username']);
            if ($user_id) {
                return $user_id;
            }
        }
        if (!empty($submission_data['csa_user']) && !Access::is_anyone_username($submission_data['csa_user'])) {
            $user_id = Access::resolve_enabled_user_id($submission_data['csa_user']);
            if ($user_id) {
                return $user_id;
            }
            $user_id = Access::resolve_enabled_user_id_by_name($submission_data['csa_user']);
            return $user_id ?: null;
        }
        return null;
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
        $weekly = $db->get_weekly_availability($user_id);
        $holiday_availability = $db->get_holiday_availability($user_id);
        $dow = date('w', strtotime($date));
        $default_hours = isset($weekly[$dow]) ? $weekly[$dow] : [];
        $overrides = $db->get_overrides_for_date($date, $user_id);
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

            if ($db->is_slot_blocked($date, $time, $user_id) || $submissions->is_slot_booked($date, $time, $user_id)) {
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
    private function reserve_time_range($date, $slots, $db, $user_id = null) {
        $reserved = [];
        foreach ($slots as $time) {
            $ok = $db->reserve_time_slot($date, $time, $user_id);
            if (!$ok) {
                foreach ($reserved as $t) {
                    $db->unblock_time_slot($date, $t, $user_id);
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
     * @return bool|\WP_Error
     */
    private function check_master_time_available($date, $time, $duration_seconds) {
        $response = Multisite::fetch_master_available_times($date, $duration_seconds);
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
     * Book an appointment on the master site.
     *
     * @param string $date
     * @param string $time
     * @param string $service_title
     * @param int $duration_seconds
     * @param array $submission_data
     * @return array|\WP_Error
     */
    private function book_on_master_site($date, $time, $service_title, $duration_seconds, $submission_data) {
        return Multisite::book_on_master($date, $time, $service_title, $duration_seconds, $submission_data);
    }

    /**
     * Add an Elementor error message.
     *
     * @param object $record
     * @param object $ajax_handler
     * @param string $message
     * @return void
     */
    private function add_blocking_error($record, $ajax_handler, $message) {
        if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_error_message')) {
            $ajax_handler->add_error_message($message);
        }
    }
}
