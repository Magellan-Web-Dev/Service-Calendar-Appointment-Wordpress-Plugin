<?php
/**
 * Appointment combined field for Elementor forms
 *
 * @package CalendarServiceAppointmentsForm\Integrations\Elementor\Fields
 */

namespace CalendarServiceAppointmentsForm\Integrations\Elementor\Fields;

use CalendarServiceAppointmentsForm\Core\Database;
use CalendarServiceAppointmentsForm\Views\Shortcodes\AppointmentField;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders a combined Appointment field (date + time)
 */
class Appointment extends \ElementorPro\Modules\Forms\Fields\Field_Base {

    /**
     * Field type identifier
     *
     * @var string
     */
    public const TYPE = 'csa_appointment';

    /**
     * Plugin text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'calendar-service-appointments-form';

    /**
     * Field label
     *
     * @var string
     */
    public const LABEL = 'Service Appointment';

    /**
     * Hidden field name for appointment date
     *
     * @var string
     */
    public const FIELD_APPOINTMENT_DATE = 'appointment_date';

    /**
     * Hidden field name for appointment time
     *
     * @var string
     */
    public const FIELD_APPOINTMENT_TIME = 'appointment_time';

    public function get_type() {
        return self::TYPE;
    }

    public function get_name() {
        return __(self::LABEL, self::TEXT_DOMAIN);
    }

    public function render($item, $item_index, $form) {
        $label = '';
        if (!empty($item['field_label'])) {
            $label = (string) $item['field_label'];
        }

        $services = $this->get_services_for_render();

        echo '<div class="csa-elementor-appointment">';
        echo AppointmentField::render([
            'text_domain' => self::TEXT_DOMAIN,
            'type' => 'service_select',
            'label' => $label !== '' ? $label : self::LABEL,
            'elementor_prop' => '',
            'services' => $services,
        ]);

        echo AppointmentField::render([
            'text_domain' => self::TEXT_DOMAIN,
            'type' => 'time',
            'label' => '',
            'elementor_prop' => '',
            'services' => [],
        ]);
        echo '</div>';
    }

    public function validation($field, $record, $ajax_handler) {
        // The processing hook will perform availability validation; here ensure values are present if required
        // Find values in raw submission
        $fields = $record->get('fields');
        $date = '';
        $time = '';
        $service = '';
        foreach ($fields as $f) {
            if (isset($f['name']) && $f['name'] === self::FIELD_APPOINTMENT_DATE) {
                $date = $f['value'];
            }
            if (isset($f['name']) && $f['name'] === self::FIELD_APPOINTMENT_TIME) {
                $time = $f['value'];
            }
            if (isset($f['name']) && $f['name'] === 'appointment_service') {
                $service = $f['value'];
            }
        }

        if (!empty($field['required']) && (empty($date) || empty($time) || empty($service))) {
            $ajax_handler->add_error($field['id'], __('Please select a service, date, and time for your appointment.', self::TEXT_DOMAIN));
        }
    }

    public function content_template() {
        ?>
        <div class="csa-elementor-appointment">
            <div class="csa-appointment-field" data-type="service_select">
                <div class="csa-appointment-main-label"><?php echo esc_html__(self::LABEL, self::TEXT_DOMAIN); ?></div>
                <div class="csa-appointment-services">
                    <ul class="csa-service-list">
                        <li class="csa-service-item selected">
                            <h4><?php echo esc_html__('Service A', self::TEXT_DOMAIN); ?></h4>
                            <p class="csa-service-duration"><?php echo esc_html__('30 minutes', self::TEXT_DOMAIN); ?></p>
                        </li>
                        <li class="csa-service-item">
                            <h4><?php echo esc_html__('Service B', self::TEXT_DOMAIN); ?></h4>
                            <p class="csa-service-duration"><?php echo esc_html__('1 hour', self::TEXT_DOMAIN); ?></p>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="csa-appointment-field" data-type="time">
                <div class="csa-appointment-calendar">
                    <div class="csa-calendar-widget"></div>
                    <div class="csa-calendar-time">
                        <div class="csa-time-notification"><?php echo esc_html__('Select A Service And Date To See Available Times.', self::TEXT_DOMAIN); ?></div>
                        <div class="csa-field csa-field-time">
                            <ul class="csa-appointment-time-list">
                                <li class="csa-time-placeholder"><?php echo esc_html__('Select a day first', self::TEXT_DOMAIN); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Fetch services for rendering in Elementor.
     *
     * @return array
     */
    private function get_services_for_render() {
        $db = Database::get_instance();
        $stored = $db->get_services();
        $labels = $this->get_service_duration_labels();
        $services = [];

        foreach ($stored as $service) {
            if (!is_array($service)) {
                continue;
            }
            $title = isset($service['title']) ? sanitize_text_field($service['title']) : '';
            if ($title === '') {
                continue;
            }
            $sub_heading = isset($service['sub_heading']) ? sanitize_text_field($service['sub_heading']) : '';
            $description = isset($service['description']) ? sanitize_textarea_field($service['description']) : '';
            $duration_raw = isset($service['duration']) ? (string) $service['duration'] : '';
            $duration_seconds = ctype_digit($duration_raw) ? (int) $duration_raw : 0;
            $duration_label = '';
            if ($duration_seconds > 0 && isset($labels[$duration_raw])) {
                $duration_label = $labels[$duration_raw];
            } elseif ($duration_raw !== '') {
                $duration_label = $duration_raw;
            }
            $services[] = [
                'title' => $title,
                'sub_heading' => $sub_heading,
                'description' => $description,
                'duration_seconds' => $duration_seconds,
                'duration_label' => $duration_label,
            ];
        }

        return $services;
    }

    /**
     * Map duration seconds to labels.
     *
     * @return array
     */
    private function get_service_duration_labels() {
        return [
            '900' => '15 minutes',
            '1800' => '30 minutes',
            '2700' => '45 minutes',
            '3600' => '1 hour',
            '4500' => '1 hour and 15 minutes',
            '5400' => '1 hour and 30 minutes',
            '6300' => '1 hour and 45 minutes',
            '7200' => '2 hours',
            '8100' => '2 hours and 15 minutes',
            '9000' => '2 hours and 30 minutes',
            '9900' => '2 hours and 45 minutes',
            '10800' => '3 hours',
            '11700' => '3 hours and 15 minutes',
            '12600' => '3 hours and 30 minutes',
            '13500' => '3 hours and 45 minutes',
            '14400' => '4 hours',
        ];
    }
}
