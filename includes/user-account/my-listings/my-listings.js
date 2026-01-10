(function ($) {
  "use strict";

  /**
   * Toggle car sold/available status via AJAX.
   * This mirrors the original inline implementation but uses
   * localized configuration instead of inline PHP.
   */
  function toggleCarStatus(carId, markAsSold) {
    if (!window.myListingsData || !myListingsData.ajaxUrl) {
      return;
    }

    if (myListingsData.isDevelopment) {
      // eslint-disable-next-line no-console
      console.log("Toggle function called with:", { carId, markAsSold });
    }

    // Confirm with the user
    // eslint-disable-next-line no-alert
    if (
      !window.confirm(
        markAsSold
          ? "Are you sure you want to mark this car as sold?"
          : "Are you sure you want to mark this car as available?"
      )
    ) {
      return;
    }

    var data = {
      action: "toggle_car_status",
      car_id: carId,
      mark_as_sold: markAsSold,
      nonce: myListingsData.toggleNonce,
    };

    if (myListingsData.isDevelopment) {
      // eslint-disable-next-line no-console
      console.log("Sending AJAX request with data:", data);
    }

    $.post(myListingsData.ajaxUrl, data)
      .done(function (response) {
        if (myListingsData.isDevelopment) {
          // eslint-disable-next-line no-console
          console.log("AJAX response:", response);
        }

        if (response && response.success) {
          window.location.reload();
        } else {
          // eslint-disable-next-line no-alert
          window.alert("Error updating car status. Please try again.");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        if (myListingsData.isDevelopment) {
          // eslint-disable-next-line no-console
          console.error("AJAX request failed:", {
            textStatus: textStatus,
            errorThrown: errorThrown,
          });
        }
        // eslint-disable-next-line no-alert
        window.alert("Error updating car status. Please try again.");
      });
  }

  // Expose for existing onclick handlers (backwards compatible).
  window.toggleCarStatus = toggleCarStatus;

  /**
   * Initialize AJAX-powered listings (filter, search, load more).
   */
  function initMyListingsAjax() {
    if (!window.myListingsData || !myListingsData.ajaxUrl) {
      return;
    }

    var $container = $(".my-listings-container");
    if (!$container.length) {
      return;
    }

    var $grid = $container.find(".listings-grid");
    if (!$grid.length) {
      return;
    }

    var $statusFilter = $("#status-filter");
    var $sortSelect = $("#sort-select");
    var $searchInput = $("#listing-search");

    var currentPage = parseInt($grid.data("page"), 10) || 1;
    var maxPages = parseInt($grid.data("max-pages"), 10) || 1;
    var perPage = parseInt($grid.data("per-page"), 10) || myListingsData.perPage || 10;
    var isLoading = false;

    // Loader and "Load more" button
    var $loader = $('<div class="my-listings-loader" style="display:none;">Loading...</div>');
    var $loadMoreBtn = $(
      '<button type="button" class="btn btn-secondary my-listings-load-more">Load more</button>'
    );

    $grid.after($loader);
    $loader.after($loadMoreBtn);

    updateLoadMoreVisibility();

    function getFilters() {
      return {
        status: $statusFilter.length ? $statusFilter.val() : "all",
        sort: $sortSelect.length ? $sortSelect.val() : "newest",
        search: $searchInput.length ? $searchInput.val() : "",
      };
    }

    function fetchListings(options) {
      if (isLoading) {
        return;
      }

      var opts = $.extend(
        {
          reset: false,
        },
        options || {}
      );

      if (opts.reset) {
        currentPage = 1;
      }

      if (currentPage > maxPages && !opts.force) {
        return;
      }

      isLoading = true;
      $loader.show();

      var filters = getFilters();

      $.post(myListingsData.ajaxUrl, {
        action: myListingsData.listingsAjaxAction,
        nonce: myListingsData.listingsNonce,
        page: currentPage,
        per_page: perPage,
        status: filters.status,
        sort: filters.sort,
        search: filters.search,
      })
        .done(function (response) {
          if (!response || !response.success || !response.data) {
            if (myListingsData.isDevelopment) {
              // eslint-disable-next-line no-console
              console.error("Unexpected response format:", response);
            }
            return;
          }

          var html = response.data.html || "";
          var hasMore = !!response.data.has_more;
          maxPages = parseInt(response.data.max_pages, 10) || 1;

          if (opts.reset) {
            $grid.html(html);
          } else {
            $grid.append(html);
          }

          $grid.data("page", currentPage);
          $grid.data("max-pages", maxPages);

          if (hasMore && currentPage < maxPages) {
            $loadMoreBtn.show();
          } else {
            $loadMoreBtn.hide();
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          if (myListingsData.isDevelopment) {
            // eslint-disable-next-line no-console
            console.error("Error loading user listings via AJAX", {
              status: jqXHR.status,
              responseText: jqXHR.responseText,
              textStatus: textStatus,
              errorThrown: errorThrown,
            });
          }
        })
        .always(function () {
          isLoading = false;
          $loader.hide();
        });
    }

    function updateLoadMoreVisibility() {
      if (currentPage < maxPages) {
        $loadMoreBtn.show();
      } else {
        $loadMoreBtn.hide();
      }
    }

    // Event bindings
    var searchTimeout = null;

    if ($searchInput.length) {
      $searchInput.on("input", function () {
        window.clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(function () {
          fetchListings({ reset: true });
        }, 300);
      });
    }

    if ($statusFilter.length) {
      $statusFilter.on("change", function (event) {
        event.preventDefault();
        fetchListings({ reset: true });
      });
    }

    if ($sortSelect.length) {
      $sortSelect.on("change", function () {
        fetchListings({ reset: true });
      });
    }

    $loadMoreBtn.on("click", function () {
      if (currentPage >= maxPages || isLoading) {
        return;
      }
      currentPage += 1;
      fetchListings({ reset: false });
    });

    // Delegate click handling for sold/available buttons in both
    // initially rendered and AJAX-loaded items.
    $container.on("click", ".sold-button, .available-button", function (event) {
      event.preventDefault();

      var $button = $(this);
      var carId = parseInt($button.data("car-id"), 10);
      if (!carId) {
        return;
      }

      var isSoldAttr = $button.data("is-sold");
      var isSold = isSoldAttr === 1 || isSoldAttr === "1";

      toggleCarStatus(carId, !isSold);
    });
  }

  $(document).ready(function () {
    initMyListingsAjax();
  });
})(jQuery);


