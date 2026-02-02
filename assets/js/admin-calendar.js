import { qs, qsa, delegate, on, postAjax, scrollToTopOnLoad, formatTime } from './utils.js';
import { AdminDayDetailsRenderer } from './render/admin/day-details-renderer.js';
import { AdminDaySummaryRenderer } from './render/admin/day-summary-renderer.js';
import { AdminHolidayAvailabilityRenderer } from './render/admin/holiday-availability-renderer.js';
import { AdminSubmissionFieldsRenderer } from './render/admin/submission-fields-renderer.js';
import { AdminWeeklyAvailabilityRenderer } from './render/admin/weekly-availability-renderer.js';

export class AdminCalendar {
    /**
     * Create an admin calendar controller.
     */
    constructor() {
        this.config = window.csaAdmin || null;
        this.calendar = qs('.csa-calendar');
        this.calendarWrapper = qs('.csa-calendar-wrapper');
        this.body = document.body;
        this.ajaxInFlight = 0;
        this.currentMonth = this.calendar ? parseInt(this.calendar.dataset.month, 10) : null;
        this.currentYear = this.calendar ? parseInt(this.calendar.dataset.year, 10) : null;
        this.minDate = new Date();
        this.minDate.setMonth(this.minDate.getMonth() - 3);
        this.minMonth = this.minDate.getMonth() + 1;
        this.minYear = this.minDate.getFullYear();
        this.weeklyBaseline = '';
        this.saveWeeklyButton = qs('#csa-save-weekly-availability');
        this.holidayBaseline = '';
        this.saveHolidayButton = qs('#csa-save-holiday-availability');
        this.keepLoading = false;
        this.rescheduleState = {
            active: false,
            apptId: null,
            durationSeconds: 0,
        };
        this.rescheduleBanner = qs('#csa-reschedule-banner');
        this.rescheduleCancel = qs('#csa-reschedule-cancel');
        this.currentDayDetails = null;
        this.daySlotMap = {};
        this.customBookingState = {
            active: false,
            date: '',
            time: '',
        };
    }

    /**
     * Initialize event bindings and render state.
     *
     * @returns {void}
     */
    init() {
        if (!this.config || !this.calendar) {
            return;
        }

        scrollToTopOnLoad();
        this.bindGlobalLoading();
        this.bindNavigation();
        this.bindCalendarDayClick();
        this.bindModalClose();
        this.bindAppointmentActions();
        this.bindBulkActions();
        this.bindWeeklyAvailability();
        this.bindHolidayAvailability();
        this.bindDayRendered();
        this.bindLoadSubmissionFields();
        this.bindTimezoneSelector();
        this.bindRescheduleControls();
        this.bindCustomAppointments();

        this.updateNavButtons();
        this.renderWeeklyAvailability();
        this.renderHolidayAvailability();
        this.weeklyBaseline = this.getWeeklySelectionSignature();
        this.updateWeeklySaveState();
        this.holidayBaseline = this.getHolidaySelectionSignature();
        this.updateHolidaySaveState();
    }

    /**
     * Bind global loading overlay handlers.
     *
     * @returns {void}
     */
    bindGlobalLoading() {
        document.addEventListener('csa:ajaxStart', () => {
            this.ajaxInFlight += 1;
            this.body.classList.add('loading-overlay');
        });

        document.addEventListener('csa:ajaxComplete', () => {
            this.ajaxInFlight = Math.max(this.ajaxInFlight - 1, 0);
            if (!this.ajaxInFlight && !this.keepLoading) {
                this.body.classList.remove('loading-overlay');
            }
        });
    }

    /**
     * Bind month navigation controls.
     *
     * @returns {void}
     */
    bindNavigation() {
        const prev = qs('#csa-prev-month');
        const next = qs('#csa-next-month');

        if (prev) {
            on(prev, 'click', () => {
                let nextMonth = this.currentMonth - 1;
                let nextYear = this.currentYear;
                if (nextMonth < 1) {
                    nextMonth = 12;
                    nextYear = this.currentYear - 1;
                }
                if (this.isBeforeMin(nextMonth, nextYear)) {
                    window.alert('Cannot navigate to dates older than 3 months.');
                    return;
                }
                this.currentMonth = nextMonth;
                this.currentYear = nextYear;
                this.loadCalendar(this.currentMonth, this.currentYear);
            });
        }

        if (next) {
            on(next, 'click', () => {
                this.currentMonth += 1;
                if (this.currentMonth > 12) {
                    this.currentMonth = 1;
                    this.currentYear += 1;
                }
                this.loadCalendar(this.currentMonth, this.currentYear);
            });
        }
    }

