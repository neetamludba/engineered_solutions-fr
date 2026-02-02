/**
 * Form Data Persistence System
 * Saves and restores form data when users login/register
 */

class ESAFormPersistence {
    constructor() {
        this.storageKey = this.buildStorageKey();
        this.storage = this.getStorageProvider();
        this.debouncedSave = this.debounce(() => this.saveCurrentFormData(), 400);
        this.init();
    }

    init() {
        // Save form data before login/registration
        this.setupFormSaving();

        // Restore form data after successful login/registration
        this.setupFormRestoration();

        // Clear data when user logs out
        this.setupDataClearing();
    }

    setupFormSaving() {
        if (!this.storage) {
            return;
        }

        // Save form data ONLY when specific buttons are clicked
        document.addEventListener('click', (e) => {
            // Check for buttons or their children
            const target = e.target.closest('button') || e.target;
            const text = target.textContent || '';

            if (target.classList.contains('esa-login-icon') ||
                (target.classList.contains('esa-btn-primary') && text.includes('Sign In')) ||
                text.includes('Login to Continue') ||
                text.includes('Login to View') ||
                (target.tagName === 'BUTTON' && text.includes('Sign In'))) {
                this.saveCurrentFormData();
            }
        });

        // Save form data when registration modal is shown
        document.addEventListener('click', (e) => {
            const target = e.target.closest('.esa-tab-btn') || e.target;
            if (target.classList.contains('esa-tab-btn') &&
                target.dataset.tab === 'register') {
                this.saveCurrentFormData();
            }
        });

        // Save form data before page unload (for reload/refresh support)
        window.addEventListener('beforeunload', () => {
            this.saveCurrentFormData();
        });
    }

