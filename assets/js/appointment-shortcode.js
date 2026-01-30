import { qs, qsa, on, postAjax } from './utils.js';

const DAY_LABELS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
const MONTH_LABELS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const getElementorPropTarget = (form, prop) => {
    if (!prop) {
        return null;
    }

    const byId = document.getElementById(prop);
    if (byId) {
        return byId;
    }

    const name = `form_fields[${prop}]`;
    if (form) {
        const scoped = qs(`.elementor-field-group:not(.elementor-field-type-html) [name="${name}"]`, form);
        if (scoped) {
            return scoped;
        }
    }
    return qs(`[name="${name}"]`, document);
};

const setElementorPropValue = (form, prop, value) => {
    if (!prop) {
        return;
    }
    const hiddenId = `csa-field-${prop}`;
    const hidden = document.getElementById(hiddenId);
    if (hidden) {
        hidden.value = value;
    }
    const target = getElementorPropTarget(form, prop);
    if (target) {
        target.value = value;
    }
};

const formatPropValue = (type, value) => {
    if (!value) {
        return '';
    }
    return `csa::${type} --> ${value}`;
};

const clearElementorPropValue = (form, prop) => {
    setElementorPropValue(form, prop, '');
};

class ServiceShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.items = qsa('.csa-service-item', container);
    }

    init() {
        if (!this.items.length) {
            return;
        }

        this.items.forEach((item) => {
            on(item, 'click', () => this.selectService(item));
        });

        const preselected = this.items.find((item) => {
            const radio = qs('.csa-service-radio', item);
            return radio && radio.checked;
        });
        if (preselected) {
            this.selectService(preselected);
        }
    }

    selectService(item) {
        const title = item.dataset.title || '';
        const durationSeconds = parseInt(item.dataset.durationSeconds || '0', 10);

        this.items.forEach((entry) => entry.classList.remove('selected'));
        item.classList.add('selected');

        const radio = qs('.csa-service-radio', item);
        if (radio) {
            radio.checked = true;
        }

        if (this.form) {
            this.form.dataset.csaServiceDuration = Number.isFinite(durationSeconds) ? String(durationSeconds) : '0';
            this.form.dataset.csaServiceTitle = title;
            const event = new CustomEvent('csa:serviceChanged', {
                detail: { durationSeconds, title },
                bubbles: true,
            });
            this.form.dispatchEvent(event);
        }

        if (this.prop) {
            setElementorPropValue(this.form, this.prop, formatPropValue('service', title));
        }
    }
}

class TimeShortcode {
    constructor(container, config) {
        this.container = container;
        this.config = config;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.calendar = qs('.csa-calendar-widget', container);
        this.calendarWrapper = qs('.csa-appointment-calendar', container);
        this.timeNotification = qs('.csa-time-notification', container);
        this.timeSelect = qs('.csa-appointment-time-select', container);
        this.timeField = qs('.csa-field-time', container);
        this.hiddenDate = qs('.csa-appointment-date-hidden', container);
        this.hiddenTime = qs('.csa-appointment-time-hidden', container);
        this.composite = qs('.csa-appointment-composite-hidden', container);
        const today = new Date();
        this.currentMonth = today.getMonth();
        this.currentYear = today.getFullYear();
        this.selectedDate = '';
        this.availableDays = new Set();
    }

    init() {
        if (!this.calendar || !this.timeSelect) {
            return;
        }

        on(this.timeSelect, 'change', () => this.handleTimeChange());

        if (this.form) {
            on(this.form, 'csa:serviceChanged', () => {
                this.resetSelection();
                this.loadAvailableDays();
            });
        }

        this.renderCalendar();
        this.loadAvailableDays();
        this.updateCalendarDisabledState();
        this.updateTimeSelectState('Select a day first', true);
        this.updateTimeNotificationState();
    }

