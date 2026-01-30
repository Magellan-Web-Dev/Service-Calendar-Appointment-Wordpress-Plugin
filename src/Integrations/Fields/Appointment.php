<?php
/**
 * Appointment combined field for Elementor forms
 *
 * @package CalendarServiceAppointmentsForm\Integrations\Fields
 */

namespace CalendarServiceAppointmentsForm\Integrations\Fields;

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
    public const LABEL = 'Appointment';

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
        // Visible date input (readonly) and time select; actual saved values use hidden inputs named appointment_date and appointment_time
        $form->add_render_attribute(
            'input' . $item_index,
            array(
                'class' => 'elementor-field-textual elementor-date-field csa-appointment-date-field',
                'type' => 'text',
                'name' => $this->get_control_name($item) . '_display',
                'id' => $this->get_control_id($item) . '_display',
                'readonly' => 'readonly',
                'placeholder' => __('Select a date', self::TEXT_DOMAIN),
            )
        );

        if (!empty($item['required'])) {
            $form->add_render_attribute('input' . $item_index, 'required', 'required');
        }

        // Date visible
        echo '<input ' . $form->get_render_attribute_string('input' . $item_index) . ' />';
        // Hidden inputs that will be saved with known keys
        echo '<input type="hidden" class="csa-selected-date" name="' . self::FIELD_APPOINTMENT_DATE . '">';

        // Time select (will be populated by frontend JS)
        echo '<select class="csa-appointment-time-field" name="' . self::FIELD_APPOINTMENT_TIME . '">';
        echo '<option value="">' . esc_html__('Select a time', self::TEXT_DOMAIN) . '</option>';
        echo '</select>';
    }

    public function validation($field, $record, $ajax_handler) {
        // The processing hook will perform availability validation; here ensure values are present if required
        // Find values in raw submission
        $fields = $record->get('fields');
        $date = '';
        $time = '';
        foreach ($fields as $f) {
            if (isset($f['name']) && $f['name'] === self::FIELD_APPOINTMENT_DATE) {
                $date = $f['value'];
            }
            if (isset($f['name']) && $f['name'] === self::FIELD_APPOINTMENT_TIME) {
                $time = $f['value'];
            }
        }

        if (!empty($field['required']) && (empty($date) || empty($time))) {
            $ajax_handler->add_error($field['id'], __('Please select a date and time for your appointment.', self::TEXT_DOMAIN));
        }
    }
}
