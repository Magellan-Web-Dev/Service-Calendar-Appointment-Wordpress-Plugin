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

const setFieldPropValue = (prop, value) => {
    if (!prop) {
        return;
    }
    const target = document.getElementById(prop);
    if (!target) {
        return;
    }
    target.value = value;
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
};

const formatPropValue = (type, value) => {
    if (!value) {
        return '';
    }
    return `csa::${type} --> ${value}`;
};

const formatUserPropValue = (username, fullName) => {
    if (!username) {
        return '';
    }
    const name = (fullName || '').trim();
    if (!name) {
        return `csa::user --> ${username}`;
    }
    return `csa::user --> ${username} --> ${name}`;
};

const clearElementorPropValue = (form, prop) => {
    setElementorPropValue(form, prop, '');
};

const clearFieldPropValue = (prop) => {
    setFieldPropValue(prop, '');
};

const getUsernameFromForm = (container) => {
    const form = container ? container.closest('form') : null;
    if (!form) {
        return '';
    }
    const anyoneSelected = form.dataset.csaAnyoneSelected === '1';
    const anyoneValue = (form.dataset.csaAnyoneValue || '').trim();
    if (anyoneSelected && anyoneValue) {
        return anyoneValue;
    }
    const input = qs('input[name="csa_user"]', form);
    const inputUsername = qs('input[name="csa_username"]', form);
    if (inputUsername && (inputUsername.value || '').trim()) {
        return (inputUsername.value || '').trim();
    }
    if (!input) {
        return '';
    }
    return (input.value || '').trim();
};

class UserShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.username = container.dataset.user || getUsernameFromForm(container);
        this.fullName = container.dataset.userFullName || '';
    }

    init() {
        if (!this.username) {
            return;
        }
        const fullName = (this.fullName || '').trim();
        if (this.prop) {
            setElementorPropValue(this.form, this.prop, formatUserPropValue(this.username, fullName));
        }
        if (this.fieldProp) {
            setFieldPropValue(this.fieldProp, formatUserPropValue(this.username, fullName));
        }
    }
}

class UserSelectShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.anyoneValue = container.dataset.anyoneValue || '';
        this.list = qsa('.csa-user-item', container);
        this.select = qs('.csa-user-select', container);
        this.hidden = qs('.csa-user-hidden', container);
        this.hiddenForm = qs('.csa-user-hidden-form', container);
        this.hiddenUsername = qs('.csa-user-hidden-username', container);
        this.hiddenUsernameForm = qs('.csa-user-hidden-username-form', container);
    }

    init() {
        if (!this.list.length && !this.select) {
            return;
        }
        if (this.form && this.anyoneValue) {
            this.form.dataset.csaAnyoneValue = this.anyoneValue;
        }
        if (this.form && this.container && this.container.dataset.autoAnyone === '1') {
            this.form.dataset.csaAutoAnyone = '1';
        }
        if (this.form) {
            if (this.prop) {
                this.form.dataset.csaUserProp = this.prop;
            }
            if (this.fieldProp) {
                this.form.dataset.csaUserFieldProp = this.fieldProp;
            }
        }

        this.list.forEach((item) => {
            on(item, 'click', () => this.selectUser(item.dataset.username || '', item.dataset.fullName || ''));
        });

        if (this.select) {
            on(this.select, 'change', () => {
                const option = this.select.options[this.select.selectedIndex];
                const fullName = option ? (option.dataset.fullName || '') : '';
                this.selectUser(this.select.value || '', fullName);
            });
        }

        if (this.form && this.form.dataset.csaAutoAnyone === '1') {
            const autoValue = (this.form.dataset.csaAnyoneValue || '').trim();
            if (autoValue) {
                this.selectUser(autoValue, 'Anyone');
            }
        }
    }

    selectUser(username, fullName = '') {
        if (!username) {
            this.list.forEach((entry) => {
                entry.classList.remove('selected');
                const radio = qs('.csa-user-radio', entry);
                if (radio) {
                    radio.checked = false;
                }
            });
            if (this.select) {
                this.select.value = '';
            }
            if (this.hidden) {
                this.hidden.value = '';
            }
            if (this.hiddenForm) {
                this.hiddenForm.value = '';
            }
            if (this.hiddenUsername) {
                this.hiddenUsername.value = '';
            }
            if (this.hiddenUsernameForm) {
                this.hiddenUsernameForm.value = '';
            }
            if (this.form) {
                delete this.form.dataset.csaAnyoneSelected;
                delete this.form.dataset.csaResolvedUser;
            }
            if (this.prop) {
                clearElementorPropValue(this.form, this.prop);
            }
            if (this.fieldProp) {
                clearFieldPropValue(this.fieldProp);
            }
            if (this.form) {
                const event = new CustomEvent('csa:userChanged', {
                    detail: { username: '' },
                    bubbles: true,
                });
                this.form.dispatchEvent(event);
            }
            return;
        }
        this.list.forEach((entry) => entry.classList.remove('selected'));
        const match = this.list.find((entry) => entry.dataset.username === username);
        if (match) {
            match.classList.add('selected');
            const radio = qs('.csa-user-radio', match);
            if (radio) {
                radio.checked = true;
            }
            if (!fullName && match.dataset.fullName) {
                fullName = match.dataset.fullName;
            }
        }
        if (this.select && this.select.value !== username) {
            this.select.value = username;
        }
        if (this.hidden) {
            this.hidden.value = username;
        }
        if (this.hiddenForm) {
            this.hiddenForm.value = username;
        }
        if (this.hiddenUsername) {
            this.hiddenUsername.value = username;
        }
        if (this.hiddenUsernameForm) {
            this.hiddenUsernameForm.value = username;
        }
        if (this.form) {
            if (this.anyoneValue && username === this.anyoneValue) {
                this.form.dataset.csaAnyoneSelected = '1';
                delete this.form.dataset.csaResolvedUser;
            } else {
                delete this.form.dataset.csaAnyoneSelected;
                delete this.form.dataset.csaResolvedUser;
            }
        }
        const propValue = fullName || username;
        if (this.prop) {
            setElementorPropValue(this.form, this.prop, formatUserPropValue(username, propValue));
        }
        if (this.fieldProp) {
            setFieldPropValue(this.fieldProp, formatUserPropValue(username, propValue));
        }
        if (this.form) {
            const event = new CustomEvent('csa:userChanged', {
                detail: { username },
                bubbles: true,
            });
            this.form.dispatchEvent(event);
        }
    }
}

class ServiceShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.username = getUsernameFromForm(container);
        this.items = qsa('.csa-service-item', container);
        this.select = qs('.csa-service-select', container);
    }

    init() {
        if (!this.items.length) {
            return;
        }

        this.items.forEach((item) => {
            on(item, 'click', () => this.selectService(item));
        });

        if (this.select) {
            on(this.select, 'change', () => {
                const value = this.select.value || '';
                const match = this.items.find((entry) => entry.dataset.title === value);
                if (match) {
                    this.selectService(match);
                } else {
                    this.resetSelection();
                }
            });
        }

        const preselected = this.items.find((item) => {
            const radio = qs('.csa-service-radio', item);
            return radio && radio.checked;
        });
        if (preselected) {
            this.selectService(preselected);
        }

        if (this.form) {
            on(this.form, 'csa:userChanged', () => {
                this.updateDisabledState();
                this.resetSelection();
            });
        }

        this.updateDisabledState();
    }

    selectService(item) {
        if (!this.updateDisabledState()) {
            return;
        }
        const title = item.dataset.title || '';
        const durationSeconds = parseInt(item.dataset.durationSeconds || '0', 10);

        this.items.forEach((entry) => entry.classList.remove('selected'));
        item.classList.add('selected');

        const radio = qs('.csa-service-radio', item);
        if (radio) {
            radio.checked = true;
        }
        if (this.select && this.select.value !== title) {
            this.select.value = title;
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
        if (this.fieldProp) {
            setFieldPropValue(this.fieldProp, formatPropValue('service', title));
        }
    }

    resetSelection() {
        this.items.forEach((entry) => {
            entry.classList.remove('selected');
            const radio = qs('.csa-service-radio', entry);
            if (radio) {
                radio.checked = false;
            }
        });
        if (this.select) {
            this.select.value = '';
        }

        if (this.form) {
            this.form.dataset.csaServiceDuration = '0';
            this.form.dataset.csaServiceTitle = '';
        }

        if (this.prop) {
            clearElementorPropValue(this.form, this.prop);
        }
        if (this.fieldProp) {
            clearFieldPropValue(this.fieldProp);
        }
    }

    updateDisabledState() {
        const hasUser = !!getUsernameFromForm(this.container);
        this.container.classList.toggle('csa-field-disabled', !hasUser);
        if (this.select) {
            this.select.disabled = !hasUser;
        }
        return hasUser;
    }
}

class ServiceFixedShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.title = container.dataset.serviceTitle || '';
        const rawDuration = parseInt(container.dataset.serviceDuration || '0', 10);
        this.durationSeconds = Number.isFinite(rawDuration) ? rawDuration : 0;
    }

    init() {
        if (!this.title) {
            return;
        }

        if (this.form) {
            this.form.dataset.csaServiceDuration = String(this.durationSeconds);
            this.form.dataset.csaServiceTitle = this.title;
            const event = new CustomEvent('csa:serviceChanged', {
                detail: { durationSeconds: this.durationSeconds, title: this.title },
                bubbles: true,
            });
            this.form.dispatchEvent(event);
        }

        if (this.prop) {
            setElementorPropValue(this.form, this.prop, formatPropValue('service', this.title));
        }
        if (this.fieldProp) {
            setFieldPropValue(this.fieldProp, formatPropValue('service', this.title));
        }
    }
}

class TimeShortcode {
    constructor(container, config) {
        this.container = container;
        this.config = config;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.username = container.dataset.user || getUsernameFromForm(container);
        this.anyoneValue = this.form ? (this.form.dataset.csaAnyoneValue || '') : '';
        this.selectLabel = (container.dataset.label || '').trim() || 'Select';
        this.calendar = qs('.csa-calendar-widget', container);
        this.calendarWrapper = qs('.csa-appointment-calendar', container);
        this.timeNotification = qs('.csa-time-notification', container);
        this.timeList = qs('.csa-appointment-time-list', container);
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
        this.resolveToken = 0;
        this.resolveInFlight = false;
        this.anyoneResolvedUsers = {};
    }

