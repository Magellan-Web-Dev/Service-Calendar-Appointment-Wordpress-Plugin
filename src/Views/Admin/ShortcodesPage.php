<?php
/**
 * Admin shortcode guide page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class ShortcodesPage {

    /**
     * Render the admin shortcode guide page.
     *
     * @param array $context
     * @return void
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : '';

        ?>
        <div class="wrap csa-shortcodes-page">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>

            <p><?php esc_html_e('Use the [csa_appointment_field] shortcode inside any content area that supports shortcodes (including Elementor HTML/Raw HTML fields).', $text_domain); ?></p>

            <h2><?php esc_html_e('Recommended Flow', $text_domain); ?></h2>
            <p><?php esc_html_e('For user-select bookings, place these in order:', $text_domain); ?></p>
            <ol>
                <li><?php esc_html_e('Service selection', $text_domain); ?> — <code>[csa_appointment_field type="service_select"]</code></li>
                <li><?php esc_html_e('User selection', $text_domain); ?> — <code>[csa_appointment_field type="user_select"]</code></li>
                <li><?php esc_html_e('User selection (with Anyone)', $text_domain); ?> — <code>[csa_appointment_field type="user_anyone"]</code></li>
                <li><?php esc_html_e('Optional service duration prop (hidden)', $text_domain); ?> — <code>[csa_appointment_field type="service_duration" elementor_prop="duration_field_id"]</code></li>
                <li><?php esc_html_e('Calendar and time', $text_domain); ?> — <code>[csa_appointment_field type="time"]</code></li>
            </ol>

            <h2><?php esc_html_e('Shortcode Types', $text_domain); ?></h2>
            <table class="widefat striped" style="max-width: 1200px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Type', $text_domain); ?></th>
                        <th><?php esc_html_e('What it does', $text_domain); ?></th>
                        <th><?php esc_html_e('Example', $text_domain); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>user</code></td>
                        <td><?php esc_html_e('Hidden user field that preselects a specific user by username. The user must be enabled.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="user" user="username"]</code></td>
                    </tr>
                    <tr>
                        <td><code>user_select</code></td>
                        <td><?php esc_html_e('Renders the user list and dropdown. Required when you want the visitor to choose a user. If only one user is enabled, it auto-selects and hides the list.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="user_select"]</code></td>
                    </tr>
                    <tr>
                        <td><code>user_anyone</code></td>
                        <td><?php esc_html_e('Same as user_select but includes the "Anyone" option.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="user_anyone"]</code></td>
                    </tr>
                    <tr>
                        <td><code>user_anyone_only</code></td>
                        <td><?php esc_html_e('Hidden version of user_anyone. Auto-selects "Anyone" and resolves a specific user when a time is chosen.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="user_anyone_only"]</code></td>
                    </tr>
                    <tr>
                        <td><code>service_select</code></td>
                        <td><?php esc_html_e('Displays the services list with duration and descriptions.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="service_select"]</code></td>
                    </tr>
                    <tr>
                        <td><code>service</code></td>
                        <td><?php esc_html_e('Preselects a specific service by slug and displays the selected service details.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="service" service="your-service-slug"]</code></td>
                    </tr>
                    <tr>
                        <td><code>service_duration</code></td>
                        <td><?php esc_html_e('Hidden helper that writes the selected service duration (seconds) into elementor_prop or field_prop.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="service_duration" elementor_prop="duration_field_id"]</code></td>
                    </tr>
                    <tr>
                        <td><code>time</code></td>
                        <td><?php esc_html_e('Shows the calendar and available time slots.', $text_domain); ?></td>
                        <td><code>[csa_appointment_field type="time"]</code></td>
                    </tr>
                </tbody>
            </table>

            <p><em><?php esc_html_e('Note: The legacy type "services" is still accepted and maps to "service_select".', $text_domain); ?></em></p>

            <h2><?php esc_html_e('Attributes', $text_domain); ?></h2>
            <table class="widefat striped" style="max-width: 1200px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Attribute', $text_domain); ?></th>
                        <th><?php esc_html_e('Used with', $text_domain); ?></th>
                        <th><?php esc_html_e('Description', $text_domain); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>type</code></td>
                        <td><?php esc_html_e('All', $text_domain); ?></td>
                        <td><?php esc_html_e('Controls which UI component is rendered: user, user_select, user_anyone, user_anyone_only, service_select, service, service_duration, or time. Invalid values return an error message.', $text_domain); ?></td>
                    </tr>
                    <tr>
                        <td><code>user</code></td>
                        <td><code>type="user"</code></td>
                        <td><?php esc_html_e('Username to preselect. Required for the user type.', $text_domain); ?></td>
                    </tr>
                    <tr>
                        <td><code>service</code></td>
                        <td><code>type="service"</code></td>
                        <td><?php esc_html_e('Service slug to preselect. Found on the Services admin page.', $text_domain); ?></td>
                    </tr>
                    <tr>
                        <td><code>label</code></td>
                        <td><?php esc_html_e('All', $text_domain); ?></td>
                        <td><?php esc_html_e('Optional label displayed above the field.', $text_domain); ?></td>
                    </tr>
                    <tr>
                        <td><code>elementor_prop</code></td>
                        <td><?php esc_html_e('All', $text_domain); ?></td>
                        <td><?php esc_html_e('Elementor Pro field ID to sync the selected value into (hidden input).', $text_domain); ?></td>
                    </tr>
                    <tr>
                        <td><code>field_prop</code></td>
                        <td><?php esc_html_e('All', $text_domain); ?></td>
                        <td><?php esc_html_e('Custom field ID to sync into for non-Elementor forms.', $text_domain); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e('Examples', $text_domain); ?></h2>
            <ul>
                <li><code>[csa_appointment_field type="user_select"]</code></li>
                <li><code>[csa_appointment_field type="user_anyone"]</code></li>
                <li><code>[csa_appointment_field type="user_anyone_only"]</code></li>
                <li><code>[csa_appointment_field type="service_select" elementor_prop="service_field_id"]</code></li>
                <li><code>[csa_appointment_field type="service" service="intro-consult" elementor_prop="service_field_id"]</code></li>
                <li><code>[csa_appointment_field type="service_duration" elementor_prop="duration_field_id"]</code></li>
                <li><code>[csa_appointment_field type="time" elementor_prop="appointment_field_id"]</code></li>
            </ul>
        </div>
        <?php
    }
}
