/**
 * Single Car Main Gallery JavaScript
 * Handles the main gallery functionality including thumbnails and navigation
 */

jQuery(document).ready(function ($) {
  // Initialize hero slider
  $(".hero-slider").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    fade: true,
    asNavFor: ".thumbnail-slider",
    adaptiveHeight: false,
    swipe: true,
    touchThreshold: 10,
    speed: 100,
    cssEase: "linear",
    responsive: [
      {
        breakpoint: 768,
        settings: {
          arrows: true,
        },
      },
    ],
  });

  // Initialize thumbnail slider
  $(".thumbnail-slider").slick({
    slidesToShow: 5,
    slidesToScroll: 1,
    asNavFor: ".hero-slider",
    dots: false,
    adaptiveHeight: false,
    centerMode: false,
    focusOnSelect: true,
    arrows: false,
    speed: 100,
    cssEase: "linear",
    responsive: [
      {
        breakpoint: 768,
        settings: {
          slidesToShow: 3,
          slidesToScroll: 1,
        },
      },
    ],
  });

  // Initialize fullpage slider
  $(".fullpage-slider").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: false,
    fade: true,
    speed: 100,
    cssEase: "linear",
  });

  // Update current slide number for main gallery
  $(".hero-slider").on("afterChange", function (event, slick, currentSlide) {
    $(".current-slide").text(currentSlide + 1);
  });

  // Update current slide number for fullpage gallery
  $(".fullpage-slider").on(
    "afterChange",
    function (event, slick, currentSlide) {
      $(".fullpage-current").text(currentSlide + 1);
    }
  );

  // Handle view gallery button click
  $(".view-gallery-btn").on("click", function () {
    $(".fullpage-gallery").addClass("active");
    $("body").css("overflow", "hidden");

    // Sync fullpage slider with main slider
    var currentSlide = $(".hero-slider").slick("slickCurrentSlide");
    $(".fullpage-slider").slick("slickGoTo", currentSlide);
  });

  // Handle fullpage navigation
  $(".fullpage-nav.prev").on("click", function () {
    $(".fullpage-slider").slick("slickPrev");
  });

  $(".fullpage-nav.next").on("click", function () {
    $(".fullpage-slider").slick("slickNext");
  });

  // Handle fullpage close
  $(".fullpage-close").on("click", function () {
    $(".fullpage-gallery").removeClass("active");
    $("body").css("overflow", "");
  });

  // Close fullpage gallery on escape key
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $(".fullpage-gallery").hasClass("active")) {
      $(".fullpage-gallery").removeClass("active");
      $("body").css("overflow", "");
    }
  });

  // Close fullpage gallery when clicking outside the image
  $(".fullpage-gallery").on("click", function (e) {
    if ($(e.target).hasClass("fullpage-gallery")) {
      $(".fullpage-gallery").removeClass("active");
      $("body").css("overflow", "");
    }
  });
});
