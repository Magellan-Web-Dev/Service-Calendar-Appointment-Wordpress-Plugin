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
        $calendar_cells = isset($context['calendar_cells']) ? $context['calendar_cells'] : array();
        $month = isset($context['month']) ? (int) $context['month'] : 0;
        $year = isset($context['year']) ? (int) $context['year'] : 0;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>
            <div class="notice notice-info inline"><p style="font-weight: 600"><?php esc_html_e('Note: Appointments older than 3 months are automatically deleted daily by the system.', $text_domain); ?></p></div>
            <p style="width: min(120ch, 100%);"><?php esc_html_e('To add an appointment selector to a form or page, insert the shortcode [elementor_appointment_field] inside an HTML/Raw HTML field (Elementor) or any content area that supports shortcodes.  Attributes of "label" for a main label and "prop" for a hidden prop field are supported.', $text_domain); ?></p>

            <div class="csa-calendar-wrapper">
                <div class="csa-calendar-header">
                    <button class="button" id="csa-prev-month">&larr; <?php esc_html_e('Previous', $text_domain); ?></button>
                    <h2 id="csa-current-month"><?php echo esc_html($month_label); ?></h2>
                    <button class="button" id="csa-next-month"><?php esc_html_e('Next', $text_domain); ?> &rarr;</button>
                </div>

                <div id="csa-calendar-container">
                    <?php CalendarGrid::render(array(
                        'text_domain' => $text_domain,
                        'calendar_cells' => $calendar_cells,
                        'month' => $month,
                        'year' => $year,
                    )); ?>
                </div>
            </div>

            <div class="csa-weekly-availability-section">
                <h2><?php esc_html_e('Weekly Availability', $text_domain); ?></h2>
                <p><?php esc_html_e('Set days of week and time slots (30-minute increments) when you are generally available. Leave blank to mark times as unavailable by default.', $text_domain); ?></p>
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
                        <h3 id="csa-modal-date"></h3>
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
