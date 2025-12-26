/**
 * Page Integration Code for Engineered Solutions Authentication
 * This code should be added to each of your four pages
 */

// Page-specific integration functions
class ESAPageIntegration {
    constructor(pageType) {
        this.pageType = pageType; // 'domestic_booster', 'rainwater_harvesting', etc.
        this.authListenerRegistered = false;
        this.init();
    }

    init() {
        // Wait for ESA Auth to be available
        if (typeof window.esaAuth !== 'undefined') {
            this.setupPageIntegration();
        } else {
            // Wait for ESA Auth to load
            const checkESA = setInterval(() => {
                if (typeof window.esaAuth !== 'undefined') {
                    clearInterval(checkESA);
                    this.setupPageIntegration();
                }
            }, 100);
        }
    }

    setupPageIntegration() {
        // Override the existing user authentication variables
        this.setupAuthenticationVariables();

        // Override chart functions
        this.setupChartOverrides();

        // Override button functions
        this.setupButtonOverrides();

        // Setup estimate saving
        this.setupEstimateSaving();

        // React to auth state changes without requiring reload
        this.registerAuthStateListener();
    }

    setupAuthenticationVariables() {
        // Override the global user and approved variables
        Object.defineProperty(window, 'user', {
            get: () => window.esaAuth ? window.esaAuth.isLoggedIn : false,
            configurable: true
        });

        Object.defineProperty(window, 'approved', {
            get: () => window.esaAuth ? window.esaAuth.userApproved : false,
            configurable: true
        });
    }

    registerAuthStateListener() {
        if (this.authListenerRegistered) {
            return;
        }
        this.authListenerRegistered = true;

        document.addEventListener('esaAuthState', (event) => {
            if (typeof window.initializeChartsForNonAuthenticatedUsers === 'function') {
                window.initializeChartsForNonAuthenticatedUsers();
                return;
            }

            const state = event ? event.detail : null;
            if (state && state.isLoggedIn && state.userApproved) {
                this.showAuthenticatedCharts();
            } else {
                this.showGuestCharts();
            }
        });
    }

    setupChartOverrides() {
        // Override the existing chart initialization function
        if (typeof window.initializeChartsForNonAuthenticatedUsers !== 'undefined') {
            const originalFunction = window.initializeChartsForNonAuthenticatedUsers;

            window.initializeChartsForNonAuthenticatedUsers = async () => {
                // If user is logged in but not approved, check approval status first
                if (window.esaAuth && window.esaAuth.isLoggedIn && !window.esaAuth.userApproved) {
                    console.log('ESA Page: Checking approval status before showing charts...');
                    await this.checkAndUpdateApprovalStatus();
                }

                if (window.esaAuth && window.esaAuth.isLoggedIn && window.esaAuth.userApproved) {
                    // User is authenticated and approved - show real charts
                    this.showAuthenticatedCharts();
                } else {
                    // User is not authenticated or not approved - show dummy charts
                    this.showGuestCharts();
                }
            };
        }

        // Override the pump type button functions to check status on click
        const pumpFunctions = ['municipalLinePumpType', 'dayTankPumpType'];

        pumpFunctions.forEach(funcName => {
            if (typeof window[funcName] === 'function') {
                const originalFunc = window[funcName];
                window[funcName] = async (...args) => {
                    console.log(`ESA Page: Intercepted ${funcName}`);

                    // Always check status if logged in (to catch approvals AND revocations)
                    if (window.esaAuth && window.esaAuth.isLoggedIn) {
                        console.log('ESA Page: Verifying approval status...');
                        await this.checkAndUpdateApprovalStatus();
                    }

                    // Call original function (charts will update based on new status)
                    originalFunc.apply(window, args);
                };
            }
        });
    }

    async checkAndUpdateApprovalStatus() {
        try {
            if (!window.esaAuth || !window.esaAuth.checkApprovalStatus) {
                return;
            }

            // Call the approval status check method
            // This will now update the local userApproved state if it changed (approved <-> denied)
            await window.esaAuth.checkApprovalStatus();

        } catch (error) {
            console.error('ESA Page: Error checking approval status:', error);
        }
    }

