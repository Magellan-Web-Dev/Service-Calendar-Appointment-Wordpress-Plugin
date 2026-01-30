<?php
/**
 * Admin services page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class ServicesPage {

    /**
     * Render the admin services page.
     *
     * @param array $context
     * @return void
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : '';
        $services = isset($context['services']) && is_array($context['services']) ? $context['services'] : [];
        $duration_options = isset($context['duration_options']) && is_array($context['duration_options'])
            ? $context['duration_options']
            : [];
        $saved = isset($context['saved']) ? (bool) $context['saved'] : false;

        if (empty($services)) {
            $services = [[]];
        }

        $next_index = count($services);

        ?>
        <div class="wrap csa-services-page">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Services updated.', $text_domain); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('csa_save_services', 'csa_services_nonce'); ?>
                <input type="hidden" name="action" value="csa_save_services" />

                <div id="csa-services-list" data-next-index="<?php echo esc_attr($next_index); ?>">
                    <?php foreach ($services as $index => $service) :
                        $title = isset($service['title']) ? $service['title'] : '';
                        $sub_heading = isset($service['sub_heading']) ? $service['sub_heading'] : '';
                        $duration = isset($service['duration']) ? $service['duration'] : '';
                        $description = isset($service['description']) ? $service['description'] : '';
                        ?>
                        <div class="csa-service-item">
                            <div class="csa-service-item-header">
                                <h2><?php echo esc_html__('Service', $text_domain); ?></h2>
                                <button type="button" class="button-link-delete csa-remove-service"><?php esc_html_e('Remove', $text_domain); ?></button>
                            </div>

                            <p>
                                <label for="csa-service-title-<?php echo esc_attr($index); ?>"><strong><?php esc_html_e('Title', $text_domain); ?></strong></label><br />
                                <input class="regular-text" type="text" id="csa-service-title-<?php echo esc_attr($index); ?>" name="services[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($title); ?>" />
                            </p>

                            <p>
                                <label for="csa-service-sub-heading-<?php echo esc_attr($index); ?>"><strong><?php esc_html_e('Sub heading', $text_domain); ?></strong></label><br />
                                <input class="regular-text" type="text" id="csa-service-sub-heading-<?php echo esc_attr($index); ?>" name="services[<?php echo esc_attr($index); ?>][sub_heading]" value="<?php echo esc_attr($sub_heading); ?>" />
                            </p>

                            <p>
                                <label for="csa-service-duration-<?php echo esc_attr($index); ?>"><strong><?php esc_html_e('Duration', $text_domain); ?></strong></label><br />
                                <select id="csa-service-duration-<?php echo esc_attr($index); ?>" name="services[<?php echo esc_attr($index); ?>][duration]">
                                    <option value=""><?php esc_html_e('Select duration', $text_domain); ?></option>
                                    <?php foreach ($duration_options as $value => $option) :
                                        $selected = ($duration === (string) $value || $duration === $option) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p>
                                <label for="csa-service-description-<?php echo esc_attr($index); ?>"><strong><?php esc_html_e('Description', $text_domain); ?></strong></label><br />
                                <textarea class="large-text" rows="4" id="csa-service-description-<?php echo esc_attr($index); ?>" name="services[<?php echo esc_attr($index); ?>][description]"><?php echo esc_textarea($description); ?></textarea>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button" id="csa-add-service"><?php esc_html_e('Add Service', $text_domain); ?></button>
                </p>

                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Services', $text_domain); ?></button>
                </p>
            </form>

            <template id="csa-service-template">
                <div class="csa-service-item">
                    <div class="csa-service-item-header">
                        <h2><?php echo esc_html__('Service', $text_domain); ?></h2>
                        <button type="button" class="button-link-delete csa-remove-service"><?php esc_html_e('Remove', $text_domain); ?></button>
                    </div>

                    <p>
                        <label for="csa-service-title-__INDEX__"><strong><?php esc_html_e('Title', $text_domain); ?></strong></label><br />
                        <input class="regular-text" type="text" id="csa-service-title-__INDEX__" name="services[__INDEX__][title]" value="" />
                    </p>

                    <p>
                        <label for="csa-service-sub-heading-__INDEX__"><strong><?php esc_html_e('Sub heading', $text_domain); ?></strong></label><br />
                        <input class="regular-text" type="text" id="csa-service-sub-heading-__INDEX__" name="services[__INDEX__][sub_heading]" value="" />
                    </p>

                    <p>
                        <label for="csa-service-duration-__INDEX__"><strong><?php esc_html_e('Duration', $text_domain); ?></strong></label><br />
                        <select id="csa-service-duration-__INDEX__" name="services[__INDEX__][duration]">
                            <option value=""><?php esc_html_e('Select duration', $text_domain); ?></option>
                            <?php foreach ($duration_options as $value => $option) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="csa-service-description-__INDEX__"><strong><?php esc_html_e('Description', $text_domain); ?></strong></label><br />
                        <textarea class="large-text" rows="4" id="csa-service-description-__INDEX__" name="services[__INDEX__][description]"></textarea>
                    </p>
                </div>
            </template>
        </div>
        <?php
    }
}
