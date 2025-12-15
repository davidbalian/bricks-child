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
   * Initialize make/model selects with search functionality
   */
  function initializeSelects() {
    const makeSelect = $("#homepage-filter-make");
    const modelSelect = $("#homepage-filter-model");
    const makeSearch = $("#homepage-filter-make-search");
    const modelSearch = $("#homepage-filter-model-search");

    // Store original options for make select (for search functionality)
    const makeOptionsData = [];
    makeSelect.find("option").each(function () {
      makeOptionsData.push({
        value: $(this).val(),
        text: $(this).text(),
        data: {
          slug: $(this).data("slug") || "",
        },
      });
    });
    makeSelect.data("original-options", makeOptionsData);

    // Make select change handler
    makeSelect.on("change", function () {
      const makeTermId = $(this).val();
      const makeSlug = $(this).find("option:selected").data("slug");

      selectedMake = makeTermId ? { id: makeTermId, slug: makeSlug } : null;
      selectedModel = null;

      // Reset model select
      modelSelect
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
        // Reset to global ranges
        resetRanges();
      }
    });

    // Model select change handler
    modelSelect.on("change", function () {
      const modelTermId = $(this).val();
      const modelSlug = $(this).find("option:selected").data("slug");

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
    });

    // Search functionality for make select
    makeSelect.on("focus", function () {
      makeSearch.addClass("active");
    });

    makeSearch.on("input", function () {
      const searchTerm = $(this).val();
      filterSelectOptions(makeSelect, searchTerm);
    });

    makeSearch.on("blur", function () {
      setTimeout(function () {
        // Clear search and restore all options when search loses focus
        makeSearch.val("");
        filterSelectOptions(makeSelect, "");
        makeSearch.removeClass("active");
      }, 200);
    });

    // Search functionality for model select
    modelSelect.on("focus", function () {
      if (!$(this).prop("disabled")) {
        modelSearch.addClass("active");
      }
    });

    modelSearch.on("input", function () {
      const searchTerm = $(this).val();
      filterSelectOptions(modelSelect, searchTerm);
    });

    modelSearch.on("blur", function () {
      setTimeout(function () {
        // Clear search and restore all options when search loses focus
        modelSearch.val("");
        filterSelectOptions(modelSelect, "");
        modelSearch.removeClass("active");
      }, 200);
    });
  }

  /**
   * Filter select options based on search term
   * Note: Native selects don't support hiding options well, so we'll
   * rebuild the select with filtered options
   */
  function filterSelectOptions($select, searchTerm) {
    if (!searchTerm) {
      // Show all options
      $select.find("option").show();
      return;
    }

    // Store original options if not already stored
    if (!$select.data("original-options")) {
      const originalOptions = [];
      $select.find("option").each(function () {
        originalOptions.push({
          value: $(this).val(),
          text: $(this).text(),
          data: $(this).data(),
        });
      });
      $select.data("original-options", originalOptions);
    }

    const originalOptions = $select.data("original-options");
    const filteredOptions = originalOptions.filter(function (option) {
      if (!option.value) return true; // Always show placeholder
      return option.text.toLowerCase().includes(searchTerm.toLowerCase());
    });

    // Rebuild select with filtered options
    const currentValue = $select.val();
    $select.empty();
    filteredOptions.forEach(function (option) {
      const $option = $("<option>", {
        value: option.value,
        text: option.text,
      });
      // Restore data attributes
      Object.keys(option.data || {}).forEach(function (key) {
        $option.attr("data-" + key, option.data[key]);
      });
      $select.append($option);
    });

    // Restore selection if it's still in filtered list
    if (
      currentValue &&
      $select.find('option[value="' + currentValue + '"]').length
    ) {
      $select.val(currentValue);
    }
  }

  /**
   * Load models for selected make
   */
  function loadModels(makeTermId) {
    // Get all model terms (children of selected make)
    const modelSelect = $("#homepage-filter-model");
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
          // Clear existing options and reset search data
          modelSelect.html('<option value="">Select Model</option>');
          modelSelect.removeData("original-options");

          // Build options array for search functionality
          const optionsData = [{ value: "", text: "Select Model", data: {} }];

          response.data.forEach(function (model) {
            const $option = $("<option>", {
              value: model.term_id,
              "data-slug": model.slug,
              text: model.name,
            });
            modelSelect.append($option);

            // Store in options data for search
            optionsData.push({
              value: model.term_id,
              text: model.name,
              data: { slug: model.slug },
            });
          });

          // Store original options for search filtering
          modelSelect.data("original-options", optionsData);

          modelSelect.prop("disabled", false);
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
