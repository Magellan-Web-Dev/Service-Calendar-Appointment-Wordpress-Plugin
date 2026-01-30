import { formatTime, qs } from '../../utils.js';

export class AdminDayDetailsRenderer {
    /**
     * Render the day details modal content.
     *
     * @param {Object} options
     * @param {Object} options.data
     * @param {HTMLElement|null} options.timeSlots
     * @param {HTMLElement|null} options.modalDate
     * @param {HTMLElement|null} options.modalTimezone
     * @param {string} [options.timezoneLabel]
     * @param {Object} [options.serviceDurationMap]
     * @param {string} [options.timezoneName]
     * @param {Object} [options.rescheduleState]
     * @returns {void}
     */
    static render({ data, timeSlots, modalDate, modalTimezone, timezoneLabel, serviceDurationMap, timezoneName, rescheduleState }) {
        if (!data) {
            return;
        }

        const durationMap = normalizeDurationMap(serviceDurationMap || {});
        const occupiedTimes = buildOccupiedTimes(data.time_slots || [], durationMap);
        applyAppointmentDurations(data.time_slots || [], durationMap);
        const isReschedule = !!(rescheduleState && rescheduleState.active);

        const formattedDate = new Date(`${data.date}T00:00:00`).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });

        if (modalDate) {
            modalDate.textContent = formattedDate;
        }
        if (modalTimezone) {
            const label = timezoneLabel || data.timezone_label || '';
            modalTimezone.textContent = label ? `Times shown are based off the ${label} time zone` : '';
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
            if (!isReschedule) {
                timeSlots.insertAdjacentElement('beforebegin', controls);
            }
            const slotsHtml = (data.time_slots || [])
                .map((slot) => renderSlot(slot, data.date, occupiedTimes, timezoneName, isReschedule))
                .join('');
            timeSlots.innerHTML = slotsHtml;
        }
    }
}

const renderSlot = (slot, date, occupiedTimes, timezoneName, isReschedule) => {
    if (slot.is_occupied) {
        return '';
    }
        if (!slot.appointments && occupiedTimes && occupiedTimes.has(slot.time)) {
            return '';
        }
    const timeFormatted = safeFormatTime(slot.time);
    let rangeLabel = timeFormatted;
    let slotClass = '';
    let statusHtml = '';
    let actionsHtml = '';

    let createdLabel = '';
    if (slot.appointments) {
        slotClass = 'booked';
        const first = slot.appointments;
        const serviceLabel = first.service || '';
        createdLabel = first.submitted_at_unix
            ? formatTimestamp(first.submitted_at_unix, timezoneName)
            : (first.created_at ? formatTimestamp(first.created_at, timezoneName) : '');
        const startRaw = first.time || slot.time;
        const startLabel = safeFormatTime(startRaw);
        let endLabel = safeFormatTime(first.end_time);
        if (!endLabel && first.duration_seconds && startRaw) {
            endLabel = safeFormatTime(addMinutesToTime(startRaw, Math.round(first.duration_seconds / 60)));
        }
        rangeLabel = startLabel && endLabel ? `${startLabel} - ${endLabel}` : (startLabel || timeFormatted);
        statusHtml = '<div class="csa-time-slot-info">' +
            `<div class="csa-time-slot-customer">${first.name || ''}</div>` +
            '<div class="csa-time-slot-contact">' +
            `${first.email || ''}${first.phone ? ` | ${first.phone}` : ''}` +
            '</div>' +
            '</div>' +
            `<div class="csa-time-slot-meta">` +
            `${serviceLabel ? `<div class="csa-time-slot-service"><strong>Service:</strong> ${serviceLabel}</div>` : ''}` +
            '</div>' +
            `<span class="csa-time-slot-status ${first.status || 'booked'}">${first.status || 'booked'}</span>`;
        actionsHtml = isReschedule ? '' : `<button class="csa-btn csa-btn-view" data-time="${slot.time}">View</button>`;
    } else if (slot.is_blocked_explicit) {
        slotClass = 'blocked';
        statusHtml = '<div class="csa-time-slot-info"><em>Blocked by admin</em></div>';
        actionsHtml = isReschedule ? '' : `<button class="csa-btn csa-btn-unblock" data-date="${date}" data-time="${slot.time}">Unblock</button>`;
    } else if (!slot.is_default_available) {
        slotClass = 'unavailable';
        statusHtml = '<div class="csa-time-slot-info"><em>Unavailable (default)</em></div>';
        actionsHtml = isReschedule ? '' : `<button class="csa-btn csa-btn-allow" data-date="${date}" data-time="${slot.time}">Allow</button>`;
    } else {
        statusHtml = '<div class="csa-time-slot-info"><em>Available</em></div>';
        if (isReschedule) {
            actionsHtml = `<button class="csa-btn csa-btn-reschedule-slot" data-date="${date}" data-time="${slot.time}">Reschedule here</button>`;
        } else {
            actionsHtml = `<button class="csa-btn csa-btn-block" data-date="${date}" data-time="${slot.time}">Block</button>`;
        }
    }

    let appointmentListHtml = '';
    if (slot.appointments) {
        const listItem = renderAppointment(slot.appointments, date);
        appointmentListHtml = `<div class="csa-appointment-list" style="display:none;">${listItem}</div>`;
    }

    const rescheduleClass = isReschedule && slot.appointments ? ' csa-reschedule-disabled' : '';
    const headerHtml = `<div class="csa-time-slot-header">` +
        `<div class="csa-time-slot-time">${slotClass === 'booked' ? rangeLabel : timeFormatted}</div>` +
        `${createdLabel ? `<div class="csa-time-slot-created"><strong>Submitted:</strong> ${createdLabel}</div>` : ''}` +
        `</div>`;
    return `<div class="csa-time-slot ${slotClass}${rescheduleClass}" data-time="${slot.time}">` +
        headerHtml +
        statusHtml +
        `<div class="csa-time-slot-actions">${actionsHtml}</div>` +
        appointmentListHtml +
        '</div>';
};

