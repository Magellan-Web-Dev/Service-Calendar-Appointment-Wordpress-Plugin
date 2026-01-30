<?php
/**
 * Admin calendar page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class CalendarPage {

    /**
     * Render the admin calendar page.
     *
     * @param array $context
     * @return void
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : '';
        $month_label = isset($context['month_label']) ? $context['month_label'] : '';
        $calendar_cells = isset($context['calendar_cells']) ? $context['calendar_cells'] : [];
        $month = isset($context['month']) ? (int) $context['month'] : 0;
        $year = isset($context['year']) ? (int) $context['year'] : 0;
        $timezone = isset($context['timezone']) ? $context['timezone'] : 'America/New_York';
        $timezone_label = isset($context['timezone_label']) ? $context['timezone_label'] : $timezone;
        $timezone_options = isset($context['timezone_options']) && is_array($context['timezone_options'])
            ? $context['timezone_options']
            : [];

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>
            <div class="notice notice-info inline"><p style="font-weight: 600"><?php esc_html_e('Note: Appointments older than 3 months are automatically deleted daily by the system.', $text_domain); ?></p></div>
            <p style="width: min(120ch, 100%);"><?php esc_html_e('To add appointment fields, use the shortcode [csa_appointment_field] inside an HTML/Raw HTML field (Elementor) or any content area that supports shortcodes. Use attributes type=\"services\" or type=\"time\" and elementor_prop=\"field_id\" for hidden field syncing.', $text_domain); ?></p>

            <div class="csa-calendar-wrapper">
                <div class="csa-calendar-header">
                    <button class="button" id="csa-prev-month">&larr; <?php esc_html_e('Previous', $text_domain); ?></button>
                    <h2 id="csa-current-month"><?php echo esc_html($month_label); ?></h2>
                    <button class="button" id="csa-next-month"><?php esc_html_e('Next', $text_domain); ?> &rarr;</button>
                </div>

                <div id="csa-calendar-container">
                    <?php CalendarGrid::render([
                        'text_domain' => $text_domain,
                        'calendar_cells' => $calendar_cells,
                        'month' => $month,
                        'year' => $year,
                    ]); ?>
                </div>
            </div>

            <div class="csa-timezone-section">
                <h2><?php esc_html_e('Time Zone', $text_domain); ?></h2>
                <p><?php esc_html_e('Select the time zone used for displaying and storing appointment times.', $text_domain); ?></p>
                <label for="csa-admin-timezone" class="screen-reader-text"><?php esc_html_e('Time Zone', $text_domain); ?></label>
                <select id="csa-admin-timezone">
                    <?php foreach ($timezone_options as $value => $label_option) :
                        $selected = ($timezone === $value) ? 'selected' : '';
                        ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($label_option); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-secondary" id="csa-save-timezone"><?php esc_html_e('Save Time Zone', $text_domain); ?></button>
            </div>

            <div class="csa-weekly-availability-section">
                <h2><?php echo esc_html__('Weekly Availability', $text_domain) . ' - ' . esc_html($timezone_label); ?></h2>
                <p><?php esc_html_e('Set days of week and time slots when you are generally available. Leave blank to mark times as unavailable by default.', $text_domain); ?></p>
                <div id="csa-weekly-availability"></div>
                <button class="button button-primary" id="csa-save-weekly-availability"><?php esc_html_e('Save Availability', $text_domain); ?></button>
            </div>

            <div class="csa-holiday-availability-section">
                <h2><?php esc_html_e('Holiday Availability', $text_domain); ?></h2>
                <p><?php esc_html_e('Select US holidays that should be available. Unchecked holidays will block all time slots for that day.', $text_domain); ?></p>
                <div id="csa-holiday-availability"></div>
                <button class="button button-primary" id="csa-save-holiday-availability"><?php esc_html_e('Save Holiday Availability', $text_domain); ?></button>
            </div>

            <div id="csa-day-detail-modal" style="display:none;">
                <div class="csa-modal-overlay"></div>
                <div class="csa-modal-content">
                    <div class="csa-modal-header">
                        <div class="csa-modal-title">
                            <h3 id="csa-modal-date"></h3>
                            <div id="csa-modal-timezone" class="csa-modal-timezone"></div>
                        </div>
                        <button class="csa-modal-close">&times;</button>
                    </div>
                    <div class="csa-modal-body">
                        <div id="csa-time-slots"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
