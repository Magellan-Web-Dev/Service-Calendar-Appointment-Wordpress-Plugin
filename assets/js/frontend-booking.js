import { qs, qsa, delegate, on, postAjax } from './utils.js';

export class FrontendBooking {
    /**
     * Create a frontend booking controller.
     */
    constructor() {
        this.currentMonth = new Date().getMonth();
        this.currentYear = new Date().getFullYear();
        this.initializedFields = new WeakSet();
        this.selectedDates = new WeakMap();
        this.observer = null;
        this.config = window.csaFrontend || null;
    }

    /**
     * Initialize date pickers and observers.
     *
     * @returns {void}
     */
    init() {
        if (!this.config) {
            return;
        }

        this.initExistingDatePickers();
        this.observeNewFields();
        this.bindDateFieldChange();
    }

    /**
     * Initialize existing date picker fields.
     *
     * @returns {void}
     */
    initExistingDatePickers() {
        qsa('.csa-appointment-date-field').forEach((field) => {
            this.initDatePicker(field);
        });
    }

    /**
     * Observe for dynamically added appointment fields.
     *
     * @returns {void}
     */
    observeNewFields() {
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }
                    if (node.matches && node.matches('.csa-appointment-date-field')) {
                        this.initDatePicker(node);
                    } else {
                        qsa('.csa-appointment-date-field', node).forEach((field) => {
                            this.initDatePicker(field);
                        });
                    }
                });
            });
        });

        this.observer.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Bind date field change handler.
     *
     * @returns {void}
     */
    bindDateFieldChange() {
        delegate(document, 'change', '.csa-appointment-date-field', (event, target) => {
            const hiddenDate = qs('.csa-selected-date', target.closest('.elementor-field'));
            const date = hiddenDate ? hiddenDate.value : '';
            if (date) {
                this.updateTimeSlots(date, target);
            }
        });
    }

    /**
     * Initialize a single date picker field.
     *
     * @param {HTMLInputElement} field
     * @returns {void}
     */
    initDatePicker(field) {
        if (this.initializedFields.has(field)) {
            return;
        }

        const container = qs('.csa-date-picker-container', field.closest('.elementor-field'));
        const calendar = container ? qs('.csa-calendar-widget', container) : null;

        if (!container || !calendar) {
            return;
        }

        on(field, 'click', (event) => {
            event.stopPropagation();
            qsa('.csa-date-picker-container').forEach((picker) => {
                if (picker !== container) {
                    picker.style.display = 'none';
                }
            });
            container.style.display = container.style.display === 'block' ? 'none' : 'block';
            if (container.style.display === 'block') {
                this.renderCalendar(calendar, this.currentMonth, this.currentYear, field);
            }
        });

        on(document, 'click', (event) => {
            if (!event.target.closest('.csa-date-picker-container') && !event.target.closest('.csa-appointment-date-field')) {
                container.style.display = 'none';
            }
        });

        this.initializedFields.add(field);
    }

    /**
     * Render the calendar UI for a field.
     *
     * @param {HTMLElement} calendar
     * @param {number} month
     * @param {number} year
     * @param {HTMLInputElement} field
     * @returns {void}
     */
    renderCalendar(calendar, month, year, field) {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        let html = '<div class="csa-calendar-widget-header">';
        html += '<button type="button" class="csa-calendar-nav-btn csa-prev-month">&larr;</button>';
        html += `<h4>${monthNames[month]} ${year}</h4>`;
        html += '<button type="button" class="csa-calendar-nav-btn csa-next-month">&rarr;</button>';
        html += '</div>';

        html += '<div class="csa-calendar-grid">';

        dayNames.forEach((day) => {
            html += `<div class="csa-calendar-day-header">${day}</div>`;
        });

        for (let i = 0; i < startingDayOfWeek; i++) {
            html += '<div class="csa-calendar-day-cell disabled"></div>';
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dayOfWeek = date.getDay();
            const dateString = this.formatDateString(date);

            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
            const isPast = date < today;
            const isDisabled = isWeekend || isPast;

            const classes = ['csa-calendar-day-cell'];
            if (isDisabled) {
                classes.push('disabled');
            }
            if (dateString === this.formatDateString(today)) {
                classes.push('today');
            }
            if (this.selectedDates.get(field) === dateString) {
                classes.push('selected');
            }

            html += `<div class="${classes.join(' ')}" data-date="${dateString}">${day}</div>`;
        }

        html += '</div>';
        calendar.innerHTML = html;

        const prev = qs('.csa-prev-month', calendar);
        if (prev) {
            on(prev, 'click', () => {
                this.currentMonth -= 1;
                if (this.currentMonth < 0) {
                    this.currentMonth = 11;
                    this.currentYear -= 1;
                }
                this.renderCalendar(calendar, this.currentMonth, this.currentYear, field);
            });
        }

        const next = qs('.csa-next-month', calendar);
        if (next) {
            on(next, 'click', () => {
                this.currentMonth += 1;
                if (this.currentMonth > 11) {
                    this.currentMonth = 0;
                    this.currentYear += 1;
                }
                this.renderCalendar(calendar, this.currentMonth, this.currentYear, field);
            });
        }

        delegate(calendar, 'click', '.csa-calendar-day-cell:not(.disabled)', (event, target) => {
            const date = target.dataset.date;
            this.selectedDates.set(field, date);
            field.value = this.formatDateDisplay(date);

            const hiddenDate = qs('.csa-selected-date', field.closest('.elementor-field'));
            if (hiddenDate) {
                hiddenDate.value = date;
            }

            const picker = field.closest('.elementor-field').querySelector('.csa-date-picker-container');
            if (picker) {
                picker.style.display = 'none';
            }

            this.updateTimeSlots(date, field);
        });
    }

    /**
     * Format a Date as YYYY-MM-DD.
     *
     * @param {Date} date
     * @returns {string}
     */
    formatDateString(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Format a YYYY-MM-DD string for display.
     *
     * @param {string} dateString
     * @returns {string}
     */
    formatDateDisplay(dateString) {
        const date = new Date(`${dateString}T00:00:00`);
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    /**
     * Load available times for a selected date.
     *
     * @param {string} date
     * @param {HTMLInputElement} dateField
     * @returns {Promise<void>}
     */
    async updateTimeSlots(date, dateField) {
        const form = dateField.closest('form');
        const timeField = form ? qs('.csa-appointment-time-field', form) : null;

        if (!timeField) {
            return;
        }

        timeField.innerHTML = '<option value="">Loading...</option>';
        timeField.disabled = true;

        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_times',
                date,
            });

            if (response.success) {
                const times = response.data.times;
                let html = '<option value="">Select a time</option>';

                if (!times.length) {
                    html = '<option value="">No times available</option>';
                } else {
                    html = times.map((time) => `<option value="${time.value}">${time.label}</option>`).join('');
                    html = `<option value="">Select a time</option>${html}`;
                }

                timeField.innerHTML = html;
                timeField.disabled = times.length === 0;
            } else {
                timeField.innerHTML = '<option value="">Error loading times</option>';
                timeField.disabled = true;
            }
        } catch (error) {
            timeField.innerHTML = '<option value="">Error loading times</option>';
            timeField.disabled = true;
        }
    }
}

const frontendBooking = new FrontendBooking();
frontendBooking.init();