    getDurationSeconds() {
        if (!this.form) {
            return 0;
        }
        const raw = this.form.dataset.csaServiceDuration || '';
        const parsed = parseInt(raw, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    updateTimeSelectState(placeholder, disabled) {
        if (!this.timeSelect) {
            return;
        }
        let html = '';
        if (placeholder) {
            html = `<option value="" disabled selected>${placeholder}</option>`;
        }
        this.timeSelect.innerHTML = html;
        this.timeSelect.disabled = !!disabled;
        if (this.timeField) {
            this.timeField.classList.toggle('csa-field-disabled', !!disabled);
        }
    }

    resetSelection() {
        this.selectedDate = '';
        if (this.hiddenDate) {
            this.hiddenDate.value = '';
        }
        if (this.hiddenTime) {
            this.hiddenTime.value = '';
        }
        if (this.composite) {
            this.composite.value = '';
        }
        if (this.prop) {
            clearElementorPropValue(this.form, this.prop);
        }
        this.updateCalendarDisabledState();
        this.updateTimeSelectState('Select a day first', true);
        this.updateTimeNotificationState();
    }

    renderCalendar() {
        if (!this.calendar) {
            return;
        }

        const today = new Date();
        const isCurrentMonth = this.currentYear === today.getFullYear() && this.currentMonth === today.getMonth();

        const firstDay = new Date(this.currentYear, this.currentMonth, 1);
        const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        let html = '<div class="csa-calendar-widget-header">';
        html += '<button type="button" class="csa-calendar-nav-btn csa-prev-month" aria-label="Previous month">&larr;</button>';
        html += `<h4>${MONTH_LABELS[this.currentMonth]} ${this.currentYear}</h4>`;
        html += '<button type="button" class="csa-calendar-nav-btn csa-next-month" aria-label="Next month">&rarr;</button>';
        html += '</div>';

        html += '<div class="csa-calendar-grid">';
        DAY_LABELS.forEach((day) => {
            html += `<div class="csa-calendar-day-header">${day}</div>`;
        });

        for (let i = 0; i < startingDayOfWeek; i += 1) {
            html += '<div class="csa-calendar-day-cell disabled"></div>';
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const dateString = this.formatDateString(this.currentYear, this.currentMonth, day);
            const dayKey = String(day).padStart(2, '0');
            const isAvailable = this.availableDays.has(dayKey);
            const classes = ['csa-calendar-day-cell'];
            if (!isAvailable) {
                classes.push('disabled');
            }
            if (dateString === this.selectedDate) {
                classes.push('selected');
            }

            html += `<div class="${classes.join(' ')}" data-date="${dateString}">${day}</div>`;
        }

        html += '</div>';
        this.calendar.innerHTML = html;

        const prev = qs('.csa-prev-month', this.calendar);
        const next = qs('.csa-next-month', this.calendar);
        if (prev) {
            if (isCurrentMonth) {
                prev.style.visibility = 'hidden';
                prev.setAttribute('disabled', 'disabled');
            } else {
                prev.style.visibility = '';
                prev.removeAttribute('disabled');
                on(prev, 'click', () => this.changeMonth(-1));
            }
        }
        if (next) {
            on(next, 'click', () => this.changeMonth(1));
        }

        qsa('.csa-calendar-day-cell:not(.disabled)', this.calendar).forEach((cell) => {
            on(cell, 'click', () => this.selectDate(cell.dataset.date || ''));
        });
    }

    updateCalendarDisabledState() {
        if (!this.calendarWrapper) {
            return;
        }
        const durationSeconds = this.getDurationSeconds();
        const disabled = !durationSeconds;
        this.calendarWrapper.classList.toggle('csa-field-disabled', disabled);
    }

    updateTimeNotificationState() {
        if (!this.timeNotification) {
            return;
        }
        const hasService = this.getDurationSeconds() > 0;
        const hasDate = !!this.selectedDate;
        const active = hasService && !hasDate;
        this.timeNotification.classList.toggle('csa-time-notification-active', active);
    }

    changeMonth(delta) {
        const today = new Date();
        const minMonth = today.getMonth();
        const minYear = today.getFullYear();

        this.currentMonth += delta;
        if (this.currentMonth < 0) {
            this.currentMonth = 11;
            this.currentYear -= 1;
        }
        if (this.currentMonth > 11) {
            this.currentMonth = 0;
            this.currentYear += 1;
        }

        if (this.currentYear < minYear || (this.currentYear === minYear && this.currentMonth < minMonth)) {
            this.currentYear = minYear;
            this.currentMonth = minMonth;
        }

        this.selectedDate = '';
        if (this.hiddenDate) {
            this.hiddenDate.value = '';
        }
        if (this.hiddenTime) {
            this.hiddenTime.value = '';
        }
        if (this.composite) {
            this.composite.value = '';
        }
        if (this.prop) {
            clearElementorPropValue(this.form, this.prop);
        }
        this.updateTimeSelectState('Select a day first', true);
        this.loadAvailableDays();
    }

    async loadAvailableDays() {
        const durationSeconds = this.getDurationSeconds();
        if (!durationSeconds) {
            this.availableDays = new Set();
            this.renderCalendar();
            this.updateTimeSelectState('Select a service first', true);
            this.updateCalendarDisabledState();
            this.updateTimeNotificationState();
            return;
        }

        const monthVal = `${this.currentYear}-${String(this.currentMonth + 1).padStart(2, '0')}`;
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_days',
                month: monthVal,
                duration_seconds: durationSeconds,
            });

            if (response && response.success && response.data && Array.isArray(response.data.days)) {
                this.availableDays = new Set(response.data.days.map((day) => day.value));
            } else {
                this.availableDays = new Set();
            }
        } catch (error) {
            this.availableDays = new Set();
        }

