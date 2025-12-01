<?php
/**
 * Shortcodes for ESA Authentication System
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add shortcode for authentication system
add_shortcode('esa_auth', 'esa_auth_shortcode');
add_shortcode('esa_user_info', 'esa_user_info_shortcode');
add_shortcode('esa_login_button', 'esa_login_button_shortcode');
add_shortcode('esa_user_greeting', 'esa_user_greeting_shortcode');

function esa_auth_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_user_bar' => 'true',
        'show_login_modal' => 'true'
    ), $atts);
    
    ob_start();
    ?>
    <div id="esa-auth-container">
        <?php if ($atts['show_user_bar'] === 'true'): ?>
            <div id="esa-user-bar" class="esa-user-bar" style="display: none;">
                <div class="esa-user-info">
                    <span id="esa-user-greeting">Welcome, <span id="esa-user-name"></span>!</span>
                    <button id="esa-logout-btn" class="esa-btn esa-btn-small">Sign Out</button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($atts['show_login_modal'] === 'true'): ?>
            <div id="esa-auth-modal" class="esa-modal" style="display: none;">
                <div class="esa-modal-content">
                    <div class="esa-modal-header">
                        <div class="esa-logo-container">
                            <img src="https://rainwaterharvesting.services/wp-content/uploads/2023/10/Engineered_Solutions_logo_FINAL.png" alt="Engineered Solutions" class="esa-logo">
                        </div>
                        <span class="esa-close">&times;</span>
                    </div>
                    <div class="esa-modal-body">
                        <div class="esa-tabs">
                            <button class="esa-tab-btn active" data-tab="login">Login</button>
                            <button class="esa-tab-btn" data-tab="register">Register</button>
                        </div>
                        
                        <!-- Login Form -->
                        <div id="esa-login-form" class="esa-tab-content active">
                            <form id="esa-login">
                                <!-- Honeypot field (hidden from humans, visible to bots) -->
                                <input type="text" name="website" id="esa-website-login" style="position:absolute;left:-9999px;width:1px;height:1px;" tabindex="-1" autocomplete="off">
                                <div class="esa-form-group">
                                    <label for="login-email">Email:</label>
                                    <input type="email" id="login-email" name="email" required>
                                </div>
                                <div class="esa-form-group">
                                    <label for="login-password">Password:</label>
                                    <div class="esa-password-wrapper">
                                        <input type="password" id="login-password" name="password" required>
                                        <button type="button" class="esa-password-toggle" aria-label="Toggle password visibility">
                                            <svg class="esa-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            <svg class="esa-eye-slash-icon" style="display:none" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                                <line x1="1" y1="1" x2="23" y2="23"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="esa-btn esa-btn-primary">Login</button>
                            </form>
                            
                            <div class="esa-social-login">
                                <p>Or login with:</p>
                                <button class="esa-btn esa-btn-google" onclick="esaAuth.loginWithGoogle()">
                                    <i class="fab fa-google"></i> Google
                                </button>
                                <button class="esa-btn esa-btn-facebook" onclick="esaAuth.loginWithFacebook()">
                                    <i class="fab fa-facebook"></i> Facebook
                                </button>
                            </div>
                        </div>
                        
                        <!-- Register Form -->
                        <div id="esa-register-form" class="esa-tab-content">
                            <form id="esa-register">
                                <!-- Honeypot field (hidden from humans, visible to bots) -->
                                <input type="text" name="website" id="esa-website-register" style="position:absolute;left:-9999px;width:1px;height:1px;" tabindex="-1" autocomplete="off">
                                <div class="esa-form-row">
                                    <div class="esa-form-group">
                                        <label for="reg-first-name">First Name:</label>
                                        <input type="text" id="reg-first-name" name="first_name" required>
                                    </div>
                                    <div class="esa-form-group">
                                        <label for="reg-last-name">Last Name:</label>
                                        <input type="text" id="reg-last-name" name="last_name" required>
                                    </div>
                                </div>
                                <div class="esa-form-group">
                                    <label for="reg-email">Email:</label>
                                    <input type="email" id="reg-email" name="email" required>
                                </div>
                                <div class="esa-form-group">
                                    <label for="reg-password">Password:</label>
                                    <div class="esa-password-wrapper">
                                        <input type="password" id="reg-password" name="password" required>
                                        <button type="button" class="esa-password-toggle" aria-label="Toggle password visibility">
                                            <svg class="esa-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            <svg class="esa-eye-slash-icon" style="display:none" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                                <line x1="1" y1="1" x2="23" y2="23"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="esa-btn esa-btn-primary">Register</button>
                            </form>
                            
                            <div class="esa-social-login">
                                <p>Or register with:</p>
                                <button class="esa-btn esa-btn-google" onclick="esaAuth.registerWithGoogle()">
                                    <i class="fab fa-google"></i> Google
                                </button>
                                <button class="esa-btn esa-btn-facebook" onclick="esaAuth.registerWithFacebook()">
                                    <i class="fab fa-facebook"></i> Facebook
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function esa_user_info_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_name' => 'true',
        'show_email' => 'false',
        'show_status' => 'true'
    ), $atts);
    
    if (!is_user_logged_in()) {
        return '<p>Please <a href="#" onclick="esaAuth.showModal(); return false;">login</a> to view your information.</p>';
    }
    
    $user = wp_get_current_user();
    $is_approved = esa_is_user_approved($user->ID);
    
    ob_start();
    ?>
    <div class="esa-user-info-widget">
        <?php if ($atts['show_name'] === 'true'): ?>
            <p><strong>Name:</strong> <?php echo esc_html($user->display_name); ?></p>
        <?php endif; ?>
        
        <?php if ($atts['show_email'] === 'true'): ?>
            <p><strong>Email:</strong> <?php echo esc_html($user->user_email); ?></p>
        <?php endif; ?>
        
        <?php if ($atts['show_status'] === 'true'): ?>
            <p><strong>Status:</strong> 
                <span class="esa-status <?php echo $is_approved ? 'approved' : 'pending'; ?>">
                    <?php echo $is_approved ? 'Approved' : 'Pending Approval'; ?>
                </span>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function esa_login_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'Login',
        'class' => 'esa-btn esa-btn-primary',
        'redirect' => ''
    ), $atts);
    
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        return '<p>Welcome, ' . esc_html($user->display_name) . '! <a href="#" onclick="esaAuth.handleLogout(); return false;">Logout</a></p>';
    }
    
    $onclick = "esaAuth.showModal();";
    if ($atts['redirect']) {
        $onclick .= " localStorage.setItem('esa_redirect', '" . esc_js($atts['redirect']) . "');";
    }
    
    return '<button class="' . esc_attr($atts['class']) . '" onclick="' . $onclick . '">' . esc_html($atts['text']) . '</button>';
}

function esa_user_greeting_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_user_name' => 'true',
        'show_status' => 'true',
        'show_buttons' => 'true',
        'greeting_text' => 'Welcome',
        'position' => 'right'
    ), $atts);
    
    $show_user_name = $atts['show_user_name'] === 'true';
    $show_status = $atts['show_status'] === 'true';
    $show_buttons = $atts['show_buttons'] === 'true';
    $greeting_text = $atts['greeting_text'];
    $position = $atts['position'];
    
    $position_class = $position === 'left' ? 'justify-content: flex-start' : 'justify-content: flex-end';
    
    // Get current user info
    $current_user = wp_get_current_user();
    $is_logged_in = is_user_logged_in();
    $user_name = 'Guest';
    $is_approved = false;
    
    if ($is_logged_in && $current_user && isset($current_user->display_name)) {
        $user_name = $current_user->display_name ?: $current_user->user_login;
        $is_approved = get_user_meta($current_user->ID, 'esa_approved', true);
    }
    
    ob_start();
    ?>
    <div class="esa-user-greeting-widget" style="<?php echo $position_class; ?>" data-greeting="<?php echo esc_attr($greeting_text); ?>">
        <div class="esa-user-info">
            <div class="esa-user-details">
                <?php if ($show_user_name): ?>
                    <p class="esa-user-name" id="esa-user-name">
                        <?php echo esc_html($greeting_text); ?>, <?php echo esc_html($user_name); ?>!
                    </p>
                <?php endif; ?>
                <?php if ($show_status): ?>
                    <p class="esa-user-status">
                        Status: <span class="esa-status-badge esa-status-<?php echo $is_logged_in ? ($is_approved ? 'approved' : 'pending') : 'guest'; ?>">
                            <?php echo $is_logged_in ? ($is_approved ? 'Approved' : 'Pending Approval') : 'Guest User'; ?>
                        </span>
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($show_buttons): ?>
                <div class="esa-auth-buttons">
                    <?php if ($is_logged_in): ?>
                        <button class="esa-btn esa-btn-secondary esa-logout-icon" onclick="window.esaAuth.handleLogout()">
                            Sign Out
                        </button>
                    <?php else: ?>
                        <button class="esa-btn esa-btn-primary esa-login-icon" onclick="window.esaAuth.showModal()">
                            Sign In
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Helper function to check if user is approved
function esa_is_user_approved($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'esa_user_approval';
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT is_approved FROM $table WHERE user_id = %d",
        $user_id
    ));
    
    return $result == 1;
}
