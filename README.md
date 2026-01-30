Calendar Service Appointments Form — README

Overview
- Adds an admin Appointment Calendar where admins set weekly availability (per weekday, 1-hour slots between 06:00–17:00) and can manually block/unblock specific dates/times.
- Provides a shortcode [csa_appointment_field] with type=\"services\" and type=\"time\" modes for service selection and time booking.
- Server-side validation prevents double-booking and enforces admin availability.

Shortcode usage
- Insert `[csa_appointment_field type=\"services\" elementor_prop=\"service_field_id\"]` and `[csa_appointment_field type=\"time\" elementor_prop=\"appointment_field_id\"]` inside an Elementor HTML widget (or any content area that accepts shortcodes).
- The shortcode outputs three labeled selects:
  - `appointment_month` (value: YYYY-MM)
  - `appointment_day` (value: DD)
  - `appointment_time` (value: HH:MM)
  - plus a hidden `appointment_date` input (value: YYYY-MM-DD)
- On form submission (e.g., Elementor Pro Form or custom handler), include `appointment_date` and `appointment_time` in the data sent to the server. The plugin hooks into Elementor Pro form processing to validate these fields when present.

AJAX endpoints (used by the shortcode script)
- `csa_get_available_months` — returns next 12 months that have at least one available slot.
- `csa_get_available_days` — takes `month=YYYY-MM`, returns days in that month with available slots.
- `csa_get_available_times` — takes `date=YYYY-MM-DD`, returns available times (06:00–17:00) for that day.

Admin
- Open the plugin "Appointments" admin page to set weekly availability and manually block/unblock per-date slots.
- Weekly availability is stored in the WordPress options table (`csa_weekly_availability`). Manual overrides are in `csa_manual_overrides`.

Developer notes
- Elementor editor field registration was attempted but not reliably supported across versions; the shortcode approach is intentionally editor-agnostic.
- Server-side validation occurs in `src/Integrations/Elementor.php::process_appointment_form()` and will reject submissions where the selected slot is blocked or already booked.
- Default allowed hours are 06:00–17:00 inclusive for start times.

Files of interest
- `src/Admin/Calendar.php` — admin calendar and UI
- `src/Core/Database.php` — persistence + helpers
- `src/Ajax/Handlers.php` — AJAX endpoints and validation logic
- `src/Shortcodes.php` — shortcode registration
- `assets/js/appointment-shortcode.js` — frontend code for populating the selects

Testing
- Enable WP_DEBUG and WP_DEBUG_LOG if you need logs.
- Use the admin calendar to set availability, then add the shortcode to a page, open that page, choose month/day/time, and submit with a form that includes hidden `appointment_date` and `appointment_time` values (Elementor Pro Form will be validated automatically if present).

If you'd like, I can now:
- Remove the unused Elementor field class files, or
- Provide step-by-step instructions to add the shortcode to an Elementor page and test a booking flow.
# Service-Calendar-Appointment-Wordpress-Plugin
