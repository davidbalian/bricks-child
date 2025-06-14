jQuery(document).ready(function ($) {
  $(".single-car-template-gallery-wrapper").each(function () {
    var $wrapper = $(this);
    var $mainSlider = $wrapper.find(".main-image-slider");
    var $thumbnailNav = $wrapper.find(".thumbnail-nav");
    var $currentPhoto = $wrapper.find(".current-photo");
    var $prevArrow = $wrapper.find(".slider-prev");
    var $nextArrow = $wrapper.find(".slider-next");
    var $viewAllBtn = $wrapper.find(".view-all-images-btn");
    var images = [];
    var totalImages = parseInt($wrapper.data("total-images"));

    // Extract image data for lightbox
    $mainSlider.find(".slide img").each(function (index) {
      images.push({
        src: $(this).attr("src"),
        alt: $(this).attr("alt"),
      });
    });

    // Initialize main slider
    $mainSlider.slick({
      dots: false,
      arrows: false,
      infinite: true,
      speed: 100,
      slidesToShow: 1,
      slidesToScroll: 1,
      fade: true,
      adaptiveHeight: true,
    });

    // Custom arrow functionality
    $prevArrow.on("click", function () {
      $mainSlider.slick("slickPrev");
    });

    $nextArrow.on("click", function () {
      $mainSlider.slick("slickNext");
    });

    // Thumbnail click functionality
    $thumbnailNav.find(".row-image-item").on("click", function () {
      var slideIndex = $(this).data("slide");
      $mainSlider.slick("slickGoTo", slideIndex);
    });

    // Update counter and thumbnail active state on slide change
    $mainSlider.on(
      "beforeChange",
      function (event, slick, currentSlide, nextSlide) {
        // Update photo counter
        $currentPhoto.text(nextSlide + 1);

        // Update thumbnail active state
        $thumbnailNav.find(".row-image-item").removeClass("active");
        $thumbnailNav
          .find('.row-image-item[data-slide="' + nextSlide + '"]')
          .addClass("active");

        // Handle thumbnail visibility (show active thumbnail in visible area)
        scrollToActiveThumbnail(nextSlide);
      }
    );

    // Set initial active thumbnail
    $thumbnailNav.find('.row-image-item[data-slide="0"]').addClass("active");

    // Function to scroll thumbnail row to show active thumbnail
    function scrollToActiveThumbnail(activeIndex) {
      var $activeThumbnail = $thumbnailNav.find(
        '.row-image-item[data-slide="' + activeIndex + '"]'
      );
      var $thumbnailContainer = $thumbnailNav;

      if ($activeThumbnail.length && $thumbnailContainer.length) {
        // Stop any current animations to prevent queueing
        $thumbnailContainer.stop(true, false);

        var containerWidth = $thumbnailContainer.width();
        var thumbnailWidth = $activeThumbnail.outerWidth(true);
        var thumbnailOffset = $activeThumbnail.position().left;
        var currentScroll = $thumbnailContainer.scrollLeft();

        // Calculate if thumbnail is out of view
        if (thumbnailOffset < 0) {
          // Thumbnail is to the left of visible area
          $thumbnailContainer.animate(
            {
              scrollLeft: currentScroll + thumbnailOffset - 20,
            },
            150
          );
        } else if (thumbnailOffset + thumbnailWidth > containerWidth) {
          // Thumbnail is to the right of visible area
          $thumbnailContainer.animate(
            {
              scrollLeft:
                currentScroll +
                (thumbnailOffset + thumbnailWidth - containerWidth) +
                20,
            },
            150
          );
        }
      }
    }

    // Handle keyboard navigation
    $(document).on("keydown", function (e) {
      if ($wrapper.is(":visible")) {
        if (e.keyCode === 37) {
          // Left arrow
          e.preventDefault();
          $mainSlider.slick("slickPrev");
        } else if (e.keyCode === 39) {
          // Right arrow
          e.preventDefault();
          $mainSlider.slick("slickNext");
        }
      }
    });

    // Touch/swipe support for thumbnails on mobile
    var startX = 0;
    var scrollLeft = 0;

    $thumbnailNav.on("touchstart", function (e) {
      startX = e.originalEvent.touches[0].pageX;
      scrollLeft = $(this).scrollLeft();
    });

    $thumbnailNav.on("touchmove", function (e) {
      e.preventDefault();
      var x = e.originalEvent.touches[0].pageX;
      var walk = (x - startX) * 2;
      $(this).scrollLeft(scrollLeft - walk);
    });

    // View All Images button - Open lightbox
    $viewAllBtn.on("click", function () {
      openLightbox($mainSlider.slick("slickCurrentSlide"));
    });

    // Create and open lightbox
    function openLightbox(startSlide) {
      var currentSlide = startSlide || 0;

      // Create lightbox HTML
      var lightboxHtml = `
        <div class="car-gallery-lightbox">
          <div class="lightbox-overlay"></div>
          <div class="lightbox-content">
            <div class="lightbox-header">
              <div class="lightbox-counter">
                <span class="lightbox-current">${
                  currentSlide + 1
                }</span>/<span class="lightbox-total">${totalImages}</span> photos
              </div>
              <button class="lightbox-close" type="button">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="lightbox-main">
              <div class="lightbox-slider">
                ${images
                  .map(
                    (img) => `
                  <div class="lightbox-slide">
                    <img src="${img.src}" alt="${img.alt}" class="lightbox-image">
                  </div>
                `
                  )
                  .join("")}
              </div>
              <div class="lightbox-nav">
                <button class="lightbox-arrow lightbox-prev" type="button">
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="lightbox-arrow lightbox-next" type="button">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="lightbox-thumbnails">
              <div class="lightbox-thumbnail-row">
                ${images
                  .map(
                    (img, index) => `
                  <div class="lightbox-thumbnail-item ${
                    index === currentSlide ? "active" : ""
                  }" data-slide="${index}">
                    <img src="${img.src}" alt="${img.alt}">
                  </div>
                `
                  )
                  .join("")}
              </div>
            </div>
          </div>
        </div>
      `;

      // Add lightbox to body
      $("body").append(lightboxHtml);
      $("body").addClass("lightbox-open");

      var $lightbox = $(".car-gallery-lightbox");
      var $lightboxSlider = $lightbox.find(".lightbox-slider");
      var $lightboxThumbnails = $lightbox.find(".lightbox-thumbnail-row");
      var $lightboxCounter = $lightbox.find(".lightbox-current");
      var $lightboxPrev = $lightbox.find(".lightbox-prev");
      var $lightboxNext = $lightbox.find(".lightbox-next");
      var $lightboxClose = $lightbox.find(".lightbox-close");
      var $lightboxOverlay = $lightbox.find(".lightbox-overlay");

      // Initialize lightbox slider
      $lightboxSlider.slick({
        dots: false,
        arrows: false,
        infinite: true,
        speed: 100,
        slidesToShow: 1,
        slidesToScroll: 1,
        fade: true,
        initialSlide: currentSlide,
      });

      // Lightbox navigation
      $lightboxPrev.on("click", function () {
        $lightboxSlider.slick("slickPrev");
      });

      $lightboxNext.on("click", function () {
        $lightboxSlider.slick("slickNext");
      });

      // Lightbox thumbnail clicks
      $lightboxThumbnails
        .find(".lightbox-thumbnail-item")
        .on("click", function () {
          var slideIndex = $(this).data("slide");
          $lightboxSlider.slick("slickGoTo", slideIndex);
        });

      // Update lightbox counter and thumbnails
      $lightboxSlider.on(
        "beforeChange",
        function (event, slick, currentSlide, nextSlide) {
          $lightboxCounter.text(nextSlide + 1);
          $lightboxThumbnails
            .find(".lightbox-thumbnail-item")
            .removeClass("active");
          $lightboxThumbnails
            .find('.lightbox-thumbnail-item[data-slide="' + nextSlide + '"]')
            .addClass("active");
          scrollLightboxThumbnails(nextSlide);
        }
      );

      // Scroll lightbox thumbnails to active
      function scrollLightboxThumbnails(activeIndex) {
        var $activeThumbnail = $lightboxThumbnails.find(
          '.lightbox-thumbnail-item[data-slide="' + activeIndex + '"]'
        );
        var $container = $lightboxThumbnails;

        if ($activeThumbnail.length && $container.length) {
          // Stop any current animations to prevent queueing
          $container.stop(true, false);

          var containerWidth = $container.width();
          var thumbnailWidth = $activeThumbnail.outerWidth(true);
          var thumbnailOffset = $activeThumbnail.position().left;
          var currentScroll = $container.scrollLeft();

          if (thumbnailOffset < 0) {
            $container.animate(
              {
                scrollLeft: currentScroll + thumbnailOffset - 20,
              },
              150
            );
          } else if (thumbnailOffset + thumbnailWidth > containerWidth) {
            $container.animate(
              {
                scrollLeft:
                  currentScroll +
                  (thumbnailOffset + thumbnailWidth - containerWidth) +
                  20,
              },
              150
            );
          }
        }
      }

      // Close lightbox functionality
      function closeLightbox() {
        $lightbox.fadeOut(300, function () {
          $(this).remove();
          $("body").removeClass("lightbox-open");
        });
      }

      $lightboxClose.on("click", closeLightbox);
      $lightboxOverlay.on("click", closeLightbox);

      // Keyboard navigation for lightbox
      $(document).on("keydown.lightbox", function (e) {
        if (e.keyCode === 27) {
          // Escape key
          closeLightbox();
        } else if (e.keyCode === 37) {
          // Left arrow
          e.preventDefault();
          $lightboxSlider.slick("slickPrev");
        } else if (e.keyCode === 39) {
          // Right arrow
          e.preventDefault();
          $lightboxSlider.slick("slickNext");
        }
      });

      // Prevent background scrolling
      $lightbox.on("wheel", function (e) {
        e.preventDefault();
      });

      // Clean up on lightbox close
      $lightbox.on("remove", function () {
        $(document).off("keydown.lightbox");
      });
    }
  });
});
