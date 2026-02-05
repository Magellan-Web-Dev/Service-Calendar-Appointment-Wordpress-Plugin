<?php
/**
 * Plugin Name: Calendar Service Appointments Form
 * Description: A complete service appointment booking system with calendar interface and Elementor Pro forms integration via shortcodes.
 * Version: 1.14.0
 * Author: Chris Paschall
 */

namespace CalendarServiceAppointmentsForm;
use CalendarServiceAppointmentsForm\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check for minimum PHP version of 8.1.
 */

if (version_compare(PHP_VERSION, '8.1', '<')) {
    if (is_admin()) {
        add_action('admin_notices', function () {
            $current = PHP_VERSION;
            $message = sprintf(
                'Calendar Service Appointments Form requires PHP version 8.1 or higher. Your site is running PHP version %s, so the plugin has been disabled.',
                esc_html($current)
            );
            echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
        });
    }
    return;
}

/**
 * Absolute filesystem path to the plugin directory.
 */
define('CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * URL to the plugin directory.
 */
define('CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Current plugin version.
 */
define('CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION', '1.14.0');

/**
 * Load the plugin autoloader.
 */
require_once CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . 'src/Autoloader.php';

/**
 * Register the autoloader for plugin classes.
 *
 * @var Autoloader $autoloader
 */
$autoloader = new Autoloader();
$autoloader->register();

/**
 * Get plugin instance.
 *
 * @return Plugin
 */

Plugin::get_instance();
