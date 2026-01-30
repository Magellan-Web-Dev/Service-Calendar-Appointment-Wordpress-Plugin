import { qs } from '../../utils.js';

export class AdminDaySummaryRenderer {
    /**
     * Render day summary counts into the calendar grid.
     *
     * @param {Object} options
     * @param {Object} options.data
     * @param {HTMLElement|null} options.cell
     * @returns {void}
     */
    static render({ data, cell }) {
        if (!data || !data.date || !cell) {
            return;
        }

        let appointmentCount = 0;
        let blockedCount = 0;

        if (data.time_slots && data.time_slots.length) {
            data.time_slots.forEach((slot) => {
                if (slot.appointments && slot.appointments.length) {
                    appointmentCount += slot.appointments.length;
                }
                if (slot.is_blocked_explicit) {
                    blockedCount += 1;
                }
            });
        }

        let apptInfo = qs('.day-info.appointments', cell);
        const noneInfo = qs('.day-info.none', cell);

        if (appointmentCount > 0) {
            if (!apptInfo) {
                if (noneInfo) {
                    noneInfo.remove();
                }
                cell.insertAdjacentHTML('beforeend', '<div class="day-info appointments"></div>');
                apptInfo = qs('.day-info.appointments', cell);
            }
            apptInfo.textContent = `${appointmentCount} ${appointmentCount === 1 ? 'appointment' : 'appointment(s)'}`;
        } else {
            if (apptInfo) {
                apptInfo.remove();
            }
            if (!noneInfo) {
                cell.insertAdjacentHTML('beforeend', '<div class="day-info none">No appointments booked</div>');
            }
        }

        let blockedInfo = qs('.day-info.blocked', cell);
        if (blockedCount > 0) {
            if (!blockedInfo) {
                cell.insertAdjacentHTML('beforeend', '<div class="day-info blocked"></div>');
                blockedInfo = qs('.day-info.blocked', cell);
            }

            const totalSlots = data.time_slots ? data.time_slots.length : 0;
            if (appointmentCount === 0 && totalSlots > 0 && blockedCount === totalSlots) {
                blockedInfo.textContent = 'Day fully blocked';
            } else {
                blockedInfo.textContent = `${blockedCount} blocked`;
            }
        } else if (blockedInfo) {
            blockedInfo.remove();
        }
    }
}
