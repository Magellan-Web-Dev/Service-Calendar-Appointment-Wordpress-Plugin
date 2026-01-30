import { qs, qsa, on, postAjax } from './utils.js';
import { AppointmentFieldRenderer } from './render/shortcode/appointment-field-renderer.js';

export class AppointmentShortcode {
    /**
     * Create a shortcode controller for a container.
     *
     * @param {HTMLElement} container
     * @param {Object} config
     */
    constructor(container, config) {
        this.container = container;
        this.config = config;
        this.monthSelect = qs('.csa-appointment-month', container);
        this.daySelect = qs('.csa-appointment-day', container);
        this.timeSelect = qs('.csa-appointment-time', container);
        this.hiddenDate = qs('.csa-appointment-date-hidden', container);
        this.composite = qs('.csa-appointment-composite-hidden', container);
        this.prop = container.dataset.prop || '';
        this.form = container.closest('form');
    }

    /**
     * Initialize listeners and populate the month list.
     *
     * @returns {void}
     */
    init() {
        if (!this.monthSelect || !this.daySelect || !this.timeSelect) {
            return;
        }

        on(this.monthSelect, 'change', () => {
            const val = this.monthSelect.value;
            AppointmentFieldRenderer.render({ select: this.monthSelect, errorMessage: null });
            if (!val) {
                AppointmentFieldRenderer.render({
                    select: this.daySelect,
                    items: [],
                    placeholder: 'Select a month first',
                    disabled: true,
                });
                AppointmentFieldRenderer.render({
                    select: this.timeSelect,
                    items: [],
                    placeholder: 'Select a day first',
                    disabled: true,
                });
                if (this.hiddenDate) {
                    this.hiddenDate.value = '';
                }
                this.clearCompositeAndForm();
                return;
            }
            this.loadDays(val);
            this.clearCompositeAndForm();
        });

        on(this.daySelect, 'change', () => {
            const dayVal = this.daySelect.value;
            const monthVal = this.monthSelect.value;
            AppointmentFieldRenderer.render({ select: this.daySelect, errorMessage: null });
            if (!dayVal || !monthVal) {
                AppointmentFieldRenderer.render({
                    select: this.timeSelect,
                    items: [],
                    placeholder: 'Select a day first',
                    disabled: true,
                });
                if (this.hiddenDate) {
                    this.hiddenDate.value = '';
                }
                this.clearCompositeAndForm();
                return;
            }
            const dateStr = `${monthVal}-${dayVal}`;
            if (this.hiddenDate) {
                this.hiddenDate.value = dateStr;
            }
            this.clearCompositeAndForm();
            this.loadTimes(dateStr);
        });

        on(this.timeSelect, 'change', () => {
            AppointmentFieldRenderer.render({ select: this.timeSelect, errorMessage: null });
            this.syncCompositeAndForm();
        });

        if (this.form) {
            this.bindFormValidation();
        }

        this.loadMonths();
        AppointmentFieldRenderer.render({ select: this.daySelect, disabled: true });
        AppointmentFieldRenderer.render({ select: this.timeSelect, disabled: true });
    }

    /**
     * Bind form submit validation for appointment fields.
     *
     * @returns {void}
     */
    bindFormValidation() {
        if (this.form.dataset.csaBound) {
            return;
        }

        this.form.dataset.csaBound = 'true';
        on(this.form, 'submit', (event) => {
            const instances = qsa('.csa-appointment-field', this.form).map((container) => {
                return new AppointmentShortcode(container, this.config);
            });

            let hasErrors = false;
            instances.forEach((instance) => {
                if (!instance.monthSelect || !instance.daySelect || !instance.timeSelect) {
                    return;
                }

                if (!instance.monthSelect.value) {
                    AppointmentFieldRenderer.render({
                        select: instance.monthSelect,
                        errorMessage: 'Please select a month.',
                    });
                    hasErrors = true;
                } else {
                    AppointmentFieldRenderer.render({ select: instance.monthSelect, errorMessage: null });
                }

                if (!instance.daySelect.value) {
                    AppointmentFieldRenderer.render({
                        select: instance.daySelect,
                        errorMessage: 'Please select a day.',
                    });
                    hasErrors = true;
                } else {
                    AppointmentFieldRenderer.render({ select: instance.daySelect, errorMessage: null });
                }

                if (!instance.timeSelect.value) {
                    AppointmentFieldRenderer.render({
                        select: instance.timeSelect,
                        errorMessage: 'Please select a time.',
                    });
                    hasErrors = true;
                } else {
                    AppointmentFieldRenderer.render({ select: instance.timeSelect, errorMessage: null });
                }
            });

            if (hasErrors) {
                event.preventDefault();
            }
        });
    }

