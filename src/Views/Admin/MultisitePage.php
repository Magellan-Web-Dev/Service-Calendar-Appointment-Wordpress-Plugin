<?php
declare(strict_types=1);
/**
 * Multisite settings page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class MultisitePage {

    /**
     * Render multisite settings page.
     *
     * @param array $context
     * @return void
     */
    public static function render(array $context): void {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : 'Multisite';
        $mode = isset($context['mode']) ? $context['mode'] : 'standalone';
        $is_master = ($mode === 'master');
        $is_child = ($mode === 'child');
        $master_url = isset($context['master_url']) ? $context['master_url'] : '';
        $api_key = isset($context['api_key']) ? $context['api_key'] : '';
        $saved = !empty($context['saved']);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>
            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Multisite settings saved.', $text_domain); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('csa_save_multisite', 'csa_multisite_nonce'); ?>
                <input type="hidden" name="action" value="csa_save_multisite" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Mode', $text_domain); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="csa_multisite_mode" value="standalone" <?php checked($mode, 'standalone'); ?> />
                                        <?php esc_html_e('Standalone (no sync)', $text_domain); ?>
                                    </label><br />
                                    <label>
                                        <input type="radio" name="csa_multisite_mode" value="master" <?php checked($mode, 'master'); ?> />
                                        <?php esc_html_e('Master site (stores appointments)', $text_domain); ?>
                                    </label><br />
                                    <label>
                                        <input type="radio" name="csa_multisite_mode" value="child" <?php checked($mode, 'child'); ?> />
                                        <?php esc_html_e('Child site (syncs from master)', $text_domain); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Master Site URL', $text_domain); ?></th>
                            <td>
                                <input type="url" class="regular-text" name="csa_multisite_master_url" value="<?php echo esc_attr($master_url); ?>" placeholder="https://example.com" <?php echo $is_child ? '' : 'disabled'; ?> />
                                <p class="description"><?php esc_html_e('Required for child sites. Example: https://example.com', $text_domain); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', $text_domain); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="csa_multisite_api_key" value="<?php echo esc_attr($api_key); ?>" <?php echo $is_master ? 'readonly' : ''; ?> />
                                <p class="description"><?php esc_html_e('For master sites this key is generated automatically. For child sites, paste the master key here.', $text_domain); ?></p>
                                <label style="display:block; margin-top:8px;">
                                    <input type="checkbox" name="csa_multisite_regenerate" value="1" <?php echo $is_master ? '' : 'disabled'; ?> />
                                    <?php esc_html_e('Regenerate master API key', $text_domain); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Multisite Settings', $text_domain)); ?>
            </form>
        </div>
        <?php
    }
}
