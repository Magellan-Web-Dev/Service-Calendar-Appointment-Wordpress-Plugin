<?php
/**
 * Plugin Name: Calendar Service Appointments Form
 * Description: A complete service appointment booking system with calendar interface and Elementor Pro forms integration via shortcodes.
 * Version: 1.7.6
 * Author: Chris Paschall
 */

namespace CalendarServiceAppointmentsForm;
use CalendarServiceAppointmentsForm\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

define('CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION', '1.7.6');

require_once CALENDAR_SERVICE_APPOINTMENTS_FORM_PLUGIN_DIR . 'src/Autoloader.php';

$autoloader = new Autoloader();
$autoloader->register();

/**
 * Get plugin instance
 *
 * @return Plugin
 */

Plugin::get_instance();
