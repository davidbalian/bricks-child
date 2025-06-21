/**
 * Single Car Template Gallery - Swiper Implementation
 */
document.addEventListener("DOMContentLoaded", function () {
  // Check if sliders exist on the page
  if (!document.querySelector(".single-car-main-slider")) {
    return;
  }

  // Initialize the thumbnail slider first
  const thumbnailSlider = new Swiper(".single-car-thumbnail-slider", {
    slidesPerView: 5,
    slidesPerGroup: 1,
    spaceBetween: 10,
    watchSlidesProgress: true,
    slideToClickedSlide: true,
    centeredSlides: false,
    loop: true,
    loopedSlides: 10,
    speed: 200,
    navigation: {
      nextEl: ".single-car-thumbnail-slider .swiper-button-next",
      prevEl: ".single-car-thumbnail-slider .swiper-button-prev",
    },
    breakpoints: {
      1024: {
        slidesPerView: 4,
        spaceBetween: 6,
      },
      1200: {
        slidesPerView: 5,
        spaceBetween: 6,
      },
    },
  });

  // Initialize the main slider
  const mainSlider = new Swiper(".single-car-main-slider", {
    slidesPerView: 1,
    spaceBetween: 0,
    effect: "fade",
    fadeEffect: {
      crossFade: true,
    },
    speed: 200,
    loop: true,
    loopedSlides: 10,
    allowTouchMove: true,
    grabCursor: true,
    thumbs: {
      swiper: thumbnailSlider,
    },
    on: {
      slideChange: function () {
        // Update photo counter with real index for looped slides
        const currentSlide = this.realIndex + 1;
        const counterElement = document.querySelector(
          ".photo-counter .current-slide"
        );
        if (counterElement) {
          counterElement.textContent = currentSlide;
        }
      },
    },
  });

  // Custom arrow functionality for main slider
  const prevButton = document.querySelector(".slider-arrows .custom-prev-btn");
  const nextButton = document.querySelector(".slider-arrows .custom-next-btn");

  if (prevButton) {
    prevButton.addEventListener("click", function (e) {
      e.preventDefault();
      mainSlider.slidePrev();
    });
  }

  if (nextButton) {
    nextButton.addEventListener("click", function (e) {
      e.preventDefault();
      mainSlider.slideNext();
    });
  }

  // Keyboard navigation
  document.addEventListener("keydown", function (e) {
    if (document.querySelector(".single-car-gallery-container")) {
      if (e.keyCode === 37) {
        // Left arrow
        e.preventDefault();
        mainSlider.slidePrev();
      } else if (e.keyCode === 39) {
        // Right arrow
        e.preventDefault();
        mainSlider.slideNext();
      }
    }
  });

  // Enhanced touch/swipe support
  let startX = 0;
  let startY = 0;
  const threshold = 50; // minimum distance for swipe

  const mainSliderEl = document.querySelector(".single-car-main-slider");

  if (mainSliderEl) {
    mainSliderEl.addEventListener("touchstart", function (e) {
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
    });

    mainSliderEl.addEventListener("touchend", function (e) {
      const endX = e.changedTouches[0].clientX;
      const endY = e.changedTouches[0].clientY;
      const deltaX = endX - startX;
      const deltaY = endY - startY;

      // Check if it's more horizontal than vertical swipe
      if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > threshold) {
        if (deltaX > 0) {
          // Swipe right - go to previous slide
          mainSlider.slidePrev();
        } else {
          // Swipe left - go to next slide
          mainSlider.slideNext();
        }
      }
    });
  }

  // Handle window resize
  window.addEventListener("resize", function () {
    // Force swiper to recalculate dimensions
    setTimeout(function () {
      if (mainSlider) mainSlider.update();
      if (thumbnailSlider) thumbnailSlider.update();
    }, 100);
  });

  // Add loading state management
  let imagesLoaded = 0;
  const images = document.querySelectorAll(
    ".single-car-main-slider .swiper-slide img"
  );
  const totalImages = images.length;

  images.forEach(function (img) {
    const image = new Image();
    image.onload = function () {
      imagesLoaded++;
      if (imagesLoaded === totalImages) {
        // All images loaded, refresh sliders
        setTimeout(function () {
          if (mainSlider) mainSlider.update();
          if (thumbnailSlider) thumbnailSlider.update();
        }, 100);
      }
    };
    image.src = img.src;
  });

  // Make thumbnail clicks more responsive
  const thumbnailSlides = document.querySelectorAll(
    ".single-car-thumbnail-slider .swiper-slide"
  );
  thumbnailSlides.forEach(function (slide, index) {
    slide.addEventListener("click", function () {
      // Use slideToLoop for infinite loop compatibility
      mainSlider.slideToLoop(index);
    });
  });

  // Lightbox functionality
  const viewAllButton = document.querySelector(".view-all-button button");

  if (viewAllButton) {
    viewAllButton.addEventListener("click", function () {
      openLightbox();
    });
  }

  function openLightbox() {
    // Get all images from the main slider
    const images = Array.from(
      document.querySelectorAll(".single-car-main-slider .swiper-slide img")
    ).map((img) => ({
      src: img.src,
      alt: img.alt || "",
    }));

    // Create lightbox HTML
    const lightboxHTML = `
      <div class="gallery-lightbox">
        <div class="lightbox-overlay"></div>
        <div class="lightbox-content">
          <div class="lightbox-header">
            <div class="lightbox-counter">
              <span class="lightbox-current">1</span> / <span class="lightbox-total">${
                images.length
              }</span>
            </div>
            <button class="lightbox-close" type="button" aria-label="Close gallery">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div class="lightbox-main-slider-wrapper">
            <div class="swiper lightbox-main-slider">
              <div class="swiper-wrapper">
                ${images
                  .map(
                    (img) => `
                  <div class="swiper-slide">
                    <img src="${img.src}" alt="${img.alt}" />
                  </div>
                `
                  )
                  .join("")}
              </div>
            </div>
            
            <div class="lightbox-arrows">
              <button class="lightbox-prev-btn" aria-label="Previous image">
                <i class="fas fa-chevron-left"></i>
              </button>
              <button class="lightbox-next-btn" aria-label="Next image">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
          
          <div class="lightbox-thumbnail-wrapper">
            <div class="swiper lightbox-thumbnail-slider">
              <div class="swiper-wrapper">
                ${images
                  .map(
                    (img) => `
                  <div class="swiper-slide">
                    <div class="thumbnail">
                      <img src="${img.src}" alt="${img.alt}" />
                    </div>
                  </div>
                `
                  )
                  .join("")}
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    // Add lightbox to body
    document.body.insertAdjacentHTML("beforeend", lightboxHTML);
    document.body.classList.add("lightbox-open");

    // Initialize lightbox sliders
    const lightboxThumbnailSlider = new Swiper(".lightbox-thumbnail-slider", {
      slidesPerView: 5,
      slidesPerGroup: 1,
      spaceBetween: 10,
      watchSlidesProgress: true,
      slideToClickedSlide: true,
      centeredSlides: false,
      loop: true,
      loopedSlides: 10,
      speed: 200,
      breakpoints: {
        1024: {
          slidesPerView: 4,
          spaceBetween: 10,
        },
        1200: {
          slidesPerView: 5,
          spaceBetween: 10,
        },
      },
    });

    const lightboxMainSlider = new Swiper(".lightbox-main-slider", {
      slidesPerView: 1,
      spaceBetween: 0,
      effect: "fade",
      fadeEffect: {
        crossFade: true,
      },
      speed: 200,
      loop: true,
      loopedSlides: 10,
      allowTouchMove: true,
      grabCursor: true,
      thumbs: {
        swiper: lightboxThumbnailSlider,
      },
      on: {
        slideChange: function () {
          const currentSlide = this.realIndex + 1;
          const counterElement = document.querySelector(".lightbox-current");
          if (counterElement) {
            counterElement.textContent = currentSlide;
          }
        },
      },
    });

    // Custom arrow functionality for lightbox
    const lightboxPrevButton = document.querySelector(".lightbox-prev-btn");
    const lightboxNextButton = document.querySelector(".lightbox-next-btn");

    if (lightboxPrevButton) {
      lightboxPrevButton.addEventListener("click", function (e) {
        e.preventDefault();
        lightboxMainSlider.slidePrev();
      });
    }

    if (lightboxNextButton) {
      lightboxNextButton.addEventListener("click", function (e) {
        e.preventDefault();
        lightboxMainSlider.slideNext();
      });
    }

    // Close lightbox functionality
    const closeButton = document.querySelector(".lightbox-close");
    const overlay = document.querySelector(".lightbox-overlay");

    function closeLightbox() {
      const lightbox = document.querySelector(".gallery-lightbox");
      if (lightbox) {
        lightbox.remove();
        document.body.classList.remove("lightbox-open");
      }
    }

    if (closeButton) closeButton.addEventListener("click", closeLightbox);
    if (overlay) overlay.addEventListener("click", closeLightbox);

    // ESC key to close
    document.addEventListener("keydown", function handleEscape(e) {
      if (e.key === "Escape") {
        closeLightbox();
        document.removeEventListener("keydown", handleEscape);
      }
    });

    // Start at current slide position
    const currentIndex = mainSlider.realIndex;
    lightboxMainSlider.slideToLoop(currentIndex);
  }
});
