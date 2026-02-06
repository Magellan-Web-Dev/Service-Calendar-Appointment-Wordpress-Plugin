<?php
/**
 * Admin delete appointments page view
 *
 * @package CalendarServiceAppointmentsForm\Views\Admin
 */

namespace CalendarServiceAppointmentsForm\Views\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class DeleteAppointmentsPage {

    /**
     * Render the delete appointments page.
     *
     * @param array $context
     * @return void
     */
    public static function render($context) {
        $text_domain = isset($context['text_domain']) ? $context['text_domain'] : 'calendar-service-appointments-form';
        $label = isset($context['label']) ? $context['label'] : '';
        $users = isset($context['users']) && is_array($context['users']) ? $context['users'] : [];
        $deleted_user = isset($context['deleted_user']) ? (int) $context['deleted_user'] : 0;
        $deleted_all = !empty($context['deleted_all']);
        $deleted_count = isset($context['deleted_count']) ? (int) $context['deleted_count'] : 0;

        ?>
        <div class="wrap csa-delete-appointments-page">
            <h1><?php echo esc_html__($label, $text_domain); ?></h1>

            <p><?php esc_html_e('Use this page to permanently delete appointments. This cannot be undone.', $text_domain); ?></p>

            <?php if ($deleted_user > 0) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__('Deleted %1$d appointment(s) for user ID %2$d.', $text_domain),
                            intval($deleted_count),
                            intval($deleted_user)
                        );
                        ?>
                    </p>
                </div>
            <?php elseif ($deleted_all) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__('Deleted %d appointment(s) for all users.', $text_domain),
                            intval($deleted_count)
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Delete By User', $text_domain); ?></h2>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', $text_domain); ?></th>
                        <th><?php esc_html_e('Username', $text_domain); ?></th>
                        <th><?php esc_html_e('Action', $text_domain); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)) : ?>
                        <tr>
                            <td colspan="3"><?php esc_html_e('No users found.', $text_domain); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($users as $user) : ?>
                            <?php
                            $first = isset($user->first_name) ? trim((string) $user->first_name) : '';
                            $last = isset($user->last_name) ? trim((string) $user->last_name) : '';
                            $display = trim($first . ' ' . $last);
                            if ($display === '' && isset($user->display_name)) {
                                $display = (string) $user->display_name;
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($display); ?></td>
                                <td><?php echo esc_html($user->user_login ?? ''); ?></td>
                                <td>
                                    <?php
                                    $confirm_message = sprintf(
                                        __('This will delete all appointments for %s. Are you sure?', $text_domain),
                                        $display !== '' ? $display : ($user->user_login ?? '')
                                    );
                                    ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($confirm_message); ?>');">
                                        <?php wp_nonce_field('csa_delete_appointments', 'csa_delete_nonce'); ?>
                                        <input type="hidden" name="action" value="csa_delete_user_appointments" />
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user->ID); ?>" />
                                        <button type="submit" class="button button-secondary">
                                            <?php esc_html_e('Delete User Appointments', $text_domain); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 style="margin-top: 24px;"><?php esc_html_e('Delete All Appointments', $text_domain); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('This will delete all appointments for all users. Are you sure?', $text_domain)); ?>');">
                <?php wp_nonce_field('csa_delete_appointments_all', 'csa_delete_all_nonce'); ?>
                <input type="hidden" name="action" value="csa_delete_all_appointments" />
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Delete All Plugin Appointments', $text_domain); ?>
                </button>
            </form>
        </div>
        <?php
    }
}
