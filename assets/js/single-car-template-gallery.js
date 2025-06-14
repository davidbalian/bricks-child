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
});
