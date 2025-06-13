/**
 * Single Car Gallery JavaScript - Gallery popup functionality
 * Separated for better organization
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add Gallery Popup Functionality
    const viewGalleryBtn = document.querySelector('.view-gallery-btn');
    const galleryPopup = document.querySelector('.gallery-popup');
    const backToAdvertBtn = document.querySelector('.back-to-advert-btn');
    const galleryMainImage = document.querySelector('.gallery-main-image img');
    const galleryThumbnails = document.querySelectorAll('.gallery-thumbnail');
    let lastActiveThumbnailIndex = 0; // Track the last active thumbnail

    // Function to open gallery with specific image
    function openGalleryWithImage(imageIndex) {
        galleryPopup.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Remove active class from all thumbnails
        galleryThumbnails.forEach(thumb => thumb.classList.remove('active'));
        
        // Set the clicked thumbnail as active
        if (galleryThumbnails[imageIndex]) {
            galleryThumbnails[imageIndex].classList.add('active');
            galleryThumbnails[imageIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            
            // Update main image
            const newImageUrl = galleryThumbnails[imageIndex].dataset.fullUrl;
            if (newImageUrl && galleryMainImage) {
                galleryMainImage.src = newImageUrl;
            }
        }
        
        lastActiveThumbnailIndex = imageIndex;
    }

    // Add click handlers to all clickable images
    document.querySelectorAll('.clickable-image').forEach(img => {
        img.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.blur(); // Remove focus after click
            const imageIndex = parseInt(this.dataset.imageIndex);
            openGalleryWithImage(imageIndex);
        });
    });

    if (viewGalleryBtn && galleryPopup) {
        viewGalleryBtn.addEventListener('click', function() {
            openGalleryWithImage(lastActiveThumbnailIndex); // Use last active index instead of 0
        });
    }

    // Restore back to advert button functionality
    if (backToAdvertBtn) {
        backToAdvertBtn.addEventListener('click', function() {
            galleryPopup.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        });
    }

    // Restore click outside to close functionality
    galleryPopup.addEventListener('click', function(e) {
        if (e.target === galleryPopup) {
            galleryPopup.style.display = 'none';
            document.body.style.overflow = '';
        }
    });

    // Handle gallery thumbnail clicks
    galleryThumbnails.forEach((thumb, index) => {
        thumb.addEventListener('click', function() {
            galleryThumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            lastActiveThumbnailIndex = index;
            const newImageUrl = this.dataset.fullUrl;
            if (newImageUrl && galleryMainImage) {
                galleryMainImage.src = newImageUrl;
            }
        });
    });
}); 