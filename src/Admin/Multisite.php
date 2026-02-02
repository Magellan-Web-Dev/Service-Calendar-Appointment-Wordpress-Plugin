<?php
/**
 * Multisite admin settings
 *
 * @package CalendarServiceAppointmentsForm\Admin
 */

namespace CalendarServiceAppointmentsForm\Admin;

use CalendarServiceAppointmentsForm\Core\Multisite as MultisiteSettings;
use CalendarServiceAppointmentsForm\Views\Admin\MultisitePage;

if (!defined('ABSPATH')) {
    exit;
}

class Multisite {

    public const TEXT_DOMAIN = 'calendar-service-appointments-form';
    public const MENU_SLUG = 'csa-multisite';
    public const LABEL_MULTISITE = 'Multisite';

    /**
     * @var Multisite|null
     */
    private static $instance = null;

    /**
     * @return Multisite
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
        add_action('admin_menu', [$this, 'add_multisite_page']);
        add_action('admin_post_csa_save_multisite', [$this, 'handle_save_multisite']);
    }

    /**
     * Add multisite submenu page.
     *
     * @return void
     */
    public function add_multisite_page() {
        add_submenu_page(
            Calendar::MENU_SLUG,
            __(self::LABEL_MULTISITE, self::TEXT_DOMAIN),
            __(self::LABEL_MULTISITE, self::TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_multisite_page']
        );
    }

    /**
     * Render multisite settings page.
     *
     * @return void
     */
    public function render_multisite_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', self::TEXT_DOMAIN));
        }

        $saved = isset($_GET['csa_multisite_saved']) ? (int) $_GET['csa_multisite_saved'] : 0;
        $mode = MultisiteSettings::get_mode();
        $master_url = MultisiteSettings::get_master_url();
        $api_key = MultisiteSettings::get_api_key();

        if ($mode === MultisiteSettings::MODE_MASTER) {
            $api_key = MultisiteSettings::ensure_master_key();
        }

        MultisitePage::render([
            'text_domain' => self::TEXT_DOMAIN,
            'label' => self::LABEL_MULTISITE,
            'mode' => $mode,
            'master_url' => $master_url,
            'api_key' => $api_key,
            'saved' => ($saved === 1),
        ]);
    }

    /**
     * Handle multisite settings save.
     *
     * @return void
     */
    public function handle_save_multisite() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', self::TEXT_DOMAIN));
        }

        check_admin_referer('csa_save_multisite', 'csa_multisite_nonce');

        $mode = isset($_POST['csa_multisite_mode']) ? sanitize_text_field($_POST['csa_multisite_mode']) : MultisiteSettings::MODE_STANDALONE;
        $master_url = isset($_POST['csa_multisite_master_url']) ? esc_url_raw($_POST['csa_multisite_master_url']) : '';
        $api_key = isset($_POST['csa_multisite_api_key']) ? sanitize_text_field($_POST['csa_multisite_api_key']) : '';
        $regenerate = !empty($_POST['csa_multisite_regenerate']);

        MultisiteSettings::save_settings($mode, $master_url, $api_key, $regenerate);

        $redirect = add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'csa_multisite_saved' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
