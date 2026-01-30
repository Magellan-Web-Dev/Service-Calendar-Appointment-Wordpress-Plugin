export class AdminSubmissionFieldsRenderer {
    /**
     * Render submission field values for an appointment.
     *
     * @param {Object} options
     * @param {HTMLElement} options.element
     * @param {Object|null} options.values
     * @param {string|null} options.fallback
     * @returns {void}
     */
    static render({ element, values, fallback }) {
        if (!element) {
            return;
        }

        if (values && Object.keys(values).length > 0) {
            const html = Object.keys(values)
                .map((key) => `<div class="csa-appointment-field"><strong>${formatFieldLabel(key)}:</strong> ${values[key] || ''}</div>`)
                .join('');
            element.classList.remove('csa-loading');
            element.innerHTML = html;
            return;
        }

        element.classList.remove('csa-loading');
        element.innerHTML = `<em>${fallback || 'Fields not available'}</em>`;
    }
}

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
