jQuery(document).ready(function ($) {
  // Configuration
  const config = {
    heroSlider: {
      slidesToShow: 1,
      slidesToScroll: 1,
      arrows: true,
      fade: true,
      adaptiveHeight: true,
    },
    thumbnailSlider: {
      slidesToShow: 5,
      slidesToScroll: 1,
      dots: false,
      centerMode: false,
      focusOnSelect: true,
      responsive: [
        {
          breakpoint: 768,
          settings: {
            slidesToShow: 4,
          },
        },
        {
          breakpoint: 576,
          settings: {
            slidesToShow: 3,
          },
        },
      ],
    },
    fullpageSlider: {
      slidesToShow: 1,
      slidesToScroll: 1,
      arrows: true,
      fade: true,
      adaptiveHeight: true,
    },
  };

  // Initialize sliders
  function initializeSliders() {
    // Hero slider
    const $heroSlider = $(".hero-slider");
    $heroSlider.slick({
      ...config.heroSlider,
      asNavFor: ".thumbnail-slider",
    });

    // Thumbnail slider
    const $thumbnailSlider = $(".thumbnail-slider");
    $thumbnailSlider.slick({
      ...config.thumbnailSlider,
      asNavFor: ".hero-slider",
    });

    // Fullpage slider
    const $fullpageSlider = $(".fullpage-slider");
    $fullpageSlider.slick(config.fullpageSlider);

    return { $heroSlider, $thumbnailSlider, $fullpageSlider };
  }

  // Gallery controls
  function initializeGalleryControls($heroSlider, $fullpageSlider) {
    const $fullpageGallery = $(".fullpage-gallery");
    const $body = $("body");

    // Update slide counter
    $heroSlider.on("afterChange", function (event, slick, currentSlide) {
      $(".current-slide").text(currentSlide + 1);
    });

    // Open fullpage gallery
    $(".view-gallery-btn").on("click", function () {
      const currentSlide = $heroSlider.slick("slickCurrentSlide");
      $fullpageGallery.addClass("active");
      $fullpageSlider.slick("slickGoTo", currentSlide);
      $body.css("overflow", "hidden");
    });

    // Close fullpage gallery
    function closeFullpageGallery() {
      $fullpageGallery.removeClass("active");
      $body.css("overflow", "");
    }

    // Close button
    $(".fullpage-close").on("click", closeFullpageGallery);

    // Close on click outside
    $(".fullpage-slide").on("click", function (e) {
      if (e.target === this) {
        closeFullpageGallery();
      }
    });

    // Close on escape key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $fullpageGallery.hasClass("active")) {
        closeFullpageGallery();
      }
    });

    // Navigation controls
    $(".fullpage-prev").on("click", function () {
      $fullpageSlider.slick("slickPrev");
    });

    $(".fullpage-next").on("click", function () {
      $fullpageSlider.slick("slickNext");
    });
  }

  // Image height management
  function manageImageHeights() {
    const $heroSlides = $(".hero-slide");
    const maxHeight = Math.max(
      ...$heroSlides
        .map(function () {
          return $(this).height();
        })
        .get()
    );

    $heroSlides.height(maxHeight);
  }

  // Initialize everything
  const sliders = initializeSliders();
  initializeGalleryControls(sliders.$heroSlider, sliders.$fullpageSlider);

  // Handle image heights on load and resize
  $(window).on("load resize", manageImageHeights);
});
