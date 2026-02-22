# Engineered Solutions Authentication (ESA) v2.6.0

Complete WordPress authentication for pump sizing applications with OTP email verification, social login integration, access control, user tracking, estimate submission, admin approvals, rate limiting, and bot protection.

## Overview

- Authentication: email/password with OTP-only verification, plus Nextend Social Login Pro integration
- Authorization: guest vs approved roles with UI controls and content gating
- Verification: 6‑digit OTP by email before account creation (no links)
- Administration: approval workflow with public approve/deny links for admins
- Observability: login and activity tracking with session heartbeat
- Security: CAPTCHA, honeypot, IP-based rate limiting, OTP attempt lockouts

## Architecture

- Core plugin: `engineered-solutions-auth.php` (hooks, endpoints, DB schema, flows)
- Frontend assets: `assets/js/auth-production.js`, `assets/css/*.css`
- Shortcodes and UI widgets: `includes/*.php`
- Admin UI: `admin/admin-page.php`, `admin/settings-page.php`
- Optional integration helpers: `integration/*.js`

## Features

- Email/password login and registration (two-step with OTP)
- 6‑digit OTP email verification prior to user creation
- Social login integration via Nextend Social Login Pro
- Guest vs approved access with dynamic UI states
- User greeting widget and login modal
- User activity and login logging (with IP address and provider)
- Estimate request capture and admin email notifications
- Admin approval workflow with public approve/deny links
- IP-based rate limiting and honeypot field protections
- reCAPTCHA v2/v3 or hCaptcha support


# iPad Compatibility & Magic Link Implementation Plan

## Overview
This document outlines the changes made to the Engineered Solutions Authentication plugin to improve iPad compatibility and introduce a new Magic Link authentication method.

## 1. iPad Compatibility Fixes
Targeted improvements to ensure a smooth user experience on iPad and other touch devices.

### CSS Optimizations (`assets/css/modern-auth.css`)
- **Prevent Auto-Zoom**: Set input font size to `16px` on mobile devices to prevent iOS from zooming in when focusing on inputs.
- **Touch Targets**: Increased padding and size of buttons and interactive elements for easier tapping.
- **Native Feel**: Added `-webkit-overflow-scrolling: touch` for smooth momentum scrolling and `-webkit-appearance: none` to reset default iOS styling.
- **Safe Area Support**: Implemented `env(safe-area-inset-*)` to respect the notch and home indicator areas on newer iPads/iPhones.
- **Hardware Acceleration**: Added `-webkit-transform: translateZ(0)` to modal content to prevent flickering and improve rendering performance.

### JavaScript Enhancements (`assets/js/auth-production.js`)
- **Touch Event Support**: Added `touchend` event listeners alongside `click` events to eliminate the 300ms delay on touch devices.
- **Device Detection**: Added `isIPad` getter to conditionally apply logic if needed (currently used for general touch support).
- **Passive Listeners**: Used `{ passive: false }` where appropriate to ensure `preventDefault()` works correctly for custom touch handling.

## 2. Magic Link Authentication
A new, passwordless login method that sends a secure, one-time link to the user's email.

### Frontend (`assets/js/auth-production.js`)
- **UI Updates**: Added a "Login with Magic Link" button to the main login form and a dedicated "Magic Link Request" form.
- **Request Logic**: Implemented `handleMagicLinkRequest()` to send the user's email to the backend.
- **Verification Logic**: Implemented `verifyMagicLink()` which runs on page load to check for `esa_magic_token` in the URL, verify it, and log the user in automatically.

### Backend (`engineered-solutions-auth.php`)
- **Database**: Added `wp_esa_magic_links` table to store secure tokens, expiration times (15 mins), and usage status.
- **AJAX Endpoints**:
    - `esa_request_magic_link`: Generates a secure random token, saves it to the DB, and emails a login link to the user.
    - `esa_verify_magic_link`: Validates the token, checks expiration, logs the user in, and invalidates the token.
- **Security**:
    - Tokens are cryptographically secure (32 bytes random).
    - Tokens expire after 15 minutes.
    - Tokens are one-time use only.
    - Rate limiting (via existing mechanisms) and IP tracking on token creation.

