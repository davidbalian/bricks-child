jQuery(document).ready(function ($) {
  // Handle make selection to populate model dropdown
  $('#buyer-request-make').on('change', function() {
    const makeName = $(this).val();
    const $modelDropdown = $('#buyer-request-model');
    const $modelWrapper = $('#buyer-request-model-wrapper');
    
    if (!makeName || makeName === '') {
      // Reset model dropdown if no make selected
      $modelDropdown.prop('disabled', true);
      $modelWrapper.addClass('car-filter-dropdown-disabled');
      $modelDropdown.val('');
      $modelWrapper.find('.car-filter-dropdown-button .car-filter-dropdown-text')
        .addClass('placeholder')
        .text('Select Model');
      $modelWrapper.find('.car-filter-dropdown-option').removeClass('selected');
      $modelWrapper.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
      return;
    }
    
    // Show loading state
    const $options = $modelWrapper.find('.car-filter-dropdown-options');
    const $buttonText = $modelWrapper.find('.car-filter-dropdown-button .car-filter-dropdown-text');
    $options.html('<div class="car-filter-loading" style="padding: 0.75rem; color: #6b7280; text-align: center;">Loading.</div>');
    $buttonText.text('Loading.');
    
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
        if (response.success && response.data) {
          // response.data is an array of model names
          const options = response.data.map(function(modelName) {
            return {
              value: modelName,
              label: modelName
            };
          });
          
          // Enable the dropdown
          $modelDropdown.prop('disabled', false);
          $modelWrapper.removeClass('car-filter-dropdown-disabled');
          
          // Update options (using the same dropdown system as add listing)
          // We need to manually update the select and the UI
          $modelDropdown.empty().append('<option value="">Select Model</option>');
          options.forEach(function(option) {
            $modelDropdown.append('<option value="' + option.value + '">' + option.label + '</option>');
          });
          
          // Update the UI dropdown options
          $options.empty();
          $options.append('<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="">Select Model</button>');
          options.forEach(function(option) {
            $options.append('<button type="button" class="car-filter-dropdown-option" role="option" data-value="' + option.value + '">' + option.label + '</button>');
          });
          
          // Reset selection
          $modelDropdown.val('');
          $buttonText.addClass('placeholder').text('Select Model');
        } else {
          $modelDropdown.prop('disabled', true);
          $modelWrapper.addClass('car-filter-dropdown-disabled');
          $buttonText.text('Error loading models');
        }
      },
      error: function(xhr, status, error) {
        $modelDropdown.prop('disabled', true);
        $modelWrapper.addClass('car-filter-dropdown-disabled');
        $buttonText.text('Error loading models');
      }
    });
  });
  
  // Collapsible sections functionality (for description)
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
});

