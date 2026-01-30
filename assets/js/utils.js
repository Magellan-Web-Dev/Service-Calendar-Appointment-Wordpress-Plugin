/**
 * Query a single element with an optional root.
 *
 * @param {string} selector
 * @param {ParentNode} [root=document]
 * @returns {Element|null}
 */
export const qs = (selector, root = document) => root.querySelector(selector);

/**
 * Query all matching elements with an optional root.
 *
 * @param {string} selector
 * @param {ParentNode} [root=document]
 * @returns {Element[]}
 */
export const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

/**
 * Attach a DOM event listener.
 *
 * @param {EventTarget} element
 * @param {string} eventName
 * @param {Function} handler
 * @param {Object|boolean} [options]
 * @returns {void}
 */
export const on = (element, eventName, handler, options) => {
    element.addEventListener(eventName, handler, options);
};

/**
 * Attach a delegated event listener.
 *
 * @param {Element|Document} root
 * @param {string} eventName
 * @param {string} selector
 * @param {Function} handler
 * @returns {void}
 */
export const delegate = (root, eventName, selector, handler) => {
    root.addEventListener(eventName, (event) => {
        const target = event.target.closest(selector);
        if (target && root.contains(target)) {
            handler(event, target);
        }
    });
};

/**
 * Post form-encoded data and parse JSON response.
 *
 * @param {string} url
 * @param {Object} data
 * @returns {Promise<Object>}
 */
export const postAjax = async (url, data) => {
    const body = new URLSearchParams();
    Object.keys(data).forEach((key) => {
        if (data[key] !== undefined && data[key] !== null) {
            body.append(key, data[key]);
        }
    });

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
    });

    if (!response.ok) {
        throw new Error('Request failed');
    }

    return response.json();
};

/**
 * Format HH:MM into h:MM AM/PM.
 *
 * @param {string} time
 * @returns {string}
 */
export const formatTime = (time) => {
    const parts = time.split(':');
    let hour = parseInt(parts[0], 10);
    const minute = parts[1];
    const period = hour >= 12 ? 'PM' : 'AM';

    if (hour > 12) {
        hour -= 12;
    } else if (hour === 0) {
        hour = 12;
    }

    return `${hour}:${minute} ${period}`;
};
