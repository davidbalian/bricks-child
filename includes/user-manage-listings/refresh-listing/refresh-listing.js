/**
 * Refresh Listing JavaScript
 * 
 * Handles client-side interactions for listing refresh functionality
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Refresh Listing Handler
     */
    const RefreshListingHandler = {
        
        /**
         * Initialize the handler
         */
        init: function() {
            this.bindEvents();
            this.isDevelopment = this.checkDevelopmentMode();
        },
        
        /**
         * Check if in development mode
         * 
         * @return {boolean}
         */
        checkDevelopmentMode: function() {
            return window.location.hostname === 'localhost' || 
                   window.location.hostname.includes('staging') ||
                   window.location.search.includes('debug=true');
        },
        
        /**
         * Bind event listeners
         */
        bindEvents: function() {
            $(document).on('click', '.refresh-button:not(.disabled)', this.handleRefreshClick.bind(this));
        },
        
        /**
         * Handle refresh button click
         * 
         * @param {Event} event The click event
         */
        handleRefreshClick: function(event) {
            event.preventDefault();
            
            const $button = $(event.currentTarget);
            const carId = $button.data('car-id');
            const canRefresh = $button.data('can-refresh');
            
            if (!canRefresh || canRefresh === '0') {
                this.showMessage('This listing cannot be refreshed yet.', 'error');
                return;
            }
            
            if (!this.confirmRefresh()) {
                return;
            }
            
            this.performRefresh(carId, $button);
        },
        
        /**
         * Confirm refresh action with user
         * 
         * @return {boolean}
         */
        confirmRefresh: function() {
            return confirm(
                'Refresh this listing?\n\n' +
                'This will move your listing to the top of search results. ' +
                'You can refresh again in 7 days.\n\n' +
                'Continue?'
            );
        },
        
        /**
         * Perform AJAX refresh request
         * 
         * @param {number} carId The car listing ID
         * @param {jQuery} $button The button element
         */
        performRefresh: function(carId, $button) {
            // Disable button and show loading state
            this.setButtonLoading($button, true);
            
            const data = {
                action: refreshListingData.ajaxAction,
                car_id: carId,
                nonce: refreshListingData.nonce
            };
            
            if (this.isDevelopment) {
                console.log('Sending refresh request:', data);
            }
            
            $.ajax({
                url: refreshListingData.ajaxUrl,
                type: 'POST',
                data: data,
                success: this.handleSuccess.bind(this, $button),
                error: this.handleError.bind(this, $button)
            });
        },
        
        /**
         * Handle successful refresh
         * 
         * @param {jQuery} $button The button element
         * @param {Object} response The AJAX response
         */
        handleSuccess: function($button, response) {
            if (this.isDevelopment) {
                console.log('Refresh response:', response);
            }
            
            if (response.success) {
                this.showMessage(response.data.message, 'success');
                this.updateButtonState($button, response.data);
                
                // Optionally reload page after short delay to show updated listing
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                this.showMessage(response.data.message || 'Failed to refresh listing', 'error');
                this.setButtonLoading($button, false);
            }
        },
        
        /**
         * Handle AJAX error
         * 
         * @param {jQuery} $button The button element
         * @param {Object} jqXHR The jQuery XHR object
         * @param {string} textStatus The status text
         * @param {string} errorThrown The error thrown
         */
        handleError: function($button, jqXHR, textStatus, errorThrown) {
            if (this.isDevelopment) {
                console.error('Refresh AJAX error:', {
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    response: jqXHR.responseText
                });
            }
            
            this.showMessage('An error occurred. Please try again.', 'error');
            this.setButtonLoading($button, false);
        },
        
        /**
         * Set button loading state
         * 
         * @param {jQuery} $button The button element
         * @param {boolean} loading Whether button is loading
         */
        setButtonLoading: function($button, loading) {
            const $icon = $button.find('i');
            const $text = $button.find('.refresh-button-text');
            
            if (loading) {
                $button.prop('disabled', true).addClass('loading');
                $icon.removeClass('fa-sync-alt').addClass('fa-spinner fa-spin');
                $button.data('original-text', $text.text());
                $text.text('Refreshing...');
            } else {
                $button.prop('disabled', false).removeClass('loading');
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
                const originalText = $button.data('original-text') || 'Refresh Listing';
                $text.text(originalText);
            }
        },
        
        /**
         * Update button state after refresh
         * 
         * @param {jQuery} $button The button element
         * @param {Object} data Response data
         */
        updateButtonState: function($button, data) {
            const $icon = $button.find('i');
            const $text = $button.find('.refresh-button-text');
            
            // Update button state
            $button.prop('disabled', true)
                   .addClass('disabled')
                   .removeClass('loading')
                   .data('can-refresh', '0');
            
            // Update icon and text
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
            $text.text('Available in 7 days');
            
            // Update refresh count if element exists
            const $listingItem = $button.closest('.listing-item');
            const $refreshInfo = $listingItem.find('.refresh-info');
            
            if (data.refresh_count) {
                const countText = data.refresh_count + ' time' + (data.refresh_count > 1 ? 's' : '');
                if ($refreshInfo.length) {
                    $refreshInfo.html('<i class="fas fa-info-circle"></i> Refreshed ' + countText);
                } else {
                    $button.after(
                        '<span class="refresh-info" title="Total refreshes: ' + data.refresh_count + '">' +
                        '<i class="fas fa-info-circle"></i> Refreshed ' + countText +
                        '</span>'
                    );
                }
            }
        },
        
        /**
         * Show message to user
         * 
         * @param {string} message The message text
         * @param {string} type The message type (success/error)
         */
        showMessage: function(message, type) {
            // Remove existing messages
            $('.refresh-listing-message').remove();
            
            // Create message element
            const $message = $('<div>', {
                class: 'refresh-listing-message notice notice-' + type,
                html: '<p>' + message + '</p>'
            });
            
            // Insert message at top of listings container
            $('.my-listings-container h2').after($message);
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: $message.offset().top - 100
            }, 300);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $message.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if (typeof refreshListingData !== 'undefined') {
            RefreshListingHandler.init();
        }
    });
    
})(jQuery);

