/**
 * Shared Share Button JavaScript
 * Extracted from single-car-buttons.js for reuse across the site
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === SHARE BUTTON FUNCTIONALITY ===
    // Handle clicks on any share button (supports multiple buttons on page)
    document.addEventListener('click', function(e) {
        // Check if clicked element is a share button
        if (e.target.closest('.share-btn')) {
            const shareBtn = e.target.closest('.share-btn');
            handleShareClick(shareBtn, e);
        }
    });
    
    function handleShareClick(shareBtn, e) {
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
}); 