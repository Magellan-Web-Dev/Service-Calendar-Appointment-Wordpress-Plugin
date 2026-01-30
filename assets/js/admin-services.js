import { scrollToTopOnLoad } from './utils.js';

(() => {
    const list = document.getElementById('csa-services-list');
    const addButton = document.getElementById('csa-add-service');
    const template = document.getElementById('csa-service-template');

    if (!list || !addButton || !template) {
        return;
    }

    const getNextIndex = () => {
        const raw = list.getAttribute('data-next-index');
        const parsed = Number.parseInt(raw, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const setNextIndex = (value) => {
        list.setAttribute('data-next-index', String(value));
    };

    const addServiceItem = () => {
        const nextIndex = getNextIndex();
        const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const item = wrapper.firstElementChild;
        if (!item) {
            return;
        }
        list.appendChild(item);
        setNextIndex(nextIndex + 1);
    };

    addButton.addEventListener('click', (event) => {
        event.preventDefault();
        addServiceItem();
    });

    scrollToTopOnLoad();

    list.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.classList.contains('csa-remove-service')) {
            event.preventDefault();
            const item = target.closest('.csa-service-item');
            if (item) {
                item.remove();
            }
        }
    });
})();
