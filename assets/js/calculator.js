/**
 * Wright Courier Calculator - Shortcode JavaScript
 * Isolated and theme-conflict-free implementation
 */

(function($) {
    'use strict';
    
    // Main calculator object with complete isolation
    const WWCCalculator = {
        
        // Configuration from localized data
        config: {
            testMode: window.wwcCalculator?.testMode === 'yes',
            apiKey: window.wwcCalculator?.googleApiKey || '',
            restUrl: window.wwcCalculator?.resturl || '/wp-json/wright/v1/',
            nonce: window.wwcCalculator?.nonce || '',
            ajaxUrl: window.wwcCalculator?.ajaxurl || '/wp-admin/admin-ajax.php',
            i18n: window.wwcCalculator?.i18n || {},
            pluginUrl: window.wwcCalculator?.pluginUrl || ''
        },
        
        // State management per calculator instance
        instances: new Map(),
        
        // Initialize all calculator instances on the page
        init: function() {
            const containers = document.querySelectorAll('.wwc-calculator-container');
            
            containers.forEach((container, index) => {
                this.initInstance(container, index);
            });
            
            console.log(`Wright Courier Calculator initialized ${containers.length} instances`, {
                testMode: this.config.testMode,
                hasApiKey: !!this.config.apiKey
            });
        },
        
        // Initialize a single calculator instance
        initInstance: function(container, index) {
            const instanceId = `wwc-instance-${index}`;
            const productId = container.dataset.productId || '177';
            
            // Create instance state
            const instance = {
                id: instanceId,
                container: container,
                productId: productId,
                elements: {},
                state: {
                    pickupPlaceId: null,
                    dropoffPlaceId: null,
                    currentQuote: null,
                    isCalculating: false,
                    autocompletePickup: null,
                    autocompleteDropoff: null
                }
            };
            
            // Cache elements with proper scoping
            this.cacheElements(instance);
            
            // Bind events for this instance
            this.bindEvents(instance);
            
            // Initialize autocomplete for this instance
            this.initAutocomplete(instance);
            
            // Update calculate button state
            this.updateCalculateButton(instance);
            
            // Store instance
            this.instances.set(instanceId, instance);
        },
        
        // Cache DOM elements for a specific instance
        cacheElements: function(instance) {
            const container = instance.container;
            
            instance.elements = {
                form: container.querySelector('#wwc-quote-form'),
                pickupInput: container.querySelector('#wwc_pickup'),
                dropoffInput: container.querySelector('#wwc_dropoff'),
                pickupPlaceIdInput: container.querySelector('#wwc_pickup_place_id'),
                dropoffPlaceIdInput: container.querySelector('#wwc_dropoff_place_id'),
                calculateBtn: container.querySelector('#wwc-calculate-btn'),
                calculateBtnText: container.querySelector('#wwc-calculate-btn .button-text'),
                calculateBtnLoading: container.querySelector('#wwc-calculate-btn .button-loading'),
                results: container.querySelector('#wwc-results'),
                error: container.querySelector('#wwc-error'),
                errorMessage: container.querySelector('#wwc-error .error-message'),
                retryBtn: container.querySelector('.wwc-retry-button'),
                totalAmount: container.querySelector('#wwc-total-amount'),
                breakdownContent: container.querySelector('#wwc-breakdown-content'),
                addToCartBtn: container.querySelector('#wwc-add-to-cart'),
                quoteDataInput: container.querySelector('#wwc-quote-data'),
                productIdInput: container.querySelector('#wwc-product-id'),
                nonceInput: container.querySelector('#wwc-nonce'),
                tierInputs: container.querySelectorAll('input[name="tier"]'),
                addonInputs: container.querySelectorAll('input[name="addons[]"]')
            };
        },
        
        // Bind event handlers for a specific instance
        bindEvents: function(instance) {
            const elements = instance.elements;
            
            // Form submission
            if (elements.form) {
                elements.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.calculateQuote(instance);
                });
            }
            
            // Address input changes
            if (elements.pickupInput) {
                elements.pickupInput.addEventListener('input', () => {
                    instance.state.pickupPlaceId = null;
                    if (elements.pickupPlaceIdInput) {
                        elements.pickupPlaceIdInput.value = '';
                    }
                    this.updateCalculateButton(instance);
                });
            }
            
            if (elements.dropoffInput) {
                elements.dropoffInput.addEventListener('input', () => {
                    instance.state.dropoffPlaceId = null;
                    if (elements.dropoffPlaceIdInput) {
                        elements.dropoffPlaceIdInput.value = '';
                    }
                    this.updateCalculateButton(instance);
                });
            }
            
            // Tier and addon changes
            elements.tierInputs.forEach(input => {
                input.addEventListener('change', () => {
                    if (instance.state.currentQuote) {
                        this.calculateQuote(instance);
                    }
                });
            });
            
            elements.addonInputs.forEach(input => {
                input.addEventListener('change', () => {
                    if (instance.state.currentQuote) {
                        this.calculateQuote(instance);
                    }
                });
            });
            
            // Add to cart
            if (elements.addToCartBtn) {
                elements.addToCartBtn.addEventListener('click', () => {
                    this.addToCart(instance);
                });
            }
            
            // Retry button
            if (elements.retryBtn) {
                elements.retryBtn.addEventListener('click', () => {
                    this.hideError(instance);
                    this.calculateQuote(instance);
                });
            }
        },
        
        // Initialize autocomplete for specific instance
        initAutocomplete: function(instance) {
            // Skip if in test mode or no API key
            if (this.config.testMode || !this.config.apiKey) {
                this.initTestModeAutocomplete(instance);
                return;
            }
            
            // Wait for Google Maps API
            if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
                console.log('Google Maps API not loaded, using test mode');
                this.initTestModeAutocomplete(instance);
                return;
            }
            
            this.initGoogleAutocomplete(instance);
        },
        
        // Initialize Google Places autocomplete
        initGoogleAutocomplete: function(instance) {
            const elements = instance.elements;
            
            const autocompleteOptions = {
                types: ['address'],
                componentRestrictions: { country: 'us' },
                fields: ['place_id', 'formatted_address', 'geometry']
            };
            
            // Initialize pickup autocomplete
            if (elements.pickupInput) {
                instance.state.autocompletePickup = new google.maps.places.Autocomplete(
                    elements.pickupInput,
                    autocompleteOptions
                );
                
                instance.state.autocompletePickup.addListener('place_changed', () => {
                    this.handlePlaceChanged(instance, 'pickup');
                });
            }
            
            // Initialize dropoff autocomplete  
            if (elements.dropoffInput) {
                instance.state.autocompleteDropoff = new google.maps.places.Autocomplete(
                    elements.dropoffInput,
                    autocompleteOptions
                );
                
                instance.state.autocompleteDropoff.addListener('place_changed', () => {
                    this.handlePlaceChanged(instance, 'dropoff');
                });
            }
        },
        
        // Initialize test mode autocomplete
        initTestModeAutocomplete: function(instance) {
            const elements = instance.elements;
            
            console.log('Initializing test mode autocomplete for instance:', instance.id);
            
            // In test mode, we don't need place IDs for validation
            // The calculate button validation and form submission will handle it
            
            // Optional: Add visual feedback for test mode
            if (elements.pickupInput) {
                elements.pickupInput.setAttribute('placeholder', 
                    elements.pickupInput.getAttribute('placeholder') + ' (Test Mode)');
            }
            
            if (elements.dropoffInput) {
                elements.dropoffInput.setAttribute('placeholder', 
                    elements.dropoffInput.getAttribute('placeholder') + ' (Test Mode)');
            }
        },
        
        // Handle place selection from Google autocomplete
        handlePlaceChanged: function(instance, type) {
            const autocomplete = type === 'pickup' 
                ? instance.state.autocompletePickup 
                : instance.state.autocompleteDropoff;
                
            const place = autocomplete.getPlace();
            const elements = instance.elements;
            
            if (!place.place_id) {
                console.warn(`${type} place selection invalid`);
                return;
            }
            
            if (type === 'pickup') {
                instance.state.pickupPlaceId = place.place_id;
                if (elements.pickupPlaceIdInput) {
                    elements.pickupPlaceIdInput.value = place.place_id;
                }
                if (elements.pickupInput) {
                    elements.pickupInput.value = place.formatted_address;
                }
            } else {
                instance.state.dropoffPlaceId = place.place_id;
                if (elements.dropoffPlaceIdInput) {
                    elements.dropoffPlaceIdInput.value = place.place_id;
                }
                if (elements.dropoffInput) {
                    elements.dropoffInput.value = place.formatted_address;
                }
            }
            
            this.updateCalculateButton(instance);
            
            // Auto-calculate if both addresses are set
            if (instance.state.pickupPlaceId && instance.state.dropoffPlaceId) {
                setTimeout(() => this.calculateQuote(instance), 500);
            }
        },
        
        // Update calculate button state for instance
        updateCalculateButton: function(instance) {
            const elements = instance.elements;
            
            // In test mode, just check if addresses have minimum length
            if (this.config.testMode) {
                const hasPickup = elements.pickupInput && elements.pickupInput.value.trim().length >= 3;
                const hasDropoff = elements.dropoffInput && elements.dropoffInput.value.trim().length >= 3;
                const canCalculate = hasPickup && hasDropoff && !instance.state.isCalculating;
                
                if (elements.calculateBtn) {
                    elements.calculateBtn.disabled = !canCalculate;
                }
                return;
            }
            
            // In production mode, check for place IDs
            const hasPickup = instance.state.pickupPlaceId || 
                (elements.pickupInput && elements.pickupInput.value.length >= 10);
            const hasDropoff = instance.state.dropoffPlaceId || 
                (elements.dropoffInput && elements.dropoffInput.value.length >= 10);
            const canCalculate = hasPickup && hasDropoff && !instance.state.isCalculating;
            
            if (elements.calculateBtn) {
                elements.calculateBtn.disabled = !canCalculate;
            }
        },
        
        // Calculate quote for specific instance
        calculateQuote: function(instance) {
            if (instance.state.isCalculating) return;
            
            instance.state.isCalculating = true;
            this.showLoading(instance);
            this.hideError(instance);
            this.hideResults(instance);
            
            // Collect form data
            const formData = this.collectFormData(instance);
            
            // Validate form data
            if (!this.validateFormData(instance, formData)) {
                instance.state.isCalculating = false;
                this.hideLoading(instance);
                return;
            }
            
            // Make API request using fetch for better compatibility
            const headers = {
                'Content-Type': 'application/json'
            };
            
            // Only include nonce header in production mode, skip in test mode for public endpoint
            if (!this.config.testMode && this.config.nonce) {
                headers['X-WP-Nonce'] = this.config.nonce;
            }
            
            fetch(this.config.restUrl + 'quote', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    this.handleQuoteSuccess(instance, data);
                } else {
                    this.handleQuoteError(instance, { responseJSON: data });
                }
            })
            .catch(error => {
                console.error('Quote calculation failed:', error);
                this.handleQuoteError(instance, {});
            })
            .finally(() => {
                instance.state.isCalculating = false;
                this.hideLoading(instance);
                this.updateCalculateButton(instance);
            });
        },
        
        // Collect form data for quote request
        collectFormData: function(instance) {
            const elements = instance.elements;
            
            // Get pickup and dropoff addresses
            const pickup = elements.pickupInput ? elements.pickupInput.value.trim() : '';
            const dropoff = elements.dropoffInput ? elements.dropoffInput.value.trim() : '';
            
            let pickupPlaceId = elements.pickupPlaceIdInput ? elements.pickupPlaceIdInput.value : '';
            let dropoffPlaceId = elements.dropoffPlaceIdInput ? elements.dropoffPlaceIdInput.value : '';
            
            // In test mode or when place IDs are missing, generate them
            if ((!pickupPlaceId && pickup) || this.config.testMode) {
                pickupPlaceId = 'test_pickup_' + this.hashCode(pickup);
                if (elements.pickupPlaceIdInput) {
                    elements.pickupPlaceIdInput.value = pickupPlaceId;
                }
                // Update instance state
                instance.state.pickupPlaceId = pickupPlaceId;
            }
            
            if ((!dropoffPlaceId && dropoff) || this.config.testMode) {
                dropoffPlaceId = 'test_dropoff_' + this.hashCode(dropoff);
                if (elements.dropoffPlaceIdInput) {
                    elements.dropoffPlaceIdInput.value = dropoffPlaceId;
                }
                // Update instance state
                instance.state.dropoffPlaceId = dropoffPlaceId;
            }
            
            // Get selected tier
            let selectedTier = 'standard';
            elements.tierInputs.forEach(input => {
                if (input.checked) {
                    selectedTier = input.value;
                }
            });
            
            // Get selected addons
            const selectedAddons = [];
            elements.addonInputs.forEach(input => {
                if (input.checked) {
                    selectedAddons.push(input.value);
                }
            });
            
            return {
                pickup: {
                    place_id: pickupPlaceId,
                    label: pickup
                },
                dropoff: {
                    place_id: dropoffPlaceId,
                    label: dropoff
                },
                tier: selectedTier,
                addons: selectedAddons
            };
        },
        
        // Validate form data before sending
        validateFormData: function(instance, data) {
            if (!data.pickup.label || data.pickup.label.trim().length < 5) {
                this.showError(instance, this.config.i18n.invalidAddress || 'Please enter a valid pickup address.');
                return false;
            }
            
            if (!data.dropoff.label || data.dropoff.label.trim().length < 5) {
                this.showError(instance, this.config.i18n.invalidAddress || 'Please enter a valid drop-off address.');
                return false;
            }
            
            if (data.pickup.label.trim().toLowerCase() === data.dropoff.label.trim().toLowerCase()) {
                this.showError(instance, 'Pickup and drop-off addresses must be different.');
                return false;
            }
            
            // In production mode, also check for place IDs
            if (!this.config.testMode) {
                if (!data.pickup.place_id) {
                    this.showError(instance, 'Please select a pickup address from the suggestions.');
                    return false;
                }
                
                if (!data.dropoff.place_id) {
                    this.showError(instance, 'Please select a drop-off address from the suggestions.');
                    return false;
                }
            }
            
            return true;
        },
        
        // Handle successful quote response
        handleQuoteSuccess: function(instance, response) {
            console.log('Quote calculated successfully', response);
            
            instance.state.currentQuote = response;
            const elements = instance.elements;
            
            // Update price display
            if (elements.totalAmount && response.pricing) {
                elements.totalAmount.textContent = '$' + response.pricing.total.toFixed(2);
            }
            
            // Update breakdown
            if (elements.breakdownContent && response.breakdown_html) {
                elements.breakdownContent.innerHTML = response.breakdown_html;
            }
            
            // Store quote data for cart
            if (elements.quoteDataInput && response.quote_data) {
                elements.quoteDataInput.value = JSON.stringify({
                    ...response.quote_data,
                    pricing: response.pricing,
                    miles: response.miles
                });
            }
            
            // Show results with animation
            this.showResults(instance);
        },
        
        // Handle quote calculation error
        handleQuoteError: function(instance, xhr) {
            console.error('Quote calculation failed', xhr);
            
            let message = this.config.i18n.error || 'Error calculating price. Please try again.';
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.code) {
                switch (xhr.responseJSON.code) {
                    case 'out_of_radius':
                        message = this.config.i18n.outOfRadius || message;
                        break;
                    case 'invalid_data':
                        message = this.config.i18n.invalidAddress || message;
                        break;
                    case 'calculation_error':
                    case 'api_error':
                        message = this.config.i18n.apiError || message;
                        break;
                }
            }
            
            this.showError(instance, message);
        },
        
        // Add calculated quote to cart
        addToCart: function(instance) {
            if (!instance.state.currentQuote) {
                this.showError(instance, 'Please calculate a quote first.');
                return;
            }
            
            const elements = instance.elements;
            const quoteData = elements.quoteDataInput ? elements.quoteDataInput.value : '';
            
            if (!quoteData) {
                this.showError(instance, 'Quote data is missing. Please recalculate.');
                return;
            }
            
            // Get product ID
            const productId = elements.productIdInput ? elements.productIdInput.value : instance.productId;
            const nonce = elements.nonceInput ? elements.nonceInput.value : this.config.nonce;
            
            if (!productId) {
                this.showError(instance, 'Product ID not found.');
                return;
            }
            
            // Disable add to cart button
            if (elements.addToCartBtn) {
                elements.addToCartBtn.disabled = true;
                elements.addToCartBtn.textContent = this.config.i18n.addingToCart || 'Adding to Cart...';
            }
            
            // Prepare cart data
            const formData = new FormData();
            formData.append('action', 'woocommerce_add_to_cart');
            formData.append('product_id', productId);
            formData.append('wwc_quote_data', quoteData);
            formData.append('wwc_nonce', nonce);
            
            // Add to cart via AJAX
            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data && data.trim() !== '') {
                    // Redirect to cart or provided URL
                    window.location.href = data;
                } else {
                    // Reload page to show cart update
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Add to cart failed:', error);
                this.showError(instance, 'Failed to add to cart. Please try again.');
                
                if (elements.addToCartBtn) {
                    elements.addToCartBtn.disabled = false;
                    elements.addToCartBtn.textContent = this.config.i18n.addToCart || 'Add to Cart';
                }
            });
        },
        
        // UI helper methods for specific instance
        showLoading: function(instance) {
            const elements = instance.elements;
            if (elements.calculateBtn) {
                elements.calculateBtn.classList.add('wwc-loading');
            }
            if (elements.calculateBtnText) {
                elements.calculateBtnText.style.display = 'none';
            }
            if (elements.calculateBtnLoading) {
                elements.calculateBtnLoading.style.display = 'inline-block';
            }
        },
        
        hideLoading: function(instance) {
            const elements = instance.elements;
            if (elements.calculateBtn) {
                elements.calculateBtn.classList.remove('wwc-loading');
            }
            if (elements.calculateBtnText) {
                elements.calculateBtnText.style.display = 'inline';
            }
            if (elements.calculateBtnLoading) {
                elements.calculateBtnLoading.style.display = 'none';
            }
        },
        
        showResults: function(instance) {
            const elements = instance.elements;
            if (elements.error) {
                elements.error.style.display = 'none';
            }
            if (elements.results) {
                elements.results.classList.add('wwc-fade-in');
                elements.results.style.display = 'block';
            }
        },
        
        hideResults: function(instance) {
            const elements = instance.elements;
            if (elements.results) {
                elements.results.style.display = 'none';
                elements.results.classList.remove('wwc-fade-in');
            }
        },
        
        showError: function(instance, message) {
            const elements = instance.elements;
            if (elements.errorMessage) {
                elements.errorMessage.textContent = message;
            }
            if (elements.results) {
                elements.results.style.display = 'none';
            }
            if (elements.error) {
                elements.error.classList.add('wwc-fade-in');
                elements.error.style.display = 'block';
            }
        },
        
        hideError: function(instance) {
            const elements = instance.elements;
            if (elements.error) {
                elements.error.style.display = 'none';
                elements.error.classList.remove('wwc-fade-in');
            }
        },
        
        // Utility method to generate hash for test mode
        hashCode: function(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32-bit integer
            }
            return Math.abs(hash);
        }
    };
    
    // Global callback for Google Maps API
    window.wwcInitGoogleMaps = function() {
        console.log('Google Maps API loaded, initializing calculators');
        WWCCalculator.init();
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            WWCCalculator.init();
        });
    } else {
        // DOM is already ready
        WWCCalculator.init();
    }
    
    // Expose calculator object for debugging
    window.WWCCalculator = WWCCalculator;
    
    // jQuery compatibility wrapper
    if (typeof $ !== 'undefined') {
        $(document).ready(function() {
            // Re-initialize if jQuery modifies the DOM
            WWCCalculator.init();
        });
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : null);