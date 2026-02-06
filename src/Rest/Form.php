<?php
declare(strict_types=1);
/**
 * REST API endpoint for custom form hooks
 *
 * @package CalendarServiceAppointmentsForm\Rest
 */

namespace CalendarServiceAppointmentsForm\Rest;

if (!defined('ABSPATH')) {
    exit;
}

class Form {

    private const REST_NAMESPACE = 'csa/v1';
    private const ROUTE_SUBMIT = '/form/submit';

    /**
     * @var Form|null
     */
    private static $instance = null;

    /**
     * @return Form
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::ROUTE_SUBMIT, [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'submit_form'],
            'permission_callback' => [$this, 'authorize_request'],
        ]);
    }

    /**
     * Allow custom access control for the endpoint.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function authorize_request(\WP_REST_Request $request): bool {
        return (bool) apply_filters('csa_form_api_permission', true, $request);
    }

    /**
     * Handle custom form submission.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function submit_form(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $fields = [];
        $raw_fields = [];
        if (isset($params['fields']) && is_array($params['fields'])) {
            $fields = $params['fields'];
        }
        if (isset($params['raw_fields']) && is_array($params['raw_fields'])) {
            $raw_fields = $params['raw_fields'];
        }
        if (empty($fields) && is_array($params)) {
            $fields = $params;
        }
        if (empty($raw_fields)) {
            $raw_fields = $fields;
        }

        $payload = $this->build_validation_payload($fields, $raw_fields);
        $payload = apply_filters('csa_form_validation', $payload, $request);

        if (!$payload->validation) {
            $messages = $payload->get_error_messages();
            $message = !empty($messages) ? implode(' ', $messages) : __('Validation failed.', 'calendar-service-appointments-form');
            return new \WP_Error('csa_form_validation_failed', $message, [
                'status' => 400,
                'messages' => $messages,
            ]);
        }

        $record_payload = (object) [
            'data' => $payload->fields,
            'raw_fields' => $payload->raw_fields,
        ];
        do_action('csa_form_new_record', $record_payload, $request);

        return rest_ensure_response([
            'success' => true,
        ]);
    }

    /**
     * Build validation payload for csa_form_validation filter.
     *
     * @param array $fields
     * @param array $raw_fields
     * @return object
     */
    private function build_validation_payload(array $fields, array $raw_fields): object {
        return new class($fields, $raw_fields) {
            public $validation = true;
            private $fields = [];
            private $raw_fields = [];
            private $errors = [];

            public function __construct(array $fields, array $raw_fields) {
                $this->fields = is_array($fields) ? $fields : [];
                $this->raw_fields = is_array($raw_fields) ? $raw_fields : [];
            }

            public function __get(string $name): mixed {
                if ($name === 'fields') {
                    return $this->fields;
                }
                if ($name === 'raw_fields') {
                    return $this->raw_fields;
                }
                return null;
            }

            public function add_error_message(string $message): void {
                $msg = is_string($message) ? trim($message) : '';
                if ($msg === '') {
                    return;
                }
                $this->validation = false;
                $this->errors[] = $msg;
            }

            public function get_error_messages(): array {
                return $this->errors;
            }
        };
    }
}
