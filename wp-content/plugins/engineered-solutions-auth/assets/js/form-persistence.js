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

        // Save form data when login modal is shown
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('esa-login-icon') || 
                (e.target.classList.contains('esa-btn-primary') && 
                e.target.textContent.includes('Sign In')) ||
                e.target.textContent.includes('Login to Continue')) {
                this.saveCurrentFormData();
            }
        });
        
        // Save form data when registration modal is shown
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('esa-tab-btn') && 
                e.target.dataset.tab === 'register') {
                this.saveCurrentFormData();
            }
        });
        
        // Save form data before page unload (as backup)
        window.addEventListener('beforeunload', () => {
            this.saveCurrentFormData();
        });

        // Auto-save as fields change during the session
        ['input', 'change'].forEach(eventName => {
            document.addEventListener(eventName, () => {
                this.debouncedSave();
            }, true);
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
            
            // Save all input fields
            document.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.name && input.value && !input.type.includes('password')) {
                    formData[input.name] = input.value;
                }
            });
            
            // Save radio button selections
            document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                if (radio.name) {
                    formData[radio.name] = radio.value;
                }
            });
            
            // Save checkbox selections
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                if (checkbox.name) {
                    formData[checkbox.name] = checkbox.checked;
                }
            });
            
            // Save calculated values
            const calculatedValues = {};
            document.querySelectorAll('[id$="Result"]').forEach(element => {
                if (element.textContent || element.innerHTML) {
                    calculatedValues[element.id] = element.textContent || element.innerHTML;
                }
            });
            formData.calculatedValues = calculatedValues;
            
            // Save specific application fields
            const applicationFields = [
                'flow_rate_sdt', 'flow_rate_simplex', 'flow_rate_duplex',
                'head_sdt', 'head_simplex', 'head_duplex',
                'fixture_count', 'day_tank_capacity', 'storage_tank_capacity',
                'pump_type', 'system_type', 'application_type'
            ];
            
            applicationFields.forEach(fieldName => {
                const element = document.querySelector(`[name="${fieldName}"], #${fieldName}`);
                if (element && element.value) {
                    formData[fieldName] = element.value;
                }
            });
            
            // Save form state
            formData.timestamp = Date.now();
            formData.pageUrl = window.location.href;
            
            // Store in sessionStorage (per browser tab)
            if (!this.storage) {
                return;
            }
            this.storage.setItem(this.storageKey, JSON.stringify(formData));
            
            console.log('ESA Form Data: Saved form data', formData);
        } catch (error) {
            console.error('ESA Form Data: Error saving form data', error);
        }
    }
    
    restoreFormData() {
        try {
            if (!this.storage) return;

            const savedData = this.storage.getItem(this.storageKey);
            if (!savedData) return;
            
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
            
            // Restore input fields
            Object.keys(formData).forEach(key => {
                if (key === 'calculatedValues' || key === 'timestamp' || key === 'pageUrl') return;
                
                const elements = document.querySelectorAll(`[name="${key}"]`);
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
                        element.textContent = formData.calculatedValues[elementId];
                    }
                });
            }
            
            // Trigger change events to update any dependent calculations
            document.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.value) {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            
            console.log('ESA Form Data: Restored form data', formData);
            
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
