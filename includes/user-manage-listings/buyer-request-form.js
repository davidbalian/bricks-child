jQuery(document).ready(function ($) {
  // =====================================================
  // FORM VALIDATION CONFIGURATION
  // =====================================================
  const requiredFields = {
    'buyer-request-make': { type: 'dropdown', label: 'Make' },
    'buyer-request-year': { type: 'dropdown', label: 'Year' },
    'buyer_price': { type: 'text', label: 'Price' }
    // Note: buyer-request-model and buyer_description are optional
  };

  // =====================================================
  // FORM VALIDATION FUNCTIONS
  // =====================================================

  /**
   * Check if a specific field is valid
   */
  function isFieldValid(fieldId, config) {
    if (config.type === 'dropdown') {
      const $wrapper = $('#' + fieldId + '-wrapper');
      const $select = $wrapper.find('select');

      // Skip model validation if dropdown is disabled (no make selected) - model is optional anyway
      if (fieldId === 'buyer-request-model' && $select.prop('disabled')) {
        return true; // Model is optional, so if disabled it's still valid
      }

      const value = $select.val();
      return value !== '' && value !== null && value !== undefined;
    } else if (config.type === 'text') {
      const $input = $('#' + fieldId);
      let value = $input.val().trim();
      
      // For price, remove commas before checking
      if (fieldId === 'buyer_price') {
        value = value.replace(/,/g, '');
      }
      
      return value !== '' && !isNaN(value) && parseFloat(value) > 0;
    }
    return false;
  }

  /**
   * Validate all required fields and return validation result
   */
  function validateAllFields() {
    const errors = [];

    // Check all required fields
    for (const [fieldId, config] of Object.entries(requiredFields)) {
      if (!isFieldValid(fieldId, config)) {
        errors.push({ fieldId, label: config.label, type: config.type });
      }
    }

    return {
      isValid: errors.length === 0,
      errors: errors
    };
  }

  /**
   * Show error state for a specific field
   */
  function showFieldError(fieldId, label) {
    let $container;

    if (fieldId.startsWith('buyer-request-')) {
      // Dropdown field
      $container = $('#' + fieldId + '-wrapper').closest('.form-third, .form-half, .form-row');
    } else {
      // Text input
      $container = $('#' + fieldId).closest('.form-third, .form-half, .form-row');
    }

    if ($container.length) {
      $container.addClass('field-has-error');

      // Add error message if not already present
      if (!$container.find('.field-error-message').length) {
        let errorMsg = 'This field is required';
        if (fieldId === 'buyer_price') {
          errorMsg = 'Please enter a valid price';
        }
        $container.append('<span class="field-error-message">' + errorMsg + '</span>');
      }
    }
  }

  /**
   * Clear error state for a specific field
   */
  function clearFieldError(fieldId) {
    let $container;

    if (fieldId.startsWith('buyer-request-')) {
      $container = $('#' + fieldId + '-wrapper').closest('.form-third, .form-half, .form-row');
    } else {
      $container = $('#' + fieldId).closest('.form-third, .form-half, .form-row');
    }

    if ($container.length) {
      $container.removeClass('field-has-error');
      $container.find('.field-error-message').remove();
    }
  }

  /**
   * Clear all field errors
   */
  function clearAllFieldErrors() {
    $('.field-has-error').removeClass('field-has-error');
    $('.field-error-message').remove();
  }

  /**
   * Show all validation errors and scroll to first error
   */
  function showAllValidationErrors(errors) {
    // First clear all existing errors
    clearAllFieldErrors();

    // Show each error
    errors.forEach(error => {
      showFieldError(error.fieldId, error.label);
    });

    // Scroll to first error
    if (errors.length > 0) {
      let $firstError;
      const firstErrorId = errors[0].fieldId;

      if (firstErrorId.startsWith('buyer-request-')) {
        $firstError = $('#' + firstErrorId + '-wrapper');
      } else {
        $firstError = $('#' + firstErrorId);
      }

      if ($firstError.length) {
        $('html, body').animate({
          scrollTop: $firstError.offset().top - 100
        }, 300);
      }
    }
  }

  /**
   * Bind validation events to form fields
   */
  function bindValidationEvents() {
    // Dropdown changes - listen to hidden select change events
    Object.keys(requiredFields).forEach(fieldId => {
      const config = requiredFields[fieldId];

      if (config.type === 'dropdown') {
        const $select = $('#' + fieldId);
        $select.on('change', function() {
          // Clear error for this field if now valid
          if (isFieldValid(fieldId, config)) {
            clearFieldError(fieldId);
          }
        });
      } else if (config.type === 'text') {
        const $input = $('#' + fieldId);
        $input.on('input blur', function() {
          if (isFieldValid(fieldId, config)) {
            clearFieldError(fieldId);
          }
        });
      }
    });
  }

  // Initialize validation after a short delay to ensure DOM is ready
  setTimeout(function() {
    bindValidationEvents();
  }, 100);

  // =====================================================
  // DROPDOWN SYSTEM (copied from add-listing.js)
  // =====================================================
  var BuyerRequestDropdown = {
    init: function() {
      // Close dropdown when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.car-filter-dropdown').length) {
          $('.car-filter-dropdown.open').removeClass('open')
            .find('.car-filter-dropdown-button').attr('aria-expanded', 'false');
        }
      });

      // Toggle dropdown on button click
      $(document).on('click', '.car-filter-dropdown-button', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if ($(this).prop('disabled')) return;
        
        var $dropdown = $(this).closest('.car-filter-dropdown');
        var isOpen = $dropdown.hasClass('open');
        
        // Close all other dropdowns
        $('.car-filter-dropdown.open').not($dropdown).removeClass('open')
          .find('.car-filter-dropdown-button').attr('aria-expanded', 'false');
        
        // Toggle this dropdown
        if (isOpen) {
          $dropdown.removeClass('open');
          $(this).attr('aria-expanded', 'false');
        } else {
          $dropdown.addClass('open');
          $(this).attr('aria-expanded', 'true');
          $dropdown.find('.car-filter-dropdown-search').focus();
        }
      });

      // Select option
      var self = this;
      $(document).on('click', '.car-filter-dropdown-option', function(e) {
        e.stopPropagation();
        var $dropdown = $(this).closest('.car-filter-dropdown');
        self.selectOption($dropdown, $(this));
      });

      // Search functionality
      $(document).on('input', '.car-filter-dropdown-search', function() {
        var $dropdown = $(this).closest('.car-filter-dropdown');
        var searchTerm = $(this).val().toLowerCase();
        $dropdown.find('.car-filter-dropdown-option').each(function() {
          var text = $(this).text().toLowerCase();
          if (text.includes(searchTerm)) {
            $(this).removeClass('hidden');
          } else {
            $(this).addClass('hidden');
          }
        });
      });
    },
    
    selectOption: function($dropdown, $option) {
      var value = $option.data('value');
      var label = $option.clone().children().remove().end().text().trim();
      var $select = $dropdown.find('select');
      
      $select.val(value).trigger('change');
      $dropdown.removeClass('open');
      $dropdown.find('.car-filter-dropdown-button').attr('aria-expanded', 'false');
      
      // Update UI
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $text = $button.find('.car-filter-dropdown-text');
      $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
      $option.addClass('selected');
      
      if (value === '' || value === null || value === undefined) {
        $text.addClass('placeholder').text($select.find('option:first').text());
      } else {
        $text.removeClass('placeholder').text(label);
      }
      
      // Clear search
      $dropdown.find('.car-filter-dropdown-search').val('');
      this.filterOptions($dropdown, '');
    },
    
    filterOptions: function($dropdown, query) {
      var $options = $dropdown.find('.car-filter-dropdown-option');
      query = query.toLowerCase().trim();
      
      $options.each(function() {
        var text = $(this).text().toLowerCase();
        var matches = !query || text.indexOf(query) !== -1;
        $(this).toggleClass('hidden', !matches);
      });
    },
    
    setLoading: function($dropdown, isLoading) {
      var $options = $dropdown.find('.car-filter-dropdown-options');
      var $button = $dropdown.find('.car-filter-dropdown-button');
      
      if (isLoading) {
        $button.prop('disabled', true);
        $options.html('<div class="car-filter-loading" style="padding: 0.75rem; color: #6b7280; text-align: center;">Loading...</div>');
      } else {
        $button.prop('disabled', false);
      }
    },
    
    updateOptions: function($dropdown, options, placeholder) {
      var $options = $dropdown.find('.car-filter-dropdown-options');
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $search = $dropdown.find('.car-filter-dropdown-search');
      var $select = $dropdown.find('select');
      
      // Build options HTML
      var html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="">' + placeholder + '</button>';
      var selectHtml = '<option value="">' + placeholder + '</option>';
      
      options.forEach(function(opt) {
        html += '<button type="button" class="car-filter-dropdown-option" role="option" data-value="' + opt.value + '">' + opt.label + '</button>';
        selectHtml += '<option value="' + opt.value + '">' + opt.label + '</option>';
      });
      
      html += '<div class="car-filter-no-results hidden">No matching results</div>';
      
      $options.html(html);
      $select.html(selectHtml);
      
      // Reset button text to placeholder
      $button.find('.car-filter-dropdown-text').addClass('placeholder').text(placeholder);
      
      // Enable/disable based on options
      if (options.length > 0) {
        $dropdown.removeClass('car-filter-dropdown-disabled');
        $button.prop('disabled', false).removeAttr('disabled');
        $select.prop('disabled', false).removeAttr('disabled');
        if ($search.length) {
          $search.prop('disabled', false).removeAttr('disabled');
        }
      } else {
        $dropdown.addClass('car-filter-dropdown-disabled');
        $button.prop('disabled', true);
        $select.prop('disabled', true);
        if ($search.length) {
          $search.prop('disabled', true);
        }
      }
    },
    
    disable: function($dropdown, placeholder) {
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $search = $dropdown.find('.car-filter-dropdown-search');
      var $options = $dropdown.find('.car-filter-dropdown-options');
      var $select = $dropdown.find('select');
      
      $button.prop('disabled', true);
      $select.prop('disabled', true);
      $dropdown.addClass('car-filter-dropdown-disabled');
      $search.prop('disabled', true);
      $button.find('.car-filter-dropdown-text').addClass('placeholder').text(placeholder);
      
      var html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="">' + placeholder + '</button>';
      html += '<div class="car-filter-no-results hidden">No matching results</div>';
      $options.html(html);
      $select.html('<option value="">' + placeholder + '</option>');
    },
    
    enable: function($dropdown) {
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $search = $dropdown.find('.car-filter-dropdown-search');
      var $select = $dropdown.find('select');
      
      // Remove disabled class from the dropdown wrapper itself
      $dropdown.removeClass('car-filter-dropdown-disabled');
      
      // Enable all elements
      $button.prop('disabled', false).removeAttr('disabled');
      $select.prop('disabled', false).removeAttr('disabled');
      if ($search.length) {
        $search.prop('disabled', false).removeAttr('disabled');
      }
    }
  };
  
  // Initialize dropdown system
  BuyerRequestDropdown.init();
  
  // =====================================================
  // MAKE/MODEL HANDLING
  // =====================================================
  // Note: car_filter_render_dropdown creates wrapper with id="{id}-wrapper"
  var isLoadingModels = false;
  
  // Handle make selection to populate model dropdown
  // Listen to the hidden select change event
  $('#buyer-request-make').on('change', function() {
    const makeName = $(this).val();
    
    // Get model dropdown - the wrapper IS the dropdown (it has class car-filter-dropdown)
    var $modelDropdown = $('#buyer-request-model-wrapper');
    
    if (!$modelDropdown.length) {
      console.error('Buyer Request: Model dropdown wrapper not found!');
      return;
    }
    
    if (!makeName || makeName === '') {
      // Reset model dropdown if no make selected
      BuyerRequestDropdown.disable($modelDropdown, 'Select Model');
      return;
    }
    
    // Show loading state
    BuyerRequestDropdown.setLoading($modelDropdown, true);
    
    isLoadingModels = true;
    
    // Fetch models via AJAX
    $.ajax({
      url: buyerRequestData.ajaxurl,
      type: "POST",
      data: {
        action: "get_models_for_make",
        make: makeName,
        nonce: buyerRequestData.nonce,
      },
      success: function(response) {
        isLoadingModels = false;
        
        // Re-get dropdown (wrapper IS the dropdown)
        var $modelDropdown = $('#buyer-request-model-wrapper');
        
        BuyerRequestDropdown.setLoading($modelDropdown, false);
        
        console.log('Buyer Request: Models AJAX response:', response);
        
        if (response.success && response.data && response.data.length > 0) {
          // response.data is an array of model names
          const options = response.data.map(function(modelName) {
            return {
              value: modelName,
              label: modelName
            };
          });
          
          console.log('Buyer Request: Updating model dropdown with', options.length, 'options');
          
          // Update options (this will enable the dropdown if options exist)
          BuyerRequestDropdown.updateOptions($modelDropdown, options, 'Select Model');
          
          // Explicitly enable the dropdown to ensure it's enabled
          BuyerRequestDropdown.enable($modelDropdown);
          
          console.log('Buyer Request: Model dropdown enabled. Button disabled?', $modelDropdown.find('.car-filter-dropdown-button').prop('disabled'));
        } else {
          console.log('Buyer Request: No models found or error in response');
          BuyerRequestDropdown.disable($modelDropdown, response.data && response.data.length === 0 ? 'No models available' : 'Error loading models');
        }
      },
      error: function(xhr, status, error) {
        isLoadingModels = false;
        
        // Re-get dropdown (wrapper IS the dropdown)
        var $modelDropdown = $('#buyer-request-model-wrapper');
        
        BuyerRequestDropdown.setLoading($modelDropdown, false);
        BuyerRequestDropdown.disable($modelDropdown, 'Error loading models');
        console.error('Buyer Request: AJAX error:', error);
      }
    });
  });
  
  // =====================================================
  // PRICE FORMATTING (copied from add-listing.js)
  // =====================================================
  const priceInput = $("#buyer_price");
  
  // Format number with commas
  function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }
  
  // Remove commas and convert to number
  function unformatNumber(formattedNumber) {
    return parseInt(formattedNumber.replace(/[^0-9]/g, "")) || 0;
  }
  
  // Handle price input event for real-time formatting
  priceInput.on("input", function (e) {
    // Get the raw value without commas
    let value = this.value.replace(/[,]/g, "");
    
    // Only allow numbers
    value = value.replace(/[^\d]/g, "");
    
    // Format with commas
    if (value) {
      const formattedValue = formatNumber(value);
      
      // Update the display value
      this.value = formattedValue;
      
      // Store the raw value in a data attribute
      $(this).data("raw-value", unformatNumber(value));
    } else {
      this.value = "";
      $(this).data("raw-value", 0);
    }
  });
  
  // =====================================================
  // COLLAPSIBLE SECTIONS
  // =====================================================
  $(".collapsible-section .section-header").on("click", function () {
    const $section = $(this).closest(".collapsible-section");
    const isCollapsed = $section.hasClass("collapsed");
    
    $section.toggleClass("collapsed");
    $(this).attr("aria-expanded", isCollapsed ? "true" : "false");
  });
  
  // Keyboard accessibility
  $(".collapsible-section .section-header").on("keydown", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      $(this).trigger("click");
    }
  });
  
  // Handle form submission
  $("#create-buyer-request-form").on("submit", function (e) {
    // Run full validation
    const validation = validateAllFields();

    if (!validation.isValid) {
      e.preventDefault();
      showAllValidationErrors(validation.errors);
      console.log('Buyer Request: Form submission blocked - validation failed:', validation.errors);
      return false;
    }

    // Re-enable model dropdown before submission (disabled fields don't submit)
    const $modelSelect = $("#buyer-request-model");
    if ($modelSelect.prop("disabled")) {
      $modelSelect.prop("disabled", false);
    }
    
    // Price is already formatted with commas, backend will handle it
    // No need to unformat since backend uses str_replace(',', '', ...)
  });
});

