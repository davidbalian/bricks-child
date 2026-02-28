jQuery(document).ready(function ($) {
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
        $button.prop('disabled', false);
        $select.prop('disabled', false);
        $dropdown.removeClass('car-filter-dropdown-disabled');
        $search.prop('disabled', false);
      } else {
        $button.prop('disabled', true);
        $select.prop('disabled', true);
        $dropdown.addClass('car-filter-dropdown-disabled');
        $search.prop('disabled', true);
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
      
      $button.prop('disabled', false);
      $select.prop('disabled', false);
      $dropdown.removeClass('car-filter-dropdown-disabled');
      $search.prop('disabled', false);
    }
  };
  
  // Initialize dropdown system
  BuyerRequestDropdown.init();
  
  // =====================================================
  // MAKE/MODEL HANDLING
  // =====================================================
  // Note: car_filter_render_dropdown creates wrapper with id="{id}-wrapper"
  var $makeDropdown = $('#buyer-request-make-wrapper').find('.car-filter-dropdown');
  var $modelDropdown = $('#buyer-request-model-wrapper').find('.car-filter-dropdown');
  var isLoadingModels = false;
  
  // Handle make selection to populate model dropdown
  // Listen to the hidden select change event
  $('#buyer-request-make').on('change', function() {
    const makeName = $(this).val();
    
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
        BuyerRequestDropdown.setLoading($modelDropdown, false);
        
        if (response.success && response.data) {
          // response.data is an array of model names
          const options = response.data.map(function(modelName) {
            return {
              value: modelName,
              label: modelName
            };
          });
          
          BuyerRequestDropdown.updateOptions($modelDropdown, options, 'Select Model');
        } else {
          BuyerRequestDropdown.disable($modelDropdown, 'Error loading models');
        }
      },
      error: function(xhr, status, error) {
        isLoadingModels = false;
        BuyerRequestDropdown.setLoading($modelDropdown, false);
        BuyerRequestDropdown.disable($modelDropdown, 'Error loading models');
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
    // Re-enable model dropdown before submission (disabled fields don't submit)
    const $modelSelect = $("#buyer-request-model");
    if ($modelSelect.prop("disabled")) {
      $modelSelect.prop("disabled", false);
    }
    
    // Price is already formatted with commas, backend will handle it
    // No need to unformat since backend uses str_replace(',', '', ...)
  });
});

