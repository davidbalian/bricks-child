// Clean Swiper-based implementation (no Slick references)
document.addEventListener('DOMContentLoaded', function () {
  // Main Swiper
  var mainSwiper = new Swiper('.main-image-slider', {
    slidesPerView: 1,
    spaceBetween: 0,
    loop: true,
    navigation: {
      nextEl: '.slider-next',
      prevEl: '.slider-prev',
    },
    thumbs: {
      swiper: null // will be set below
    },
    on: {
      slideChange: function () {
        var current = this.realIndex + 1;
        document.querySelector('.current-photo').textContent = current;
      }
    }
  });

  // Thumbnail Swiper
  var thumbSwiper = new Swiper('.thumbnail-nav', {
    slidesPerView: 'auto',
    spaceBetween: 8,
    watchSlidesProgress: true,
    slideToClickedSlide: true,
    centeredSlides: false,
    loop: false
  });

  // Link thumbs to main
  mainSwiper.thumbs.swiper = thumbSwiper;
  mainSwiper.thumbs.init();
  mainSwiper.thumbs.update();

  // Click on thumbnail
  document.querySelectorAll('.row-image-item').forEach(function (el, idx) {
    el.addEventListener('click', function () {
      mainSwiper.slideToLoop(idx);
    });
  });

  // Lightbox implementation
  var viewAllBtn = document.querySelector('.view-all-images-btn');
  var images = Array.from(document.querySelectorAll('.main-image-slider .main-image')).map(function(img) {
    return {
      src: img.getAttribute('src'),
      alt: img.getAttribute('alt') || ''
    };
  });

  function openLightbox(startIndex) {
    // Create lightbox HTML
    var lightboxHtml = `
      <div class="car-gallery-lightbox">
        <div class="lightbox-overlay"></div>
        <div class="lightbox-content">
          <div class="lightbox-header">
            <div class="lightbox-counter">
              <span class="lightbox-current">${startIndex + 1}</span>/<span class="lightbox-total">${images.length}</span> photos
            </div>
            <button class="lightbox-close" type="button" aria-label="Close gallery">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="lightbox-main">
            <div class="swiper lightbox-slider">
              <div class="swiper-wrapper">
                ${images.map(img => `<div class='swiper-slide'><img src='${img.src}' alt='${img.alt}' class='lightbox-image'></div>`).join('')}
              </div>
              <div class="swiper-button-prev lightbox-arrow"></div>
              <div class="swiper-button-next lightbox-arrow"></div>
            </div>
          </div>
          <div class="lightbox-thumbnails">
            <div class="swiper lightbox-thumbnails-slider">
              <div class="swiper-wrapper">
                ${images.map(img => `<div class='swiper-slide'><img src='${img.src}' alt='${img.alt}'></div>`).join('')}
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', lightboxHtml);
    document.body.classList.add('lightbox-open');

    // Init Swiper for lightbox
    var lightboxThumbs = new Swiper('.lightbox-thumbnails-slider', {
      slidesPerView: 'auto',
      spaceBetween: 8,
      watchSlidesProgress: true,
      slideToClickedSlide: true,
      centeredSlides: false,
      loop: false
    });
    var lightboxMain = new Swiper('.lightbox-slider', {
      slidesPerView: 1,
      spaceBetween: 0,
      loop: true,
      navigation: {
        nextEl: '.lightbox-arrow.swiper-button-next',
        prevEl: '.lightbox-arrow.swiper-button-prev',
      },
      thumbs: {
        swiper: lightboxThumbs
      },
      initialSlide: startIndex,
      on: {
        slideChange: function () {
          var current = this.realIndex + 1;
          document.querySelector('.lightbox-current').textContent = current;
        }
      }
    });
    // Sync thumbs click
    document.querySelectorAll('.lightbox-thumbnails-slider .swiper-slide').forEach(function (el, idx) {
      el.addEventListener('click', function () {
        lightboxMain.slideToLoop(idx);
      });
    });
    // Close events
    document.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
    document.querySelector('.lightbox-overlay').addEventListener('click', closeLightbox);
    function closeLightbox() {
      var lb = document.querySelector('.car-gallery-lightbox');
      if(lb) lb.remove();
      document.body.classList.remove('lightbox-open');
    }
  }

  if(viewAllBtn) {
    viewAllBtn.addEventListener('click', function() {
      var currentIndex = mainSwiper.realIndex;
      openLightbox(currentIndex);
    });
  }
});