    setupFormRestoration() {
        // Listen for successful login/registration
        document.addEventListener('userAuthChanged', (event) => {
            if (event.detail && event.detail.success) {
                setTimeout(() => {
                    this.restoreFormData();
                }, 1000);
            }
        });

        // Listen to unified auth state events
        document.addEventListener('esaAuthState', (event) => {
            if (event.detail && event.detail.isLoggedIn && event.detail.action === 'login') {
                setTimeout(() => {
                    this.restoreFormData();
                }, 800);
            }
        });

        // Also restore on page load if user is logged in
        if (window.esaAuth && window.esaAuth.isLoggedIn) {
            setTimeout(() => {
                this.restoreFormData();
            }, 2000);
        }

        // Restore on DOM ready as well
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                this.restoreFormData();
            }, 1000);
        });
    }

    setupDataClearing() {
        // Clear data when user logs out
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('esa-logout-icon') ||
                e.target.textContent.includes('Sign Out')) {
                this.clearFormData();
            }
        });

        document.addEventListener('esaAuthState', (event) => {
            if (event.detail && event.detail.action === 'logout') {
                this.clearFormData();
            }
        });
    }

    saveCurrentFormData() {
        try {
            const formData = {};

            // Save all input fields (including IDs without names)
            document.querySelectorAll('input, select, textarea').forEach(input => {
                const key = input.name || input.id;
                if (key && input.value && !input.type.includes('password')) {
                    formData[key] = input.value;
                }
            });

            // Save radio button selections
            document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                const key = radio.name || radio.id;
                if (key) {
                    formData[key] = radio.value;
                }
            });

            // Save checkbox selections
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                const key = checkbox.name || checkbox.id;
                if (key) {
                    formData[key] = checkbox.checked;
                }
            });

            // Save ALL calculated values and result divs
            const calculatedValues = {};
            document.querySelectorAll('[id$="Result"], [id*="Result"], [id*="result"]').forEach(element => {
                if (element.textContent || element.innerHTML) {
                    calculatedValues[element.id] = element.innerHTML;
                }
            });
            formData.calculatedValues = calculatedValues;

            // Save fixture table data
            const fixtureTable = document.getElementById('addedFixturesTable');
            if (fixtureTable) {
                formData.fixtureTableHTML = fixtureTable.innerHTML;
            }

            const fixtureBody = document.getElementById('addedFixturesBody');
            if (fixtureBody) {
                formData.fixtureBodyHTML = fixtureBody.innerHTML;
            }

            // Save canvas images
            const canvases = {};
            document.querySelectorAll('canvas').forEach(canvas => {
                if (canvas.id) {
                    try {
                        canvases[canvas.id] = canvas.toDataURL();
                    } catch (e) {
                        console.warn('Could not save canvas:', canvas.id, e);
                    }
                }
            });
            formData.canvases = canvases;

            // Save selected system/option
            const selectedSystemElements = document.querySelectorAll('.option.selected');
            if (selectedSystemElements.length > 0) {
                formData.selectedSystems = Array.from(selectedSystemElements).map(el => el.id);
            }

            // Save pump results (Simplex, Duplex, Triplex)
            ['pumpResultSimplex', 'pumpResultDuplex', 'pumpResultTriplex'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    formData[id] = element.textContent || element.innerHTML;
                }
            });

            // Save all span content that might contain results
            document.querySelectorAll('span[id]').forEach(span => {
                if (span.textContent && span.id) {
                    formData[`span_${span.id}`] = span.textContent;
                }
            });

            // Save specific application state
            const stateElements = [
                'selectedSystem', 'selectedOption', 'day_tank_pump_type',
                'totalYearly', 'totalMonthly', 'total_irrigation_flow_rate'
            ];

            stateElements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    if (element.value !== undefined) {
                        formData[id] = element.value;
                    } else if (element.textContent) {
                        formData[id] = element.textContent;
                    }
                }
            });

            // Save global variables if they exist
            if (typeof window.selectedSystem !== 'undefined') {
                formData.globalSelectedSystem = window.selectedSystem;
            }
            if (typeof window.selectedOption !== 'undefined') {
                formData.globalSelectedOption = window.selectedOption;
            }
            if (typeof window.day_tank_pump_type !== 'undefined') {
                formData.globalDayTankPumpType = window.day_tank_pump_type;
            }

            // Save form state
            formData.timestamp = Date.now();
            formData.pageUrl = window.location.href;

            // Store in sessionStorage (per browser tab)
            if (!this.storage) {
                return;
            }
            this.storage.setItem(this.storageKey, JSON.stringify(formData));

            console.log('ESA Form Data: Saved complete form data', formData);
        } catch (error) {
            console.error('ESA Form Data: Error saving form data', error);
        }
    }

    restoreFormData() {
        try {
            if (!this.storage) return;

            const savedData = this.storage.getItem(this.storageKey);
            if (!savedData) {
                console.log('ESA Form Data: No saved data found');
                return;
            }

            const formData = JSON.parse(savedData);

            // Check if data is for current page
            if (formData.pageUrl) {
                try {
                    const savedUrl = new URL(formData.pageUrl, window.location.origin);
                    if (savedUrl.pathname !== window.location.pathname) {
                        console.log('ESA Form Data: Data is for different page, skipping restore');
                        return;
                    }
                } catch (error) {
                    if (formData.pageUrl !== window.location.href) {
                        console.log('ESA Form Data: Data is for different page, skipping restore');
                        return;
                    }
                }
            }

            // Check if data is not too old (24 hours)
            const maxAge = 24 * 60 * 60 * 1000; // 24 hours
            if (Date.now() - formData.timestamp > maxAge) {
                console.log('ESA Form Data: Data is too old, skipping restore');
                this.clearFormData();
                return;
            }

            // Restore input fields (by name or id)
            Object.keys(formData).forEach(key => {
                if (['calculatedValues', 'timestamp', 'pageUrl', 'fixtureTableHTML', 'fixtureBodyHTML',
                    'canvases', 'selectedSystems', 'globalSelectedSystem', 'globalSelectedOption',
                    'globalDayTankPumpType', 'pumpResultSimplex', 'pumpResultDuplex', 'pumpResultTriplex'].includes(key)) {
                    return; // Skip these, they're handled separately
                }

                if (key.startsWith('span_')) {
                    return; // Skip span content, handled separately
                }

                // Try to find element by name first, then by id
                let elements = document.querySelectorAll(`[name="${key}"]`);
                if (elements.length === 0) {
                    const element = document.getElementById(key);
                    if (element) {
                        elements = [element];
                    }
                }

                elements.forEach(element => {
                    if (element.type === 'radio') {
                        if (element.value === formData[key]) {
                            element.checked = true;
                        }
                    } else if (element.type === 'checkbox') {
                        element.checked = formData[key];
                    } else {
                        element.value = formData[key];
                    }
                });
            });

            // Restore calculated values
            if (formData.calculatedValues) {
                Object.keys(formData.calculatedValues).forEach(elementId => {
                    const element = document.getElementById(elementId);
                    if (element) {
                        element.innerHTML = formData.calculatedValues[elementId];
                    }
                });
            }

            // Restore fixture table
            if (formData.fixtureTableHTML) {
                const fixtureTable = document.getElementById('addedFixturesTable');
                if (fixtureTable) {
                    fixtureTable.innerHTML = formData.fixtureTableHTML;
                }
            }

            if (formData.fixtureBodyHTML) {
                const fixtureBody = document.getElementById('addedFixturesBody');
                if (fixtureBody) {
                    fixtureBody.innerHTML = formData.fixtureBodyHTML;
                }
            }

            // Restore canvases
            if (formData.canvases) {
                Object.keys(formData.canvases).forEach(canvasId => {
                    const canvas = document.getElementById(canvasId);
                    if (canvas) {
                        const ctx = canvas.getContext('2d');
                        const img = new Image();
                        img.onload = function () {
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(img, 0, 0);
                        };
                        img.src = formData.canvases[canvasId];
                    }
                });
            }

            // Restore selected systems/options
            if (formData.selectedSystems) {
                formData.selectedSystems.forEach(systemId => {
                    const element = document.getElementById(systemId);
                    if (element) {
                        element.classList.add('selected');
                    }
                });
            }

            // Restore pump results
            ['pumpResultSimplex', 'pumpResultDuplex', 'pumpResultTriplex'].forEach(id => {
                if (formData[id]) {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = formData[id];
                    }
                }
            });

            // Restore span content
            Object.keys(formData).forEach(key => {
                if (key.startsWith('span_')) {
                    const spanId = key.substring(5); // Remove 'span_' prefix
                    const span = document.getElementById(spanId);
                    if (span) {
                        span.textContent = formData[key];
                    }
                }
            });

            // Restore global variables
            if (formData.globalSelectedSystem !== undefined) {
                window.selectedSystem = formData.globalSelectedSystem;
            }
            if (formData.globalSelectedOption !== undefined) {
                window.selectedOption = formData.globalSelectedOption;
            }
            if (formData.globalDayTankPumpType !== undefined) {
                window.day_tank_pump_type = formData.globalDayTankPumpType;
            }

            // Trigger change events to update any dependent calculations
            document.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.value) {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            console.log('ESA Form Data: Restored complete form data', formData);

            // Show a subtle notification
            this.showRestoreNotification();

        } catch (error) {
            console.error('ESA Form Data: Error restoring form data', error);
        }
    }

    clearFormData() {
        try {
            if (!this.storage) {
                return;
            }
            this.storage.removeItem(this.storageKey);
            console.log('ESA Form Data: Cleared saved form data');
        } catch (error) {
            console.error('ESA Form Data: Error clearing form data', error);
        }
    }

    buildStorageKey() {
        try {
            const path = window.location && window.location.pathname ? window.location.pathname : 'esa';
            const normalizedPath = path.replace(/[^a-z0-9]/gi, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
            return `esa_form_data_${normalizedPath || 'root'}`;
        } catch (error) {
            return 'esa_form_data';
        }
    }

    getStorageProvider() {
        const attemptProvider = (providerFn) => {
            try {
                const storage = providerFn();
                if (!storage) return null;
                const testKey = '__esa_storage_test__';
                storage.setItem(testKey, '1');
                storage.removeItem(testKey);
                return storage;
            } catch (error) {
                return null;
            }
        };

        const sessionStore = attemptProvider(() => window.sessionStorage);
        if (sessionStore) {
            return sessionStore;
        }

        const localStore = attemptProvider(() => window.localStorage);
        if (localStore) {
            return localStore;
        }

        console.warn('ESA Form Data: No web storage available, using in-memory store.');
        const memoryStore = {};
        return {
            setItem: (key, value) => {
                memoryStore[key] = value;
            },
            getItem: (key) => memoryStore[key] || null,
            removeItem: (key) => {
                delete memoryStore[key];
            }
        };
    }

    debounce(fn, delay = 300) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    showRestoreNotification() {
        // Create a subtle notification
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
            animation: slideIn 0.3s ease-out;
        `;
        notification.textContent = '✓ Form data restored';

        // Add animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);

        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.remove();
            style.remove();
        }, 3000);
    }
}

// Initialize form persistence when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.esaFormPersistence === 'undefined') {
        window.esaFormPersistence = new ESAFormPersistence();
    }
});

// Export for use in other scripts
window.ESAFormPersistence = ESAFormPersistence;
