/**
 * Favorite Listings JavaScript - Separated for better organization
 * Handles carousel navigation and favorite button functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Function to update the results counter
    function updateResultsCounter(count) {
        const counter = document.querySelector('.results-counter');
        if (counter) {
            const countSpan = counter.querySelector('.count');
            if (countSpan) {
                countSpan.textContent = count;
            }
        }
    }

    // Carousel functionality
    document.querySelectorAll('.car-listing-image-carousel').forEach(carousel => {
        const images = carousel.querySelectorAll('.car-listing-image');
        const prevBtn = carousel.querySelector('.carousel-nav.prev');
        const nextBtn = carousel.querySelector('.carousel-nav.next');
        const seeAllImagesBtn = carousel.querySelector('.see-all-images');
        let currentIndex = 0;

        // Function to update image visibility
        const updateImages = () => {
            images.forEach((img, index) => {
                img.classList.toggle('active', index === currentIndex);
            });

            // Update navigation buttons
            prevBtn.style.display = currentIndex === 0 ? 'none' : 'flex';
            nextBtn.style.display = currentIndex === images.length - 1 ? 'none' : 'flex';

            // Update "See All Images" button visibility
            if (seeAllImagesBtn) {
                seeAllImagesBtn.style.display = currentIndex === images.length - 1 ? 'block' : 'none';
            }
        };

        // Initialize
        updateImages();

        // Event listeners for navigation
        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateImages();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentIndex < images.length - 1) {
                currentIndex++;
                updateImages();
            }
        });
    });

    // --- Favorite button functionality ---
    const favoriteBtns = document.querySelectorAll('.favorite-btn');
    if (favoriteBtns.length > 0) {
        favoriteBtns.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Check if user is logged in (nonce should be available)
                if (typeof favouriteListingsData === 'undefined' || typeof favouriteListingsData.ajaxurl === 'undefined' || typeof favouriteListingsData.nonce === 'undefined') {
                    alert('Please log in to add favorites.');
                    return;
                }

                const carId = this.getAttribute('data-car-id');
                const isActive = this.classList.contains('active');
                const heartIcon = this.querySelector('i');
                const card = this.closest('.car-listing-card');

                // Optimistic UI update
                this.classList.toggle('active');
                if (isActive) {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                } else {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                }

                // Prepare AJAX data
                const formData = new FormData();
                formData.append('action', 'toggle_favorite_car');
                formData.append('car_id', carId);
                formData.append('is_favorite', !isActive ? '1' : '0');
                formData.append('nonce', favouriteListingsData.nonce);

                // Send AJAX request
                fetch(favouriteListingsData.ajaxurl, {
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
                        // Revert UI on failure
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
                    } else {
                        // If we're on the favorites page and removing a favorite, remove the card
                        if (isActive) {
                            // Add fade-out animation
                            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(-20px)';
                            
                            // Remove the card after animation
                            setTimeout(() => {
                                card.remove();
                                
                                // Check if there are any cards left
                                const remainingCards = document.querySelectorAll('.car-listing-card');
                                if (remainingCards.length === 0) {
                                    // Reload the page to show the "no favorites" message
                                    window.location.reload();
                                } else {
                                    // Update the results counter immediately
                                    updateResultsCounter(remainingCards.length);
                                }
                            }, 300);
                        }
                    }
                })
                .catch(error => {
                    // Revert UI on network/fetch error
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
        });
    }
}); 