import { formatTime } from '../../utils.js';

export class AdminWeeklyAvailabilityRenderer {
    /**
     * Render the weekly availability grid.
     *
     * @param {Object} options
     * @param {HTMLElement|null} options.container
     * @param {Object} options.weekly
     * @param {Array} options.hours
     * @returns {void}
     */
    static render({ container, weekly, hours }) {
        if (!container) {
            return;
        }

        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        let html = '<table class="csa-weekly-grid"><thead><tr><th></th>';
        days.forEach((day) => {
            html += `<th>${day}</th>`;
        });
        html += '</tr></thead><tbody>';

        (hours || []).forEach((hour) => {
            const hourLabel = typeof hour === 'string'
                ? hour
                : `${String(hour).padStart(2, '0')}:00`;
            html += `<tr><td class="hour-label">${formatTime(hourLabel)}</td>`;
            for (let d = 0; d < 7; d++) {
                const checked = weekly && weekly[d] && weekly[d].includes(hourLabel) ? 'checked' : '';
                html += `<td><input type="checkbox" class="csa-weekly-checkbox" data-day="${d}" data-hour="${hourLabel}" ${checked}></td>`;
            }
            html += '</tr>';
        });

        html += '</tbody></table>';

        container.innerHTML = html;
    }
}
