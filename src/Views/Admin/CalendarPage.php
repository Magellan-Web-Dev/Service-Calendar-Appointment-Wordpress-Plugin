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
        $users = isset($context['users']) && is_array($context['users']) ? $context['users'] : [];
        $selected_user_id = isset($context['selected_user_id']) ? intval($context['selected_user_id']) : 0;
        $is_admin = !empty($context['is_admin']);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>
            <div class="notice notice-info inline"><p style="font-weight: 600"><?php esc_html_e('Note: Appointments older than 3 months are automatically deleted daily by the system.', $text_domain); ?></p></div>
            <p style="width: min(120ch, 100%);"><?php esc_html_e('To add appointment fields on the frontend, use the shortcode [csa_appointment_field] inside an HTML/Raw HTML field or any content area that supports shortcodes. Use attributes type="services" for services listings or type="time" for calendar and time slots.  An attribute of "user" or "user_select" is required to target a specific user. Use elementor_prop="field_id" for Elementor Pro form field syncing, or field_prop="field_id" to target a specific field by ID.', $text_domain); ?></p>

            <div class="csa-calendar-wrapper">
                <?php if ($is_admin && !empty($users)) : ?>
                    <form method="get" class="csa-user-filter" style="margin-bottom: 12px;">
                        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>" />
                        <input type="hidden" name="month" value="<?php echo esc_attr($month); ?>" />
                        <input type="hidden" name="year" value="<?php echo esc_attr($year); ?>" />
                        <label for="csa-user-filter-select" style="font-weight: 600;"><?php esc_html_e('View calendar for:', $text_domain); ?></label>
                        <select id="csa-user-filter-select" name="user_id">
                            <?php foreach ($users as $user) :
                                $uid = intval($user->ID);
                                $display = trim($user->first_name . ' ' . $user->last_name);
                                if ($display === '') {
                                    $display = $user->display_name;
                                }
                                $label = $display . ' (' . $user->user_login . ')';
                                $selected = $selected_user_id === $uid ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($uid); ?>" <?php echo esc_attr($selected); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button" type="submit"><?php esc_html_e('View', $text_domain); ?></button>
                    </form>
                <?php endif; ?>
                <div id="csa-reschedule-banner" class="csa-reschedule-banner" style="display:none;">
                    <span><?php esc_html_e('Select date to reassign appointment', $text_domain); ?></span>
                    <button class="button button-secondary" id="csa-reschedule-cancel"><?php esc_html_e('Cancel Reschedule', $text_domain); ?></button>
                </div>
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

            <div id="csa-custom-appointment-modal" style="display:none;">
                <div class="csa-modal-overlay"></div>
                <div class="csa-modal-content csa-custom-appointment-content">
                    <div class="csa-modal-header">
                        <div class="csa-modal-title">
                            <h3><?php esc_html_e('Schedule Custom Appointment', $text_domain); ?></h3>
                        </div>
                        <button class="csa-modal-close" type="button">&times;</button>
                    </div>
                    <div class="csa-modal-body">
                        <form class="csa-custom-appointment-form">
                            <div class="csa-custom-appointment-meta">
                                <div><strong><?php esc_html_e('Date:', $text_domain); ?></strong> <span id="csa-custom-appointment-date"></span></div>
                                <div><strong><?php esc_html_e('Start Time:', $text_domain); ?></strong> <span id="csa-custom-appointment-time"></span></div>
                                <div><strong><?php esc_html_e('Ends:', $text_domain); ?></strong> <span id="csa-custom-appointment-end"></span></div>
                            </div>

                            <label class="csa-custom-appointment-label" for="csa-custom-appointment-title">
                                <?php esc_html_e('Appointment Title', $text_domain); ?>
                            </label>
                            <input id="csa-custom-appointment-title" type="text" class="csa-custom-appointment-input" placeholder="<?php esc_attr_e('Custom appointment title', $text_domain); ?>" />

                            <label class="csa-custom-appointment-label" for="csa-custom-appointment-duration">
                                <?php esc_html_e('Duration', $text_domain); ?>
                            </label>
                            <select id="csa-custom-appointment-duration" class="csa-custom-appointment-input"></select>

                            <label class="csa-custom-appointment-label" for="csa-custom-appointment-notes">
                                <?php esc_html_e('Notes', $text_domain); ?>
                            </label>
                            <textarea id="csa-custom-appointment-notes" class="csa-custom-appointment-input" rows="4" placeholder="<?php esc_attr_e('Add internal notes for this appointment', $text_domain); ?>"></textarea>

                            <div id="csa-custom-appointment-warning" class="csa-custom-appointment-warning" style="display:none;"></div>

                            <div class="csa-custom-appointment-actions">
                                <button type="button" class="csa-btn csa-btn-view" id="csa-custom-appointment-submit">
                                    <?php esc_html_e('Schedule Appointment', $text_domain); ?>
                                </button>
                                <button type="button" class="csa-btn" id="csa-custom-appointment-cancel">
                                    <?php esc_html_e('Cancel', $text_domain); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
