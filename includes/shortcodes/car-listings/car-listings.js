/**
 * Car Listings Shortcode JavaScript
 * Handles infinite scroll functionality
 */

(function ($) {
  "use strict";

  function log() {
    if (window.console && window.console.info) {
      var args = Array.prototype.slice.call(arguments);
      args.unshift("[AutoAgora listings]");
      window.console.info.apply(window.console, args);
    }
  }

  $(document).ready(function () {
    initInfiniteScroll();
  });

  /**
   * Get filter parameters from URL query params
   */
  function getFilterParams() {
    var filterParams = {};
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

      log("init", {
        id: $container.attr("id") || "",
        currentPage: currentPage,
        maxPages: maxPages,
        hasLoader: !!$loader.length,
      });

      // Only setup if there are more pages
      if (maxPages <= 1) {
        $loader.hide();
        log("skip infinite scroll: no extra pages");
        return;
      }

      function setLoaderIdle() {
        $loader.show().addClass("car-listings-loader-idle").removeClass("car-listings-loader-active");
      }

      function setLoaderActive() {
        $loader.show().removeClass("car-listings-loader-idle").addClass("car-listings-loader-active");
      }

      function stopLoader() {
        $loader.hide().removeClass("car-listings-loader-idle car-listings-loader-active");
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
          setLoaderIdle();
          observer.observe($loader[0]);
          log("observer attached");
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

        setLoaderIdle();
        log("using scroll fallback");
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
        setLoaderActive();

        // Get current filter parameters (supports both pretty URL and query params)
        var filterParams = getFilterParams();
        log("load more start", {
          page: currentPage,
          maxPages: maxPages,
          filters: filterParams,
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
            log("load more response", response);
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
                stopLoader();

                // Disconnect observer if using IntersectionObserver
                if (typeof observer !== "undefined") {
                  observer.disconnect();
                }

                // Remove scroll event if using fallback
                $(window).off("scroll.carListings");
              } else {
                setLoaderIdle();
              }
            } else {
              // No more content
              stopLoader();
            }
          },
          error: function (xhr, status, error) {
            console.error("[AutoAgora listings] Error loading more car listings", {
              status: status,
              error: error,
              responseText: xhr && xhr.responseText ? xhr.responseText.slice(0, 500) : "",
            });
            // Revert page on error
            currentPage--;
            stopLoader();
          },
          complete: function () {
            loading = false;
          },
        });
      }
    });
  }
})(jQuery);
