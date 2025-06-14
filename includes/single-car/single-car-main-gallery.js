/**
 * Single Car Main Gallery JavaScript
 * Handles the main gallery functionality including thumbnails and navigation
 */

document.addEventListener("DOMContentLoaded", function () {
  // Initialize hero slider
  const heroSlider = document.querySelector(".hero-slider");
  if (heroSlider && typeof $(heroSlider).slick === "function") {
    $(heroSlider).slick({
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
  }

  // Initialize thumbnail slider
  const thumbnailSlider = document.querySelector(".thumbnail-slider");
  if (thumbnailSlider && typeof $(thumbnailSlider).slick === "function") {
    $(thumbnailSlider).slick({
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
  }

  // Initialize fullpage slider
  const fullpageSlider = document.querySelector(".fullpage-slider");
  if (fullpageSlider && typeof $(fullpageSlider).slick === "function") {
    $(fullpageSlider).slick({
      slidesToShow: 1,
      slidesToScroll: 1,
      arrows: false,
      fade: true,
      speed: 100,
      cssEase: "linear",
    });
  }

  // Update current slide number for main gallery
  if (heroSlider) {
    $(heroSlider).on("afterChange", function (event, slick, currentSlide) {
      const currentSlideElement = document.querySelector(".current-slide");
      if (currentSlideElement) {
        currentSlideElement.textContent = currentSlide + 1;
      }
    });
  }

  // Update current slide number for fullpage gallery
  if (fullpageSlider) {
    $(fullpageSlider).on("afterChange", function (event, slick, currentSlide) {
      const fullpageCurrentElement =
        document.querySelector(".fullpage-current");
      if (fullpageCurrentElement) {
        fullpageCurrentElement.textContent = currentSlide + 1;
      }
    });
  }

  // Handle view gallery button click
  const viewGalleryBtn = document.querySelector(".view-gallery-btn");
  if (viewGalleryBtn) {
    viewGalleryBtn.addEventListener("click", function () {
      const fullpageGallery = document.querySelector(".fullpage-gallery");
      if (fullpageGallery) {
        fullpageGallery.classList.add("active");
        document.body.style.overflow = "hidden";

        // Sync fullpage slider with main slider
        if (heroSlider && fullpageSlider) {
          const currentSlide = $(heroSlider).slick("slickCurrentSlide");
          $(fullpageSlider).slick("slickGoTo", currentSlide);
        }
      }
    });
  }

  // Handle fullpage navigation
  const fullpagePrev = document.querySelector(".fullpage-nav.prev");
  if (fullpagePrev) {
    fullpagePrev.addEventListener("click", function () {
      if (fullpageSlider) {
        $(fullpageSlider).slick("slickPrev");
      }
    });
  }

  const fullpageNext = document.querySelector(".fullpage-nav.next");
  if (fullpageNext) {
    fullpageNext.addEventListener("click", function () {
      if (fullpageSlider) {
        $(fullpageSlider).slick("slickNext");
      }
    });
  }

  // Handle fullpage close
  const fullpageClose = document.querySelector(".fullpage-close");
  if (fullpageClose) {
    fullpageClose.addEventListener("click", function () {
      const fullpageGallery = document.querySelector(".fullpage-gallery");
      if (fullpageGallery) {
        fullpageGallery.classList.remove("active");
        document.body.style.overflow = "";
      }
    });
  }

  // Close fullpage gallery on escape key
  document.addEventListener("keydown", function (e) {
    const fullpageGallery = document.querySelector(".fullpage-gallery");
    if (
      e.key === "Escape" &&
      fullpageGallery &&
      fullpageGallery.classList.contains("active")
    ) {
      fullpageGallery.classList.remove("active");
      document.body.style.overflow = "";
    }
  });

  // Close fullpage gallery when clicking outside the image
  const fullpageGallery = document.querySelector(".fullpage-gallery");
  if (fullpageGallery) {
    fullpageGallery.addEventListener("click", function (e) {
      if (e.target.classList.contains("fullpage-gallery")) {
        fullpageGallery.classList.remove("active");
        document.body.style.overflow = "";
      }
    });
  }
});
