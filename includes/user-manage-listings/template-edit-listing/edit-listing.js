jQuery(document).ready(function($) {
    // PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (window.location.hostname === 'localhost' ||
                                               window.location.hostname.includes('staging') ||
                                               window.location.search.includes('debug=true'));

    // =====================================================
    // FORM VALIDATION CONFIGURATION
    // =====================================================
    const requiredFields = {
        'edit-listing-year': { type: 'dropdown', label: 'Year' },
        'edit-listing-availability': { type: 'dropdown', label: 'Availability' },
        'edit-listing-engine-capacity': { type: 'dropdown', label: 'Engine Capacity' },
        'edit-listing-fuel-type': { type: 'dropdown', label: 'Fuel Type' },
        'edit-listing-transmission': { type: 'dropdown', label: 'Transmission' },
        'edit-listing-body-type': { type: 'dropdown', label: 'Body Type' },
        'edit-listing-exterior-color': { type: 'dropdown', label: 'Exterior Color' },
        'mileage': { type: 'text', label: 'Mileage' },
        'price': { type: 'text', label: 'Price' }
    };

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
        
        if (isDevelopment) console.log('[Edit Listing] Async upload manager initialized with session:', asyncUploadManager.session.id);
    } else {
        if (isDevelopment) console.warn('[Edit Listing] AsyncUploadManager not available - async uploads disabled');
    }

    // Store the makes data
    const makesData = editListingData.makesData;
    let accumulatedFilesList = []; // For newly added files

    // Keep engine capacity behavior aligned with add listing (lock for electric)
    const $fuelType = $('#fuel_type');
    const $engineCapacity = $('#engine_capacity');

    function handleElectricFuelType() {
        if (!$fuelType.length || !$engineCapacity.length) return;

        const selectedFuelType = $fuelType.val();

        if (selectedFuelType === 'Electric') {
            if ($engineCapacity.find('option[value="0.0"]').length === 0) {
                $engineCapacity
                    .find('option[value=""]')
                    .after('<option value="0.0">0.0</option>');
            }
            $engineCapacity.val('0.0');
            $engineCapacity.prop('disabled', true);
            $engineCapacity.addClass('electric-locked');

            // Clear any validation error since field is now auto-filled
            clearFieldError('edit-listing-engine-capacity');
        } else {
            $engineCapacity.prop('disabled', false);
            $engineCapacity.removeClass('electric-locked');

            if ($engineCapacity.val() === '0.0') {
                $engineCapacity.val('');
            }
            $engineCapacity.find('option[value="0.0"]').remove();
        }
    }

    handleElectricFuelType();
    $fuelType.on('change', handleElectricFuelType);

    // =====================================================
    // FORM VALIDATION FUNCTIONS
    // =====================================================

    /**
     * Get the current total image count (existing + new async uploads)
     */
    function getImageCount() {
        const existingImagesCount = imagePreviewContainer.find(existingImageSelector).length;

        if (asyncUploadManager) {
            const asyncUploadedCount = asyncUploadManager.getUploadedAttachmentIds().length;
            return existingImagesCount + asyncUploadedCount;
        }
        return existingImagesCount + accumulatedFilesList.length;
    }

    /**
     * Check if a specific field is valid
     */
    function isFieldValid(fieldId, config) {
        if (config.type === 'dropdown') {
            const $wrapper = $('#' + fieldId + '-wrapper');
            const $select = $wrapper.find('select');

            // Skip engine capacity validation for electric vehicles
            if (fieldId === 'edit-listing-engine-capacity') {
                const fuelType = $('#edit-listing-fuel-type').val();
                if (fuelType === 'Electric') {
                    return true; // Electric vehicles auto-fill 0.0
                }
            }

            const value = $select.val();
            return value !== '' && value !== null && value !== undefined;
        } else if (config.type === 'text') {
            const $input = $('#' + fieldId);
            const value = $input.val().trim();
            return value !== '';
        }
        return false;
    }

    /**
     * Validate all required fields and return validation result
     */
    function validateAllFields() {
        const errors = [];

        // Check images first (minimum 2) - they're at the top of the page
        const imageCount = getImageCount();
        if (imageCount < 2) {
            errors.push({ fieldId: 'image-preview', label: 'Images (minimum 2)', type: 'images' });
        }

        // Check all required fields
        for (const [fieldId, config] of Object.entries(requiredFields)) {
            if (!isFieldValid(fieldId, config)) {
                errors.push({ fieldId, label: config.label, type: config.type });
            }
        }

        // Check location
        const locationValue = $('#location').val();
        if (!locationValue || locationValue.trim() === '') {
            errors.push({ fieldId: 'location-row', label: 'Location', type: 'location' });
        }

        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Clear errors for valid fields (called on field change)
     * Note: Button always stays enabled - validation happens on submit
     */
    function updateSubmitButtonState() {
        // No-op: button always stays enabled, validation happens on submit click
    }

    /**
     * Show error state for a specific field
     */
    function showFieldError(fieldId, label) {
        let $container;

        if (fieldId.startsWith('edit-listing-')) {
            // Dropdown field
            $container = $('#' + fieldId + '-wrapper').closest('.form-third, .form-half, .form-row');
        } else if (fieldId === 'location-row') {
            $container = $('#location-row');
        } else if (fieldId === 'image-preview') {
            // Images section - add error class and message
            const $imagesSection = $('.add-listing-images-section');
            $imagesSection.addClass('images-section-has-error');
            if (!$imagesSection.find('.images-section-error').length) {
                $imagesSection.append('<p class="images-section-error">Please ensure at least 2 images</p>');
            }
            return;
        } else {
            // Text input
            $container = $('#' + fieldId).closest('.form-third, .form-half, .form-row');
        }

        if ($container.length) {
            $container.addClass('field-has-error');

            // Add error message if not already present
            if (!$container.find('.field-error-message').length) {
                $container.append('<span class="field-error-message">This field is required</span>');
            }
        }
    }

    /**
     * Clear error state for a specific field
     */
    function clearFieldError(fieldId) {
        let $container;

        if (fieldId.startsWith('edit-listing-')) {
            $container = $('#' + fieldId + '-wrapper').closest('.form-third, .form-half, .form-row');
        } else if (fieldId === 'location-row') {
            $container = $('#location-row');
        } else if (fieldId === 'image-preview') {
            $('.add-listing-images-section').removeClass('images-section-has-error');
            $('.add-listing-images-section .images-section-error').remove();
            return;
        } else {
            $container = $('#' + fieldId).closest('.form-third, .form-half, .form-row');
        }

        if ($container.length) {
            $container.removeClass('field-has-error');
            $container.find('.field-error-message').remove();
        }
    }

    /**
     * Clear all field errors
     */
    function clearAllFieldErrors() {
        $('.field-has-error').removeClass('field-has-error');
        $('.field-error-message').remove();
        $('.images-section-has-error').removeClass('images-section-has-error');
        $('.images-section-error').remove();
        $('#location-row').removeClass('has-error');
    }

    /**
     * Show all validation errors and scroll to first error
     */
    function showAllValidationErrors(errors) {
        // First clear all existing errors
        clearAllFieldErrors();

        // Show each error
        errors.forEach(error => {
            showFieldError(error.fieldId, error.label);
        });

        // Scroll to first error
        if (errors.length > 0) {
            let $firstError;
            const firstErrorId = errors[0].fieldId;

            if (firstErrorId === 'image-preview') {
                $firstError = $('.add-listing-images-section');
            } else if (firstErrorId === 'location-row') {
                $firstError = $('#location-row');
            } else if (firstErrorId.startsWith('edit-listing-')) {
                $firstError = $('#' + firstErrorId + '-wrapper');
            } else {
                $firstError = $('#' + firstErrorId);
            }

            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 300);
            }
        }
    }

    /**
     * Bind validation events to form fields
     */
    function bindValidationEvents() {
        // Dropdown changes - listen to hidden select change events
        Object.keys(requiredFields).forEach(fieldId => {
            const config = requiredFields[fieldId];

            if (config.type === 'dropdown') {
                const $select = $('#' + fieldId);
                $select.on('change', function() {
                    // Clear error for this field if now valid
                    if (isFieldValid(fieldId, config)) {
                        clearFieldError(fieldId);
                    }
                    updateSubmitButtonState();
                });
            } else if (config.type === 'text') {
                const $input = $('#' + fieldId);
                $input.on('input blur', function() {
                    if (isFieldValid(fieldId, config)) {
                        clearFieldError(fieldId);
                    }
                    updateSubmitButtonState();
                });
            }
        });

        // Location field - observe hidden field value changes
        const locationObserver = new MutationObserver(function() {
            const hasLocation = $('#location').val().trim() !== '';
            if (hasLocation) {
                clearFieldError('location-row');
                $('#location-row').removeClass('has-error');
            }
            updateSubmitButtonState();
        });

        const $locationField = document.getElementById('location');
        if ($locationField) {
            locationObserver.observe($locationField, { attributes: true, attributeFilter: ['value'] });

            // Also listen to direct value changes
            $('#location').on('change', function() {
                const hasLocation = $(this).val().trim() !== '';
                if (hasLocation) {
                    clearFieldError('location-row');
                    $('#location-row').removeClass('has-error');
                }
                updateSubmitButtonState();
            });
        }

        if (isDevelopment) console.log('[Edit Listing] Validation events bound');
    }

    // Define these early for use in initial count and event handlers
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreviewContainer = $('#image-preview');
    
    // Selector for identifying existing image preview items
    const existingImageSelector = '.image-preview-item:has(.remove-image[data-image-id])';

    // Initial count of existing images on page load
    const initialExistingImageCount = imagePreviewContainer.find(existingImageSelector).length;
    if (isDevelopment) console.log('[Edit Listing] Initial existing images on page load:', initialExistingImageCount);

    /**
     * IMAGE REORDERING (drag & drop)
     * --------------------------------
     * We use native HTML5 drag & drop on each .image-preview-item.
     * - Desktop: hover → click & hold → drag
     * - Mobile: tap & hold where supported.
     *
     * Reordering affects:
     * - The DOM order of .image-preview-item
     * - The order we send to PHP via hidden image_order[] inputs
     * - The order of accumulatedFilesList for traditional uploads
     */
    let dragSourceItem = null;

    function applyGrabCursor($item) {
        if ($item && $item.length) {
            $item.css('cursor', 'grab');
        }
    }

    // Attach identity metadata to existing items (their attachment IDs)
    imagePreviewContainer.find('.image-preview-item').each(function () {
        const $item = $(this);
        const imageId = $item.find('.remove-image[data-image-id]').data('image-id');
        if (imageId) {
            $item.data('imageId', parseInt(imageId, 10));
        }
        $item.attr('draggable', 'true');
        applyGrabCursor($item);
        attachSwapHandle($item);
    });

    function enableImageReordering() {
        imagePreviewContainer.on('dragstart', '.image-preview-item', function (e) {
            dragSourceItem = this;
            $(this).addClass('dragging');
            $(this).css('cursor', 'grabbing');
            if (e.originalEvent && e.originalEvent.dataTransfer) {
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', 'drag');
            }
        });

        imagePreviewContainer.on('dragover', '.image-preview-item', function (e) {
            e.preventDefault();
            if (e.originalEvent && e.originalEvent.dataTransfer) {
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
        });

        imagePreviewContainer.on('drop', '.image-preview-item', function (e) {
            e.preventDefault();
            if (!dragSourceItem || dragSourceItem === this) {
                return;
            }

            const $dragSource = $(dragSourceItem);
            const $target = $(this);

            if ($target.index() < $dragSource.index()) {
                $target.before($dragSource);
            } else {
                $target.after($dragSource);
            }

            imagePreviewContainer.find('.image-preview-item').removeClass('dragging');
            imagePreviewContainer.find('.image-preview-item').css('cursor', 'grab');
            dragSourceItem = null;

            syncNewFilesWithDomOrder();
            updateImageOrderField();
        });

        imagePreviewContainer.on('dragend', '.image-preview-item', function () {
            imagePreviewContainer.find('.image-preview-item').removeClass('dragging');
            imagePreviewContainer.find('.image-preview-item').css('cursor', 'grab');
            dragSourceItem = null;
        });
    }

    function swapPreviewWithNeighbour($item) {
        if (!$item || !$item.length) return;

        let swapped = false;
        const $next = $item.next('.image-preview-item');

        if ($next.length) {
            $next.after($item);
            swapped = true;
        } else {
            const $prev = $item.prev('.image-preview-item');
            if ($prev.length) {
                $prev.before($item);
                swapped = true;
            }
        }

        if (swapped) {
            syncNewFilesWithDomOrder();
            updateImageOrderField();
        }
    }

    function attachSwapHandle($item) {
        if ($item.find('.image-swap-handle').length) return;

        const handle = $('<button type="button" class="image-swap-handle" aria-label="Swap image position">&larr;&rarr;</button>')
            .css({
                position: 'absolute',
                bottom: '8px',
                right: '8px',
                border: 'none',
                borderRadius: '999px',
                padding: '4px 8px',
                fontSize: '0.75rem',
                lineHeight: '1',
                background: 'rgba(0,0,0,0.65)',
                color: '#fff',
                cursor: 'pointer',
                boxShadow: '0 2px 6px rgba(0,0,0,0.25)',
            })
            .on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                swapPreviewWithNeighbour($item);
            });

        $item.append(handle);
    }

    function syncNewFilesWithDomOrder() {
        if (!accumulatedFilesList.length) return;

        const reorderedNewFiles = [];
        imagePreviewContainer.find('.image-preview-item').each(function () {
            const fileObj = $(this).data('fileObj');
            if (fileObj) {
                reorderedNewFiles.push(fileObj);
            }
        });

        if (reorderedNewFiles.length === accumulatedFilesList.length) {
            accumulatedFilesList = reorderedNewFiles;
            updateActualFileInput();
            if (isDevelopment) {
                console.log(
                    '[Edit Listing] New files reordered. New order:',
                    accumulatedFilesList.map((f) => f.name)
                );
            }
        }
    }

    function updateImageOrderField() {
        const $form = $('#edit-car-listing-form');
        if (!$form.length) return;

        // Clear old order inputs
        $form.find('input[name="image_order[]"]').remove();

        // Build new order from DOM, mixing existing + async-completed images
        imagePreviewContainer.find('.image-preview-item').each(function () {
            const imageId = $(this).data('imageId');
            const fileObj = $(this).data('fileObj');
            let attachmentId = null;

            if (imageId) {
                attachmentId = imageId;
            } else if (fileObj && fileObj.attachmentId) {
                attachmentId = fileObj.attachmentId;
            }

            if (attachmentId) {
                $('<input>')
                    .attr({
                        type: 'hidden',
                        name: 'image_order[]',
                        value: attachmentId,
                    })
                    .appendTo($form);
            }
        });
    }

    // Enable drag & drop reordering on load
    enableImageReordering();

    // Set initial make value
    const selectedMake = editListingData.selectedMake;
    if (selectedMake) {
        $('#make').val(selectedMake);
    }
    
    // Set initial model options based on the selected make
    if (selectedMake && makesData[selectedMake]) {
        const modelSelect = $('#model');
        // makesData[selectedMake] is now a simple array of models
        makesData[selectedMake].forEach(model => {
            const option = $('<option>', {
                value: model,
                text: model
            });
            if (model === editListingData.selectedModel) {
                option.prop('selected', true);
            }
            modelSelect.append(option);
        });
        
        // variant options removed
    }
    
    // Handle make selection change
    $('#make').on('change', function() {
        const selectedMake = $(this).val();
        const modelSelect = $('#model');
        
        modelSelect.html('<option value="">Select Model</option>');
        
        if (selectedMake && makesData[selectedMake]) {
            // makesData[selectedMake] is now a simple array of models
            makesData[selectedMake].forEach(model => {
                modelSelect.append($('<option>', { value: model, text: model }));
            });
        }
    });
    
    // variant handling removed from model change

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
            if (isDevelopment) console.log('[Edit Listing] Marked existing image for removal, ID:', imageId);
        }
        $(this).closest('.image-preview-item').remove(); // Use closest to ensure the correct item is removed
        // After removal, refresh order field
        updateImageOrderField();

        // Update validation state after image removal
        updateSubmitButtonState();
    });
    
    function processNewFiles(candidateFiles) {
        if (isDevelopment) console.log('[Edit Listing] Processing', candidateFiles.length, 'new candidate files.');
        const maxTotalFiles = 25;
        const maxFileSize = 12 * 1024 * 1024; // 12MB
        const allowedTypes = ['image/jpeg', 'image/jfif', 'image/pjpeg', 'image/jpg', 'image/x-jfif', 'image/pipeg', 'image/png', 'image/gif', 'image/webp'];
        const allowedExtensions = ['jpg', 'jpeg', 'jfif', 'jpe', 'png', 'gif', 'webp'];

        const currentExistingImageDOMCount = imagePreviewContainer.find(existingImageSelector).length;

        if (isDevelopment) console.log('[Edit Listing] Current existing images in DOM (for processNewFiles):', currentExistingImageDOMCount);
        if (isDevelopment) console.log('[Edit Listing] Currently accumulated new files:', accumulatedFilesList.length);

        // Show processing indicator
        showImageProcessingIndicator(true);

        // Initialize the image optimizer (minimal processing - server handles WebP)
        const optimizer = new ImageOptimizer({
            maxWidth: 1920,
            maxHeight: 1080,
            quality: 0.8,
            maxFileSize: 12288, // 12MB in KB - only process very large files
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
                if (isDevelopment) console.log('[Edit Listing] Skipping duplicate new file (already in this edit session):', file.name);
                    continue;
            }

            if (!allowedTypes.includes(file.type)) {
                alert(`File type not allowed for ${file.name}. Only JPG, PNG, GIF, and WebP are permitted.`);
                    continue;
            }

            if (file.size > maxFileSize) {
                alert(`File ${file.name} is too large (max 12MB).`);
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

                    if (isDevelopment) console.log('[Edit Listing] File optimized:', file.name, 'Original:', (originalSize/1024).toFixed(2) + 'KB', 'Optimized:', (optimizedSize/1024).toFixed(2) + 'KB');

                    // ASYNC UPLOAD INTEGRATION - Start background upload
                    if (asyncUploadManager) {
                        try {
                            updateProcessingStatus('Uploading ' + file.name + '...');
                            const fileKey = await asyncUploadManager.addFileToQueue(optimizedFile, file);
                            
                            // Store the file key for tracking (FIXED TO MATCH ADD LISTING)
                            optimizedFile.asyncFileKey = fileKey;
                            optimizedFile.asyncUploadStatus = 'uploading';
                            
                            if (isDevelopment) console.log('[Edit Listing] Started async upload for optimized file:', file.name, 'FileKey:', fileKey);
                        } catch (error) {
                            if (isDevelopment) console.error('[Edit Listing] Failed to start async upload for optimized file:', file.name, error);
                        }
                    }

                    // Add optimized file to our array
                    accumulatedFilesList.push(optimizedFile);
                    createAndDisplayPreviewForNewFile(optimizedFile, originalSize, optimizedSize);
                    filesActuallyAddedInThisBatch++;
                } catch (error) {
                    if (isDevelopment) console.error('[Edit Listing] Error optimizing image:', file.name, error);
                    optimizationErrors++;
                    
                    // Fall back to original file if optimization fails
                    if (isDevelopment) console.log('[Edit Listing] Using original file as fallback for:', file.name);
                    
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
                            if (isDevelopment) console.log('[Edit Listing] Started async upload for fallback file:', file.name, 'FileKey:', fileKey);
                        } catch (error) {
                            if (isDevelopment) console.error('[Edit Listing] Failed to start async upload for fallback file:', file.name, error);
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

        if (isDevelopment) console.log('[Edit Listing] Processed batch. Total new files added in session:', accumulatedFilesList.length);

        // Update validation state after files are added
        clearFieldError('image-preview');
        updateSubmitButtonState();

        } catch (error) {
            if (isDevelopment) console.error('[Edit Listing] Error in batch processing:', error);
        } finally {
            // Hide processing indicator
            showImageProcessingIndicator(false);
        }
    }

    function createAndDisplayPreviewForNewFile(file, originalSize = null, optimizedSize = null) {
        if (isDevelopment) console.log('[Edit Listing] Creating preview for new file:', file.name);
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewItem = $('<div>').addClass('image-preview-item new-image');
            // Keep reference to underlying file for later reordering
            previewItem.data('fileObj', file);
            
            // Add async file key if available (FIXED TO MATCH ADD LISTING)
            if (file.asyncFileKey) {
                previewItem.addClass('image-preview').attr('data-async-key', file.asyncFileKey);
            }
            
            const img = $('<img>').attr({ 'src': e.target.result, 'alt': file.name });
            
            const removeBtn = $('<div>').addClass('remove-image remove-new-image')
                .html('<i class="fas fa-times"></i>')
                .on('click', function(event) {
                    event.stopPropagation();
                    if (isDevelopment) console.log('[Edit Listing] Remove button clicked for new file:', file.name);
                    
                    // Remove from async system if applicable (FIXED TO MATCH ADD LISTING)
                    if (file.asyncFileKey && asyncUploadManager) {
                        asyncUploadManager.removeImage(file.asyncFileKey).catch(error => {
                            if (isDevelopment) console.error('[Edit Listing] Failed to remove from async system:', error);
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

            // Ensure new items are draggable, have swap handle and are part of the order calculations
            previewItem.attr('draggable', 'true');
            applyGrabCursor(previewItem);
            attachSwapHandle(previewItem);
            updateImageOrderField();
        };
        reader.onerror = function() {
            if (isDevelopment) console.error('[Edit Listing] Error reading new file for preview:', file.name);
        };
        reader.readAsDataURL(file);
    }

    function removeNewFileFromSelection(fileNameToRemove) {
        if (isDevelopment) console.log('[Edit Listing] Attempting to remove new file from selection:', fileNameToRemove);

        accumulatedFilesList = accumulatedFilesList.filter(
            file => file.name !== fileNameToRemove
        );
        updateActualFileInput();
        if (isDevelopment) console.log('[Edit Listing] New file removed. Accumulated new files count:', accumulatedFilesList.length);

        // Update validation state after image removal
        updateSubmitButtonState();
    }
    
    function updateActualFileInput() {
        const dataTransfer = new DataTransfer();
        accumulatedFilesList.forEach(file => {
            try {
                dataTransfer.items.add(file);
            } catch (error) {
                if (isDevelopment) console.error('[Edit Listing] Error adding new file to DataTransfer:', file.name, error);
            }
        });
        try {
            fileInput[0].files = dataTransfer.files;
        } catch (error) {
            if (isDevelopment) console.error('[Edit Listing] Error setting new files on input element:', error);
        }
        if (isDevelopment) console.log('[Edit Listing] Actual file input updated with new files. Count:', fileInput[0].files.length);
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
        // Run full validation and show all errors
        const validation = validateAllFields();

        if (!validation.isValid) {
            e.preventDefault();
            showAllValidationErrors(validation.errors);

            if (isDevelopment) console.log('[Edit Listing] Form submission blocked - validation failed:', validation.errors);
            return false;
        }

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

        // Ensure engine capacity value submits even when locked for electric
        if ($engineCapacity.length && $engineCapacity.prop('disabled')) {
            $engineCapacity.prop('disabled', false);
        }
        
        // Disable the original inputs
        $('#mileage, #price, #hp').prop('disabled', true);

        // Validate image count - either async uploaded or traditional (FIXED TO MATCH ADD LISTING)
        let totalImages = 0;
        const existingImagesCount = imagePreviewContainer.find(existingImageSelector).length;
        
        if (asyncUploadManager) {
            // Count async uploaded images + existing images
            const asyncUploadedCount = asyncUploadManager.getUploadedAttachmentIds().length;
            totalImages = existingImagesCount + asyncUploadedCount;
            if (isDevelopment) console.log('[Edit Listing] Async mode - Existing:', existingImagesCount, 'Async uploaded:', asyncUploadedCount, 'Total:', totalImages);
        } else {
            // Count traditional uploaded files + existing images
            totalImages = existingImagesCount + accumulatedFilesList.length;
            if (isDevelopment) console.log('[Edit Listing] Traditional mode - Existing:', existingImagesCount, 'New files:', accumulatedFilesList.length, 'Total:', totalImages);
        }
        
        if (totalImages < 2) {
            e.preventDefault();
            alert('Please ensure there are at least 2 images for your car listing (including existing and newly added).');
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
            if (isDevelopment) console.log('[Edit Listing] Async upload session marked as completed');
            
            // Clear file input to prevent duplicate uploads when using async system
            const fileInput = $('#car_images')[0];
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                fileInput.value = '';
                if (isDevelopment) console.log('[Edit Listing] Cleared file input for async uploads');
            }
        } else {
            // For traditional uploads, ensure fileInput has correct files
            updateActualFileInput();
        }
        
        if (isDevelopment) console.log('🚀 [Edit Listing] All validations passed - form will now submit');
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
                                .catch(error => { if (isDevelopment) console.error('Error:', error); });
    }

    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#location-picker-modal')) {
            $('#location-picker-modal').hide();
        }
    });

    // Collapsible sections (matching add-listing.js)
    $(".collapsible-section .section-header").on("click", function () {
        const $section = $(this).closest(".collapsible-section");
        $section.toggleClass("collapsed");
        const isExpanded = !$section.hasClass("collapsed");
        $(this).attr("aria-expanded", isExpanded);
    });

    $(".collapsible-section .section-header").on("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            $(this).trigger("click");
        }
    });

    // Initialize validation after a short delay to ensure DOM is ready
    setTimeout(function() {
        bindValidationEvents();
        updateSubmitButtonState();
    }, 100);

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

            if (isDevelopment) console.log('[Edit Listing] Async upload completed for:', data.original_filename);

            updateImageOrderField();

            // Update validation state after image upload completes
            clearFieldError('image-preview');
            updateSubmitButtonState();
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
            
            if (isDevelopment) console.error('[Edit Listing] Async upload failed for file key:', fileKey, error);
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

        if (isDevelopment) console.log('[Edit Listing] Image removed from async system:', fileKey);

        // Update validation state after image removal
        updateSubmitButtonState();
    }

    // =====================================================
    // SAVED LOCATIONS DROPDOWN CONTROLLER
    // =====================================================

    /**
     * Custom Dropdown Controller for Edit Listing page
     */
    var EditListingDropdown = {
        init: function() {
            this.bindEvents();
            if (isDevelopment) console.log("[Edit Listing] Custom dropdown controller initialized");
        },

        bindEvents: function() {
            var self = this;

            // Toggle dropdown
            $(document).on('click', '.car-filter-dropdown-button', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if ($(this).prop('disabled')) return;

                var $dropdown = $(this).closest('.car-filter-dropdown');
                self.toggle($dropdown);
            });

            // Select option
            $(document).on('click', '.car-filter-dropdown-option', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $dropdown = $(this).closest('.car-filter-dropdown');
                self.selectOption($dropdown, $(this));
            });

            // Search input
            $(document).on('input', '.car-filter-dropdown-search', function() {
                var $dropdown = $(this).closest('.car-filter-dropdown');
                self.filterOptions($dropdown, $(this).val());
            });

            // Close on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.car-filter-dropdown').length) {
                    self.closeAll();
                }
            });

            // Keyboard navigation
            $(document).on('keydown', '.car-filter-dropdown', function(e) {
                self.handleKeyboard($(this), e);
            });
        },

        toggle: function($dropdown) {
            var isOpen = $dropdown.hasClass('open');
            this.closeAll();

            if (!isOpen) {
                $dropdown.addClass('open');
                $dropdown.find('.car-filter-dropdown-button').attr('aria-expanded', 'true');
                $dropdown.find('.car-filter-dropdown-search').focus();
            }
        },

        closeAll: function() {
            $('.car-filter-dropdown.open').removeClass('open')
                .find('.car-filter-dropdown-button').attr('aria-expanded', 'false');
        },

        selectOption: function($dropdown, $option) {
            var value = $option.data('value');
            var label = $option.clone().children('.car-filter-count').remove().end().text().trim();
            var filterType = $dropdown.data('filter-type');

            // Update hidden select
            var $select = $dropdown.find('select');
            $select.val(value).trigger('change');

            // Update button text
            var $button = $dropdown.find('.car-filter-dropdown-button');
            var $text = $button.find('.car-filter-dropdown-text');

            if (value === '' || value === null || value === undefined) {
                $text.addClass('placeholder').text($select.find('option:first').text());
            } else {
                $text.removeClass('placeholder').text(label);
            }

            // Update selected state
            $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
            $option.addClass('selected');

            // Close dropdown
            this.closeAll();

            // Clear search
            $dropdown.find('.car-filter-dropdown-search').val('');
            this.filterOptions($dropdown, '');
        },

        filterOptions: function($dropdown, query) {
            var $options = $dropdown.find('.car-filter-dropdown-option');
            var $noResults = $dropdown.find('.car-filter-no-results');
            var hasVisible = false;

            query = query.toLowerCase().trim();

            $options.each(function() {
                var text = $(this).text().toLowerCase();
                var matches = !query || text.indexOf(query) !== -1;

                $(this).toggleClass('hidden', !matches);
                if (matches) hasVisible = true;
            });

            // Show/hide section headers and separators
            $dropdown.find('.car-filter-section-header, .car-filter-separator').toggleClass('hidden', !!query);

            // Show no results message
            $noResults.toggleClass('hidden', hasVisible);
        },

        handleKeyboard: function($dropdown, e) {
            if (!$dropdown.hasClass('open')) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.toggle($dropdown);
                }
                return;
            }

            var $options = $dropdown.find('.car-filter-dropdown-option:not(.hidden)');
            var $focused = $options.filter('.focused');
            var index = $options.index($focused);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    index = Math.min(index + 1, $options.length - 1);
                    $options.removeClass('focused');
                    $options.eq(index).addClass('focused');
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    index = Math.max(index - 1, 0);
                    $options.removeClass('focused');
                    $options.eq(index).addClass('focused');
                    break;

                case 'Enter':
                    e.preventDefault();
                    if ($focused.length) {
                        this.selectOption($dropdown, $focused);
                    }
                    break;

                case 'Escape':
                    e.preventDefault();
                    this.closeAll();
                    break;
            }
        }
    };

    // Initialize dropdown controller
    EditListingDropdown.init();

    // =====================================================
    // SAVED LOCATIONS FUNCTIONALITY
    // =====================================================

    // Store saved locations data for reference
    let savedLocationsData = [];

    /**
     * Fetch and display user's saved locations from past listings
     */
    function initSavedLocations() {
        const $wrapper = $("#saved-locations-wrapper");
        const $selectorWrapper = $(".location-selector-wrapper");

        if (!$wrapper.length) {
            if (isDevelopment) console.log("[Edit Listing] Saved locations wrapper not found");
            return;
        }

        // Get current location value
        const currentLocation = $("#location").val();

        // Fetch saved locations via AJAX
        $.ajax({
            url: editListingData.ajaxurl,
            type: "POST",
            data: {
                action: "get_user_saved_locations",
                nonce: editListingData.savedLocationsNonce,
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    savedLocationsData = response.data;

                    // Build options array for the dropdown
                    const options = savedLocationsData.map(function (loc, index) {
                        return {
                            value: index.toString(),
                            label: loc.address,
                            locationData: JSON.stringify(loc),
                        };
                    });

                    // Update dropdown options
                    updateSavedLocationsDropdown(options);

                    // If there's a current location, show it in the dropdown
                    if (currentLocation) {
                        showLocationInDropdown(currentLocation);
                    }

                    if (isDevelopment) console.log("[Edit Listing] Loaded", savedLocationsData.length, "saved locations");
                } else {
                    if (isDevelopment) console.log("[Edit Listing] No saved locations found");

                    // Only hide dropdown if there's also no current location
                    if (!currentLocation) {
                        $selectorWrapper.addClass("no-saved-locations");
                    } else {
                        // There's a current location but no saved locations
                        // Show the dropdown with just the current location displayed
                        showLocationInDropdown(currentLocation);
                    }
                }
            },
            error: function (xhr, status, error) {
                if (isDevelopment) console.error("[Edit Listing] Error fetching saved locations:", error);

                // Only hide dropdown if there's also no current location
                if (!currentLocation) {
                    $selectorWrapper.addClass("no-saved-locations");
                } else {
                    // There's a current location, show it
                    showLocationInDropdown(currentLocation);
                }
            },
        });

        // Initialize clear button handler
        initClearLocationButton();
    }

    /**
     * Update the saved locations dropdown with options
     */
    function updateSavedLocationsDropdown(options) {
        const $dropdownWrapper = $("#saved-locations-wrapper");
        const $options = $dropdownWrapper.find(".car-filter-dropdown-options");
        const $select = $dropdownWrapper.find("select");
        const $button = $dropdownWrapper.find(".car-filter-dropdown-button");
        const $search = $dropdownWrapper.find(".car-filter-dropdown-search");

        const placeholder = "Recently used locations";

        // Build options HTML
        let html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="">' + placeholder + "</button>";
        let selectHtml = '<option value="">' + placeholder + "</option>";

        options.forEach(function (opt) {
            html += '<button type="button" class="car-filter-dropdown-option" role="option" data-value="' +
                opt.value + '" data-location=\'' + opt.locationData + '\'>' + opt.label + "</button>";
            selectHtml += '<option value="' + opt.value + '" data-location=\'' + opt.locationData + '\'>' + opt.label + "</option>";
        });

        html += '<div class="car-filter-no-results hidden">No matching results</div>';

        $options.html(html);
        $select.html(selectHtml);

        // Reset button text
        $button.find(".car-filter-dropdown-text").addClass("placeholder").text(placeholder);

        // Enable dropdown
        $button.prop("disabled", false);
        $select.prop("disabled", false);
        $dropdownWrapper.find(".car-filter-dropdown").removeClass("car-filter-dropdown-disabled");
        $search.prop("disabled", false);

        // Bind click handler for saved location options
        $options.off("click.savedLocation").on("click.savedLocation", ".car-filter-dropdown-option", function () {
            const $option = $(this);
            const locationDataStr = $option.data("location");
            const value = $option.data("value");

            if (locationDataStr) {
                const locationData = typeof locationDataStr === "string" ? JSON.parse(locationDataStr) : locationDataStr;
                applySavedLocation(locationData);
            } else {
                // Placeholder selected - clear location
                clearLocation();
                return;
            }

            // Update dropdown UI
            const label = $option.text().trim();

            $select.val(value);
            $options.find(".car-filter-dropdown-option").removeClass("selected");
            $option.addClass("selected");

            $button.find(".car-filter-dropdown-text")
                .removeClass("placeholder")
                .addClass("location-selected")
                .text(label);

            // Show clear button and hide dropdown arrow
            $("#clear-location-btn").show();
            $("#saved-locations-wrapper").addClass("has-location");

            // Close dropdown
            $dropdownWrapper.find(".car-filter-dropdown").removeClass("open");
            $button.attr("aria-expanded", "false");
        });
    }

    /**
     * Apply selected saved location to the form
     */
    function applySavedLocation(locationData) {
        if (!locationData) return;

        if (isDevelopment) console.log("[Edit Listing] Applying saved location:", locationData);

        // Update hidden location field
        const $locationField = $("#location");
        if ($locationField.length) {
            $locationField.val(locationData.address);
        }

        // Round coordinates to 6 decimal places
        const roundCoord = (num) => parseFloat(Number(num).toFixed(6));

        // Update the existing hidden fields
        $("#car_city").val(locationData.city || "");
        $("#car_district").val(locationData.district || locationData.city || "");
        $("#car_latitude").val(roundCoord(locationData.latitude || 0));
        $("#car_longitude").val(roundCoord(locationData.longitude || 0));
        $("#car_address").val(locationData.address || "");

        // Remove error state from location row if present
        $("#location-row").removeClass("has-error");

        // Show clear button and hide dropdown arrow
        $("#clear-location-btn").show();
        $("#saved-locations-wrapper").addClass("has-location");

        if (isDevelopment) console.log("[Edit Listing] Saved location applied successfully");
    }

    /**
     * Update dropdown to show location selected via map picker or existing location
     */
    function showLocationInDropdown(address) {
        const $dropdownWrapper = $("#saved-locations-wrapper");
        const $selectorWrapper = $(".location-selector-wrapper");
        const $button = $dropdownWrapper.find(".car-filter-dropdown-button");
        const $options = $dropdownWrapper.find(".car-filter-dropdown-options");
        const $select = $dropdownWrapper.find("select");

        // Update button text to show the selected address
        $button.find(".car-filter-dropdown-text")
            .removeClass("placeholder")
            .addClass("location-selected")
            .text(address);

        // Deselect all options (since this might be a custom location from map or existing)
        $options.find(".car-filter-dropdown-option").removeClass("selected");
        $options.find('.car-filter-dropdown-option[data-value=""]').addClass("selected");
        $select.val("");

        // Show clear button and hide dropdown arrow
        $("#clear-location-btn").show();
        $dropdownWrapper.addClass("has-location");
        
        // Remove no-saved-locations class to ensure the location bar is visible
        $selectorWrapper.removeClass("no-saved-locations");
    }

    /**
     * Initialize clear location button
     */
    function initClearLocationButton() {
        $("#clear-location-btn").off("click").on("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            clearLocation();
        });
    }

    /**
     * Clear the selected location
     */
    function clearLocation() {
        const $dropdownWrapper = $("#saved-locations-wrapper");
        const $selectorWrapper = $(".location-selector-wrapper");
        const $button = $dropdownWrapper.find(".car-filter-dropdown-button");
        const $options = $dropdownWrapper.find(".car-filter-dropdown-options");
        const $select = $dropdownWrapper.find("select");

        // Reset dropdown to placeholder
        $button.find(".car-filter-dropdown-text")
            .addClass("placeholder")
            .removeClass("location-selected")
            .text("Recently used locations");

        // Reset selection
        $options.find(".car-filter-dropdown-option").removeClass("selected");
        $options.find('.car-filter-dropdown-option[data-value=""]').addClass("selected");
        $select.val("");

        // Clear hidden location fields
        $("#location").val("");
        $("#car_city").val("");
        $("#car_district").val("");
        $("#car_latitude").val("");
        $("#car_longitude").val("");
        $("#car_address").val("");

        // Hide clear button and show dropdown arrow
        $("#clear-location-btn").hide();
        $dropdownWrapper.removeClass("has-location");
        
        // Only add no-saved-locations class if there are no saved locations
        if (!savedLocationsData || savedLocationsData.length === 0) {
            $selectorWrapper.addClass("no-saved-locations");
        }

        if (isDevelopment) console.log("[Edit Listing] Location cleared");
    }

    // Make showLocationInDropdown available globally for location-picker.js
    window.showLocationInDropdown = showLocationInDropdown;

    // Initialize saved locations on page load
    initSavedLocations();
}); 