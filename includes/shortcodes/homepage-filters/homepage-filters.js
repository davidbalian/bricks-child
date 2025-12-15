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
    // Price Slider
    const priceSliderEl = document.getElementById(
      "homepage-filter-price-slider"
    );
    if (priceSliderEl) {
      priceSlider = noUiSlider.create(priceSliderEl, {
        start: [currentRanges.price.min, currentRanges.price.max],
        connect: true,
        range: {
          min: currentRanges.price.min,
          max: currentRanges.price.max,
        },
        step: 100,
        format: {
          to: function (value) {
            return Math.round(value);
          },
          from: function (value) {
            return Number(value);
          },
        },
      });

      // Update inputs when slider changes
      priceSlider.on("update", function (values) {
        $("#homepage-filter-price-min").val(Math.round(values[0]));
        $("#homepage-filter-price-max").val(Math.round(values[1]));
      });

      // Update slider when inputs change
      $("#homepage-filter-price-min, #homepage-filter-price-max").on(
        "change",
        function () {
          const minVal = $("#homepage-filter-price-min").val();
          const maxVal = $("#homepage-filter-price-max").val();
          const min =
            minVal !== "" && !isNaN(minVal)
              ? parseInt(minVal)
              : currentRanges.price.min;
          const max =
            maxVal !== "" && !isNaN(maxVal)
              ? parseInt(maxVal)
              : currentRanges.price.max;
          priceSlider.set([min, max]);
        }
      );
    }

    // Mileage Slider
    const mileageSliderEl = document.getElementById(
      "homepage-filter-mileage-slider"
    );
    if (mileageSliderEl) {
      mileageSlider = noUiSlider.create(mileageSliderEl, {
        start: [currentRanges.mileage.min, currentRanges.mileage.max],
        connect: true,
        range: {
          min: currentRanges.mileage.min,
          max: currentRanges.mileage.max,
        },
        step: 1000,
        format: {
          to: function (value) {
            return Math.round(value);
          },
          from: function (value) {
            return Number(value);
          },
        },
      });

      // Update inputs when slider changes
      mileageSlider.on("update", function (values) {
        $("#homepage-filter-mileage-min").val(Math.round(values[0]));
        $("#homepage-filter-mileage-max").val(Math.round(values[1]));
      });

      // Update slider when inputs change
      $("#homepage-filter-mileage-min, #homepage-filter-mileage-max").on(
        "change",
        function () {
          const minVal = $("#homepage-filter-mileage-min").val();
          const maxVal = $("#homepage-filter-mileage-max").val();
          const min =
            minVal !== "" && !isNaN(minVal)
              ? parseInt(minVal)
              : currentRanges.mileage.min;
          const max =
            maxVal !== "" && !isNaN(maxVal)
              ? parseInt(maxVal)
              : currentRanges.mileage.max;
          mileageSlider.set([min, max]);
        }
      );
    }
  }

  /**
   * Initialize make/model custom dropdowns with search functionality
   */
  function initializeSelects() {
    initializeDropdown("make");
    initializeDropdown("model");

    // Click outside to close dropdowns
    $(document).on("click", function (e) {
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

    // Toggle dropdown on button click
    button.on("click", function (e) {
      e.stopPropagation();
      if ($(this).prop("disabled")) return;

      const isOpen = menu.hasClass("open");
      closeAllDropdowns();

      if (!isOpen) {
        openDropdown(type);
        search.focus();
      }
    });

    // Handle option selection
    options.on("click", ".homepage-filters-dropdown-option", function (e) {
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
    });

    // Search functionality
    search.on("input", function () {
      const searchTerm = $(this).val().toLowerCase();
      filterDropdownOptions(options, searchTerm);
    });

    // Prevent dropdown from closing when clicking inside
    menu.on("click", function (e) {
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

          // Update price slider
          if (priceSlider) {
            priceSlider.updateOptions({
              range: {
                min: currentRanges.price.min,
                max: currentRanges.price.max,
              },
            });
            priceSlider.set([currentRanges.price.min, currentRanges.price.max]);
          }

          // Update mileage slider
          if (mileageSlider) {
            mileageSlider.updateOptions({
              range: {
                min: currentRanges.mileage.min,
                max: currentRanges.mileage.max,
              },
            });
            mileageSlider.set([
              currentRanges.mileage.min,
              currentRanges.mileage.max,
            ]);
          }
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

    if (priceSlider) {
      priceSlider.updateOptions({
        range: {
          min: currentRanges.price.min,
          max: currentRanges.price.max,
        },
      });
      priceSlider.set([currentRanges.price.min, currentRanges.price.max]);
    }

    if (mileageSlider) {
      mileageSlider.updateOptions({
        range: {
          min: currentRanges.mileage.min,
          max: currentRanges.mileage.max,
        },
      });
      mileageSlider.set([currentRanges.mileage.min, currentRanges.mileage.max]);
    }
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
    if (selectedMake) {
      let makePart = "make:" + selectedMake.slug;
      if (selectedModel) {
        makePart += "-" + selectedModel.slug;
      }
      parts.push(makePart);
    }

    // Build meta filters part
    const metaParts = [];

    // Price range
    const priceMinVal = $("#homepage-filter-price-min").val();
    const priceMaxVal = $("#homepage-filter-price-max").val();
    const priceMin =
      priceMinVal !== "" && !isNaN(priceMinVal)
        ? parseInt(priceMinVal)
        : currentRanges.price.min;
    const priceMax =
      priceMaxVal !== "" && !isNaN(priceMaxVal)
        ? parseInt(priceMaxVal)
        : currentRanges.price.max;
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
      mileageMinVal !== "" && !isNaN(mileageMinVal)
        ? parseInt(mileageMinVal)
        : currentRanges.mileage.min;
    const mileageMax =
      mileageMaxVal !== "" && !isNaN(mileageMaxVal)
        ? parseInt(mileageMaxVal)
        : currentRanges.mileage.max;
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