const renderAppointment = (appt, date) => {
    let metaHtml = '';
    if (appt.id) {
        metaHtml += ` - <a href="admin.php?page=e-form-submissions&action=view&id=${appt.id}" target="_blank">Open submission</a>`;
    }

    let allDataHtml = '';
    if (appt.all_data && Object.keys(appt.all_data).length > 0) {
        const keys = Object.keys(appt.all_data);
        const serviceKeys = keys.filter((key) => isServiceField(key));
        const otherKeys = keys.filter((key) => !isServiceField(key) && !isTimeField(key));
        const ordered = [...serviceKeys, ...otherKeys];
        const fields = ordered
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
        `<div class="csa-appointment-actions">` +
        `<button class="csa-btn csa-btn-reschedule" data-appt-id="${appt.appt_id || ''}" data-duration="${appt.duration_seconds || ''}">Reschedule</button>` +
        `<button class="csa-btn csa-btn-delete"${deleteData} data-date="${date}" data-time="${appt.time}">Delete</button>` +
        '</div>' +
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

const normalizeKey = (key) => {
    return key.toLowerCase().replace(/\\s+/g, '').replace(/[_-]+/g, '');
};

const isServiceField = (key) => {
    if (!key) {
        return false;
    }
    if (key === 'csa_service') {
        return true;
    }
    const normalized = normalizeKey(key);
    return normalized.includes('service');
};

const isTimeField = (key) => {
    if (!key) {
        return false;
    }
    const normalized = normalizeKey(key);
    if (normalized.includes('appointment') && normalized.includes('time')) {
        return true;
    }
    if (normalized.includes('appointment') && normalized.includes('date')) {
        return true;
    }
    if (normalized.includes('time') && normalized.includes('slot')) {
        return true;
    }
    if (normalized === 'time' || normalized.endsWith('time')) {
        return true;
    }
    return false;
};

const formatTimestamp = (value, timezoneName) => {
    if (!value) {
        return '';
    }
    let date;
    if (typeof value === 'number' && Number.isFinite(value)) {
        date = new Date(value * 1000);
    } else if (typeof value === 'string' && /^\d{10,}$/.test(value)) {
        date = new Date(Number(value) * 1000);
    } else {
        const normalized = typeof value === 'string' ? value.replace(' ', 'T') : String(value);
        date = new Date(normalized);
    }
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    };
    if (timezoneName) {
        return new Intl.DateTimeFormat('en-US', { ...options, timeZone: timezoneName }).format(date);
    }
    return date.toLocaleString('en-US', options);
};

const safeFormatTime = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }
    const normalized = value.trim();
    if (!normalized) {
        return '';
    }
    const timePart = normalized.includes('T') ? normalized.split('T')[1] : normalized;
    return formatTime(timePart.slice(0, 5));
};

