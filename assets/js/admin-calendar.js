import { qs, qsa, delegate, on, postAjax } from './utils.js';
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
            if (!this.ajaxInFlight) {
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
        delegate(document, 'click', '.csa-calendar-day:not(.empty)', (event, target) => {
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

        delegate(document, 'click', '.csa-btn-block', async (event, target) => {
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

                try {
                    const response = await this.request({
                        action: 'csa_save_weekly_availability',
                        nonce: this.config.nonce,
                        weekly: JSON.stringify(weekly),
                    });

                    if (response.success) {
                    const message = response.data && response.data.message ? response.data.message : 'Saved';
                    window.alert(message);
                        window.location.reload();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Error saving availability';
                        window.alert(message);
                        this.updateWeeklySaveState();
                    }
                } catch (error) {
                    window.alert('Error saving availability');
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

                try {
                    const response = await this.request({
                        action: 'csa_save_holiday_availability',
                        nonce: this.config.nonce,
                        holidays: JSON.stringify(holidays),
                    });

                    if (response.success) {
                        const message = response.data && response.data.message ? response.data.message : 'Saved';
                        window.alert(message);
                        window.location.reload();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Error saving holiday availability';
                        window.alert(message);
                        this.updateHolidaySaveState();
                    }
                } catch (error) {
                    window.alert('Error saving holiday availability');
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
        AdminDayDetailsRenderer.render({
            data,
            timeSlots: qs('#csa-time-slots'),
            modalDate: qs('#csa-modal-date'),
        });

        document.dispatchEvent(new CustomEvent('csa:dayRendered', { detail: data }));
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
        try {
            return await postAjax(this.config.ajax_url, payload);
        } finally {
            document.dispatchEvent(new Event('csa:ajaxComplete'));
        }
    }
}

const adminCalendar = new AdminCalendar();
adminCalendar.init();