## 3. Next Steps for User
1.  **Clear Cache**: Clear browser cache on iPad to ensure new CSS and JS are loaded.
2.  **Test Magic Link**: Try logging in using the "Login with Magic Link" button. Check email for the link and verify it logs you in.
3.  **Test iPad Interaction**: Verify that inputs don't zoom, scrolling is smooth, and buttons are easy to tap on an iPad.


## OTP Verification Flow (v2.2.0)

1) User submits registration details → server sends 6‑digit code to email  
2) User enters the code → server verifies; if valid, user account is created as `esa_guest` and marked email verified  
3) Admins receive approval email with public approve/deny links  
4) On approval, user role is promoted to `esa_user` (core roles are preserved)

Behavior details:
- Code TTL: 10 minutes
- Resend cooldown: 60 seconds
- Attempt limit: 5 tries; lock for 15 minutes on exceed
- Code hashing: stored with `password_hash`, cleared on success

## AJAX Endpoints

- `esa_login` (POST): Authenticate user (validates CAPTCHA if enabled)
- `esa_logout` (POST): Log out and clear session
- `esa_track_activity` (POST): Track page activity and heartbeat
- `esa_save_estimate` (POST): Save estimate request and notify admins
- `esa_request_otp` (POST): Send OTP for email (rate-limited, with resend cooldown)
- `esa_verify_otp` (POST): Verify OTP and create user → email verified + admin approval email
- Legacy compatibility:
  - `esa_register` returns an instructional error (OTP-only flow)
  - `?action=verify_email` shows message indicating OTP flow is required
  - `esa_resend_verification` kept for legacy but OTP is authoritative

## Public Admin Links (Approval)

- Approve: `admin-post.php?action=esa_public_approve&token=...`
- Deny: `admin-post.php?action=esa_public_deny&token=...`
- Resend approval: `admin-post.php?action=esa_public_resend_approval&token=...`

## Database Schema

Created and managed by the plugin:

- `wp_esa_user_logins`
  - Tracks user logins, IP address, login/logout times, social provider
- `wp_esa_user_activity`
  - Tracks page visits, page titles, time spent, session id, timestamps
- `wp_esa_estimate_requests`
  - Stores estimate submissions: page type, selected model, form data, timestamp
- `wp_esa_user_approval`
  - Stores approval status and metadata; also mirrored to user meta
- `wp_esa_approval_tokens`
  - One-time tokens for public admin approve/deny links
- `wp_esa_email_verification`
  - OTP flow storage (reused and extended)
  - Fields: `user_id`, `email`, `token` (legacy), `created_at`, `expires_at`, `verified`, `code_hash`, `attempt_count`, `locked_until`
  - Indexes: `token` (unique, legacy), `user_id`, `email`
- `wp_esa_rate_limit`
  - IP-based rate-limit ledger with action type and timestamp

## Roles

- `esa_guest`: default for newly created accounts (limited access)
- `esa_user`: granted after admin approval (full access in-app)
- Core roles (admin/editor/author/contributor/subscriber) are preserved and not downgraded

## Shortcodes

- `[esa_auth show_user_bar="true" show_login_modal="true"]`
  - Injects the login/register modal and optional user bar
- `[esa_user_info show_name="true" show_email="false" show_status="true"]`
  - Displays current user info (or a login link)
- `[esa_login_button text="Login to View Charts" class="esa-btn esa-btn-primary" redirect="/path"]`
  - Renders a login button; optional redirect stored client-side
- `[esa_user_greeting show_user_name="true" show_status="true" show_buttons="true" greeting_text="Welcome" position="right"]`
  - Renders the greeting/status widget with buttons

## Frontend Assets

- `assets/js/auth-production.js`
  - Modal UI; login/register; OTP step (request/resend/verify); logout; captcha integration; tracking heartbeat; greeting updates
- `assets/js/form-persistence.js`
  - Optional form state persistence (restores after auth)
- `assets/css/auth.css`, `assets/css/modern-auth.css`
  - Styling for modal, widgets, and admin badges
- `integration/*.js`
  - Page-specific helpers (`ipc-*`, `mhc-*`, `page-integration.js`) for app integration points

## Admin UI

Menu: WordPress Admin → ESA Users

- User List:
  - Approve/deny actions, role handling for ESA roles, status badges
  - Shows last login, IP address, and login method (social provider)
- User Activity:
  - Recent page views, time spent, visit time, session ids
- Estimate Requests:
  - Recent requests with type, model, timestamp, and view actions
