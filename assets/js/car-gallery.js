// Helper to wait for all images in a container to load
function imagesLoaded($container, callback) {
  var $imgs = $container.find("img");
  var imgCount = $imgs.length;
  if (imgCount === 0) {
    callback();
    return;
  }
  var loaded = 0;
  $imgs.each(function () {
    if (this.complete) {
      loaded++;
      if (loaded === imgCount) callback();
    } else {
      $(this).one("load error", function () {
        loaded++;
        if (loaded === imgCount) callback();
      });
    }
  });
}

jQuery(function ($) {
  var $galleryContainer = $(".car-gallery-container");
  imagesLoaded($galleryContainer, function () {
    // Configuration
    const config = {
      heroSlider: {
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
        fade: true,
        adaptiveHeight: true,
        asNavFor: ".thumbnail-slider",
      },
      thumbnailSlider: {
        slidesToShow: 5,
        slidesToScroll: 1,
        dots: false,
        centerMode: false,
        focusOnSelect: true,
        asNavFor: ".hero-slider",
        responsive: [
          {
            breakpoint: 768,
            settings: { slidesToShow: 4 },
          },
          {
            breakpoint: 576,
            settings: { slidesToShow: 3 },
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
      const $heroSlider = $(".hero-slider");
      $heroSlider.slick(config.heroSlider);
      const $thumbnailSlider = $(".thumbnail-slider");
      $thumbnailSlider.slick(config.thumbnailSlider);
      const $fullpageSlider = $(".fullpage-slider");
      $fullpageSlider.slick(config.fullpageSlider);
      return { $heroSlider, $thumbnailSlider, $fullpageSlider };
    }

    // Gallery controls
    function initializeGalleryControls($heroSlider, $fullpageSlider) {
      const $fullpageGallery = $(".fullpage-gallery");
      const $body = $("body");
      $heroSlider.on("afterChange", function (event, slick, currentSlide) {
        $(".current-slide").text(currentSlide + 1);
      });
      $(".view-gallery-btn").on("click", function () {
        const currentSlide = $heroSlider.slick("slickCurrentSlide");
        $fullpageGallery.addClass("active");
        $fullpageSlider.slick("slickGoTo", currentSlide);
        $body.css("overflow", "hidden");
      });
      function closeFullpageGallery() {
        $fullpageGallery.removeClass("active");
        $body.css("overflow", "");
      }
      $(".fullpage-close").on("click", closeFullpageGallery);
      $(".fullpage-slide").on("click", function (e) {
        if (e.target === this) {
          closeFullpageGallery();
        }
      });
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && $fullpageGallery.hasClass("active")) {
          closeFullpageGallery();
        }
      });
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
    $(window).on("load resize", manageImageHeights);
  });
});
