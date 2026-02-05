Calendar Service Appointments Form — README

Overview
- Appointment booking system with an admin calendar, services, per‑user availability, and per‑user appointment data.
- Elementor Pro integration with server‑side validation and automatic appointment storage.
- Multisite support (master/child sync) with API key authentication.
- Frontend shortcodes for services, calendar/time, and user selection.
- Custom hooks for validation and new record events.
- Times are stored in UTC and displayed using the configured admin timezone.

Key features
- Admin calendar with day details, reschedule tools, and appointment drill‑down.
- Weekly availability, holiday availability, and manual per‑slot overrides (scoped per user).
- Services management (title, subheading, duration, description).
- User enable/disable management for the plugin (admins always enabled).
- Per‑user calendars: admins can switch users; non‑admins can only view their own.
- Multisite master/child: child sites read availability from master and post bookings back.
- REST endpoints for non‑Elementor forms (optional).
- GitHub release updater integration.

Shortcodes (frontend)
Use `[csa_appointment_field]` in a page, template, or HTML/Raw HTML block.

Required flow for user‑select bookings:
1) user_select or user_anyone → 2) service_select → 3) calendar/time

Shortcode types
- `type="user"`:
  - Hidden user field (pre‑selects a specific user).
  - Must include `user="username"` and the user must be enabled.
  - Example: `[csa_appointment_field type="user" user="bdorsey"]`
- `type="user_select"`:
  - Renders a user list + select dropdown that stay in sync.
  - Populates hidden user fields for booking and Elementor syncing.
  - If only one user is enabled, it auto‑selects and hides the list.
  - Example: `[csa_appointment_field type="user_select"]`
- `type="user_anyone"`:
  - Same as `user_select` but includes the “Anyone” option.
  - Example: `[csa_appointment_field type="user_anyone"]`
- `type="user_anyone_only"`:
  - Hidden version of `user_anyone`.
  - Auto‑selects “Anyone” and resolves a specific user when a time is chosen.
  - Example: `[csa_appointment_field type="user_anyone_only"]`
- `type="service_select"`:
  - Service list with duration labels and descriptions.
  - Example: `[csa_appointment_field type="service_select"]`
- `type="service"`:
  - Preselects a specific service by slug and shows its details.
  - Example: `[csa_appointment_field type="service" service="your-service-slug"]`
- Legacy: `type="services"` is still accepted and maps to `service_select`.
- `type="time"`:
  - Calendar + time slots (list + select).
  - Example: `[csa_appointment_field type="time"]`
Invalid `type` values return an error message.

Elementor Pro syncing (optional)
- Add `elementor_prop="field_id"` to any shortcode.
- The plugin writes to hidden inputs named `csa-field-{id}` and `form_fields[{id}]`.
- Example:
  - `[csa_appointment_field type="service_select" elementor_prop="service_field_id"]`
  - `[csa_appointment_field type="time" elementor_prop="appointment_field_id"]`

Frontend validation behavior
- When `type="user_select"` is used, services and calendar/time are disabled until a user is selected.
- Changing user resets service/time selections and reloads availability.
- Same‑day bookings require a 2‑hour lead time.

AJAX endpoints (frontend script)
- `csa_get_available_months` — months with at least one available day.
- `csa_get_available_days` — available days for a month (user + duration aware).
- `csa_get_available_times` — available times for a date (user + duration aware).

Admin screens
- Calendar:
  - Per‑user availability, holidays, overrides, and blocked slots.
  - Day details modal with appointment data + actions.
  - Reschedule flow.
  - Admins can switch users; non‑admins see only their own.
- Services:
  - Manage service catalog and durations.
- Users:
  - Enable/disable users for plugin access (admins always enabled).
- Multisite:
  - Admin‑only settings for master/child configuration.

Multisite (master/child)
- Child sites read availability from master and post bookings to master.
- Requests are authenticated via API key.
- Bookings on child are validated on master for availability.

REST API (optional)
- `GET /csa/v1/sync/available-times`
- `GET /csa/v1/sync/available-days`
- `GET /csa/v1/sync/services`
- `POST /csa/v1/sync/book`
- `POST /csa/v1/form/submit` (non‑Elementor form submissions; runs the same validation + storage)

Custom hooks
- Filter: `csa_form_validation`
  - Fires before Elementor validation.
  - Receives an object with:
    - `fields` (readonly associative array of field values)
    - `validation` (bool, default true)
    - `add_error_message($message)`
- Action: `csa_form_new_record`
  - Fires just before Elementor `new_record`.
  - Receives an object with `data` (associative array of submitted values).

Data model
- Appointments are stored in a custom table with `user_id`.
- Availability, holidays, and overrides are scoped per user.
- Blocked slots are per user (includes admin‑blocked times).
- Times are stored as UTC with a selected display timezone.

Files of interest
- `src/Admin/Calendar.php` — admin calendar and UI
- `src/Views/Admin/CalendarPage.php` — calendar view (user selector, etc.)
- `src/Views/Admin/UsersPage.php` — plugin user access management
- `src/Core/Database.php` — persistence + migrations
- `src/Ajax/Handlers.php` — AJAX endpoints and validation logic
- `src/Rest/Sync.php` — master/child sync endpoints
- `src/Rest/Form.php` — REST form submission endpoint
- `src/Integrations/Elementor.php` — Elementor integration + validation
- `src/Shortcodes.php` — shortcode registration
- `assets/js/appointment-shortcode.js` — frontend booking UI
- `src/Updates/GitHubUpdater.php` — GitHub release updater

Testing
- Enable WP_DEBUG and WP_DEBUG_LOG for detailed logs.
- Admin:
  - Set per‑user weekly and holiday availability.
  - Block/unblock slots and verify they are user‑scoped.
- Frontend:
  - Use `user_select` then `services` then `time`.
  - Submit via Elementor to verify validation + storage.
- Multisite:
  - Confirm child availability matches master and bookings post back to master.

Auto‑updates (GitHub)
- The plugin can check GitHub Releases for updates and prompt or auto‑update.
- Create a release tag like `v1.3.1`, then bump `CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION` to `1.3.1`.
# Service-Calendar-Appointment-Wordpress-Plugin
