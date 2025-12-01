/**
 * Engineered Solutions Authentication System - Production Version
 * Handles login, registration, user tracking, and access control
 * Optimized for production with better error handling
 */

// Prevent multiple declarations and conflicts
(function () {
    'use strict';

    // Check if already loaded
    if (typeof window.ESAAuth !== 'undefined') {
        console.warn('ESAAuth already loaded, skipping re-initialization');
        return;
    }

    // Check if required dependencies are available
    if (typeof esa_ajax === 'undefined') {
        console.warn('ESA Auth: esa_ajax object not found. Plugin may not be fully loaded yet.');
        // Wait for the object to be available
        setTimeout(() => {
            if (typeof esa_ajax !== 'undefined') {
                console.log('ESA Auth: esa_ajax object loaded successfully');
            } else {
                console.error('ESA Auth: esa_ajax object still not found after timeout');
            }
        }, 1000);
    }

    window.ESAAuth = class ESAAuth {
        constructor() {
            try {
                // Fallback if esa_ajax is not available
                this.ajaxData = typeof esa_ajax !== 'undefined' ? esa_ajax : {
                    is_user_logged_in: false,
                    user_id: null,
                    user_approved: false,
                    ajax_url: '/wp-admin/admin-ajax.php',
                    nonce: ''
                };

                this.isLoggedIn = this.ajaxData.is_user_logged_in || false;
                this.userId = this.ajaxData.user_id || 0;
                this.userApproved = this.ajaxData.user_approved || false;
                // Initialize userName from PHP data if user is already logged in
                const initialName = this.ajaxData.user_name || '';
                const initialEmail = this.ajaxData.user_email || '';
                this.userName = initialName || initialEmail;
                this.userEmail = initialEmail;
                this.sessionId = this.generateSessionId();
                this.pageStartTime = Date.now();
                this.heartbeatInterval = null;
                this.captchaWidgetIds = {
                    login: null,
                    register: null
                };
                this.captchaValid = {
                    login: false,
                    register: false
                };
                this.registrationStage = 'details'; // 'details' -> 'code'
                this.registrationData = null;
                this.otpResendSeconds = 0;
                this.otpResendInterval = null;
                this.storageBroadcastKey = 'esaAuthBroadcast';
                this.passwordResetStage = 'email'; // 'email' -> 'code'
                this.passwordResetEmail = null;

                this.setupAuthBroadcast();
                this.init();
            } catch (error) {
                console.error('ESA Auth initialization error:', error);
            }
        }

        init() {
            try {
                this.createAuthModal();
                this.bindEvents();
                this.setupPasswordToggle();
                this.startUserTracking();
                this.initializePageAccess();
                this.setupNextendIntegration();
                this.verifyMagicLink();

                // Update the widget with initial state
                this.updateUserGreeting();

                // Check if user needs to be prompted for login
                if (!this.isLoggedIn) {
                    this.showGuestAccess();
                } else {
                    this.showAuthenticatedAccess();
                }

                // Emit initial auth state so all widgets share the same view
                this.emitAuthEvent('init');
            } catch (error) {
                console.error('ESA Auth init error:', error);
            }
        }

        setupPasswordToggle() {
            try {
                // Setup password toggle buttons
                document.addEventListener('click', (e) => {
                    if (e.target.closest('.esa-password-toggle')) {
                        const button = e.target.closest('.esa-password-toggle');
                        const wrapper = button.closest('.esa-password-wrapper');
                        const input = wrapper.querySelector('input');
                        const eyeIcon = button.querySelector('.esa-eye-icon');
                        const eyeSlashIcon = button.querySelector('.esa-eye-slash-icon');

                        if (input.type === 'password') {
                            input.type = 'text';
                            eyeIcon.style.display = 'none';
                            eyeSlashIcon.style.display = 'block';
                        } else {
                            input.type = 'password';
                            eyeIcon.style.display = 'block';
                            eyeSlashIcon.style.display = 'none';
                        }
                    }
                });
            } catch (error) {
                console.error('ESA Auth password toggle setup error:', error);
            }
        }

        setupNextendIntegration() {
            try {
                // Listen for Nextend Social Login Pro events
                document.addEventListener('NextendSocialLoginSuccess', (event) => {
                    this.handleNextendSuccess(event.detail);
                });

                document.addEventListener('NextendSocialLoginError', (event) => {
                    this.handleNextendError(event.detail);
                });

                // Check if user just logged in via Nextend
                if (this.isNextendLogin()) {
                    this.handleNextendRedirect();
                }
            } catch (error) {
                console.error('ESA Auth Nextend integration error:', error);
            }
        }

        isNextendLogin() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.has('nsl_login') || urlParams.has('nsl_register');
            } catch (error) {
                return false;
            }
        }

        handleNextendRedirect() {
            try {
                // User just logged in via Nextend Social Login Pro
                setTimeout(() => {
                    this.isLoggedIn = true;
                    this.userId = esa_ajax.user_id || 0;
                    this.userApproved = esa_ajax.user_approved || false;
                    this.userName = esa_ajax.user_name || this.userName;
                    this.userEmail = esa_ajax.user_email || this.userEmail;

                    this.showAuthenticatedAccess();
                    this.showMessage('Login successful!', 'success');
                    this.updateUserGreeting();
                    this.broadcastAuthState('login');

                    // Clean up URL
                    const url = new URL(window.location);
                    url.searchParams.delete('nsl_login');
                    url.searchParams.delete('nsl_register');
                    window.history.replaceState({}, document.title, url);
                }, 1000);
            } catch (error) {
                console.error('ESA Auth Nextend redirect error:', error);
            }
        }

        handleNextendSuccess(data) {
            try {
                this.showMessage('Social login successful!', 'success');
                // Refresh page to update authentication state
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } catch (error) {
                console.error('ESA Auth Nextend success error:', error);
            }
        }

        handleNextendError(data) {
            try {
                this.showMessage('Social login failed. Please try again.', 'error');
            } catch (error) {
                console.error('ESA Auth Nextend error handler error:', error);
            }
        }

        generateSessionId() {
            return 'esa_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        handleCaptchaSuccess(response, formType) {
            try {
                console.log('ESA Auth: CAPTCHA completed for', formType, 'token length:', response ? response.length : 0);
                this.captchaValid[formType] = true;
                this.updateSubmitButtons(formType, false); // Enable
                this.clearCaptchaError(formType);
            } catch (error) {
                console.error('ESA Auth CAPTCHA success handler error:', error);
            }
        }

        handleCaptchaExpired(formType) {
            try {
                console.log('ESA Auth: CAPTCHA expired for', formType);
                this.captchaValid[formType] = false;
                this.updateSubmitButtons(formType, true); // Disable
                this.showCaptchaError(formType, 'CAPTCHA expired. Please verify again.');

                // Auto-reset the widget
                if (this.captchaWidgetIds[formType] !== null) {
                    grecaptcha.reset(this.captchaWidgetIds[formType]);
                }
            } catch (error) {
                console.error('ESA Auth CAPTCHA expired handler error:', error);
            }
        }

        updateSubmitButtons(formType, disabled) {
            try {
                const formId = formType === 'login' ? 'esa-login' : 'esa-register';
                const form = document.getElementById(formId);
                if (form) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = disabled;
                        console.log('ESA Auth: Submit button', disabled ? 'disabled' : 'enabled', 'for', formType);
                    }
                }
            } catch (error) {
                console.error('ESA Auth update submit buttons error:', error);
            }
        }

        showCaptchaError(formType, message) {
            try {
                const containerId = `esa-captcha-${formType}`;
                const container = document.getElementById(containerId);
                if (container) {
                    let errorDiv = container.querySelector('.captcha-error');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'captcha-error';
                        errorDiv.style.cssText = 'color: #dc2626; font-size: 12px; margin-top: 5px;';
                        container.appendChild(errorDiv);
                    }
                    errorDiv.textContent = message;
                    console.log('ESA Auth: CAPTCHA error shown for', formType);
                }
            } catch (error) {
                console.error('ESA Auth show CAPTCHA error error:', error);
            }
        }

        clearCaptchaError(formType) {
            try {
                const containerId = `esa-captcha-${formType}`;
                const container = document.getElementById(containerId);
                if (container) {
                    const errorDiv = container.querySelector('.captcha-error');
                    if (errorDiv) {
                        errorDiv.remove();
                        console.log('ESA Auth: CAPTCHA error cleared for', formType);
                    }
                }
            } catch (error) {
                console.error('ESA Auth clear CAPTCHA error error:', error);
            }
        }

        renderCaptcha() {
            try {
                if (!this.ajaxData.captcha_enabled) return;

                const captchaType = this.ajaxData.captcha_type;
                const siteKey = this.ajaxData.captcha_site_key;

                if (!siteKey) {
                    console.warn('ESA Auth: CAPTCHA site key not configured');
                    return;
                }

                if (captchaType === 'recaptcha_v2') {
                    if (typeof grecaptcha !== 'undefined' && grecaptcha.render) {
                        const loginContainer = document.getElementById('esa-captcha-login');
                        const registerContainer = document.getElementById('esa-captcha-register');

                        // LOGIN WIDGET
                        if (loginContainer) {
                            if (this.captchaWidgetIds.login !== null) {
                                console.log('ESA Auth: Resetting existing login CAPTCHA widget');
                                grecaptcha.reset(this.captchaWidgetIds.login);
                            } else {
                                console.log('ESA Auth: Rendering new login CAPTCHA widget');
                                this.captchaWidgetIds.login = grecaptcha.render(loginContainer, {
                                    'sitekey': siteKey,
                                    'callback': (response) => this.handleCaptchaSuccess(response, 'login'),
                                    'expired-callback': () => this.handleCaptchaExpired('login')
                                });
                            }
                        }

                        // REGISTER WIDGET (same logic as login)
                        if (registerContainer) {
                            if (this.captchaWidgetIds.register !== null) {
                                console.log('ESA Auth: Resetting existing register CAPTCHA widget');
                                grecaptcha.reset(this.captchaWidgetIds.register);
                            } else {
                                console.log('ESA Auth: Rendering new register CAPTCHA widget');
                                this.captchaWidgetIds.register = grecaptcha.render(registerContainer, {
                                    'sitekey': siteKey,
                                    'callback': (response) => this.handleCaptchaSuccess(response, 'register'),
                                    'expired-callback': () => this.handleCaptchaExpired('register')
                                });
                            }
                        }

                        // Initially disable submit buttons until CAPTCHA is completed
                        this.updateSubmitButtons('login', true);
                        this.updateSubmitButtons('register', true);
                    }
                    else {
                        console.warn('ESA Auth: reCAPTCHA script not loaded');
                    }
                } else if (captchaType === 'recaptcha_v3') {
                    // reCAPTCHA v3 is invisible, just execute
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(() => {
                            grecaptcha.execute(siteKey, { action: 'login' }).then(token => {
                                const loginInput = document.getElementById('g-recaptcha-response-login');
                                if (loginInput) loginInput.value = token;
                            });
                            grecaptcha.execute(siteKey, { action: 'register' }).then(token => {
                                const registerInput = document.getElementById('g-recaptcha-response-register');
                                if (registerInput) registerInput.value = token;
                            });
                        });
                    }
                } else if (captchaType === 'hcaptcha') {
                    // Render hCaptcha
                    if (typeof hcaptcha !== 'undefined') {
                        const loginContainer = document.getElementById('esa-captcha-login');
                        const registerContainer = document.getElementById('esa-captcha-register');

                        if (loginContainer) {
                            console.log('ESA Auth: Rendering hCaptcha login widget');
                            loginContainer.innerHTML = '';
                            hcaptcha.render(loginContainer, { 'sitekey': siteKey });
                        }

                        if (registerContainer) {
                            console.log('ESA Auth: Rendering hCaptcha register widget');
                            registerContainer.innerHTML = '';
                            hcaptcha.render(registerContainer, { 'sitekey': siteKey });
                        }
                    } else {
                        console.warn('ESA Auth: hCaptcha script not loaded');
                    }
                }
            } catch (error) {
                console.error('ESA Auth CAPTCHA rendering error:', error);
            }
        }

        createAuthModal() {
            try {
                // Check if modal already exists
                if (document.getElementById('esa-auth-modal')) {
                    return;
                }

                const modalHTML = `
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
                                    <button class="esa-tab-btn" data-tab="reset" style="display:none;">Reset Password</button>
                                </div>
                                
                                <!-- Login Form -->
                                <div id="esa-login-form" class="esa-tab-content active">
                                    <form id="esa-login">
                                        <div class="esa-form-group">
                                            <label for="login-email">Email:</label>
                                            <input type="email" id="login-email" name="email" required>
                                        </div>
                                        <div class="esa-form-group">
                                            <label for="login-password">Password:</label>
                                            <input type="password" id="login-password" name="password" required>
                                        </div>
                                        <div class="esa-form-group">
                                            <a href="#" id="esa-forgot-password-link" style="font-size: 14px; color: #2563eb; text-decoration: none;">Forgot Password?</a>
                                        </div>
                                        <div class="esa-form-group">
                                            <div id="esa-captcha-login"></div>
                                            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-login">
                                        </div>
                                    <button type="submit" class="esa-btn esa-btn-primary">Login</button>
                                    
                                    <div class="esa-separator" style="margin: 1.5rem 0; text-align: center; border-bottom: 1px solid #e2e8f0; line-height: 0.1em;">
                                        <span style="background:#fff; padding:0 10px; color:#64748b; font-size: 14px;">OR</span>
                                    </div>
                                    
                                    <button type="button" id="esa-magic-link-btn" class="esa-btn esa-btn-secondary" style="width: 100%; justify-content: center;">
                                        <span style="margin-right: 8px;">✨</span> Login with Magic Link
                                    </button>
                                </form>
                                
                                <!-- Google login disabled -->
                            </div>

                            <!-- Magic Link Form -->
                            <div id="esa-magic-link-form" class="esa-tab-content" style="display: none;">
                                <form id="esa-magic-link-request">
                                    <div class="esa-form-group">
                                        <label for="magic-link-email">Email Address:</label>
                                        <input type="email" id="magic-link-email" name="email" required placeholder="Enter your email">
                                        <small style="color: #64748b; display: block; margin-top: 0.5rem;">We'll send you a secure link to log in instantly.</small>
                                    </div>
                                    <button type="submit" class="esa-btn esa-btn-primary" style="width: 100%;">Send Magic Link</button>
                                    <button type="button" id="esa-magic-link-back" class="esa-btn esa-btn-secondary" style="width: 100%; margin-top: 10rem;">Back to Login</button>
                                </form>
                                <div id="esa-magic-link-success" style="display: none; text-align: center; padding: 1rem;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">📧</div>
                                    <h3 style="margin-bottom: 0.5rem;">Check your email</h3>
                                    <p style="color: #64748b; margin-bottom: 1.5rem;">We've sent a login link to <strong id="esa-magic-link-sent-email"></strong></p>
                                    <button type="button" class="esa-btn esa-btn-secondary" onclick="location.reload()">Close</button>
                                </div>
                            </div>
                                
                                <!-- Reset Password Form -->
                                <div id="esa-reset-form" class="esa-tab-content">
                                    <form id="esa-reset-password">
                                        <div id="esa-reset-email-stage">
                                            <div class="esa-form-group">
                                                <label for="reset-email">Email Address:</label>
                                                <input type="email" id="reset-email" name="reset_email" required>
                                                <small style="color: #666;">Enter your email to receive a 6-digit reset code</small>
                                            </div>
                                            <button type="submit" class="esa-btn esa-btn-primary">Send Reset Code</button>
                                        </div>
                                        <div id="esa-reset-code-stage" style="display:none;">
                                            <div class="esa-form-group">
                                                <label for="reset-code">6-Digit Code:</label>
                                                <input type="text" id="reset-code" name="reset_code" inputmode="numeric" pattern="\\d{6}" maxlength="6" placeholder="Enter code" required>
                                            </div>
                                            <div class="esa-form-group">
                                                <label for="reset-new-password">New Password:</label>
                                                <input type="password" id="reset-new-password" name="new_password" minlength="8" required>
                                                <small style="color: #666;">Minimum 8 characters</small>
                                            </div>
                                            <div class="esa-form-group">
                                                <label for="reset-confirm-password">Confirm Password:</label>
                                                <input type="password" id="reset-confirm-password" name="confirm_new_password" minlength="8" required>
                                            </div>
                                            <div id="esa-reset-resend" style="margin-bottom:10px;font-size:12px;color:#4b5563;">
                                                <button type="button" id="esa-reset-resend-btn" class="esa-btn esa-btn-secondary" disabled>Resend code (60)</button>
                                            </div>
                                            <button type="submit" class="esa-btn esa-btn-primary">Reset Password</button>
                                            <button type="button" id="esa-reset-back-btn" class="esa-btn esa-btn-secondary" style="margin-left:10px;">Back</button>
                                        </div>
                                    </form>
                                    <div style="margin-top: 15px; text-align: center;">
                                        <a href="#" id="esa-back-to-login-link" style="font-size: 14px; color: #2563eb; text-decoration: none;">Back to Login</a>
                                    </div>
                                </div>
                                
                                <!-- Register Form -->
                                <div id="esa-register-form" class="esa-tab-content">
                                    <form id="esa-register">
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
                                            <label for="reg-company">Company Name:</label>
                                            <input type="text" id="reg-company" name="company_name" required>
                                        </div>
                                        <div class="esa-form-group">
                                            <label for="reg-email">Email:</label>
                                            <input type="email" id="reg-email" name="email" required>
                                        </div>
                                        <div class="esa-form-group">
                                            <label for="reg-password">Password:</label>
                                            <input type="password" id="reg-password" name="password" required>
                                        </div>
                                        <div class="esa-form-group">
                                            <label for="reg-confirm-password">Confirm Password:</label>
                                            <input type="password" id="reg-confirm-password" name="confirm_password" required>
                                        </div>
                                        <div class="esa-form-group" id="esa-otp-group" style="display:none;">
                                            <label for="reg-otp-code">Verification Code:</label>
                                            <input type="text" id="reg-otp-code" name="code" inputmode="numeric" pattern="\\d{6}" maxlength="6" placeholder="6-digit code">
                                            <div id="esa-otp-resend" style="margin-top:6px;font-size:12px;color:#4b5563;">
                                                <button type="button" id="esa-otp-resend-btn" class="esa-btn esa-btn-secondary" disabled>Resend code (60)</button>
                                            </div>
                                        </div>
                                        <div class="esa-form-group">
                                            <div id="esa-captcha-register"></div>
                                            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-register">
                                        </div>
                                        <button type="submit" class="esa-btn esa-btn-primary">Register</button>
                                    </form>
                                    
                                    <!-- Google registration disabled -->
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHTML);
            } catch (error) {
                console.error('ESA Auth modal creation error:', error);
            }
        }

        bindEvents() {
            try {
                // Helper for touch events to remove 300ms delay
                const addTouchHandler = (selector, handler) => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        el.addEventListener('click', (e) => {
                            e.preventDefault();
                            handler(e);
                        });
                        el.addEventListener('touchend', (e) => {
                            e.preventDefault();
                            handler(e);
                        }, { passive: false });
                    });
                };

                // Tab switching
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('esa-tab-btn')) {
                        e.preventDefault();
                        this.switchTab(e.target.dataset.tab);
                    }
                });

                // Add touch support for tabs
                document.addEventListener('touchend', (e) => {
                    if (e.target.classList.contains('esa-tab-btn')) {
                        e.preventDefault();
                        this.switchTab(e.target.dataset.tab);
                    }
                }, { passive: false });

                // Modal close
                const closeHandler = (e) => {
                    if (e.target.classList.contains('esa-close') || e.target.classList.contains('esa-modal')) {
                        this.hideModal();
                    }
                };
                document.addEventListener('click', closeHandler);
                document.addEventListener('touchend', closeHandler, { passive: false });

                // Form submissions
                document.addEventListener('submit', (e) => {
                    if (e.target.id === 'esa-login') {
                        e.preventDefault();
                        this.handleLogin();
                    } else if (e.target.id === 'esa-register') {
                        e.preventDefault();
                        this.handleRegister();
                    } else if (e.target.id === 'esa-reset-password') {
                        e.preventDefault();
                        this.handlePasswordReset();
                    } else if (e.target.id === 'esa-magic-link-request') {
                        e.preventDefault();
                        this.handleMagicLinkRequest();
                    }
                });

                // Magic Link Navigation
                document.addEventListener('click', (e) => {
                    if (e.target.id === 'esa-magic-link-btn') {
                        e.preventDefault();
                        this.switchTab('magic-link-form');
                    } else if (e.target.id === 'esa-magic-link-back') {
                        e.preventDefault();
                        this.switchTab('login');
                    }
                });

                // Forgot password link
                document.addEventListener('click', (e) => {
                    if (e.target.id === 'esa-forgot-password-link') {
                        e.preventDefault();
                        this.showPasswordResetTab();
                    } else if (e.target.id === 'esa-back-to-login-link') {
                        e.preventDefault();
                        this.resetPasswordResetStage();
                        this.switchTab('login');
                    } else if (e.target.id === 'esa-reset-back-btn') {
                        e.preventDefault();
                        this.resetPasswordResetStage();
                    }
                });

                // Button clicks that require authentication
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('esa-requires-auth') && !this.isLoggedIn) {
                        e.preventDefault();
                        this.showModal();
                    }
                });
            } catch (error) {
                console.error('ESA Auth event binding error:', error);
            }
        }

        async handleMagicLinkRequest() {
            const emailInput = document.getElementById('magic-link-email');
            const email = emailInput.value;
            const form = document.getElementById('esa-magic-link-request');
            const submitBtn = form.querySelector('button[type="submit"]');

            if (!email) {
                this.showMessage('Please enter your email address', 'error');
                return;
            }

            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;

            try {
                const response = await fetch(this.ajaxData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'esa_request_magic_link',
                        nonce: this.ajaxData.nonce,
                        email: email
                    })
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('esa-magic-link-request').style.display = 'none';
                    document.getElementById('esa-magic-link-success').style.display = 'block';
                    document.getElementById('esa-magic-link-sent-email').textContent = email;
                } else {
                    this.showMessage(result.data.message || 'Failed to send magic link', 'error');
                }
            } catch (error) {
                console.error('Magic link error:', error);
                this.showMessage('An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        }

        async verifyMagicLink() {
            const urlParams = new URLSearchParams(window.location.search);
            const magicToken = urlParams.get('esa_magic_token');
            const magicEmail = urlParams.get('email');

            if (magicToken && magicEmail) {
                // Show loading state
                setTimeout(() => this.showMessage('Verifying magic link...', 'info'), 500);

                try {
                    const response = await fetch(this.ajaxData.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'esa_verify_magic_link',
                            nonce: this.ajaxData.nonce,
                            token: magicToken,
                            email: magicEmail
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showMessage('Login successful! Redirecting...', 'success');

                        // Update state
                        this.isLoggedIn = true;
                        this.userId = result.data.user_id;
                        this.userName = result.data.user_name;
                        this.userEmail = result.data.user_email;
                        this.userApproved = result.data.user_approved;

                        // Broadcast login
                        this.broadcastAuthState('login');

                        // Clean URL
                        const newUrl = window.location.pathname + window.location.hash;
                        window.history.replaceState({}, document.title, newUrl);

                        // Reload to ensure all PHP state is synced
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        this.showMessage(result.data.message || 'Invalid or expired magic link', 'error');
                        // Open modal to let them try again
                        setTimeout(() => {
                            this.showModal();
                            this.switchTab('magic-link-form');
                        }, 2000);
                    }
                } catch (error) {
                    console.error('Magic link verification error:', error);
                    this.showMessage('Verification failed. Please try again.', 'error');
                }
            }
        }

        get isIPad() {
            return navigator.userAgent.match(/iPad/i) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 1 && /Macintosh/i.test(navigator.userAgent));
        }

        switchTab(tabName) {
            try {
                // Update tab buttons
                document.querySelectorAll('.esa-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.tab === 'reset') {
                        btn.style.display = 'none';
                    }
                });
                const targetBtn = document.querySelector(`[data-tab="${tabName}"]`);
                if (targetBtn) {
                    targetBtn.classList.add('active');
                    if (tabName === 'reset') {
                        targetBtn.style.display = 'inline-block';
                    }
                }

                // Update tab content
                document.querySelectorAll('.esa-tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                document.getElementById(`esa-${tabName}-form`).style.display = 'block';
            } catch (error) {
                console.error('ESA Auth tab switch error:', error);
            }
        }

        showModal() {
            try {
                const modal = document.getElementById('esa-auth-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    // Render CAPTCHA when modal opens
                    this.renderCaptcha();
                }
            } catch (error) {
                console.error('ESA Auth show modal error:', error);
            }
        }

        hideModal() {
            try {
                const modal = document.getElementById('esa-auth-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            } catch (error) {
                console.error('ESA Auth hide modal error:', error);
            }
        }

        async handleLogin() {
            let originalText = '';
            try {
                const formData = new FormData(document.getElementById('esa-login'));
                const data = Object.fromEntries(formData);

                console.log('ESA Login: Starting login process');

                // Set loading state
                const form = document.getElementById('esa-login');
                const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
                if (submitBtn) {
                    originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Signing in...';
                    submitBtn.disabled = true;
                }

                const postData = {
                    action: 'esa_login',
                    nonce: this.ajaxData.nonce,
                    email: data.email,
                    password: data.password
                };

                // Get CAPTCHA response - check form data first, then fallback to widget response
                let loginCaptchaResponse = data['g-recaptcha-response'];
                if (!loginCaptchaResponse && this.captchaWidgetIds.login !== null && typeof grecaptcha !== 'undefined') {
                    try {
                        loginCaptchaResponse = grecaptcha.getResponse(this.captchaWidgetIds.login);
                        console.log('ESA Login: Fallback widget response length:', loginCaptchaResponse ? loginCaptchaResponse.length : 0);
                    } catch (e) {
                        console.warn('ESA Login: Unable to read widget response', e);
                    }
                }

                // Validate CAPTCHA token before submitting
                // Only enforce CAPTCHA if it's truly enabled (convert to boolean)
                const captchaRequired = this.ajaxData.captcha_enabled === 1 || this.ajaxData.captcha_enabled === true || this.ajaxData.captcha_enabled === '1';
                console.log('ESA Login: CAPTCHA required:', captchaRequired, 'CAPTCHA enabled value:', this.ajaxData.captcha_enabled);
                if (captchaRequired && !loginCaptchaResponse) {
                    console.log('ESA Login: CAPTCHA not completed by user');
                    this.showMessage('Please complete the CAPTCHA verification', 'error');
                    return;
                }

                // Include CAPTCHA response if present
                if (loginCaptchaResponse) {
                    console.log('ESA Login: CAPTCHA token found, length:', loginCaptchaResponse.length);
                    console.log('ESA Login: CAPTCHA token preview:', loginCaptchaResponse.substring(0, 20) + '...');
                    postData['g-recaptcha-response'] = loginCaptchaResponse;
                } else {
                    console.log('ESA Login: No CAPTCHA token found');
                }

                const response = await fetch(this.ajaxData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(postData)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                console.log('ESA Login: Server response received:', result);
                console.log('ESA Login: Response success:', result.success);
                console.log('ESA Login: Response data:', result.data);

                if (result.success) {
                    console.log('ESA Login: Login successful, updating UI...');
                    try {
                        this.userName = result.data.user.name || result.data.user.email;
                        this.userEmail = result.data.user.email || this.userEmail;
                        console.log('ESA Login: User details set:', { name: this.userName, email: this.userEmail });

                        this.showMessage(result.data.message, 'success');
                        console.log('ESA Login: Success message shown');

                        // Update authentication state
                        this.isLoggedIn = true;
                        this.userId = result.data.user.id;
                        this.userApproved = result.data.user.approved;
                        console.log('ESA Login: Auth state updated:', { isLoggedIn: this.isLoggedIn, userId: this.userId, approved: this.userApproved });

                        // Update user greeting widget
                        this.updateUserGreeting();
                        console.log('ESA Login: User greeting updated');

                        // Hide modal
                        this.hideModal();
                        console.log('ESA Login: Modal hidden');

                        // Broadcast new auth state for all widgets/pages
                        this.broadcastAuthState('login');
                        console.log('ESA Login: Auth state broadcasted');

                        // Trigger form restoration event
                        document.dispatchEvent(new CustomEvent('userAuthChanged', {
                            detail: { action: 'login', success: true }
                        }));
                        console.log('ESA Login: Form restoration event triggered');

                        // Trigger event for graphs/charts to refresh
                        document.dispatchEvent(new CustomEvent('esaAuthSuccess', {
                            detail: { user: result.data.user, action: 'login' }
                        }));
                        console.log('ESA Login: Auth success event triggered');

                        console.log('ESA Login: All UI updates completed successfully');
                    } catch (uiError) {
                        console.error('ESA Login: Error during UI update:', uiError);
                        // Still show success message even if UI update fails
                        this.showMessage('Login successful, but page may need refresh', 'success');
                    }
                } else {
                    console.log('ESA Login: Login failed:', result.data.message);
                    this.showMessage(result.data.message, 'error');

                    // Reset CAPTCHA on login failure
                    if (this.ajaxData.captcha_enabled && this.captchaWidgetIds.login !== null) {
                        console.log('ESA Login: Resetting CAPTCHA after failed attempt');
                        grecaptcha.reset(this.captchaWidgetIds.login);
                        this.captchaValid.login = false;
                        this.updateSubmitButtons('login', true);
                    }
                }
            } catch (error) {
                console.error('ESA Auth login error:', error);
                this.showMessage('Login failed. Please try again.', 'error');
            }
            finally {
                // Restore button
                const form = document.getElementById('esa-login');
                const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
                if (submitBtn) {
                    submitBtn.textContent = originalText || 'Login';
                    // Keep disabled if CAPTCHA must be completed
                    const shouldDisable = this.ajaxData.captcha_enabled && !this.captchaValid.login;
                    submitBtn.disabled = shouldDisable;
                }
            }
        }

        async handleRegister() {
            let originalText = '';
            try {
                const formData = new FormData(document.getElementById('esa-register'));
                const data = Object.fromEntries(formData);

                console.log('ESA Register: Starting registration process, stage =', this.registrationStage);

                // Set loading state
                const form = document.getElementById('esa-register');
                const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
                if (submitBtn) {
                    originalText = submitBtn.textContent;
                    submitBtn.textContent = this.registrationStage === 'details' ? 'Sending code...' : 'Verifying...';
                    submitBtn.disabled = true;
                }

                if (this.registrationStage === 'details') {
                    // Validate password confirmation
                    if (data.password !== data.confirm_password) {
                        console.log('ESA Register: Password validation failed');
                        this.showMessage('Passwords do not match', 'error');
                        return;
                    }
                    // Validate required fields
                    if (!data.first_name || !data.last_name || !data.company_name || !data.email || !data.password) {
                        console.log('ESA Register: Required fields validation failed');
                        this.showMessage('Please fill in all required fields', 'error');
                        return;
                    }
                    const postData = {
                        action: 'esa_request_otp',
                        nonce: this.ajaxData.nonce,
                        first_name: data.first_name,
                        last_name: data.last_name,
                        company_name: data.company_name,
                        email: data.email,
                        password: data.password
                    };
                    // CAPTCHA token
                    let registerCaptchaResponse = data['g-recaptcha-response'];
                    if (!registerCaptchaResponse && this.captchaWidgetIds.register !== null && typeof grecaptcha !== 'undefined') {
                        try {
                            registerCaptchaResponse = grecaptcha.getResponse(this.captchaWidgetIds.register);
                        } catch (e) { }
                    }
                    // Only enforce CAPTCHA if it's truly enabled (convert to boolean)
                    const captchaRequired = this.ajaxData.captcha_enabled === 1 || this.ajaxData.captcha_enabled === true || this.ajaxData.captcha_enabled === '1';
                    console.log('ESA Register: CAPTCHA required:', captchaRequired, 'CAPTCHA enabled value:', this.ajaxData.captcha_enabled);
                    if (captchaRequired && !registerCaptchaResponse) {
                        console.log('ESA Register: CAPTCHA not completed');
                        this.showMessage('Please complete the CAPTCHA verification', 'error');
                        return;
                    }
                    if (registerCaptchaResponse) {
                        postData['g-recaptcha-response'] = registerCaptchaResponse;
                    }
                    const response = await fetch(this.ajaxData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(postData)
                    });
                    const result = await response.json();
                    if (result && result.success) {
                        this.showMessage(result.data.message, 'success');
                        this.registrationData = {
                            first_name: data.first_name,
                            last_name: data.last_name,
                            company_name: data.company_name,
                            email: data.email,
                            password: data.password
                        };
                        this.moveToOtpStage(result.data.resend_seconds || 60);
                    } else {
                        const msg = (result && result.data && result.data.message) ? result.data.message : 'Failed to send code.';
                        this.showMessage(msg, 'error');
                        // Reset CAPTCHA on failure
                        if (this.ajaxData.captcha_enabled && this.captchaWidgetIds.register !== null) {
                            try { grecaptcha.reset(this.captchaWidgetIds.register); } catch (e) { }
                            this.captchaValid.register = false;
                            this.updateSubmitButtons('register', true);
                        }
                    }
                } else {
                    // Code stage
                    const code = (data.code || '').trim();
                    if (!/^[0-9]{6}$/.test(code)) {
                        this.showMessage('Please enter the 6-digit code.', 'error');
                        return;
                    }
                    const payload = {
                        action: 'esa_verify_otp',
                        nonce: this.ajaxData.nonce,
                        email: this.registrationData.email,
                        first_name: this.registrationData.first_name,
                        last_name: this.registrationData.last_name,
                        company_name: this.registrationData.company_name,
                        password: this.registrationData.password,
                        code
                    };
                    const response = await fetch(this.ajaxData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(payload)
                    });
                    const result = await response.json();
                    if (result && result.success) {
                        this.showMessage(result.data.message, 'success');
                        // Switch to login tab
                        this.switchTab('login');
                        // Clear OTP state
                        this.resetOtpStage();
                        // Trigger event
                        document.dispatchEvent(new CustomEvent('userAuthChanged', {
                            detail: { action: 'register', success: true }
                        }));
                    } else {
                        const msg = (result && result.data && result.data.message) ? result.data.message : 'Verification failed.';
                        this.showMessage(msg, 'error');
                    }
                }
            } catch (error) {
                console.error('ESA Auth registration error:', error);
                this.showMessage('Registration failed. Please try again.', 'error');
            }
            finally {
                // Restore button
                const form = document.getElementById('esa-register');
                const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
                if (submitBtn) {
                    submitBtn.textContent = originalText || (this.registrationStage === 'details' ? 'Register' : 'Verify Code');
                    // Keep disabled if CAPTCHA must be completed (detail stage only)
                    const shouldDisable = this.ajaxData.captcha_enabled && !this.captchaValid.register && this.registrationStage === 'details';
                    submitBtn.disabled = shouldDisable;
                }
            }
        }

        moveToOtpStage(resendSeconds) {
            this.registrationStage = 'code';
            const emailInput = document.getElementById('reg-email');
            const otpGroup = document.getElementById('esa-otp-group');
            const submitBtn = document.querySelector('#esa-register button[type="submit"]');
            if (emailInput) {
                emailInput.setAttribute('readonly', 'readonly');
                emailInput.style.background = '#f3f4f6';
            }
            if (otpGroup) {
                otpGroup.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.textContent = 'Verify Code';
            }
            if (this.ajaxData.captcha_enabled) {
                if (this.ajaxData.captcha_type === 'hcaptcha' && typeof hcaptcha !== 'undefined') {
                    try { hcaptcha.reset(); } catch (e) { }
                } else if (typeof grecaptcha !== 'undefined') {
                    if (this.captchaWidgetIds.register !== null) {
                        try { grecaptcha.reset(this.captchaWidgetIds.register); } catch (e) { }
                    }
                }
                this.captchaValid.register = false;
            }
            // Setup resend
            const resendBtn = document.getElementById('esa-otp-resend-btn');
            if (resendBtn) {
                this.otpResendSeconds = resendSeconds || 60;
                resendBtn.disabled = true;
                resendBtn.textContent = `Resend code (${this.otpResendSeconds})`;
                if (this.otpResendInterval) clearInterval(this.otpResendInterval);
                this.otpResendInterval = setInterval(() => {
                    this.otpResendSeconds -= 1;
                    if (this.otpResendSeconds <= 0) {
                        clearInterval(this.otpResendInterval);
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend code';
                    } else {
                        resendBtn.textContent = `Resend code (${this.otpResendSeconds})`;
                    }
                }, 1000);
                resendBtn.onclick = async () => {
                    if (!this.registrationData) return;
                    resendBtn.disabled = true;
                    resendBtn.textContent = 'Sending...';
                    try {
                        const postData = {
                            action: 'esa_request_otp',
                            nonce: this.ajaxData.nonce,
                            email: this.registrationData.email,
                            first_name: this.registrationData.first_name,
                            last_name: this.registrationData.last_name,
                            company_name: this.registrationData.company_name,
                            password: this.registrationData.password
                        };
                        // Check if CAPTCHA is truly enabled (convert to boolean)
                        const captchaRequired = this.ajaxData.captcha_enabled === 1 || this.ajaxData.captcha_enabled === true || this.ajaxData.captcha_enabled === '1';
                        if (captchaRequired) {
                            let captchaToken = '';
                            if (this.ajaxData.captcha_type === 'hcaptcha' && typeof hcaptcha !== 'undefined') {
                                captchaToken = hcaptcha.getResponse();
                                if (!captchaToken) {
                                    this.showMessage('Please complete the CAPTCHA verification', 'error');
                                    resendBtn.disabled = false;
                                    resendBtn.textContent = 'Resend code';
                                    return;
                                }
                                postData['h-captcha-response'] = captchaToken;
                            } else if (typeof grecaptcha !== 'undefined') {
                                if (this.ajaxData.captcha_type === 'recaptcha_v3' && this.ajaxData.captcha_site_key) {
                                    captchaToken = await grecaptcha.execute(this.ajaxData.captcha_site_key, { action: 'registration' });
                                } else if (this.captchaWidgetIds.register !== null) {
                                    captchaToken = grecaptcha.getResponse(this.captchaWidgetIds.register);
                                }
                                if (!captchaToken) {
                                    this.showMessage('Please complete the CAPTCHA verification', 'error');
                                    resendBtn.disabled = false;
                                    resendBtn.textContent = 'Resend code';
                                    return;
                                }
                                postData['g-recaptcha-response'] = captchaToken;
                            }
                        }
                        const resp = await fetch(this.ajaxData.ajax_url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(postData)
                        });
                        const result = await resp.json();
                        if (result && result.success) {
                            this.showMessage('A new code was sent to your email.', 'success');
                            this.moveToOtpStage(result.data.resend_seconds || 60);
                        } else {
                            const msg = (result && result.data && result.data.message) ? result.data.message : 'Failed to resend code.';
                            this.showMessage(msg, 'error');
                            resendBtn.disabled = false;
                            resendBtn.textContent = 'Resend code';
                        }
                    } catch (e) {
                        this.showMessage('Failed to resend code.', 'error');
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend code';
                    }
                };
            }
        }

        async handleLogout() {
            try {
                const response = await fetch(this.ajaxData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'esa_logout',
                        nonce: this.ajaxData.nonce
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Update UI state immediately
                    this.isLoggedIn = false;
                    this.userApproved = false;
                    this.userName = '';  // Clear username on logout
                    this.userEmail = '';

                    // Clear form data
                    if (window.esaFormPersistence) {
                        window.esaFormPersistence.clearFormData();
                    }

                    // Update user greeting widget
                    this.updateUserGreeting();

                    // Show success message
                    this.showMessage('Logged out successfully', 'success');

                    // Broadcast logout for all pages
                    this.broadcastAuthState('logout');

                    // Trigger event for graphs/charts to refresh
                    document.dispatchEvent(new CustomEvent('esaAuthSuccess', {
                        detail: { user: null, action: 'logout' }
                    }));

                    // Trigger form restoration event
                    document.dispatchEvent(new CustomEvent('userAuthChanged', {
                        detail: { action: 'logout', success: true }
                    }));
                } else {
                    throw new Error(data.data || 'Logout failed');
                }
            } catch (error) {
                console.error('ESA Auth logout error:', error);
                this.showMessage('Logout failed. Please try again.', 'error');
            }
        }

        loginWithGoogle() {
            try {
                // Use Nextend Social Login Pro for Google login
                if (typeof window.NextendSocialLogin !== 'undefined') {
                    window.NextendSocialLogin.login('google');
                } else {
                    // Fallback to Nextend Social Login Pro URL
                    window.location.href = this.getNextendLoginUrl('google');
                }
            } catch (error) {
                console.error('ESA Auth Google login error:', error);
            }
        }

        registerWithGoogle() {
            try {
                // Use Nextend Social Login Pro for Google registration
                if (typeof window.NextendSocialLogin !== 'undefined') {
                    window.NextendSocialLogin.register('google');
                } else {
                    // Fallback to Nextend Social Login Pro URL
                    window.location.href = this.getNextendRegisterUrl('google');
                }
            } catch (error) {
                console.error('ESA Auth Google registration error:', error);
            }
        }

        getNextendLoginUrl(provider) {
            try {
                const currentUrl = encodeURIComponent(window.location.href);
                return `${this.ajaxData.ajax_url}?action=nsl_login&provider=${provider}&redirect_to=${currentUrl}`;
            } catch (error) {
                return '#';
            }
        }

        getNextendRegisterUrl(provider) {
            try {
                const currentUrl = encodeURIComponent(window.location.href);
                return `${this.ajaxData.ajax_url}?action=nsl_register&provider=${provider}&redirect_to=${currentUrl}`;
            } catch (error) {
                return '#';
            }
        }

        showMessage(message, type) {
            try {
                // Remove existing messages
                document.querySelectorAll('.esa-message').forEach(msg => msg.remove());

                const messageDiv = document.createElement('div');
                messageDiv.className = `esa-message esa-message-${type}`;
                messageDiv.textContent = message;
                messageDiv.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 1rem 1.5rem;
                    border-radius: 0.5rem;
                    color: white;
                    font-weight: 500;
                    z-index: 10001;
                    max-width: 300px;
                    word-wrap: break-word;
                    ${type === 'success' ? 'background: #059669;' : 'background: #dc2626;'}
                `;

                document.body.appendChild(messageDiv);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 5000);
            } catch (error) {
                console.error('ESA Auth message display error:', error);
            }
        }

        updateUserGreeting() {
            // Update the user greeting widget
            const greetingWidget = document.querySelector('.esa-user-greeting-widget');
            if (greetingWidget) {
                if (this.isLoggedIn) {
                    const displayName = this.userName || this.userEmail || 'User';
                    // Show logged in state
                    greetingWidget.innerHTML = `
                        <div class="esa-user-info">
                            <div class="esa-user-details">
                                <p class="esa-user-name">Welcome, ${displayName}!</p>
                                <p class="esa-user-status">
                                    Status: <span class="esa-status-badge esa-status-${this.userApproved ? 'approved' : 'pending'}">
                                        ${this.userApproved ? 'Approved' : 'Pending Approval'}
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
                } else {
                    // Show guest state
                    greetingWidget.innerHTML = `
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
            }
        }

        setupAuthBroadcast() {
            try {
                if (typeof window === 'undefined' || typeof window.addEventListener === 'undefined') {
                    return;
                }

                window.addEventListener('storage', (event) => {
                    if (!event || event.key !== this.storageBroadcastKey || !event.newValue) {
                        return;
                    }

                    try {
                        const payload = JSON.parse(event.newValue);
                        if (!payload || payload.sourceId === this.sessionId) {
                            return;
                        }

                        this.isLoggedIn = !!payload.isLoggedIn;
                        this.userApproved = !!payload.userApproved;
                        this.userName = payload.userName || '';
                        this.userEmail = payload.userEmail || '';

                        if (!this.isLoggedIn) {
                            this.userApproved = false;
                        }

                        this.updateUserGreeting();

                        if (this.isLoggedIn && this.userApproved) {
                            this.showAuthenticatedAccess();
                        } else {
                            this.showGuestAccess();
                        }

                        this.emitAuthEvent(payload.action || 'sync', { origin: 'broadcast' });
                    } catch (error) {
                        console.error('ESA Auth: Error parsing broadcast payload', error);
                    }
                });
            } catch (error) {
                console.warn('ESA Auth: Unable to set up auth broadcast channel', error);
            }
        }

        emitAuthEvent(action = 'sync', overrides = {}) {
            const detail = {
                action,
                isLoggedIn: this.isLoggedIn,
                userApproved: this.userApproved,
                userName: this.userName,
                userEmail: this.userEmail,
                ...overrides
            };

            document.dispatchEvent(new CustomEvent('esaAuthState', { detail }));
            return detail;
        }

        broadcastAuthState(action = 'sync', overrides = {}) {
            const detail = this.emitAuthEvent(action, overrides);
            this.shareAuthState(detail);
        }

        shareAuthState(detail) {
            try {
                if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
                    return;
                }

                const payload = {
                    ...detail,
                    timestamp: Date.now(),
                    sourceId: this.sessionId
                };

                window.localStorage.setItem(this.storageBroadcastKey, JSON.stringify(payload));
                // Remove key to allow subsequent broadcasts with same payload value
                window.localStorage.removeItem(this.storageBroadcastKey);
            } catch (error) {
                console.warn('ESA Auth: Unable to broadcast auth state', error);
            }
        }

        startUserTracking() {
            try {
                // Track page visit
                this.trackActivity('page_visit');

                // Set up heartbeat
                this.heartbeatInterval = setInterval(() => {
                    this.trackActivity('heartbeat');
                }, 30000); // Every 30 seconds
            } catch (error) {
                console.error('ESA Auth tracking error:', error);
            }
        }

        async trackActivity(activityType) {
            try {
                await fetch(this.ajaxData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'esa_track_activity',
                        nonce: this.ajaxData.nonce,
                        activity_type: activityType,
                        page_url: window.location.href,
                        session_id: this.sessionId
                    })
                });
            } catch (error) {
                console.error('ESA Auth activity tracking error:', error);
            }
        }

        showGuestAccess() {
            try {
                // This will be handled by the integration scripts
                console.log('ESA Auth: Showing guest access');
            } catch (error) {
                console.error('ESA Auth guest access error:', error);
            }
        }

        showAuthenticatedAccess() {
            try {
                // This will be handled by the integration scripts
                console.log('ESA Auth: Showing authenticated access');
            } catch (error) {
                console.error('ESA Auth authenticated access error:', error);
            }
        }

        initializePageAccess() {
            try {
                // This function will be called by each page to set up access control
                if (typeof window.esaAuth === 'undefined') {
                    window.esaAuth = this;
                }
            } catch (error) {
                console.error('ESA Auth page access initialization error:', error);
            }
        }
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        try {
            if (typeof window.ESAAuth !== 'undefined' && !window.esaAuth) {
                window.esaAuth = new window.ESAAuth();
            }
        } catch (error) {
            console.error('ESA Auth DOM ready initialization error:', error);
        }
    });

})();

// Global functions for page integration
window.esaRequiresAuth = function (callback) {
    try {
        if (window.esaAuth && window.esaAuth.isLoggedIn) {
            callback();
        } else {
            window.esaAuth.showModal();
        }
    } catch (error) {
        console.error('ESA Auth requires auth error:', error);
    }
};

window.esaSaveEstimate = function (pageType, selectedModel, formData) {
    try {
        if (window.esaAuth) {
            window.esaAuth.saveEstimateRequest(pageType, selectedModel, formData);
        }
    } catch (error) {
        console.error('ESA Auth save estimate error:', error);
    }
};
