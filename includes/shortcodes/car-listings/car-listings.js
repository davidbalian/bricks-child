/**
 * Car Listings Shortcode JavaScript
 * Handles infinite scroll functionality
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    initInfiniteScroll();
  });

  /**
   * Parse pretty URL into filter parameters
   * Format: /cars/filter/make:bmw-m2/meta/price!range:10000_50000/
   */
  function parsePrettyUrl() {
    var path = window.location.pathname;
    var match = path.match(/\/filter\/(.+?)\/?$/);
    if (!match) return null;

    var filterString = match[1];
    var result = {};

    // Parse make:slug (could be make or make-model combined)
    var makeMatch = filterString.match(/make:([^\/]+)/);
    if (makeMatch) {
      // Pass the combined slug - PHP will resolve if it's make or model
      result.make_slug = makeMatch[1];
    }

    // Parse meta/field:value;field!range:min_max
    var metaMatch = filterString.match(/meta\/([^\/]+)/);
    if (metaMatch) {
      var metaParts = metaMatch[1].split(';');
      metaParts.forEach(function(part) {
        if (part.indexOf('!range:') !== -1) {
          // Range: price!range:10000_50000
          var rangeParts = part.split('!range:');
          var field = rangeParts[0];
          var values = rangeParts[1].split('_');
          var min = parseInt(values[0], 10);
          var max = parseInt(values[1], 10);

          // Only set if not placeholder values
          if (min > 0) {
            result[field + '_min'] = min;
          }
          if (max > 0 && max < 999999999 && (field !== 'year' || max < 2100)) {
            result[field + '_max'] = max;
          }
        } else if (part.indexOf(':') !== -1) {
          // Simple: fuel_type:Diesel
          var simpleParts = part.split(':');
          result[simpleParts[0]] = simpleParts[1];
        }
      });
    }

    return result;
  }

  /**
   * Get filter parameters from URL (pretty URL or query params)
   */
  function getFilterParams() {
    var filterParams = {};

    // Try pretty URL first
    var prettyParams = parsePrettyUrl();
    if (prettyParams) {
      return prettyParams;
    }

    // Fallback to query parameters
    var urlParams = new URLSearchParams(window.location.search);
    ['make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max',
     'year_min', 'year_max', 'fuel_type', 'body_type'].forEach(function(key) {
      if (urlParams.has(key)) {
        filterParams[key] = urlParams.get(key);
      }
    });

    return filterParams;
  }

  /**
   * Initialize infinite scroll for all instances
   */
  function initInfiniteScroll() {
    $('.car-listings-container[data-infinite-scroll="true"]').each(function () {
      var $container = $(this);
      var loading = false;
      var currentPage = parseInt($container.data("page"), 10) || 1;
      var maxPages = parseInt($container.data("max-pages"), 10) || 1;
      var atts = $container.data("atts");
      var $wrapper = $container.find(".car-listings-wrapper");
      var $loader = $container.find(".car-listings-loader");

      // Only setup if there are more pages
      if (maxPages <= 1) {
        return;
      }

      // IntersectionObserver for infinite scroll
      if ("IntersectionObserver" in window) {
        var observer = new IntersectionObserver(
          function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting && !loading && currentPage < maxPages) {
                loadMore();
              }
            });
          },
          {
            rootMargin: "200px",
          }
        );

        // Show and observe the loader element
        if ($loader.length) {
          $loader.show();
          observer.observe($loader[0]);
        }
      } else {
        // Fallback for older browsers - use scroll event
        $(window).on("scroll.carListings", function () {
          if (loading || currentPage >= maxPages) {
            return;
          }

          var containerBottom = $container.offset().top + $container.outerHeight();
          var scrollBottom = $(window).scrollTop() + $(window).height();

          if (scrollBottom > containerBottom - 200) {
            loadMore();
          }
        });

        $loader.show();
      }

      /**
       * Load more posts via AJAX
       */
      function loadMore() {
        if (loading || currentPage >= maxPages) {
          return;
        }

        loading = true;
        currentPage++;
        $loader.show();

        // Get current filter parameters (supports both pretty URL and query params)
        var filterParams = getFilterParams();

        $.ajax({
          url: carListingsConfig.ajaxUrl,
          type: "POST",
          data: {
            action: "car_listings_load_more",
            nonce: carListingsConfig.nonce,
            page: currentPage,
            atts: JSON.stringify(atts),
            filters: JSON.stringify(filterParams),
          },
          success: function (response) {
            if (response.success && response.data.html) {
              $wrapper.append(response.data.html);

              // Update page tracking
              $container.data("page", currentPage);

              // Update max pages if it changed
              if (response.data.max_pages) {
                maxPages = response.data.max_pages;
                $container.data("max-pages", maxPages);
              }

              // Hide loader if no more pages
              if (!response.data.has_more || currentPage >= maxPages) {
                $loader.hide();

                // Disconnect observer if using IntersectionObserver
                if (typeof observer !== "undefined") {
                  observer.disconnect();
                }

                // Remove scroll event if using fallback
                $(window).off("scroll.carListings");
              }
            } else {
              // No more content
              $loader.hide();
            }
          },
          error: function () {
            console.error("Error loading more car listings");
            // Revert page on error
            currentPage--;
          },
          complete: function () {
            loading = false;
          },
        });
      }
    });
  }
})(jQuery);
