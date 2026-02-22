/**
 * Form Data Persistence System
 * Saves and restores form data when users login/register
 * Works for all 4 applications: IPC/MHC Domestic Booster Sizing & Rainwater Harvesting Sizing
 */

class ESAFormPersistence {
    constructor() {
        this.storageKey = this.buildStorageKey();
        this.storage = this.getStorageProvider();
        this.hasRestored = false; // Guard: only restore once per page load
        this.init();
    }

    init() {
        this.setupFormSaving();
        this.setupFormRestoration();
        this.setupDataClearing();
    }

    setupFormSaving() {
        if (!this.storage) return;

        // Auto-save form data on input/change events with a debounce
        let saveTimeout;
        const debouncedSave = () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                this.saveCurrentFormData();
            }, 1000); // 1-second debounce to prevent lag
        };

        // Listen for user input
        document.addEventListener('input', (e) => {
            if (e.target.closest('.esa-auth-modal') || e.target.closest('.esa-user-greeting-widget')) return;
            debouncedSave();
        });

        // Listen for changes (like dropdowns, checkboxes)
        document.addEventListener('change', (e) => {
            if (e.target.closest('.esa-auth-modal') || e.target.closest('.esa-user-greeting-widget')) return;
            debouncedSave();
        });

        // Save immediately on any app button click (like "Calculate" or "Add Fixture")
        document.addEventListener('click', (e) => {
            if (e.target.closest('.esa-auth-modal') || e.target.closest('.esa-user-greeting-widget')) return;

            const target = e.target.closest('button') || e.target;
            if (target.tagName === 'BUTTON' || target.type === 'button') {
                this.saveCurrentFormData();
            }
        });

        // Save explicitly when trying to login/register via our modal triggers
        document.addEventListener('click', (e) => {
            const target = e.target.closest('button') || e.target;
            const text = (target.textContent || '').trim();

            const isSaveButton =
                target.classList.contains('esa-login-icon') ||
                (target.classList.contains('esa-btn-primary') && text.includes('Sign In')) ||
                (target.tagName === 'BUTTON' && text.includes('Sign In')) ||
                text.includes('Login to Continue') ||
                text.includes('Login to View') ||
                (target.classList.contains('esa-tab-btn') && target.dataset.tab === 'register');

            if (isSaveButton) {
                console.log('ESA Form Data: Saving on Auth button click:', text);
                this.saveCurrentFormData();
            }
        });

        // Fail-safe: Save right before the user refreshes or leaves the page
        window.addEventListener('beforeunload', () => {
            console.log('ESA Form Data: Saving right before page unload');
            this.saveCurrentFormData();
        });
    }

    setupFormRestoration() {
        // Restore after explicit login action only (not on 'init' or 'logout')
        document.addEventListener('esaAuthState', (event) => {
            if (event.detail &&
                event.detail.isLoggedIn &&
                event.detail.action === 'login' &&
                !this.hasRestored) {
                setTimeout(() => this.restoreFormData(), 800);
            }
        });

        // Also listen to the legacy userAuthChanged event
        document.addEventListener('userAuthChanged', (event) => {
            if (event.detail &&
                event.detail.action === 'login' &&
                event.detail.success &&
                !this.hasRestored) {
                setTimeout(() => this.restoreFormData(), 1000);
            }
        });

        // Restore on page load ONLY if:
        //   a) The user is already logged in (from PHP), AND
        //   b) We have saved data for this page
        // Use readyState because this class is typically constructed inside DOMContentLoaded,
        // so adding another DOMContentLoaded listener here would never fire.
        const doPageLoadRestore = () => {
            const isLoggedIn = (typeof esa_ajax !== 'undefined' && (esa_ajax.is_logged_in || esa_ajax.is_user_logged_in));
            if (isLoggedIn && !this.hasRestored) {
                // Small delay to let the page's own JS finish initializing
                setTimeout(() => this.restoreFormData(), 1500);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', doPageLoadRestore);
        } else {
            // DOM already loaded — run shortly after construction
            setTimeout(doPageLoadRestore, 0);
        }
    }

    setupDataClearing() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('esa-logout-icon') ||
                (e.target.textContent || '').includes('Sign Out')) {
                this.clearFormData();
                this.hasRestored = false;
            }
        });

        document.addEventListener('esaAuthState', (event) => {
            if (event.detail && event.detail.action === 'logout') {
                this.clearFormData();
                this.hasRestored = false;
            }
        });
    }

    saveCurrentFormData() {
        try {
            if (!this.storage) return;
            const formData = {};

            // ── Basic inputs (by name OR id, excluding passwords) ────────────────────
            document.querySelectorAll('input, select, textarea').forEach(input => {
                const key = input.name || input.id;
                if (!key || input.type === 'password' || key.startsWith('esa-')) return;
                if (input.closest('.esa-auth-modal') || input.closest('.esa-user-greeting-widget')) return;
                if (input.type === 'radio' || input.type === 'checkbox') return; // handled below
                if (input.value) formData[key] = input.value;
            });

            // ── Radio buttons ─────────────────────────────────────────────────────────
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                const key = radio.name || radio.id;
                if (key && !key.startsWith('esa-') && !radio.closest('.esa-auth-modal') && !radio.closest('.esa-user-greeting-widget')) {
                    // Store both which group value is selected AND individual checked state
                    if (radio.checked) formData[key] = radio.value;
                    if (radio.id) formData[`radio_checked_${radio.id}`] = radio.checked;
                }
            });

            // ── Checkboxes ────────────────────────────────────────────────────────────
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                const key = checkbox.name || checkbox.id;
                if (key && !key.startsWith('esa-') && !checkbox.closest('.esa-auth-modal') && !checkbox.closest('.esa-user-greeting-widget')) {
                    formData[key] = checkbox.checked;
                }
            });

            // ── Section / panel visibility (Day Tank, Municipal Line, etc.) ───────────
            // Save the display state of every element with an ID containing section keywords
            const sectionVisibility = {};
            document.querySelectorAll('[id]').forEach(el => {
                const id = el.id;
                if (!id || id.startsWith('esa-') || el.closest('.esa-auth-modal') || el.closest('.esa-user-greeting-widget')) return;

                // Capture everything that might be a toggled section
                if (el.tagName !== 'SCRIPT' && el.tagName !== 'STYLE') {
                    sectionVisibility[id] = {
                        display: el.style.display,
                        hidden: el.hidden
                    };
                }
            });
            formData.sectionVisibility = sectionVisibility;

            // ── Toggle/active button state for section selectors ─────────────────────
            const activeButtons = [];
            document.querySelectorAll('.active, .selected, [aria-selected="true"]').forEach(el => {
                if (el.id && !el.id.startsWith('esa-') && !el.closest('.esa-auth-modal') && !el.closest('.esa-user-greeting-widget')) {
                    activeButtons.push(el.id);
                }
            });
            formData.activeButtons = activeButtons;

            // ── All calculated results and result divs ───────────────────────────────
            const calculatedValues = {};
            document.querySelectorAll('[id$="Result"], [id*="Result"], [id*="result"]').forEach(el => {
                if (el.id && !el.id.startsWith('esa-') && !el.closest('.esa-auth-modal') && (el.textContent || el.innerHTML)) {
                    calculatedValues[el.id] = el.innerHTML;
                }
            });
            formData.calculatedValues = calculatedValues;

            // ── Fixture / added-items table ───────────────────────────────────────────
            ['addedFixturesTable', 'addedFixturesBody'].forEach(id => {
                const el = document.getElementById(id);
                if (el) formData[`html_${id}`] = el.innerHTML;
            });

            // ── Canvas drawings ───────────────────────────────────────────────────────
            const canvases = {};
            document.querySelectorAll('canvas').forEach(canvas => {
                if (canvas.id) {
                    try { canvases[canvas.id] = canvas.toDataURL(); }
                    catch (e) { console.warn('Could not save canvas:', canvas.id, e); }
                }
            });
            formData.canvases = canvases;

            // ── Selected system/option panels ─────────────────────────────────────────
            formData.selectedSystems = Array.from(
                document.querySelectorAll('.option.selected, .system-option.selected, [data-selected="true"]')
            ).map(el => el.id).filter(Boolean);

            // ── Pump result displays (Simplex, Duplex, Triplex) ───────────────────────
            ['pumpResultSimplex', 'pumpResultDuplex', 'pumpResultTriplex'].forEach(id => {
                const el = document.getElementById(id);
                if (el) formData[id] = el.innerHTML;
            });

            // ── All spans that contain computed text ──────────────────────────────────
            document.querySelectorAll('span[id]').forEach(span => {
                if (span.textContent && span.id && !span.id.startsWith('esa-') && !span.closest('.esa-auth-modal') && !span.closest('.esa-user-greeting-widget')) {
                    formData[`span_${span.id}`] = span.textContent;
                }
            });

            // ── Global JS state variables ─────────────────────────────────────────────
            ['selectedSystem', 'selectedOption', 'day_tank_pump_type', 'fixturesTotalWSFU'].forEach(varName => {
                if (typeof window[varName] !== 'undefined') formData[`global_${varName}`] = window[varName];
            });

            // ── Pump results visibility ───────────────────────────────────────────────
            const pumpResultsVisibility = [];
            document.querySelectorAll('.pumpResult').forEach(el => {
                pumpResultsVisibility.push(el.style.display || '');
            });
            formData.pumpResultsVisibility = pumpResultsVisibility;

            // ── Meta ──────────────────────────────────────────────────────────────────
            formData.timestamp = Date.now();
            formData.pageUrl = window.location.href;

            this.storage.setItem(this.storageKey, JSON.stringify(formData));
            console.log('ESA Form Data: Saved complete form data for', window.location.pathname);
        } catch (error) {
            console.error('ESA Form Data: Error saving form data', error);
        }
    }

    restoreFormData() {
        try {
            if (!this.storage) return;
            if (this.hasRestored) {
                console.log('ESA Form Data: Already restored this page load, skipping');
                return;
            }

            const savedData = this.storage.getItem(this.storageKey);
            if (!savedData) {
                console.log('ESA Form Data: No saved data found');
                return;
            }

            const formData = JSON.parse(savedData);

            // ── Page match check ──────────────────────────────────────────────────────
            if (formData.pageUrl) {
                try {
                    const savedUrl = new URL(formData.pageUrl, window.location.origin);
                    if (savedUrl.pathname !== window.location.pathname) {
                        console.log('ESA Form Data: Data is for a different page, skipping restore');
                        return;
                    }
                } catch (e) {
                    if (formData.pageUrl !== window.location.href) return;
                }
            }

            console.log('ESA Form Data: Payload being restored:', formData);

            // ── Freshness check (24 hours) ────────────────────────────────────────────
            if (Date.now() - formData.timestamp > 24 * 60 * 60 * 1000) {
                console.log('ESA Form Data: Saved data expired, clearing');
                this.clearFormData();
                return;
            }

            // Mark restored immediately to prevent duplicate runs
            this.hasRestored = true;

            // ── STEP 1: Restore section visibility FIRST so hidden fields become visible ──
            if (formData.sectionVisibility) {
                Object.keys(formData.sectionVisibility).forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const saved = formData.sectionVisibility[id];
                    if (typeof saved.display !== 'undefined' && saved.display !== '') {
                        el.style.display = saved.display;
                    }
                    if (typeof saved.hidden !== 'undefined') {
                        el.hidden = saved.hidden;
                    }
                });
            }

            // ── STEP 2: Restore active/selected button states so app JS updates sections ──
            if (formData.activeButtons && formData.activeButtons.length) {
                formData.activeButtons.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.classList.add('active');
                });
            }

            if (formData.selectedSystems && formData.selectedSystems.length) {
                formData.selectedSystems.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.classList.add('selected');
                });
            }

            // ── STEP 3: Restore radio buttons (fire click to trigger app-side toggle JS) ──
            Object.keys(formData).forEach(key => {
                if (!key.startsWith('radio_checked_')) return;
                const radioId = key.substring('radio_checked_'.length);
                const radio = document.getElementById(radioId);
                if (radio && formData[key] === true) {
                    radio.checked = true;
                    // Fire click to trigger app-side section switching
                    radio.dispatchEvent(new Event('click', { bubbles: true }));
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // ── STEP 4: Restore input values ──────────────────────────────────────────
            const skipKeys = new Set([
                'calculatedValues', 'sectionVisibility', 'activeButtons', 'selectedSystems',
                'canvases', 'timestamp', 'pageUrl',
                'pumpResultSimplex', 'pumpResultDuplex', 'pumpResultTriplex'
            ]);

            Object.keys(formData).forEach(key => {
                if (skipKeys.has(key)) return;
                if (key.startsWith('html_') || key.startsWith('span_') ||
                    key.startsWith('global_') || key.startsWith('radio_checked_')) return;

                // By name first, then by id
                let elements = document.querySelectorAll(`[name="${key}"]`);
                if (elements.length === 0) {
                    const el = document.getElementById(key);
                    if (el) elements = [el];
                }

                elements.forEach(el => {
                    if (el.type === 'radio') {
                        if (el.value === formData[key]) {
                            el.checked = true;
                        }
                    } else if (el.type === 'checkbox') {
                        el.checked = !!formData[key];
                    } else if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
                        el.value = formData[key];
                    }
                });
            });

            // ── STEP 5: Restore HTML tables ──────────────────────────────────────────
            ['addedFixturesTable', 'addedFixturesBody'].forEach(id => {
                if (formData[`html_${id}`]) {
                    const el = document.getElementById(id);
                    if (el) el.innerHTML = formData[`html_${id}`];
                }
            });

            // ── STEP 6: Restore canvas drawings ───────────────────────────────────────
            if (formData.canvases) {
                Object.keys(formData.canvases).forEach(canvasId => {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    const img = new Image();
                    img.onload = () => {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0);
                    };
                    img.src = formData.canvases[canvasId];
                });
            }

            // ── STEP 7: Restore calculated result HTML ─────────────────────────────────
            if (formData.calculatedValues) {
                Object.keys(formData.calculatedValues).forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.innerHTML = formData.calculatedValues[id];
                });
            }

            // ── STEP 8: Restore pump result text ──────────────────────────────────────
            ['pumpResultSimplex', 'pumpResultDuplex', 'pumpResultTriplex'].forEach(id => {
                if (formData[id]) {
                    const el = document.getElementById(id);
                    if (el) el.innerHTML = formData[id];
                }
            });

            // ── STEP 9: Restore span content ──────────────────────────────────────────
            Object.keys(formData).forEach(key => {
                if (!key.startsWith('span_')) return;
                const spanId = key.substring(5);
                const span = document.getElementById(spanId);
                if (span) span.textContent = formData[key];
            });

            // ── STEP 10: Restore pump results visibility ──────────────────────────────
            if (formData.pumpResultsVisibility) {
                const pumpResultEls = document.querySelectorAll('.pumpResult');
                formData.pumpResultsVisibility.forEach((display, i) => {
                    if (pumpResultEls[i]) {
                        pumpResultEls[i].style.display = display;
                    }
                });
            }

            // ── STEP 11: Restore global JS variables ──────────────────────────────────
            Object.keys(formData).forEach(key => {
                if (!key.startsWith('global_')) return;
                const varName = key.substring(7);
                window[varName] = formData[key];
            });

            // ── STEP 12: Fire change events globally after all values are set ────────
            setTimeout(() => {
                document.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.value && input.type !== 'radio' && input.type !== 'checkbox') {
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                // ── STEP 13: Execute application-specific table renders ──────────────────
                if (typeof window.renderFixtureTable === 'function') {
                    window.renderFixtureTable();
                }
                if (typeof window.calculateFixtureFlow === 'function') {
                    window.calculateFixtureFlow();
                }
            }, 100);

            console.log('ESA Form Data: Restored complete form data for', window.location.pathname);
            this.showRestoreNotification();

        } catch (error) {
            console.error('ESA Form Data: Error restoring form data', error);
            this.hasRestored = false; // Allow retry on error
        }
    }

    clearFormData() {
        try {
            if (this.storage) this.storage.removeItem(this.storageKey);
            console.log('ESA Form Data: Cleared saved form data');
        } catch (error) {
            console.error('ESA Form Data: Error clearing form data', error);
        }
    }

    buildStorageKey() {
        try {
            const path = window.location.pathname;
            const normalized = path.replace(/[^a-z0-9]/gi, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
            return `esa_form_data_${normalized || 'root'}`;
        } catch {
            return 'esa_form_data';
        }
    }

    getStorageProvider() {
        const test = (fn) => {
            try {
                const s = fn();
                if (!s) return null;
                s.setItem('__esa_test__', '1');
                s.removeItem('__esa_test__');
                return s;
            } catch { return null; }
        };

        return (
            test(() => window.sessionStorage) ||
            test(() => window.localStorage) ||
            (() => {
                console.warn('ESA Form Data: No web storage available, using in-memory store.');
                const mem = {};
                return {
                    setItem: (k, v) => { mem[k] = v; },
                    getItem: (k) => mem[k] ?? null,
                    removeItem: (k) => { delete mem[k]; }
                };
            })()
        );
    }

    showRestoreNotification() {
        try {
            // Only show notification if there's meaningful visible content (not just empty first load)
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #059669;
                color: white;
                padding: 12px 20px;
                border-radius: 6px;
                font-size: 14px;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transition: opacity 0.3s ease;
            `;
            notification.textContent = '✓ Your previous work has been restored';
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        } catch (e) { /* non-critical */ }
    }
}

// Initialize once on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.esaFormPersistence === 'undefined') {
        window.esaFormPersistence = new ESAFormPersistence();
    }
});

window.ESAFormPersistence = ESAFormPersistence;
