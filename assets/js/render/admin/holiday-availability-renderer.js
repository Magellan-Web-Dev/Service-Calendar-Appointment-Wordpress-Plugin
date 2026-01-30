export class AdminHolidayAvailabilityRenderer {
    /**
     * Render holiday availability checkboxes.
     *
     * @param {Object} options
     * @param {HTMLElement|null} options.container
     * @param {Array} options.holidays
     * @param {Array} options.enabled
     * @returns {void}
     */
    static render({ container, holidays, enabled }) {
        if (!container) {
            return;
        }

        const enabledSet = new Set(Array.isArray(enabled) ? enabled : []);
        const list = Array.isArray(holidays) ? holidays : [];

        let html = '<div class="csa-holiday-list">';
        list.forEach((holiday) => {
            const key = holiday.key;
            const label = holiday.label || key;
            const dateLabel = holiday.date_label ? ` (${holiday.date_label})` : '';
            const checked = enabledSet.has(key) ? 'checked' : '';
            html += '<label class="csa-holiday-item">' +
                `<input type="checkbox" class="csa-holiday-checkbox" data-key="${key}" ${checked}>` +
                `<span>${label}${dateLabel}</span>` +
                '</label>';
        });
        html += '</div>';

        container.innerHTML = html;
    }
}
