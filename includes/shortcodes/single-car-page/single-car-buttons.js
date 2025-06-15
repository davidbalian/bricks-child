/**
 * Single Car Buttons JavaScript - Favorite, Share, Report functionality
 * Extracted from old single-car.js for shortcode use
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === FAVORITE BUTTON FUNCTIONALITY ===
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
                    // Revert the visual changes
                    this.classList.toggle('active');
                    if (isActive) {
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    } else {
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                    }
                    
                    // Show specific error message
                    const errorMessage = data.data || 'Unknown error occurred';
                    console.error('Favorite toggle failed:', errorMessage);
                    alert('Failed to update favorites: ' + errorMessage);
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

    // === SHARE BUTTON FUNCTIONALITY ===
    const shareBtn = document.querySelector('.share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get current page URL
            const currentUrl = window.location.href;
            
            // Check if Web Share API is available
            if (navigator.share) {
                navigator.share({
                    title: document.title,
                    url: currentUrl
                }).catch(err => {
                    console.log('Error sharing:', err);
                    // Fallback to copying URL
                    copyToClipboard(currentUrl);
                });
            } else {
                // Fallback: copy URL to clipboard
                copyToClipboard(currentUrl);
            }
        });
    }

    // Helper function to copy URL to clipboard
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Link copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                fallbackCopyTextToClipboard(text);
            });
        } else {
            fallbackCopyTextToClipboard(text);
        }
    }

    // Fallback copy function for older browsers
    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                alert('Link copied to clipboard!');
            } else {
                alert('Unable to copy link. Please copy manually: ' + text);
            }
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
            alert('Unable to copy link. Please copy manually: ' + text);
        }
        document.body.removeChild(textArea);
    }

    // === REPORT BUTTON FUNCTIONALITY ===
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