- Settings:
  - Admin notification recipients (comma/newline separated)
  - Session timeout (minutes)
  - Enable user tracking
  - Enable CAPTCHA and select provider (reCAPTCHA v2/v3 or hCaptcha)
  - reCAPTCHA site/secret keys, hCaptcha site/secret keys, v3 minimum score
  - CAPTCHA test utility (verifies configuration)

## Email Notifications

- OTP email to the prospective user (6‑digit code, 10‑minute TTL)
- Admin approval request for each new verified registration
  - Public Approve and Deny links
  - Includes user names, email, IP, and registration time
- Estimate request notifications to configured admin emails
- Approval/Denial email to the user when admin acts

## Installation

1) Upload `engineered-solutions-auth` to `wp-content/plugins/`  
2) Activate via Admin → Plugins  
3) Configure via Admin → ESA Users → Settings (admins, CAPTCHA keys, etc.)

## Page Integration

Recommended: use shortcodes on your WordPress pages.  
For custom or static pages, enqueue CSS/JS and call integration helpers where needed.

```php
// Example enqueue for specific WordPress pages (theme functions.php)
function esa_enqueue_scripts() {
    if (is_page('domestic-booster-sizing') || 
        is_page('rainwater-harvesting-sizing') ||
        is_page('municipal-line-sizing') ||
        is_page('pump-selection')) {
        wp_enqueue_style('esa-auth-css', plugin_dir_url(__FILE__) . 'wp-content/plugins/engineered-solutions-auth/assets/css/auth.css', array(), '2.2.2');
        wp_enqueue_script('esa-auth-production-js', plugin_dir_url(__FILE__) . 'wp-content/plugins/engineered-solutions-auth/assets/js/auth-production.js', array('jquery'), '2.2.2', true);
    }
}
add_action('wp_enqueue_scripts', 'esa_enqueue_scripts');
```

## Testing Scenarios

- Guest user (not logged in): sees guest UI and modal prompts
- Registered but pending approval: logged-in with “Pending Approval” status
- Approved user: full access; actions enabled; estimate saving allowed

## Troubleshooting

- Charts not showing: ensure user is logged in and approved
- Modal not appearing: verify CSS/JS enqueues and shortcode presence
- OTP not received: check spam, confirm email correctness, respect resend cooldown
- Too many attempts: wait for lockout to expire or request a new code
- DB errors: deactivate/reactivate plugin to re-run DB setup; check permissions

## Security

- Sanitization and capability checks throughout
- Nonces on all AJAX endpoints
- OTP codes hashed at rest; TTLs and lockouts enforced
- Rate limiting by IP and action type
- CAPTCHA integration (reCAPTCHA v2/v3 or hCaptcha)
- Honeypot fields on forms
- Account suspension flag blocks login

## Social Login (Nextend)

- Requires Nextend Social Login Pro installed and configured
- Set default role to `ESA Guest` in Nextend
- ESA integrates with Nextend hooks to log and set roles appropriately

## Changelog

### 2.5.0 - Fix Pump Function Override in Integration Files

# Quick Reference: Form Persistence & Email Fixes

## ✅ What Was Fixed

### 1. Form Data Now Reloads on Page Refresh
**Before**: All form data was lost when page refreshed
**After**: Complete form state is preserved including:
- Input fields
- Fixture tables
- Canvas drawings
- Calculated results
- Selected systems
- Pump selections

### 2. Form Data Save Trigger Changed
**Before**: Saved on every input change and page unload
**After**: Only saves when user clicks:
- "Sign In" button
- "Login to View" button

### 3. Denial Emails Disabled
**Before**: Users received emails when denied
**After**: Users do NOT receive emails when denied (only admins are notified)

## 🔧 Files Changed

1. `wp-content/plugins/engineered-solutions-auth/assets/js/form-persistence.js`
   - Enhanced to save/restore complete form state
   - Changed to save only on specific button clicks

2. `wp-content/plugins/engineered-solutions-auth/admin/admin-page.php`
   - Added documentation to keep denial emails disabled

## 🧪 How to Test

### Test Form Persistence:
1. Fill in any form (add fixtures, enter values, draw on canvas)
2. Click "Login to View" on a pump chart
3. Log in
4. ✅ All your data should be restored

