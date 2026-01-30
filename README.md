Calendar Service Appointments Form — README

Overview
- Adds an admin Appointment Calendar with weekly availability, holiday availability, manual per-slot overrides, and a timezone selector.
- Adds a Services admin screen to manage service types (title, subheading, duration in seconds, description).
- Provides a shortcode [csa_appointment_field] with type=\"services\" and type=\"time\" for frontend booking.
- Stores appointment times in UTC and converts to the selected admin timezone for display.
- Validates bookings server-side to prevent overlaps and enforce availability.

Shortcode usage
- Insert both shortcodes on your page:
  - `[csa_appointment_field type=\"services\" elementor_prop=\"service_field_id\"]`
  - `[csa_appointment_field type=\"time\" elementor_prop=\"appointment_field_id\"]`
- Services output:
  - `<ul><li>` list with title, subheading, duration label, and description
  - each item includes a hidden radio input
- Time output:
  - custom calendar (month/day picker)
  - available time slots rendered as a list (`<ul><li>`) and a compact `<select>` (mobile)
  - hidden inputs: `appointment_date` (YYYY-MM-DD) and `appointment_time` (HH:MM)
- Elementor Pro syncing:
  - `elementor_prop` maps to your Elementor field id
  - plugin writes to a hidden input named `csa-field-{id}` and also to `form_fields[{id}]`
- When the service selection changes, the calendar/time selection resets to prevent overlaps.

AJAX endpoints (used by the shortcode script)
- `csa_get_available_months` — returns next 12 months that have at least one available slot.
- `csa_get_available_days` — takes `month=YYYY-MM`, returns days in that month with available slots.
- `csa_get_available_times` — takes `date=YYYY-MM-DD`, returns available times (06:00–17:00) for that day.

Admin
- Calendar submenu:
  - weekly availability and per-day overrides
  - holiday availability
  - timezone selection (US timezones)
  - day details modal with booked appointments and submission data
  - reschedule flow for booked appointments
- Services submenu:
  - manage service items and durations (stored in seconds)
- Weekly availability is stored in options (`csa_weekly_availability`).

Developer notes
- Server-side validation lives in `src/Integrations/Elementor.php` and blocks invalid or overlapping appointments.
- Times are stored in UTC and converted to the selected admin timezone on display.
- Same‑day bookings require a 2‑hour lead time (frontend availability).
- Default business hours are defined in `src/Admin/Calendar.php::get_business_hours()` (30‑minute increments).

Files of interest
- `src/Admin/Calendar.php` — admin calendar and UI
- `src/Core/Database.php` — persistence + helpers
- `src/Ajax/Handlers.php` — AJAX endpoints and validation logic
- `src/Shortcodes.php` — shortcode registration
- `assets/js/appointment-shortcode.js` — frontend calendar/time selection
- `src/Updates/GitHubUpdater.php` — GitHub release updater

Testing
- Enable WP_DEBUG and WP_DEBUG_LOG if you need logs.
 - Use the admin calendar to set availability and add services.
 - Add both shortcodes to a page, select a service, then choose a day/time and submit.
 - Elementor Pro submissions will be validated automatically if the hidden date/time fields are present.

Auto‑updates (GitHub)
- The plugin can check GitHub Releases for updates and prompt or auto‑update.
- Create a release tag like `v1.3.1`, then bump `CALENDAR_SERVICE_APPOINTMENTS_FORM_VERSION` to `1.3.1`.
# Service-Calendar-Appointment-Wordpress-Plugin
