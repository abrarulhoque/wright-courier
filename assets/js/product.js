/**
 * Wright Courier Calculator - Frontend JavaScript
 * Handles address autocomplete, quote calculation, and cart integration
 */

(function($) {
    'use strict';
    
    // Main calculator object
    const WWCCalculator = {
        
        // Configuration
        config: {
            testMode: wwcAjax.testMode === 'yes',
            apiKey: wwcAjax.googleApiKey,
            restUrl: wwcAjax.resturl,
            nonce: wwcAjax.nonce,
            i18n: wwcAjax.i18n
        },
        
        // State management
        state: {
            pickupPlaceId: null,
            dropoffPlaceId: null,
            currentQuote: null,
            isCalculating: false,
            autocompletePickup: null,
            autocompleteDropoff: null
        },
        
        // DOM elements
        elements: {},
        
        // Initialize the calculator
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initAutocomplete();
            this.updateCalculateButton();
            
            console.log('Wright Courier Calculator initialized', {
                testMode: this.config.testMode,
                hasApiKey: !!this.config.apiKey
            });
        },
        
        // Cache DOM elements
        cacheElements: function() {
            this.elements = {
                form: $('#wwc-quote-form'),
                pickupInput: $('#wwc_pickup'),
                dropoffInput: $('#wwc_dropoff'),
                pickupPlaceIdInput: $('#wwc_pickup_place_id'),
                dropoffPlaceIdInput: $('#wwc_dropoff_place_id'),
                calculateBtn: $('#wwc-calculate-btn'),
                calculateBtnText: $('#wwc-calculate-btn .button-text'),
                calculateBtnLoading: $('#wwc-calculate-btn .button-loading'),
                results: $('#wwc-results'),
                error: $('#wwc-error'),
                errorMessage: $('#wwc-error .error-message'),
                retryBtn: $('.wwc-retry-button'),
                totalAmount: $('#wwc-total-amount'),
                breakdownContent: $('#wwc-breakdown-content'),
                addToCartBtn: $('#wwc-add-to-cart'),
                quoteDataInput: $('#wwc-quote-data'),
                tierInputs: $('input[name="tier"]'),
                addonInputs: $('input[name="addons[]"]')
            };
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Form submission
            this.elements.form.on('submit', (e) => {
                e.preventDefault();
                this.calculateQuote();
            });
            
            // Address input changes
            this.elements.pickupInput.on('input', () => {
                this.state.pickupPlaceId = null;
                this.elements.pickupPlaceIdInput.val('');
                this.updateCalculateButton();
            });
            
            this.elements.dropoffInput.on('input', () => {
                this.state.dropoffPlaceId = null;
                this.elements.dropoffPlaceIdInput.val('');
                this.updateCalculateButton();
            });
            
            // Tier and addon changes
            this.elements.tierInputs.on('change', () => {
                if (this.state.currentQuote) {
                    this.calculateQuote();
                }
            });
            
            this.elements.addonInputs.on('change', () => {
                if (this.state.currentQuote) {
                    this.calculateQuote();
                }
            });
            
            // Add to cart
            this.elements.addToCartBtn.on('click', () => {
                this.addToCart();
            });
            
            // Retry button
            this.elements.retryBtn.on('click', () => {
                this.hideError();
                this.calculateQuote();
            });
        },
        
        // Initialize Google Places Autocomplete
        initAutocomplete: function() {
            // Skip if in test mode or no API key
            if (this.config.testMode || !this.config.apiKey) {
                this.initTestModeAutocomplete();
                return;
            }
            
            // Wait for Google Maps API to load
            if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
                console.log('Google Maps API not loaded, using test mode');
                this.initTestModeAutocomplete();
                return;
            }
            
            // Configure autocomplete options
            const autocompleteOptions = {
                types: ['address'],
                componentRestrictions: { country: 'us' },
                fields: ['place_id', 'formatted_address', 'geometry']
            };
            
            // Initialize pickup autocomplete
            this.state.autocompletePickup = new google.maps.places.Autocomplete(
                this.elements.pickupInput[0],
                autocompleteOptions
            );
            
            this.state.autocompletePickup.addListener('place_changed', () => {
                this.handlePlaceChanged('pickup');
            });
            
            // Initialize dropoff autocomplete
            this.state.autocompleteDropoff = new google.maps.places.Autocomplete(
                this.elements.dropoffInput[0],
                autocompleteOptions
            );
            
            this.state.autocompleteDropoff.addListener('place_changed', () => {
                this.handlePlaceChanged('dropoff');
            });
        },
        
        // Initialize test mode autocomplete (simple validation)
        initTestModeAutocomplete: function() {
            console.log('Initializing test mode autocomplete');
            
            // Simple validation for test mode
            this.elements.pickupInput.on('blur', () => {
                const value = this.elements.pickupInput.val().trim();
                if (value.length >= 10) { // Minimum viable address length
                    this.state.pickupPlaceId = 'test_pickup_' + this.hashCode(value);
                    this.elements.pickupPlaceIdInput.val(this.state.pickupPlaceId);
                    this.updateCalculateButton();
                }
            });
            
            this.elements.dropoffInput.on('blur', () => {
                const value = this.elements.dropoffInput.val().trim();
                if (value.length >= 10) { // Minimum viable address length
                    this.state.dropoffPlaceId = 'test_dropoff_' + this.hashCode(value);
                    this.elements.dropoffPlaceIdInput.val(this.state.dropoffPlaceId);
                    this.updateCalculateButton();
                }
            });
        },
        
        // Handle place selection from autocomplete
        handlePlaceChanged: function(type) {
            const autocomplete = type === 'pickup' ? this.state.autocompletePickup : this.state.autocompleteDropoff;
            const place = autocomplete.getPlace();
            
            if (!place.place_id) {
                console.warn(`${type} place selection invalid`);
                return;
            }
            
            if (type === 'pickup') {
                this.state.pickupPlaceId = place.place_id;
                this.elements.pickupPlaceIdInput.val(place.place_id);
                this.elements.pickupInput.val(place.formatted_address);
            } else {
                this.state.dropoffPlaceId = place.place_id;
                this.elements.dropoffPlaceIdInput.val(place.place_id);
                this.elements.dropoffInput.val(place.formatted_address);
            }
            
            this.updateCalculateButton();
            
            // Auto-calculate if both addresses are set
            if (this.state.pickupPlaceId && this.state.dropoffPlaceId) {
                setTimeout(() => this.calculateQuote(), 500);
            }
        },
        
        // Update calculate button state
        updateCalculateButton: function() {
            const hasPickup = this.state.pickupPlaceId || this.elements.pickupInput.val().length >= 10;
            const hasDropoff = this.state.dropoffPlaceId || this.elements.dropoffInput.val().length >= 10;
            const canCalculate = hasPickup && hasDropoff && !this.state.isCalculating;
            
            this.elements.calculateBtn.prop('disabled', !canCalculate);
            
            if (canCalculate && this.config.testMode) {
                this.elements.calculateBtn.removeClass('wwc-loading');
                this.elements.calculateBtnText.text(this.config.i18n.calculating || 'Calculate Price');
            }
        },
        
        // Calculate quote via REST API
        calculateQuote: function() {
            if (this.state.isCalculating) return;
            
            this.state.isCalculating = true;
            this.showLoading();
            this.hideError();
            this.hideResults();
            
            // Collect form data
            const formData = this.collectFormData();
            
            // Validate form data
            if (!this.validateFormData(formData)) {
                this.state.isCalculating = false;
                this.hideLoading();
                return;
            }
            
            // Make API request
            $.ajax({
                url: this.config.restUrl + 'quote',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', this.config.nonce);
                }
            })
            .done((response) => {
                this.handleQuoteSuccess(response);
            })
            .fail((xhr) => {
                this.handleQuoteError(xhr);
            })
            .always(() => {
                this.state.isCalculating = false;
                this.hideLoading();
                this.updateCalculateButton();
            });
        },
        
        // Collect form data for quote request
        collectFormData: function() {
            // Get pickup and dropoff with fallback place IDs for test mode
            const pickup = this.elements.pickupInput.val().trim();
            const dropoff = this.elements.dropoffInput.val().trim();
            
            let pickupPlaceId = this.elements.pickupPlaceIdInput.val();
            let dropoffPlaceId = this.elements.dropoffPlaceIdInput.val();
            
            // Generate test place IDs if missing
            if (!pickupPlaceId && pickup) {
                pickupPlaceId = 'test_pickup_' + this.hashCode(pickup);
                this.elements.pickupPlaceIdInput.val(pickupPlaceId);
            }
            
            if (!dropoffPlaceId && dropoff) {
                dropoffPlaceId = 'test_dropoff_' + this.hashCode(dropoff);
                this.elements.dropoffPlaceIdInput.val(dropoffPlaceId);
            }
            
            return {
                pickup: {
                    place_id: pickupPlaceId,
                    label: pickup
                },
                dropoff: {
                    place_id: dropoffPlaceId,
                    label: dropoff
                },
                tier: this.elements.tierInputs.filter(':checked').val() || 'standard',
                addons: this.elements.addonInputs.filter(':checked').map(function() {
                    return this.value;
                }).get()
            };
        },
        
        // Validate form data before sending
        validateFormData: function(data) {
            if (!data.pickup.label || !data.pickup.place_id) {
                this.showError(this.config.i18n.invalidAddress || 'Please enter a valid pickup address.');
                return false;
            }
            
            if (!data.dropoff.label || !data.dropoff.place_id) {
                this.showError(this.config.i18n.invalidAddress || 'Please enter a valid drop-off address.');
                return false;
            }
            
            if (data.pickup.label === data.dropoff.label) {
                this.showError('Pickup and drop-off addresses must be different.');
                return false;
            }
            
            return true;
        },
        
        // Handle successful quote response
        handleQuoteSuccess: function(response) {
            console.log('Quote calculated successfully', response);
            
            this.state.currentQuote = response;
            
            // Update price display
            this.elements.totalAmount.text('$' + response.pricing.total.toFixed(2));
            
            // Update breakdown
            this.elements.breakdownContent.html(response.breakdown_html);
            
            // Store quote data for cart
            this.elements.quoteDataInput.val(JSON.stringify({
                ...response.quote_data,
                pricing: response.pricing,
                miles: response.miles
            }));
            
            // Show results with animation
            this.showResults();
        },
        
        // Handle quote calculation error
        handleQuoteError: function(xhr) {
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
            
            this.showError(message);
        },
        
        // Add calculated quote to cart
        addToCart: function() {
            if (!this.state.currentQuote) {
                this.showError('Please calculate a quote first.');
                return;
            }
            
            const quoteData = this.elements.quoteDataInput.val();
            if (!quoteData) {
                this.showError('Quote data is missing. Please recalculate.');
                return;
            }
            
            // Get product ID from data attribute
            const productId = $('.wwc-courier-calculator').data('product-id');
            if (!productId) {
                this.showError('Product ID not found.');
                return;
            }
            
            // Disable add to cart button
            this.elements.addToCartBtn.prop('disabled', true).text('Adding to Cart...');
            
            // Prepare cart data
            const cartData = {
                action: 'woocommerce_add_to_cart',
                product_id: productId,
                wwc_quote_data: quoteData,
                wwc_nonce: this.config.nonce
            };
            
            // Add to cart via AJAX
            $.post(wwcAjax.ajaxurl, cartData)
                .done((response) => {
                    if (response && response.trim() !== '') {
                        // Redirect to cart or show success message
                        window.location.href = response;
                    } else {
                        // Reload page to show cart update
                        window.location.reload();
                    }
                })
                .fail(() => {
                    this.showError('Failed to add to cart. Please try again.');
                    this.elements.addToCartBtn.prop('disabled', false).text('Add to Cart');
                });
        },
        
        // UI helper methods
        showLoading: function() {
            this.elements.calculateBtn.addClass('wwc-loading');
            this.elements.calculateBtnText.hide();
            this.elements.calculateBtnLoading.show();
        },
        
        hideLoading: function() {
            this.elements.calculateBtn.removeClass('wwc-loading');
            this.elements.calculateBtnText.show();
            this.elements.calculateBtnLoading.hide();
        },
        
        showResults: function() {
            this.elements.error.hide();
            this.elements.results.addClass('wwc-fade-in').show();
        },
        
        hideResults: function() {
            this.elements.results.hide().removeClass('wwc-fade-in');
        },
        
        showError: function(message) {
            this.elements.errorMessage.text(message);
            this.elements.results.hide();
            this.elements.error.addClass('wwc-fade-in').show();
        },
        
        hideError: function() {
            this.elements.error.hide().removeClass('wwc-fade-in');
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
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize if calculator is present on page
        if ($('.wwc-courier-calculator').length > 0) {
            WWCCalculator.init();
        }
    });
    
    // Expose calculator object for debugging
    window.WWCCalculator = WWCCalculator;
    
})(jQuery);