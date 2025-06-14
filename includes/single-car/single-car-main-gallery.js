/**
 * Single Car Main Gallery JavaScript
 * Handles the main gallery functionality including thumbnails and navigation
 */

document.addEventListener("DOMContentLoaded", function () {
  // PHP generated array of image URLs
  const allImagesData = window.allImagesData || [];

  if (allImagesData.length === 0) {
    // Hide the gallery container if no images are available
    const galleryContainer = document.querySelector(".gallery-container");
    if (galleryContainer) {
      galleryContainer.style.display = "none";
    }
    return; // Exit if no images
  }

  const heroImage = document.getElementById("heroImage");
  const prevArrow = document.getElementById("prevArrow");
  const nextArrow = document.getElementById("nextArrow");
  const thumbnailsWrapper = document.getElementById("thumbnailsWrapper");
  const thumbnailSection = document.querySelector(".thumbnail-section");

  let currentIndex = 0;
  let isTransitioning = false;

  function updateGallery() {
    if (isTransitioning) return;
    isTransitioning = true;

    // Change the image source immediately
    heroImage.src = allImagesData[currentIndex];

    // Add fade-out class to start the transition
    heroImage.classList.add("fade-out");

    // Update thumbnails
    document.querySelectorAll(".thumbnail-item").forEach((thumb) => {
      thumb.classList.remove("active");
    });

    const activeThumbnail = document.querySelector(
      `.thumbnail-item[data-index="${currentIndex}"]`
    );
    if (activeThumbnail) {
      activeThumbnail.classList.add("active");

      const thumbWidthWithGap = activeThumbnail.offsetWidth + 10;
      const wrapperTotalWidth = thumbnailsWrapper.scrollWidth;
      const visibleContainerWidth = thumbnailSection.offsetWidth;

      let targetScrollOffset = 0;

      if (allImagesData.length * thumbWidthWithGap <= visibleContainerWidth) {
        targetScrollOffset = 0; // All thumbnails fit, no scrolling needed
      } else {
        const centerOffset = visibleContainerWidth / 2 - thumbWidthWithGap / 2;
        targetScrollOffset = currentIndex * thumbWidthWithGap - centerOffset;

        targetScrollOffset = Math.max(0, targetScrollOffset);

        const maxPossibleScroll = wrapperTotalWidth - visibleContainerWidth;
        targetScrollOffset = Math.min(targetScrollOffset, maxPossibleScroll);
      }
      thumbnailsWrapper.style.transform = `translateX(-${targetScrollOffset}px)`;
    }

    // Remove fade-out class to start fade-in
    requestAnimationFrame(() => {
      heroImage.classList.remove("fade-out");
    });

    // Reset transition flag after animation completes
    setTimeout(() => {
      isTransitioning = false;
    }, 50); // Match this with the CSS transition duration
  }

  function createThumbnails() {
    thumbnailsWrapper.innerHTML = "";
    allImagesData.forEach((image, index) => {
      const img = document.createElement("img");
      img.src = image;
      img.alt = `Car thumbnail ${index + 1}`;
      img.classList.add("thumbnail-item");
      img.dataset.index = index;

      img.addEventListener("click", () => {
        if (!isTransitioning) {
          currentIndex = index;
          updateGallery();
        }
      });
      thumbnailsWrapper.appendChild(img);
    });
    updateGallery();
  }

  function showNext() {
    if (!isTransitioning) {
      currentIndex = (currentIndex + 1) % allImagesData.length;
      updateGallery();
    }
  }

  function showPrev() {
    if (!isTransitioning) {
      currentIndex =
        (currentIndex - 1 + allImagesData.length) % allImagesData.length;
      updateGallery();
    }
  }

  nextArrow.addEventListener("click", showNext);
  prevArrow.addEventListener("click", showPrev);
  window.addEventListener("resize", updateGallery);

  // Initial setup
  createThumbnails();
});
