import { formatTime, qs } from '../../utils.js';

export class AdminDayDetailsRenderer {
    /**
     * Render the day details modal content.
     *
     * @param {Object} options
     * @param {Object} options.data
     * @param {HTMLElement|null} options.timeSlots
     * @param {HTMLElement|null} options.modalDate
     * @returns {void}
     */
    static render({ data, timeSlots, modalDate }) {
        if (!data) {
            return;
        }

        const formattedDate = new Date(`${data.date}T00:00:00`).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });

        if (modalDate) {
            modalDate.textContent = formattedDate;
        }

        const existingControls = qs('#csa-day-controls');
        if (existingControls) {
            existingControls.remove();
        }

        if (timeSlots) {
            const controls = document.createElement('div');
            controls.id = 'csa-day-controls';
            controls.className = 'csa-day-controls';
            controls.style.marginBottom = '10px';
            controls.dataset.date = data.date;
            controls.innerHTML = '<button id="csa-block-all" class="csa-btn">Block All</button> ' +
                '<button id="csa-unblock-all" class="csa-btn">Unblock All</button>';
            timeSlots.insertAdjacentElement('beforebegin', controls);

            const slotsHtml = (data.time_slots || []).map((slot) => renderSlot(slot, data.date)).join('');
            timeSlots.innerHTML = slotsHtml;
        }
    }
}

const renderSlot = (slot, date) => {
    const timeFormatted = formatTime(slot.time);
    let slotClass = '';
    let statusHtml = '';
    let actionsHtml = '';

    if (slot.appointments && slot.appointments.length > 0) {
        slotClass = 'booked';
        const first = slot.appointments[0];
        statusHtml = '<div class="csa-time-slot-info">' +
            `<div class="csa-time-slot-customer">${first.name || ''}</div>` +
            '<div class="csa-time-slot-contact">' +
            `${first.email || ''}${first.phone ? ` | ${first.phone}` : ''}` +
            '</div>' +
            '</div>' +
            `<span class="csa-time-slot-status ${first.status || 'booked'}">${first.status || 'booked'}</span>`;
        actionsHtml = `<button class="csa-btn csa-btn-view" data-time="${slot.time}">View</button>`;
    } else if (slot.is_blocked_explicit) {
        slotClass = 'blocked';
        statusHtml = '<div class="csa-time-slot-info"><em>Blocked by admin</em></div>';
        actionsHtml = `<button class="csa-btn csa-btn-unblock" data-date="${date}" data-time="${slot.time}">Unblock</button>`;
    } else if (!slot.is_default_available) {
        slotClass = 'unavailable';
        statusHtml = '<div class="csa-time-slot-info"><em>Unavailable (default)</em></div>';
        actionsHtml = `<button class="csa-btn csa-btn-allow" data-date="${date}" data-time="${slot.time}">Allow</button>`;
    } else {
        statusHtml = '<div class="csa-time-slot-info"><em>Available</em></div>';
        actionsHtml = `<button class="csa-btn csa-btn-block" data-date="${date}" data-time="${slot.time}">Block</button>`;
    }

    let appointmentListHtml = '';
    if (slot.appointments && slot.appointments.length > 0) {
        const listItems = slot.appointments.map((appt) => renderAppointment(appt, date)).join('');
        appointmentListHtml = `<div class="csa-appointment-list" style="display:none;">${listItems}</div>`;
    }

    return `<div class="csa-time-slot ${slotClass}" data-time="${slot.time}">` +
        `<div class="csa-time-slot-time">${timeFormatted}</div>` +
        statusHtml +
        `<div class="csa-time-slot-actions">${actionsHtml}</div>` +
        appointmentListHtml +
        '</div>';
};

const renderAppointment = (appt, date) => {
    let metaHtml = appt.created_at ? `${appt.created_at}` : '';
    if (appt.id) {
        metaHtml += ` - <a href="admin.php?page=e-form-submissions&action=view&id=${appt.id}" target="_blank">Open submission</a>`;
    }

    let allDataHtml = '';
    if (appt.all_data && Object.keys(appt.all_data).length > 0) {
        const fields = Object.keys(appt.all_data)
            .map((key) => `<div class="csa-appointment-field"><strong>${formatFieldLabel(key)}:</strong> ${appt.all_data[key] || ''}</div>`)
            .join('');
        allDataHtml = `<div class="csa-appointment-all-data">${fields}</div>`;
    } else if (appt.id) {
        allDataHtml = `<div class="csa-appointment-all-data csa-loading" data-submission-id="${appt.id}">Loading submission fields...</div>`;
    }

    const apptRecordId = appt.appt_id ? appt.appt_id : null;
    let deleteData = '';
    if (apptRecordId) {
        deleteData = ` data-appt-id="${apptRecordId}"`;
    } else if (appt.id) {
        deleteData = ` data-submission-id="${appt.id}"`;
    }

    return '<div class="csa-appointment-item">' +
        '<div class="csa-appointment-left">' +
        `<div class="csa-appointment-name">${appt.name || ''}</div>` +
        `<div class="csa-appointment-contact">${appt.email || ''}${appt.phone ? ` | ${appt.phone}` : ''}</div>` +
        `<div class="csa-appointment-meta">${metaHtml}</div>` +
        allDataHtml +
        '</div>' +
        `<div class="csa-appointment-actions"><button class="csa-btn csa-btn-delete"${deleteData} data-date="${date}" data-time="${appt.time}">Delete</button></div>` +
        '</div>';
};

const formatFieldLabel = (key) => {
    if (!key) {
        return '';
    }
    const withSpaces = key
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim();
    return withSpaces
        .split(' ')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};
