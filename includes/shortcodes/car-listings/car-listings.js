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

        // Get current URL parameters to pass filter state
        var urlParams = new URLSearchParams(window.location.search);
        var filterParams = {};
        ['make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type'].forEach(function(key) {
          if (urlParams.has(key)) {
            filterParams[key] = urlParams.get(key);
          }
        });

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
