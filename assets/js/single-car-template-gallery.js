jQuery(document).ready(function ($) {
  $(".single-car-template-gallery-wrapper").each(function () {
    var $wrapper = $(this);
    var $mainSlider = $wrapper.find(".main-image-slider");
    var $thumbnailNav = $wrapper.find(".thumbnail-nav");
    var $currentPhoto = $wrapper.find(".current-photo");
    var $prevArrow = $wrapper.find(".slider-prev");
    var $nextArrow = $wrapper.find(".slider-next");

    // Initialize main slider
    $mainSlider.slick({
      dots: false,
      arrows: false,
      infinite: true,
      speed: 300,
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
            300
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
            300
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
  });
});
