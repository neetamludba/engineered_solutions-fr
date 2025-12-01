<?php
/**
 * User Greeting Widget for Elementor
 * This widget shows user status, greeting, and login/logout buttons
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register the widget
add_action('elementor/widgets/widgets_registered', 'esa_register_user_greeting_widget');

function esa_register_user_greeting_widget() {
    if (class_exists('Elementor\Widget_Base')) {
        require_once plugin_dir_path(__FILE__) . 'user-greeting-widget-class.php';
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new ESA_User_Greeting_Widget());
    }
}

// Add custom CSS for the widget
add_action('wp_head', 'esa_user_greeting_widget_css');

function esa_user_greeting_widget_css() {
    ?>
    <style>
    .esa-user-greeting-widget {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 10px 20px;
        background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
        color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 124, 186, 0.3);
        margin: 10px 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .esa-user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .esa-user-details {
        text-align: right;
    }
    
    .esa-user-name {
        font-weight: 600;
        font-size: 16px;
        margin: 0;
    }
    
    .esa-user-status {
        font-size: 12px;
        margin: 2px 0 0 0;
        opacity: 0.9;
    }
    
    .esa-status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .esa-status-approved {
        background: #28a745;
        color: white;
    }
    
    .esa-status-pending {
        background: #ffc107;
        color: #212529;
    }
    
    .esa-status-guest {
        background: #6c757d;
        color: white;
    }
    
    .esa-auth-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .esa-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .esa-btn-primary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .esa-btn-primary:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }
    
    .esa-btn-secondary {
        background: transparent;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    
    .esa-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .esa-login-icon::before {
        content: "🔑";
        margin-right: 5px;
    }
    
    .esa-logout-icon::before {
        content: "🚪";
        margin-right: 5px;
    }
    
    .esa-user-icon::before {
        content: "👤";
        margin-right: 5px;
    }
    
    @media (max-width: 768px) {
        .esa-user-greeting-widget {
            flex-direction: column;
            text-align: center;
            padding: 15px;
        }
        
        .esa-user-info {
            flex-direction: column;
            gap: 10px;
        }
        
        .esa-user-details {
            text-align: center;
        }
        
        .esa-auth-buttons {
            justify-content: center;
        }
    }
    </style>
    <?php
}

// Add JavaScript for the widget
add_action('wp_footer', 'esa_user_greeting_widget_js');

function esa_user_greeting_widget_js() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for ESA Auth to be available
        const checkESA = setInterval(() => {
            if (typeof window.esaAuth !== 'undefined') {
                clearInterval(checkESA);
                initializeUserGreetingWidget();
            }
        }, 100);
    });
    
    function initializeUserGreetingWidget() {
        const widget = document.querySelector('.esa-user-greeting-widget');
        if (!widget) return;
        
        updateUserGreeting();
        
        // Listen for authentication changes
        document.addEventListener('userAuthChanged', updateUserGreeting);
    }
    
    function updateUserGreeting() {
        const widget = document.querySelector('.esa-user-greeting-widget');
        if (!widget) return;
        
        if (window.esaAuth && window.esaAuth.isLoggedIn) {
            showAuthenticatedUser(widget);
        } else {
            showGuestUser(widget);
        }
    }
    
    function showAuthenticatedUser(widget) {
        // Get user name from the existing element, but extract just the name part
        const existingNameElement = document.getElementById('esa-user-name');
        let userName = 'User';
        
        if (existingNameElement) {
            const text = existingNameElement.textContent;
            // Extract name from "Welcome, [Name]!" format
            const match = text.match(/Welcome,\s*(.+?)!/);
            if (match) {
                userName = match[1];
            }
        }
        
        const isApproved = window.esaAuth ? window.esaAuth.userApproved : false;
        
        widget.innerHTML = `
            <div class="esa-user-info">
                <div class="esa-user-details">
                    <p class="esa-user-name">Welcome, ${userName}!</p>
                    <p class="esa-user-status">
                        Status: <span class="esa-status-badge esa-status-${isApproved ? 'approved' : 'pending'}">
                            ${isApproved ? 'Approved' : 'Pending Approval'}
                        </span>
                    </p>
                </div>
                <div class="esa-auth-buttons">
                    <button class="esa-btn esa-btn-secondary esa-logout-icon" onclick="window.esaAuth.handleLogout()">
                        Sign Out
                    </button>
                </div>
            </div>
        `;
    }
    
    function showGuestUser(widget) {
        widget.innerHTML = `
            <div class="esa-user-info">
                <div class="esa-user-details">
                    <p class="esa-user-name">Welcome, Guest!</p>
                    <p class="esa-user-status">
                        Status: <span class="esa-status-badge esa-status-guest">Guest User</span>
                    </p>
                </div>
                <div class="esa-auth-buttons">
                    <button class="esa-btn esa-btn-primary esa-login-icon" onclick="window.esaAuth.showModal()">
                        Sign In
                    </button>
                </div>
            </div>
        `;
    }
    </script>
    <?php
}
