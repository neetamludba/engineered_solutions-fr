<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>ESA Settings</h1>
    
    <?php
    // Handle form submission
    if (isset($_POST['submit'])) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['esa_settings_nonce'], 'esa_settings')) {
            wp_die('Security check failed');
        }
        
        // Update settings
        update_option('esa_admin_emails', sanitize_textarea_field($_POST['esa_admin_emails']));
        update_option('esa_enable_captcha', isset($_POST['esa_enable_captcha']) ? 1 : 0);
        update_option('esa_captcha_type', sanitize_text_field($_POST['esa_captcha_type']));
        update_option('esa_recaptcha_site_key', sanitize_text_field($_POST['esa_recaptcha_site_key']));
        update_option('esa_recaptcha_secret_key', sanitize_text_field($_POST['esa_recaptcha_secret_key']));
        update_option('esa_recaptcha_v3_min_score', floatval($_POST['esa_recaptcha_v3_min_score']));
        update_option('esa_hcaptcha_site_key', sanitize_text_field($_POST['esa_hcaptcha_site_key']));
        update_option('esa_hcaptcha_secret_key', sanitize_text_field($_POST['esa_hcaptcha_secret_key']));
        
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $admin_emails = get_option('esa_admin_emails', '');
    $enable_captcha = get_option('esa_enable_captcha', 1);
    $captcha_type = get_option('esa_captcha_type', 'recaptcha_v2');
    $recaptcha_site_key = get_option('esa_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('esa_recaptcha_secret_key', '');
    $recaptcha_v3_min_score = get_option('esa_recaptcha_v3_min_score', 0.5);
    $hcaptcha_site_key = get_option('esa_hcaptcha_site_key', '');
    $hcaptcha_secret_key = get_option('esa_hcaptcha_secret_key', '');
    ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('esa_settings', 'esa_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="esa_admin_emails">Admin Emails for Notifications</label>
                </th>
                <td>
                    <textarea 
                        id="esa_admin_emails" 
                        name="esa_admin_emails" 
                        rows="4" 
                        cols="50" 
                        class="regular-text"
                        placeholder="admin1@example.com, admin2@example.com, admin3@example.com"
                    ><?php echo esc_textarea($admin_emails); ?></textarea>
                    <p class="description">
                        Enter email addresses separated by commas. These emails will receive user registration and estimate request notifications.
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Enable CAPTCHA</th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            name="esa_enable_captcha" 
                            value="1" 
                            <?php checked($enable_captcha, 1); ?>
                        />
                        Enable CAPTCHA for registration and login forms
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="esa_captcha_type">CAPTCHA Type</label>
                </th>
                <td>
                    <select id="esa_captcha_type" name="esa_captcha_type">
                        <option value="recaptcha_v2" <?php selected($captcha_type, 'recaptcha_v2'); ?>>reCAPTCHA v2</option>
                        <option value="recaptcha_v3" <?php selected($captcha_type, 'recaptcha_v3'); ?>>reCAPTCHA v3</option>
                        <option value="hcaptcha" <?php selected($captcha_type, 'hcaptcha'); ?>>hCaptcha</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="esa_recaptcha_site_key">reCAPTCHA Site Key</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="esa_recaptcha_site_key" 
                        name="esa_recaptcha_site_key" 
                        value="<?php echo esc_attr($recaptcha_site_key); ?>" 
                        class="regular-text"
                    />
                    <p class="description">
                        Get your reCAPTCHA keys from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="esa_recaptcha_secret_key">reCAPTCHA Secret Key</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        id="esa_recaptcha_secret_key" 
                        name="esa_recaptcha_secret_key" 
                        value="<?php echo esc_attr($recaptcha_secret_key); ?>" 
                        class="regular-text"
                    />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="esa_recaptcha_v3_min_score">reCAPTCHA v3 Minimum Score</label>
                </th>
                <td>
                    <input 
                        type="number" 
                        id="esa_recaptcha_v3_min_score" 
                        name="esa_recaptcha_v3_min_score" 
                        value="<?php echo esc_attr($recaptcha_v3_min_score); ?>" 
                        min="0" 
                        max="1" 
                        step="0.1" 
                        class="small-text"
                    />
                    <p class="description">
                        Score between 0.0 (likely bot) and 1.0 (likely human). Recommended: 0.5
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="esa_hcaptcha_site_key">hCaptcha Site Key</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="esa_hcaptcha_site_key" 
                        name="esa_hcaptcha_site_key" 
                        value="<?php echo esc_attr($hcaptcha_site_key); ?>" 
                        class="regular-text"
                    />
                    <p class="description">
                        Get your hCaptcha keys from <a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="esa_hcaptcha_secret_key">hCaptcha Secret Key</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        id="esa_hcaptcha_secret_key" 
                        name="esa_hcaptcha_secret_key" 
                        value="<?php echo esc_attr($hcaptcha_secret_key); ?>" 
                        class="regular-text"
                    />
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save Settings'); ?>
    </form>
    
    <div class="esa-settings-info">
        <h3>Settings Information</h3>
        <ul>
            <li><strong>Admin Emails:</strong> Multiple emails can be separated by commas</li>
            <li><strong>CAPTCHA:</strong> Helps prevent spam registrations</li>
            <li><strong>reCAPTCHA v2:</strong> Shows "I'm not a robot" checkbox</li>
            <li><strong>reCAPTCHA v3:</strong> Invisible, scores user behavior</li>
            <li><strong>hCaptcha:</strong> Privacy-focused alternative to reCAPTCHA</li>
        </ul>
    </div>
    
    <div class="esa-debug-info">
        <h3>CAPTCHA Debug Information</h3>
        <table class="widefat">
            <tr>
                <td><strong>CAPTCHA Status:</strong></td>
                <td><?php echo $enable_captcha ? '<span style="color: green;">✓ Enabled</span>' : '<span style="color: red;">✗ Disabled</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>CAPTCHA Type:</strong></td>
                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $captcha_type))); ?></td>
            </tr>
            <tr>
                <td><strong>Site Key Configured:</strong></td>
                <td>
                    <?php 
                    $site_key = '';
                    if ($captcha_type === 'recaptcha_v2' || $captcha_type === 'recaptcha_v3') {
                        $site_key = $recaptcha_site_key;
                    } elseif ($captcha_type === 'hcaptcha') {
                        $site_key = $hcaptcha_site_key;
                    }
                    echo !empty($site_key) ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Secret Key Configured:</strong></td>
                <td>
                    <?php 
                    $secret_key = '';
                    if ($captcha_type === 'recaptcha_v2' || $captcha_type === 'recaptcha_v3') {
                        $secret_key = $recaptcha_secret_key;
                    } elseif ($captcha_type === 'hcaptcha') {
                        $secret_key = $hcaptcha_secret_key;
                    }
                    echo !empty($secret_key) ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>';
                    ?>
                </td>
            </tr>
            <?php if ($captcha_type === 'recaptcha_v3'): ?>
            <tr>
                <td><strong>Minimum Score:</strong></td>
                <td><?php echo esc_html($recaptcha_v3_min_score); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <h4>Test CAPTCHA Verification</h4>
        <p>Use this button to test if your CAPTCHA configuration is working correctly:</p>
        <button type="button" id="test-captcha" class="button button-secondary">Test CAPTCHA Configuration</button>
        <div id="captcha-test-result" style="margin-top: 10px;"></div>
        
        <h4>Debug Logs</h4>
        <p>Check your WordPress debug log for detailed CAPTCHA verification logs. Look for entries starting with "ESA reCAPTCHA" or "ESA Login" or "ESA Register".</p>
        <p><strong>Log Location:</strong> <code><?php echo defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? WP_CONTENT_DIR . '/debug.log' : 'Debug logging not enabled'; ?></code></p>
    </div>
