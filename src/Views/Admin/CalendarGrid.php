<?php
/**
 * Admin calendar grid view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class CalendarGrid {

    /**
     * Render the calendar table.
     *
     * @param array $context
     * @return void
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $calendar_cells = isset($context['calendar_cells']) ? $context['calendar_cells'] : array();
        $month = isset($context['month']) ? (int) $context['month'] : 0;
        $year = isset($context['year']) ? (int) $context['year'] : 0;
        $day_labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

        ?>
        <table class="csa-calendar" data-month="<?php echo esc_attr($month); ?>" data-year="<?php echo esc_attr($year); ?>">
            <thead>
                <tr>
                    <?php foreach ($day_labels as $label) : ?>
                        <th><?php echo esc_html__($label, $text_domain); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($calendar_cells as $week) : ?>
                    <tr>
                        <?php foreach ($week as $cell) : ?>
                            <?php if (empty($cell)) : ?>
                                <td class="csa-calendar-day empty"></td>
                            <?php else : ?>
                                <td class="<?php echo esc_attr(implode(' ', $cell['classes'])); ?>" data-date="<?php echo esc_attr($cell['date']); ?>">
                                    <div class="day-number"><?php echo esc_html($cell['day']); ?></div>
                                    <?php if (!empty($cell['appointment_count'])) : ?>
                                        <div class="day-info appointments">
                                            <?php echo esc_html($cell['appointment_count']); ?>
                                            <?php echo esc_html__('appointment(s)', $text_domain); ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="day-info none"><?php esc_html_e('No appointments booked', $text_domain); ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($cell['blocked_count'])) : ?>
                                        <div class="day-info blocked">
                                            <?php echo esc_html($cell['blocked_count']); ?>
                                            <?php echo esc_html__('blocked', $text_domain); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
