/**
 * Homepage Filters JavaScript
 */

(function ($) {
  "use strict";

  let priceSlider = null;
  let mileageSlider = null;
  let filterData = null;
  let currentRanges = {
    price: { min: 0, max: 1000000 },
    mileage: { min: 0, max: 500000 },
  };
  let selectedMake = null;
  let selectedModel = null;
  let firstSelectedFilter = null; // Track which filter was selected first

  /**
   * Format number with commas
   */
  function formatNumber(num) {
    if (num === null || num === undefined || isNaN(num)) {
      return "";
    }
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  /**
   * Parse formatted number (remove commas)
   */
  function parseFormattedNumber(str) {
    if (!str || str === "" || str === "Min" || str === "Max") {
      return null;
    }
    const cleaned = str.replace(/,/g, "").trim();
    if (cleaned === "") {
      return null;
    }
    const parsed = parseInt(cleaned, 10);
    return isNaN(parsed) ? null : parsed;
  }

  /**
   * Initialize filters when DOM is ready
   */
  $(document).ready(function () {
    // Get filter data from JSON script tag
    const dataScript = $("#homepage-filters-data");
    if (dataScript.length) {
      try {
        filterData = JSON.parse(dataScript.html());
        currentRanges = filterData.ranges;
      } catch (e) {
        console.error("Error parsing filter data:", e);
        return;
      }
    } else {
      console.error("Filter data script not found");
      return;
    }

    initializeSliders();
    initializeSelects();
    initializeSearchButton();
  });

  /**
   * Initialize noUiSlider instances for price and mileage
   */
  function initializeSliders() {
    // Price Slider - COMMENTED OUT
    // const priceSliderEl = document.getElementById(
    //   "homepage-filter-price-slider"
    // );
    // if (priceSliderEl) {
    //   priceSlider = noUiSlider.create(priceSliderEl, {
    //     start: [currentRanges.price.min, currentRanges.price.max],
    //     connect: true,
    //     range: {
    //       min: currentRanges.price.min,
    //       max: currentRanges.price.max,
    //     },
    //     step: 100,
    //     format: {
    //       to: function (value) {
    //         return Math.round(value);
    //       },
    //       from: function (value) {
    //         return Number(value);
    //       },
    //     },
    //   });

    // Set initial values
    $("#homepage-filter-price-min").val(formatNumber(currentRanges.price.min));
    $("#homepage-filter-price-max").val(formatNumber(currentRanges.price.max));

    // Update inputs when slider changes - COMMENTED OUT
    // priceSlider.on("update", function (values) {
    //   const minFormatted = formatNumber(Math.round(values[0]));
    //   const maxFormatted = formatNumber(Math.round(values[1]));
    //   $("#homepage-filter-price-min").val(minFormatted);
    //   $("#homepage-filter-price-max").val(maxFormatted);
    // });

    // Format on input (as user types) - only format if there's a value
    $("#homepage-filter-price-min, #homepage-filter-price-max").on(
      "input",
      function () {
        const $input = $(this);
        const value = $input.val();

        // Allow empty or just commas
        if (value === "" || value === ",") {
          return;
        }

        const cursorPos = this.selectionStart;
        const numericValue = parseFormattedNumber(value);

        if (numericValue !== null && !isNaN(numericValue)) {
          const formatted = formatNumber(numericValue);
          $input.val(formatted);

          // Restore cursor position
          const diff = formatted.length - value.length;
          const newCursorPos = Math.max(
            0,
            Math.min(cursorPos + diff, formatted.length)
          );
          this.setSelectionRange(newCursorPos, newCursorPos);
        }
      }
    );

    // Update slider when inputs change - COMMENTED OUT
    // $("#homepage-filter-price-min, #homepage-filter-price-max").on(
    //   "change",
    //   function () {
    //     const minVal = $("#homepage-filter-price-min").val();
    //     const maxVal = $("#homepage-filter-price-max").val();
    //     const min = parseFormattedNumber(minVal) || currentRanges.price.min;
    //     const max = parseFormattedNumber(maxVal) || currentRanges.price.max;
    //     priceSlider.set([min, max]);
    //   }
    // );
    // }

    // Mileage Slider - COMMENTED OUT
    // const mileageSliderEl = document.getElementById(
    //   "homepage-filter-mileage-slider"
    // );
    // if (mileageSliderEl) {
    //   mileageSlider = noUiSlider.create(mileageSliderEl, {
    //     start: [currentRanges.mileage.min, currentRanges.mileage.max],
    //     connect: true,
    //     range: {
    //       min: currentRanges.mileage.min,
    //       max: currentRanges.mileage.max,
    //     },
    //     step: 1000,
    //     format: {
    //       to: function (value) {
    //         return Math.round(value);
    //       },
    //       from: function (value) {
    //         return Number(value);
    //       },
    //     },
    //   });

    // Set initial values
    $("#homepage-filter-mileage-min").val(
      formatNumber(currentRanges.mileage.min)
    );
    $("#homepage-filter-mileage-max").val(
      formatNumber(currentRanges.mileage.max)
    );

    // Update inputs when slider changes - COMMENTED OUT
    // mileageSlider.on("update", function (values) {
    //   const minFormatted = formatNumber(Math.round(values[0]));
    //   const maxFormatted = formatNumber(Math.round(values[1]));
    //   $("#homepage-filter-mileage-min").val(minFormatted);
    //   $("#homepage-filter-mileage-max").val(maxFormatted);
    // });

    // Format on input (as user types) - only format if there's a value
    $("#homepage-filter-mileage-min, #homepage-filter-mileage-max").on(
      "input",
      function () {
        const $input = $(this);
        const value = $input.val();

        // Allow empty or just commas
        if (value === "" || value === ",") {
          return;
        }

        const cursorPos = this.selectionStart;
        const numericValue = parseFormattedNumber(value);

        if (numericValue !== null && !isNaN(numericValue)) {
          const formatted = formatNumber(numericValue);
          $input.val(formatted);

          // Restore cursor position
          const diff = formatted.length - value.length;
          const newCursorPos = Math.max(
            0,
            Math.min(cursorPos + diff, formatted.length)
          );
          this.setSelectionRange(newCursorPos, newCursorPos);
        }
      }
    );

    // Update slider when inputs change - COMMENTED OUT
    // $("#homepage-filter-mileage-min, #homepage-filter-mileage-max").on(
    //   "change",
    //   function () {
    //     const minVal = $("#homepage-filter-mileage-min").val();
    //     const maxVal = $("#homepage-filter-mileage-max").val();
    //     const min = parseFormattedNumber(minVal) || currentRanges.mileage.min;
    //     const max = parseFormattedNumber(maxVal) || currentRanges.mileage.max;
    //     mileageSlider.set([min, max]);
    //   }
    // );
    // }
  }

  /**
   * Initialize make/model custom dropdowns with search functionality
   */
  function initializeSelects() {
    initializeDropdown("make");
    initializeDropdown("model");

    // Click/touch outside to close dropdowns
    $(document).on("click touchend", function (e) {
      // Don't close if clicking inside dropdown
      if (!$(e.target).closest(".homepage-filters-dropdown").length) {
        closeAllDropdowns();
      }
    });
  }

  /**
   * Initialize a single dropdown (make or model)
   */
  function initializeDropdown(type) {
    const dropdown = $(`.homepage-filters-dropdown[data-filter="${type}"]`);
    const button = $(`#homepage-filter-${type}-button`);
    const menu = $(`#homepage-filter-${type}-menu`);
    const search = $(`#homepage-filter-${type}-search`);
    const options = $(`#homepage-filter-${type}-options`);
    const hiddenSelect = $(`#homepage-filter-${type}`);

    // Track if touch was used to prevent double-firing on mobile
    let touchUsed = false;

    // Toggle dropdown on button click/touch
    function handleButtonToggle(e) {
      e.stopPropagation();
      if (button.prop("disabled")) return;

      const isOpen = menu.hasClass("open");
      closeAllDropdowns();

      if (!isOpen) {
        openDropdown(type);
        // Small delay for mobile to ensure menu is visible before focusing
        setTimeout(function () {
          search.focus();
        }, 100);
      }
    }

    // Handle touch events for mobile
    button.on("touchend", function (e) {
      touchUsed = true;
      handleButtonToggle(e);
      // Prevent click event from firing after touch
      setTimeout(function () {
        touchUsed = false;
      }, 300);
    });

    // Handle click events (desktop and mobile fallback)
    button.on("click", function (e) {
      if (!touchUsed) {
        handleButtonToggle(e);
      }
    });

    // Track touch for option selection
    let optionTouchUsed = false;

    // Handle option selection with touch
    options.on("touchend", ".homepage-filters-dropdown-option", function (e) {
      optionTouchUsed = true;
      e.stopPropagation();
      e.preventDefault();
      const $option = $(this);
      const value = $option.data("value");
      const slug = $option.data("slug");
      const text = $option.text().trim();

      // Update button text
      const buttonText = button.find(".homepage-filters-dropdown-text");
      buttonText.text(text).removeClass("placeholder");

      // Update hidden select
      hiddenSelect.val(value).trigger("change");

      // Update selected state
      options.find(".homepage-filters-dropdown-option").removeClass("selected");
      $option.addClass("selected");

      // Close dropdown
      closeDropdown(type);

      // Handle selection logic
      if (type === "make") {
        handleMakeSelection(value, slug);
      } else if (type === "model") {
        handleModelSelection(value, slug);
      }

      // Reset touch flag
      setTimeout(function () {
        optionTouchUsed = false;
      }, 300);
    });

    // Handle option selection with click
    options.on("click", ".homepage-filters-dropdown-option", function (e) {
      if (!optionTouchUsed) {
        e.stopPropagation();
        const $option = $(this);
        const value = $option.data("value");
        const slug = $option.data("slug");
        const text = $option.text().trim();

        // Update button text
        const buttonText = button.find(".homepage-filters-dropdown-text");
        buttonText.text(text).removeClass("placeholder");

        // Update hidden select
        hiddenSelect.val(value).trigger("change");

        // Update selected state
        options
          .find(".homepage-filters-dropdown-option")
          .removeClass("selected");
        $option.addClass("selected");

        // Close dropdown
        closeDropdown(type);

        // Handle selection logic
        if (type === "make") {
          handleMakeSelection(value, slug);
        } else if (type === "model") {
          handleModelSelection(value, slug);
        }
      }
    });

    // Search functionality
    search.on("input", function () {
      const searchTerm = $(this).val().toLowerCase();
      filterDropdownOptions(options, searchTerm);
    });

    // Prevent dropdown from closing when clicking/touching inside
    menu.on("click touchend", function (e) {
      e.stopPropagation();
    });
  }

  /**
   * Open a dropdown
   */
  function openDropdown(type) {
    const menu = $(`#homepage-filter-${type}-menu`);
    const button = $(`#homepage-filter-${type}-button`);

    menu.addClass("open");
    button.addClass("homepage-filters-dropdown-button-open");
    button.attr("aria-expanded", "true");
  }

  /**
   * Close a dropdown
   */
  function closeDropdown(type) {
    const menu = $(`#homepage-filter-${type}-menu`);
    const button = $(`#homepage-filter-${type}-button`);
    const search = $(`#homepage-filter-${type}-search`);

    menu.removeClass("open");
    button.removeClass("homepage-filters-dropdown-button-open");
    button.attr("aria-expanded", "false");
    search.val("");
    filterDropdownOptions($(`#homepage-filter-${type}-options`), "");
  }

  /**
   * Close all dropdowns
   */
  function closeAllDropdowns() {
    closeDropdown("make");
    closeDropdown("model");
  }

  /**
   * Filter dropdown options based on search term
   */
  function filterDropdownOptions($optionsContainer, searchTerm) {
    $optionsContainer
      .find(".homepage-filters-dropdown-option")
      .each(function () {
        const $option = $(this);
        const text = $option.text().toLowerCase();

        if (!searchTerm || text.includes(searchTerm.toLowerCase())) {
          $option.removeClass("hidden");
        } else {
          $option.addClass("hidden");
        }
      });
  }

  /**
   * Handle make selection
   */
  function handleMakeSelection(makeTermId, makeSlug) {
    selectedMake = makeTermId ? { id: makeTermId, slug: makeSlug } : null;
    selectedModel = null;

    // Reset model dropdown
    const modelButton = $("#homepage-filter-model-button");
    const modelButtonText = modelButton.find(".homepage-filters-dropdown-text");
    const modelOptions = $("#homepage-filter-model-options");
    const modelHiddenSelect = $("#homepage-filter-model");
    const modelSearch = $("#homepage-filter-model-search");

    modelButton
      .prop("disabled", true)
      .addClass("homepage-filters-dropdown-button-disabled");
    modelButtonText.text("Select Model").addClass("placeholder");
    modelOptions.empty();
    modelHiddenSelect
      .prop("disabled", true)
      .html('<option value="">Select Model</option>');
    modelSearch.prop("disabled", true);

    if (makeTermId) {
      if (!firstSelectedFilter) {
        firstSelectedFilter = "make";
      }
      loadModels(makeTermId);
      updateRanges();
    } else {
      firstSelectedFilter = null;
      resetRanges();
    }
  }

  /**
   * Handle model selection
   */
  function handleModelSelection(modelTermId, modelSlug) {
    selectedModel = modelTermId ? { id: modelTermId, slug: modelSlug } : null;

    if (modelTermId) {
      if (!firstSelectedFilter) {
        firstSelectedFilter = "model";
      }
      updateRanges();
    } else {
      if (firstSelectedFilter === "model") {
        firstSelectedFilter = selectedMake ? "make" : null;
      }
      updateRanges();
    }
  }

  /**
   * Load models for selected make
   */
  function loadModels(makeTermId) {
    const modelOptions = $("#homepage-filter-model-options");
    const modelHiddenSelect = $("#homepage-filter-model");
    const modelButton = $("#homepage-filter-model-button");
    const modelSearch = $("#homepage-filter-model-search");

    // Use AJAX to get models from taxonomy
    $.ajax({
      url: filterData.ajaxUrl,
      type: "POST",
      data: {
        action: "homepage_filters_get_models",
        make_term_id: makeTermId,
        nonce: filterData.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          // Clear existing options
          modelOptions.empty();
          modelHiddenSelect.html('<option value="">Select Model</option>');

          // Build options for custom dropdown and hidden select
          response.data.forEach(function (model) {
            // Add to custom dropdown
            const $option = $("<button>", {
              type: "button",
              class: "homepage-filters-dropdown-option",
              "data-value": model.term_id,
              "data-slug": model.slug,
              text: model.name,
            });
            modelOptions.append($option);

            // Add to hidden select
            const $hiddenOption = $("<option>", {
              value: model.term_id,
              "data-slug": model.slug,
              text: model.name,
            });
            modelHiddenSelect.append($hiddenOption);
          });

          // Enable dropdown
          modelButton
            .prop("disabled", false)
            .removeClass("homepage-filters-dropdown-button-disabled");
          modelSearch.prop("disabled", false);
        }
      },
      error: function () {
        console.error("Error loading models");
      },
    });
  }

  /**
   * Update slider ranges based on selected make/model
   */
  function updateRanges() {
    const makeTermId = selectedMake ? selectedMake.id : 0;
    const modelTermId = selectedModel ? selectedModel.id : 0;

    $.ajax({
      url: filterData.ajaxUrl,
      type: "POST",
      data: {
        action: "homepage_filters_get_ranges",
        make_term_id: makeTermId,
        model_term_id: modelTermId,
        nonce: filterData.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          currentRanges = response.data;

          // Update price inputs
          $("#homepage-filter-price-min").val(
            formatNumber(currentRanges.price.min)
          );
          $("#homepage-filter-price-max").val(
            formatNumber(currentRanges.price.max)
          );

          // Update mileage inputs
          $("#homepage-filter-mileage-min").val(
            formatNumber(currentRanges.mileage.min)
          );
          $("#homepage-filter-mileage-max").val(
            formatNumber(currentRanges.mileage.max)
          );

          // Update price slider - COMMENTED OUT
          // if (priceSlider) {
          //   priceSlider.updateOptions({
          //     range: {
          //       min: currentRanges.price.min,
          //       max: currentRanges.price.max,
          //     },
          //   });
          //   priceSlider.set([currentRanges.price.min, currentRanges.price.max]);
          // }

          // Update mileage slider - COMMENTED OUT
          // if (mileageSlider) {
          //   mileageSlider.updateOptions({
          //     range: {
          //       min: currentRanges.mileage.min,
          //       max: currentRanges.mileage.max,
          //     },
          //   });
          //   mileageSlider.set([
          //     currentRanges.mileage.min,
          //     currentRanges.mileage.max,
          //   ]);
          // }
        }
      },
      error: function () {
        console.error("Error updating ranges");
      },
    });
  }

  /**
   * Reset ranges to global values
   */
  function resetRanges() {
    currentRanges = filterData.ranges;

    // Update price inputs
    $("#homepage-filter-price-min").val(formatNumber(currentRanges.price.min));
    $("#homepage-filter-price-max").val(formatNumber(currentRanges.price.max));

    // Update mileage inputs
    $("#homepage-filter-mileage-min").val(
      formatNumber(currentRanges.mileage.min)
    );
    $("#homepage-filter-mileage-max").val(
      formatNumber(currentRanges.mileage.max)
    );

    // Update price slider - COMMENTED OUT
    // if (priceSlider) {
    //   priceSlider.updateOptions({
    //     range: {
    //       min: currentRanges.price.min,
    //       max: currentRanges.price.max,
    //     },
    //   });
    //   priceSlider.set([currentRanges.price.min, currentRanges.price.max]);
    // }

    // Update mileage slider - COMMENTED OUT
    // if (mileageSlider) {
    //   mileageSlider.updateOptions({
    //     range: {
    //       min: currentRanges.mileage.min,
    //       max: currentRanges.mileage.max,
    //     },
    //   });
    //   mileageSlider.set([currentRanges.mileage.min, currentRanges.mileage.max]);
    // }
  }

  /**
   * Initialize search button and URL generation
   */
  function initializeSearchButton() {
    $("#homepage-filters-search-btn").on("click", function () {
      const url = buildFilterUrl();
      if (url) {
        window.location.href = url;
      }
    });
  }

  /**
   * Build JetSmartFilters URL from current filter selections
   */
  function buildFilterUrl() {
    let url = filterData.baseUrl;
    const parts = [];

    // Build make/model part
    // If model is selected, use model slug (it already includes make slug)
    // Otherwise, use make slug if only make is selected
    if (selectedModel) {
      parts.push("make:" + selectedModel.slug);
    } else if (selectedMake) {
      parts.push("make:" + selectedMake.slug);
    }

    // Build meta filters part
    const metaParts = [];

    // Price range
    const priceMinVal = $("#homepage-filter-price-min").val();
    const priceMaxVal = $("#homepage-filter-price-max").val();
    const priceMin =
      parseFormattedNumber(priceMinVal) || currentRanges.price.min;
    const priceMax =
      parseFormattedNumber(priceMaxVal) || currentRanges.price.max;
    if (
      priceMin !== currentRanges.price.min ||
      priceMax !== currentRanges.price.max
    ) {
      metaParts.push("price!range:" + priceMin + "_" + priceMax);
    }

    // Mileage range
    const mileageMinVal = $("#homepage-filter-mileage-min").val();
    const mileageMaxVal = $("#homepage-filter-mileage-max").val();
    const mileageMin =
      parseFormattedNumber(mileageMinVal) || currentRanges.mileage.min;
    const mileageMax =
      parseFormattedNumber(mileageMaxVal) || currentRanges.mileage.max;
    if (
      mileageMin !== currentRanges.mileage.min ||
      mileageMax !== currentRanges.mileage.max
    ) {
      metaParts.push("mileage!range:" + mileageMin + "_" + mileageMax);
    }

    // Combine parts
    if (parts.length > 0) {
      url += parts.join("/");
    }

    if (metaParts.length > 0) {
      url += "/meta/" + metaParts.join(";");
    }

    url += "/";

    return url;
  }
})(jQuery);
