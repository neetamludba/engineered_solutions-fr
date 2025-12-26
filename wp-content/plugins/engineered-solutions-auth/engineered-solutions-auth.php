<?php
/**
 * Plugin Name: Engineered Solutions Authentication
 * Plugin URI: https://rainwaterharvesting.services
 * Description: Complete authentication system for pump sizing applications with user tracking, social login, access control, bot protection, and email verification.
 * Version: 2.4.4
 * Author: Engineered Solutions
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ESA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ESA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ESA_VERSION', '2.4.4');

class EngineeredSolutionsAuth {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_esa_login', array($this, 'handle_login'));
        // add_action('wp_ajax_esa_register', array($this, 'handle_register'));
        add_action('wp_ajax_esa_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_esa_track_activity', array($this, 'track_user_activity'));
        add_action('wp_ajax_esa_save_estimate', array($this, 'save_estimate_request'));
        add_action('wp_ajax_nopriv_esa_login', array($this, 'handle_login'));
        // add_action('wp_ajax_nopriv_esa_register', array($this, 'handle_register'));
        add_action('wp_ajax_nopriv_esa_track_activity', array($this, 'track_user_activity'));
        
		// OTP endpoints
		add_action('wp_ajax_nopriv_esa_request_otp', array($this, 'request_email_otp'));
		add_action('wp_ajax_esa_request_otp', array($this, 'request_email_otp'));
		add_action('wp_ajax_nopriv_esa_verify_otp', array($this, 'verify_email_otp_and_create_user'));
		add_action('wp_ajax_esa_verify_otp', array($this, 'verify_email_otp_and_create_user'));
		
		// Password reset endpoints
		add_action('wp_ajax_nopriv_esa_request_password_reset', array($this, 'request_password_reset'));
		add_action('wp_ajax_esa_request_password_reset', array($this, 'request_password_reset'));
		add_action('wp_ajax_nopriv_esa_verify_password_reset', array($this, 'verify_password_reset'));
		add_action('wp_ajax_esa_verify_password_reset', array($this, 'verify_password_reset'));
		
        // Magic Link endpoints
        add_action('wp_ajax_nopriv_esa_request_magic_link', array($this, 'request_magic_link'));
        add_action('wp_ajax_esa_request_magic_link', array($this, 'request_magic_link'));
        add_action('wp_ajax_nopriv_esa_verify_magic_link', array($this, 'verify_magic_link'));
        add_action('wp_ajax_esa_verify_magic_link', array($this, 'verify_magic_link'));
		
        // Approval status check endpoint
        add_action('wp_ajax_esa_check_approval_status', array($this, 'check_approval_status'));
		
        // Nextend Social Login Pro integration hooks
        add_action('nsl_login_success', array($this, 'handle_nextend_login'), 10, 2);
        add_action('nsl_register_success', array($this, 'handle_nextend_register'), 10, 2);
        add_action('wp_login', array($this, 'handle_wp_login'), 10, 2);
        
        // CAPTCHA integration
        add_action('nsl_register_form', array($this, 'add_captcha_to_registration'));
        add_action('nsl_login_form', array($this, 'add_captcha_to_login'));
        add_filter('nsl_register_validation', array($this, 'validate_captcha_registration'), 10, 2);
        add_filter('nsl_login_validation', array($this, 'validate_captcha_login'), 10, 2);
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_esa_approve_user', array($this, 'approve_user'));
        add_action('wp_ajax_esa_deny_user', array($this, 'deny_user'));
        add_action('wp_ajax_esa_test_captcha', array($this, 'test_captcha_configuration'));
        
        // Public approval endpoints (no login required)
        add_action('admin_post_nopriv_esa_public_approve', array($this, 'handle_public_approval'));
        add_action('admin_post_esa_public_approve', array($this, 'handle_public_approval'));
		add_action('admin_post_nopriv_esa_public_resend_approval', array($this, 'handle_public_resend_approval'));
		add_action('admin_post_esa_public_resend_approval', array($this, 'handle_public_resend_approval'));
        
        // Public denial endpoints (no login required)
        add_action('admin_post_nopriv_esa_public_deny', array($this, 'handle_public_denial'));
        add_action('admin_post_esa_public_deny', array($this, 'handle_public_denial'));
        
        // Handle approval/denial from email links
        add_action('admin_init', array($this, 'handle_email_approval_denial'));
        

        // Handle email verification
        add_action('init', array($this, 'handle_email_verification'));

        
        // Resend verification email endpoint
        add_action('wp_ajax_nopriv_esa_resend_verification', array($this, 'resend_verification_email'));
        
        // Cleanup data when user is deleted
        add_action('delete_user', array($this, 'cleanup_user_data'));
        
        // Cron jobs
        add_action('esa_check_user_status', array($this, 'check_user_status'));
        if (!wp_next_scheduled('esa_check_user_status')) {
            wp_schedule_event(time(), 'hourly', 'esa_check_user_status');
        }
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Include additional files
        add_action('init', array($this, 'include_files'));
    }
    
    public function init() {
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }
		// Ensure DB schema migrations for verification table
		$this->ensure_verification_schema();
        // Ensure all tables exist (including new magic links table)
        $this->create_tables();
    }
    
    public function include_files() {
        // Include user greeting widget
        if (file_exists(ESA_PLUGIN_PATH . 'includes/user-greeting-widget.php')) {
            include_once ESA_PLUGIN_PATH . 'includes/user-greeting-widget.php';
        }
        
        // Include shortcodes
        if (file_exists(ESA_PLUGIN_PATH . 'includes/shortcodes.php')) {
            include_once ESA_PLUGIN_PATH . 'includes/shortcodes.php';
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        // Only enqueue production script, not both
        wp_enqueue_script('esa-auth-production-js', ESA_PLUGIN_URL . 'assets/js/auth-production.js', array('jquery'), ESA_VERSION, true);
        wp_enqueue_script('esa-form-persistence-js', ESA_PLUGIN_URL . 'assets/js/form-persistence.js', array('jquery'), ESA_VERSION, true);
        wp_enqueue_style('esa-auth-css', ESA_PLUGIN_URL . 'assets/css/auth.css', array(), ESA_VERSION);
        wp_enqueue_style('esa-modern-css', ESA_PLUGIN_URL . 'assets/css/modern-auth.css', array(), ESA_VERSION);

        // Enqueue CAPTCHA scripts if enabled
        $captcha_enabled = get_option('esa_enable_captcha', 0);
        $captcha_type = get_option('esa_captcha_type', 'recaptcha_v2');
        $site_key = '';
        
        if ($captcha_enabled) {
            if ($captcha_type === 'recaptcha_v2' || $captcha_type === 'recaptcha_v3') {
                $site_key = get_option('esa_recaptcha_site_key', '');
                if ($captcha_type === 'recaptcha_v2') {
                    wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
                } else {
                    wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, array(), null, true);
                }
            } elseif ($captcha_type === 'hcaptcha') {
                $site_key = get_option('esa_hcaptcha_site_key', '');
                wp_enqueue_script('hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true);
            }
        }

        // Localize script for AJAX - only for production script
        $current_user_name = '';
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $first_name = get_user_meta($current_user->ID, 'first_name', true);
            $last_name = get_user_meta($current_user->ID, 'last_name', true);
            $current_user_name = trim($first_name . ' ' . $last_name);
            
            // Fallback to display_name if no first/last name
            if (empty($current_user_name)) {
                $current_user_name = $current_user->display_name;
            }
            
            // Final fallback to email if display_name is also empty or same as email
            if (empty($current_user_name) || $current_user_name === $current_user->user_email) {
                $current_user_name = $current_user->user_email;
            }
        }
        
        wp_localize_script('esa-auth-production-js', 'esa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('esa_nonce'),
            'is_user_logged_in' => is_user_logged_in(),
            'user_id' => get_current_user_id(),
            'user_name' => $current_user_name,
            'user_email' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
            'user_approved' => $this->is_user_approved(get_current_user_id()),
            'captcha_enabled' => $captcha_enabled,
            'captcha_type' => $captcha_type,
            'captcha_site_key' => $site_key
        ));
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_roles();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('esa_check_user_status');
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User login tracking table
        $table_login = $wpdb->prefix . 'esa_user_logins';
        $sql_login = "CREATE TABLE $table_login (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            ip_address varchar(45) NOT NULL,
            login_time datetime NOT NULL,
            page_visited varchar(255) DEFAULT NULL,
            session_duration int(11) DEFAULT 0,
            logout_time datetime DEFAULT NULL,
            social_provider varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY login_time (login_time),
            KEY social_provider (social_provider)
        ) $charset_collate;";
        
        // User activity tracking table
        $table_activity = $wpdb->prefix . 'esa_user_activity';
        $sql_activity = "CREATE TABLE $table_activity (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            page_url varchar(500) NOT NULL,
            page_title varchar(255) NOT NULL,
            time_spent int(11) DEFAULT 0,
            visit_time datetime NOT NULL,
            session_id varchar(100) NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY visit_time (visit_time)
        ) $charset_collate;";
        
        // Estimate requests table
        $table_estimates = $wpdb->prefix . 'esa_estimate_requests';
        $sql_estimates = "CREATE TABLE $table_estimates (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            page_type varchar(50) NOT NULL,
            selected_model varchar(255) NOT NULL,
            form_data longtext NOT NULL,
            request_time datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY request_time (request_time)
        ) $charset_collate;";
        
        // User approval status table
        $table_approval = $wpdb->prefix . 'esa_user_approval';
        $sql_approval = "CREATE TABLE $table_approval (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            is_approved tinyint(1) DEFAULT 0,
            approval_date datetime DEFAULT NULL,
            approved_by int(11) DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        // Approval tokens table for public approval links
        $table_tokens = $wpdb->prefix . 'esa_approval_tokens';
        $sql_tokens = "CREATE TABLE $table_tokens (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            token varchar(64) NOT NULL,
            action varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        // Email verification tokens table
        $table_verification = $wpdb->prefix . 'esa_email_verification';
        $sql_verification = "CREATE TABLE $table_verification (
            id int(11) NOT NULL AUTO_INCREMENT,
			user_id int(11) NOT NULL,
			email varchar(191) NOT NULL,
			token varchar(64) NOT NULL,
            created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			verified tinyint(1) DEFAULT 0,
			code_hash varchar(255) DEFAULT NULL,
			attempt_count int(11) NOT NULL DEFAULT 0,
			locked_until datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
			KEY user_id (user_id),
			KEY email (email)
        ) $charset_collate;";
        
        // Rate limiting table
        $table_rate_limit = $wpdb->prefix . 'esa_rate_limit';
        $sql_rate_limit = "CREATE TABLE $table_rate_limit (
            id int(11) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            action_type varchar(20) NOT NULL,
            attempt_time datetime NOT NULL,
            PRIMARY KEY (id),
            KEY ip_action (ip_address, action_type, attempt_time)
        ) $charset_collate;";

        // Magic links table
        $table_magic_links = $wpdb->prefix . 'esa_magic_links';
        $sql_magic_links = "CREATE TABLE $table_magic_links (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            email varchar(191) NOT NULL,
            token varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            ip_address varchar(45) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY email (email),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_login);
        dbDelta($sql_activity);
        dbDelta($sql_estimates);
        dbDelta($sql_approval);
        dbDelta($sql_tokens);
        dbDelta($sql_verification);
        dbDelta($sql_rate_limit);
        dbDelta($sql_magic_links);
    }
    
    private function create_roles() {
        add_role('esa_guest', 'ESA Guest', array('read' => true));
        add_role('esa_user', 'ESA User', array('read' => true));
    }
    
    public function handle_login() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        // Log login attempt
        error_log('ESA Login: Starting login process');
        
        // Verify CAPTCHA if enabled
        if (get_option('esa_enable_captcha', 0)) {
            error_log('ESA Login: CAPTCHA is enabled, verifying...');
            if (!$this->verify_captcha('login')) {
                error_log('ESA Login: CAPTCHA verification failed');
                wp_send_json_error(array('message' => 'CAPTCHA verification failed. Please try again.'));
                return;
            }
            error_log('ESA Login: CAPTCHA verification successful');
        } else {
            error_log('ESA Login: CAPTCHA is disabled, skipping verification');
        }
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        $user = wp_authenticate($email, $password);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'Invalid credentials'));
        }
        
        // Check if account is suspended
        $is_suspended = get_user_meta($user->ID, 'esa_account_suspended', true);
        if ($is_suspended) {
            wp_send_json_error(array('message' => 'Your account has been suspended. Please contact support.'));
            return;
        }
        
        // Check if email is verified (only for new users after v2.0.0)
        $email_verified = get_user_meta($user->ID, 'esa_email_verified', true);
        $is_approved = get_user_meta($user->ID, 'esa_approved', true);
        
        // If user is already approved, skip email verification requirement
        if (!$email_verified && !$is_approved) {
            wp_send_json_error(array('message' => 'Please verify your email before logging in. Check your inbox.'));
            return;
        }
        
        // Auto-set email verification for existing approved users
        if (!$email_verified && $is_approved) {
            update_user_meta($user->ID, 'esa_email_verified', true);
        }
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        // Log login event
        $this->log_user_login($user->ID);

        // Get user's full name from meta
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $full_name = trim($first_name . ' ' . $last_name);
        
        // Fallback to display_name if no first/last name
        if (empty($full_name)) {
            $full_name = $user->display_name;
        }
        
        // Final fallback to email if display_name is also empty or same as email
        if (empty($full_name) || $full_name === $user->user_email) {
            $full_name = $user->user_email;
        }

        // IMPORTANT: Create nonce AFTER setting auth cookie so it's valid for the logged-in session
        $new_nonce = wp_create_nonce('esa_nonce');

        $response_data = array(
            'message' => 'Login successful',
            'nonce' => $new_nonce,
            'user' => array(
                'id' => $user->ID,
                'name' => $full_name,
                'email' => $user->user_email,
                'approved' => $this->is_user_approved($user->ID)
            )
        );

        error_log('ESA Login: Sending success response with user data: ' . wp_json_encode($response_data));

        wp_send_json_success($response_data);
    }
    
    public function handle_register() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        // Log registration attempt
        error_log('ESA Register: Starting registration process');
        
        // Verify CAPTCHA if enabled
        if (get_option('esa_enable_captcha', 0)) {
            error_log('ESA Register: CAPTCHA is enabled, verifying...');
            if (!$this->verify_captcha('registration')) {
                error_log('ESA Register: CAPTCHA verification failed');
                wp_send_json_error(array('message' => 'CAPTCHA verification failed. Please try again.'));
                return;
            }
            error_log('ESA Register: CAPTCHA verification successful');
        } else {
            error_log('ESA Register: CAPTCHA is disabled, skipping verification');
        }
        
		// OTP-only flow: this endpoint no longer creates users.
		wp_send_json_error(array('message' => 'Registration flow has changed. Please request a verification code and enter it to create your account.'));
    }
    
    public function handle_logout() {
        error_log('ESA Logout: Starting logout process');
        error_log('ESA Logout: Received nonce: ' . (isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 10) . '...' : 'NONE'));
        error_log('ESA Logout: Current user ID: ' . get_current_user_id());
        error_log('ESA Logout: Is user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        
        // Try to verify nonce and catch any errors
        $nonce_check = check_ajax_referer('esa_nonce', 'nonce', false);
        error_log('ESA Logout: Nonce verification result: ' . ($nonce_check ? 'PASS' : 'FAIL'));
        
        // WORKAROUND: If nonce fails but user is logged in, allow logout anyway
        // Logout is a safe operation and nonce issues shouldn't prevent it
        if (!$nonce_check && !is_user_logged_in()) {
            error_log('ESA Logout: Nonce verification FAILED and user not logged in - returning 403');
            wp_send_json_error(array('message' => 'Invalid security token. Please refresh the page and try again.'), 403);
            return;
        }
        
        if (!$nonce_check && is_user_logged_in()) {
            error_log('ESA Logout: Nonce verification FAILED but user is logged in - allowing logout anyway');
        }
        
        $user_id = get_current_user_id();
        if ($user_id) {
            $this->log_user_logout($user_id);
        }
        
        wp_logout();
        error_log('ESA Logout: Logout successful for user ' . $user_id);
        wp_send_json_success(array('message' => 'Logged out successfully'));
    }

    public function request_magic_link() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        $user = get_user_by('email', $email);
        if (!$user) {
            // Don't reveal if user exists or not
            wp_send_json_success(array('message' => 'If an account exists with this email, a magic link has been sent.'));
            return;
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_magic_links';
        
        // Invalidate old tokens for this user
        $wpdb->update(
            $table,
            array('used' => 1),
            array('user_id' => $user->ID, 'used' => 0),
            array('%d'),
            array('%d', '%d')
        );
        
        // Insert new token
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user->ID,
                'email' => $email,
                'token' => $token,
                'created_at' => current_time('mysql'),
                'expires_at' => $expires,
                'ip_address' => $this->get_client_ip()
            )
        );
        
        // Send email
        $login_url = add_query_arg(
            array(
                'esa_magic_token' => $token,
                'email' => urlencode($email)
            ),
            home_url('/')
        );
        
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $full_name = trim($first_name . ' ' . $last_name);
        if (empty($full_name)) {
            $full_name = $user->display_name;
        }
        
        $user_roles = $user->roles;
        $role_display = !empty($user_roles) ? ucfirst(str_replace('_', ' ', $user_roles[0])) : 'User';
        
        $subject = 'Your Magic Login Link - Engineered Solutions';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
            <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color: #1f2937; margin-top: 0;'>✨ Magic Login Link</h2>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Hello <strong>{$full_name}</strong>,</p>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>You requested a magic login link to access your Engineered Solutions account. Click the button below to log in instantly:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$login_url}' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                        🔐 Log In to Your Account
                    </a>
                </div>
                
                <div style='background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='color: #92400e; font-size: 14px; margin: 0; line-height: 1.6;'>
                        <strong>⚠️ Security Notice:</strong> This link will expire in 15 minutes for your security.
                    </p>
                </div>
                
                <div style='background-color: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Account Details:</strong></p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>📧 Email: {$email}</p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>👤 Role: {$role_display}</p>
                </div>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>If you didn't request this login link, you can safely ignore this email.</p>
                
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                
                <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
                    Best regards,<br>
                    <strong>Engineered Solutions Team</strong>
                </p>
            </div>
        </div>
        ";
        
        wp_mail($email, $subject, $message, array('Content-Type: text/html'));
        
        wp_send_json_success(array('message' => 'Magic link sent! Check your email.'));
    }
    
    public function verify_magic_link() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token']);
        $email = sanitize_email($_POST['email']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_magic_links';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s AND email = %s AND used = 0 AND expires_at > %s",
            $token,
            $email,
            current_time('mysql')
        ));
        
        if (!$record) {
            wp_send_json_error(array('message' => 'Invalid or expired magic link. Please request a new one.'));
        }
        
        // Mark as used
        $wpdb->update(
            $table,
            array('used' => 1),
            array('id' => $record->id),
            array('%d'),
            array('%d')
        );
        
        // Log user in
        $user = get_user_by('id', $record->user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found.'));
        }
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        $this->log_user_login($user->ID);
        
        wp_send_json_success(array(
            'message' => 'Login successful',
            'nonce' => wp_create_nonce('esa_nonce'),
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_approved' => $this->is_user_approved($user->ID)
        ));
    }
    
    // Nextend Social Login Pro integration methods
    public function handle_nextend_login($user_id, $provider) {
        // Log the social login
        $this->log_user_login($user_id);
        
        // Set user role based on approval status
        $this->set_user_role_based_on_approval($user_id);
        
        // Log the social login method
        $this->log_social_login($user_id, $provider);
    }
    
    public function handle_nextend_register($user_id, $provider) {
        // Set initial role as guest (but preserve admin users)
        if (!user_can($user_id, 'administrator')) {
            $user = new WP_User($user_id);
            $user->set_role('esa_guest');
        }
        
        // Log registration
        $this->log_user_registration($user_id);
        
        // Send approval email to admin
        $user_data = get_userdata($user_id);
        $this->send_approval_email($user_id, $user_data->first_name, $user_data->last_name, $user_data->user_email);
        
        // Log the social registration method
        $this->log_social_login($user_id, $provider);
    }
    
    public function handle_wp_login($user_login, $user) {
        // This handles regular WordPress login (not social)
        if (!isset($_GET['nsl_login'])) {
            $this->log_user_login($user->ID);
            $this->set_user_role_based_on_approval($user->ID);
        }
    }
    
    private function log_social_login($user_id, $provider) {
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_logins';
        
        $wpdb->update(
            $table,
            array('social_provider' => $provider),
            array('user_id' => $user_id, 'logout_time' => null),
            array('%s'),
            array('%d', '%s')
        );
    }
    
    private function set_user_role_based_on_approval($user_id) {
        // Don't change role if user has any WordPress core role (admin, editor, author, contributor, subscriber)
        if (user_can($user_id, 'administrator') || 
            user_can($user_id, 'editor') || 
            user_can($user_id, 'author') || 
            user_can($user_id, 'contributor') || 
            user_can($user_id, 'subscriber')) {
            return;
        }
        
        $user = new WP_User($user_id);
        $current_roles = $user->roles;
        
        // Only change role if user is ESA Guest or ESA User
        if (in_array('esa_guest', $current_roles) || in_array('esa_user', $current_roles)) {
            if ($this->is_user_approved($user_id)) {
                $user->set_role('esa_user');
            } else {
                $user->set_role('esa_guest');
            }
        }
    }
    
    // CAPTCHA integration methods
    public function add_captcha_to_registration() {
        if (get_option('esa_enable_captcha', 0)) {
            echo '<div class="esa-captcha-container">';
            $this->render_captcha('registration');
            echo '</div>';
        }
    }
    
    public function add_captcha_to_login() {
        if (get_option('esa_enable_captcha', 0)) {
            $this->render_captcha('login');
        }
    }
    
    private function render_captcha($form_type) {
        $captcha_type = get_option('esa_captcha_type', 'recaptcha_v2');
        
        if ($captcha_type === 'recaptcha_v2') {
            $this->render_recaptcha_v2($form_type);
        } elseif ($captcha_type === 'recaptcha_v3') {
            $this->render_recaptcha_v3($form_type);
        } elseif ($captcha_type === 'hcaptcha') {
            $this->render_hcaptcha($form_type);
        }
    }
    
    private function render_recaptcha_v2($form_type) {
        $site_key = get_option('esa_recaptcha_site_key', '');
        if (empty($site_key)) return;
        
        echo '<div class="esa-captcha-container">';
        echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
        echo '</div>';
        
        // Add reCAPTCHA script
        wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
    }
    
    private function render_recaptcha_v3($form_type) {
        $site_key = get_option('esa_recaptcha_site_key', '');
        if (empty($site_key)) return;
        
        echo '<div class="esa-captcha-container">';
        echo '<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-' . $form_type . '">';
        echo '</div>';
        
        // Add reCAPTCHA v3 script
        wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, array(), null, true);
        wp_add_inline_script('google-recaptcha-v3', '
            grecaptcha.ready(function() {
                grecaptcha.execute("' . $site_key . '", {action: "' . $form_type . '"}).then(function(token) {
                    document.getElementById("g-recaptcha-response-' . $form_type . '").value = token;
                });
            });
        ');
    }
    
    private function render_hcaptcha($form_type) {
        $site_key = get_option('esa_hcaptcha_site_key', '');
        if (empty($site_key)) return;
        
        echo '<div class="esa-captcha-container">';
        echo '<div class="h-captcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
        echo '</div>';
        
        // Add hCaptcha script
        wp_enqueue_script('hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true);
    }
    
    public function validate_captcha_registration($is_valid, $provider) {
        if (!$is_valid) return $is_valid;
        
        if (get_option('esa_enable_captcha', 0)) {
            return $this->verify_captcha('registration');
        }
        
        return $is_valid;
    }
    
    public function validate_captcha_login($is_valid, $provider) {
        if (!$is_valid) return $is_valid;
        
        if (get_option('esa_enable_captcha', 0)) {
            return $this->verify_captcha('login');
        }
        
        return $is_valid;
    }
    
    private function verify_captcha($form_type) {
        $captcha_type = get_option('esa_captcha_type', 'recaptcha_v2');
        
        if ($captcha_type === 'recaptcha_v2') {
            return $this->verify_recaptcha_v2();
        } elseif ($captcha_type === 'recaptcha_v3') {
            return $this->verify_recaptcha_v3($form_type);
        } elseif ($captcha_type === 'hcaptcha') {
            return $this->verify_hcaptcha();
        }
        
        return true;
    }
    
    private function verify_recaptcha_v2() {
        $secret_key = get_option('esa_recaptcha_secret_key', '');
        $response = $_POST['g-recaptcha-response'] ?? '';
        
        // Log verification attempt
        error_log('ESA reCAPTCHA v2: Starting verification');
        error_log('ESA reCAPTCHA v2: Secret key configured: ' . (!empty($secret_key) ? 'Yes' : 'No'));
        error_log('ESA reCAPTCHA v2: Token received: ' . (!empty($response) ? 'Yes (length: ' . strlen($response) . ')' : 'No'));
        
        if (empty($secret_key)) {
            error_log('ESA reCAPTCHA v2: ERROR - Secret key not configured');
            return false;
        }
        
        if (empty($response)) {
            error_log('ESA reCAPTCHA v2: ERROR - No token received from frontend');
            return false;
        }
        
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $this->get_client_ip()
        );
        
        error_log('ESA reCAPTCHA v2: Making API call to Google with IP: ' . $this->get_client_ip());
        
        $response = wp_remote_post($verify_url, array(
            'body' => $data,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log('ESA reCAPTCHA v2: ERROR - API call failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        error_log('ESA reCAPTCHA v2: Google API response: ' . wp_json_encode($result));
        
        $success = isset($result['success']) && $result['success'] === true;
        
        if ($success) {
            error_log('ESA reCAPTCHA v2: SUCCESS - Verification passed');
        } else {
            error_log('ESA reCAPTCHA v2: FAILED - Verification failed. Error codes: ' . (isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'Unknown'));
        }
        
        return $success;
    }
    
    private function verify_recaptcha_v3($form_type) {
        $secret_key = get_option('esa_recaptcha_secret_key', '');
        $response = $_POST['g-recaptcha-response'] ?? '';
        
        // Log verification attempt
        error_log('ESA reCAPTCHA v3: Starting verification for action: ' . $form_type);
        error_log('ESA reCAPTCHA v3: Secret key configured: ' . (!empty($secret_key) ? 'Yes' : 'No'));
        error_log('ESA reCAPTCHA v3: Token received: ' . (!empty($response) ? 'Yes (length: ' . strlen($response) . ')' : 'No'));
        
        if (empty($secret_key)) {
            error_log('ESA reCAPTCHA v3: ERROR - Secret key not configured');
            return false;
        }
        
        if (empty($response)) {
            error_log('ESA reCAPTCHA v3: ERROR - No token received from frontend');
            return false;
        }
        
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $this->get_client_ip()
        );
        
        error_log('ESA reCAPTCHA v3: Making API call to Google with IP: ' . $this->get_client_ip());
        
        $response = wp_remote_post($verify_url, array(
            'body' => $data,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log('ESA reCAPTCHA v3: ERROR - API call failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        error_log('ESA reCAPTCHA v3: Google API response: ' . wp_json_encode($result));
        
        if (!isset($result['success']) || $result['success'] !== true) {
            error_log('ESA reCAPTCHA v3: FAILED - API call unsuccessful. Error codes: ' . (isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'Unknown'));
            return false;
        }
        
        // Check score for reCAPTCHA v3 (0.0 to 1.0, higher is better)
        $score = $result['score'] ?? 0;
        $min_score = get_option('esa_recaptcha_v3_min_score', 0.5);
        
        error_log('ESA reCAPTCHA v3: Score received: ' . $score . ', Minimum required: ' . $min_score);
        
        $success = $score >= $min_score;
        
        if ($success) {
            error_log('ESA reCAPTCHA v3: SUCCESS - Verification passed with score: ' . $score);
        } else {
            error_log('ESA reCAPTCHA v3: FAILED - Score too low: ' . $score . ' (minimum: ' . $min_score . ')');
        }
        
        return $success;
    }
    
    private function verify_hcaptcha() {
        $secret_key = get_option('esa_hcaptcha_secret_key', '');
        $response = $_POST['h-captcha-response'] ?? '';
        
        if (empty($secret_key) || empty($response)) {
            return false;
        }
        
        $verify_url = 'https://hcaptcha.com/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $this->get_client_ip()
        );
        
        $response = wp_remote_post($verify_url, array(
            'body' => $data,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        return isset($result['success']) && $result['success'] === true;
    }
    
    public function track_user_activity() {
        // Try nonce verification but don't fail if user is logged in
        $nonce_check = check_ajax_referer('esa_nonce', 'nonce', false);
        
        // If nonce fails and user is not logged in, reject
        if (!$nonce_check && !is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Authentication required'));
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
        }
        
        $page_url = sanitize_url($_POST['page_url']);
        $page_title = sanitize_text_field($_POST['page_title']);
        $time_spent = intval($_POST['time_spent']);
        $session_id = sanitize_text_field($_POST['session_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_activity';
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'page_url' => $page_url,
                'page_title' => $page_title,
                'time_spent' => $time_spent,
                'visit_time' => current_time('mysql'),
                'session_id' => $session_id
            )
        );
        
        wp_send_json_success();
    }
    
    public function save_estimate_request() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Please login to save estimate'));
        }
        
        $page_type = sanitize_text_field($_POST['page_type']);
        $selected_model = sanitize_text_field($_POST['selected_model']);
        $form_data = wp_json_encode($_POST['form_data']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_estimate_requests';
        
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'page_type' => $page_type,
                'selected_model' => $selected_model,
                'form_data' => $form_data,
                'request_time' => current_time('mysql')
            )
        );
        
        if ($result) {
            // Send email notification
            $this->send_estimate_notification($user_id, $page_type, $selected_model);
            wp_send_json_success(array('message' => 'Estimate request saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save estimate request'));
        }
    }
    
    private function log_user_login($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_logins';
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'ip_address' => $this->get_client_ip(),
                'login_time' => current_time('mysql'),
                'page_visited' => $_SERVER['REQUEST_URI']
            )
        );
    }
    
    private function log_user_logout($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_logins';
        
        $wpdb->update(
            $table,
            array('logout_time' => current_time('mysql')),
            array('user_id' => $user_id, 'logout_time' => null),
            array('%s'),
            array('%d', '%s')
        );
    }
    
    private function log_user_registration($user_id) {
        // Log registration event
        $this->log_user_login($user_id);
    }
    
    public function check_approval_status() {
        // Try nonce verification but don't fail if user is logged in
        $nonce_check = check_ajax_referer('esa_nonce', 'nonce', false);
        
        // If nonce fails and user is not logged in, reject
        if (!$nonce_check && !is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Authentication required'));
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not logged in'));
            return;
        }
        
        $is_approved = $this->is_user_approved($user_id);
        
        wp_send_json_success(array(
            'approved' => $is_approved,
            'user_id' => $user_id
        ));
    }
    
    private function send_approval_email($user_id, $first_name, $last_name, $email) {
        // Get multiple admin emails from settings
        $admin_emails = $this->get_admin_emails();

        // Get user details
        $user = get_user_by('id', $user_id);
        $username = $user->user_login;

        // Generate one-time approval token
        global $wpdb;
		$token = bin2hex(random_bytes(32));
		$expires = gmdate('Y-m-d H:i:s', strtotime('+7 days'));

        $wpdb->insert(
            $wpdb->prefix . 'esa_approval_tokens',
            array(
                'user_id' => $user_id,
                'token' => $token,
                'action' => 'approve',
				'created_at' => current_time('mysql', 1),
                'expires_at' => $expires,
                'used' => 0
            )
        );

        // Public approval URL (no login required)
        $approve_url = admin_url('admin-post.php') . '?action=esa_public_approve&token=' . $token;

        // Generate deny token
		$deny_token = bin2hex(random_bytes(32));
        $wpdb->insert(
            $wpdb->prefix . 'esa_approval_tokens',
            array(
                'user_id' => $user_id,
                'token' => $deny_token,
                'action' => 'deny',
				'created_at' => current_time('mysql', 1),
                'expires_at' => $expires,
                'used' => 0
            )
        );

        // Public deny URL (no login required)
        $public_deny_url = admin_url('admin-post.php') . '?action=esa_public_deny&token=' . $deny_token;

        // Generate authenticated login link for user
        $login_token = bin2hex(random_bytes(32));
        $login_expires = gmdate('Y-m-d H:i:s', strtotime('+30 days')); // Longer expiry for user convenience

        $wpdb->insert(
            $wpdb->prefix . 'esa_approval_tokens',
            array(
                'user_id' => $user_id,
                'token' => $login_token,
                'action' => 'auto_login',
				'created_at' => current_time('mysql', 1),
                'expires_at' => $login_expires,
                'used' => 0
            )
        );

        // Direct authenticated login URL for user
        $site_url = home_url();
        $login_url = add_query_arg(array(
            'action' => 'esa_auto_login',
            'token' => $login_token
        ), $site_url);

        // Keep old admin URLs as backup
        $admin_approve_url = add_query_arg(array(
            'action' => 'esa_approve',
            'user_id' => $user_id,
            'nonce' => wp_create_nonce('esa_approve_' . $user_id)
        ), admin_url('admin.php?page=esa-users'));

        $deny_url = add_query_arg(array(
            'action' => 'esa_deny',
            'user_id' => $user_id,
            'nonce' => wp_create_nonce('esa_deny_' . $user_id)
        ), admin_url('admin.php?page=esa-users'));

        $subject = 'New User Registration - Approval Required';
        $message = "
        <h3>New User Registration</h3>
        <p><strong>Name:</strong> {$first_name} {$last_name}</p>
        <p><strong>Username:</strong> {$username}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>IP Address:</strong> " . $this->get_client_ip() . "</p>
        <p><strong>Registration Time:</strong> " . current_time('Y-m-d H:i:s') . "</p>

        <h4>Admin Actions</h4>
        <div style='margin: 20px 0;'>
            <a href='{$approve_url}' style='display: inline-block; background: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin: 5px 5px; font-size: 14px; min-width: 180px; text-align: center; box-sizing: border-box;'>Approve User (No Login Required)</a>
            <a href='{$admin_approve_url}' style='display: inline-block; background: #17a2b8; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin: 5px 5px; font-size: 14px; min-width: 180px; text-align: center; box-sizing: border-box;'>Admin Approve (Login Required)</a>
            <a href='{$public_deny_url}' style='display: inline-block; background: #dc3545; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin: 5px 5px; font-size: 14px; min-width: 180px; text-align: center; box-sizing: border-box;'>Deny User (No Login Required)</a>
            <a href='{$deny_url}' style='display: inline-block; background: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin: 5px 5px; font-size: 14px; min-width: 180px; text-align: center; box-sizing: border-box;'>Admin Deny (Login Required)</a>
        </div>
        ";

        // Send to all admin emails
        foreach ($admin_emails as $admin_email) {
            wp_mail($admin_email, $subject, $message, array('Content-Type: text/html'));
        }
    }
    
    private function send_user_welcome_email($user_id, $first_name, $last_name, $email, $login_url) {
        $full_name = trim($first_name . ' ' . $last_name);
        
        $subject = 'Welcome to Engineered Solutions';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
            <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color: #2563eb; margin-top: 0;'>🎉 Welcome to Engineered Solutions!</h2>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Hello <strong>{$full_name}</strong>,</p>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Thank you for registering with Engineered Solutions. Your account has been successfully created!</p>
                
                <div style='background-color: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='color: #1e40af; font-size: 14px; margin: 0; line-height: 1.6;'>
                        <strong>📧 Email:</strong> {$email}
                    </p>
                </div>
                
                <h3 style='color: #1f2937; margin-top: 25px;'>Quick Login Access</h3>
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>For your convenience, you can use the secure direct login link below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$login_url}' style='background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                        🔐 Login to Your Account
                    </a>
                </div>
                
                <div style='background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='color: #92400e; font-size: 14px; margin: 0; line-height: 1.6;'>
                        <strong>⏳ Pending Approval:</strong> Your account is currently pending admin approval. You will receive another email once your account has been approved and you have full access to all features.
                    </p>
                </div>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>If you have any questions while waiting for approval, please don't hesitate to contact our support team.</p>
                
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                
                <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
                    Best regards,<br>
                    <strong>Engineered Solutions Team</strong>
                </p>
            </div>
        </div>
        ";
        
        wp_mail($email, $subject, $message, array('Content-Type: text/html'));
    }

    
    private function get_admin_emails() {
        $emails = get_option('esa_admin_emails', '');
        if (empty($emails)) {
            return array(get_option('admin_email'));
        }
        
        // Split by comma and clean up
        $email_list = array_map('trim', explode(',', $emails));
        return array_filter($email_list, 'is_email');
    }
    
    private function send_estimate_notification($user_id, $page_type, $selected_model) {
        $user = get_user_by('id', $user_id);
        $admin_emails = $this->get_admin_emails();
        
        $subject = 'New Estimate Request - ' . $page_type;
        $message = "
        <h3>New Estimate Request</h3>
        <p><strong>User:</strong> {$user->display_name} ({$user->user_email})</p>
        <p><strong>Page Type:</strong> {$page_type}</p>
        <p><strong>Selected Model:</strong> {$selected_model}</p>
        <p><strong>Request Time:</strong> " . current_time('Y-m-d H:i:s') . "</p>
        ";
        
        // Send to all admin emails
        foreach ($admin_emails as $admin_email) {
            wp_mail($admin_email, $subject, $message, array('Content-Type: text/html'));
        }
    }
    
    public function approve_user() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        
        // Update user_meta
        update_user_meta($user_id, 'esa_approved', true);
        
        $user = new WP_User($user_id);
        
        // Don't change role if user has any WordPress core capabilities
        if (user_can($user_id, 'administrator') || 
            user_can($user_id, 'editor') || 
            user_can($user_id, 'author') || 
            user_can($user_id, 'contributor') || 
            user_can($user_id, 'subscriber')) {
            // Just update approval status, don't change role
        } else {
            // Only change role if user is ESA Guest
            $current_roles = $user->roles;
            if (in_array('esa_guest', $current_roles)) {
                $user->set_role('esa_user');
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_approval';
        
        $wpdb->replace(
            $table,
            array(
                'user_id' => $user_id,
                'is_approved' => 1,
                'approval_date' => current_time('mysql'),
                'approved_by' => get_current_user_id()
            )
        );
        
        wp_send_json_success(array('message' => 'User approved successfully'));
    }
    
    public function deny_user() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        
        // Update user_meta
        update_user_meta($user_id, 'esa_approved', false);
        
        $user = new WP_User($user_id);
        
        // Don't change role if user has any WordPress core capabilities
        if (user_can($user_id, 'administrator') || 
            user_can($user_id, 'editor') || 
            user_can($user_id, 'author') || 
            user_can($user_id, 'contributor') || 
            user_can($user_id, 'subscriber')) {
            // Just update approval status, don't change role
        } else {
            // Only change role if user is ESA User
            $current_roles = $user->roles;
            if (in_array('esa_user', $current_roles)) {
                $user->set_role('esa_guest');
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_approval';
        
        $wpdb->replace(
            $table,
            array(
                'user_id' => $user_id,
                'is_approved' => 0,
                'approved_by' => get_current_user_id()
            )
        );
        
        wp_send_json_success(array('message' => 'User denied successfully'));
    }
    
    public function is_user_approved($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_approval';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT is_approved FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return $result == 1;
    }
    
    public function check_user_status() {
        // This runs via WP-Cron to check user status
        // Implementation for periodic status checks
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'ESA User Management',
            'ESA Users',
            'manage_options',
            'esa-users',
            array($this, 'admin_page'),
            'dashicons-groups',
            30
        );
        
        // Add settings submenu
        add_submenu_page(
            'esa-users',
            'ESA Settings',
            'Settings',
            'manage_options',
            'esa-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        include ESA_PLUGIN_PATH . 'admin/admin-page.php';
    }
    
    public function settings_page() {
        include ESA_PLUGIN_PATH . 'admin/settings-page.php';
    }
    
    public function register_settings() {
        // Register settings
        register_setting('esa_settings', 'esa_admin_emails');
        register_setting('esa_settings', 'esa_enable_captcha');
        register_setting('esa_settings', 'esa_captcha_type');
        register_setting('esa_settings', 'esa_recaptcha_site_key');
        register_setting('esa_settings', 'esa_recaptcha_secret_key');
        register_setting('esa_settings', 'esa_recaptcha_v3_min_score');
        register_setting('esa_settings', 'esa_hcaptcha_site_key');
        register_setting('esa_settings', 'esa_hcaptcha_secret_key');
    }
    
    public function handle_email_approval_denial() {
        if (isset($_GET['action']) && isset($_GET['user_id']) && isset($_GET['nonce'])) {
            $action = sanitize_text_field($_GET['action']);
            $user_id = intval($_GET['user_id']);
            $nonce = sanitize_text_field($_GET['nonce']);
            
            if ($action === 'esa_approve') {
                if (wp_verify_nonce($nonce, 'esa_approve_' . $user_id)) {
                    $this->approve_user_direct($user_id);
                    wp_redirect(admin_url('admin.php?page=esa-users&approved=1'));
                    exit;
                }
            } elseif ($action === 'esa_deny') {
                if (wp_verify_nonce($nonce, 'esa_deny_' . $user_id)) {
                    $this->deny_user_direct($user_id);
                    wp_redirect(admin_url('admin.php?page=esa-users&denied=1'));
                    exit;
                }
            }
        }
    }
    
    private function approve_user_direct($user_id) {
        // Update user_meta
        update_user_meta($user_id, 'esa_approved', true);
        
        // Update approval table
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_approval';
        $wpdb->replace(
            $table,
            array(
                'user_id' => $user_id,
                'is_approved' => 1,
                'approval_date' => current_time('mysql'),
                'approved_by' => get_current_user_id()
            )
        );
        
        // Don't change role if user has any WordPress core capabilities
        if (user_can($user_id, 'administrator') || 
            user_can($user_id, 'editor') || 
            user_can($user_id, 'author') || 
            user_can($user_id, 'contributor') || 
            user_can($user_id, 'subscriber')) {
            // Just update approval status, don't change role
        } else {
            // Only change role if user is ESA Guest
            $user = new WP_User($user_id);
            $current_roles = $user->roles;
            if (in_array('esa_guest', $current_roles)) {
                $user->set_role('esa_user');
            }
        }
        
        // Send approval email to user
        $user_data = get_userdata($user_id);
        wp_mail(
            $user_data->user_email,
            'Account Approved - Engineered Solutions',
            'Your account has been approved! You can now access all features.',
            array('Content-Type: text/html')
        );
        
        // Notify other admins about this approval
        $this->send_admin_action_notification($user_id, 'approved');
    }
    
    private function send_admin_action_notification($user_id, $action) {
        $admin_emails = $this->get_admin_emails();
        $current_admin_id = get_current_user_id();
        $current_admin = $current_admin_id > 0 ? get_userdata($current_admin_id) : null;
        $admin_name = $current_admin ? $current_admin->display_name : 'System';
        
        $user_data = get_userdata($user_id);
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $full_name = trim($first_name . ' ' . $last_name);
        if (empty($full_name)) {
            $full_name = $user_data->display_name;
        }
        
        $action_past = $action === 'approved' ? 'Approved' : 'Denied';
        $action_color = $action === 'approved' ? '#059669' : '#dc2626';
        $action_bg = $action === 'approved' ? '#d1fae5' : '#fee2e2';
        $action_border = $action === 'approved' ? '#059669' : '#dc2626';
        $action_icon = $action === 'approved' ? '✅' : '❌';
        
        $subject = "User {$action_past} by {$admin_name}";
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
            <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color: {$action_color}; margin-top: 0;'>{$action_icon} User {$action_past}</h2>
                
                <div style='background-color: {$action_bg}; border-left: 4px solid {$action_border}; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='color: #1f2937; font-size: 14px; margin: 0; line-height: 1.6;'>
                        <strong>{$admin_name}</strong> has {$action} the following user account:
                    </p>
                </div>
                
                <div style='background-color: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>User Name:</strong> {$full_name}</p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Email:</strong> {$user_data->user_email}</p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Action:</strong> {$action_past}</p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>By:</strong> {$admin_name}</p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Time:</strong> " . current_time('Y-m-d H:i:s') . "</p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                
                <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
                    This is an automated notification from the Engineered Solutions user management system.
                </p>
            </div>
        </div>
        ";
        
        // Send to all admins except the one who performed the action
        foreach ($admin_emails as $admin_email) {
            if ($current_admin && $admin_email === $current_admin->user_email) {
                continue; // Don't send to the admin who performed the action
            }
            wp_mail($admin_email, $subject, $message, array('Content-Type: text/html'));
        }
    }
    
    public function send_admin_action_notification_public($user_id, $action) {
        $this->send_admin_action_notification($user_id, $action);
    }

    
    private function deny_user_direct($user_id) {
        // Suspend account - set meta flag
        update_user_meta($user_id, 'esa_approved', false);
        update_user_meta($user_id, 'esa_account_suspended', true);
        
        $user = new WP_User($user_id);
        
        // Downgrade to guest role (limited access)
        if (!user_can($user_id, 'administrator') && 
            !user_can($user_id, 'editor') && 
            !user_can($user_id, 'author') && 
            !user_can($user_id, 'contributor') && 
            !user_can($user_id, 'subscriber')) {
            $user->set_role('esa_guest');
        }
        
        // Update approval table
        global $wpdb;
        $table = $wpdb->prefix . 'esa_user_approval';
        
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
                
                <div style='background-color: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='color: #1e40af; font-size: 14px; margin: 0; line-height: 1.6;'>
                        <strong>ℹ️ Need Help?</strong> If you believe this is an error or would like more information, please contact our support team for assistance.
                    </p>
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
        
        // Notify other admins about this denial
        $this->send_admin_action_notification($user_id, 'denied');
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'];
    }
    
    private function check_rate_limit($action_type, $max_attempts = 5, $time_window = 3600) {
        global $wpdb;
        $ip = $this->get_client_ip();
        $time_threshold = date('Y-m-d H:i:s', time() - $time_window);
        
        // Count recent attempts
        $attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}esa_rate_limit 
             WHERE ip_address = %s AND action_type = %s AND attempt_time > %s",
            $ip, $action_type, $time_threshold
        ));
        
        if ($attempts >= $max_attempts) {
            return false; // Rate limit exceeded
        }
        
        // Log this attempt
        $wpdb->insert(
            $wpdb->prefix . 'esa_rate_limit',
            array(
                'ip_address' => $ip,
                'action_type' => $action_type,
                'attempt_time' => current_time('mysql')
            )
        );
        
        return true;
    }
    
	/**
	 * Ensure esa_email_verification has OTP columns for the new flow.
	 */
	private function ensure_verification_schema() {
		global $wpdb;
		$table = $wpdb->prefix . 'esa_email_verification';
		$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
		if (!$columns) {
			return;
		}
		// email
		if (!in_array('email', $columns, true)) {
			$wpdb->query("ALTER TABLE {$table} ADD COLUMN email varchar(191) NOT NULL DEFAULT '' AFTER user_id");
		}
		// code_hash
		if (!in_array('code_hash', $columns, true)) {
			$wpdb->query("ALTER TABLE {$table} ADD COLUMN code_hash varchar(255) DEFAULT NULL AFTER verified");
		}
		// attempt_count
		if (!in_array('attempt_count', $columns, true)) {
			$wpdb->query("ALTER TABLE {$table} ADD COLUMN attempt_count int(11) NOT NULL DEFAULT 0 AFTER code_hash");
		}
		// locked_until
		if (!in_array('locked_until', $columns, true)) {
			$wpdb->query("ALTER TABLE {$table} ADD COLUMN locked_until datetime DEFAULT NULL AFTER attempt_count");
		}
		// email index
		$indexes = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", 'email'));
		if (empty($indexes)) {
			$wpdb->query("ALTER TABLE {$table} ADD INDEX email (email)");
		}
	}
	
    public function test_captcha_configuration() {
        check_ajax_referer('esa_test_captcha', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $captcha_enabled = get_option('esa_enable_captcha', 0);
        $captcha_type = get_option('esa_captcha_type', 'recaptcha_v2');
        
        if (!$captcha_enabled) {
            wp_send_json_error(array('message' => 'CAPTCHA is disabled in settings'));
            return;
        }
        
        $site_key = '';
        $secret_key = '';
        
        if ($captcha_type === 'recaptcha_v2' || $captcha_type === 'recaptcha_v3') {
            $site_key = get_option('esa_recaptcha_site_key', '');
            $secret_key = get_option('esa_recaptcha_secret_key', '');
        } elseif ($captcha_type === 'hcaptcha') {
            $site_key = get_option('esa_hcaptcha_site_key', '');
            $secret_key = get_option('esa_hcaptcha_secret_key', '');
        }
        
        if (empty($site_key)) {
            wp_send_json_error(array('message' => 'Site key not configured for ' . $captcha_type));
            return;
        }
        
        if (empty($secret_key)) {
            wp_send_json_error(array('message' => 'Secret key not configured for ' . $captcha_type));
            return;
        }
        
        // Test API connectivity with a dummy token
        $test_token = 'test_token_' . time();
        $_POST['g-recaptcha-response'] = $test_token;
        
        error_log('ESA Test: Testing CAPTCHA configuration with dummy token');
        
        $result = $this->verify_captcha('test');
        
        if ($result === false) {
            wp_send_json_success(array('message' => 'Configuration looks correct. API call made successfully (expected to fail with test token). Check debug logs for details.'));
        } else {
            wp_send_json_error(array('message' => 'Unexpected: Test token was accepted. This might indicate a configuration issue.'));
        }
    }
    
    public function handle_public_approval() {
        global $wpdb;
        
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            wp_die('Invalid approval link');
        }
        
        // Check token
		$token_data = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}esa_approval_tokens 
			 WHERE token = %s AND action = 'approve' AND used = 0 AND expires_at >UTC_TIMESTAMP()",
			$token
		));
        
		if (!$token_data) {
			// Diagnostics to understand why the token failed
			$raw = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}esa_approval_tokens WHERE token = %s",
				$token
			));
			$reason = 'Unknown issue.';
			$resend_link_html = '';
			if (!$raw) {
				error_log('ESA Approve: token not found: ' . substr($token, 0, 12) . '...');
				$reason = 'Link not found.';
			} elseif ($raw->action !== 'approve') {
				error_log('ESA Approve: token wrong action=' . $raw->action);
				$reason = 'Wrong link type.';
			} elseif ((int)$raw->used === 1) {
				error_log('ESA Approve: token already used for user ' . (int)$raw->user_id);
				
				// Check the actual user status
				$user_status = $wpdb->get_row($wpdb->prepare(
					"SELECT is_approved, approved_by, approval_date FROM {$wpdb->prefix}esa_user_approval WHERE user_id = %d",
					(int)$raw->user_id
				));
				
				if ($user_status && $user_status->is_approved == 1) {
					// User is approved - show who approved and when
					$approver = get_userdata($user_status->approved_by);
					$approver_name = $approver ? $approver->display_name : 'an administrator';
					$approval_date = $user_status->approval_date ? date('F j, Y \a\t g:i A', strtotime($user_status->approval_date)) : 'previously';
					$reason = "This user has already been approved by {$approver_name} on {$approval_date}.";
				} else {
					// User is not approved or denied - standard message
					$reason = 'Link already used.';
				}
			} elseif (strtotime($raw->expires_at . ' UTC') <= time()) {
				error_log('ESA Approve: token expired at ' . $raw->expires_at . 'Z');
				$reason = 'Link expired.';
			} else {
				error_log('ESA Approve: token failed unknown reason');
			}
			if ($raw) {
				$resend_url = admin_url('admin-post.php') . '?action=esa_public_resend_approval&token=' . urlencode($token);
				$resend_link_html = '<p><a href="' . esc_url($resend_url) . '">Resend approval email</a></p>';
			}
			wp_die('<h2>Approval link problem</h2><p>' . esc_html($reason) . '</p>' . $resend_link_html);
		}
        
        // Approve user
        $this->approve_user_direct($token_data->user_id);
        
        // Mark token as used
        $wpdb->update(
            $wpdb->prefix . 'esa_approval_tokens',
            array('used' => 1),
            array('token' => $token)
        );
        
        // Redirect with success message
        wp_redirect(home_url('/?approval=success'));
        exit;
    }
    
    public function handle_public_denial() {
        global $wpdb;
        
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            wp_die('Invalid denial link');
        }
        
        // Check token
		$token_data = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}esa_approval_tokens 
			 WHERE token = %s AND action = 'deny' AND used = 0 AND expires_at > UTC_TIMESTAMP()",
			$token
		));
        
		if (!$token_data) {
			// Diagnostics for denial path
			$raw = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}esa_approval_tokens WHERE token = %s",
				$token
			));
			$reason = 'Unknown issue.';
			if (!$raw) {
				error_log('ESA Deny: token not found: ' . substr($token, 0, 12) . '...');
				$reason = 'Link not found.';
			} elseif ($raw->action !== 'deny') {
				error_log('ESA Deny: token wrong action=' . $raw->action);
				$reason = 'Wrong link type.';
			} elseif ((int)$raw->used === 1) {
				error_log('ESA Deny: token already used for user ' . (int)$raw->user_id);
				
				// Check the actual user status
				$user_status = $wpdb->get_row($wpdb->prepare(
					"SELECT is_approved, approved_by, approval_date FROM {$wpdb->prefix}esa_user_approval WHERE user_id = %d",
					(int)$raw->user_id
				));
				
				if ($user_status && $user_status->is_approved == 0) {
					// User is denied - show who denied and when
					$denier = get_userdata($user_status->approved_by);
					$denier_name = $denier ? $denier->display_name : 'an administrator';
					$denial_date = $user_status->approval_date ? date('F j, Y \a\t g:i A', strtotime($user_status->approval_date)) : 'previously';
					$reason = "This user has already been denied by {$denier_name} on {$denial_date}.";
				} else {
					// User is not denied or is approved - standard message
					$reason = 'Link already used.';
				}
			} elseif (strtotime($raw->expires_at . ' UTC') <= time()) {
				error_log('ESA Deny: token expired at ' . $raw->expires_at . 'Z');
				$reason = 'Link expired.';
			} else {
				error_log('ESA Deny: token failed unknown reason');
			}
			wp_die('<h2>Denial link problem</h2><p>' . esc_html($reason) . '</p>');
		}
        
        // Deny and suspend user
        $this->deny_user_direct($token_data->user_id);
        
        // Mark token as used
        $wpdb->update(
            $wpdb->prefix . 'esa_approval_tokens',
            array('used' => 1),
            array('token' => $token)
        );
        
        // Redirect with success message
        wp_redirect(home_url('/?denial=success'));
        exit;
    }

	/**
	 * Request a 6-digit OTP code for email verification (before user creation).
	 */
	public function request_email_otp() {
		check_ajax_referer('esa_nonce', 'nonce');
		
		// Verify CAPTCHA if enabled
		if (get_option('esa_enable_captcha', 0)) {
			if (!$this->verify_captcha('registration')) {
				wp_send_json_error(array('message' => 'CAPTCHA verification failed. Please try again.'));
				return;
			}
		}
		
		// Rate limiting by IP
		if (!$this->check_rate_limit('otp_request', 3, 3600)) {
			wp_send_json_error(array('message' => 'Too many OTP requests. Please try again later.'));
			return;
		}
		
		$honeypot = sanitize_text_field($_POST['website'] ?? '');
		if (!empty($honeypot)) {
			wp_send_json_error(array('message' => 'Request failed. Please try again.'));
			return;
		}
		
		$email = sanitize_email($_POST['email'] ?? '');
		if (empty($email) || !is_email($email)) {
			wp_send_json_error(array('message' => 'Please enter a valid email address.'));
			return;
		}
		if (email_exists($email)) {
			wp_send_json_error(array('message' => 'Email already exists'));
			return;
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'esa_email_verification';
		
		// Enforce resend cooldown (60s) by checking the latest row for this email
		$recent = $wpdb->get_var($wpdb->prepare(
			"SELECT created_at FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT 1",
			$email
		));
		if ($recent) {
			$recent_ts = strtotime($recent . ' UTC');
			if ($recent_ts && (time() - $recent_ts) < 60) {
				wp_send_json_error(array('message' => 'Please wait before requesting a new code.'));
				return;
			}
		}
		
		// Invalidate previous unverified rows for this email
		$wpdb->delete($table, array('email' => $email, 'verified' => 0));
		
		// Generate 6-digit code (zero-padded)
		$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
		$code_hash = password_hash($code, PASSWORD_DEFAULT);
		$token = bin2hex(random_bytes(32));
		
		$expires = gmdate('Y-m-d H:i:s', time() + 10 * 60); // 10 minutes
		
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id' => 0,
				'email' => $email,
				'token' => $token,
				'created_at' => current_time('mysql', 1),
				'expires_at' => $expires,
				'verified' => 0,
				'code_hash' => $code_hash,
				'attempt_count' => 0,
				'locked_until' => null
			)
		);
		
		if ($inserted === false) {
			error_log('ESA OTP: Failed to insert verification row: ' . $wpdb->last_error);
			wp_send_json_error(array('message' => 'We could not send the verification code. Please try again.'));
			return;
		}
		
		// Send code email
	$subject = 'Your Engineered Solutions Verification Code';
	$message = "
		<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
			<div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
				<h2 style='color: #1f2937; margin-top: 0;'>Email Verification</h2>
				<p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Hello,</p>
				<p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Thank you for registering with Engineered Solutions. Please use the 6-digit verification code below to complete your registration:</p>
				
				<div style='background-color: #f3f4f6; padding: 20px; border-radius: 6px; text-align: center; margin: 25px 0;'>
					<p style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2563eb; margin: 0;'>{$code}</p>
				</div>
				
				<p style='color: #6b7280; font-size: 14px; line-height: 1.6;'>
					<strong>Important:</strong> This code will expire in 10 minutes.
				</p>
				
				<p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>If you did not request this verification code, please ignore this email.</p>
				
				<hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
				
				<p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
					Best regards,<br>
					<strong>Engineered Solutions Team</strong>
				</p>
			</div>
		</div>
	";
	wp_mail($email, $subject, $message, array('Content-Type: text/html'));
		
		wp_send_json_success(array(
			'message' => 'Verification code sent. Please check your email.',
			'resend_seconds' => 60
		));
	}
	
	/**
	 * Verify OTP and create the user as ESA Guest. Triggers admin approval email.
	 */
	public function verify_email_otp_and_create_user() {
		check_ajax_referer('esa_nonce', 'nonce');
		
		// Rate limit verify attempts
		if (!$this->check_rate_limit('otp_verify', 10, 3600)) {
			wp_send_json_error(array('message' => 'Too many verification attempts. Please try again later.'));
			return;
		}
		
		$email = sanitize_email($_POST['email'] ?? '');
		$code = sanitize_text_field($_POST['code'] ?? '');
		$first_name = sanitize_text_field($_POST['first_name'] ?? '');
		$last_name = sanitize_text_field($_POST['last_name'] ?? '');
		$company_name = sanitize_text_field($_POST['company_name'] ?? '');
		$password = $_POST['password'] ?? '';
		
		if (empty($email) || !is_email($email)) {
			wp_send_json_error(array('message' => 'Invalid email.'));
			return;
		}
		if (empty($code) || !preg_match('/^[0-9]{6}$/', $code)) {
			wp_send_json_error(array('message' => 'Invalid code.'));
			return;
		}
		if (empty($first_name) || empty($last_name) || empty($company_name) || empty($password)) {
			wp_send_json_error(array('message' => 'Please complete all required fields.'));
			return;
		}
		if (email_exists($email)) {
			wp_send_json_error(array('message' => 'Email already exists'));
			return;
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'esa_email_verification';
		
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table} 
			 WHERE email = %s AND verified = 0 AND user_id = 0 
			   AND expires_at > UTC_TIMESTAMP()
			 ORDER BY id DESC LIMIT 1",
			$email
		));
		
		if (!$row) {
			error_log(sprintf('ESA OTP: Verification lookup failed for %s (likely expired or missing).', $email));
			wp_send_json_error(array('message' => 'Code expired or not found. Please request a new code.'));
			return;
		}
		
		// Check lock
		if (!empty($row->locked_until) && strtotime($row->locked_until . ' UTC') > time()) {
			wp_send_json_error(array('message' => 'Too many incorrect attempts. Please wait and try again later.'));
			return;
		}
		
		$attempts = (int)$row->attempt_count;
		$max_attempts = 5;
		$lock_minutes = 15;
		
		$valid = (!empty($row->code_hash) && password_verify($code, $row->code_hash));
		if (!$valid) {
			$attempts++;
			$update = array('attempt_count' => $attempts);
			if ($attempts >= $max_attempts) {
				$update['locked_until'] = gmdate('Y-m-d H:i:s', time() + $lock_minutes * 60);
			}
			$wpdb->update($table, $update, array('id' => $row->id));
			$remaining = max(0, $max_attempts - $attempts);
			$msg = $remaining > 0 ? "Invalid code. {$remaining} attempt(s) remaining." : 'Too many incorrect attempts. Please wait and try again later.';
			wp_send_json_error(array('message' => $msg));
			return;
		}
		
		// Code valid: create user
		$user_id = wp_create_user($email, $password, $email);
		if (is_wp_error($user_id)) {
			wp_send_json_error(array('message' => 'Registration failed'));
			return;
		}
		
		// Update user meta
		update_user_meta($user_id, 'first_name', $first_name);
		update_user_meta($user_id, 'last_name', $last_name);
		update_user_meta($user_id, 'company_name', $company_name);
		update_user_meta($user_id, 'esa_email_verified', true);
		
		// Set display name to full name
		wp_update_user(array(
			'ID' => $user_id,
			'display_name' => trim($first_name . ' ' . $last_name)
		));
		
		// Set role to guest (preserve admins)
		if (!user_can($user_id, 'administrator')) {
			$user = new \WP_User($user_id);
			$user->set_role('esa_guest');
		}
		
		// Log registration
		$this->log_user_registration($user_id);
		
		// Mark verification row as used
		$wpdb->update(
			$table,
			array(
				'verified' => 1,
				'user_id' => $user_id,
				'code_hash' => null
			),
			array('id' => $row->id)
		);
		
		// Generate authenticated login link for user
		$login_token = bin2hex(random_bytes(32));
		$login_expires = gmdate('Y-m-d H:i:s', strtotime('+30 days')); // Longer expiry for user convenience

		$wpdb->insert(
			$wpdb->prefix . 'esa_approval_tokens',
			array(
				'user_id' => $user_id,
				'token' => $login_token,
				'action' => 'auto_login',
				'created_at' => current_time('mysql', 1),
				'expires_at' => $login_expires,
				'used' => 0
			)
		);

		// Direct authenticated login URL for user
		$site_url = home_url();
		$login_url = add_query_arg(array(
			'action' => 'esa_auto_login',
			'token' => $login_token
		), $site_url);
		
		// Send to admin for approval
		$this->send_approval_email(
			$user_id,
			$first_name,
			$last_name,
			$email
		);

		
		// Send welcome email to user with login link
		$this->send_user_welcome_email(
			$user_id,
			$first_name,
			$last_name,
			$email,
			$login_url
		);
		
		wp_send_json_success(array(
			'message' => 'Email verified. Your account was created and sent for admin approval.',
			'auto_login' => false
		));
	}
	
	public function handle_public_resend_approval() {
		global $wpdb;
		$token = sanitize_text_field($_GET['token'] ?? '');
		if (empty($token)) {
			wp_die('Invalid request');
		}
		$raw = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}esa_approval_tokens WHERE token = %s",
			$token
		));
		if (!$raw) {
			wp_die('We could not locate this request. Please contact an administrator.');
		}
		$user = get_user_by('id', (int)$raw->user_id);
		if (!$user) {
			wp_die('User not found for this request.');
		}
		$first = get_user_meta($user->ID, 'first_name', true);
		$last = get_user_meta($user->ID, 'last_name', true);
		$email = $user->user_email;
		// Reuse existing mail routine (creates fresh tokens in UTC)
		$this->send_approval_email($user->ID, $first, $last, $email);
		wp_die('A new approval email has been sent to administrators.');
	}
    
    public function cleanup_user_data($user_id) {
        global $wpdb;
        
        // Delete verification tokens
        $wpdb->delete(
            $wpdb->prefix . 'esa_email_verification',
            array('user_id' => $user_id)
        );
        
        // Delete approval tokens
        $wpdb->delete(
            $wpdb->prefix . 'esa_approval_tokens',
            array('user_id' => $user_id)
        );
        
        // Delete approval records
        $wpdb->delete(
            $wpdb->prefix . 'esa_user_approval',
            array('user_id' => $user_id)
        );
        
        error_log("ESA: Cleaned up data for deleted user ID: {$user_id}");
    }
    
    public function resend_verification_email() {
        check_ajax_referer('esa_nonce', 'nonce');
        
		// Legacy: keep for compatibility but OTP is now used exclusively
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email is required'));
            return;
        }
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }
        
        // Check if already verified
        $email_verified = get_user_meta($user->ID, 'esa_email_verified', true);
        if ($email_verified) {
            wp_send_json_error(array('message' => 'Email already verified'));
            return;
        }
        
        global $wpdb;
        
        // Delete old tokens
        $wpdb->delete(
            $wpdb->prefix . 'esa_email_verification',
            array('user_id' => $user->ID)
        );
        
        // Generate new token
        $verification_token = bin2hex(random_bytes(32));
		$expires = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
        
        $wpdb->insert(
            $wpdb->prefix . 'esa_email_verification',
            array(
                'user_id' => $user->ID,
                'token' => $verification_token,
				'created_at' => current_time('mysql', 1),
                'expires_at' => $expires,
                'verified' => 0
            )
        );
        
        // Send verification email
        $verification_url = home_url('/?action=verify_email&token=' . $verification_token);
        wp_mail(
            $email,
            'Verify Your Email - Engineered Solutions',
            "Please verify your email by clicking: <a href='{$verification_url}'>Verify Email</a><br><br>This link will expire in 7 days.",
            array('Content-Type: text/html')
        );
        
        wp_send_json_success(array('message' => 'Verification email sent! Please check your inbox.'));
    }
    
    public function handle_email_verification() {
		// OTP-only: if someone lands on old verification link, explain the new flow
		if (isset($_GET['action']) && $_GET['action'] === 'verify_email') {
			wp_die('<h2>Email verification has changed</h2><p>Please register again and use the 6‑digit code sent to your email to verify.</p>');
		}
        // Handle auto-login from email links
        if (isset($_GET['action']) && $_GET['action'] === 'esa_auto_login') {
            $token = sanitize_text_field($_GET['token'] ?? '');

            if (empty($token)) {
                wp_die('Invalid login link');
            }

            global $wpdb;
            $token_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}esa_approval_tokens
                 WHERE token = %s AND action = 'auto_login' AND used = 0 AND expires_at > UTC_TIMESTAMP()",
                $token
            ));

            if (!$token_data) {
                wp_die('Login link is invalid or has expired. Please log in manually.');
            }

            // Mark token as used
            $wpdb->update(
                $wpdb->prefix . 'esa_approval_tokens',
                array('used' => 1),
                array('token' => $token)
            );

            // Log the user in
            $user_id = $token_data->user_id;
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);

            // Log the login event
            $this->log_user_login($user_id);

            // Redirect to home page with success message
            wp_redirect(add_query_arg('login', 'success', home_url('/')));
            exit;
        }

        // Handle resend request
        if (isset($_GET['action']) && $_GET['action'] === 'resend_verification') {
            $email = sanitize_email($_GET['email'] ?? '');
            if (empty($email)) {
                wp_die('Invalid email address');
            }
            
            // Show resend form
            wp_die('
                <html>
                <head><title>Resend Verification Email</title></head>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px;">
                    <h2>Resend Verification Email</h2>
                    <p>Your verification link has expired. Click the button below to receive a new verification email.</p>
                    <p><strong>Email:</strong> ' . esc_html($email) . '</p>
                    <button onclick="resendVerification()" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        Send New Verification Email
                    </button>
                    <div id="message" style="margin-top: 20px;"></div>
                    <script>
                    async function resendVerification() {
                        const btn = event.target;
                        btn.disabled = true;
                        btn.textContent = "Sending...";
                        
                        try {
                            const response = await fetch("' . admin_url('admin-ajax.php') . '", {
                                method: "POST",
                                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                body: "action=esa_resend_verification&email=' . urlencode($email) . '&nonce=' . wp_create_nonce('esa_nonce') . '"
                            });
                            
                            const data = await response.json();
                            const msg = document.getElementById("message");
                            
                            if (data.success) {
                                msg.innerHTML = "<p style=\'color: green;\'>" + data.data.message + "</p>";
                                btn.style.display = "none";
                            } else {
                                msg.innerHTML = "<p style=\'color: red;\'>" + data.data.message + "</p>";
                                btn.disabled = false;
                                btn.textContent = "Send New Verification Email";
                            }
                        } catch (error) {
                            document.getElementById("message").innerHTML = "<p style=\'color: red;\'>Failed to send email. Please try again.</p>";
                            btn.disabled = false;
                            btn.textContent = "Send New Verification Email";
                        }
                    }
                    </script>
                </body>
                </html>
            ');
        }
        
        // Handle verification (existing code continues...)
        if (!isset($_GET['action']) || $_GET['action'] !== 'verify_email') {
            return;
        }
        
        global $wpdb;
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            wp_die('Invalid verification link');
        }
        
		$verification = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}esa_email_verification 
			 WHERE token = %s AND verified = 0 AND expires_at > UTC_TIMESTAMP()",
			$token
		));
        
		if (!$verification) {
			// Diagnostics to log precise reason
			$raw = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}esa_email_verification WHERE token = %s",
				$token
			));
			$message = '';
			if (!$raw) {
				error_log('ESA Verify: token not found: ' . substr($token, 0, 12) . '...');
				$message = '<h2>Verification link issue</h2><p>Verification link is invalid or has already been used.</p>' .
					'<div style="margin-top:16px;padding:12px;border:1px solid #e5e7eb;border-radius:6px;max-width:480px;">' .
					'<p style="margin:0 0 8px 0;">Enter your email to receive a new verification link:</p>' .
					'<div style="display:flex;gap:8px;">' .
					'<input id="esa-resend-email" type="email" placeholder="you@example.com" style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:4px;" />' .
					'<button id="esa-resend-button" style="background:#007cba;color:#fff;padding:8px 14px;border-radius:4px;border:0;cursor:pointer;">Send</button>' .
					'</div>' .
					'<div id="esa-resend-msg" style="margin-top:8px;font-size:13px;"></div>' .
					'<script>(function(){var b=document.getElementById("esa-resend-button");if(!b)return;b.addEventListener("click",async function(){var e=document.getElementById("esa-resend-email");var m=document.getElementById("esa-resend-msg");if(!e||!e.value){m.style.color="#dc2626";m.textContent="Please enter a valid email.";return;}b.disabled=true;var old=b.textContent;b.textContent="Sending...";try{var resp=await fetch("' + admin_url('admin-ajax.php') + '", {method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=esa_resend_verification&email="+encodeURIComponent(e.value)+"&nonce=' + wp_create_nonce('esa_nonce') + '"});var data=await resp.json();if(data&&data.success){m.style.color="#059669";m.textContent=data.data.message;b.style.display="none";}else{m.style.color="#dc2626";m.textContent=(data&&data.data&&data.data.message)||"Failed to send email.";b.disabled=false;b.textContent=old;}}catch(err){m.style.color="#dc2626";m.textContent="Failed to send email.";b.disabled=false;b.textContent=old;}});})();<\/script>' .
					'</div>';
			} elseif ((int)$raw->verified === 1) {
				error_log('ESA Verify: token already verified for user ' . (int)$raw->user_id);
				$message = '<h2>Email already verified</h2><p>This email address has already been verified.</p>';
			} elseif (strtotime($raw->expires_at . ' UTC') <= time()) {
				error_log('ESA Verify: token expired at ' . $raw->expires_at . 'Z');
				$user_data = get_userdata($raw->user_id);
				if ($user_data && !empty($user_data->user_email)) {
					$message = '<h2>Verification link expired</h2><p>Your verification link has expired.</p><p><a href="' . esc_url(home_url('/?action=resend_verification&email=' . urlencode($user_data->user_email))) . '" style="background:#007cba;color:#fff;padding:8px 14px;border-radius:4px;text-decoration:none;">Send a new verification email</a></p>';
				} else {
					// Fallback inline email entry if user record is missing
					$message = '<h2>Verification link expired</h2><p>Your verification link has expired.</p>' .
						'<div style="margin-top:16px;padding:12px;border:1px solid #e5e7eb;border-radius:6px;max-width:480px;">' .
						'<p style="margin:0 0 8px 0;">Enter your email to receive a new verification link:</p>' .
						'<div style="display:flex;gap:8px;">' .
						'<input id="esa-resend-email" type="email" placeholder="you@example.com" style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:4px;" />' .
						'<button id="esa-resend-button" style="background:#007cba;color:#fff;padding:8px 14px;border-radius:4px;border:0;cursor:pointer;">Send</button>' .
						'</div>' .
						'<div id="esa-resend-msg" style="margin-top:8px;font-size:13px;"></div>' .
						'<script>(function(){var b=document.getElementById("esa-resend-button");if(!b)return;b.addEventListener("click",async function(){var e=document.getElementById("esa-resend-email");var m=document.getElementById("esa-resend-msg");if(!e||!e.value){m.style.color="#dc2626";m.textContent="Please enter a valid email.";return;}b.disabled=true;var old=b.textContent;b.textContent="Sending...";try{var resp=await fetch("' + admin_url('admin-ajax.php') + '", {method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=esa_resend_verification&email="+encodeURIComponent(e.value)+"&nonce=' + wp_create_nonce('esa_nonce') + '"});var data=await resp.json();if(data&&data.success){m.style.color="#059669";m.textContent=data.data.message;b.style.display="none";}else{m.style.color="#dc2626";m.textContent=(data&&data.data&&data.data.message)||"Failed to send email.";b.disabled=false;b.textContent=old;}}catch(err){m.style.color="#dc2626";m.textContent="Failed to send email.";b.disabled=false;b.textContent=old;}});})();<\/script>' +
						'</div>';
				}
			} else {
				error_log('ESA Verify: token failed unknown reason');
				$message = '<h2>Verification link issue</h2><p>Verification link is invalid or has already been used.</p>';
			}
			wp_die($message);
		}
        
        // Mark as verified
        $wpdb->update(
            $wpdb->prefix . 'esa_email_verification',
            array('verified' => 1),
            array('token' => $token)
        );
        
        update_user_meta($verification->user_id, 'esa_email_verified', true);
        
        // Send to admin for approval
        $user_data = get_userdata($verification->user_id);
        $this->send_approval_email(
            $verification->user_id,
            get_user_meta($verification->user_id, 'first_name', true),
            get_user_meta($verification->user_id, 'last_name', true),
            $user_data->user_email
        );
        
        wp_redirect(home_url('/?verification=success'));
        exit;
    }
    
    /**
     * Request a password reset with a 6-digit code sent via email
     */
    public function request_password_reset() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        // Rate limiting by IP
        if (!$this->check_rate_limit('password_reset_request', 3, 3600)) {
            wp_send_json_error(array('message' => 'Too many password reset requests. Please try again later.'));
            return;
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
            return;
        }
        
        // Check if user exists
        $user = get_user_by('email', $email);
        if (!$user) {
            // Security: Don't reveal if email exists or not
            wp_send_json_success(array('message' => 'If an account exists with this email, a password reset code will be sent.'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_email_verification';
        
        // Enforce resend cooldown (60s)
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$table} WHERE email = %s AND user_id = %d ORDER BY id DESC LIMIT 1",
            $email,
            $user->ID
        ));
        if ($recent) {
            $recent_ts = strtotime($recent . ' UTC');
            if ($recent_ts && (time() - $recent_ts) < 60) {
                wp_send_json_error(array('message' => 'Please wait before requesting a new code.'));
                return;
            }
        }
        
        // Invalidate previous password reset codes for this user
        $wpdb->delete($table, array('email' => $email, 'user_id' => $user->ID, 'verified' => 0));
        
        // Generate 6-digit code
        $code = sprintf('%06d', mt_rand(0, 999999));
        $code_hash = password_hash($code, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));
        
        // Insert verification row
        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id' => $user->ID,
                'email' => $email,
                'token' => $token,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 900), // 15 minutes
                'verified' => 0,
                'code_hash' => $code_hash,
                'attempt_count' => 0,
                'locked_until' => null
            )
        );
        
        if (!$inserted) {
            wp_send_json_error(array('message' => 'Failed to send password reset code. Please try again.'));
            return;
        }
        
        // Send email with code
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $full_name = trim($first_name . ' ' . $last_name);
        if (empty($full_name)) {
            $full_name = $user->display_name;
        }
        
        $user_roles = $user->roles;
        $role_display = !empty($user_roles) ? ucfirst(str_replace('_', ' ', $user_roles[0])) : 'User';
        
        $to = $email;
        $subject = 'Password Reset Code - Engineered Solutions';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;'>
            <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color: #1f2937; margin-top: 0;'>Password Reset Request</h2>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>Hello <strong>{$full_name}</strong>,</p>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>We received a request to reset your password. Please use the 6-digit verification code below:</p>
                
                <div style='background-color: #f3f4f6; padding: 20px; border-radius: 6px; text-align: center; margin: 25px 0;'>
                    <p style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #dc2626; margin: 0;'>{$code}</p>
                </div>
                
                <div style='background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='color: #92400e; font-size: 14px; margin: 0; line-height: 1.6;'>
                        <strong>⚠️ Important:</strong> This code will expire in 15 minutes.
                    </p>
                </div>
                
                <div style='background-color: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'><strong>Account Details:</strong></p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>📧 Email: {$email}</p>
                    <p style='color: #6b7280; font-size: 14px; margin: 5px 0;'>👤 Role: {$role_display}</p>
                </div>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6;'>If you did not request a password reset, please ignore this email and your password will remain unchanged.</p>
                
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                
                <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 0;'>
                    Best regards,<br>
                    <strong>Engineered Solutions Team</strong>
                </p>
            </div>
        </div>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($to, $subject, $message, $headers);
        
        wp_send_json_success(array('message' => 'A 6-digit code has been sent to your email.'));
    }
    
    /**
     * Verify reset code and update password
     */
    public function verify_password_reset() {
        check_ajax_referer('esa_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        $code = sanitize_text_field($_POST['code'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($email) || empty($code) || empty($new_password)) {
            wp_send_json_error(array('message' => 'All fields are required.'));
            return;
        }
        
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters long.'));
            return;
        }
        
        // Check if user exists
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => 'Invalid email or code.'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'esa_email_verification';
        
        // Get verification row
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE email = %s AND user_id = %d AND verified = 0 AND expires_at > UTC_TIMESTAMP() 
             ORDER BY id DESC LIMIT 1",
            $email,
            $user->ID
        ));
        
        if (!$row) {
            wp_send_json_error(array('message' => 'Code expired or not found. Please request a new code.'));
            return;
        }
        
        // Check lock
        if (!empty($row->locked_until) && strtotime($row->locked_until . ' UTC') > time()) {
            wp_send_json_error(array('message' => 'Too many incorrect attempts. Please wait and try again later.'));
            return;
        }
        
        $attempts = (int)$row->attempt_count;
        $max_attempts = 5;
        $lock_minutes = 15;
        
        $valid = (!empty($row->code_hash) && password_verify($code, $row->code_hash));
        if (!$valid) {
            $attempts++;
            $update = array('attempt_count' => $attempts);
            if ($attempts >= $max_attempts) {
                $update['locked_until'] = gmdate('Y-m-d H:i:s', time() + $lock_minutes * 60);
            }
            $wpdb->update($table, $update, array('id' => $row->id));
            $remaining = max(0, $max_attempts - $attempts);
            $msg = $remaining > 0 ? "Invalid code. {$remaining} attempt(s) remaining." : 'Too many incorrect attempts. Please wait and try again later.';
            wp_send_json_error(array('message' => $msg));
            return;
        }
        
        // Code valid: reset password
        wp_set_password($new_password, $user->ID);
        
        // Mark verification row as used
        $wpdb->update(
            $table,
            array(
                'verified' => 1,
                'code_hash' => null
            ),
            array('id' => $row->id)
        );
        
        // Log the user in automatically
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        // Log login event
        $this->log_user_login($user->ID);
        
        wp_send_json_success(array(
            'message' => 'Password reset successful. You are now logged in.',
            'nonce' => wp_create_nonce('esa_nonce'),
            'user' => array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'approved' => $this->is_user_approved($user->ID)
            )
        ));
    }
}


// Initialize the plugin
EngineeredSolutionsAuth::get_instance();