        this.renderCalendar();
        this.updateCalendarDisabledState();
        this.updateTimeNotificationState();
    }

    async selectDate(dateString) {
        if (!dateString) {
            return;
        }

        this.selectedDate = dateString;
        if (this.hiddenDate) {
            this.hiddenDate.value = dateString;
        }
        if (this.hiddenTime) {
            this.hiddenTime.value = '';
        }
        if (this.composite) {
            this.composite.value = '';
        }
        if (this.prop) {
            clearElementorPropValue(this.form, this.prop);
        }

        this.renderCalendar();
        this.updateTimeNotificationState();
        await this.loadTimes(dateString);
    }

    async loadTimes(dateString) {
        const durationSeconds = this.getDurationSeconds();
        if (!durationSeconds) {
            this.updateTimeSelectState('Select a service first', true);
            return;
        }

        this.updateTimeSelectState('Loading...', true);
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_times',
                date: dateString,
                duration_seconds: durationSeconds,
            });

            if (response && response.success && response.data && Array.isArray(response.data.times)) {
                const times = response.data.times;
                if (times.length === 0) {
                    this.updateTimeSelectState('No times available', true);
                } else {
                    let html = '<option value="" disabled selected>Select time</option>';
                    times.forEach((time) => {
                        html += `<option value="${time.value}">${time.label}</option>`;
                    });
                    this.timeSelect.innerHTML = html;
                    this.timeSelect.disabled = false;
                    if (this.timeField) {
                        this.timeField.classList.remove('csa-field-disabled');
                    }
                }
            } else {
                this.updateTimeSelectState('Error loading times', true);
            }
        } catch (error) {
            this.updateTimeSelectState('Error loading times', true);
        }
    }

    handleTimeChange() {
        const timeVal = this.timeSelect ? this.timeSelect.value : '';
        if (!timeVal || !this.selectedDate) {
            return;
        }

        if (this.hiddenTime) {
            this.hiddenTime.value = timeVal;
        }

        const composite = this.formatComposite(this.selectedDate, timeVal);
        if (this.composite) {
            this.composite.value = composite;
        }
        if (this.prop) {
            setElementorPropValue(this.form, this.prop, formatPropValue('time', composite));
        }
    }

    formatDateString(year, monthIndex, day) {
        const month = String(monthIndex + 1).padStart(2, '0');
        const dayStr = String(day).padStart(2, '0');
        return `${year}-${month}-${dayStr}`;
    }

    formatComposite(dateStr, timeStr) {
        if (!dateStr || !timeStr) {
            return '';
        }
        const parts = dateStr.split('-');
        if (parts.length < 3) {
            return '';
        }
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);

        const timeParts = timeStr.split(':');
        let hour = parseInt(timeParts[0], 10);
        const minute = timeParts.length > 1 ? parseInt(timeParts[1], 10) : 0;
        const date = new Date(year, month, day, hour, minute);
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const monthName = MONTH_LABELS[date.getMonth()];
        const dayNum = date.getDate();
        const fullYear = date.getFullYear();

        const rawHour = date.getHours();
        const ampm = rawHour >= 12 ? 'PM' : 'AM';
        hour = rawHour % 12;
        if (hour === 0) {
            hour = 12;
        }
        const minuteStr = minute < 10 ? `0${minute}` : `${minute}`;

        return `${monthName} ${dayNum}, ${fullYear} - ${hour}:${minuteStr}${ampm}`;
    }
}

const appointmentConfig = window.csaAppointment || null;
if (appointmentConfig) {
    qsa('.csa-appointment-field').forEach((container) => {
        const type = container.dataset.type || 'time';
        if (type === 'services') {
            const instance = new ServiceShortcode(container);
            instance.init();
        } else if (type === 'time') {
            const instance = new TimeShortcode(container, appointmentConfig);
            instance.init();
        }
    });
}
