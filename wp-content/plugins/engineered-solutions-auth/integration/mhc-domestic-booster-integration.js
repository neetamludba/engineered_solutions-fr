/**
 * MHC Domestic Booster Sizing Integration
 * This code should be added to MHC/domestic_booster_sizing.html
 */

// Override the existing authentication variables and functions
document.addEventListener('DOMContentLoaded', function() {
    // Wait for ESA Auth to be available
    const checkESA = setInterval(() => {
        if (typeof window.esaAuth !== 'undefined') {
            clearInterval(checkESA);
            initializeMHCDomesticBoosterIntegration();
        }
    }, 100);
});

function initializeMHCDomesticBoosterIntegration() {
    // Override the existing user and approved variables
    Object.defineProperty(window, 'user', {
        get: () => window.esaAuth ? window.esaAuth.isLoggedIn : false,
        configurable: true
    });
    
    Object.defineProperty(window, 'approved', {
        get: () => window.esaAuth ? window.esaAuth.userApproved : false,
        configurable: true
    });
    
    // Override the existing initializeChartsForNonAuthenticatedUsers function
    if (typeof window.initializeChartsForNonAuthenticatedUsers !== 'undefined') {
        const originalFunction = window.initializeChartsForNonAuthenticatedUsers;
        
        window.initializeChartsForNonAuthenticatedUsers = function() {
            if (window.esaAuth && window.esaAuth.isLoggedIn && window.esaAuth.userApproved) {
                // User is authenticated and approved - show real charts
                showAuthenticatedCharts();
            } else {
                // User is not authenticated or not approved - show dummy charts
                showGuestCharts();
            }
        };
    }
    
    // Override the existing drawChart function
    if (typeof window.drawChart !== 'undefined') {
        const originalDrawChart = window.drawChart;
        
        window.drawChart = function(chartName, fr, h, chartConfig, divID, hAxisMax, vAxisMax) {
            // Check authentication before drawing chart
            if (!window.esaAuth || !window.esaAuth.isLoggedIn || !window.esaAuth.userApproved) {
                // Show dummy chart for non-authenticated users
                const graph = document.getElementById(divID);
                if (graph) {
                    const isLoggedIn = window.esaAuth ? window.esaAuth.isLoggedIn : false;
                    const isApproved = window.esaAuth ? window.esaAuth.userApproved : false;
                    
                    let message = '';
                    let buttonHtml = '';
                    
                    if (!isLoggedIn) {
                        // Guest user
                        message = `
                            <h3>Access Restricted</h3>
                            <p>Please login to view this pump performance chart</p>
                        `;
                        buttonHtml = `
                            <button class="esa-btn esa-btn-primary" onclick="window.esaAuth.showModal()">
                                Login to Continue
                            </button>
                        `;
                    } else if (!isApproved) {
                        // Logged in but not approved
                        message = `
                            <h3>Access Restricted</h3>
                            <p>Please wait for your approval to see graphs</p>
                        `;
                        buttonHtml = ''; // No button for pending approval
                    }
                    
                    graph.innerHTML = `
                        <div class="esa-guest-chart">
                            ${message}
                            ${buttonHtml}
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
    
    // Override the existing drawDummyChart function
    if (typeof window.drawDummyChart !== 'undefined') {
        const originalDrawDummyChart = window.drawDummyChart;
        
    window.drawDummyChart = function(divID) {
        const graph = document.getElementById(divID);
        if (graph) {
            // Check user status to show appropriate message
            const isLoggedIn = window.esaAuth ? window.esaAuth.isLoggedIn : false;
            const isApproved = window.esaAuth ? window.esaAuth.userApproved : false;
            
            let message = '';
            let buttonHtml = '';
            
            if (!isLoggedIn) {
                // Guest user
                message = `
                    <h3>Access Restricted</h3>
                    <p>Please login to view pump performance charts</p>
                `;
                buttonHtml = `
                    <button class="esa-btn esa-btn-primary" onclick="window.esaAuth.showModal()">
                        Login to Continue
                    </button>
                `;
            } else if (!isApproved) {
                // Logged in but not approved
                message = `
                    <h3>Access Restricted</h3>
                    <p>Please wait for your approval to see graphs</p>
                `;
                buttonHtml = ''; // No button for pending approval
            }
            
            graph.innerHTML = `
                <div class="esa-guest-chart">
                    ${message}
                    ${buttonHtml}
                </div>
            `;
            graph.parentElement.style.display = 'block';
            graph.style.display = "inline-block";
            
            // Hide buttons for dummy charts
            const container = graph.parentElement;
            const summaryOverlay = container.querySelector('.summary-overlay');
            const chartOverlay = container.querySelector('.chart-overlay');
            
            if (summaryOverlay) summaryOverlay.style.display = 'none';
            if (chartOverlay) chartOverlay.style.display = 'none';
        }
    };
    }
    
    // Override the updateSummaryPage function to save estimates
    if (typeof window.updateSummaryPage !== 'undefined') {
        const originalUpdateSummaryPage = window.updateSummaryPage;
        
        window.updateSummaryPage = function(type, modelId) {
            // Check if user is authenticated
            if (!window.esaAuth || !window.esaAuth.isLoggedIn) {
                window.esaAuth.showModal();
                return;
            }
            
            // Call original function
            const result = originalUpdateSummaryPage.call(this, type, modelId);
            
            // Save estimate request
            saveMHCDomesticBoosterEstimate(type, modelId);
            
            return result;
        };
    }
    
    // Override the pump type button function to check status on click
    setupPumpFunctionOverride('municipalLinePumpType');
    
    // Initialize the page
    initializeMHCDomesticBoosterPage();

    // Keep charts/buttons synced with authentication changes
    document.addEventListener('esaAuthState', handleMHCDomesticAuthState);
}

function handleMHCDomesticAuthState(event) {
    const state = event ? event.detail : null;
    refreshMHCDomesticView(state);
}

function refreshMHCDomesticView(state) {
    if (typeof window.initializeChartsForNonAuthenticatedUsers === 'function') {
        window.initializeChartsForNonAuthenticatedUsers();
        return;
    }

    if (state && state.isLoggedIn && state.userApproved) {
        showAuthenticatedCharts();
    } else {
        showGuestCharts();
    }
}

function showAuthenticatedCharts() {
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

function showGuestCharts() {
    // Hide all chart containers initially
    document.querySelectorAll('.chart-container').forEach(container => {
        container.style.display = 'none';
    });
    
    // Show only one dummy chart
    showDummyChart();
    
    // Add guest styling to buttons
    document.querySelectorAll('.summary-button, .pdf-button').forEach(button => {
        button.classList.add('esa-guest-button');
    });
}

function showDummyChart() {
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

function saveMHCDomesticBoosterEstimate(type, modelId) {
    // Collect form data
    const formData = collectMHCDomesticBoosterFormData();
    
    // Save estimate request
    if (window.esaAuth) {
        window.esaAuth.saveEstimateRequest('mhc_domestic_booster', modelId, formData);
    }
}

function collectMHCDomesticBoosterFormData() {
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
    
    // Collect fixtures data
    const addedFixtures = document.getElementById('addedFixturesTable');
    if (addedFixtures) {
        formData.addedFixtures = addedFixtures.innerHTML;
    }
    
    const fixtureResult = document.getElementById('fixtureResult');
    if (fixtureResult) {
        formData.fixtureResult = fixtureResult.innerHTML;
    }
    
    // Collect irrigation flow rate
    const totalIrrigationFlowRate = document.getElementById('total_irrigation_flow_rate');
    if (totalIrrigationFlowRate) {
        formData.totalIrrigationFlowRate = totalIrrigationFlowRate.value;
    }
    
    // Collect pump results
    const pumpResultSimplex = document.getElementById('pumpResultSimplex');
    const pumpResultDuplex = document.getElementById('pumpResultDuplex');
    const pumpResultTriplex = document.getElementById('pumpResultTriplex');
    
    if (pumpResultSimplex) formData.pumpResultSimplex = pumpResultSimplex.innerText;
    if (pumpResultDuplex) formData.pumpResultDuplex = pumpResultDuplex.innerText;
    if (pumpResultTriplex) formData.pumpResultTriplex = pumpResultTriplex.innerText;
    
    // Collect selected pump
    let selectedPump = '';
    if (document.getElementById('flow_rate_simplex').checked) {
        selectedPump = 'Simplex: ' + (pumpResultSimplex ? pumpResultSimplex.innerText : '') + ' GPM';
    } else if (document.getElementById('flow_rate_duplex').checked) {
        selectedPump = 'Duplex: ' + (pumpResultDuplex ? pumpResultDuplex.innerText : '') + ' GPM';
    } else if (document.getElementById('flow_rate_triplex').checked) {
        selectedPump = 'Triplex: ' + (pumpResultTriplex ? pumpResultTriplex.innerText : '') + ' GPM';
    }
    formData.selectedPump = selectedPump;
    
    return formData;
}

async function checkAndUpdateApprovalStatus() {
    try {
        if (!window.esaAuth || !window.esaAuth.checkApprovalStatus) {
            return;
        }
        
        // Call the approval status check method
        // This will update the local userApproved state if it changed (approved <-> denied)
        await window.esaAuth.checkApprovalStatus();
        
    } catch (error) {
        console.error('ESA MHC Domestic Booster: Error checking approval status:', error);
    }
}

function setupPumpFunctionOverride(funcName) {
    // Wait for the function to exist before overriding (functions may load after DOMContentLoaded)
    const checkFunction = setInterval(() => {
        if (typeof window[funcName] === 'function') {
            clearInterval(checkFunction);
            
            const originalFunc = window[funcName];
            window[funcName] = async function(...args) {
                console.log(`ESA MHC Domestic Booster: Intercepted ${funcName}`);
                
                // CRITICAL: Capture event synchronously before any async operations
                // The event object exists in onclick handler scope but may not be on window.event
                let capturedEvent = null;
                try {
                    // Try to access the implicit 'event' variable (available in onclick handlers)
                    // This needs to be in a try-catch because 'event' might not exist in strict mode
                    if (typeof event !== 'undefined' && event) {
                        capturedEvent = event;
                    }
                } catch (e) {
                    // event is not accessible
                }
                
                // Also check window.event and first argument as fallbacks
                if (!capturedEvent) {
                    if (typeof window.event !== 'undefined' && window.event) {
                        capturedEvent = window.event;
                    } else if (args[0] && typeof args[0].preventDefault === 'function') {
                        capturedEvent = args[0];
                    } else {
                        // Create a dummy event object if none exists (for nested functions that need it)
                        capturedEvent = {
                            preventDefault: function() {},
                            stopPropagation: function() {},
                            target: null,
                            currentTarget: null
                        };
                    }
                }
                
                // Store event globally so nested functions can access it via window.event
                // In non-strict mode, window.event should be accessible as 'event' in global scope
                const previousEvent = window.event;
                window.event = capturedEvent;
                
                // Always check status if logged in (to catch approvals AND revocations)
                // Must await to ensure status is updated before calling original function
                if (window.esaAuth && window.esaAuth.isLoggedIn) {
                    console.log('ESA MHC Domestic Booster: Verifying approval status...');
                    try {
                        await checkAndUpdateApprovalStatus();
                    } catch (err) {
                        console.error('ESA MHC Domestic Booster: Error in approval check:', err);
                    }
                }
                
                try {
                    // Call original function with all arguments
                    // The event should now be available via window.event for nested functions
                    // In non-strict mode, window.event should be accessible as 'event'
                    return originalFunc.apply(this, args);
                } finally {
                    // Restore previous event state
                    if (previousEvent !== undefined) {
                        window.event = previousEvent;
                    } else {
                        try {
                            delete window.event;
                        } catch (e) {
                            window.event = undefined;
                        }
                    }
                }
            };
        }
    }, 100);
    
    // Stop checking after 5 seconds to avoid infinite loop
    setTimeout(() => {
        clearInterval(checkFunction);
    }, 5000);
}

function initializeMHCDomesticBoosterPage() {
    // Initialize charts based on authentication status
    if (typeof window.initializeChartsForNonAuthenticatedUsers === 'function') {
        window.initializeChartsForNonAuthenticatedUsers();
    }
    
    // Add click handlers to buttons that require authentication
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('summary-button') || 
            e.target.classList.contains('pdf-button')) {
            
            if (!window.esaAuth || !window.esaAuth.isLoggedIn) {
                e.preventDefault();
                e.stopPropagation();
                window.esaAuth.showModal();
                return false;
            }
        }
    });
}

// Utility functions
window.esaRequiresAuth = function(callback) {
    if (window.esaAuth && window.esaAuth.isLoggedIn) {
        callback();
    } else {
        window.esaAuth.showModal();
    }
};

window.esaSaveEstimate = function(pageType, selectedModel, formData) {
    if (window.esaAuth) {
        window.esaAuth.saveEstimateRequest(pageType, selectedModel, formData);
    }
};

