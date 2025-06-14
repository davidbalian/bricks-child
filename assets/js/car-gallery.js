jQuery(document).ready(function ($) {
  // Initialize hero slider
  $(".hero-slider").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    fade: true,
    asNavFor: ".thumbnail-slider",
    adaptiveHeight: false,
    infinite: true,
    speed: 500,
    cssEase: "linear",
  });

  // Initialize thumbnail slider
  $(".thumbnail-slider").slick({
    slidesToShow: 5,
    slidesToScroll: 1,
    asNavFor: ".hero-slider",
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
  });

  // Initialize fullpage slider
  $(".fullpage-slider").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    fade: true,
    adaptiveHeight: false,
  });

  // Update current slide number
  $(".hero-slider").on("afterChange", function (event, slick, currentSlide) {
    $(".current-slide").text(currentSlide + 1);
  });

  // View Gallery Button Click
  $(".view-gallery-btn").on("click", function () {
    const currentSlide = $(".hero-slider").slick("slickCurrentSlide");
    $(".fullpage-gallery").addClass("active");
    $(".fullpage-slider").slick("slickGoTo", currentSlide);
    $("body").css("overflow", "hidden");
  });

  // Close Fullpage Gallery
  $(".fullpage-close").on("click", function () {
    $(".fullpage-gallery").removeClass("active");
    $("body").css("overflow", "");
  });

  // Close on click outside image
  $(".fullpage-slide").on("click", function (e) {
    if (e.target === this) {
      $(".fullpage-gallery").removeClass("active");
      $("body").css("overflow", "");
    }
  });

  // Close on escape key
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $(".fullpage-gallery").hasClass("active")) {
      $(".fullpage-gallery").removeClass("active");
      $("body").css("overflow", "");
    }
  });

  // Fullpage Navigation
  $(".fullpage-prev").on("click", function () {
    $(".fullpage-slider").slick("slickPrev");
  });

  $(".fullpage-next").on("click", function () {
    $(".fullpage-slider").slick("slickNext");
  });
});
