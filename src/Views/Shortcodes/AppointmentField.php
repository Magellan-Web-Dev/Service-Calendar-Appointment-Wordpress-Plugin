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
        $field_prop = isset($context['field_prop']) ? $context['field_prop'] : '';
        $services = isset($context['services']) && is_array($context['services']) ? $context['services'] : [];
        $service = isset($context['service']) && is_array($context['service']) ? $context['service'] : [];
        $username = isset($context['user']) ? $context['user'] : '';
        $user_full_name = isset($context['user_full_name']) ? $context['user_full_name'] : '';
        $users = isset($context['users']) && is_array($context['users']) ? $context['users'] : [];
        $anyone_value = isset($context['anyone_value']) ? $context['anyone_value'] : '';
        $auto_anyone = !empty($context['auto_anyone']);
        $hide_user_list = !empty($context['hide_user_list']);

        $hidden_style = (in_array($type, ['user', 'service'], true) || $hide_user_list) ? ' style="display:none;"' : '';
        $html = '<div class="csa-appointment-field" data-type="' . esc_attr($type) . '"' . $hidden_style;
        if (!empty($elementor_prop)) {
            $html .= ' data-elementor-prop="' . esc_attr($elementor_prop) . '"';
        }
        if (!empty($field_prop)) {
            $html .= ' data-field-prop="' . esc_attr($field_prop) . '"';
        }
        if (!empty($label)) {
            $html .= ' data-label="' . esc_attr($label) . '"';
        }
        if (!empty($username)) {
            $html .= ' data-user="' . esc_attr($username) . '"';
        }
        if (!empty($user_full_name)) {
            $html .= ' data-user-full-name="' . esc_attr($user_full_name) . '"';
        }
        if (!empty($anyone_value)) {
            $html .= ' data-anyone-value="' . esc_attr($anyone_value) . '"';
        }
        if ($auto_anyone) {
            $html .= ' data-auto-anyone="1"';
        }
        if ($type === 'service' && !empty($service)) {
            $service_title = isset($service['title']) ? $service['title'] : '';
            $service_slug = isset($service['slug']) ? $service['slug'] : '';
            $service_duration = isset($service['duration_seconds']) ? (int) $service['duration_seconds'] : 0;
            if ($service_title !== '') {
                $html .= ' data-service-title="' . esc_attr($service_title) . '"';
                $html .= ' data-service-slug="' . esc_attr($service_slug) . '"';
                $html .= ' data-service-duration="' . esc_attr((string) $service_duration) . '"';
            }
        }
        $html .= '>';

        if (!empty($label) && !in_array($type, ['user', 'service'], true)) {
            $html .= '<div class="csa-appointment-main-label">' . esc_html($label) . '</div>';
        }

        if ($type === 'user' && !empty($username)) {
            $html .= '<input type="hidden" name="csa_user" value="' . esc_attr($username) . '" />';
            $html .= '<input type="hidden" name="form_fields[csa_user]" value="' . esc_attr($username) . '" />';
            if (!empty($elementor_prop)) {
                $prop_key = 'csa-field-' . $elementor_prop;
                $prop_value = 'csa::user --> ' . $username;
                if ($user_full_name !== '') {
                    $prop_value .= ' --> ' . $user_full_name;
                }
                $html .= '<input type="hidden" name="' . esc_attr($prop_key) . '" id="' . esc_attr($prop_key) . '" class="csa-elementor-prop-hidden" value="' . esc_attr($prop_value) . '" />';
            }
        }

        if ($type === 'user') {
            $html .= '</div>';
            return $html;
        }

        if ($type === 'service') {
            $service_title = isset($service['title']) ? $service['title'] : '';
            if ($service_title !== '') {
                $html .= '<input type="hidden" name="appointment_service" value="' . esc_attr($service_title) . '" />';
                $html .= '<input type="hidden" name="form_fields[appointment_service]" value="' . esc_attr($service_title) . '" />';
            }
            if (!empty($elementor_prop)) {
                $prop_key = 'csa-field-' . $elementor_prop;
                $html .= '<input type="hidden" name="' . esc_attr($prop_key) . '" id="' . esc_attr($prop_key) . '" class="csa-elementor-prop-hidden" value="" />';
            }
            $html .= '</div>';
            return $html;
        }

        if ($type === 'service_select' || $type === 'services') {
            $select_label = $label !== '' ? $label : esc_html__('Select', $text_domain);
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
            $html .= '<select class="csa-service-select"><option value="" disabled selected>' . esc_html($select_label) . '</option>';
            foreach ($services as $service) {
                $title = isset($service['title']) ? $service['title'] : '';
                if ($title === '') {
                    continue;
                }
                $html .= '<option value="' . esc_attr($title) . '">' . esc_html($title) . '</option>';
            }
            $html .= '</select>';
            if (!empty($elementor_prop)) {
                $prop_key = 'csa-field-' . $elementor_prop;
                $html .= '<input type="hidden" name="' . esc_attr($prop_key) . '" id="' . esc_attr($prop_key) . '" class="csa-elementor-prop-hidden" value="" />';
            }
            $html .= '</div>';
        } elseif ($type === 'user_select') {
            if (empty($users)) {
                $html .= '<div class="csa-appointment-error">' . esc_html__('No users available for booking.', $text_domain) . '</div>';
                $html .= '</div>';
                return $html;
            }
            $select_label = $label !== '' ? $label : esc_html__('Select', $text_domain);
            $html .= '<div class="csa-appointment-users">';
            $html .= '<ul class="csa-user-list">';
            foreach ($users as $index => $user) {
                $user_label = isset($user['label']) ? $user['label'] : '';
                $user_name = isset($user['username']) ? $user['username'] : '';
                $user_full = isset($user['full_name']) ? $user['full_name'] : $user_label;
                if ($user_label === '' || $user_name === '') {
                    continue;
                }
                $input_id = 'csa-user-' . intval($index) . '-' . wp_rand(1000, 9999);
                $html .= '<li class="csa-user-item" data-username="' . esc_attr($user_name) . '" data-full-name="' . esc_attr($user_full) . '">';
                $html .= '<input type="radio" class="csa-user-radio" name="csa_user_select" id="' . esc_attr($input_id) . '" value="' . esc_attr($user_name) . '" hidden />';
                $html .= '<h4>' . esc_html($user_label) . '</h4>';
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '<select class="csa-user-select"><option value="" disabled selected>' . esc_html($select_label) . '</option>';
            foreach ($users as $user) {
                $user_label = isset($user['label']) ? $user['label'] : '';
                $user_name = isset($user['username']) ? $user['username'] : '';
                $user_full = isset($user['full_name']) ? $user['full_name'] : $user_label;
                if ($user_label === '' || $user_name === '') {
                    continue;
                }
                $html .= '<option value="' . esc_attr($user_name) . '" data-full-name="' . esc_attr($user_full) . '">' . esc_html($user_label) . '</option>';
            }
            $html .= '</select>';
            $html .= '<input type="hidden" name="csa_user" class="csa-user-hidden" value="" />';
            $html .= '<input type="hidden" name="form_fields[csa_user]" class="csa-user-hidden-form" value="" />';
            $html .= '<input type="hidden" name="csa_username" class="csa-user-hidden-username" value="" />';
            $html .= '<input type="hidden" name="form_fields[csa_username]" class="csa-user-hidden-username-form" value="" />';
            if (!empty($elementor_prop)) {
                $prop_key = 'csa-field-' . $elementor_prop;
                $html .= '<input type="hidden" name="' . esc_attr($prop_key) . '" id="' . esc_attr($prop_key) . '" class="csa-elementor-prop-hidden" value="" />';
            }
            $html .= '</div>';
        } else {
            $select_label = $label !== '' ? $label : esc_html__('Select', $text_domain);
            $html .= '<div class="csa-appointment-calendar">';
            $html .= '<div class="csa-calendar-widget"></div>';
            $html .= '<div class="csa-calendar-time">';
            $html .= '<div class="csa-time-notification">Select A Date To See Available Times.</div>';
            $html .= '<div class="csa-field csa-field-time">';
            $html .= '<ul class="csa-appointment-time-list"><li class="csa-time-placeholder">' . esc_html__('Select a day first', $text_domain) . '</li></ul>';
            $html .= '<select name="appointment_time_select" class="csa-appointment-time-select"><option value="" disabled selected>' . esc_html($select_label) . '</option></select>';
            $html .= '</div></div>';
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
