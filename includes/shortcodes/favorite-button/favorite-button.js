/**
 * Shared Favorite Button JavaScript
 * Extracted from single-car-buttons.js for reuse across the site
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === FAVORITE BUTTON FUNCTIONALITY ===
    // Handle clicks on any favorite button (supports multiple buttons on page)
    document.addEventListener('click', function(e) {
        // Check if clicked element is a favorite button
        if (e.target.closest('.favorite-btn')) {
            const favoriteBtn = e.target.closest('.favorite-btn');
            handleFavoriteClick(favoriteBtn, e);
        }
    });
    
    function handleFavoriteClick(favoriteBtn, e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Check if user is logged in first
        if (typeof favoriteButtonData === 'undefined' || !favoriteButtonData.is_user_logged_in) {
            alert('Log in to favourite a listing.');
            return;
        }
        
        // Security checks for logged-in users
        if (typeof favoriteButtonData.ajaxurl === 'undefined' || typeof favoriteButtonData.nonce === 'undefined') {
            alert('Error: Missing data. Please refresh the page and try again.');
            return;
        }

        const carId = favoriteBtn.getAttribute('data-car-id');
        const isActive = favoriteBtn.classList.contains('active');
        const heartIcon = favoriteBtn.querySelector('i');

        // Optimistically update UI
        favoriteBtn.classList.toggle('active');
        if (isActive) {
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
            favoriteBtn.title = 'Add to favorites';
        } else {
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
            favoriteBtn.title = 'Remove from favorites';
        }

        // Prepare AJAX data
        const formData = new FormData();
        formData.append('action', 'toggle_favorite_car');
        formData.append('car_id', carId);
        formData.append('is_favorite', !isActive ? '1' : '0');
        formData.append('nonce', favoriteButtonData.nonce);

        // Send AJAX request
        fetch(favoriteButtonData.ajaxurl, {
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
                // Revert the visual changes on failure
                revertFavoriteUI(favoriteBtn, isActive, heartIcon);
                
                // Show specific error message
                const errorMessage = data.data || 'Unknown error occurred';
                console.error('Favorite toggle failed:', errorMessage);
                alert('Failed to update favorites: ' + errorMessage);
            }
            // Success - UI already updated optimistically
        })
        .catch(error => {
            // Revert the visual changes on error
            revertFavoriteUI(favoriteBtn, isActive, heartIcon);
            console.error('Error:', error);
            alert('Failed to update favorites. An error occurred.');
        });
    }
    
    function revertFavoriteUI(favoriteBtn, wasActive, heartIcon) {
        favoriteBtn.classList.toggle('active');
        if (wasActive) {
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
            favoriteBtn.title = 'Remove from favorites';
        } else {
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
            favoriteBtn.title = 'Add to favorites';
        }
    }
}); 