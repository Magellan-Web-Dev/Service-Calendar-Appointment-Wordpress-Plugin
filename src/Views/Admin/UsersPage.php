<?php
/**
 * Admin users page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

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
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $users = isset($context['users']) && is_array($context['users']) ? $context['users'] : [];
        $enabled = isset($context['enabled']) && is_array($context['enabled']) ? $context['enabled'] : [];
        $saved = !empty($context['saved']);
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
                            $is_admin = user_can($user, 'manage_options');
                            $display = trim($user->first_name . ' ' . $user->last_name);
                            if ($display === '') {
                                $display = $user->display_name;
                            }
                            $checked = $is_admin || in_array($uid, $enabled, true);
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="csa_enabled_users[]" value="<?php echo esc_attr($uid); ?>" <?php echo $checked ? 'checked' : ''; ?> <?php echo $is_admin ? 'disabled' : ''; ?> />
                                </td>
                                <td><?php echo esc_html($display); ?></td>
                                <td><?php echo esc_html($user->user_login); ?></td>
                                <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
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
