/**
 * Shared Report Button JavaScript
 * Extracted from single-car-buttons.js for reuse across the site
 */

document.addEventListener('DOMContentLoaded', function() {
    // PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (window.location.hostname === 'localhost' || 
                                               window.location.hostname.includes('staging') ||
                                               window.location.search.includes('debug=true'));
    
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
            
            if (typeof reportButtonData === 'undefined') {
                alert('Error: Unable to submit report. Please refresh the page and try again.');
                return;
            }
            
            const submitBtn = this.querySelector('.submit-report-btn');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;

            const formData = new FormData(this);
            
            fetch(reportButtonData.ajaxurl, {
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
                if (isDevelopment) console.error('Error:', error);
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