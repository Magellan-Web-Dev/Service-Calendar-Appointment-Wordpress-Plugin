<?php
declare(strict_types=1);
/**
 * Base AJAX handler with shared constants and helpers.
 *
 * @package CalendarServiceAppointmentsForm\Ajax
 */

namespace CalendarServiceAppointmentsForm\Ajax\Handlers;

use CalendarServiceAppointmentsForm\Ajax\Concerns\AppointmentHelpers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseHandler {

    /**
     * Plugin text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'calendar-service-appointments-form';

    /**
     * AJAX nonce action for admin requests
     *
     * @var string
     */
    public const NONCE_ACTION = 'csa_admin_nonce';

    /**
     * Capability required for admin actions
     *
     * @var string
     */
    public const CAPABILITY = 'manage_options';

    use AppointmentHelpers;

    /**
     * Capability accessor for helpers.
     *
     * @return string
     */
    protected function get_capability(): string {
        return self::CAPABILITY;
    }
}