</div>

<style>
.esa-settings-info, .esa-debug-info {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.esa-settings-info h3, .esa-debug-info h3 {
    margin-top: 0;
}

.esa-settings-info ul {
    margin: 10px 0;
}

.esa-settings-info li {
    margin: 5px 0;
}

.esa-debug-info table {
    margin: 10px 0;
}

.esa-debug-info td {
    padding: 8px;
    border-bottom: 1px solid #eee;
}

.esa-debug-info td:first-child {
    font-weight: bold;
    width: 200px;
}

#captcha-test-result {
    padding: 10px;
    border-radius: 4px;
    display: none;
}

#captcha-test-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

#captcha-test-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#test-captcha').on('click', function() {
        var button = $(this);
        var resultDiv = $('#captcha-test-result');
        
        button.prop('disabled', true).text('Testing...');
        resultDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'esa_test_captcha',
                nonce: '<?php echo wp_create_nonce('esa_test_captcha'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.removeClass('error').addClass('success').html('✓ ' + response.data.message).show();
                } else {
                    resultDiv.removeClass('success').addClass('error').html('✗ ' + response.data.message).show();
                }
            },
            error: function() {
                resultDiv.removeClass('success').addClass('error').html('✗ Test failed - check console for errors').show();
            },
            complete: function() {
                button.prop('disabled', false).text('Test CAPTCHA Configuration');
            }
        });
    });
});
</script>
