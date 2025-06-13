/**
 * Single Car Display JavaScript - Separated for better organization
 * Main car functionality (thumbnails, read more, favorites, specs toggle)
 */

document.addEventListener('DOMContentLoaded', function() {
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.querySelector('.main-image img');
    
    const preloadImages = () => {
        thumbnails.forEach(thumb => {
            const fullUrl = thumb.dataset.fullUrl;
            if (fullUrl) {
                const img = new Image();
                img.src = fullUrl;
            }
        });
    };
    
    preloadImages();

    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const newImageUrl = this.dataset.fullUrl;
            if (newImageUrl && mainImage) {
                mainImage.src = newImageUrl;
            }
        });
    });

    const readMoreBtn = document.querySelector('.read-more-btn');
    if (readMoreBtn) {
        readMoreBtn.addEventListener('click', function() {
            const descriptionText = document.querySelector('.description-text');
            const fullDescription = document.querySelector('.full-description');
            
            if (descriptionText && fullDescription) {
                if (descriptionText.style.display === 'none') {
                    descriptionText.style.display = 'block';
                    fullDescription.style.display = 'none';
                    this.textContent = 'Read more';
                } else {
                    descriptionText.style.display = 'none';
                    fullDescription.style.display = 'block';
                    this.textContent = 'Show less';
                }
            }
        });
    }

    const favoriteBtn = document.querySelector('.favorite-btn');
    if (favoriteBtn) {
        favoriteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
                alert('Please log in to add favorites. (Error: Script data missing)');
                return;
            }

            const carId = this.getAttribute('data-car-id');
            const isActive = this.classList.contains('active');
            const heartIcon = this.querySelector('i');

            this.classList.toggle('active');
            if (isActive) {
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
            } else {
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
            }

            const formData = new FormData();
            formData.append('action', 'toggle_favorite_car');
            formData.append('car_id', carId);
            formData.append('is_favorite', !isActive ? '1' : '0');
            formData.append('nonce', carListingsData.nonce);

            fetch(carListingsData.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok.');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    this.classList.toggle('active');
                    if (isActive) {
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    } else {
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                    }
                    console.error('Favorite toggle failed:', data);
                    alert('Failed to update favorites. Please try again.');
                }
            })
            .catch(error => {
                this.classList.toggle('active');
                if (isActive) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                } else {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                }
                console.error('Error:', error);
                alert('Failed to update favorites. An error occurred.');
            });
        });
    }

    const toggleBtn = document.querySelector('.specs-features-toggle');
    const content = document.querySelector('.specs-features-content');
    
    if (toggleBtn && content) {
        toggleBtn.addEventListener('click', function() {
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            toggleBtn.classList.toggle('active');
        });
    }

    // Report functionality
    const reportBtn = document.querySelector('.report-btn');
    const reportModal = document.querySelector('.report-modal');
    const closeReportModal = document.querySelector('.close-report-modal');
    const cancelReportBtn = document.querySelector('.cancel-report-btn');
    const reportForm = document.getElementById('report-listing-form');

    // Open report modal
    if (reportBtn && reportModal) {
        reportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            reportModal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        });
    }

    // Close report modal functions
    function closeModal() {
        if (reportModal) {
            reportModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
            // Reset form
            if (reportForm) {
                reportForm.reset();
            }
        }
    }

    // Close modal events
    if (closeReportModal) {
        closeReportModal.addEventListener('click', closeModal);
    }

    if (cancelReportBtn) {
        cancelReportBtn.addEventListener('click', closeModal);
    }

    // Close modal when clicking outside
    if (reportModal) {
        reportModal.addEventListener('click', function(e) {
            if (e.target === reportModal) {
                closeModal();
            }
        });
    }

    // Handle form submission
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (typeof carListingsData === 'undefined') {
                alert('Error: Unable to submit report. Please refresh the page and try again.');
                return;
            }
            
            const submitBtn = this.querySelector('.submit-report-btn');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;

            const formData = new FormData(this);
            
            fetch(carListingsData.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thank you for your report. We will review it and take appropriate action if necessary.');
                    closeModal();
                } else {
                    alert('Error submitting report: ' + (data.data || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to submit report. Please try again later.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}); 