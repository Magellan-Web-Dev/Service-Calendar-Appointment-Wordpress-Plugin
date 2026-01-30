export class AppointmentFieldRenderer {
    /**
     * Render select options and field errors for the appointment shortcode.
     *
     * @param {Object} options
     * @param {HTMLSelectElement|null} options.select
     * @param {Array|null} options.items
     * @param {string|null} options.placeholder
     * @param {boolean|null} options.disabled
     * @param {string|null|undefined} options.errorMessage
     * @returns {void}
     */
    static render({ select, items, placeholder, disabled, errorMessage }) {
        if (!select) {
            return;
        }

        if (Array.isArray(items) || typeof placeholder === 'string') {
            let html = '';
            if (placeholder) {
                html += `<option value="" disabled selected>${placeholder}</option>`;
            }
            if (Array.isArray(items)) {
                items.forEach((item) => {
                    html += `<option value="${item.value}">${item.label}</option>`;
                });
            }
            select.innerHTML = html;
        }

        if (typeof disabled === 'boolean') {
            select.classList.toggle('disabled', disabled);
            select.disabled = disabled;
        }

        if (errorMessage !== undefined) {
            const existing = select.parentElement.querySelector('.csa-field-error');
            if (errorMessage) {
                if (existing) {
                    existing.textContent = errorMessage;
                } else {
                    const error = document.createElement('div');
                    error.className = 'csa-field-error';
                    error.textContent = errorMessage;
                    select.insertAdjacentElement('afterend', error);
                }
            } else if (existing) {
                existing.remove();
            }
        }
    }
}