### Test Page Refresh:
1. Fill in form data
2. Click "Sign In" and log in
3. Refresh the page (F5)
4. ✅ All your data should still be there

### Test Denial Email:
1. Register a new test user
2. As admin, deny the user
3. ✅ User should NOT receive any email

## 📝 Important Notes

- Data is saved per browser tab
- Data expires after 24 hours
- Data is cleared when user logs out
- Works on all modern browsers (Chrome, Firefox, Safari, Edge)

## 🚀 Ready to Use

All changes are complete and ready for testing. No additional configuration needed.


### 2.4.4 - Fix Pump Function Override in Integration Files
- await the approval status check before calling the original function


### 2.4.3 - Fix Pump Function Override in Integration Files removed async

### 2.4.2 - Approval Status Check Fix
- Fixed approval/denial status check issue

### 2.4.0 - Logout Nonce and status check fix
- Fixed logout nonce issue
- Fixed status check issue

### 2.3.8 - Login Button and Password toogle fix
- Fixed login button and password toogle issue


### 2.3.5 - iPad Compatibility & Magic Link Implementation (December 2025)
**iPad Compatibility Fixes:**
- Prevents auto-zoom on mobile devices
- Increases touch targets for better interaction
- Optimizes CSS for smooth momentum scrolling
- Adds hardware acceleration for improved performance

**Magic Link Authentication:**
- Added a "Login with Magic Link" button to the main login form
- Implemented `handleMagicLinkRequest()` to send the user's email to the backend
- Implemented `verifyMagicLink()` which runs on page load to check for `esa_magic_token` in the URL, verify it, and log the user in automatically

### 2.3.1 - Admin Panel & Form Persistence Improvements (December 2025)
**Admin Panel Fixes:**
- Fixed critical error when approving/denying users directly from ESA User Management page
- Added output buffering to prevent "headers already sent" errors
- Implemented status verification before processing to prevent duplicate actions
- Shows informative messages when user is already approved/denied with admin name and timestamp

**Enhanced Admin Messaging:**
- Public approval/denial links now show actual user status instead of generic "link expired" messages
- When link is already used, displays: *"This user has already been [approved/denied] by [Admin Name] on [Date]"*
- Improved clarity for multi-admin environments

**Form Persistence Optimization:**
- Removed auto-save on every keystroke (input/change events) for better performance
- Form data now saves only when opening login/register modals or before page unload
- Maintains data persistence while eliminating performance overhead

### 2.3.0 - Magic Link & iPad Compatibility (November 2025)
**Magic Link Authentication:**
- Passwordless login via secure one-time email link
- 15-minute token expiration with cryptographic security
- New `wp_esa_magic_links` database table
- AJAX endpoints: `esa_request_magic_link`, `esa_verify_magic_link`

**iPad & Touch Device Optimization:**
- Prevented auto-zoom on input focus (16px font size)
- Added touch event support with `touchend` listeners
- Improved touch targets and button sizes for easier tapping
- Hardware acceleration for smoother animations
- Safe area support for devices with notches

**Frontend Enhancements:**
- Touch event support alongside click events (eliminates 300ms delay)
- Device detection for conditional touch logic
- Passive event listeners for better scroll performance

### 2.2.3 - Bug Fixes & Stability
- Miscellaneous bug fixes and stability improvements
- Performance optimizations

### 2.2.2 - OTP UI Bug Fix
- Fixed `originalText` scope error that blocked OTP flow
- Bumped asset versions for cache invalidation

### 2.2.0 - OTP-only Email Verification
- Switched to 6‑digit OTP verification before user creation
- Added resend cooldown and lockout on excessive attempts
- Extended `esa_email_verification` schema (`email`, `code_hash`, `attempt_count`, `locked_until`)
- Updated frontend for 2-step registration (request code → verify)
- Kept admin approval flow unchanged

### 2.1.2 - Email Verification Improvement
- Fixed “Verification link invalid/expired”
- Resolved token conflicts on deleted/re-registered users
- Added resend functionality and improved diagnostics

### 2.0.0 - Enhanced UX & Security
- Password visibility toggle
- Smart CAPTCHA reset on failed attempts
- Honeypot + rate limiting
- Email verification before admin approval
- Public admin approve/deny links
- Account suspension and role handling

### 1.0.0
- Initial release with authentication, tracking, admin UI, and integration hooks
