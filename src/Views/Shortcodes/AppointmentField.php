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
        $type = isset($context['type']) ? $context['type'] : 'time';
        $elementor_prop = isset($context['elementor_prop']) ? $context['elementor_prop'] : '';
        $services = isset($context['services']) && is_array($context['services']) ? $context['services'] : [];

        $html = '<div class="csa-appointment-field" data-type="' . esc_attr($type) . '"';
        if (!empty($elementor_prop)) {
            $html .= ' data-elementor-prop="' . esc_attr($elementor_prop) . '"';
        }
        if (!empty($label)) {
            $html .= ' data-label="' . esc_attr($label) . '"';
        }
        $html .= '>';

        if (!empty($label)) {
            $html .= '<div class="csa-appointment-main-label">' . esc_html($label) . '</div>';
        }

        if ($type === 'services') {
            $html .= '<div class="csa-appointment-services">';
            $html .= '<ul class="csa-service-list">';
            foreach ($services as $index => $service) {
                $title = isset($service['title']) ? $service['title'] : '';
                if ($title === '') {
                    continue;
                }
                $sub_heading = isset($service['sub_heading']) ? $service['sub_heading'] : '';
                $description = isset($service['description']) ? $service['description'] : '';
                $duration_label = isset($service['duration_label']) ? $service['duration_label'] : '';
                $duration_seconds = isset($service['duration_seconds']) ? (int) $service['duration_seconds'] : 0;
                $input_id = 'csa-service-' . intval($index) . '-' . wp_rand(1000, 9999);

                $html .= '<li class="csa-service-item" data-title="' . esc_attr($title) . '" data-duration-seconds="' . esc_attr((string) $duration_seconds) . '">';
                $html .= '<input type="radio" class="csa-service-radio" name="appointment_service" id="' . esc_attr($input_id) . '" value="' . esc_attr($title) . '" hidden />';
                $html .= '<h4>' . esc_html($title) . '</h4>';
                if (!empty($sub_heading)) {
                    $html .= '<h5>' . esc_html($sub_heading) . '</h5>';
                }
                if (!empty($duration_label)) {
                    $html .= '<p class="csa-service-duration">' . esc_html($duration_label) . '</p>';
                }
                if (!empty($description)) {
                    $html .= '<p class="csa-service-description">' . esc_html($description) . '</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
            if (!empty($elementor_prop)) {
                $prop_key = 'csa-field-' . $elementor_prop;
                $html .= '<input type="hidden" name="' . esc_attr($prop_key) . '" id="' . esc_attr($prop_key) . '" class="csa-elementor-prop-hidden" value="" />';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="csa-appointment-calendar">';
            $html .= '<div class="csa-calendar-widget"></div>';
            $html .= '<div class="csa-time-notification">Select A Service And Date To See Available Times.</div>';
            $html .= '<div class="csa-field csa-field-time">';
            $html .= '<label>' . esc_html__('Time', $text_domain) . '</label>';
            $html .= '<select name="appointment_time_select" class="csa-appointment-time-select"><option value="">' . esc_html__('Select a day first', $text_domain) . '</option></select>';
            $html .= '</div>';
            $html .= '<input type="hidden" name="appointment_date" class="csa-appointment-date-hidden" value="" />';
            $html .= '<input type="hidden" name="appointment_time" class="csa-appointment-time-hidden" value="" />';
            if (!empty($elementor_prop)) {
                $prop_key = 'csa-field-' . $elementor_prop;
                $html .= '<input type="hidden" name="' . esc_attr($prop_key) . '" id="' . esc_attr($prop_key) . '" class="csa-appointment-composite-hidden" value="" />';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
