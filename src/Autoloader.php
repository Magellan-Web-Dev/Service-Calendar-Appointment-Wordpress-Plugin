<?php
/**
 * Autoloader class for PSR-4 compliant class loading
 *
 * @package CalendarServiceAppointmentsForm
 */

namespace CalendarServiceAppointmentsForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class
 */
class Autoloader {

    /**
     * The namespace prefix for this plugin
     *
     * @var string
     */
    private $namespace_prefix = 'CalendarServiceAppointmentsForm\\';

    /**
     * The base directory for this plugin
     *
     * @var string
     */
    private $base_directory;

    /**
     * Register the autoloader
     *
     * @return void
     */
    public function register() {
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->base_directory = plugin_dir_path(dirname(__FILE__)) . 'src/';
    }

    /**
     * Autoload classes
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    private function autoload($class) {
        if (strpos($class, $this->namespace_prefix) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($this->namespace_prefix));

        $file = $this->base_directory . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
