<?php
/**
 * AppointmentDate field class
 *
 * @package CalendarServiceAppointmentsForm\Integrations\Elementor\Fields
 */

namespace CalendarServiceAppointmentsForm\Integrations\Elementor\Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom appointment date field for Elementor forms
 */
class AppointmentDate extends \ElementorPro\Modules\Forms\Fields\Field_Base {

    /**
     * Field type identifier
     *
     * @var string
     */
    public const TYPE = 'csa_appointment_date';

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
    public const LABEL = 'Appointment Date';

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
                'class' => 'elementor-field-textual elementor-date-field csa-appointment-date-field',
                'type' => 'text',
                'name' => $this->get_control_name($item),
                'id' => $this->get_control_id($item),
                'readonly' => 'readonly',
                'placeholder' => __('Select a date', self::TEXT_DOMAIN),
            ]
        );

        if ($item['required']) {
            $form->add_render_attribute('input' . $item_index, 'required', 'required');
        }

        ?>
        <input <?php echo $form->get_render_attribute_string('input' . $item_index); ?>>
        <input type="hidden" class="csa-selected-date" name="<?php echo $this->get_control_name($item); ?>_hidden">
        <div class="csa-date-picker-container" style="display:none;">
            <div class="csa-calendar-widget"></div>
        </div>
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
            $ajax_handler->add_error($field['id'], __('Please select an appointment date.', self::TEXT_DOMAIN));
            return;
        }

        // Detailed availability checks are performed server-side in the form processing step
    }
}
