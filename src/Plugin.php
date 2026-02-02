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
        add_action('admin_init', [$this, 'maybe_handle_manual_update_check']);
        add_action('admin_notices', [$this, 'manual_update_notice']);
        add_filter('plugin_row_meta', [$this, 'add_check_updates_link'], 10, 2);
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
        Updates\GitHubUpdater::init();

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

    /**
     * Add "Check for updates" link to the plugin row meta.
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function add_check_updates_link($links, $file) {
        if (!current_user_can('update_plugins')) {
            return $links;
        }

        $plugin_basename = plugin_basename(CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . 'calendar-service-appointments-form.php');
        if ($file !== $plugin_basename) {
            return $links;
        }

        $url = wp_nonce_url(admin_url('plugins.php?csa-check-updates=1'), 'csa-check-updates');
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates', 'calendar-service-appointments-form') . '</a>';

        return $links;
    }

    /**
     * Handle manual update checks triggered from the plugins page.
     *
     * @return void
     */
    public function maybe_handle_manual_update_check() {
        if (!is_admin() || !current_user_can('update_plugins')) {
            return;
        }
        if (empty($_GET['csa-check-updates'])) {
            return;
        }

        check_admin_referer('csa-check-updates');

        delete_site_transient('csa_github_release');
        delete_site_transient('update_plugins');
        wp_update_plugins();

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('plugins.php');
        }
        $redirect = remove_query_arg(['csa-check-updates', '_wpnonce'], $redirect);
        $redirect = add_query_arg('csa-updates-checked', '1', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Show a success notice after a manual update check.
     *
     * @return void
     */
    public function manual_update_notice() {
        if (!is_admin() || !current_user_can('update_plugins')) {
            return;
        }
        if (empty($_GET['csa-updates-checked'])) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' .
            esc_html__('Update check completed.', 'calendar-service-appointments-form') .
            '</p></div>';
    }
}
