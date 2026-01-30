<?php
/**
 * AppointmentTime field class
 *
 * @package CalendarServiceAppointmentsForm\Integrations\Fields
 */

namespace CalendarServiceAppointmentsForm\Integrations\Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom appointment time field for Elementor forms
 */
class AppointmentTime extends \ElementorPro\Modules\Forms\Fields\Field_Base {

    /**
     * Field type identifier
     *
     * @var string
     */
    public const TYPE = 'csa_appointment_time';

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
    public const LABEL = 'Appointment Time';

    /**
     * Get field type
     *
     * @return string
     */
    public function get_type() {
        return self::TYPE;
    }

    /**
     * Get field name
     *
     * @return string
     */
    public function get_name() {
        return __(self::LABEL, self::TEXT_DOMAIN);
    }

    /**
     * Render field
     *
     * @param array  $item Field settings.
     * @param int    $item_index Field index.
     * @param object $form Form object.
     * @return void
     */
    public function render($item, $item_index, $form) {
        $form->add_render_attribute(
            'input' . $item_index,
            [
                'class' => 'elementor-field-textual csa-appointment-time-field',
                'name' => $this->get_control_name($item),
                'id' => $this->get_control_id($item),
            ]
        );

        if ($item['required']) {
            $form->add_render_attribute('input' . $item_index, 'required', 'required');
        }

        ?>
        <select <?php echo $form->get_render_attribute_string('input' . $item_index); ?>>
            <option value=""><?php _e('Select a time', self::TEXT_DOMAIN); ?></option>
        </select>
        <?php
    }

    /**
     * Validate field
     *
     * @param array  $field Field data.
     * @param object $record Form record.
     * @param object $ajax_handler AJAX handler.
     * @return void
     */
    public function validation($field, $record, $ajax_handler) {
        if (empty($field['value'])) {
            $ajax_handler->add_error($field['id'], __('Please select an appointment time.', self::TEXT_DOMAIN));
        }
    }
}
