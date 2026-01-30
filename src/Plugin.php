<?php
/**
 * Main plugin class
 *
 * @package CalendarServiceAppointmentsForm
 */

namespace CalendarServiceAppointmentsForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps plugin initialization and lifecycle hooks
 */
class Plugin {

    /**
     * Plugin bootstrap filename
     *
     * @var string
     */
    public const PLUGIN_FILE = 'calendar-service-appointments.php';

    /**
     * Cron hook for daily cleanup
     *
     * @var string
     */
    public const CLEANUP_HOOK = 'csa_daily_cleanup';

    /**
     * Script handles that must be loaded as ES modules
     *
     * @var array
     */
    public const MODULE_SCRIPT_HANDLES = [
        'csa-admin-calendar',
        'csa-frontend',
        'csa-elementor-editor',
        'csa-appointment-shortcode',
    ];

    /**
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Plugin
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        register_activation_hook(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . self::PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . self::PLUGIN_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_filter('script_loader_tag', [$this, 'filter_module_script_tag'], 10, 3);
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    public function init() {
        $db = Core\Database::get_instance();
        $db->maybe_migrate_times_to_utc();
        $db->maybe_backfill_submitted_at_unix();
        Core\Submissions::get_instance();
        Admin\Calendar::get_instance();
        Ajax\Handlers::get_instance();
        Integrations\Elementor::get_instance();

        // schedule cleanup hook responder
        add_action(self::CLEANUP_HOOK, [$this, 'daily_cleanup']);
        
        // Initialize shortcodes (loads src/Shortcodes.php via autoloader)
        if (class_exists('\CalendarServiceAppointmentsForm\Shortcodes')) {
            \CalendarServiceAppointmentsForm\Shortcodes::init();
        }
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        Core\Database::create_tables();
        // schedule daily cleanup if not scheduled
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
        }
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate() {
        flush_rewrite_rules();
        // remove scheduled cleanup
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }

    /**
     * Daily cleanup handler - deletes appointments older than 3 months
     *
     * @return void
     */
    public function daily_cleanup() {
        $db = Core\Database::get_instance();
        $db->delete_appointments_older_than_months(3);
    }

    /**
     * Ensure ES module scripts are loaded with type="module".
     *
     * @param string $tag Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src Script source URL.
     * @return string
     */
    public function filter_module_script_tag($tag, $handle, $src) {
        if (!in_array($handle, self::MODULE_SCRIPT_HANDLES, true)) {
            return $tag;
        }

        if (strpos($tag, ' type=') !== false) {
            return $tag;
        }

        return str_replace('<script ', '<script type="module" ', $tag);
    }
}
