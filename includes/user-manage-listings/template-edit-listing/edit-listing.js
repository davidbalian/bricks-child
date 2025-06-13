jQuery(document).ready(function($) {
    // Initialize Async Upload Manager (ADDED FROM ADD LISTING PAGE)
    let asyncUploadManager = null;
    if (typeof AsyncUploadManager !== 'undefined') {
        asyncUploadManager = new AsyncUploadManager();
        
        // Set session ID in the form
        $('#async_session_id').val(asyncUploadManager.session.id);
        
        // Override progress callback for edit listing
        asyncUploadManager.updateUploadProgress = function(fileKey, progress) {
            updateAsyncUploadProgress(fileKey, progress);
        };
        
        asyncUploadManager.onUploadSuccess = function(fileKey, data) {
            onAsyncUploadSuccess(fileKey, data);
        };
        
        asyncUploadManager.onUploadError = function(fileKey, error) {
            onAsyncUploadError(fileKey, error);
        };
        
        asyncUploadManager.onImageRemoved = function(fileKey) {
            onAsyncImageRemoved(fileKey);
        };
        
        console.log('[Edit Listing] Async upload manager initialized with session:', asyncUploadManager.session.id);
    } else {
        console.warn('[Edit Listing] AsyncUploadManager not available - async uploads disabled');
    }

    // Store the makes data
    const makesData = editListingData.makesData;
    let accumulatedFilesList = []; // For newly added files
    
    // Define these early for use in initial count and event handlers
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreviewContainer = $('#image-preview');
    
    // Selector for identifying existing image preview items
    const existingImageSelector = '.image-preview-item:has(.remove-image[data-image-id])';

    // Initial count of existing images on page load
    const initialExistingImageCount = imagePreviewContainer.find(existingImageSelector).length;
    console.log('[Edit Listing] Initial existing images on page load:', initialExistingImageCount);

    // Set initial make value
    const selectedMake = editListingData.selectedMake;
    if (selectedMake) {
        $('#make').val(selectedMake);
    }
    
    // Set initial model and variant options based on the selected make
    if (selectedMake && makesData[selectedMake]) {
        const modelSelect = $('#model');
        Object.keys(makesData[selectedMake]).forEach(model => {
            const option = $('<option>', {
                value: model,
                text: model
            });
            if (model === editListingData.selectedModel) {
                option.prop('selected', true);
            }
            modelSelect.append(option);
        });
        
        // Set initial variant options
        const selectedModel = editListingData.selectedModel;
        if (selectedModel && makesData[selectedMake][selectedModel]) {
            const variantSelect = $('#variant');
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) {
                    const option = $('<option>', {
                        value: variant,
                        text: variant
                    });
                    if (variant === editListingData.selectedVariant) {
                        option.prop('selected', true);
                    }
                    variantSelect.append(option);
                }
            });
        }
    }
    
    // Handle make selection change
    $('#make').on('change', function() {
        const selectedMake = $(this).val();
        const modelSelect = $('#model');
        const variantSelect = $('#variant');
        
        modelSelect.html('<option value="">Select Model</option>');
        variantSelect.html('<option value="">Select Variant</option>');
        
        if (selectedMake && makesData[selectedMake]) {
            Object.keys(makesData[selectedMake]).forEach(model => {
                modelSelect.append($('<option>', { value: model, text: model }));
            });
        }
    });
    
    $('#model').on('change', function() {
        const selectedMake = $('#make').val();
        const selectedModel = $(this).val();
        const variantSelect = $('#variant');
        variantSelect.html('<option value="">Select Variant</option>');
        if (selectedMake && selectedModel && makesData[selectedMake] && makesData[selectedMake][selectedModel]) {
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) {
                    variantSelect.append($('<option>', { value: variant, text: variant }));
                }
            });
        }
    });

    // Handle click on upload area
    fileUploadArea.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    // Handle file selection through dialog
    fileInput.on('change', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const newlySelectedThroughDialog = Array.from(this.files);
        if (newlySelectedThroughDialog.length > 0) {
            processNewFiles(newlySelectedThroughDialog);
        }
        $(this).val(''); // Clear the input to allow re-selecting the same file
    });
    
    // Handle drag and drop
    fileUploadArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });
    
    fileUploadArea.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });
    
    fileUploadArea.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        const droppedFiles = Array.from(e.originalEvent.dataTransfer.files);
        processNewFiles(droppedFiles);
    });
    
    // Handle removing EXISTING images (those loaded with the page)
    imagePreviewContainer.on('click', '.remove-image[data-image-id]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const imageId = $(this).data('image-id');
        if (imageId) {
            // Add hidden input to track removed images for the backend
            $('<input>').attr({
                type: 'hidden',
                name: 'removed_images[]',
                value: imageId
            }).appendTo('#edit-car-listing-form');
            console.log('[Edit Listing] Marked existing image for removal, ID:', imageId);
        }
        $(this).closest('.image-preview-item').remove(); // Use closest to ensure the correct item is removed
    });
    
    function processNewFiles(candidateFiles) {
        console.log('[Edit Listing] Processing', candidateFiles.length, 'new candidate files.');
        const maxTotalFiles = 25;
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        const currentExistingImageDOMCount = imagePreviewContainer.find(existingImageSelector).length;

        console.log('[Edit Listing] Current existing images in DOM (for processNewFiles):', currentExistingImageDOMCount);
        console.log('[Edit Listing] Currently accumulated new files:', accumulatedFilesList.length);

        // Show processing indicator
        showImageProcessingIndicator(true);

        // Initialize the image optimizer
        const optimizer = new ImageOptimizer({
            maxWidth: 1920,
            maxHeight: 1080,
            quality: 0.8,
            maxFileSize: 2048, // 2MB in KB
            allowedTypes: allowedTypes
        });

        // Process files asynchronously with optimization
        processFilesWithOptimization(candidateFiles, optimizer, maxTotalFiles, allowedTypes, maxFileSize, currentExistingImageDOMCount);
    }

    async function processFilesWithOptimization(candidateFiles, optimizer, maxTotalFiles, allowedTypes, maxFileSize, currentExistingImageDOMCount) {
        let filesActuallyAddedInThisBatch = 0;
        let totalSavings = 0;
        let totalOriginalSize = 0;
        let optimizationErrors = 0;

        try {
            for (const file of candidateFiles) {
                // Check if adding this file would exceed the maximum
            if (currentExistingImageDOMCount + accumulatedFilesList.length >= maxTotalFiles) {
                alert('Maximum ' + maxTotalFiles + ' total images allowed. Cannot add "' + file.name + '".');
                    break;
            }

                // FIXED: Check for duplicates using ORIGINAL file properties (before optimization)
            const isDuplicateInNew = accumulatedFilesList.some(
                    existingFile => {
                        // Compare against original properties if they exist, otherwise current properties
                        const existingOriginalName = existingFile.originalName || existingFile.name;
                        const existingOriginalSize = existingFile.originalSize || existingFile.size;
                        const existingOriginalType = existingFile.originalType || existingFile.type;
                        
                        return existingOriginalName === file.name && 
                               existingOriginalSize === file.size && 
                               existingOriginalType === file.type;
                    }
                );
                
            if (isDuplicateInNew) {
                console.log('[Edit Listing] Skipping duplicate new file (already in this edit session):', file.name);
                    continue;
            }

            if (!allowedTypes.includes(file.type)) {
                alert(`File type not allowed for ${file.name}. Only JPG, PNG, GIF, and WebP are permitted.`);
                    continue;
            }

            if (file.size > maxFileSize) {
                alert(`File ${file.name} is too large (max 5MB).`);
                    continue;
                }

                try {
                    // Update processing status
                    updateProcessingStatus('Optimizing ' + file.name + '...');

                    const originalSize = file.size;
                    totalOriginalSize += originalSize;

                    // Optimize the image
                    const optimizedFile = await optimizer.optimizeImage(file);
                    const optimizedSize = optimizedFile.size;
                    totalSavings += (originalSize - optimizedSize);

                    // FIXED: Store original file properties for future duplicate detection
                    optimizedFile.originalName = file.name;
                    optimizedFile.originalSize = file.size;
                    optimizedFile.originalType = file.type;

                    console.log('[Edit Listing] File optimized:', file.name, 'Original:', (originalSize/1024).toFixed(2) + 'KB', 'Optimized:', (optimizedSize/1024).toFixed(2) + 'KB');

                    // ASYNC UPLOAD INTEGRATION - Start background upload
                    if (asyncUploadManager) {
                        try {
                            updateProcessingStatus('Uploading ' + file.name + '...');
                            const fileKey = await asyncUploadManager.addFileToQueue(optimizedFile, file);
                            
                            // Store the file key for tracking (FIXED TO MATCH ADD LISTING)
                            optimizedFile.asyncFileKey = fileKey;
                            optimizedFile.asyncUploadStatus = 'uploading';
                            
                            console.log('[Edit Listing] Started async upload for optimized file:', file.name, 'FileKey:', fileKey);
                        } catch (error) {
                            console.error('[Edit Listing] Failed to start async upload for optimized file:', file.name, error);
                        }
                    }

                    // Add optimized file to our array
                    accumulatedFilesList.push(optimizedFile);
                    createAndDisplayPreviewForNewFile(optimizedFile, originalSize, optimizedSize);
                    filesActuallyAddedInThisBatch++;
                } catch (error) {
                    console.error('[Edit Listing] Error optimizing image:', file.name, error);
                    optimizationErrors++;
                    
                    // Fall back to original file if optimization fails
                    console.log('[Edit Listing] Using original file as fallback for:', file.name);
                    
                    // Even for fallback, store original properties for consistency
                    file.originalName = file.name;
                    file.originalSize = file.size;
                    file.originalType = file.type;

                    // ASYNC UPLOAD INTEGRATION - Start background upload for fallback file
                    if (asyncUploadManager) {
                        try {
                            const fileKey = await asyncUploadManager.addFileToQueue(file);
                            file.asyncFileKey = fileKey;
                            file.asyncUploadStatus = 'uploading';
                            console.log('[Edit Listing] Started async upload for fallback file:', file.name, 'FileKey:', fileKey);
                        } catch (error) {
                            console.error('[Edit Listing] Failed to start async upload for fallback file:', file.name, error);
                        }
                    }

                    accumulatedFilesList.push(file);
                    createAndDisplayPreviewForNewFile(file);
                    filesActuallyAddedInThisBatch++;
                }
            }

            // Show optimization summary
        if (filesActuallyAddedInThisBatch > 0) {
            updateActualFileInput();
                
                if (totalSavings > 0) {
                    const compressionPercent = ((totalSavings / totalOriginalSize) * 100).toFixed(1);
                    showOptimizationSummary(
                        filesActuallyAddedInThisBatch,
                        totalSavings,
                        compressionPercent,
                        optimizationErrors
                    );
                }
            }

        console.log('[Edit Listing] Processed batch. Total new files added in session:', accumulatedFilesList.length);

        } catch (error) {
            console.error('[Edit Listing] Error in batch processing:', error);
        } finally {
            // Hide processing indicator
            showImageProcessingIndicator(false);
        }
    }

    function createAndDisplayPreviewForNewFile(file, originalSize = null, optimizedSize = null) {
        console.log('[Edit Listing] Creating preview for new file:', file.name);
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewItem = $('<div>').addClass('image-preview-item new-image');
            
            // Add async file key if available (FIXED TO MATCH ADD LISTING)
            if (file.asyncFileKey) {
                previewItem.addClass('image-preview').attr('data-async-key', file.asyncFileKey);
            }
            
            const img = $('<img>').attr({ 'src': e.target.result, 'alt': file.name });
            
            const removeBtn = $('<div>').addClass('remove-image remove-new-image')
                .html('<i class="fas fa-times"></i>')
                .on('click', function(event) {
                    event.stopPropagation();
                    console.log('[Edit Listing] Remove button clicked for new file:', file.name);
                    
                    // Remove from async system if applicable (FIXED TO MATCH ADD LISTING)
                    if (file.asyncFileKey && asyncUploadManager) {
                        asyncUploadManager.removeImage(file.asyncFileKey).catch(error => {
                            console.error('[Edit Listing] Failed to remove from async system:', error);
                        });
                    }
                    
                    removeNewFileFromSelection(file.name);
                    previewItem.remove();
                });

            previewItem.append(img).append(removeBtn);
            
            // Add initial upload status if async upload is starting (FIXED TO MATCH ADD LISTING)
            if (file.asyncFileKey) {
                previewItem.append('<div class="upload-status upload-pending">⏳ Uploading...</div>');
            }

            // Add compression stats if available
            if (originalSize && optimizedSize && originalSize !== optimizedSize) {
                const savings = originalSize - optimizedSize;
                const compressionPercent = ((savings / originalSize) * 100).toFixed(1);
                const statsDiv = $('<div>').addClass('image-stats')
                    .html(`
                        <small>Optimized: ${compressionPercent}% smaller</small><br>
                        <small>${(originalSize/1024).toFixed(1)}KB → ${(optimizedSize/1024).toFixed(1)}KB</small>
                    `);
                previewItem.append(statsDiv);
            }
            
            imagePreviewContainer.append(previewItem);
        };
        reader.onerror = function() {
            console.error('[Edit Listing] Error reading new file for preview:', file.name);
        };
        reader.readAsDataURL(file);
    }

    function removeNewFileFromSelection(fileNameToRemove) {
        console.log('[Edit Listing] Attempting to remove new file from selection:', fileNameToRemove);
        
        accumulatedFilesList = accumulatedFilesList.filter(
            file => file.name !== fileNameToRemove
        );
        updateActualFileInput();
        console.log('[Edit Listing] New file removed. Accumulated new files count:', accumulatedFilesList.length);
    }
    
    function updateActualFileInput() {
        const dataTransfer = new DataTransfer();
        accumulatedFilesList.forEach(file => {
            try {
                dataTransfer.items.add(file);
            } catch (error) {
                console.error('[Edit Listing] Error adding new file to DataTransfer:', file.name, error);
            }
        });
        try {
            fileInput[0].files = dataTransfer.files;
        } catch (error) {
            console.error('[Edit Listing] Error setting new files on input element:', error);
        }
        console.log('[Edit Listing] Actual file input updated with new files. Count:', fileInput[0].files.length);
    }
    
    // Format numbers with commas
    function formatNumber(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Remove commas from number
    function unformatNumber(number) {
        return number.toString().replace(/,/g, '');
    }
    
    // Format mileage input
    $('#mileage').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Format price input
    $('#price').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Format HP input
    $('#hp').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Handle form submission
    $('#edit-car-listing-form').on('submit', function(e) {
        // Get the raw values from data attributes
        const rawMileage = $('#mileage').data('raw-value') || unformatNumber($('#mileage').val());
        const rawPrice = $('#price').data('raw-value') || unformatNumber($('#price').val());
        const rawHp = $('#hp').data('raw-value') || unformatNumber($('#hp').val());
        
        // Create hidden inputs with the raw values
        $('<input>').attr({
            type: 'hidden',
            name: 'mileage',
            value: rawMileage
        }).appendTo(this);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'price',
            value: rawPrice
        }).appendTo(this);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'hp',
            value: rawHp
        }).appendTo(this);
        
        // Disable the original inputs
        $('#mileage, #price, #hp').prop('disabled', true);

        // Validate image count - either async uploaded or traditional (FIXED TO MATCH ADD LISTING)
        let totalImages = 0;
        const existingImagesCount = imagePreviewContainer.find(existingImageSelector).length;
        
        if (asyncUploadManager) {
            // Count async uploaded images + existing images
            const asyncUploadedCount = asyncUploadManager.getUploadedAttachmentIds().length;
            totalImages = existingImagesCount + asyncUploadedCount;
            console.log('[Edit Listing] Async mode - Existing:', existingImagesCount, 'Async uploaded:', asyncUploadedCount, 'Total:', totalImages);
        } else {
            // Count traditional uploaded files + existing images
            totalImages = existingImagesCount + accumulatedFilesList.length;
            console.log('[Edit Listing] Traditional mode - Existing:', existingImagesCount, 'New files:', accumulatedFilesList.length, 'Total:', totalImages);
        }
        
        if (totalImages < 5) {
            e.preventDefault();
            alert('Please ensure there are at least 5 images for your car listing (including existing and newly added).');
            return false;
        }
        if (totalImages > 25) {
            e.preventDefault();
            alert('You can have a maximum of 25 images for your car listing (including existing and newly added).');
            return false;
        }

        // If using async uploads, mark session as completed, otherwise use traditional method
        if (asyncUploadManager) {
            // Check if any async uploads are still in progress
            const pendingUploads = accumulatedFilesList.filter(file => 
                file.asyncUploadStatus === 'uploading'
            ).length;
            
            if (pendingUploads > 0) {
                e.preventDefault();
                alert(`Please wait for ${pendingUploads} image(s) to finish uploading before submitting.`);
                return false;
            }
            
            asyncUploadManager.markSessionCompleted();
            console.log('[Edit Listing] Async upload session marked as completed');
            
            // Clear file input to prevent duplicate uploads when using async system
            const fileInput = $('#car_images')[0];
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                fileInput.value = '';
                console.log('[Edit Listing] Cleared file input for async uploads');
            }
        } else {
            // For traditional uploads, ensure fileInput has correct files
            updateActualFileInput();
        }
        
        console.log('🚀 [Edit Listing] All validations passed - form will now submit');
        // Form should submit normally after this point
    });

    // Initialize location picker
    $('.choose-location-btn').on('click', function() {
        // Open the location picker modal
        $('#location-picker-modal').show();
        
        // Initialize the map if not already initialized
        if (!window.locationMap) {
            initializeLocationMap();
        }
    });

    // Function to initialize the location map
    function initializeLocationMap() {
        // Create map instance
        window.locationMap = L.map('location-map').setView([51.505, -0.09], 13);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(window.locationMap);

        // Add marker
        window.locationMarker = L.marker([51.505, -0.09], {
            draggable: true
        }).addTo(window.locationMap);

        // Handle marker drag end
        window.locationMarker.on('dragend', function(e) {
            updateLocationFields(e.target.getLatLng());
        });

        // Handle map click
        window.locationMap.on('click', function(e) {
            window.locationMarker.setLatLng(e.latlng);
            updateLocationFields(e.latlng);
        });
    }

    // Function to update location fields
    function updateLocationFields(latlng) {
        // Reverse geocode the coordinates
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)
            .then(response => response.json())
            .then(data => {
                // Update the location fields
                $('#location').val(data.display_name);
                $('#car_latitude').val(latlng.lat);
                $('#car_longitude').val(latlng.lng);
                $('#car_address').val(data.display_name);
                
                // Extract city and district from address components
                const address = data.address;
                $('#car_city').val(address.city || address.town || address.village || '');
                $('#car_district').val(address.county || address.state || '');
            })
            .catch(error => console.error('Error:', error));
    }

    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#location-picker-modal')) {
            $('#location-picker-modal').hide();
        }
    });

    // Collapsible sections
    $('.collapsible-section-title').on('click', function() {
        $(this).toggleClass('active');
        var content = $(this).next('.collapsible-section-content');
        content.slideToggle(300); // Use slideToggle for a smooth animation
        
        // Optional: Change arrow direction if you want to swap between ▼ and ▲
        // var arrow = $(this).find('.toggle-arrow');
        // if ($(this).hasClass('active')) {
        //     arrow.text('▲');
        // } else {
        //     arrow.text('▼');
        // }
    });

    // Helper functions for optimization feedback
    function showImageProcessingIndicator(show) {
        if (show) {
            if ($('.image-processing-indicator').length === 0) {
                const indicator = $(`
                    <div class="image-processing-indicator">
                        <div class="processing-spinner"></div>
                        <span class="processing-text">Optimizing images...</span>
                        <span class="processing-status"></span>
                    </div>
                `);
                imagePreviewContainer.before(indicator);
            }
        } else {
            $('.image-processing-indicator').remove();
        }
    }

    function updateProcessingStatus(status) {
        $('.processing-status').text(status);
    }

    function showOptimizationSummary(fileCount, totalSavings, compressionPercent, errors) {
        // Remove any existing summaries
        $('.optimization-summary, .error-summary').remove();
        
        const summaryClass = errors > 0 ? 'error-summary' : 'optimization-summary';
        const message = errors > 0 
            ? `${fileCount} images processed with ${errors} optimization errors`
            : `${fileCount} images optimized! Saved ${(totalSavings/1024).toFixed(1)}KB (${compressionPercent}% compression)`;
            
        const summary = $(`<div class="${summaryClass}">${message}</div>`);
        imagePreviewContainer.before(summary);
        
        // Remove summary after 5 seconds
        setTimeout(() => {
            summary.fadeOut(() => summary.remove());
        }, 5000);
    }

    /**
     * Async upload callback functions (ADDED TO MATCH ADD LISTING PAGE)
     */
    function updateAsyncUploadProgress(fileKey, progress) {
        const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
        if ($preview.length) {
            let $progressBar = $preview.find('.upload-progress');
            if (!$progressBar.length) {
                $progressBar = $('<div class="upload-progress"><div class="upload-progress-bar"></div><span class="upload-progress-text">0%</span></div>');
                $preview.append($progressBar);
            }
            
            // Update CSS custom property for progress bar
            $progressBar.find('.upload-progress-bar').css('--progress', progress + '%');
            $progressBar.find('.upload-progress-text').text(progress + '%');
            
            if (progress >= 100) {
                setTimeout(() => {
                    $progressBar.fadeOut(() => $progressBar.remove());
                }, 1000);
            }
        }
    }
    
    function onAsyncUploadSuccess(fileKey, data) {
        const fileIndex = accumulatedFilesList.findIndex(file => file.asyncFileKey === fileKey);
        if (fileIndex !== -1) {
            accumulatedFilesList[fileIndex].asyncUploadStatus = 'completed';
            accumulatedFilesList[fileIndex].attachmentId = data.attachment_id;
            
            const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
            $preview.find('.upload-status').remove();
            $preview.append('<div class="upload-status upload-success">✓ Uploaded</div>');
            
            setTimeout(() => {
                $preview.find('.upload-success').fadeOut(() => {
                    $preview.find('.upload-success').remove();
                });
            }, 3000);
            
            console.log('[Edit Listing] Async upload completed for:', data.original_filename);
        }
    }
    
    function onAsyncUploadError(fileKey, error) {
        const fileIndex = accumulatedFilesList.findIndex(file => file.asyncFileKey === fileKey);
        if (fileIndex !== -1) {
            accumulatedFilesList[fileIndex].asyncUploadStatus = 'failed';
            accumulatedFilesList[fileIndex].asyncUploadError = error.message;
            
            const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
            $preview.find('.upload-status').remove();
            $preview.append('<div class="upload-status upload-error">✗ Upload failed</div>');
            
            // Show fallback message below upload area
            showAsyncUploadFallbackMessage();
            
            console.error('[Edit Listing] Async upload failed for file key:', fileKey, error);
        }
    }
    
    function showAsyncUploadFallbackMessage() {
        // Only show if not already shown
        if ($('.async-upload-fallback-message').length === 0) {
            const message = $(`
                <div class="async-upload-fallback-message">
                    <i class="fas fa-info-circle fallback-icon"></i>
                    <span>Background upload failed but images will submit normally when you press submit. You may continue filling the form.</span>
                </div>
            `);
            
            // Insert after the file upload area
            fileUploadArea.after(message);
        }
    }
    
    function onAsyncImageRemoved(fileKey) {
        // Remove from accumulated files list
        const fileIndex = accumulatedFilesList.findIndex(file => file.asyncFileKey === fileKey);
        if (fileIndex !== -1) {
            accumulatedFilesList.splice(fileIndex, 1);
            updateActualFileInput();
        }
        
        // Remove preview element
        $(`.image-preview[data-async-key="${fileKey}"]`).fadeOut(() => {
            $(`.image-preview[data-async-key="${fileKey}"]`).remove();
        });
        
        console.log('[Edit Listing] Image removed from async system:', fileKey);
    }
}); 