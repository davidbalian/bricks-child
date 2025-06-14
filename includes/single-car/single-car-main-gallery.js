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
    speed: 200,
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
    centerMode: false,
    focusOnSelect: true,
    arrows: true,
    speed: 200,
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

  // Ensure all images have the same height
  function equalizeImageHeights() {
    var maxHeight = 0;
    $(".hero-slide img").each(function () {
      var height = $(this).height();
      maxHeight = height > maxHeight ? height : maxHeight;
    });
    $(".hero-slide img").height(maxHeight);
  }

  // Call on load and resize
  $(window).on("load resize", equalizeImageHeights);
  $(".hero-slider").on("setPosition", equalizeImageHeights);

  // Handle view gallery button click
  $(".view-gallery-btn").on("click", function () {
    $(".gallery-popup").fadeIn(200);
    $("body").css("overflow", "hidden");
  });

  // Handle back to advert button click
  $(".back-to-advert-btn").on("click", function () {
    $(".gallery-popup").fadeOut(200);
    $("body").css("overflow", "");
  });

  // Close gallery popup when clicking outside
  $(document).on("click", function (e) {
    if (
      $(e.target).closest(".gallery-popup-content").length === 0 &&
      $(e.target).closest(".view-gallery-btn").length === 0
    ) {
      $(".gallery-popup").fadeOut(200);
      $("body").css("overflow", "");
    }
  });
});
