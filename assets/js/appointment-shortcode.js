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

const setPropValue = (form, prop, fieldProp, value) => {
    if (prop) {
        setElementorPropValue(form, prop, value);
    }
    if (fieldProp) {
        setFieldPropValue(fieldProp, value);
    }
};

const DURATION_LABELS = {
    900: '15 minutes',
    1800: '30 minutes',
    2700: '45 minutes',
    3600: '1 hour',
    4500: '1 hour and 15 minutes',
    5400: '1 hour and 30 minutes',
    6300: '1 hour and 45 minutes',
    7200: '2 hours',
    8100: '2 hours and 15 minutes',
    9000: '2 hours and 30 minutes',
    9900: '2 hours and 45 minutes',
    10800: '3 hours',
    11700: '3 hours and 15 minutes',
    12600: '3 hours and 30 minutes',
    13500: '3 hours and 45 minutes',
    14400: '4 hours',
};

const formatDurationLabel = (seconds) => {
    if (!Number.isFinite(seconds) || seconds <= 0) {
        return '';
    }
    if (DURATION_LABELS[seconds]) {
        return DURATION_LABELS[seconds];
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} minute${minutes === 1 ? '' : 's'}`;
    }
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    if (!remaining) {
        return `${hours} hour${hours === 1 ? '' : 's'}`;
    }
    const minuteLabel = `${remaining} minute${remaining === 1 ? '' : 's'}`;
    return `${hours} hour${hours === 1 ? '' : 's'} and ${minuteLabel}`;
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

const getServiceSlugsForUser = (username) => {
    const config = window.csaAppointment || null;
    if (!config || !config.user_services) {
        return null;
    }
    const key = (username || '').trim();
    if (!key) {
        return null;
    }
    if (key in config.user_services) {
        return Array.isArray(config.user_services[key]) ? config.user_services[key] : [];
    }
    return [];
};

const getServiceUsersMap = () => {
    const config = window.csaAppointment || null;
    if (!config || !config.user_services) {
        return null;
    }
    const map = {};
    Object.entries(config.user_services).forEach(([username, slugs]) => {
        if (!Array.isArray(slugs)) {
            return;
        }
        slugs.forEach((slug) => {
            if (!slug) {
                return;
            }
            if (!map[slug]) {
                map[slug] = [];
            }
            map[slug].push(username);
        });
    });
    return map;
};

const getServiceSlugFromForm = (container) => {
    if (!container) {
        return '';
    }
    const form = container.closest('form');
    if (!form) {
        return '';
    }
    return (form.dataset.csaServiceSlug || '').trim();
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
        this.placeholder = qs('.csa-user-placeholder', container);
        this.list = qsa('.csa-user-item', container);
        this.select = qs('.csa-user-select', container);
        this.selectOptions = this.select
            ? Array.from(this.select.options).map((option) => ({
                value: option.value || '',
                label: option.textContent || '',
                fullName: option.dataset.fullName || '',
                disabled: option.disabled,
                isPlaceholder: (option.value || '') === '',
            }))
            : [];
        this.hidden = qs('.csa-user-hidden', container);
        this.hiddenForm = qs('.csa-user-hidden-form', container);
        this.hiddenUsername = qs('.csa-user-hidden-username', container);
        this.hiddenUsernameForm = qs('.csa-user-hidden-username-form', container);
        this.serviceUsersMap = getServiceUsersMap();
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

        if (this.form) {
            on(this.form, 'csa:serviceChanged', () => {
                const serviceSlug = getServiceSlugFromForm(this.container);
                this.applyUserFilter(serviceSlug);
                this.updateVisibility();
                this.updateDisabledState();
            });
        }

        this.applyUserFilter(getServiceSlugFromForm(this.container));
        this.updateVisibility();
        this.updateDisabledState();
    }

    renderUserSelect(allowedSet, showAnyone = true) {
        if (!this.select) {
            return;
        }
        const current = (this.hidden ? (this.hidden.value || '').trim() : '') || (this.select.value || '').trim();
        const options = this.selectOptions.filter((option) => {
            if (option.isPlaceholder) {
                return true;
            }
            if (!allowedSet) {
                return true;
            }
            if (!option.value) {
                return true;
            }
            if (this.anyoneValue && option.value === this.anyoneValue) {
                return !!showAnyone;
            }
            return allowedSet.has(option.value);
        });

        this.select.innerHTML = '';
        let hasCurrent = false;
        options.forEach((option) => {
            const el = document.createElement('option');
            el.value = option.value;
            el.textContent = option.label;
            if (option.fullName) {
                el.dataset.fullName = option.fullName;
            }
            if (option.disabled) {
                el.disabled = true;
            }
            if (option.value && option.value === current) {
                el.selected = true;
                hasCurrent = true;
            }
            this.select.appendChild(el);
        });

        if (!hasCurrent) {
            const placeholder = this.select.querySelector('option[value=""]');
            if (placeholder) {
                placeholder.selected = true;
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

    updateDisabledState() {
        const serviceSlug = getServiceSlugFromForm(this.container);
        const hasService = !!serviceSlug;
        const hasUsers = this.list.some((item) => item.style.display !== 'none');
        const disabled = !hasService || !hasUsers;
        this.container.classList.toggle('csa-field-disabled', disabled);
        if (this.select) {
            this.select.disabled = disabled;
        }
        return !disabled;
    }

    updateVisibility() {
        const serviceSlug = getServiceSlugFromForm(this.container);
        const hasService = !!serviceSlug;
        if (this.placeholder) {
            if (!hasService) {
                this.placeholder.style.display = '';
            }
        }
        const listContainer = this.list.length ? this.list[0].closest('.csa-user-list') : null;
        if (listContainer) {
            listContainer.style.display = hasService ? '' : 'none';
        }
        if (this.select) {
            this.select.style.display = hasService ? '' : 'none';
        }
    }

    applyUserFilter(serviceSlug) {
        if (!serviceSlug || !this.serviceUsersMap) {
            this.list.forEach((item) => {
                item.style.display = '';
            });
            this.renderUserSelect(null, true);
            if (this.placeholder) {
                this.placeholder.textContent = 'Select a service';
            }
            return;
        }

        const allowedUsers = this.serviceUsersMap[serviceSlug] || [];
        const allowedSet = new Set(allowedUsers);
        let eligibleCount = 0;
        this.list.forEach((item) => {
            const username = (item.dataset.username || '').trim();
            if (!username) {
                item.style.display = 'none';
                return;
            }
            if (this.anyoneValue && username === this.anyoneValue) {
                item.style.display = 'none';
                return;
            }
            const show = allowedSet.has(username);
            item.style.display = show ? '' : 'none';
            if (show) {
                eligibleCount += 1;
            }
        });

        let showAnyone = false;

        if (this.anyoneValue) {
            showAnyone = eligibleCount > 1;
            this.list.forEach((item) => {
                const username = (item.dataset.username || '').trim();
                if (username === this.anyoneValue) {
                    item.style.display = showAnyone ? '' : 'none';
                }
            });
        }

        this.renderUserSelect(allowedSet, showAnyone);

        if (this.placeholder) {
            if (eligibleCount === 0) {
                this.placeholder.textContent = 'No users available for this service.';
                this.placeholder.style.display = '';
            } else {
                this.placeholder.textContent = 'Select a service';
                this.placeholder.style.display = 'none';
            }
        }

        const current = this.hidden ? (this.hidden.value || '').trim() : '';
        if (current && current !== this.anyoneValue && !allowedSet.has(current)) {
            this.selectUser('');
        }
        if (current && current === this.anyoneValue && eligibleCount <= 1) {
            this.selectUser('');
        }

        if (this.form && this.form.dataset.csaAutoAnyone === '1') {
            if (eligibleCount === 1) {
                const onlyUser = allowedUsers.find((user) => user);
                if (onlyUser) {
                    this.selectUser(onlyUser, '');
                }
            } else if (eligibleCount > 1 && this.anyoneValue) {
                this.selectUser(this.anyoneValue, 'Anyone');
            }
        }
    }
}

class ServiceShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.items = qsa('.csa-service-item', container);
        this.list = qs('.csa-service-list', container);
        this.select = qs('.csa-service-select', container);
        this.placeholder = qs('.csa-service-placeholder', container);
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

        this.updateVisibility();
        this.updateDisabledState();
    }

    selectService(item) {
        if (!this.updateDisabledState()) {
            return;
        }
        const title = item.dataset.title || '';
        const slug = (item.dataset.serviceSlug || '').trim();
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
            this.form.dataset.csaServiceSlug = slug;
            const event = new CustomEvent('csa:serviceChanged', {
                detail: { durationSeconds, title, slug },
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
            this.form.dataset.csaServiceSlug = '';
        }

        if (this.prop) {
            clearElementorPropValue(this.form, this.prop);
        }
        if (this.fieldProp) {
            clearFieldPropValue(this.fieldProp);
        }
    }

    updateDisabledState() {
        const hasServices = this.items.length > 0;
        const disabled = !hasServices;
        this.container.classList.toggle('csa-field-disabled', disabled);
        if (this.select) {
            this.select.disabled = disabled;
        }
        return !disabled;
    }

    updateVisibility() {
        if (this.placeholder) {
            this.placeholder.style.display = 'none';
        }
        if (this.list) {
            this.list.style.display = '';
        }
        if (this.select) {
            this.select.style.display = '';
        }
    }
}

class ServiceFixedShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
        this.title = container.dataset.serviceTitle || '';
        this.slug = container.dataset.serviceSlug || '';
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
            this.form.dataset.csaServiceSlug = this.slug;
            const event = new CustomEvent('csa:serviceChanged', {
                detail: { durationSeconds: this.durationSeconds, title: this.title, slug: this.slug },
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

class ServiceDurationShortcode {
    constructor(container) {
        this.container = container;
        this.form = container.closest('form');
        this.prop = container.dataset.elementorProp || '';
        this.fieldProp = container.dataset.fieldProp || '';
    }

    init() {
        if (!this.form || (!this.prop && !this.fieldProp)) {
            return;
        }
        this.applyDuration();
        on(this.form, 'csa:serviceChanged', () => {
            this.applyDuration();
        });
    }

    applyDuration() {
        if (!this.form) {
            return;
        }
        const raw = (this.form.dataset.csaServiceDuration || '').trim();
        const duration = parseInt(raw, 10);
        const value = formatDurationLabel(duration);
        setPropValue(this.form, this.prop, this.fieldProp, value);
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
        if (!durationSeconds) {
            this.availableDays = new Set();
            this.renderCalendar();
            this.updateTimeSelectState('Select a service first', true);
            this.updateCalendarDisabledState();
            this.updateTimeNotificationState();
            return;
        }
        if (!hasUser) {
            this.availableDays = new Set();
            this.renderCalendar();
            this.updateTimeSelectState('Select a user first', true);
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
                service: this.form ? (this.form.dataset.csaServiceTitle || '') : '',
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
                            service: this.form ? (this.form.dataset.csaServiceTitle || '') : '',
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
                    service: this.form ? (this.form.dataset.csaServiceTitle || '') : '',
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
                service: this.form ? (this.form.dataset.csaServiceTitle || '') : '',
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
                service: this.form ? (this.form.dataset.csaServiceTitle || '') : '',
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
                service: this.form ? (this.form.dataset.csaServiceTitle || '') : '',
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
        } else if (type === 'service_duration') {
            const instance = new ServiceDurationShortcode(container);
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
