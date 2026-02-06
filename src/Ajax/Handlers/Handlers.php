<?php
declare(strict_types=1);
/**
 * Handlers class
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all AJAX requests
 */
class Handlers extends BaseHandler {

    /**
     * @var Handlers|null
     */
    private static $instance = null;

    /**
     * @var Booking
     */
    private $booking;

    /**
     * @var Availability
     */
    private $availability;

    /**
     * @var Rescheduling
     */
    private $rescheduling;

    /**
     * Get singleton instance
     *
     * @return Handlers
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->booking = new Booking();
        $this->availability = new Availability();
        $this->rescheduling = new Rescheduling();

        add_action('wp_ajax_csa_get_day_details', [$this, 'get_day_details']);
        add_action('wp_ajax_nopriv_csa_get_day_details', [$this, 'get_day_details']);
        add_action('wp_ajax_csa_delete_appointment', [$this, 'delete_appointment']);
        add_action('wp_ajax_csa_fetch_submission_values', [$this, 'fetch_submission_values']);
        add_action('wp_ajax_csa_block_time_slot', [$this, 'block_time_slot']);
        add_action('wp_ajax_csa_unblock_time_slot', [$this, 'unblock_time_slot']);
        add_action('wp_ajax_csa_get_available_times', [$this, 'get_available_times']);
        add_action('wp_ajax_nopriv_csa_get_available_times', [$this, 'get_available_times']);
        add_action('wp_ajax_csa_get_weekly_availability', [$this, 'get_weekly_availability']);
        add_action('wp_ajax_csa_save_weekly_availability', [$this, 'save_weekly_availability']);
        add_action('wp_ajax_csa_save_holiday_availability', [$this, 'save_holiday_availability']);
        add_action('wp_ajax_csa_set_manual_override', [$this, 'set_manual_override']);
        add_action('wp_ajax_csa_save_timezone', [$this, 'save_timezone']);
        add_action('wp_ajax_csa_get_available_months', [$this, 'get_available_months']);
        add_action('wp_ajax_nopriv_csa_get_available_months', [$this, 'get_available_months']);
        add_action('wp_ajax_csa_get_available_days', [$this, 'get_available_days']);
        add_action('wp_ajax_nopriv_csa_get_available_days', [$this, 'get_available_days']);
        add_action('wp_ajax_csa_resolve_anyone_user', [$this, 'resolve_anyone_user']);
        add_action('wp_ajax_nopriv_csa_resolve_anyone_user', [$this, 'resolve_anyone_user']);
        add_action('wp_ajax_csa_filter_anyone_times', [$this, 'filter_anyone_times']);
        add_action('wp_ajax_nopriv_csa_filter_anyone_times', [$this, 'filter_anyone_times']);
        add_action('wp_ajax_csa_resolve_anyone_times', [$this, 'resolve_anyone_times']);
        add_action('wp_ajax_nopriv_csa_resolve_anyone_times', [$this, 'resolve_anyone_times']);
        add_action('wp_ajax_csa_reschedule_appointment', [$this, 'reschedule_appointment']);
        add_action('wp_ajax_csa_create_custom_appointment', [$this, 'create_custom_appointment']);
    }

    public function get_day_details(): void {
        $this->availability->get_day_details();
    }

    public function build_day_details_payload(string $date, ?int $user_id = null): mixed {
        return $this->availability->build_day_details_payload($date, $user_id);
    }

    public function delete_appointment(): void {
        $this->booking->delete_appointment();
    }

    public function reschedule_appointment(): void {
        $this->rescheduling->reschedule_appointment();
    }

    public function create_custom_appointment(): void {
        $this->booking->create_custom_appointment();
    }

    public function fetch_submission_values(): void {
        $this->booking->fetch_submission_values();
    }

    public function block_time_slot(): void {
        $this->booking->block_time_slot();
    }

    public function unblock_time_slot(): void {
        $this->booking->unblock_time_slot();
    }

    public function get_available_times(): void {
        $this->availability->get_available_times();
    }

    public function build_available_times(string $date, int $duration_seconds, ?int $user_id = null): mixed {
        return $this->availability->build_available_times($date, $duration_seconds, $user_id);
    }

    public function get_available_months(): void {
        $this->availability->get_available_months();
    }

    public function get_available_days(): void {
        $this->availability->get_available_days();
    }

    public function resolve_anyone_user(): void {
        $this->availability->resolve_anyone_user();
    }

    public function filter_anyone_times(): void {
        $this->availability->filter_anyone_times();
    }

    public function resolve_anyone_times(): void {
        $this->availability->resolve_anyone_times();
    }

    public function build_available_times_anyone(string $date, int $duration_seconds): mixed {
        return $this->availability->build_available_times_anyone($date, $duration_seconds);
    }

    public function build_available_days_anyone(string $month, int $slots_needed, ?int $duration_seconds = null): mixed {
        return $this->availability->build_available_days_anyone($month, $slots_needed, $duration_seconds);
    }

    public function build_available_days(string $month, int $slots_needed, ?int $user_id = null): mixed {
        return $this->availability->build_available_days($month, $slots_needed, $user_id);
    }

    public function check_time_range_available(string $date, string $start_time, int $duration_seconds, ?int $user_id = null): mixed {
        return $this->availability->check_time_range_available($date, $start_time, $duration_seconds, $user_id);
    }

    public function build_slot_times_for_duration(string $start_time, int $duration_seconds): array {
        return $this->availability->build_slot_times_for_duration($start_time, $duration_seconds);
    }

    public function get_weekly_availability(): void {
        $this->booking->get_weekly_availability();
    }

    public function save_weekly_availability(): void {
        $this->booking->save_weekly_availability();
    }

    public function save_timezone(): void {
        $this->booking->save_timezone();
    }

    public function save_holiday_availability(): void {
        $this->booking->save_holiday_availability();
    }

    public function set_manual_override(): void {
        $this->booking->set_manual_override();
    }
}