const addMinutesToTime = (value, minutesToAdd) => {
    if (!value || typeof value !== 'string' || !Number.isFinite(minutesToAdd)) {
        return '';
    }
    const normalized = value.trim();
    const timePart = normalized.includes('T') ? normalized.split('T')[1] : normalized;
    const pieces = timePart.split(':');
    if (pieces.length < 2) {
        return '';
    }
    const hours = parseInt(pieces[0], 10);
    const minutes = parseInt(pieces[1], 10);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return '';
    }
    const total = hours * 60 + minutes + minutesToAdd;
    const nextHours = Math.floor(total / 60);
    const nextMinutes = total % 60;
    return `${String(nextHours).padStart(2, '0')}:${String(nextMinutes).padStart(2, '0')}`;
};

const applyAppointmentDurations = (timeSlots, serviceDurationMap) => {
    timeSlots.forEach((slot) => {
        if (!slot.appointments) {
            return;
        }
        const appt = slot.appointments;
        if (!appt || appt.duration_seconds) {
            return;
        }
        const duration = getAppointmentDurationSeconds(appt, serviceDurationMap);
        if (duration > 0) {
            appt.duration_seconds = duration;
            if (!appt.end_time) {
                const start = appt.time || slot.time;
                if (start) {
                    appt.end_time = addMinutesToTime(start, Math.round(duration / 60));
                }
            }
        }
    });
};

const buildOccupiedTimes = (timeSlots, serviceDurationMap) => {
    const occupied = new Set();
    timeSlots.forEach((slot) => {
        if (!slot.appointments) {
            return;
        }
        const appt = slot.appointments;
        const duration = getAppointmentDurationSeconds(appt, serviceDurationMap);
        if (!duration) {
            return;
        }
        const slotsNeeded = Math.ceil(duration / 1800);
        const start = appt.time || slot.time;
        for (let i = 1; i < slotsNeeded; i += 1) {
            const next = addMinutesToTime(start, 30 * i);
            if (next) {
                occupied.add(next);
            }
        }
    });
    return occupied;
};

const getAppointmentDurationSeconds = (appointment, serviceDurationMap) => {
    if (!appointment) {
        return 0;
    }
    if (appointment.duration_seconds && !Number.isNaN(Number(appointment.duration_seconds))) {
        return Number(appointment.duration_seconds);
    }
    if (appointment.service) {
        const serviceTitle = normalizeServiceTitle(appointment.service);
        if (serviceTitle && serviceDurationMap[serviceTitle]) {
            return Number(serviceDurationMap[serviceTitle]);
        }
    }
    if (appointment.all_data && typeof appointment.all_data === 'object') {
        if (appointment.all_data.csa_service) {
            const serviceTitle = normalizeServiceTitle(appointment.all_data.csa_service);
            if (serviceTitle && serviceDurationMap[serviceTitle]) {
                return Number(serviceDurationMap[serviceTitle]);
            }
        }
        for (const value of Object.values(appointment.all_data)) {
            const serviceTitle = extractServiceFromValue(value);
            if (serviceTitle && serviceDurationMap[serviceTitle]) {
                return Number(serviceDurationMap[serviceTitle]);
            }
            const maybeDuration = extractDurationSeconds(value);
            if (maybeDuration) {
                return maybeDuration;
            }
        }
    }
    return 0;
};

const normalizeServiceTitle = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }
    let cleaned = value.trim();
    if (!cleaned) {
        return '';
    }
    if (cleaned.toLowerCase().startsWith('csa::service') && cleaned.includes('-->')) {
        cleaned = cleaned.split('-->')[1].trim();
    } else if (cleaned.includes('-->')) {
        cleaned = cleaned.split('-->')[1].trim();
    }
    return normalizeKey(cleaned);
};

const extractServiceFromValue = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }
    const cleaned = value.trim();
    if (!cleaned) {
        return '';
    }
    if (cleaned.toLowerCase().startsWith('csa::service') && cleaned.includes('-->')) {
        return normalizeKey(cleaned.split('-->')[1].trim());
    }
    return '';
};

const extractDurationSeconds = (value) => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value > 0 ? value : 0;
    }
    if (!value || typeof value !== 'string') {
        return 0;
    }
    const trimmed = value.trim();
    if (!trimmed) {
        return 0;
    }
    if (/^\\d+$/.test(trimmed)) {
        const parsed = Number(trimmed);
        return parsed > 0 ? parsed : 0;
    }
    return 0;
};

const normalizeDurationMap = (map) => {
    const normalized = {};
    Object.keys(map || {}).forEach((key) => {
        const normalizedKey = normalizeKey(key);
        if (normalizedKey) {
            normalized[normalizedKey] = Number(map[key]);
        }
    });
    return normalized;
};