    init() {
        if (!this.calendar || !this.timeList) {
            return;
        }
        this.updateTimeNotificationMessage();

        on(this.timeList, 'click', (event) => {
            const target = event.target.closest('li[data-value]');
            if (!target || !this.timeList.contains(target)) {
                return;
            }
            this.handleTimeSelect(target);
        });
        if (this.timeSelect) {
            on(this.timeSelect, 'change', () => this.handleTimeSelect(null));
        }

        if (this.form) {
            on(this.form, 'csa:serviceChanged', () => {
                this.resetSelection();
                this.loadAvailableDays();
            });
            on(this.form, 'csa:userChanged', () => {
                this.username = getUsernameFromForm(this.container);
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

    updateTimeNotificationMessage() {
        if (!this.timeNotification) {
            return;
        }
        this.timeNotification.textContent = 'Select A Date To See Available Times.';
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
        if (!this.timeList) {
            return;
        }
        let html = '';
        if (placeholder) {
            html = `<li class="csa-time-placeholder">${placeholder}</li>`;
        }
        this.timeList.innerHTML = html;
        if (this.timeSelect) {
            this.timeSelect.innerHTML = `<option value="" disabled selected>${this.selectLabel}</option>`;
            this.timeSelect.disabled = !!disabled;
        }
        if (this.timeField) {
            this.timeField.classList.toggle('csa-field-disabled', !!disabled);
        }
    }

    resetSelection() {
        this.selectedDate = '';
        this.anyoneResolvedUsers = {};
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
        if (this.fieldProp) {
            clearFieldPropValue(this.fieldProp);
        }
        this.resetResolvedUser();
        if (this.timeList) {
            qsa('.csa-time-option', this.timeList).forEach((option) => option.classList.remove('selected'));
        }
        if (this.timeSelect) {
            this.timeSelect.value = '';
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
        const hasUser = !!getUsernameFromForm(this.container);
        const disabled = !durationSeconds || !hasUser;
        this.calendarWrapper.classList.toggle('csa-field-disabled', disabled);
    }

    updateTimeNotificationState() {
        if (!this.timeNotification) {
            return;
        }
        const hasUser = !!getUsernameFromForm(this.container);
        const hasService = this.getDurationSeconds() > 0;
        const hasDate = !!this.selectedDate;
        const active = hasUser && hasService && !hasDate;
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
        if (this.fieldProp) {
            clearFieldPropValue(this.fieldProp);
        }
        this.resetResolvedUser();
        this.updateTimeSelectState('Select a day first', true);
        this.loadAvailableDays();
    }

    async loadAvailableDays() {
        const durationSeconds = this.getDurationSeconds();
        const hasUser = !!getUsernameFromForm(this.container);
        if (!hasUser) {
            this.availableDays = new Set();
            this.renderCalendar();
            this.updateTimeSelectState('Select a user first', true);
            this.updateCalendarDisabledState();
            this.updateTimeNotificationState();
            return;
        }
        if (!durationSeconds) {
            this.availableDays = new Set();
            this.renderCalendar();
            this.updateTimeSelectState('Select a service first', true);
            this.updateCalendarDisabledState();
            this.updateTimeNotificationState();
            return;
        }

        if (this.calendarWrapper) {
            this.calendarWrapper.classList.add('csa-loading');
        }
        let monthVal = `${this.currentYear}-${String(this.currentMonth + 1).padStart(2, '0')}`;
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_days',
                month: monthVal,
                duration_seconds: durationSeconds,
                user: this.username,
            });

            if (response && response.success && response.data && Array.isArray(response.data.days)) {
                this.availableDays = new Set(response.data.days.map((day) => day.value));
                if (this.availableDays.size === 0) {
                    const next = await this.findNextAvailableMonthByDays(durationSeconds);
                    if (next) {
                        this.currentYear = next.year;
                        this.currentMonth = next.month;
                        monthVal = `${next.year}-${String(next.month + 1).padStart(2, '0')}`;
                        const retry = await postAjax(this.config.ajax_url, {
                            action: 'csa_get_available_days',
                            month: monthVal,
                            duration_seconds: durationSeconds,
                            user: this.username,
                        });
                        if (retry && retry.success && retry.data && Array.isArray(retry.data.days)) {
                            this.availableDays = new Set(retry.data.days.map((day) => day.value));
                        }
                    }
                }
            } else {
                this.availableDays = new Set();
            }
        } catch (error) {
            this.availableDays = new Set();
        }

        this.renderCalendar();
        this.updateCalendarDisabledState();
        this.updateTimeNotificationState();
        if (this.calendarWrapper) {
            this.calendarWrapper.classList.remove('csa-loading');
        }
    }

    async findNextAvailableMonthByDays(durationSeconds) {
        try {
            let year = this.currentYear;
            let monthIndex = this.currentMonth;
            for (let i = 0; i < 12; i += 1) {
                const next = this.incrementMonth(year, monthIndex);
                year = next.year;
                monthIndex = next.month;
                const monthVal = `${year}-${String(monthIndex + 1).padStart(2, '0')}`;
                const response = await postAjax(this.config.ajax_url, {
                    action: 'csa_get_available_days',
                    month: monthVal,
                    duration_seconds: durationSeconds,
                });
                if (response && response.success && response.data && Array.isArray(response.data.days) && response.data.days.length) {
                    return { year, month: monthIndex };
                }
            }
            return null;
        } catch (error) {
            return null;
        }
    }

    incrementMonth(year, monthIndex) {
        let nextMonth = monthIndex + 1;
        let nextYear = year;
        if (nextMonth > 11) {
            nextMonth = 0;
            nextYear += 1;
        }
        return { year: nextYear, month: nextMonth };
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
        if (this.fieldProp) {
            clearFieldPropValue(this.fieldProp);
        }
        this.resetResolvedUser();

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

        if (this.timeField) {
            this.timeField.classList.add('csa-loading');
        }
        this.updateTimeSelectState('Loading...', true);
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_times',
                date: dateString,
                duration_seconds: durationSeconds,
                user: this.username,
            });

            if (response && response.success && response.data && Array.isArray(response.data.times)) {
                let times = response.data.times;
                this.anyoneResolvedUsers = {};
                if (this.isAnyoneSelected() && times.length) {
                    const resolved = await this.resolveAnyoneTimes(dateString, durationSeconds, times);
                    if (Array.isArray(resolved)) {
                        times = resolved;
                        times.forEach((entry) => {
                            if (entry && entry.value && entry.username) {
                                this.anyoneResolvedUsers[entry.value] = {
                                    username: entry.username,
                                    fullName: entry.full_name || '',
                                };
                            }
                        });
                    }
                }
                if (times.length === 0) {
                    this.updateTimeSelectState('No times available', true);
                } else {
                    let html = '';
                    times.forEach((time) => {
                        html += '<li class="csa-time-option" data-value="' + time.value + '">' +
                            '<input type="radio" class="csa-appointment-time-radio" name="appointment_time_select" value="' + time.value + '" hidden />' +
                            '<span>' + time.label + '</span>' +
                            '</li>';
                    });
                    this.timeList.innerHTML = html;
                    if (this.timeSelect) {
                        let optionsHtml = `<option value="" disabled selected>${this.selectLabel}</option>`;
                        times.forEach((time) => {
                            optionsHtml += '<option value="' + time.value + '">' + time.label + '</option>';
                        });
                        this.timeSelect.innerHTML = optionsHtml;
                        this.timeSelect.disabled = false;
                    }
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
        if (this.timeField) {
            this.timeField.classList.remove('csa-loading');
        }
    }

    async resolveAnyoneTimes(dateString, durationSeconds, times) {
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_resolve_anyone_times',
                date: dateString,
                duration_seconds: durationSeconds,
                times: JSON.stringify(times),
            });
            if (response && response.success && response.data && Array.isArray(response.data.times)) {
                return response.data.times;
            }
        } catch (error) {
            // fall back to empty list
        }
        return [];
    }

    handleTimeSelect(target) {
        if (this.resolveInFlight || (this.timeField && this.timeField.classList.contains('csa-field-disabled'))) {
            return;
        }
        const timeVal = target
            ? (target.dataset.value || '')
            : (this.timeSelect ? this.timeSelect.value : '');
        if (!timeVal || !this.selectedDate) {
            return;
        }

        qsa('.csa-time-option', this.timeList).forEach((option) => {
            const isSelected = option.dataset.value === timeVal;
            option.classList.toggle('selected', isSelected);
            const input = qs('input', option);
            if (input) {
                input.checked = isSelected;
            }
        });
        if (this.timeSelect && this.timeSelect.value !== timeVal) {
            this.timeSelect.value = timeVal;
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
        if (this.fieldProp) {
            setFieldPropValue(this.fieldProp, formatPropValue('time', composite));
        }

        if (this.isAnyoneSelected()) {
            const resolved = this.anyoneResolvedUsers[timeVal];
            if (resolved && resolved.username) {
                this.setResolvedUser(resolved.username, resolved.fullName || '');
            } else {
                this.resetResolvedUser();
                this.setTimeSelectionDisabled(true);
                this.resolveAnyoneUser(this.selectedDate, timeVal);
            }
        }
    }

    isAnyoneSelected() {
        if (!this.form) {
            return false;
        }
        return this.form.dataset.csaAnyoneSelected === '1' && !!(this.form.dataset.csaAnyoneValue || '');
    }

    setResolvedUser(username, fullName = '') {
        if (!this.form || !username) {
            return;
        }
        const hidden = qs('input[name="csa_user"]', this.form);
        const hiddenForm = qs('input[name="form_fields[csa_user]"]', this.form);
        const hiddenUsername = qs('input[name="csa_username"]', this.form);
        const hiddenUsernameForm = qs('input[name="form_fields[csa_username]"]', this.form);
        if (hidden) {
            hidden.value = username;
        }
        if (hiddenForm) {
            hiddenForm.value = username;
        }
        if (hiddenUsername) {
            hiddenUsername.value = username;
        }
        if (hiddenUsernameForm) {
            hiddenUsernameForm.value = username;
        }
        const userProp = this.form.dataset.csaUserProp || '';
        const userFieldProp = this.form.dataset.csaUserFieldProp || '';
        const propValue = fullName || username;
        if (userProp) {
            setElementorPropValue(this.form, userProp, formatUserPropValue(username, propValue));
        }
        if (userFieldProp) {
            setFieldPropValue(userFieldProp, formatUserPropValue(username, propValue));
        }
        this.form.dataset.csaResolvedUser = username;
    }

    clearUserForResolve() {
        if (!this.form) {
            return;
        }
        const hidden = qs('input[name="csa_user"]', this.form);
        const hiddenForm = qs('input[name="form_fields[csa_user]"]', this.form);
        const hiddenUsername = qs('input[name="csa_username"]', this.form);
        const hiddenUsernameForm = qs('input[name="form_fields[csa_username]"]', this.form);
        if (hidden) {
            hidden.value = '';
        }
        if (hiddenForm) {
            hiddenForm.value = '';
        }
        if (hiddenUsername) {
            hiddenUsername.value = '';
        }
        if (hiddenUsernameForm) {
            hiddenUsernameForm.value = '';
        }
        const userProp = this.form.dataset.csaUserProp || '';
        const userFieldProp = this.form.dataset.csaUserFieldProp || '';
        if (userProp) {
            setElementorPropValue(this.form, userProp, formatUserPropValue('', ''));
        }
        if (userFieldProp) {
            setFieldPropValue(userFieldProp, formatUserPropValue('', ''));
        }
        delete this.form.dataset.csaResolvedUser;
    }

    resetResolvedUser() {
        if (!this.form || !this.isAnyoneSelected()) {
            return;
        }
        const anyoneValue = (this.form.dataset.csaAnyoneValue || '').trim();
        if (!anyoneValue) {
            return;
        }
        const hidden = qs('input[name="csa_user"]', this.form);
        const hiddenForm = qs('input[name="form_fields[csa_user]"]', this.form);
        const hiddenUsername = qs('input[name="csa_username"]', this.form);
        const hiddenUsernameForm = qs('input[name="form_fields[csa_username]"]', this.form);
        if (hidden) {
            hidden.value = anyoneValue;
        }
        if (hiddenForm) {
            hiddenForm.value = anyoneValue;
        }
        if (hiddenUsername) {
            hiddenUsername.value = anyoneValue;
        }
        if (hiddenUsernameForm) {
            hiddenUsernameForm.value = anyoneValue;
        }
        const userProp = this.form.dataset.csaUserProp || '';
        const userFieldProp = this.form.dataset.csaUserFieldProp || '';
        if (userProp) {
            setElementorPropValue(this.form, userProp, formatUserPropValue(anyoneValue, 'Anyone'));
        }
        if (userFieldProp) {
            setFieldPropValue(userFieldProp, formatUserPropValue(anyoneValue, 'Anyone'));
        }
        delete this.form.dataset.csaResolvedUser;
    }

    async resolveAnyoneUser(dateStr, timeStr) {
        const durationSeconds = this.getDurationSeconds();
        if (!durationSeconds) {
            return;
        }
        this.clearUserForResolve();
        this.resolveInFlight = true;
        const token = ++this.resolveToken;
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_resolve_anyone_user',
                date: dateStr,
                time: timeStr,
                duration_seconds: durationSeconds,
            });
            if (token !== this.resolveToken) {
                return;
            }
            if (response && response.success && response.data && response.data.username) {
                this.setResolvedUser(response.data.username, response.data.full_name || '');
                this.setTimeSelectionDisabled(false);
                return;
            }
        } catch (error) {
            // ignore and fall through
        }
        if (this.hiddenTime) {
            this.hiddenTime.value = '';
        }
        if (this.composite) {
            this.composite.value = '';
        }
        if (this.timeSelect) {
            this.timeSelect.value = '';
        }
        if (this.timeList) {
            qsa('.csa-time-option', this.timeList).forEach((option) => option.classList.remove('selected'));
        }
        if (this.timeNotification) {
            this.timeNotification.textContent = 'That time is no longer available. Please select another.';
        }
        this.removeTimeOption(timeStr);
        this.resolveInFlight = false;
        this.setTimeSelectionDisabled(false);
    }

    removeTimeOption(timeStr) {
        if (!timeStr) {
            return;
        }
        const normalized = timeStr.slice(0, 5);
        if (this.timeList) {
            qsa('.csa-time-option', this.timeList).forEach((option) => {
                if ((option.dataset.value || '') === normalized) {
                    option.remove();
                }
            });
            if (this.timeList.children.length === 0) {
                this.updateTimeSelectState('No times available', true);
            }
        }
        if (this.timeSelect) {
            qsa('option', this.timeSelect).forEach((option) => {
                if ((option.value || '') === normalized) {
                    option.remove();
                }
            });
            if (this.timeSelect.options.length <= 1) {
                this.timeSelect.value = '';
                this.timeSelect.disabled = true;
            }
        }
    }

    setTimeSelectionDisabled(disabled) {
        if (this.timeList) {
            this.timeList.classList.toggle('csa-field-disabled', !!disabled);
        }
        if (this.timeSelect) {
            this.timeSelect.disabled = !!disabled;
        }
        if (this.timeField) {
            this.timeField.classList.toggle('csa-field-disabled', !!disabled);
        }
        if (!disabled) {
            this.resolveInFlight = false;
            if (this.isAnyoneSelected()) {
                if (this.selectedDate && this.hiddenTime && this.hiddenTime.value && !this.form.dataset.csaResolvedUser) {
                    const timeVal = this.hiddenTime.value;
                    this.resolveAnyoneUser(this.selectedDate, timeVal);
                } else if (!this.hiddenTime || !this.hiddenTime.value) {
                    this.resetResolvedUser();
                }
            }
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
        if (type === 'service_select' || type === 'services') {
            const instance = new ServiceShortcode(container);
            instance.init();
        } else if (type === 'service') {
            const instance = new ServiceFixedShortcode(container);
            instance.init();
        } else if (type === 'user_select') {
            const instance = new UserSelectShortcode(container);
            instance.init();
        } else if (type === 'user') {
            const instance = new UserShortcode(container);
            instance.init();
        } else if (type === 'time') {
            const instance = new TimeShortcode(container, appointmentConfig);
            instance.init();
        }
    });
}