    /**
     * Load available months.
     *
     * @returns {Promise<void>}
     */
    async loadMonths() {
        AppointmentFieldRenderer.render({ select: this.monthSelect, disabled: true });
        try {
            const response = await postAjax(this.config.ajax_url, { action: 'csa_get_available_months' });
            if (response && response.success) {
                const months = response.data.months;
                AppointmentFieldRenderer.render({
                    select: this.monthSelect,
                    items: months,
                    placeholder: 'Select month',
                    disabled: months.length === 0,
                });
            }
        } catch (error) {
            AppointmentFieldRenderer.render({
                select: this.monthSelect,
                items: [],
                placeholder: 'Error loading months',
                disabled: true,
            });
        }
    }

    /**
     * Load available days for a month.
     *
     * @param {string} monthVal
     * @returns {Promise<void>}
     */
    async loadDays(monthVal) {
        AppointmentFieldRenderer.render({
            select: this.daySelect,
            items: [],
            placeholder: 'Loading...',
            disabled: true,
        });
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_days',
                month: monthVal,
            });

            if (response && response.success) {
                const days = response.data.days;
                AppointmentFieldRenderer.render({
                    select: this.daySelect,
                    items: days,
                    placeholder: 'Select day',
                    disabled: days.length === 0,
                });
            } else {
                AppointmentFieldRenderer.render({
                    select: this.daySelect,
                    items: [],
                    placeholder: 'No days available',
                    disabled: true,
                });
            }
        } catch (error) {
            AppointmentFieldRenderer.render({
                select: this.daySelect,
                items: [],
                placeholder: 'No days available',
                disabled: true,
            });
        }

        AppointmentFieldRenderer.render({
            select: this.timeSelect,
            items: [],
            placeholder: 'Select a day first',
            disabled: true,
        });
        if (this.hiddenDate) {
            this.hiddenDate.value = '';
        }
        this.clearCompositeAndForm();
    }

    /**
     * Load available times for a date.
     *
     * @param {string} dateStr
     * @returns {Promise<void>}
     */
    async loadTimes(dateStr) {
        AppointmentFieldRenderer.render({
            select: this.timeSelect,
            items: [],
            placeholder: 'Loading...',
            disabled: true,
        });
        try {
            const response = await postAjax(this.config.ajax_url, {
                action: 'csa_get_available_times',
                date: dateStr,
            });

            if (response && response.success) {
                const times = response.data.times;
                if (times.length === 0) {
                    AppointmentFieldRenderer.render({
                        select: this.timeSelect,
                        items: [],
                        placeholder: 'No times available',
                        disabled: true,
                    });
                } else {
                    AppointmentFieldRenderer.render({
                        select: this.timeSelect,
                        items: times,
                        placeholder: 'Select time',
                        disabled: false,
                    });
                }
            } else {
                AppointmentFieldRenderer.render({
                    select: this.timeSelect,
                    items: [],
                    placeholder: 'Error loading times',
                    disabled: true,
                });
            }
        } catch (error) {
            AppointmentFieldRenderer.render({
                select: this.timeSelect,
                items: [],
                placeholder: 'Error loading times',
                disabled: true,
            });
        }
    }

    /**
     * Format a composite display value from date/time.
     *
     * @param {string} dateStr
     * @param {string} timeStr
     * @returns {string}
     */
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

        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const monthName = monthNames[date.getMonth()];
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

    /**
     * Sync the composite value into hidden fields and form.
     *
     * @returns {void}
     */
    syncCompositeAndForm() {
        const dateVal = this.hiddenDate ? this.hiddenDate.value : '';
        const timeVal = this.timeSelect.value;
        const composite = this.formatComposite(dateVal, timeVal);

        if (this.composite) {
            this.composite.value = composite;
        }

        if (this.prop) {
            const form = this.container.closest('form');
            if (form) {
                const name = `form_fields[${this.prop}]`;
                console.log(`[name="${name}"]`);
                const existing = qs(`.elementor-field-group:not(.elementor-field-type-html) [name="${name}"]`, form);
                if (existing) {
                    existing.value = composite;
                } 
            }
        }
    }

    /**
     * Clear composite values in the hidden fields and form.
     *
     * @returns {void}
     */
    clearCompositeAndForm() {
        if (this.composite) {
            this.composite.value = '';
        }
        if (this.prop) {
            const form = this.container.closest('form');
            if (form) {
                const name = `form_fields[${this.prop}]`;
                const existing = qs(`.elementor-field-group:not(.elementor-field-type-html) [name="${name}"]`, form);
                if (existing) {
                    existing.value = '';
                }
            }
        }
    }
}

const appointmentConfig = window.csaAppointment || null;
if (appointmentConfig) {
    qsa('.csa-appointment-field').forEach((container) => {
        const instance = new AppointmentShortcode(container, appointmentConfig);
        instance.init();
    });
}