    setupButtonOverrides() {
        // Override button click handlers
        document.addEventListener('click', (e) => {
            // Check if clicked element is a button that requires authentication
            if (e.target.classList.contains('summary-button') ||
                e.target.classList.contains('pdf-button') ||
                e.target.classList.contains('esa-requires-auth')) {

                if (!window.esaAuth || !window.esaAuth.isLoggedIn) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.esaAuth.showModal();
                    return false;
                }
            }
        });
    }

    setupEstimateSaving() {
        // Override the updateSummaryPage function to save estimates
        if (typeof window.updateSummaryPage !== 'undefined') {
            const originalFunction = window.updateSummaryPage;

            window.updateSummaryPage = (type, modelId) => {
                // Call original function first
                const result = originalFunction.call(this, type, modelId);

                // Save estimate request
                if (window.esaAuth && window.esaAuth.isLoggedIn) {
                    this.saveEstimateRequest(type, modelId);
                }

                return result;
            };
        }
    }

    showAuthenticatedCharts() {
        // Show all chart containers
        document.querySelectorAll('.chart-container').forEach(container => {
            container.style.display = 'block';
        });

        // Show all buttons
        document.querySelectorAll('.summary-overlay, .chart-overlay').forEach(overlay => {
            overlay.style.display = 'block';
        });

        // Remove guest styling
        document.querySelectorAll('.esa-guest-button').forEach(button => {
            button.classList.remove('esa-guest-button');
        });
    }

    showGuestCharts() {
        // Hide all chart containers initially
        document.querySelectorAll('.chart-container').forEach(container => {
            container.style.display = 'none';
        });

        // Show only one dummy chart
        this.showDummyChart();

        // Add guest styling to buttons
        document.querySelectorAll('.summary-button, .pdf-button').forEach(button => {
            button.classList.add('esa-guest-button');
        });
    }

    showDummyChart() {
        // Find the first chart container and show dummy chart
        const firstContainer = document.querySelector('.chart-container');
        if (firstContainer) {
            const chartDiv = firstContainer.querySelector('.shown_graph');
            if (chartDiv) {
                chartDiv.innerHTML = `
                    <div class="esa-guest-chart">
                        <h3>Access Restricted</h3>
                        <p>Please login to view pump performance charts</p>
                        <p>Guest users can see only a preview</p>
                        <button class="esa-btn esa-btn-primary" onclick="window.esaAuth.showModal()">
                            Login to Continue
                        </button>
                    </div>
                `;
                firstContainer.style.display = 'block';
            }
        }
    }

    saveEstimateRequest(type, modelId) {
        // Collect form data
        const formData = this.collectFormData();

        // Save estimate request
        if (window.esaAuth) {
            window.esaAuth.saveEstimateRequest(this.pageType, modelId, formData);
        }
    }

    collectFormData() {
        const formData = {};

        // Collect all form inputs
        document.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.name && input.value) {
                formData[input.name] = input.value;
            }
        });

        // Collect calculated values
        const calculatedValues = {};
        document.querySelectorAll('[id$="Result"]').forEach(element => {
            calculatedValues[element.id] = element.textContent || element.innerHTML;
        });

        formData.calculatedValues = calculatedValues;

        // Collect selected pump type
        const selectedPumpType = document.querySelector('input[name*="pump_type"]:checked');
        if (selectedPumpType) {
            formData.selectedPumpType = selectedPumpType.value;
        }

        return formData;
    }
}

// Initialize for each page type
const pageType = window.location.pathname.includes('domestic_booster') ? 'domestic_booster' :
    window.location.pathname.includes('rainwater_harvesting') ? 'rainwater_harvesting' :
        window.location.pathname.includes('municipal_line') ? 'municipal_line' :
            'unknown';

// Initialize page integration
document.addEventListener('DOMContentLoaded', () => {
    window.esaPageIntegration = new ESAPageIntegration(pageType);
});

// Utility functions for pages
window.esaRequiresAuth = function (callback) {
    if (window.esaAuth && window.esaAuth.isLoggedIn) {
        callback();
    } else {
        window.esaAuth.showModal();
    }
};

window.esaSaveEstimate = function (pageType, selectedModel, formData) {
    if (window.esaAuth) {
        window.esaAuth.saveEstimateRequest(pageType, selectedModel, formData);
    }
};

// Override existing functions to work with authentication
if (typeof window.drawChart !== 'undefined') {
    const originalDrawChart = window.drawChart;

    window.drawChart = function (chartName, fr, h, chartConfig, divID, hAxisMax, vAxisMax) {
        // Check authentication before drawing chart
        if (!window.esaAuth || !window.esaAuth.isLoggedIn || !window.esaAuth.userApproved) {
            // Show dummy chart for non-authenticated users
            const graph = document.getElementById(divID);
            if (graph) {
                graph.innerHTML = `
                    <div class="esa-guest-chart">
                        <h3>Access Restricted</h3>
                        <p>Please login to view this chart</p>
                        <button class="esa-btn esa-btn-primary" onclick="window.esaAuth.showModal()">
                            Login to Continue
                        </button>
                    </div>
                `;
                graph.parentElement.style.display = 'block';
                graph.style.display = "inline-block";

                // Hide buttons
                const container = graph.parentElement;
                const summaryOverlay = container.querySelector('.summary-overlay');
                const chartOverlay = container.querySelector('.chart-overlay');

                if (summaryOverlay) summaryOverlay.style.display = 'none';
                if (chartOverlay) chartOverlay.style.display = 'none';
            }
            return;
        }

        // Call original function for authenticated users
        originalDrawChart.call(this, chartName, fr, h, chartConfig, divID, hAxisMax, vAxisMax);
    };
}

// Override updateSummaryPage to include authentication check
if (typeof window.updateSummaryPage !== 'undefined') {
    const originalUpdateSummaryPage = window.updateSummaryPage;

    window.updateSummaryPage = function (type, modelId) {
        // Check if user is authenticated
        if (!window.esaAuth || !window.esaAuth.isLoggedIn) {
            window.esaAuth.showModal();
            return;
        }

        // Call original function
        const result = originalUpdateSummaryPage.call(this, type, modelId);

        // Save estimate request
        if (window.esaPageIntegration) {
            window.esaPageIntegration.saveEstimateRequest(type, modelId);
        }

        return result;
    };
}