    /**
     * Bind calendar day click handler.
     *
     * @returns {void}
     */
    bindCalendarDayClick() {
        delegate(document, 'click', '.csa-calendar-day:not(.empty):not(.holiday-closed)', (event, target) => {
            const date = target.dataset.date;
            if (!date) {
                return;
            }
            this.showDayDetails(date);
        });
    }

    /**
     * Bind modal close handlers.
     *
     * @returns {void}
     */
    bindModalClose() {
        delegate(document, 'click', '.csa-modal-close, .csa-modal-overlay', () => {
            const modal = qs('#csa-day-detail-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    }

    /**
     * Bind appointment-related action handlers.
     *
     * @returns {void}
     */
    bindAppointmentActions() {
        delegate(document, 'click', '.csa-time-slot .csa-btn-view', (event, target) => {
            const slot = target.closest('.csa-time-slot');
            if (!slot) {
                return;
            }
            const list = qs('.csa-appointment-list', slot);
            if (!list) {
                return;
            }
            const isHidden = list.style.display === 'none' || !list.style.display;
            list.style.display = isHidden ? 'block' : 'none';
            target.classList.toggle('active');
        });

        delegate(document, 'click', '.csa-btn-delete', async (event, target) => {
            const apptId = target.dataset.apptId || null;
            const submissionId = target.dataset.submissionId || null;
            const date = target.dataset.date;
            const time = target.dataset.time;

            if (!window.confirm('Delete this appointment? This action cannot be undone.')) {
                return;
            }

            const payload = {
                action: 'csa_delete_appointment',
                nonce: this.config.nonce,
            };

            if (apptId) {
                payload.appt_id = apptId;
            } else if (submissionId) {
                payload.submission_id = submissionId;
                payload.date = date;
                payload.time = time;
            } else {
                window.alert('Unable to determine appointment id');
                return;
            }

            try {
                const response = await this.request(payload);
                if (response.success) {
                    this.showDayDetails(date);
                } else {
                    const message = response.data && response.data.message ? response.data.message : 'Error deleting appointment';
                    window.alert(message);
                }
            } catch (error) {
                window.alert('Error deleting appointment');
            }
        });

        delegate(document, 'click', '.csa-btn-reschedule', (event, target) => {
            const apptId = target.dataset.apptId ? parseInt(target.dataset.apptId, 10) : 0;
            const duration = target.dataset.duration ? parseInt(target.dataset.duration, 10) : 0;
            if (!apptId) {
                return;
            }
            this.enterRescheduleMode({
                apptId,
                durationSeconds: Number.isFinite(duration) ? duration : 0,
            });
        });

        delegate(document, 'click', '.csa-btn-reschedule-slot', async (event, target) => {
            if (!this.rescheduleState.active || !this.rescheduleState.apptId) {
                return;
            }
            const date = target.dataset.date || '';
            const time = target.dataset.time || '';
            if (!date || !time) {
                return;
            }
            await this.submitReschedule(date, time);
        });

        delegate(document, 'click', '.csa-btn-block', async (event, target) => {
            if (this.customBookingState.active) {
                return;
            }
            const date = target.dataset.date;
            const time = target.dataset.time;

            if (!window.confirm('Are you sure you want to block this time slot?')) {
                return;
            }

            await this.handleBlockToggle({
                action: 'csa_block_time_slot',
                date,
                time,
                errorMessage: 'Error blocking time slot',
            });
        });

        delegate(document, 'click', '.csa-btn-unblock', async (event, target) => {
            if (this.customBookingState.active) {
                return;
            }
            const date = target.dataset.date;
            const time = target.dataset.time;

            if (!window.confirm('Are you sure you want to unblock this time slot?')) {
                return;
            }

            await this.handleBlockToggle({
                action: 'csa_unblock_time_slot',
                date,
                time,
                errorMessage: 'Error unblocking time slot',
            });
        });

        delegate(document, 'click', '.csa-btn-allow', async (event, target) => {
            if (this.rescheduleState.active) {
                return;
            }
            if (this.customBookingState.active) {
                return;
            }
            const date = target.dataset.date;
            const time = target.dataset.time;

            if (!window.confirm(`Allow this time slot for ${date}?`)) {
                return;
            }

            try {
                const response = await this.request({
                    action: 'csa_set_manual_override',
                    nonce: this.config.nonce,
                    date,
                    time,
                    status: 'allow',
                });

                if (response.success) {
                    this.showDayDetails(date);
                    window.location.reload();
                } else {
                    const message = response.data && response.data.message ? response.data.message : 'Error setting override';
                    window.alert(message);
                }
            } catch (error) {
                window.alert('Error setting override');
            }
        });
    }

    /**
     * Bind bulk block/unblock handlers.
     *
     * @returns {void}
     */
    bindBulkActions() {
        delegate(document, 'click', '#csa-block-all, #csa-unblock-all', async (event, target) => {
            if (this.rescheduleState.active) {
                return;
            }
            if (this.customBookingState.active) {
                return;
            }
            const isBlock = target.id === 'csa-block-all';
            const dayControls = qs('#csa-day-controls');
            const date = dayControls ? dayControls.dataset.date : '';
            if (!date) {
                return;
            }
            const actionLabel = isBlock ? 'block' : 'unblock';
            if (!window.confirm(`Are you sure you want to ${actionLabel} all available time slots for ${date}?`)) {
                return;
            }

            const slots = qsa('#csa-time-slots .csa-time-slot').filter((slot) => !slot.classList.contains('booked'));
            if (slots.length === 0) {
                window.alert(`No available (non-booked) slots to ${actionLabel}.`);
                return;
            }

            const times = slots
                .map((slot) => slot.dataset.time)
                .filter((time) => time);

            qsa('#csa-day-controls button').forEach((button) => {
                button.disabled = true;
            });

            for (const time of times) {
                try {
                    await this.request({
                        action: isBlock ? 'csa_block_time_slot' : 'csa_unblock_time_slot',
                        nonce: this.config.nonce,
                        date,
                        time,
                    });
                } catch (error) {
                    // continue
                }
            }

            qsa('#csa-day-controls button').forEach((button) => {
                button.disabled = false;
            });
            this.showDayDetails(date);
        });
    }

    /**
     * Bind weekly availability UI handlers.
     *
     * @returns {void}
     */
    bindWeeklyAvailability() {
        delegate(document, 'change', '.csa-weekly-checkbox', () => {
            this.updateWeeklySaveState();
        });

        if (this.saveWeeklyButton) {
            on(this.saveWeeklyButton, 'click', async () => {
                if (this.saveWeeklyButton.disabled) {
                    return;
                }

                const weekly = {};
                qsa('.csa-weekly-checkbox:checked').forEach((checkbox) => {
                    const day = checkbox.dataset.day;
                    const hour = checkbox.dataset.hour;
                    if (!weekly[day]) {
                        weekly[day] = [];
                    }
                    weekly[day].push(hour);
                });

                this.saveWeeklyButton.disabled = true;
                this.keepLoading = true;

                try {
                    const response = await this.request({
                        action: 'csa_save_weekly_availability',
                        nonce: this.config.nonce,
                        weekly: JSON.stringify(weekly),
                    });

                    if (response.success) {
                    window.location.reload();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Error saving availability';
                        window.alert(message);
                        this.keepLoading = false;
                        this.body.classList.remove('loading-overlay');
                        this.updateWeeklySaveState();
                    }
                } catch (error) {
                    window.alert('Error saving availability');
                    this.keepLoading = false;
                    this.body.classList.remove('loading-overlay');
                    this.updateWeeklySaveState();
                }
            });
        }
    }

    /**
     * Bind holiday availability UI handlers.
     *
     * @returns {void}
     */
    bindHolidayAvailability() {
        delegate(document, 'change', '.csa-holiday-checkbox', () => {
            this.updateHolidaySaveState();
        });

        if (this.saveHolidayButton) {
            on(this.saveHolidayButton, 'click', async () => {
                if (this.saveHolidayButton.disabled) {
                    return;
                }

                const holidays = qsa('.csa-holiday-checkbox:checked').map((checkbox) => checkbox.dataset.key).filter((key) => key);

                this.saveHolidayButton.disabled = true;
                this.keepLoading = true;

                try {
                    const response = await this.request({
                        action: 'csa_save_holiday_availability',
                        nonce: this.config.nonce,
                        holidays: JSON.stringify(holidays),
                    });

                    if (response.success) {
                        window.location.reload();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Error saving holiday availability';
                        window.alert(message);
                        this.keepLoading = false;
                        this.body.classList.remove('loading-overlay');
                        this.updateHolidaySaveState();
                    }
                } catch (error) {
                    window.alert('Error saving holiday availability');
                    this.keepLoading = false;
                    this.body.classList.remove('loading-overlay');
                    this.updateHolidaySaveState();
                }
            });
        }
    }

    /**
     * Bind day render event handler to update calendar counts.
     *
     * @returns {void}
     */
    bindDayRendered() {
        document.addEventListener('csa:dayRendered', (event) => {
            const data = event.detail;
            if (!data || !data.date) {
                return;
            }

            const cell = qs(`.csa-calendar-day[data-date="${data.date}"]`);
            AdminDaySummaryRenderer.render({ data, cell });
        });
    }

    /**
     * Bind submission field fetcher after rendering day details.
     *
     * @returns {void}
     */
    bindLoadSubmissionFields() {
        document.addEventListener('csa:dayRendered', () => {
            qsa('.csa-appointment-all-data.csa-loading').forEach(async (element) => {
                const submissionId = element.dataset.submissionId;
                if (!submissionId) {
                    return;
                }
                try {
                    const response = await this.request({
                        action: 'csa_fetch_submission_values',
                        nonce: this.config.nonce,
                        submission_id: submissionId,
                    });

                    if (response.success && response.data && response.data.values) {
                        AdminSubmissionFieldsRenderer.render({
                            element,
                            values: response.data.values,
                        });
                    } else {
                        AdminSubmissionFieldsRenderer.render({
                            element,
                            values: null,
                            fallback: 'Fields not available',
                        });
                    }
                } catch (error) {
                    AdminSubmissionFieldsRenderer.render({
                        element,
                        values: null,
                        fallback: 'Error loading fields',
                    });
                }
            });
        });
    }

    /**
     * Bind timezone selector.
     *
     * @returns {void}
     */
    bindTimezoneSelector() {
        const select = qs('#csa-admin-timezone');
        const save = qs('#csa-save-timezone');
        if (!select || !save) {
            return;
        }

        const baseline = this.config && this.config.timezone ? String(this.config.timezone) : '';

        const updateSaveState = () => {
            save.disabled = String(select.value) === baseline;
        };

        const saveTimezone = async () => {
            const timezone = select.value;
            if (!timezone) {
                return;
            }
            save.disabled = true;
            this.keepLoading = true;
            try {
                const response = await this.request({
                    action: 'csa_save_timezone',
                    nonce: this.config.nonce,
                    timezone,
                });
                if (response.success) {
                    window.location.reload();
                } else {
                    const message = response.data && response.data.message ? response.data.message : 'Error saving time zone';
                    window.alert(message);
                }
            } catch (error) {
                window.alert('Error saving time zone');
            } finally {
                save.disabled = false;
                this.keepLoading = false;
                this.body.classList.remove('loading-overlay');
            }
        };

        on(save, 'click', (event) => {
            event.preventDefault();
            saveTimezone();
        });

        on(select, 'change', () => {
            updateSaveState();
        });

        updateSaveState();
    }

    /**
     * Check if a month is earlier than the minimum.
     *
     * @param {number} month
     * @param {number} year
     * @returns {boolean}
     */
    isBeforeMin(month, year) {
        const candidate = new Date(year, month - 1, 1);
        const minimum = new Date(this.minYear, this.minMonth - 1, 1);
        return candidate < minimum;
    }

    /**
     * Toggle previous navigation state based on min date.
     *
     * @returns {void}
     */
    updateNavButtons() {
        const prev = qs('#csa-prev-month');
        if (!prev) {
            return;
        }
        if (this.isBeforeMin(this.currentMonth, this.currentYear) ||
            (this.currentMonth === this.minMonth && this.currentYear === this.minYear)) {
            prev.setAttribute('disabled', 'disabled');
            prev.classList.add('disabled');
        } else {
            prev.removeAttribute('disabled');
            prev.classList.remove('disabled');
        }
    }

    /**
     * Navigate to a calendar month/year.
     *
     * @param {number} month
     * @param {number} year
     * @returns {void}
     */
    loadCalendar(month, year) {
        const url = new URL(window.location.href);
        url.searchParams.set('month', month);
        url.searchParams.set('year', year);
        if (this.config && this.config.is_admin && this.config.selected_user_id) {
            url.searchParams.set('user_id', this.config.selected_user_id);
        }
        window.location.href = url.toString();
    }

    /**
     * Fetch and render day details.
     *
     * @param {string} date
     * @returns {Promise<void>}
     */
    async showDayDetails(date) {
        try {
            const response = await this.request({
                action: 'csa_get_day_details',
                nonce: this.config.nonce,
                date,
            });

            if (response.success) {
                this.renderDayDetails(response.data);
                const modal = qs('#csa-day-detail-modal');
                if (modal) {
                    modal.style.display = 'block';
                }
            } else {
                const message = response.data && response.data.message ? response.data.message : 'Error loading day details';
                window.alert(message);
            }
        } catch (error) {
            window.alert('Error loading day details');
        }
    }

    /**
     * Render the day details modal.
     *
     * @param {Object} data
     * @returns {void}
     */
    renderDayDetails(data) {
        this.currentDayDetails = data;
        this.daySlotMap = this.buildSlotMap(data && data.time_slots ? data.time_slots : []);
        this.exitCustomBookingMode();
        AdminDayDetailsRenderer.render({
            data,
            timeSlots: qs('#csa-time-slots'),
            modalDate: qs('#csa-modal-date'),
            modalTimezone: qs('#csa-modal-timezone'),
            timezoneLabel: this.config.timezone_label || '',
            serviceDurationMap: this.config.services_duration_map || {},
            timezoneName: this.config.timezone || '',
            rescheduleState: this.rescheduleState,
        });
        document.dispatchEvent(new CustomEvent('csa:dayRendered', { detail: data }));
    }

    bindRescheduleControls() {
        if (this.rescheduleCancel) {
            on(this.rescheduleCancel, 'click', () => this.exitRescheduleMode());
        }
    }

    bindCustomAppointments() {
        delegate(document, 'click', '#csa-schedule-custom-appointment', (event, target) => {
            event.preventDefault();
            if (this.rescheduleState.active) {
                return;
            }
            if (this.customBookingState.active) {
                this.exitCustomBookingMode();
                return;
            }
            this.enterCustomBookingMode();
        });

        delegate(document, 'click', '.csa-btn-custom-book', (event, target) => {
            if (!this.customBookingState.active) {
                return;
            }
            const date = target.dataset.date || '';
            const time = target.dataset.time || '';
            if (!date || !time) {
                return;
            }
            this.openCustomAppointmentModal({ date, time });
        });

        delegate(document, 'click', '#csa-custom-appointment-modal .csa-modal-close, #csa-custom-appointment-modal .csa-modal-overlay', () => {
            this.closeCustomAppointmentModal();
        });

        const cancel = qs('#csa-custom-appointment-cancel');
        if (cancel) {
            on(cancel, 'click', (event) => {
                event.preventDefault();
                this.closeCustomAppointmentModal();
            });
        }

        const submit = qs('#csa-custom-appointment-submit');
        if (submit) {
            on(submit, 'click', (event) => {
                event.preventDefault();
                this.submitCustomAppointment();
            });
        }

        const durationSelect = qs('#csa-custom-appointment-duration');
        if (durationSelect) {
            on(durationSelect, 'change', () => this.updateCustomAppointmentEndTime());
        }
    }

    enterCustomBookingMode() {
        if (!this.currentDayDetails || !this.currentDayDetails.date) {
            return;
        }
        this.customBookingState = {
            active: true,
            date: this.currentDayDetails.date,
            time: '',
        };
        const modal = qs('#csa-day-detail-modal');
        if (modal) {
            modal.classList.add('csa-custom-mode');
        }

        const scheduleButton = qs('#csa-schedule-custom-appointment');
        if (scheduleButton) {
            scheduleButton.textContent = 'Cancel Custom Appointment';
        }

        qsa('#csa-day-controls button').forEach((button) => {
            if (button.id !== 'csa-schedule-custom-appointment') {
                button.disabled = true;
            }
        });

        qsa('#csa-time-slots .csa-time-slot').forEach((slot) => {
            const time = slot.dataset.time;
            if (!time) {
                return;
            }
            const data = this.daySlotMap[time];
            if (!this.isCustomBookableSlot(data)) {
                return;
            }
            slot.classList.add('csa-custom-available');
            const actions = qs('.csa-time-slot-actions', slot);
            if (!actions) {
                return;
            }
            if (!actions.dataset.originalHtml) {
                actions.dataset.originalHtml = actions.innerHTML;
            }
            actions.innerHTML = `<button class="csa-btn csa-btn-view csa-btn-custom-book" data-date="${this.customBookingState.date}" data-time="${time}">Book timeslot</button>`;
        });
    }

    exitCustomBookingMode() {
        if (!this.customBookingState.active) {
            return;
        }
        this.customBookingState = {
            active: false,
            date: '',
            time: '',
        };
        const modal = qs('#csa-day-detail-modal');
        if (modal) {
            modal.classList.remove('csa-custom-mode');
        }
        const scheduleButton = qs('#csa-schedule-custom-appointment');
        if (scheduleButton) {
            scheduleButton.textContent = 'Schedule Custom Appointment';
        }
        qsa('#csa-day-controls button').forEach((button) => {
            button.disabled = false;
        });
        qsa('#csa-time-slots .csa-time-slot').forEach((slot) => {
            slot.classList.remove('csa-custom-available');
            const actions = qs('.csa-time-slot-actions', slot);
            if (actions && actions.dataset.originalHtml) {
                actions.innerHTML = actions.dataset.originalHtml;
                delete actions.dataset.originalHtml;
            }
        });
    }

    openCustomAppointmentModal({ date, time }) {
        const modal = qs('#csa-custom-appointment-modal');
        if (!modal) {
            return;
        }
        this.customBookingState.date = date;
        this.customBookingState.time = time;
        const dateLabel = qs('#csa-custom-appointment-date');
        const timeLabel = qs('#csa-custom-appointment-time');
        if (dateLabel) {
            dateLabel.textContent = date;
        }
        if (timeLabel) {
            timeLabel.textContent = formatTime(time);
        }

        const titleInput = qs('#csa-custom-appointment-title');
        if (titleInput) {
            titleInput.value = '';
        }
        const notesInput = qs('#csa-custom-appointment-notes');
        if (notesInput) {
            notesInput.value = '';
        }

        const durationSelect = qs('#csa-custom-appointment-duration');
        const warning = qs('#csa-custom-appointment-warning');
        const submit = qs('#csa-custom-appointment-submit');

        if (durationSelect) {
            durationSelect.innerHTML = '';
            const options = this.getAvailableCustomDurations(time);
            options.forEach((option) => {
                const opt = document.createElement('option');
                opt.value = option.value;
                opt.textContent = option.label;
                durationSelect.appendChild(opt);
            });
            durationSelect.disabled = options.length === 0;
            if (options.length === 0) {
                if (warning) {
                    warning.textContent = 'No available durations for this start time.';
                    warning.style.display = 'block';
                }
                if (submit) {
                    submit.disabled = true;
                }
            } else {
                if (warning) {
                    warning.textContent = '';
                    warning.style.display = 'none';
                }
                if (submit) {
                    submit.disabled = false;
                }
            }
        }

        this.updateCustomAppointmentEndTime();
        modal.style.display = 'block';
    }

    closeCustomAppointmentModal() {
        const modal = qs('#csa-custom-appointment-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    updateCustomAppointmentEndTime() {
        const endLabel = qs('#csa-custom-appointment-end');
        const durationSelect = qs('#csa-custom-appointment-duration');
        if (!endLabel || !durationSelect || !this.customBookingState.time) {
            return;
        }
        const durationSeconds = parseInt(durationSelect.value, 10);
        if (!Number.isFinite(durationSeconds)) {
            endLabel.textContent = '';
            return;
        }
        const endTime = addMinutesToTime(this.customBookingState.time, Math.ceil(durationSeconds / 60));
        endLabel.textContent = endTime ? formatTime(endTime) : '';
    }

    async submitCustomAppointment() {
        const date = this.customBookingState.date;
        const time = this.customBookingState.time;
        const durationSelect = qs('#csa-custom-appointment-duration');
        if (!date || !time || !durationSelect) {
            return;
        }
        const durationSeconds = parseInt(durationSelect.value, 10);
        if (!Number.isFinite(durationSeconds) || durationSeconds <= 0) {
            window.alert('Please choose a valid duration.');
            return;
        }

        const titleInput = qs('#csa-custom-appointment-title');
        const title = titleInput ? titleInput.value.trim() : '';
        const notesInput = qs('#csa-custom-appointment-notes');
        const notes = notesInput ? notesInput.value.trim() : '';
        const submit = qs('#csa-custom-appointment-submit');

        if (submit) {
            submit.disabled = true;
        }

        try {
            const response = await this.request({
                action: 'csa_create_custom_appointment',
                nonce: this.config.nonce,
                date,
                time,
                duration_seconds: durationSeconds,
                title,
                notes,
            });

            if (response.success) {
                this.closeCustomAppointmentModal();
                this.exitCustomBookingMode();
                this.showDayDetails(date);
            } else {
                const message = response.data && response.data.message ? response.data.message : 'Failed to schedule appointment';
                window.alert(message);
            }
        } catch (error) {
            window.alert('Failed to schedule appointment');
        } finally {
            if (submit) {
                submit.disabled = false;
            }
        }
    }

    getAvailableCustomDurations(startTime) {
        const options = this.getDurationOptions();
        return options.filter((option) => this.isCustomRangeAvailable(startTime, option.value));
    }

    getDurationOptions() {
        const options = this.config && this.config.service_duration_options ? this.config.service_duration_options : {};
        let entries = Object.entries(options).map(([value, label]) => ({
            value: parseInt(value, 10),
            label,
        }));
        entries = entries.filter((entry) => Number.isFinite(entry.value));
        if (!entries.length && this.config && this.config.services_duration_map) {
            const unique = new Set(Object.values(this.config.services_duration_map));
            entries = Array.from(unique)
                .filter((value) => Number.isFinite(value))
                .map((value) => ({
                    value,
                    label: `${Math.round(value / 60)} minutes`,
                }));
        }
        return entries.sort((a, b) => a.value - b.value);
    }

    isCustomRangeAvailable(startTime, durationSeconds) {
        if (!startTime || !Number.isFinite(durationSeconds) || durationSeconds <= 0) {
            return false;
        }
        const slotsNeeded = Math.ceil(durationSeconds / 1800);
        for (let i = 0; i < slotsNeeded; i += 1) {
            const slotTime = i === 0 ? startTime : addMinutesToTime(startTime, 30 * i);
            if (!slotTime) {
                return false;
            }
            const slot = this.daySlotMap[slotTime];
            if (!this.isCustomBookableSlot(slot)) {
                return false;
            }
        }
        return true;
    }

    isCustomBookableSlot(slot) {
        if (!slot || slot.is_occupied || slot.appointments) {
            return false;
        }
        if (slot.is_blocked_explicit) {
            return false;
        }
        return true;
    }

    buildSlotMap(timeSlots) {
        const map = {};
        if (!Array.isArray(timeSlots)) {
            return map;
        }
        timeSlots.forEach((slot) => {
            if (slot && slot.time) {
                map[slot.time] = slot;
            }
        });
        return map;
    }

    enterRescheduleMode({ apptId, durationSeconds }) {
        this.rescheduleState = {
            active: true,
            apptId,
            durationSeconds: durationSeconds || 0,
        };
        if (this.calendarWrapper) {
            this.calendarWrapper.classList.add('csa-reschedule-mode');
        }
        if (this.rescheduleBanner) {
            this.rescheduleBanner.style.display = 'flex';
        }
        const modal = qs('#csa-day-detail-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    exitRescheduleMode() {
        this.rescheduleState = {
            active: false,
            apptId: null,
            durationSeconds: 0,
        };
        if (this.calendarWrapper) {
            this.calendarWrapper.classList.remove('csa-reschedule-mode');
        }
        if (this.rescheduleBanner) {
            this.rescheduleBanner.style.display = 'none';
        }
    }

    async submitReschedule(date, time) {
        try {
            const response = await this.request({
                action: 'csa_reschedule_appointment',
                nonce: this.config.nonce,
                appt_id: this.rescheduleState.apptId,
                date,
                time,
            });
            if (response.success) {
                this.exitRescheduleMode();
                window.location.reload();
            } else {
                const message = response.data && response.data.message ? response.data.message : 'Failed to reschedule appointment';
                window.alert(message);
            }
        } catch (error) {
            window.alert('Failed to reschedule appointment');
        }
    }

    /**
     * Handle block/unblock requests for a slot.
     *
     * @param {Object} options
     * @param {string} options.action
     * @param {string} options.date
     * @param {string} options.time
     * @param {string} options.errorMessage
     * @returns {Promise<void>}
     */
    async handleBlockToggle({ action, date, time, errorMessage }) {
        if (this.rescheduleState.active) {
            return;
        }
        if (this.customBookingState.active) {
            return;
        }
        try {
            const response = await this.request({
                action,
                nonce: this.config.nonce,
                date,
                time,
            });

            if (response.success) {
                this.showDayDetails(date);
                window.location.reload();
            } else {
                const message = response.data && response.data.message ? response.data.message : errorMessage;
                window.alert(message);
            }
        } catch (error) {
            window.alert(errorMessage);
        }
    }

    /**
     * Render the weekly availability grid.
     *
     * @returns {void}
     */
    renderWeeklyAvailability() {
        AdminWeeklyAvailabilityRenderer.render({
            container: qs('#csa-weekly-availability'),
            weekly: this.config.weekly_availability || {},
            hours: this.config.hours || [],
        });
    }

    /**
     * Render holiday availability list.
     *
     * @returns {void}
     */
    renderHolidayAvailability() {
        AdminHolidayAvailabilityRenderer.render({
            container: qs('#csa-holiday-availability'),
            holidays: this.config.holiday_list || [],
            enabled: this.config.holiday_availability || [],
        });
    }

    /**
     * Build a stable signature of selected weekly slots.
     *
     * @returns {string}
     */
    getWeeklySelectionSignature() {
        const selected = qsa('.csa-weekly-checkbox:checked')
            .map((checkbox) => `${checkbox.dataset.day}:${checkbox.dataset.hour}`)
            .sort();
        return selected.join('|');
    }

    /**
     * Update weekly save button based on dirty state.
     *
     * @returns {void}
     */
    updateWeeklySaveState() {
        if (!this.saveWeeklyButton) {
            return;
        }
        const current = this.getWeeklySelectionSignature();
        const isDirty = current !== this.weeklyBaseline;
        this.saveWeeklyButton.disabled = !isDirty;
    }

    /**
     * Build a stable signature of selected holiday keys.
     *
     * @returns {string}
     */
    getHolidaySelectionSignature() {
        const selected = qsa('.csa-holiday-checkbox:checked')
            .map((checkbox) => checkbox.dataset.key)
            .filter((key) => key)
            .sort();
        return selected.join('|');
    }

    /**
     * Update holiday save button based on dirty state.
     *
     * @returns {void}
     */
    updateHolidaySaveState() {
        if (!this.saveHolidayButton) {
            return;
        }
        const current = this.getHolidaySelectionSignature();
        const isDirty = current !== this.holidayBaseline;
        this.saveHolidayButton.disabled = !isDirty;
    }

    /**
     * Send an AJAX request with loading overlay events.
     *
     * @param {Object} payload
     * @returns {Promise<Object>}
     */
    async request(payload) {
        document.dispatchEvent(new Event('csa:ajaxStart'));
        if (this.config && this.config.selected_user_id && !('user_id' in payload)) {
            payload.user_id = this.config.selected_user_id;
        }
        try {
            return await postAjax(this.config.ajax_url, payload);
        } finally {
            document.dispatchEvent(new Event('csa:ajaxComplete'));
        }
    }
}

const addMinutesToTime = (value, minutesToAdd) => {
    if (!value || typeof value !== 'string' || !Number.isFinite(minutesToAdd)) {
        return '';
    }
    const normalized = value.trim();
    const timePart = normalized.includes('T') ? normalized.split('T')[1] : normalized;
    const pieces = timePart.split(':');
    if (pieces.length < 2) {
        return '';
    }
    const hours = parseInt(pieces[0], 10);
    const minutes = parseInt(pieces[1], 10);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return '';
    }
    const total = hours * 60 + minutes + minutesToAdd;
    const nextHours = Math.floor(total / 60);
    const nextMinutes = total % 60;
    return `${String(nextHours).padStart(2, '0')}:${String(nextMinutes).padStart(2, '0')}`;
};

const adminCalendar = new AdminCalendar();
adminCalendar.init();
