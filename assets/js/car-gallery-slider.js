jQuery(document).ready(function ($) {
  const mainSlider = $(".main-gallery-slider");
  const thumbSlider = $(".thumbnail-gallery-slider");
  const lightbox = $(".cgs-lightbox");
  const lightboxSlider = $(".cgs-lightbox-slider");

  // Init main slider
  mainSlider.slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    fade: true,
    asNavFor: ".thumbnail-gallery-slider",
  });

  // Init thumbnail slider
  thumbSlider.slick({
    slidesToShow: 5,
    slidesToScroll: 1,
    asNavFor: ".main-gallery-slider",
    dots: false,
    arrows: false,
    centerMode: true,
    focusOnSelect: true,
    responsive: [
      {
        breakpoint: 768,
        settings: {
          slidesToShow: 3,
        },
      },
      {
        breakpoint: 480,
        settings: {
          slidesToShow: 2,
        },
      },
    ],
  });

  // Update photo counter
  mainSlider.on(
    "afterChange",
    function (event, slick, currentSlide, nextSlide) {
      $(".current-slide").text(currentSlide + 1);
    }
  );

  // "View all images" button click
  $(".view-all-images").on("click", function () {
    const currentSlide = mainSlider.slick("slickCurrentSlide");
    lightbox.show();

    // Init lightbox slider if it hasn't been initialized yet
    if (!lightboxSlider.hasClass("slick-initialized")) {
      lightboxSlider.slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
        fade: true,
        dots: false,
      });
    }
    lightboxSlider.slick("slickGoTo", currentSlide);
    $("body").css("overflow", "hidden");
  });

  // Close lightbox
  $(".cgs-close").on("click", function () {
    lightbox.hide();
    $("body").css("overflow", "auto");
  });

  // Close lightbox on escape key
  $(document).on("keydown", function (e) {
    if (e.keyCode === 27) {
      // 27 is the keycode for ESC
      if (lightbox.is(":visible")) {
        lightbox.hide();
        $("body").css("overflow", "auto");
      }
    }
  });

  // Close lightbox when clicking outside the content
  lightbox.on("click", function (e) {
    if ($(e.target).is(lightbox)) {
      lightbox.hide();
      $("body").css("overflow", "auto");
    }
  });
});
