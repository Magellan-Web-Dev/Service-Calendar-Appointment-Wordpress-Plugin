<?php
/**
 * Appointment shortcode view
 *
 * @package CalendarServiceAppointmentsForm\Views\Shortcodes
 */

namespace CalendarServiceAppointmentsForm\Views\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class AppointmentField {

    /**
     * Render appointment field HTML.
     *
     * @param array $context
     * @return string
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : '';
        $prop = isset($context['prop']) ? $context['prop'] : '';

        $html = '<div class="csa-appointment-field"';
        if (!empty($prop)) {
            $html .= ' data-prop="' . esc_attr($prop) . '"';
        }
        if (!empty($label)) {
            $html .= ' data-label="' . esc_attr($label) . '"';
        }
        $html .= '>';

        if (!empty($label)) {
            $html .= '<div class="csa-appointment-main-label">' . esc_html($label) . '</div>';
        }

        $html .= '<div class="csa-appointment-fields">';

        $html .= '<div class="csa-field csa-field-month">';
        $html .= '<label>' . esc_html__('Month', $text_domain) . '</label>';
        $html .= '<select name="appointment_month" class="csa-appointment-month"><option value="">' . esc_html__('Loading months...', $text_domain) . '</option></select>';
        $html .= '</div>';

        $html .= '<div class="csa-field csa-field-day">';
        $html .= '<label>' . esc_html__('Day', $text_domain) . '</label>';
        $html .= '<select name="appointment_day" class="csa-appointment-day"><option value="">' . esc_html__('Select a month first', $text_domain) . '</option></select>';
        $html .= '</div>';

        $html .= '<div class="csa-field csa-field-time">';
        $html .= '<label>' . esc_html__('Time', $text_domain) . '</label>';
        $html .= '<select name="appointment_time" class="csa-appointment-time"><option value="">' . esc_html__('Select a day first', $text_domain) . '</option></select>';
        $html .= '</div>';

        $html .= '<input type="hidden" name="appointment_date" class="csa-appointment-date-hidden" value="" />';

        if (!empty($prop)) {
            $prop_name = 'form_fields[' . $prop . ']';
            $html .= '<input type="hidden" name="' . esc_attr($prop_name) . '" class="csa-appointment-composite-hidden" value="" />';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
