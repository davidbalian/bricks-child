/**
 * Cookie Consent Banner JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Banner elements
    const banner = $('#cookie-consent-banner');
    const modal = $('#cookie-customize-modal');
    
    // Button elements
    const acceptAllBtn = $('#accept-all-cookies');
    const acceptNecessaryBtn = $('#accept-necessary-cookies');
    const customizeBtn = $('#customize-cookies');
    const savePreferencesBtn = $('#save-cookie-preferences');
    const acceptAllModalBtn = $('#accept-all-modal');
    const modalCloseBtn = $('.cookie-modal-close');
    
    // Handle Accept All Cookies
    acceptAllBtn.on('click', function() {
        setCookieConsent('all');
        hideBanner();
    });
    
    // Handle Accept Necessary Only
    acceptNecessaryBtn.on('click', function() {
        setCookieConsent('necessary');
        hideBanner();
    });
    
    // Handle Customize Cookies
    customizeBtn.on('click', function() {
        showModal();
    });
    
    // Handle Save Preferences
    savePreferencesBtn.on('click', function() {
        const preferences = {
            analytics: $('#analytics-cookies').is(':checked'),
            marketing: $('#marketing-cookies').is(':checked'),
            functional: $('#functional-cookies').is(':checked')
        };
        
        setCookieConsent('customize', preferences);
        hideModal();
        hideBanner();
    });
    
    // Handle Accept All from Modal
    acceptAllModalBtn.on('click', function() {
        setCookieConsent('all');
        hideModal();
        hideBanner();
    });
    
    // Handle Close Modal
    modalCloseBtn.on('click', function() {
        hideModal();
    });
    
    // Close modal when clicking outside
    modal.on('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });
    
    // Handle Escape key for modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && modal.is(':visible')) {
            hideModal();
        }
    });
    
    /**
     * Set cookie consent via AJAX
     */
    function setCookieConsent(type, preferences = {}) {
        const data = {
            action: cookieConsentAjax.action,
            nonce: cookieConsentAjax.nonce,
            consent_type: type
        };
        
        // Add preferences if customizing
        if (type === 'customize') {
            data.analytics = preferences.analytics ? 1 : 0;
            data.marketing = preferences.marketing ? 1 : 0;
            data.functional = preferences.functional ? 1 : 0;
        }
        
        $.ajax({
            url: cookieConsentAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    console.log('Cookie preferences saved:', response.data.preferences);
                    
                    // Trigger custom event for other scripts to listen to
                    $(document).trigger('cookieConsentUpdated', [response.data.preferences]);
                    
                    // Show success message (optional)
                    showSuccessMessage();
                } else {
                    console.error('Failed to save cookie preferences');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }
    
    /**
     * Show modal
     */
    function showModal() {
        modal.fadeIn(300);
        $('body').addClass('cookie-modal-open');
        
        // Focus trap for accessibility
        modal.find('button, input').first().focus();
    }
    
    /**
     * Hide modal
     */
    function hideModal() {
        modal.fadeOut(300);
        $('body').removeClass('cookie-modal-open');
    }
    
    /**
     * Hide banner with animation
     */
    function hideBanner() {
        banner.addClass('hiding');
        setTimeout(function() {
            banner.remove();
        }, 500);
    }
    
    /**
     * Show success message
     */
    function showSuccessMessage() {
        const message = $('<div class="cookie-success-message">Cookie preferences saved successfully!</div>');
        message.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: '#4caf50',
            color: 'white',
            padding: '15px 20px',
            borderRadius: '6px',
            zIndex: '10001',
            boxShadow: '0 4px 12px rgba(0, 0, 0, 0.2)',
            opacity: '0',
            transform: 'translateY(-20px)'
        });
        
        $('body').append(message);
        
        message.animate({
            opacity: 1,
            transform: 'translateY(0)'
        }, 300);
        
        setTimeout(function() {
            message.animate({
                opacity: 0,
                transform: 'translateY(-20px)'
            }, 300, function() {
                message.remove();
            });
        }, 3000);
    }
    
    /**
     * Initialize cookie preferences from existing cookies
     */
    function initializePreferences() {
        // Check if we have existing preferences
        const preferences = getCookiePreferences();
        
        if (preferences) {
            $('#analytics-cookies').prop('checked', preferences.analytics);
            $('#marketing-cookies').prop('checked', preferences.marketing);
            $('#functional-cookies').prop('checked', preferences.functional);
        }
    }
    
    /**
     * Get cookie preferences from cookie
     */
    function getCookiePreferences() {
        const cookie = getCookie('autoagora_cookie_preferences');
        if (cookie) {
            try {
                // Decode the cookie value in case it's URL encoded
                const decodedCookie = decodeURIComponent(cookie);
                return JSON.parse(decodedCookie);
            } catch (e) {
                console.error('Error parsing cookie preferences:', e);
                // Try parsing without decoding in case it's not URL encoded
                try {
                    return JSON.parse(cookie);
                } catch (e2) {
                    console.error('Error parsing cookie preferences (second attempt):', e2);
                    return null;
                }
            }
        }
        return null;
    }
    
    /**
     * Get cookie value by name
     */
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    /**
     * Accessibility improvements
     */
    function initializeAccessibility() {
        // Add ARIA attributes
        banner.attr('role', 'dialog');
        banner.attr('aria-label', 'Cookie consent banner');
        
        modal.attr('role', 'dialog');
        modal.attr('aria-modal', 'true');
        modal.attr('aria-labelledby', 'cookie-modal-title');
        
        // Add keyboard navigation
        banner.on('keydown', function(e) {
            if (e.key === 'Tab') {
                const focusableElements = banner.find('button, a, input, [tabindex]');
                const firstElement = focusableElements.first();
                const lastElement = focusableElements.last();
                
                if (e.shiftKey && $(document.activeElement).is(firstElement)) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && $(document.activeElement).is(lastElement)) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        });
    }
    
    // Initialize everything
    initializePreferences();
    initializeAccessibility();
    
    // Custom event for other scripts to check cookie status
    window.AutoAgoraCookies = {
        isAllowed: function(type) {
            const preferences = getCookiePreferences();
            return preferences ? preferences[type] : false;
        },
        getPreferences: function() {
            return getCookiePreferences();
        }
    };
    
    // CSS for body when modal is open
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            body.cookie-modal-open {
                overflow: hidden;
            }
            .cookie-success-message {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 14px;
                font-weight: 500;
            }
        `)
        .appendTo('head');
}); 