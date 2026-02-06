<?php
declare(strict_types=1);
/**
 * Admin users page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

use CalendarServiceAppointmentsForm\Core\Access;

if (!defined('ABSPATH')) {
    exit;
}

class UsersPage {

    /**
     * Render the users page.
     *
     * @param array $context
     * @return void
     */
    public static function render(array $context): void {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $users = isset($context['users']) && is_array($context['users']) ? $context['users'] : [];
        $enabled = isset($context['enabled']) && is_array($context['enabled']) ? $context['enabled'] : [];
        $services = isset($context['services']) && is_array($context['services']) ? $context['services'] : [];
        $saved = !empty($context['saved']);
        $service_items = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $title = isset($service['title']) ? (string) $service['title'] : '';
            if ($title === '') {
                continue;
            }
            $slug = '';
            if (!empty($service['slug'])) {
                $slug = sanitize_title($service['slug']);
            }
            if ($slug === '') {
                $slug = sanitize_title($title);
            }
            if ($slug === '') {
                continue;
            }
            $service_items[] = [
                'slug' => $slug,
                'title' => $title,
            ];
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Plugin Users', $text_domain); ?></h1>
            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Users saved.', $text_domain); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('csa_save_users', 'csa_users_nonce'); ?>
                <input type="hidden" name="action" value="csa_save_users" />
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Enable', $text_domain); ?></th>
                            <th><?php esc_html_e('Name', $text_domain); ?></th>
                            <th><?php esc_html_e('Username', $text_domain); ?></th>
                            <th><?php esc_html_e('Role', $text_domain); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) :
                            $uid = intval($user->ID);
                            $display = trim($user->first_name . ' ' . $user->last_name);
                            if ($display === '') {
                                $display = $user->display_name;
                            }
                            $checked = in_array($uid, $enabled, true);
                            $allowed_services = Access::get_allowed_service_slugs_for_user($uid);
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="csa_enabled_users[]" value="<?php echo esc_attr($uid); ?>" <?php echo $checked ? 'checked' : ''; ?> />
                                </td>
                                <td><?php echo esc_html($display); ?></td>
                                <td><?php echo esc_html($user->user_login); ?></td>
                                <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                            </tr>
                            <tr class="csa-user-services-row">
                                <td></td>
                                <td colspan="3">
                                    <strong><?php esc_html_e('Services', $text_domain); ?></strong>
                                    <?php if (empty($service_items)) : ?>
                                        <div style="padding: 6px 0 0 12px;">
                                            <em><?php esc_html_e('No services configured', $text_domain); ?></em>
                                        </div>
                                    <?php else : ?>
                                        <div style="padding: 6px 0 8px 12px;">
                                            <?php foreach ($service_items as $service) :
                                                $slug = $service['slug'];
                                                $title = $service['title'];
                                                $is_allowed = in_array($slug, $allowed_services, true);
                                                ?>
                                                <label style="display:block; margin: 2px 0;">
                                                    <input type="checkbox" name="csa_user_services[<?php echo esc_attr($uid); ?>][]" value="<?php echo esc_attr($slug); ?>" <?php echo $is_allowed ? 'checked' : ''; ?> />
                                                    <?php echo esc_html($title); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Users', $text_domain); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
}
