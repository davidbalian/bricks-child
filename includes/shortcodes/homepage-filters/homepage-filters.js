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
    // Get filter data from JSON script tag (use first one found, data is shared)
    const dataScript = $("#homepage-filters-data").first();
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

    // Initialize each container instance separately
    $(".homepage-filters-container").each(function () {
      const $container = $(this);
      initializeSliders($container);
      initializeSelects($container);
      initializeSearchButton($container);
    });
  });

  /**
   * Initialize noUiSlider instances for price and mileage
   */
  function initializeSliders($container) {
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
    $container
      .find("#homepage-filter-price-min")
      .val(formatNumber(currentRanges.price.min));
    $container
      .find("#homepage-filter-price-max")
      .val(formatNumber(currentRanges.price.max));

    // Update inputs when slider changes - COMMENTED OUT
    // priceSlider.on("update", function (values) {
    //   const minFormatted = formatNumber(Math.round(values[0]));
    //   const maxFormatted = formatNumber(Math.round(values[1]));
    //   $container.find("#homepage-filter-price-min").val(minFormatted);
    //   $container.find("#homepage-filter-price-max").val(maxFormatted);
    // });

    // Format on input (as user types) - only format if there's a value
    $container
      .find("#homepage-filter-price-min, #homepage-filter-price-max")
      .on("input", function () {
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
      });

    // Validate min/max relationship on blur (when user clicks/touches away)
    $container.find("#homepage-filter-price-max").on("blur", function () {
      console.log("Price max blur triggered");
      const minVal = $container.find("#homepage-filter-price-min").val();
      const maxVal = $container.find("#homepage-filter-price-max").val();
      console.log("Raw values - minVal:", minVal, "maxVal:", maxVal);

      const parsedMin = parseFormattedNumber(minVal);
      const parsedMax = parseFormattedNumber(maxVal);
      console.log(
        "Parsed values - parsedMin:",
        parsedMin,
        "parsedMax:",
        parsedMax
      );

      const min = parsedMin ?? currentRanges.price.min;
      const max = parsedMax ?? currentRanges.price.max;
      console.log(
        "Final values - min:",
        min,
        "max:",
        max,
        "currentRanges.price.min:",
        currentRanges.price.min,
        "currentRanges.price.max:",
        currentRanges.price.max
      );
      console.log("Comparison - max < min:", max < min);

      // If max is smaller than min, adjust min to be smaller than max
      if (max < min) {
        console.log("Adjusting min because max < min");
        const step = 100;
        // Allow min to go below range minimum if user sets a lower max
        const adjustedMin = Math.max(0, max - step);
        console.log("Adjusted min:", adjustedMin);
        $container
          .find("#homepage-filter-price-min")
          .val(formatNumber(adjustedMin));
        console.log("Set min value to:", formatNumber(adjustedMin));
      } else {
        console.log("No adjustment needed");
      }
    });

    $container.find("#homepage-filter-price-min").on("blur", function () {
      console.log("Price min blur triggered");
      const minVal = $container.find("#homepage-filter-price-min").val();
      const maxVal = $container.find("#homepage-filter-price-max").val();
      console.log("Raw values - minVal:", minVal, "maxVal:", maxVal);

      const parsedMin = parseFormattedNumber(minVal);
      const parsedMax = parseFormattedNumber(maxVal);
      console.log(
        "Parsed values - parsedMin:",
        parsedMin,
        "parsedMax:",
        parsedMax
      );

      const min = parsedMin ?? currentRanges.price.min;
      const max = parsedMax ?? currentRanges.price.max;
      console.log("Final values - min:", min, "max:", max);
      console.log("Comparison - min > max:", min > max);

      // If min is greater than max, adjust max to be larger than min
      if (min > max) {
        console.log("Adjusting max because min > max");
        const step = 100;
        // Allow max to go above range maximum if user sets a higher min
        const adjustedMax = min + step;
        console.log("Adjusted max:", adjustedMax);
        $container
          .find("#homepage-filter-price-max")
          .val(formatNumber(adjustedMax));
        console.log("Set max value to:", formatNumber(adjustedMax));
      } else {
        console.log("No adjustment needed");
      }
    });

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
    $container
      .find("#homepage-filter-mileage-min")
      .val(formatNumber(currentRanges.mileage.min));
    $container
      .find("#homepage-filter-mileage-max")
      .val(formatNumber(currentRanges.mileage.max));

    // Update inputs when slider changes - COMMENTED OUT
    // mileageSlider.on("update", function (values) {
    //   const minFormatted = formatNumber(Math.round(values[0]));
    //   const maxFormatted = formatNumber(Math.round(values[1]));
    //   $container.find("#homepage-filter-mileage-min").val(minFormatted);
    //   $container.find("#homepage-filter-mileage-max").val(maxFormatted);
    // });

    // Format on input (as user types) - only format if there's a value
    $container
      .find("#homepage-filter-mileage-min, #homepage-filter-mileage-max")
      .on("input", function () {
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
      });

    // Validate min/max relationship on blur (when user clicks/touches away)
    $container.find("#homepage-filter-mileage-max").on("blur", function () {
      console.log("Mileage max blur triggered");
      const minVal = $container.find("#homepage-filter-mileage-min").val();
      const maxVal = $container.find("#homepage-filter-mileage-max").val();
      console.log("Raw values - minVal:", minVal, "maxVal:", maxVal);

      const parsedMin = parseFormattedNumber(minVal);
      const parsedMax = parseFormattedNumber(maxVal);
      console.log(
        "Parsed values - parsedMin:",
        parsedMin,
        "parsedMax:",
        parsedMax
      );

      const min = parsedMin ?? currentRanges.mileage.min;
      const max = parsedMax ?? currentRanges.mileage.max;
      console.log("Final values - min:", min, "max:", max);
      console.log("Comparison - max < min:", max < min);

      // If max is smaller than min, adjust min to be smaller than max
      if (max < min) {
        console.log("Adjusting min because max < min");
        const step = 1000;
        // Allow min to go below range minimum if user sets a lower max
        const adjustedMin = Math.max(0, max - step);
        console.log("Adjusted min:", adjustedMin);
        $container
          .find("#homepage-filter-mileage-min")
          .val(formatNumber(adjustedMin));
        console.log("Set min value to:", formatNumber(adjustedMin));
      } else {
        console.log("No adjustment needed");
      }
    });

    $container.find("#homepage-filter-mileage-min").on("blur", function () {
      console.log("Mileage min blur triggered");
      const minVal = $container.find("#homepage-filter-mileage-min").val();
      const maxVal = $container.find("#homepage-filter-mileage-max").val();
      console.log("Raw values - minVal:", minVal, "maxVal:", maxVal);

      const parsedMin = parseFormattedNumber(minVal);
      const parsedMax = parseFormattedNumber(maxVal);
      console.log(
        "Parsed values - parsedMin:",
        parsedMin,
        "parsedMax:",
        parsedMax
      );

      const min = parsedMin ?? currentRanges.mileage.min;
      const max = parsedMax ?? currentRanges.mileage.max;
      console.log("Final values - min:", min, "max:", max);
      console.log("Comparison - min > max:", min > max);

      // If min is greater than max, adjust max to be larger than min
      if (min > max) {
        console.log("Adjusting max because min > max");
        const step = 1000;
        // Allow max to go above range maximum if user sets a higher min
        const adjustedMax = min + step;
        console.log("Adjusted max:", adjustedMax);
        $container
          .find("#homepage-filter-mileage-max")
          .val(formatNumber(adjustedMax));
        console.log("Set max value to:", formatNumber(adjustedMax));
      } else {
        console.log("No adjustment needed");
      }
    });

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
  function initializeSelects($container) {
    initializeDropdown("make", $container);
    initializeDropdown("model", $container);
  }

  // Click outside to close dropdowns (single global handler for all containers)
  $(document).on("click", function (e) {
    $(".homepage-filters-container").each(function () {
      const $container = $(this);
      // Close dropdowns if click is outside this container or outside dropdowns within this container
      if (
        !$(e.target).closest($container).length ||
        ($(e.target).closest($container).length &&
          !$(e.target).closest(".homepage-filters-dropdown", $container).length)
      ) {
        closeAllDropdowns($container);
      }
    });
  });

  /**
   * Initialize a single dropdown (make or model)
   */
  function initializeDropdown(type, $container) {
    const dropdown = $container.find(
      `.homepage-filters-dropdown[data-filter="${type}"]`
    );
    const button = $container.find(`#homepage-filter-${type}-button`);
    const menu = $container.find(`#homepage-filter-${type}-menu`);
    const search = $container.find(`#homepage-filter-${type}-search`);
    const options = $container.find(`#homepage-filter-${type}-options`);
    const hiddenSelect = $container.find(`#homepage-filter-${type}`);

    // Toggle dropdown on button click
    button.on("click", function (e) {
      e.stopPropagation();
      if ($(this).prop("disabled")) return;

      const isOpen = menu.hasClass("open");
      closeAllDropdowns($container);

      if (!isOpen) {
        openDropdown(type, $container);
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
      buttonText.text(text);
      // Keep placeholder class for "All Brands" and "All Models"
      if (value === "" || text === "All Brands" || text === "All Models") {
        buttonText.addClass("placeholder");
      } else {
        buttonText.removeClass("placeholder");
      }

      // Update hidden select
      hiddenSelect.val(value).trigger("change");

      // Update selected state
      options.find(".homepage-filters-dropdown-option").removeClass("selected");
      $option.addClass("selected");

      // Close dropdown
      closeDropdown(type, $container);

      // Handle selection logic
      if (type === "make") {
        handleMakeSelection(value, slug, $container);
      } else if (type === "model") {
        handleModelSelection(value, slug, $container);
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
  function openDropdown(type, $container) {
    const menu = $container.find(`#homepage-filter-${type}-menu`);
    const button = $container.find(`#homepage-filter-${type}-button`);

    menu.addClass("open");
    button.addClass("homepage-filters-dropdown-button-open");
    button.attr("aria-expanded", "true");
  }

  /**
   * Close a dropdown
   */
  function closeDropdown(type, $container) {
    const menu = $container.find(`#homepage-filter-${type}-menu`);
    const button = $container.find(`#homepage-filter-${type}-button`);
    const search = $container.find(`#homepage-filter-${type}-search`);

    menu.removeClass("open");
    button.removeClass("homepage-filters-dropdown-button-open");
    button.attr("aria-expanded", "false");
    search.val("");
    filterDropdownOptions(
      $container.find(`#homepage-filter-${type}-options`),
      ""
    );
  }

  /**
   * Close all dropdowns
   */
  function closeAllDropdowns($container) {
    closeDropdown("make", $container);
    closeDropdown("model", $container);
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
  function handleMakeSelection(makeTermId, makeSlug, $container) {
    selectedMake = makeTermId ? { id: makeTermId, slug: makeSlug } : null;
    selectedModel = null;

    // Reset model dropdown in all containers
    $(".homepage-filters-container").each(function () {
      const $cont = $(this);
      const modelButton = $cont.find("#homepage-filter-model-button");
      const modelButtonText = modelButton.find(
        ".homepage-filters-dropdown-text"
      );
      const modelOptions = $cont.find("#homepage-filter-model-options");
      const modelHiddenSelect = $cont.find("#homepage-filter-model");
      const modelSearch = $cont.find("#homepage-filter-model-search");

      modelButton
        .prop("disabled", true)
        .addClass("homepage-filters-dropdown-button-disabled");
      modelButtonText.text("All Models").addClass("placeholder");
      modelOptions.empty();
      modelHiddenSelect
        .prop("disabled", true)
        .html('<option value="">All Models</option>');
      modelSearch.prop("disabled", true);
    });

    if (makeTermId) {
      if (!firstSelectedFilter) {
        firstSelectedFilter = "make";
      }
      loadModels(makeTermId, $container);
      updateRanges($container);
    } else {
      firstSelectedFilter = null;
      resetRanges($container);
    }
  }

  /**
   * Handle model selection
   */
  function handleModelSelection(modelTermId, modelSlug, $container) {
    selectedModel = modelTermId ? { id: modelTermId, slug: modelSlug } : null;

    if (modelTermId) {
      if (!firstSelectedFilter) {
        firstSelectedFilter = "model";
      }
      updateRanges($container);
    } else {
      if (firstSelectedFilter === "model") {
        firstSelectedFilter = selectedMake ? "make" : null;
      }
      updateRanges($container);
    }
  }

  /**
   * Load models for selected make
   */
  function loadModels(makeTermId, $container) {
    // Update all containers with the same models
    $(".homepage-filters-container").each(function () {
      const $cont = $(this);
      const modelOptions = $cont.find("#homepage-filter-model-options");
      const modelHiddenSelect = $cont.find("#homepage-filter-model");
      const modelButton = $cont.find("#homepage-filter-model-button");
      const modelSearch = $cont.find("#homepage-filter-model-search");

      // Use AJAX to get models from taxonomy (only call once, then update all containers)
      if ($cont.is($container)) {
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
              // Update all containers with the same models
              $(".homepage-filters-container").each(function () {
                const $c = $(this);
                const $mOptions = $c.find("#homepage-filter-model-options");
                const $mHiddenSelect = $c.find("#homepage-filter-model");
                const $mButton = $c.find("#homepage-filter-model-button");
                const $mSearch = $c.find("#homepage-filter-model-search");

                // Clear existing options
                $mOptions.empty();
                $mHiddenSelect.html('<option value="">All Models</option>');

                // Add "All Models" as first option
                const $allModelsOption = $("<button>", {
                  type: "button",
                  class: "homepage-filters-dropdown-option",
                  "data-value": "",
                  "data-slug": "",
                  text: "All Models",
                });
                $mOptions.append($allModelsOption);

                // Build options for custom dropdown and hidden select
                response.data.forEach(function (model) {
                  // Add to custom dropdown
                  const $option = $("<button>", {
                    type: "button",
                    class: "homepage-filters-dropdown-option",
                    "data-value": model.term_id,
                    "data-slug": model.slug,
                  });
                  $option.text(model.name + " ");
                  $option.append(
                    $("<span>", {
                      class: "homepage-filters-count",
                      text: "(" + model.count + ")",
                    })
                  );
                  $mOptions.append($option);

                  // Add to hidden select
                  const $hiddenOption = $("<option>", {
                    value: model.term_id,
                    "data-slug": model.slug,
                    text: model.name + " (" + model.count + ")",
                  });
                  $mHiddenSelect.append($hiddenOption);
                });

                // Enable dropdown
                $mButton
                  .prop("disabled", false)
                  .removeClass("homepage-filters-dropdown-button-disabled");
                $mSearch.prop("disabled", false);
              });
            }
          },
          error: function () {
            console.error("Error loading models");
          },
        });
      }
    });
  }

  /**
   * Update slider ranges based on selected make/model
   */
  function updateRanges($container) {
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

          // Update price inputs in all containers
          $(".homepage-filters-container").each(function () {
            const $c = $(this);
            $c.find("#homepage-filter-price-min").val(
              formatNumber(currentRanges.price.min)
            );
            $c.find("#homepage-filter-price-max").val(
              formatNumber(currentRanges.price.max)
            );

            // Update mileage inputs
            $c.find("#homepage-filter-mileage-min").val(
              formatNumber(currentRanges.mileage.min)
            );
            $c.find("#homepage-filter-mileage-max").val(
              formatNumber(currentRanges.mileage.max)
            );
          });

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
  function resetRanges($container) {
    currentRanges = filterData.ranges;

    // Update price inputs in all containers
    $(".homepage-filters-container").each(function () {
      const $c = $(this);
      $c.find("#homepage-filter-price-min").val(
        formatNumber(currentRanges.price.min)
      );
      $c.find("#homepage-filter-price-max").val(
        formatNumber(currentRanges.price.max)
      );

      // Update mileage inputs
      $c.find("#homepage-filter-mileage-min").val(
        formatNumber(currentRanges.mileage.min)
      );
      $c.find("#homepage-filter-mileage-max").val(
        formatNumber(currentRanges.mileage.max)
      );
    });

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
  function initializeSearchButton($container) {
    $container.find("#homepage-filters-search-btn").on("click", function () {
      const url = buildFilterUrl($container);
      if (url) {
        window.location.href = url;
      }
    });
  }

  /**
   * Build JetSmartFilters URL from current filter selections
   */
  function buildFilterUrl($container) {
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

    // Price range (get from the container that triggered the search)
    const priceMinVal = $container.find("#homepage-filter-price-min").val();
    const priceMaxVal = $container.find("#homepage-filter-price-max").val();
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

    // Mileage range (get from the container that triggered the search)
    const mileageMinVal = $container.find("#homepage-filter-mileage-min").val();
    const mileageMaxVal = $container.find("#homepage-filter-mileage-max").val();
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
      // Ensure we don't create double slashes
      if (url.endsWith("/")) {
        url += "meta/" + metaParts.join(";");
      } else {
        url += "/meta/" + metaParts.join(";");
      }
    }

    // Ensure URL ends with a single slash
    if (!url.endsWith("/")) {
      url += "/";
    }

    return url;
  }
})(jQuery);
