/**
 * Form Data Persistence System
 * Saves and restores form data when users login/register
 */

class ESAFormPersistence {
    constructor() {
        this.storageKey = 'esa_form_data';
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
            
            // Store in localStorage
            localStorage.setItem(this.storageKey, JSON.stringify(formData));
            
            console.log('ESA Form Data: Saved form data', formData);
        } catch (error) {
            console.error('ESA Form Data: Error saving form data', error);
        }
    }
    
    restoreFormData() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return;
            
            const formData = JSON.parse(savedData);
            
            // Check if data is for current page
            if (formData.pageUrl !== window.location.href) {
                console.log('ESA Form Data: Data is for different page, skipping restore');
                return;
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
            localStorage.removeItem(this.storageKey);
            console.log('ESA Form Data: Cleared saved form data');
        } catch (error) {
            console.error('ESA Form Data: Error clearing form data', error);
        }
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
