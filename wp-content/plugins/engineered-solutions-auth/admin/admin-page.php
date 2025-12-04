<?php
/**
 * Admin page for ESA User Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'esa-users';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle user approval/denial
if (isset($_POST['esa_action']) && wp_verify_nonce($_POST['esa_nonce'], 'esa_admin_action')) {
    // Start output buffering to prevent "headers already sent" errors
    ob_start();
    
    $user_id = intval($_POST['user_id']);
    $action_type = sanitize_text_field($_POST['esa_action']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'esa_user_approval';
    
    // Check current approval status
    $current_status = $wpdb->get_row($wpdb->prepare(
        "SELECT is_approved, approved_by, approval_date FROM {$table} WHERE user_id = %d",
        $user_id
    ));
    
    if ($action_type === 'approve') {
        // Check if already approved
        if ($current_status && $current_status->is_approved == 1) {
            $approver = get_userdata($current_status->approved_by);
            $approver_name = $approver ? $approver->display_name : 'Another admin';
            $approval_date = date('F j, Y \a\t g:i A', strtotime($current_status->approval_date));
            
            ob_end_clean(); // Clear buffer
            echo '<div class="notice notice-info"><p><strong>User is already approved.</strong> This user was approved by ' . esc_html($approver_name) . ' on ' . esc_html($approval_date) . '.</p></div>';
        } else {
            // Update user_meta
            update_user_meta($user_id, 'esa_approved', true);
            
            $user = new WP_User($user_id);
            $user->set_role('esa_user');
            
            $wpdb->replace(
                $table,
                array(
                    'user_id' => $user_id,
                    'is_approved' => 1,
                    'approval_date' => current_time('mysql'),
                    'approved_by' => get_current_user_id()
                )
            );
            
            // Send approval email to user
            $user_data = get_userdata($user_id);
            $first_name = get_user_meta($user_id, 'first_name', true);
            $last_name = get_user_meta($user_id, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = $user_data->display_name;
            }
            
            $user_roles = $user_data->roles;
            $role_display = !empty($user_roles) ? ucfirst(str_replace('_', ' ', $user_roles[0])) : 'ESA User';
            
            $approval_message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
                <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <h2 style='color: #059669; margin-top: 0;'>🎉 Account Approved!</h2>
                    
                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Hello <strong>{$full_name}</strong>,</p>
                    
                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Great news! Your Engineered Solutions account has been approved. You now have full access to all features and tools.</p>
                    
                    <div style='background-color: #d1fae5; border-left: 4px solid #059669; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='color: #065f46; font-size: 14px; margin: 0; line-height: 1.6;'>
                            <strong>✅ Status:</strong> Your account is now active and ready to use!
                        </p>
                    </div>
                    
                    <div style='background-color: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Your Account Details:</strong></p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>👤 Name: {$full_name}</p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>📧 Email: {$user_data->user_email}</p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>🔑 Role: {$role_display}</p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>✅ Status: Approved</p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . home_url() . "' style='background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                            🚀 Access Your Account
                        </a>
                    </div>
                    
                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                    
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                    
                    <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
                        Best regards,<br>
                        <strong>Engineered Solutions Team</strong>
                    </p>
                </div>
            </div>
            ";
            
            wp_mail(
                $user_data->user_email,
                'Account Approved - Engineered Solutions',
                $approval_message,
                array('Content-Type: text/html')
            );
            
            // Notify other admins (requires access to main plugin class)
            $esa = EngineeredSolutionsAuth::get_instance();
            if ($esa && method_exists($esa, 'send_admin_action_notification_public')) {
                $esa->send_admin_action_notification_public($user_id, 'approved');
            }
            
            ob_end_clean(); // Clear buffer
            echo '<div class="notice notice-success"><p>User approved successfully!</p></div>';
        }
    } elseif ($action_type === 'deny') {
        // Check if already denied
        if ($current_status && $current_status->is_approved == 0) {
            $denier = get_userdata($current_status->approved_by);
            $denier_name = $denier ? $denier->display_name : 'Another admin';
            $denial_date = $current_status->approval_date ? date('F j, Y \a\t g:i A', strtotime($current_status->approval_date)) : 'previously';
            
            ob_end_clean(); // Clear buffer
            echo '<div class="notice notice-info"><p><strong>User is already denied.</strong> This user was denied by ' . esc_html($denier_name) . ' on ' . esc_html($denial_date) . '.</p></div>';
        } else {
            // Update user_meta
            update_user_meta($user_id, 'esa_approved', false);
            
            $user = new WP_User($user_id);
            $user->set_role('esa_guest');
            
            $wpdb->replace(
                $table,
                array(
                    'user_id' => $user_id,
                    'is_approved' => 0,
                    'approved_by' => get_current_user_id()
                )
            );
            
            // Send denial email to user
            $user_data = get_userdata($user_id);
            $first_name = get_user_meta($user_id, 'first_name', true);
            $last_name = get_user_meta($user_id, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = $user_data->display_name;
            }
            
            $user_roles = $user_data->roles;
            $role_display = !empty($user_roles) ? ucfirst(str_replace('_', ' ', $user_roles[0])) : 'ESA Guest';
            
            $denial_message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
                <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <h2 style='color: #dc2626; margin-top: 0;'>Account Access Update</h2>
                    
                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Hello <strong>{$full_name}</strong>,</p>
                    
                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>We regret to inform you that your account access request for Engineered Solutions has been denied at this time.</p>
                    
                    <div style='background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='color: #991b1b; font-size: 14px; margin: 0; line-height: 1.6;'>
                            <strong>⚠️ Status:</strong> Access Denied
                        </p>
                    </div>
                    
                    <div style='background-color: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Account Information:</strong></p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>👤 Name: {$full_name}</p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>📧 Email: {$user_data->user_email}</p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>🔑 Role: {$role_display}</p>
                        <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>❌ Status: Denied</p>
                    </div>
                    
                    <div style='background-color: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='color: #1e40af; font-size: 14px; margin: 0; line-height: 1.6;'>
                            <strong>ℹ️ Need Help?</strong> If you believe this is an error or would like more information, please contact our support team for assistance.
                        </p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='mailto:support@engineeredsolutions.com' style='background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                            📧 Contact Support
                        </a>
                    </div>
                    
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                    
                    <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
                        Best regards,<br>
                        <strong>Engineered Solutions Team</strong>
                    </p>
                </div>
            </div>
            ";
            
            wp_mail(
                $user_data->user_email,
                'Account Access Update - Engineered Solutions',
                $denial_message,
                array('Content-Type: text/html')
            );
            
            // Notify other admins (requires access to main plugin class)
            $esa = EngineeredSolutionsAuth::get_instance();
            if ($esa && method_exists($esa, 'send_admin_action_notification_public')) {
                $esa->send_admin_action_notification_public($user_id, 'denied');
            }
            
            ob_end_clean(); // Clear buffer
            echo '<div class="notice notice-success"><p>User denied successfully!</p></div>';
        }
    }
}

// Get users data
global $wpdb;
$users_table = $wpdb->prefix . 'users';
$usermeta_table = $wpdb->prefix . 'usermeta';
$approval_table = $wpdb->prefix . 'esa_user_approval';
$logins_table = $wpdb->prefix . 'esa_user_logins';

$users = $wpdb->get_results("
    SELECT 
        u.ID,
        u.user_email,
        u.display_name,
        u.user_registered,
        a.is_approved,
        a.approval_date,
        l.login_time,
        l.ip_address,
        l.social_provider,
        fn.meta_value as first_name,
        ln.meta_value as last_name
    FROM {$users_table} u
    LEFT JOIN {$approval_table} a ON u.ID = a.user_id
    LEFT JOIN (
        SELECT user_id, MAX(login_time) as login_time, ip_address, social_provider
        FROM {$logins_table}
        GROUP BY user_id
    ) l ON u.ID = l.user_id
    LEFT JOIN {$usermeta_table} fn ON u.ID = fn.user_id AND fn.meta_key = 'first_name'
    LEFT JOIN {$usermeta_table} ln ON u.ID = ln.user_id AND ln.meta_key = 'last_name'
    WHERE u.user_email LIKE '%@%'
    ORDER BY u.user_registered DESC
");

?>

<div class="wrap">
    <h1>ESA User Management</h1>
    
    <div class="esa-admin-tabs">
        <a href="?page=esa-users&action=list" class="nav-tab <?php echo $action === 'list' ? 'nav-tab-active' : ''; ?>">
            User List
        </a>
        <a href="?page=esa-users&action=activity" class="nav-tab <?php echo $action === 'activity' ? 'nav-tab-active' : ''; ?>">
            User Activity
        </a>
        <a href="?page=esa-users&action=estimates" class="nav-tab <?php echo $action === 'estimates' ? 'nav-tab-active' : ''; ?>">
            Estimate Requests
        </a>
        <a href="?page=esa-users&action=settings" class="nav-tab <?php echo $action === 'settings' ? 'nav-tab-active' : ''; ?>">
            Settings
        </a>
    </div>
    
    <?php if ($action === 'list'): ?>
        <div class="esa-admin-content">
            
            <div class="esa-stats">
                <div class="esa-stat-box">
                    <h3><?php echo count($users); ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="esa-stat-box">
                    <h3><?php echo count(array_filter($users, function($u) { return $u->is_approved == 1; })); ?></h3>
                    <p>Approved Users</p>
                </div>
                <div class="esa-stat-box">
                    <h3><?php echo count(array_filter($users, function($u) { return $u->is_approved == 0 || $u->is_approved === null; })); ?></h3>
                    <p>Pending Approval</p>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Login Method</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?php 
                                // Build full name from first and last name
                                $full_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                                // Fallback to display_name if no first/last name
                                if (empty($full_name)) {
                                    $full_name = $user->display_name;
                                }
                                // Final fallback to email if display_name is also empty
                                if (empty($full_name) || $full_name === $user->user_email) {
                                    $full_name = $user->user_email;
                                }
                                ?>
                                <strong><?php echo esc_html($full_name); ?></strong><br>
                                <?php if ($full_name !== $user->user_email): ?>
                                    <small><?php echo esc_html($user->user_email); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <?php 
                                $user_obj = get_user_by('id', $user->ID);
                                $roles = $user_obj ? $user_obj->roles : array();
                                $role_names = array();
                                foreach ($roles as $role) {
                                    $role_names[] = ucfirst(str_replace('_', ' ', $role));
                                }
                                echo implode(', ', $role_names);
                                ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user->user_registered)); ?></td>
                            <td>
                                <?php if ($user->is_approved == 1): ?>
                                    <span class="esa-status approved">Approved</span>
                                <?php elseif ($user->is_approved == 0): ?>
                                    <span class="esa-status denied">Denied</span>
                                <?php else: ?>
                                    <span class="esa-status pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $user->login_time ? date('Y-m-d H:i', strtotime($user->login_time)) : 'Never'; ?>
                            </td>
                            <td>
                                <?php if ($user->social_provider): ?>
                                    <span class="esa-social-login-badge">
                                        <i class="fab fa-<?php echo esc_attr($user->social_provider); ?>"></i>
                                        <?php echo ucfirst(esc_html($user->social_provider)); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="esa-regular-login-badge">Email/Password</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($user->ip_address); ?></td>
                            <td>
                                <?php 
                                $user_obj = get_user_by('id', $user->ID);
                                $is_esa_user = $user_obj && in_array('esa_guest', $user_obj->roles);
                                $is_admin = $user_obj && user_can($user->ID, 'administrator');
                                $is_core_role = $user_obj && (user_can($user->ID, 'editor') || user_can($user->ID, 'author') || user_can($user->ID, 'contributor') || user_can($user->ID, 'subscriber'));
                                ?>
                                
                                <?php if ($user->is_approved != 1): ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('esa_admin_action', 'esa_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="esa_action" value="approve">
                                        <button type="submit" class="button button-primary button-small" 
                                                title="<?php echo $is_esa_user ? 'Will change role from ESA Guest to ESA User' : ($is_admin || $is_core_role ? 'Will approve without changing role' : 'Will approve user'); ?>">
                                            Approve
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($user->is_approved != 0): ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('esa_admin_action', 'esa_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="esa_action" value="deny">
                                        <button type="submit" class="button button-secondary button-small"
                                                title="<?php echo $is_esa_user ? 'Will change role from ESA User to ESA Guest' : ($is_admin || $is_core_role ? 'Will deny without changing role' : 'Will deny user'); ?>">
                                            Deny
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($is_admin || $is_core_role): ?>
                                    <br><small style="color: #666;">Role preserved</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($action === 'activity'): ?>
        <div class="esa-admin-content">
            
            <?php
            $activity_table = $wpdb->prefix . 'esa_user_activity';
            $activities = $wpdb->get_results("
                SELECT 
                    a.*,
                    u.display_name,
                    u.user_email
                FROM {$activity_table} a
                LEFT JOIN {$users_table} u ON a.user_id = u.ID
                ORDER BY a.visit_time DESC
                LIMIT 100
            ");
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Page</th>
                        <th>Time Spent</th>
                        <th>Visit Time</th>
                        <th>Session ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($activity->display_name); ?></strong><br>
                                <small><?php echo esc_html($activity->user_email); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($activity->page_title); ?></strong><br>
                                <small><?php echo esc_html($activity->page_url); ?></small>
                            </td>
                            <td><?php echo gmdate('H:i:s', $activity->time_spent); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($activity->visit_time)); ?></td>
                            <td><code><?php echo esc_html($activity->session_id); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($action === 'estimates'): ?>
        <div class="esa-admin-content">
            
            <?php
            $estimates_table = $wpdb->prefix . 'esa_estimate_requests';
            $estimates = $wpdb->get_results("
                SELECT 
                    e.*,
                    u.display_name,
                    u.user_email
                FROM {$estimates_table} e
                LEFT JOIN {$users_table} u ON e.user_id = u.ID
                ORDER BY e.request_time DESC
            ");
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Page Type</th>
                        <th>Selected Model</th>
                        <th>Request Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estimates as $estimate): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($estimate->display_name); ?></strong><br>
                                <small><?php echo esc_html($estimate->user_email); ?></small>
                            </td>
                            <td><?php echo esc_html($estimate->page_type); ?></td>
                            <td><?php echo esc_html($estimate->selected_model); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($estimate->request_time)); ?></td>
                            <td>
                                <span class="esa-status <?php echo $estimate->status; ?>">
                                    <?php echo ucfirst($estimate->status); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small" onclick="esaViewEstimate(<?php echo $estimate->id; ?>)">
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($action === 'settings'): ?>
        <div class="esa-admin-content">
            
            <form method="post" action="options.php">
                <?php
                settings_fields('esa_settings');
                do_settings_sections('esa_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Admin Emails for Notifications</th>
                        <td>
                            <textarea name="esa_admin_emails" rows="3" cols="50" class="large-text"><?php echo esc_textarea(get_option('esa_admin_emails', get_option('admin_email'))); ?></textarea>
                            <p class="description">Enter multiple email addresses (one per line or comma-separated) to receive user registration and estimate request notifications. All four team members will receive notifications.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Session Timeout (minutes)</th>
                        <td>
                            <input type="number" name="esa_session_timeout" value="<?php echo get_option('esa_session_timeout', 120); ?>" class="small-text">
                            <p class="description">How long should user sessions last before requiring re-login.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable User Tracking</th>
                        <td>
                            <input type="checkbox" name="esa_enable_tracking" value="1" <?php checked(get_option('esa_enable_tracking', 1)); ?>>
                            <p class="description">Track user activity and page visits.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable CAPTCHA</th>
                        <td>
                            <input type="checkbox" name="esa_enable_captcha" value="1" <?php checked(get_option('esa_enable_captcha', 1)); ?>>
                            <p class="description">Enable CAPTCHA protection for registration and login to prevent bot spam.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">CAPTCHA Type</th>
                        <td>
                            <select name="esa_captcha_type">
                                <option value="recaptcha_v2" <?php selected(get_option('esa_captcha_type', 'recaptcha_v2'), 'recaptcha_v2'); ?>>reCAPTCHA v2 (Checkbox)</option>
                                <option value="recaptcha_v3" <?php selected(get_option('esa_captcha_type', 'recaptcha_v2'), 'recaptcha_v3'); ?>>reCAPTCHA v3 (Invisible)</option>
                                <option value="hcaptcha" <?php selected(get_option('esa_captcha_type', 'recaptcha_v2'), 'hcaptcha'); ?>>hCaptcha</option>
                            </select>
                            <p class="description">Choose the type of CAPTCHA to use.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">reCAPTCHA Site Key</th>
                        <td>
                            <input type="text" name="esa_recaptcha_site_key" value="<?php echo esc_attr(get_option('esa_recaptcha_site_key', '')); ?>" class="regular-text">
                            <p class="description">Get your reCAPTCHA site key from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">reCAPTCHA Secret Key</th>
                        <td>
                            <input type="password" name="esa_recaptcha_secret_key" value="<?php echo esc_attr(get_option('esa_recaptcha_secret_key', '')); ?>" class="regular-text">
                            <p class="description">Get your reCAPTCHA secret key from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">reCAPTCHA v3 Minimum Score</th>
                        <td>
                            <input type="number" name="esa_recaptcha_min_score" value="<?php echo get_option('esa_recaptcha_min_score', 0.5); ?>" step="0.1" min="0" max="1" class="small-text">
                            <p class="description">Minimum score for reCAPTCHA v3 (0.0 to 1.0, higher is better).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">hCaptcha Site Key</th>
                        <td>
                            <input type="text" name="esa_hcaptcha_site_key" value="<?php echo esc_attr(get_option('esa_hcaptcha_site_key', '')); ?>" class="regular-text">
                            <p class="description">Get your hCaptcha site key from <a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">hCaptcha Secret Key</th>
                        <td>
                            <input type="password" name="esa_hcaptcha_secret_key" value="<?php echo esc_attr(get_option('esa_hcaptcha_secret_key', '')); ?>" class="regular-text">
                            <p class="description">Get your hCaptcha secret key from <a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a>.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
.esa-admin-tabs {
    margin: 20px 0;
}

.esa-admin-tabs .nav-tab {
    text-decoration: none;
    padding: 8px 12px;
    margin-right: 5px;
    border: 1px solid #ccc;
    background: #f1f1f1;
    color: #333;
}

.esa-admin-tabs .nav-tab-active {
    background: #fff;
    border-bottom-color: #fff;
}

.esa-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.esa-stat-box {
    background: #fff;
    border: 1px solid #ddd;
    padding: 20px;
    text-align: center;
    border-radius: 4px;
    min-width: 120px;
}

.esa-stat-box h3 {
    font-size: 32px;
    margin: 0;
    color: #0073aa;
}

.esa-stat-box p {
    margin: 5px 0 0 0;
    color: #666;
}

.esa-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.esa-status.approved {
    background: #d4edda;
    color: #155724;
}

.esa-status.denied {
    background: #f8d7da;
    color: #721c24;
}

.esa-status.pending {
    background: #fff3cd;
    color: #856404;
}

.esa-admin-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.esa-social-login-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.esa-social-login-badge i {
    font-size: 14px;
}

.esa-regular-login-badge {
    padding: 4px 8px;
    background: #f5f5f5;
    color: #666;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
</style>

<script>
function esaViewEstimate(estimateId) {
    // Implement estimate details modal
    alert('Estimate details for ID: ' + estimateId);
}
</script>
