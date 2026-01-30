import { on } from './utils.js';

export class ElementorEditorIntegration {
    /**
     * Initialize the editor integration once Elementor is ready.
     *
     * @returns {void}
     */
    init() {
        this.interval = window.setInterval(() => {
            if (window.elementor && window.elementor.channels && window.elementor.channels.data) {
                window.clearInterval(this.interval);
                this.registerFieldType();
            }
        }, 200);
    }

    /**
     * Register the custom appointment field type.
     *
     * @returns {void}
     */
    registerFieldType() {
        try {
            if (!window.elementor) {
                return;
            }

            if (window.elementor.hooks && window.elementor.hooks.addFilter) {
                window.elementor.hooks.addFilter('elementor_pro/forms/fields_definition', (fields) => {
                    const nextFields = fields || [];
                    const exists = nextFields.some((field) => field && field.type === 'csa_appointment');
                    if (!exists) {
                        nextFields.push({
                            type: 'csa_appointment',
                            title: 'Appointment',
                            icon: 'eicon-calendar',
                            category: 'advanced',
                        });
                    }
                    return nextFields;
                });
            }

            if (window.elementor.modules && window.elementor.modules.panel && window.elementor.modules.panel.$views) {
                // best-effort: nothing destructive
            }
        } catch (error) {
            if (window.console && window.console.error) {
                window.console.error('CSA editor registration error', error);
            }
        }
    }
}

const elementorEditorIntegration = new ElementorEditorIntegration();
on(window, 'load', () => elementorEditorIntegration.init());
