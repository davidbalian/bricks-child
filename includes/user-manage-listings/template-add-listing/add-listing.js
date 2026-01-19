jQuery(document).ready(function ($) {
  // PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (window.location.hostname === 'localhost' ||
                                               window.location.hostname.includes('staging') ||
                                               window.location.search.includes('debug=true'));

  if (isDevelopment) console.log("[Add Listing] jQuery ready");

  // Collapsible sections functionality
  function initCollapsibleSections() {
    $(".collapsible-section .section-header").on("click", function () {
      const $section = $(this).closest(".collapsible-section");
      const isCollapsed = $section.hasClass("collapsed");

      $section.toggleClass("collapsed");
      $(this).attr("aria-expanded", isCollapsed ? "true" : "false");

      if (isDevelopment) console.log("[Add Listing] Section toggled:", $section.find("h2").text().trim(), "Collapsed:", !isCollapsed);
    });

    // Keyboard accessibility - toggle on Enter or Space
    $(".collapsible-section .section-header").on("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        $(this).trigger("click");
      }
    });

    if (isDevelopment) console.log("[Add Listing] Collapsible sections initialized");
  }

  // Initialize collapsible sections
  initCollapsibleSections();

  // Initialize async upload manager if available
  let asyncUploadManager = null;
  if (typeof AsyncUploadManager !== "undefined") {
    asyncUploadManager = new AsyncUploadManager();

    // Set session ID in hidden form field
    $("#async_session_id").val(asyncUploadManager.session.id);

    // Override callbacks to update UI
    asyncUploadManager.updateUploadProgress = function (fileKey, progress) {
      updateAsyncUploadProgress(fileKey, progress);
    };

    asyncUploadManager.onUploadSuccess = function (fileKey, data) {
      onAsyncUploadSuccess(fileKey, data);
    };

    asyncUploadManager.onUploadError = function (fileKey, error) {
      onAsyncUploadError(fileKey, error);
    };

    asyncUploadManager.onImageRemoved = function (fileKey) {
      onAsyncImageRemoved(fileKey);
    };

    if (isDevelopment) console.log(
      "[Add Listing] Async upload manager initialized with session:",
      asyncUploadManager.session.id
    );
  }

  /**
   * Custom Dropdown Controller for Add Listing page
   */
  var AddListingDropdown = {
    init: function() {
      this.bindEvents();
      if (isDevelopment) console.log("[Add Listing] Custom dropdown controller initialized");
    },

    bindEvents: function() {
      var self = this;

      // Toggle dropdown
      $(document).on('click', '.car-filter-dropdown-button', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if ($(this).prop('disabled')) return;

        var $dropdown = $(this).closest('.car-filter-dropdown');
        self.toggle($dropdown);
      });

      // Select option
      $(document).on('click', '.car-filter-dropdown-option', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $dropdown = $(this).closest('.car-filter-dropdown');
        self.selectOption($dropdown, $(this));
      });

      // Search input
      $(document).on('input', '.car-filter-dropdown-search', function() {
        var $dropdown = $(this).closest('.car-filter-dropdown');
        self.filterOptions($dropdown, $(this).val());
      });

      // Close on outside click
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.car-filter-dropdown').length) {
          self.closeAll();
        }
      });

      // Keyboard navigation
      $(document).on('keydown', '.car-filter-dropdown', function(e) {
        self.handleKeyboard($(this), e);
      });
    },

    toggle: function($dropdown) {
      var isOpen = $dropdown.hasClass('open');
      this.closeAll();

      if (!isOpen) {
        $dropdown.addClass('open');
        $dropdown.find('.car-filter-dropdown-button').attr('aria-expanded', 'true');
        $dropdown.find('.car-filter-dropdown-search').focus();
      }
    },

    closeAll: function() {
      $('.car-filter-dropdown.open').removeClass('open')
        .find('.car-filter-dropdown-button').attr('aria-expanded', 'false');
    },

    selectOption: function($dropdown, $option) {
      var value = $option.data('value');
      var label = $option.clone().children('.car-filter-count').remove().end().text().trim();
      var filterType = $dropdown.data('filter-type');

      // Update hidden select
      var $select = $dropdown.find('select');
      $select.val(value).trigger('change');

      // Update button text
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $text = $button.find('.car-filter-dropdown-text');

      if (value === '' || value === null || value === undefined) {
        $text.addClass('placeholder').text($select.find('option:first').text());
      } else {
        $text.removeClass('placeholder').text(label);
      }

      // Update selected state
      $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
      $option.addClass('selected');

      // Close dropdown
      this.closeAll();

      // Clear search
      $dropdown.find('.car-filter-dropdown-search').val('');
      this.filterOptions($dropdown, '');

      // Trigger custom event for make/model dependency
      if (filterType === 'make') {
        $(document).trigger('addListing:makeChanged', [value]);
      }
    },

    filterOptions: function($dropdown, query) {
      var $options = $dropdown.find('.car-filter-dropdown-option');
      var $noResults = $dropdown.find('.car-filter-no-results');
      var hasVisible = false;

      query = query.toLowerCase().trim();

      $options.each(function() {
        var text = $(this).text().toLowerCase();
        var matches = !query || text.indexOf(query) !== -1;

        $(this).toggleClass('hidden', !matches);
        if (matches) hasVisible = true;
      });

      // Show/hide section headers and separators
      $dropdown.find('.car-filter-section-header, .car-filter-separator').toggleClass('hidden', !!query);

      // Show no results message
      $noResults.toggleClass('hidden', hasVisible);
    },

    handleKeyboard: function($dropdown, e) {
      if (!$dropdown.hasClass('open')) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.toggle($dropdown);
        }
        return;
      }

      var $options = $dropdown.find('.car-filter-dropdown-option:not(.hidden)');
      var $focused = $options.filter('.focused');
      var index = $options.index($focused);

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          index = Math.min(index + 1, $options.length - 1);
          $options.removeClass('focused');
          $options.eq(index).addClass('focused');
          break;

        case 'ArrowUp':
          e.preventDefault();
          index = Math.max(index - 1, 0);
          $options.removeClass('focused');
          $options.eq(index).addClass('focused');
          break;

        case 'Enter':
          e.preventDefault();
          if ($focused.length) {
            this.selectOption($dropdown, $focused);
          }
          break;

        case 'Escape':
          e.preventDefault();
          this.closeAll();
          break;
      }
    },

    // Update dropdown options dynamically (for model dropdown)
    updateOptions: function($dropdown, options, placeholder) {
      var $options = $dropdown.find('.car-filter-dropdown-options');
      var $select = $dropdown.find('select');
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $search = $dropdown.find('.car-filter-dropdown-search');

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
        $select.prop('disabled', false);  // Enable the hidden select for form submission
        $dropdown.removeClass('car-filter-dropdown-disabled');
        $search.prop('disabled', false);
      } else {
        $button.prop('disabled', true);
        $select.prop('disabled', true);
        $dropdown.addClass('car-filter-dropdown-disabled');
        $search.prop('disabled', true);
      }
    },

    // Set dropdown to loading state
    setLoading: function($dropdown, isLoading) {
      var $options = $dropdown.find('.car-filter-dropdown-options');
      var $button = $dropdown.find('.car-filter-dropdown-button');

      if (isLoading) {
        $button.prop('disabled', true);
        $options.html('<div class="car-filter-loading" style="padding: 0.75rem; color: #6b7280; text-align: center;">Loading...</div>');
      }
    },

    // Disable dropdown
    disable: function($dropdown, placeholder) {
      var $button = $dropdown.find('.car-filter-dropdown-button');
      var $search = $dropdown.find('.car-filter-dropdown-search');
      var $options = $dropdown.find('.car-filter-dropdown-options');
      var $select = $dropdown.find('select');

      $button.prop('disabled', true);
      $select.prop('disabled', true);  // Disable the hidden select too
      $dropdown.addClass('car-filter-dropdown-disabled');
      $search.prop('disabled', true);
      $button.find('.car-filter-dropdown-text').addClass('placeholder').text(placeholder);

      var html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="">' + placeholder + '</button>';
      html += '<div class="car-filter-no-results hidden">No matching results</div>';
      $options.html(html);
      $select.html('<option value="">' + placeholder + '</option>');
    }
  };

  // Initialize dropdown controller
  AddListingDropdown.init();

  // Handle make selection change (for model dependency)
  $(document).on('addListing:makeChanged', function(e, makeName) {
    if (isDevelopment) console.log("[Add Listing] Make changed:", makeName);

    var $modelDropdown = $('#add-listing-model-wrapper');

    if (!makeName) {
      // No make selected - disable model dropdown
      AddListingDropdown.disable($modelDropdown, 'Select Model');
      return;
    }

    // Show loading state
    AddListingDropdown.setLoading($modelDropdown, true);

    // Fetch models via AJAX (using make name, returns model names)
    $.ajax({
      url: addListingData.ajaxurl,
      type: "POST",
      data: {
        action: "get_models_for_make",
        make: makeName,
        nonce: addListingData.nonce,
      },
      success: function(response) {
        if (response.success && response.data) {
          // response.data is an array of model names
          var options = response.data.map(function(modelName) {
            return {
              value: modelName,
              label: modelName
            };
          });

          AddListingDropdown.updateOptions($modelDropdown, options, 'Select Model');

          if (isDevelopment) console.log("[Add Listing] Loaded", options.length, "models");
        } else {
          if (isDevelopment) console.error("[Add Listing] Error loading models:", response);
          AddListingDropdown.disable($modelDropdown, 'Error loading models');
        }
      },
      error: function(xhr, status, error) {
        if (isDevelopment) console.error("[Add Listing] AJAX error:", error);
        AddListingDropdown.disable($modelDropdown, 'Error loading models');
      }
    });
  });

  // variant handling removed

  // Handle fuel type change to lock/unlock engine capacity for electric vehicles
  function handleElectricFuelType() {
    const $fuelTypeSelect = $("#add-listing-fuel-type");
    const selectedFuelType = $fuelTypeSelect.val();
    const $engineCapacityDropdown = $("#add-listing-engine-capacity-wrapper");
    const $engineCapacitySelect = $("#add-listing-engine-capacity");
    const $engineCapacityButton = $engineCapacityDropdown.find(".car-filter-dropdown-button");
    const $engineCapacityOptions = $engineCapacityDropdown.find(".car-filter-dropdown-options");

    if (selectedFuelType === "Electric") {
      // Add 0.0 option if it doesn't exist
      if ($engineCapacitySelect.find('option[value="0.0"]').length === 0) {
        $engineCapacitySelect.find('option[value=""]').after('<option value="0.0">0.0L</option>');
        $engineCapacityOptions.find('.car-filter-dropdown-option[data-value=""]').after(
          '<button type="button" class="car-filter-dropdown-option" role="option" data-value="0.0">0.0L</button>'
        );
      }

      // Select 0.0 and update UI
      $engineCapacitySelect.val("0.0");
      $engineCapacityDropdown.find(".car-filter-dropdown-option").removeClass("selected");
      $engineCapacityDropdown.find('.car-filter-dropdown-option[data-value="0.0"]').addClass("selected");
      $engineCapacityButton.find(".car-filter-dropdown-text").removeClass("placeholder").text("0.0L");

      // Disable the dropdown
      $engineCapacityButton.prop("disabled", true);
      $engineCapacityDropdown.addClass("car-filter-dropdown-disabled electric-locked");

      if (isDevelopment) console.log("[Add Listing] Engine capacity locked to 0.0 for electric vehicle");
    } else {
      // Re-enable the engine capacity dropdown
      $engineCapacityButton.prop("disabled", false);
      $engineCapacityDropdown.removeClass("car-filter-dropdown-disabled electric-locked");

      // Remove the 0.0 option and reset selection if it was 0.0
      if ($engineCapacitySelect.val() === "0.0") {
        $engineCapacitySelect.val("");
        $engineCapacityButton.find(".car-filter-dropdown-text").addClass("placeholder").text("Select Engine Capacity");
        $engineCapacityDropdown.find(".car-filter-dropdown-option").removeClass("selected");
        $engineCapacityDropdown.find('.car-filter-dropdown-option[data-value=""]').addClass("selected");
      }
      $engineCapacitySelect.find('option[value="0.0"]').remove();
      $engineCapacityDropdown.find('.car-filter-dropdown-option[data-value="0.0"]').remove();

      if (isDevelopment) console.log("[Add Listing] Engine capacity unlocked for non-electric vehicle");
    }
  }

  // Initialize electric fuel type handling on page load
  handleElectricFuelType();

  // Handle fuel type changes - listen to the hidden select's change event
  $("#add-listing-fuel-type").on("change", handleElectricFuelType);

  const fileInput = $("#car_images");
  const fileUploadArea = $("#file-upload-area");
  const imagePreview = $("#image-preview");
  let accumulatedFilesList = []; // Source of truth for selected files

  /**
   * IMAGE REORDERING (drag & drop)
   * --------------------------------
   * We use native HTML5 drag & drop on each .image-preview-item.
   * - Desktop: hover → click & hold → drag
   * - Mobile: tap & hold (where supported by the browser)
   *
   * Reordering affects both:
   * - The DOM order of .image-preview-item
   * - The order of accumulatedFilesList (and therefore the <input type="file">)
   */
  let dragSourceItem = null;

  function applyGrabCursor($item) {
    if ($item && $item.length) {
      $item.css("cursor", "grab");
    }
  }

  function attachSwapHandle($item) {
    if (!$item || !$item.length || $item.find(".image-swap-handle").length) return;

    const handle = $("<button>")
      .attr({
        type: "button",
        "aria-label": "Swap image position",
      })
      .addClass("image-swap-handle")
      .html("&larr;&rarr;")
      .css({
        position: "absolute",
        bottom: "8px",
        right: "8px",
        border: "none",
        borderRadius: "999px",
        padding: "4px 8px",
        fontSize: "0.75rem",
        lineHeight: "1",
        background: "rgba(0,0,0,0.65)",
        color: "#fff",
        cursor: "pointer",
        boxShadow: "0 2px 6px rgba(0,0,0,0.25)",
      })
      .on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        swapPreviewWithNeighbour($item);
      });

    $item.append(handle);
  }

  function enableImageReordering() {
    // Mark items as draggable whenever they are (re)created
    imagePreview.on("mouseenter", ".image-preview-item", function () {
      $(this).attr("draggable", "true");
      applyGrabCursor($(this));
    });

    imagePreview.on("dragstart", ".image-preview-item", function (e) {
      dragSourceItem = this;
      $(this).addClass("dragging");
      $(this).css("cursor", "grabbing");
      if (e.originalEvent && e.originalEvent.dataTransfer) {
        e.originalEvent.dataTransfer.effectAllowed = "move";
        // Some browsers require data to be set to enable drag
        e.originalEvent.dataTransfer.setData("text/plain", "drag");
      }
    });

    imagePreview.on("dragover", ".image-preview-item", function (e) {
      e.preventDefault();
      if (e.originalEvent && e.originalEvent.dataTransfer) {
        e.originalEvent.dataTransfer.dropEffect = "move";
      }
    });

    imagePreview.on("drop", ".image-preview-item", function (e) {
      e.preventDefault();
      if (!dragSourceItem || dragSourceItem === this) {
        return;
      }

      const $dragSource = $(dragSourceItem);
      const $target = $(this);

      // Insert the dragged item before/after target based on index
      if ($target.index() < $dragSource.index()) {
        $target.before($dragSource);
      } else {
        $target.after($dragSource);
      }

      imagePreview.find(".image-preview-item").removeClass("dragging");
      imagePreview.find(".image-preview-item").css("cursor", "grab");
      dragSourceItem = null;

      // Sync our in-memory list and file input with new DOM order
      syncAccumulatedFilesWithDomOrder();
      updateAsyncImageOrderField();
    });

    imagePreview.on("dragend", ".image-preview-item", function () {
      imagePreview.find(".image-preview-item").removeClass("dragging");
      imagePreview.find(".image-preview-item").css("cursor", "grab");
      dragSourceItem = null;
    });
  }

  function swapPreviewWithNeighbour($item) {
    if (!$item || !$item.length) return;

    let swapped = false;
    const $next = $item.next(".image-preview-item");

    if ($next.length) {
      $next.after($item);
      swapped = true;
    } else {
      const $prev = $item.prev(".image-preview-item");
      if ($prev.length) {
        $prev.before($item);
        swapped = true;
      }
    }

    if (swapped) {
      syncAccumulatedFilesWithDomOrder();
      updateAsyncImageOrderField();
    }
  }

  function syncAccumulatedFilesWithDomOrder() {
    if (!accumulatedFilesList.length) return;

    const reordered = [];
    imagePreview.find(".image-preview-item").each(function () {
      const fileObj = $(this).data("fileObj");
      if (fileObj) {
        reordered.push(fileObj);
      }
    });

    // Only overwrite if we actually found matching file objects
    if (reordered.length === accumulatedFilesList.length) {
      accumulatedFilesList = reordered;
      updateActualFileInput();
      if (isDevelopment)
        console.log(
          "[Add Listing] Files reordered. New order:",
          accumulatedFilesList.map((f) => f.name)
        );
    }
  }

  /**
   * Maintain async image order (attachment IDs) for the backend.
   * We store IDs in a hidden input: <input name="async_image_order[]" ... />
   */
  function updateAsyncImageOrderField() {
    const $form = $("#add-car-listing-form");
    if (!$form.length) return;

    // Remove previous values
    $form.find('input[name="async_image_order[]"]').remove();

    // Only relevant when using async uploads
    if (!asyncUploadManager) return;

    imagePreview.find(".image-preview-item").each(function () {
      const fileObj = $(this).data("fileObj");
      if (fileObj && fileObj.attachmentId) {
        $("<input>")
          .attr({
            type: "hidden",
            name: "async_image_order[]",
            value: fileObj.attachmentId,
          })
          .appendTo($form);
      }
    });
  }

  // Add mileage formatting
  const mileageInput = $("#mileage");
  const priceInput = $("#price");

  // Format number with commas
  function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // Remove commas and convert to number
  function unformatNumber(formattedNumber) {
    return parseInt(formattedNumber.replace(/[^0-9]/g, "")) || 0;
  }

  // Format mileage with commas
  mileageInput.on("input", function (e) {
    let value = this.value.replace(/[km,]/g, "");
    value = value.replace(/[^\d]/g, "");
    if (value) {
      const formattedValue = formatNumber(value);
      this.value = formattedValue;
      $(this).data("raw-value", value);
    } else {
      this.value = "";
      $(this).data("raw-value", 0);
    }
  });

  // Handle price input event for real-time formatting
  priceInput.on("input", function (e) {
    // Get the raw value without commas and euro sign
    let value = this.value.replace(/[€,]/g, "");

    // Only allow numbers
    value = value.replace(/[^\d]/g, "");

    // Format with commas and euro sign
    if (value) {
      const formattedValue = "€" + formatNumber(value);

      // Update the display value
      this.value = formattedValue;

      // Store the raw value in a data attribute
      $(this).data("raw-value", unformatNumber(value));
    } else {
      this.value = "";
      $(this).data("raw-value", 0);
    }
  });

  // Format HP input with thousand separators
  $("#hp").on("input", function () {
    // Remove any non-digit characters
    let value = $(this).val().replace(/[^\d]/g, "");

    // Add thousand separators
    if (value.length > 0) {
      value = parseInt(value).toLocaleString();
    }

    // Update the input value
    $(this).val(value);
    // Store the raw value
    $(this).data("raw-value", value.replace(/[^\d]/g, ""));
  });

  // Handle form submission
  $("#add-car-listing-form").on("submit", function (e) {
    // Re-enable model dropdown before submission (disabled fields don't submit)
    const $modelSelect = $("#add-listing-model");
    if ($modelSelect.prop("disabled")) {
      $modelSelect.prop("disabled", false);
    }

    // Re-enable engine capacity field temporarily for form submission if it's locked for electric
    const $engineCapacityDropdown = $("#add-listing-engine-capacity-wrapper");
    const $engineCapacitySelect = $("#add-listing-engine-capacity");
    const wasElectricLocked = $engineCapacityDropdown.hasClass("electric-locked");
    if (wasElectricLocked) {
      $engineCapacitySelect.prop("disabled", false);
      if (isDevelopment) console.log(
        "[Add Listing] Temporarily re-enabled engine capacity field for form submission"
      );
    }

    const locationField = $("#location");
    if (!locationField.val().trim()) {
      e.preventDefault();
      alert("Please choose a location.");
      // optional: bring attention to the button/field
      $("#location-row").addClass("has-error");
      $(".choose-location-btn").focus();
      return false;
    }


    // Validate image count - either async uploaded or traditional
    let totalImages = 0;

    if (asyncUploadManager) {
      // Count async uploaded images
      totalImages = asyncUploadManager.getUploadedAttachmentIds().length;
    } else {
      // Count traditional uploaded files
      totalImages = accumulatedFilesList.length;
    }

    if (totalImages < 2) {
      e.preventDefault();
      alert("Please upload at least 2 images before submitting the form");
      return;
    }
    if (totalImages > 25) {
      e.preventDefault();
      alert("Maximum 25 images allowed");
      return;
    }

    // If using async uploads, mark session as completed
    if (asyncUploadManager) {
      // Check if any async uploads are still in progress
      const pendingUploads = accumulatedFilesList.filter(
        (file) => file.asyncUploadStatus === "uploading"
      ).length;

      if (pendingUploads > 0) {
        e.preventDefault();
        alert(
          `Please wait for ${pendingUploads} image(s) to finish uploading before submitting.`
        );
        return false;
      }

      asyncUploadManager.markSessionCompleted();
      if (isDevelopment) console.log(
        "[Add Listing] Session marked as completed on form submission"
      );

      // Clear file input to prevent duplicate uploads when using async system
      const fileInput = $("#car_images")[0];
      if (fileInput && fileInput.files && fileInput.files.length > 0) {
        fileInput.value = "";
        if (isDevelopment) console.log("[Add Listing] Cleared file input for async uploads");
      }
    } else {
      // For traditional uploads, ensure fileInput has correct files
      updateActualFileInput();
    }

    // Get the raw values from data attributes
    const rawMileage =
      mileageInput.data("raw-value") || unformatNumber(mileageInput.val());
    const rawPrice =
      priceInput.data("raw-value") ||
      unformatNumber(priceInput.val().replace("€", ""));
    const rawHp = $("#hp").data("raw-value") || unformatNumber($("#hp").val());

    // Create hidden inputs with the raw values
    $("<input>")
      .attr({
        type: "hidden",
        name: "mileage",
        value: rawMileage,
      })
      .appendTo(this);

    $("<input>")
      .attr({
        type: "hidden",
        name: "price",
        value: rawPrice,
      })
      .appendTo(this);

    $("<input>")
      .attr({
        type: "hidden",
        name: "hp",
        value: rawHp,
      })
      .appendTo(this);

    // Remove the original inputs from submission
    mileageInput.prop("disabled", true);
    priceInput.prop("disabled", true);
    $("#hp").prop("disabled", true);

    if (isDevelopment) console.log(
      "[Add Listing] Form validation passed, submitting with",
      totalImages,
      "images"
    );
    return true;
  });

  if (isDevelopment) console.log("[Add Listing] Elements found:", {
    fileInput: fileInput.length,
    fileUploadArea: fileUploadArea.length,
    imagePreview: imagePreview.length,
  });

  // Initialise drag & drop reordering
  enableImageReordering();

  // Handle click on upload area
  fileUploadArea.on("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (isDevelopment) console.log("[Add Listing] Upload area clicked");
    fileInput.trigger("click");
  });

  // Handle when files are selected through the file dialog
  fileInput.on("change", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const newlySelectedThroughDialog = Array.from(this.files);
    if (isDevelopment) console.log(
      "[Add Listing] Files selected through file dialog:",
      newlySelectedThroughDialog.length
    );
    if (newlySelectedThroughDialog.length > 0) {
      processNewFiles(newlySelectedThroughDialog);
    }
    // Clear the file input's displayed value to allow re-selecting the same file(s)
    // and ensure 'change' event fires consistently.
    $(this).val("");
  });

  // Handle drag and drop
  fileUploadArea.on("dragover", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass("dragover");
  });

  fileUploadArea.on("dragleave", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass("dragover");
  });

  fileUploadArea.on("drop", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass("dragover");
    const droppedFiles = Array.from(e.originalEvent.dataTransfer.files);
    if (isDevelopment) console.log("[Add Listing] Files dropped:", droppedFiles.length);
    processNewFiles(droppedFiles);
  });

  function processNewFiles(candidateFiles) {
    if (isDevelopment) console.log(
      "[Add Listing] Processing",
      candidateFiles.length,
      "new candidate files."
    );
    const maxFiles = 25;
    const maxFileSize = 12 * 1024 * 1024; // 12MB
            const allowedTypes = ["image/jpeg", "image/jfif", "image/pjpeg", "image/jpg", "image/x-jfif", "image/pipeg", "image/png", "image/gif", "image/webp"];
        const allowedExtensions = ["jpg", "jpeg", "jfif", "jpe", "png", "gif", "webp"];

    // Show processing indicator
    showImageProcessingIndicator(true);

    // Initialize the image optimizer (minimal processing - server handles WebP)
    const optimizer = new ImageOptimizer({
      maxWidth: 1920,
      maxHeight: 1080,
      quality: 0.8,
      maxFileSize: 12288, // 12MB in KB - only process very large files
      allowedTypes: allowedTypes,
    });

    // Process files asynchronously with optimization
    processFilesWithOptimization(
      candidateFiles,
      optimizer,
      maxFiles,
      allowedTypes,
      maxFileSize
    );
  }

  async function processFilesWithOptimization(
    candidateFiles,
    optimizer,
    maxFiles,
    allowedTypes,
    maxFileSize
  ) {
    let filesAddedThisBatchCount = 0;
    let totalSavings = 0;
    let totalOriginalSize = 0;
    let optimizationErrors = 0;

    try {
      for (const file of candidateFiles) {
        // Check if adding this file would exceed the maximum
        if (accumulatedFilesList.length >= maxFiles) {
          alert(
            "Maximum " + maxFiles + " files allowed. Some files were not added."
          );
          break;
        }

        // FIXED: Check for duplicates using ORIGINAL file properties (before optimization)
        const isDuplicate = accumulatedFilesList.some((existingFile) => {
          // Compare against original properties if they exist, otherwise current properties
          const existingOriginalName =
            existingFile.originalName || existingFile.name;
          const existingOriginalSize =
            existingFile.originalSize || existingFile.size;
          const existingOriginalType =
            existingFile.originalType || existingFile.type;

          return (
            existingOriginalName === file.name &&
            existingOriginalSize === file.size &&
            existingOriginalType === file.type
          );
        });

        if (isDuplicate) {
          if (isDevelopment) console.log(
            "[Add Listing] Skipping duplicate file (already selected):",
            file.name
          );
          continue;
        }

        if (!allowedTypes.includes(file.type)) {
          alert(
            'File type "' +
              file.type +
              '" not allowed for "' +
              file.name +
              '". Only JPG, PNG, GIF, and WebP are permitted.'
          );
          continue;
        }

        if (file.size > maxFileSize) {
          alert(
            'File "' +
              file.name +
              '" is too large (' +
              (file.size / (1024 * 1024)).toFixed(2) +
              "MB). Maximum allowed is " +
              maxFileSize / (1024 * 1024) +
              "MB."
          );
          continue;
        }

        try {
          // Update processing status
          updateProcessingStatus("Optimizing " + file.name + "...");

          const originalSize = file.size;
          totalOriginalSize += originalSize;

          // Optimize the image
          const optimizedFile = await optimizer.optimizeImage(file);
          const optimizedSize = optimizedFile.size;
          totalSavings += originalSize - optimizedSize;

          // FIXED: Store original file properties for future duplicate detection
          optimizedFile.originalName = file.name;
          optimizedFile.originalSize = file.size;
          optimizedFile.originalType = file.type;

          if (isDevelopment) console.log(
            "[Add Listing] File optimized:",
            file.name,
            "Original:",
            (originalSize / 1024).toFixed(2) + "KB",
            "Optimized:",
            (optimizedSize / 1024).toFixed(2) + "KB"
          );

          // Start async upload immediately after optimization
          if (asyncUploadManager) {
            try {
              updateProcessingStatus("Uploading " + file.name + "...");
              const fileKey = await asyncUploadManager.addFileToQueue(
                optimizedFile,
                file
              );

              // Store the file key for tracking
              optimizedFile.asyncFileKey = fileKey;
              optimizedFile.asyncUploadStatus = "uploading";

              if (isDevelopment) console.log(
                "[Add Listing] Started async upload for:",
                file.name,
                "Key:",
                fileKey
              );
            } catch (uploadError) {
              if (isDevelopment) console.error(
                "[Add Listing] Async upload start failed:",
                file.name,
                uploadError
              );
              // Continue with regular flow
            }
          }

          accumulatedFilesList.push(optimizedFile);
          createAndDisplayPreview(optimizedFile, originalSize, optimizedSize);
          filesAddedThisBatchCount++;
        } catch (error) {
          if (isDevelopment) console.error(
            "[Add Listing] Error optimizing image:",
            file.name,
            error
          );
          optimizationErrors++;

          // Fall back to original file if optimization fails
          if (isDevelopment) console.log(
            "[Add Listing] Using original file as fallback for:",
            file.name
          );

          // Even for fallback, store original properties for consistency
          file.originalName = file.name;
          file.originalSize = file.size;
          file.originalType = file.type;

          // Start async upload for fallback file too
          if (asyncUploadManager) {
            try {
              const fileKey = await asyncUploadManager.addFileToQueue(file);
              file.asyncFileKey = fileKey;
              file.asyncUploadStatus = "uploading";
            } catch (uploadError) {
              if (isDevelopment) console.error(
                "[Add Listing] Async upload start failed for fallback:",
                file.name,
                uploadError
              );
            }
          }

          accumulatedFilesList.push(file);
          createAndDisplayPreview(file);
          filesAddedThisBatchCount++;
        }
      }

      // Show optimization summary
      if (filesAddedThisBatchCount > 0) {
        updateActualFileInput(); // Refresh the actual file input

        if (totalSavings > 0) {
          const compressionPercent = (
            (totalSavings / totalOriginalSize) *
            100
          ).toFixed(1);
          showOptimizationSummary(
            filesAddedThisBatchCount,
            totalSavings,
            compressionPercent,
            optimizationErrors
          );
        }
      }

      if (isDevelopment) console.log(
        "[Add Listing] Processed batch. Accumulated files count:",
        accumulatedFilesList.length
      );
    } catch (error) {
      if (isDevelopment) console.error("[Add Listing] Error in batch processing:", error);
    } finally {
      // Hide processing indicator
      showImageProcessingIndicator(false);
    }
  }

  function showImageProcessingIndicator(show) {
    if (show) {
      if (!$("#image-processing-indicator").length) {
        const indicator = $(`
                    <div id="image-processing-indicator" class="image-processing-indicator">
                        <div class="processing-spinner"></div>
                        <div class="processing-text">Optimizing images...</div>
                        <div class="processing-status"></div>
                    </div>
                `);
        imagePreview.before(indicator);
      }
    } else {
      $("#image-processing-indicator").remove();
    }
  }

  function updateProcessingStatus(message) {
    $("#image-processing-indicator .processing-status").text(message);
  }

  function showOptimizationSummary(
    filesCount,
    totalSavings,
    compressionPercent,
    optimizationErrors
  ) {
    if (totalSavings > 0) {
      const summaryMessage = `✅ Optimized ${filesCount} image${
        filesCount > 1 ? "s" : ""
      }: Saved ${totalSavings.toFixed(
        1
      )} KB (${compressionPercent}% reduction)`;

      const summaryEl = $(
        `<div class="optimization-summary">${summaryMessage}</div>`
      );
      imagePreview.before(summaryEl);

      // Remove summary after 5 seconds
      setTimeout(() => {
        summaryEl.fadeOut(() => summaryEl.remove());
      }, 5000);
    }
  }

  function showErrorSummary(optimizationErrors, filesAddedThisBatchCount) {
    const errorMessage = `❌ ${optimizationErrors} image${
      optimizationErrors > 1 ? "s" : ""
    } were not optimized. Please check the images and try again.`;

    const errorEl = $(`<div class="error-summary">${errorMessage}</div>`);
    imagePreview.before(errorEl);

    // Remove summary after 5 seconds
    setTimeout(() => {
      errorEl.fadeOut(() => errorEl.remove());
    }, 5000);
  }

  function createAndDisplayPreview(file) {
    if (isDevelopment) console.log("[Add Listing] Creating preview for:", file.name);
    const reader = new FileReader();
    reader.onload = function (e) {
      const previewItem = $("<div>").addClass("image-preview-item");

      // Store reference to the underlying file object for reordering
      previewItem.data("fileObj", file);

      // Add async file key if available
      if (file.asyncFileKey) {
        previewItem
          .addClass("image-preview")
          .attr("data-async-key", file.asyncFileKey);
      }

      const img = $("<img>").attr({ src: e.target.result, alt: file.name });

      const removeBtn = $("<div>")
        .addClass("remove-image")
        .html('<i class="fas fa-times"></i>')
        .on("click", function () {
          if (isDevelopment) console.log("[Add Listing] Remove button clicked for:", file.name);

          // Remove from async system if applicable
          if (file.asyncFileKey && asyncUploadManager) {
            asyncUploadManager.removeImage(file.asyncFileKey).catch((error) => {
              if (isDevelopment) console.error(
                "[Add Listing] Failed to remove from async system:",
                error
              );
            });
          }

          removeFileFromSelection(file.name);
          previewItem.remove();
        });

      previewItem.append(img).append(removeBtn);
      attachSwapHandle(previewItem);

      // Add initial upload status if async upload is starting
      if (file.asyncFileKey) {
        previewItem.append(
          '<div class="upload-status upload-pending">⏳ Uploading...</div>'
        );
      }

      imagePreview.append(previewItem);
      if (isDevelopment) console.log("[Add Listing] Preview added to DOM for:", file.name);

      // Ensure the reorder drag behaviour applies to this new item
      previewItem.attr("draggable", "true");
      applyGrabCursor(previewItem);
      attachSwapHandle(previewItem);

      // Whenever we add a new async-uploaded image, refresh the order field
      updateAsyncImageOrderField();
    };
    reader.onerror = function () {
      if (isDevelopment) console.error("[Add Listing] Error reading file for preview:", file.name);
    };
    reader.readAsDataURL(file);
  }

  function createAndDisplayPreviewWithStats(
    optimizedFile,
    originalFile,
    stats
  ) {
    if (isDevelopment) console.log(
      "[Add Listing] Creating preview with stats for:",
      optimizedFile.name
    );
    const reader = new FileReader();
    reader.onload = function (e) {
      const previewItem = $("<div>").addClass("image-preview-item");
      previewItem.data("fileObj", optimizedFile);
      const img = $("<img>").attr({
        src: e.target.result,
        alt: optimizedFile.name,
      });

      // Add compression stats tooltip
      const statsTooltip = $(`
                <div class="compression-stats" title="Original: ${stats.originalSize}KB | Optimized: ${stats.optimizedSize}KB | Saved: ${stats.savings}KB (${stats.compressionRatio}%)">
                    <span class="stats-icon">📊</span>
                    <span class="stats-text">-${stats.compressionRatio}%</span>
                </div>
            `);

      const removeBtn = $("<div>")
        .addClass("remove-image")
        .html('<i class="fas fa-times"></i>')
        .on("click", function () {
          if (isDevelopment) console.log(
            "[Add Listing] Remove button clicked for:",
            optimizedFile.name
          );
          removeFileFromSelection(optimizedFile.name);
          previewItem.remove();
        });

      previewItem.append(img).append(statsTooltip).append(removeBtn);
      imagePreview.append(previewItem);
      previewItem.attr("draggable", "true");
      applyGrabCursor(previewItem);
      attachSwapHandle(previewItem);
      updateAsyncImageOrderField();
      if (isDevelopment) console.log(
        "[Add Listing] Preview with stats added to DOM for:",
        optimizedFile.name
      );
    };
    reader.onerror = function () {
      if (isDevelopment) console.error(
        "[Add Listing] Error reading file for preview:",
        optimizedFile.name
      );
    };
    reader.readAsDataURL(optimizedFile);
  }

  function removeFileFromSelection(fileNameToRemove) {
    if (isDevelopment) console.log(
      "[Add Listing] Attempting to remove file from selection:",
      fileNameToRemove
    );
    accumulatedFilesList = accumulatedFilesList.filter(
      (file) => file.name !== fileNameToRemove
    );
    updateActualFileInput(); // Refresh the actual file input
    if (isDevelopment) console.log(
      "[Add Listing] File removed. Accumulated files count:",
      accumulatedFilesList.length
    );
  }

  function updateActualFileInput() {
    const dataTransfer = new DataTransfer();
    accumulatedFilesList.forEach((file) => {
      try {
        dataTransfer.items.add(file);
      } catch (error) {
        if (isDevelopment) console.error(
          "[Add Listing] Error adding file to DataTransfer:",
          file.name,
          error
        );
      }
    });
    try {
      fileInput[0].files = dataTransfer.files;
    } catch (error) {
      if (isDevelopment) console.error(
        "[Add Listing] Error setting files on input element:",
        error
      );
    }
    if (isDevelopment) console.log(
      "[Add Listing] Actual file input updated. Count:",
      fileInput[0].files.length
    );
  }

  // Initialize and test image optimizer
  function initializeImageOptimizer() {
    if (typeof ImageOptimizer !== "undefined") {
      const testOptimizer = new ImageOptimizer();
      if (isDevelopment) console.log(
        "[Add Listing] ✅ Image optimization ready! Browser support:",
        testOptimizer.isSupported
      );
      if (isDevelopment) console.log("[Add Listing] Optimization settings:", {
        maxWidth: testOptimizer.maxWidth,
        maxHeight: testOptimizer.maxHeight,
        quality: testOptimizer.quality,
        maxFileSize: testOptimizer.maxFileSize + "KB",
      });
    } else {
      if (isDevelopment) console.error(
        "[Add Listing] ❌ ImageOptimizer class not found! Image optimization will not work."
      );
    }
  }

  // Test the optimizer on page load
  initializeImageOptimizer();

  /**
   * Async upload callback functions
   */
  function updateAsyncUploadProgress(fileKey, progress) {
    const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
    if ($preview.length) {
      let $progressBar = $preview.find(".upload-progress");
      if (!$progressBar.length) {
        $progressBar = $(
          '<div class="upload-progress"><div class="upload-progress-bar"></div><span class="upload-progress-text">0%</span></div>'
        );
        $preview.append($progressBar);
      }

      // Update CSS custom property for progress bar
      $progressBar
        .find(".upload-progress-bar")
        .css("--progress", progress + "%");
      $progressBar.find(".upload-progress-text").text(progress + "%");

      if (progress >= 100) {
        setTimeout(() => {
          $progressBar.fadeOut(() => $progressBar.remove());
        }, 1000);
      }
    }
  }

  function onAsyncUploadSuccess(fileKey, data) {
    const fileIndex = accumulatedFilesList.findIndex(
      (file) => file.asyncFileKey === fileKey
    );
    if (fileIndex !== -1) {
      accumulatedFilesList[fileIndex].asyncUploadStatus = "completed";
      accumulatedFilesList[fileIndex].attachmentId = data.attachment_id;

      const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
      $preview.find(".upload-status").remove();
      $preview.append(
        '<div class="upload-status upload-success">✓ Uploaded</div>'
      );

      setTimeout(() => {
        $preview.find(".upload-success").fadeOut(() => {
          $preview.find(".upload-success").remove();
        });
      }, 3000);

      if (isDevelopment) console.log(
        "[Add Listing] Async upload completed for:",
        data.original_filename
      );

      updateAsyncImageOrderField();
    }
  }

  function onAsyncUploadError(fileKey, error) {
    const fileIndex = accumulatedFilesList.findIndex(
      (file) => file.asyncFileKey === fileKey
    );
    if (fileIndex !== -1) {
      accumulatedFilesList[fileIndex].asyncUploadStatus = "failed";
      accumulatedFilesList[fileIndex].asyncUploadError = error.message;

      const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
      $preview.find(".upload-status").remove();
      $preview.append(
        '<div class="upload-status upload-error">✗ Upload failed</div>'
      );

      // Show fallback message below upload area
      showAsyncUploadFallbackMessage();

      if (isDevelopment) console.error(
        "[Add Listing] Async upload failed for file key:",
        fileKey,
        error
      );
    }
  }

  function showAsyncUploadFallbackMessage() {
    // Only show if not already shown
    if ($(".async-upload-fallback-message").length === 0) {
      const message = $(`
                <div class="async-upload-fallback-message">
                    <i class="fas fa-info-circle fallback-icon"></i>
                    <span>Background upload failed but images will submit normally when you press submit. You may continue filling the form.</span>
                </div>
            `);

      // Insert after the file upload area
      fileUploadArea.after(message);
    }
  }

  function onAsyncImageRemoved(fileKey) {
    // Remove from accumulated files list
    const fileIndex = accumulatedFilesList.findIndex(
      (file) => file.asyncFileKey === fileKey
    );
    if (fileIndex !== -1) {
      accumulatedFilesList.splice(fileIndex, 1);
      updateActualFileInput();
    }

    // Remove preview element
    $(`.image-preview[data-async-key="${fileKey}"]`).fadeOut(() => {
      $(`.image-preview[data-async-key="${fileKey}"]`).remove();
    });

    if (isDevelopment) console.log("[Add Listing] Image removed from async system:", fileKey);
  }

  // =====================================================
  // SAVED LOCATIONS FUNCTIONALITY
  // =====================================================

  // Store saved locations data for reference
  let savedLocationsData = [];

  /**
   * Fetch and display user's saved locations from past listings
   */
  function initSavedLocations() {
    const $wrapper = $("#saved-locations-wrapper");
    const $selectorWrapper = $(".location-selector-wrapper");

    if (!$wrapper.length) {
      if (isDevelopment) console.log("[Add Listing] Saved locations wrapper not found");
      return;
    }

    // Fetch saved locations via AJAX
    $.ajax({
      url: addListingData.ajaxurl,
      type: "POST",
      data: {
        action: "get_user_saved_locations",
        nonce: addListingData.nonce,
      },
      success: function (response) {
        if (response.success && response.data && response.data.length > 0) {
          savedLocationsData = response.data;

          // Build options array for the dropdown
          const options = savedLocationsData.map(function (loc, index) {
            return {
              value: index.toString(),
              label: loc.address,
              locationData: JSON.stringify(loc),
            };
          });

          // Update dropdown options
          updateSavedLocationsDropdown(options);

          if (isDevelopment) console.log("[Add Listing] Loaded", savedLocationsData.length, "saved locations");
        } else {
          if (isDevelopment) console.log("[Add Listing] No saved locations found");
          // Add class to hide dropdown and OR, show only button
          $selectorWrapper.addClass("no-saved-locations");
        }
      },
      error: function (xhr, status, error) {
        if (isDevelopment) console.error("[Add Listing] Error fetching saved locations:", error);
        $selectorWrapper.addClass("no-saved-locations");
      },
    });

    // Initialize clear button handler
    initClearLocationButton();
  }

  /**
   * Update the saved locations dropdown with options
   */
  function updateSavedLocationsDropdown(options) {
    const $dropdownWrapper = $("#saved-locations-wrapper");
    const $options = $dropdownWrapper.find(".car-filter-dropdown-options");
    const $select = $dropdownWrapper.find("select");
    const $button = $dropdownWrapper.find(".car-filter-dropdown-button");
    const $search = $dropdownWrapper.find(".car-filter-dropdown-search");

    const placeholder = "Recently used locations";

    // Build options HTML
    let html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="">' + placeholder + "</button>";
    let selectHtml = '<option value="">' + placeholder + "</option>";

    options.forEach(function (opt) {
      html += '<button type="button" class="car-filter-dropdown-option" role="option" data-value="' +
        opt.value + '" data-location=\'' + opt.locationData + '\'>' + opt.label + "</button>";
      selectHtml += '<option value="' + opt.value + '" data-location=\'' + opt.locationData + '\'>' + opt.label + "</option>";
    });

    html += '<div class="car-filter-no-results hidden">No matching results</div>';

    $options.html(html);
    $select.html(selectHtml);

    // Reset button text
    $button.find(".car-filter-dropdown-text").addClass("placeholder").text(placeholder);

    // Enable dropdown
    $button.prop("disabled", false);
    $select.prop("disabled", false);
    $dropdownWrapper.find(".car-filter-dropdown").removeClass("car-filter-dropdown-disabled");
    $search.prop("disabled", false);

    // Bind click handler for saved location options
    $options.off("click.savedLocation").on("click.savedLocation", ".car-filter-dropdown-option", function () {
      const $option = $(this);
      const locationDataStr = $option.data("location");
      const value = $option.data("value");

      if (locationDataStr) {
        const locationData = typeof locationDataStr === "string" ? JSON.parse(locationDataStr) : locationDataStr;
        applySavedLocation(locationData);
      } else {
        // Placeholder selected - clear location
        clearLocation();
        return;
      }

      // Update dropdown UI
      const label = $option.text().trim();

      $select.val(value);
      $options.find(".car-filter-dropdown-option").removeClass("selected");
      $option.addClass("selected");

      $button.find(".car-filter-dropdown-text")
        .removeClass("placeholder")
        .addClass("location-selected")
        .text(label);

      // Show clear button and hide dropdown arrow
      $("#clear-location-btn").show();
      $("#saved-locations-wrapper").addClass("has-location");

      // Close dropdown
      $dropdownWrapper.find(".car-filter-dropdown").removeClass("open");
      $button.attr("aria-expanded", "false");
    });
  }

  /**
   * Apply selected saved location to the form
   */
  function applySavedLocation(locationData) {
    if (!locationData) return;

    if (isDevelopment) console.log("[Add Listing] Applying saved location:", locationData);

    // Update hidden location field
    const $locationField = $("#location");
    if ($locationField.length) {
      $locationField.val(locationData.address);
    }

    // Get the form reference
    const $form = $("#add-car-listing-form");

    // Remove any existing hidden location fields
    $form.find('input[name="car_city"], input[name="car_district"], input[name="car_latitude"], input[name="car_longitude"], input[name="car_address"]').remove();

    // Round coordinates to 6 decimal places
    const roundCoord = (num) => parseFloat(Number(num).toFixed(6));

    // Create hidden fields with location data
    const fields = {
      car_city: locationData.city || "",
      car_district: locationData.district || locationData.city || "",
      car_latitude: roundCoord(locationData.latitude || 0),
      car_longitude: roundCoord(locationData.longitude || 0),
      car_address: locationData.address || "",
    };

    Object.entries(fields).forEach(function ([name, value]) {
      const $input = $('<input type="hidden">').attr("name", name).val(value);
      $form.append($input);
    });

    // Remove error state from location row if present
    $("#location-row").removeClass("has-error");

    // Show clear button and hide dropdown arrow
    $("#clear-location-btn").show();
    $("#saved-locations-wrapper").addClass("has-location");

    if (isDevelopment) console.log("[Add Listing] Saved location applied successfully");
  }

  /**
   * Update dropdown to show location selected via map picker
   */
  function showLocationInDropdown(address) {
    const $dropdownWrapper = $("#saved-locations-wrapper");
    const $button = $dropdownWrapper.find(".car-filter-dropdown-button");
    const $options = $dropdownWrapper.find(".car-filter-dropdown-options");
    const $select = $dropdownWrapper.find("select");

    // Update button text to show the selected address
    $button.find(".car-filter-dropdown-text")
      .removeClass("placeholder")
      .addClass("location-selected")
      .text(address);

    // Deselect all options (since this is a custom location from map)
    $options.find(".car-filter-dropdown-option").removeClass("selected");
    $options.find('.car-filter-dropdown-option[data-value=""]').addClass("selected");
    $select.val("");

    // Show clear button and hide dropdown arrow
    $("#clear-location-btn").show();
    $dropdownWrapper.addClass("has-location");
  }

  /**
   * Initialize clear location button
   */
  function initClearLocationButton() {
    $("#clear-location-btn").off("click").on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      clearLocation();
    });
  }

  /**
   * Clear the selected location
   */
  function clearLocation() {
    const $dropdownWrapper = $("#saved-locations-wrapper");
    const $button = $dropdownWrapper.find(".car-filter-dropdown-button");
    const $options = $dropdownWrapper.find(".car-filter-dropdown-options");
    const $select = $dropdownWrapper.find("select");
    const $form = $("#add-car-listing-form");

    // Reset dropdown to placeholder
    $button.find(".car-filter-dropdown-text")
      .addClass("placeholder")
      .removeClass("location-selected")
      .text("Select location");

    // Reset selection
    $options.find(".car-filter-dropdown-option").removeClass("selected");
    $options.find('.car-filter-dropdown-option[data-value=""]').addClass("selected");
    $select.val("");

    // Clear hidden location field
    $("#location").val("");

    // Remove hidden location fields from form
    $form.find('input[name="car_city"], input[name="car_district"], input[name="car_latitude"], input[name="car_longitude"], input[name="car_address"]').remove();

    // Hide clear button and show dropdown arrow
    $("#clear-location-btn").hide();
    $dropdownWrapper.removeClass("has-location");

    if (isDevelopment) console.log("[Add Listing] Location cleared");
  }

  // Make showLocationInDropdown available globally for location-picker.js
  window.showLocationInDropdown = showLocationInDropdown;

  // Initialize saved locations on page load
  initSavedLocations();
});
