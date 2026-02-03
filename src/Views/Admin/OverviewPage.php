<?php
/**
 * Admin overview page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class OverviewPage {

    /**
     * Render the admin overview page.
     *
     * @param array $context
     * @return void
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : '';
        $shortcodes_url = isset($context['shortcodes_url']) ? $context['shortcodes_url'] : '';

        ?>
        <div class="wrap csa-overview-page">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>

            <p><?php esc_html_e('Calendar Service Appointments provides a full booking workflow with per-user availability, services, and a frontend appointment form.', $text_domain); ?></p>

            <h2><?php esc_html_e('What You Can Do', $text_domain); ?></h2>
            <ul>
                <li><?php esc_html_e('Manage services with durations, descriptions, and slugs.', $text_domain); ?></li>
                <li><?php esc_html_e('Set weekly availability, holidays, and one-off overrides for each user.', $text_domain); ?></li>
                <li><?php esc_html_e('Allow visitors to book appointments from a frontend form using shortcodes.', $text_domain); ?></li>
                <li><?php esc_html_e('Sync bookings and availability in multisite environments (optional).', $text_domain); ?></li>
            </ul>

            <h2><?php esc_html_e('Recommended Setup Flow', $text_domain); ?></h2>
            <ol>
                <li><?php esc_html_e('Create services and durations in the Services tab.', $text_domain); ?></li>
                <li><?php esc_html_e('Enable users who can receive bookings in the Users tab.', $text_domain); ?></li>
                <li><?php esc_html_e('Configure weekly availability and holiday availability in the Calendar tab.', $text_domain); ?></li>
                <li><?php esc_html_e('Add frontend shortcodes to your form or page.', $text_domain); ?></li>
            </ol>

            <h2><?php esc_html_e('Shortcodes', $text_domain); ?></h2>
            <p>
                <?php esc_html_e('Use the [csa_appointment_field] shortcode to add booking fields on the frontend.', $text_domain); ?>
                <a href="<?php echo esc_url($shortcodes_url); ?>"><?php esc_html_e('View the shortcode guide for all types and attributes.', $text_domain); ?></a>
            </p>

            <h2><?php esc_html_e('Notes', $text_domain); ?></h2>
            <ul>
                <li><?php esc_html_e('Bookings are stored in UTC and displayed in your selected admin timezone.', $text_domain); ?></li>
                <li><?php esc_html_e('Appointments older than 3 months (based on appointment date/time) are automatically deleted.', $text_domain); ?></li>
                <li><?php esc_html_e('Elementor Pro integration supports field syncing and server-side validation.', $text_domain); ?></li>
            </ul>
        </div>
        <?php
    }
}
