jQuery(document).ready(function ($) {
  console.log("[Add Listing] jQuery ready");

  // Initialize async upload manager if available
  let asyncUploadManager = null;
  if (typeof AsyncUploadManager !== "undefined") {
    asyncUploadManager = new AsyncUploadManager();

    // Set session ID in hidden form field
    $("#async_session_id").val(asyncUploadManager.session.id);

    // Override callbacks to update UI
    asyncUploadManager.updateUploadProgress = function (fileKey, progress) {
      updateAsyncUploadProgress(fileKey, progress);
    };

    asyncUploadManager.onUploadSuccess = function (fileKey, data) {
      onAsyncUploadSuccess(fileKey, data);
    };

    asyncUploadManager.onUploadError = function (fileKey, error) {
      onAsyncUploadError(fileKey, error);
    };

    asyncUploadManager.onImageRemoved = function (fileKey) {
      onAsyncImageRemoved(fileKey);
    };

    console.log(
      "[Add Listing] Async upload manager initialized with session:",
      asyncUploadManager.session.id
    );
  }

  // Handle make selection change
  $("#make").on("change", function () {
    const selectedMake = $(this).val();
    console.log("[Add Listing] Selected make:", selectedMake);

    const modelSelect = $("#model");

    // Clear existing options
    modelSelect.empty().append('<option value="">Select Model</option>');

    if (selectedMake) {
      // Show loading state
      modelSelect.append(
        '<option value="" disabled>Loading models...</option>'
      );

      // Fetch models via AJAX
      $.ajax({
        url: addListingData.ajaxurl,
        type: "POST",
        data: {
          action: "get_models_for_make",
          make: selectedMake,
          nonce: addListingData.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Remove loading option
            modelSelect.find("option[disabled]").remove();

            // Add model options
            response.data.forEach((model) => {
              modelSelect.append(`<option value="${model}">${model}</option>`);
            });

            console.log(
              "[Add Listing] Loaded",
              response.data.length,
              "models for",
              selectedMake
            );
          } else {
            console.error("[Add Listing] Error loading models:", response.data);
            modelSelect.find("option[disabled]").text("Error loading models");
          }
        },
        error: function (xhr, status, error) {
          console.error("[Add Listing] AJAX error:", error);
          modelSelect.find("option[disabled]").text("Error loading models");
        },
      });
    } else {
      console.log("[Add Listing] No make selected");
    }
  });

  // variant handling removed

  // Handle fuel type change to lock/unlock engine capacity for electric vehicles
  function handleElectricFuelType() {
    const selectedFuelType = $("#fuel_type").val();
    const engineCapacitySelect = $("#engine_capacity");

    if (selectedFuelType === "Electric") {
      // Add 0.0 option if it doesn't exist and select it
      if (engineCapacitySelect.find('option[value="0.0"]').length === 0) {
        // Insert 0.0 option after the placeholder option
        engineCapacitySelect
          .find('option[value=""]')
          .after('<option value="0.0">0.0</option>');
      }
      engineCapacitySelect.val("0.0");
      engineCapacitySelect.prop("disabled", true);
      engineCapacitySelect.addClass("electric-locked");
      console.log(
        "[Add Listing] Engine capacity locked to 0.0 for electric vehicle"
      );
    } else {
      // Re-enable the engine capacity field
      engineCapacitySelect.prop("disabled", false);
      engineCapacitySelect.removeClass("electric-locked");

      // Remove the 0.0 option and reset selection if it was 0.0
      if (engineCapacitySelect.val() === "0.0") {
        engineCapacitySelect.val("");
      }
      engineCapacitySelect.find('option[value="0.0"]').remove();
      console.log(
        "[Add Listing] Engine capacity unlocked for non-electric vehicle"
      );
    }
  }

  // Initialize electric fuel type handling on page load
  handleElectricFuelType();

  // Handle fuel type changes
  $("#fuel_type").on("change", handleElectricFuelType);

  const fileInput = $("#car_images");
  const fileUploadArea = $("#file-upload-area");
  const imagePreview = $("#image-preview");
  let accumulatedFilesList = []; // Source of truth for selected files

  // Add mileage formatting
  const mileageInput = $("#mileage");
  const priceInput = $("#price");

  // Format number with commas
  function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // Remove commas and convert to number
  function unformatNumber(formattedNumber) {
    return parseInt(formattedNumber.replace(/[^0-9]/g, "")) || 0;
  }

  // Format mileage with commas
  mileageInput.on("input", function (e) {
    let value = this.value.replace(/[km,]/g, "");
    value = value.replace(/[^\d]/g, "");
    if (value) {
      const formattedValue = formatNumber(value);
      this.value = formattedValue;
      $(this).data("raw-value", value);
    } else {
      this.value = "";
      $(this).data("raw-value", 0);
    }
  });

  // Handle price input event for real-time formatting
  priceInput.on("input", function (e) {
    // Get the raw value without commas and euro sign
    let value = this.value.replace(/[€,]/g, "");

    // Only allow numbers
    value = value.replace(/[^\d]/g, "");

    // Format with commas and euro sign
    if (value) {
      const formattedValue = "€" + formatNumber(value);

      // Update the display value
      this.value = formattedValue;

      // Store the raw value in a data attribute
      $(this).data("raw-value", unformatNumber(value));
    } else {
      this.value = "";
      $(this).data("raw-value", 0);
    }
  });

  // Format HP input with thousand separators
  $("#hp").on("input", function () {
    // Remove any non-digit characters
    let value = $(this).val().replace(/[^\d]/g, "");

    // Add thousand separators
    if (value.length > 0) {
      value = parseInt(value).toLocaleString();
    }

    // Update the input value
    $(this).val(value);
    // Store the raw value
    $(this).data("raw-value", value.replace(/[^\d]/g, ""));
  });

  // Handle form submission
  $("#add-car-listing-form").on("submit", function (e) {
    // Re-enable engine capacity field temporarily for form submission if it's locked for electric
    const engineCapacitySelect = $("#engine_capacity");
    const wasElectricLocked = engineCapacitySelect.hasClass("electric-locked");
    if (wasElectricLocked) {
      engineCapacitySelect.prop("disabled", false);
      console.log(
        "[Add Listing] Temporarily re-enabled engine capacity field for form submission"
      );
    }

    // Validate image count - either async uploaded or traditional
    let totalImages = 0;

    if (asyncUploadManager) {
      // Count async uploaded images
      totalImages = asyncUploadManager.getUploadedAttachmentIds().length;
    } else {
      // Count traditional uploaded files
      totalImages = accumulatedFilesList.length;
    }

    if (totalImages < 5) {
      e.preventDefault();
      alert("Please upload at least 5 images before submitting the form");
      return;
    }
    if (totalImages > 25) {
      e.preventDefault();
      alert("Maximum 25 images allowed");
      return;
    }

    // If using async uploads, mark session as completed
    if (asyncUploadManager) {
      // Check if any async uploads are still in progress
      const pendingUploads = accumulatedFilesList.filter(
        (file) => file.asyncUploadStatus === "uploading"
      ).length;

      if (pendingUploads > 0) {
        e.preventDefault();
        alert(
          `Please wait for ${pendingUploads} image(s) to finish uploading before submitting.`
        );
        return false;
      }

      asyncUploadManager.markSessionCompleted();
      console.log(
        "[Add Listing] Session marked as completed on form submission"
      );

      // Clear file input to prevent duplicate uploads when using async system
      const fileInput = $("#car_images")[0];
      if (fileInput && fileInput.files && fileInput.files.length > 0) {
        fileInput.value = "";
        console.log("[Add Listing] Cleared file input for async uploads");
      }
    } else {
      // For traditional uploads, ensure fileInput has correct files
      updateActualFileInput();
    }

    // Get the raw values from data attributes
    const rawMileage =
      mileageInput.data("raw-value") || unformatNumber(mileageInput.val());
    const rawPrice =
      priceInput.data("raw-value") ||
      unformatNumber(priceInput.val().replace("€", ""));
    const rawHp = $("#hp").data("raw-value") || unformatNumber($("#hp").val());

    // Create hidden inputs with the raw values
    $("<input>")
      .attr({
        type: "hidden",
        name: "mileage",
        value: rawMileage,
      })
      .appendTo(this);

    $("<input>")
      .attr({
        type: "hidden",
        name: "price",
        value: rawPrice,
      })
      .appendTo(this);

    $("<input>")
      .attr({
        type: "hidden",
        name: "hp",
        value: rawHp,
      })
      .appendTo(this);

    // Remove the original inputs from submission
    mileageInput.prop("disabled", true);
    priceInput.prop("disabled", true);
    $("#hp").prop("disabled", true);

    console.log(
      "[Add Listing] Form validation passed, submitting with",
      totalImages,
      "images"
    );
    return true;
  });

  console.log("[Add Listing] Elements found:", {
    fileInput: fileInput.length,
    fileUploadArea: fileUploadArea.length,
    imagePreview: imagePreview.length,
  });

  // Handle click on upload area
  fileUploadArea.on("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    console.log("[Add Listing] Upload area clicked");
    fileInput.trigger("click");
  });

  // Handle when files are selected through the file dialog
  fileInput.on("change", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const newlySelectedThroughDialog = Array.from(this.files);
    console.log(
      "[Add Listing] Files selected through file dialog:",
      newlySelectedThroughDialog.length
    );
    if (newlySelectedThroughDialog.length > 0) {
      processNewFiles(newlySelectedThroughDialog);
    }
    // Clear the file input's displayed value to allow re-selecting the same file(s)
    // and ensure 'change' event fires consistently.
    $(this).val("");
  });

  // Handle drag and drop
  fileUploadArea.on("dragover", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass("dragover");
  });

  fileUploadArea.on("dragleave", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass("dragover");
  });

  fileUploadArea.on("drop", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass("dragover");
    const droppedFiles = Array.from(e.originalEvent.dataTransfer.files);
    console.log("[Add Listing] Files dropped:", droppedFiles.length);
    processNewFiles(droppedFiles);
  });

  function processNewFiles(candidateFiles) {
    console.log(
      "[Add Listing] Processing",
      candidateFiles.length,
      "new candidate files."
    );
    const maxFiles = 25;
    const maxFileSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ["image/jpeg", "image/jfif", "image/pjpeg", "image/jpg", "image/x-jfif", "image/pipeg", "image/png", "image/gif", "image/webp"];
        const allowedExtensions = ["jpg", "jpeg", "jfif", "jpe", "png", "gif", "webp"];

    // Show processing indicator
    showImageProcessingIndicator(true);

    // Initialize the image optimizer (minimal processing - server handles WebP)
    const optimizer = new ImageOptimizer({
      maxWidth: 1920,
      maxHeight: 1080,
      quality: 0.8,
      maxFileSize: 5120, // 5MB in KB - only process very large files
      allowedTypes: allowedTypes,
    });

    // Process files asynchronously with optimization
    processFilesWithOptimization(
      candidateFiles,
      optimizer,
      maxFiles,
      allowedTypes,
      maxFileSize
    );
  }

  async function processFilesWithOptimization(
    candidateFiles,
    optimizer,
    maxFiles,
    allowedTypes,
    maxFileSize
  ) {
    let filesAddedThisBatchCount = 0;
    let totalSavings = 0;
    let totalOriginalSize = 0;
    let optimizationErrors = 0;

    try {
      for (const file of candidateFiles) {
        // Check if adding this file would exceed the maximum
        if (accumulatedFilesList.length >= maxFiles) {
          alert(
            "Maximum " + maxFiles + " files allowed. Some files were not added."
          );
          break;
        }

        // FIXED: Check for duplicates using ORIGINAL file properties (before optimization)
        const isDuplicate = accumulatedFilesList.some((existingFile) => {
          // Compare against original properties if they exist, otherwise current properties
          const existingOriginalName =
            existingFile.originalName || existingFile.name;
          const existingOriginalSize =
            existingFile.originalSize || existingFile.size;
          const existingOriginalType =
            existingFile.originalType || existingFile.type;

          return (
            existingOriginalName === file.name &&
            existingOriginalSize === file.size &&
            existingOriginalType === file.type
          );
        });

        if (isDuplicate) {
          console.log(
            "[Add Listing] Skipping duplicate file (already selected):",
            file.name
          );
          continue;
        }

        if (!allowedTypes.includes(file.type)) {
          alert(
            'File type "' +
              file.type +
              '" not allowed for "' +
              file.name +
              '". Only JPG, PNG, GIF, and WebP are permitted.'
          );
          continue;
        }

        if (file.size > maxFileSize) {
          alert(
            'File "' +
              file.name +
              '" is too large (' +
              (file.size / (1024 * 1024)).toFixed(2) +
              "MB). Maximum allowed is " +
              maxFileSize / (1024 * 1024) +
              "MB."
          );
          continue;
        }

        try {
          // Update processing status
          updateProcessingStatus("Optimizing " + file.name + "...");

          const originalSize = file.size;
          totalOriginalSize += originalSize;

          // Optimize the image
          const optimizedFile = await optimizer.optimizeImage(file);
          const optimizedSize = optimizedFile.size;
          totalSavings += originalSize - optimizedSize;

          // FIXED: Store original file properties for future duplicate detection
          optimizedFile.originalName = file.name;
          optimizedFile.originalSize = file.size;
          optimizedFile.originalType = file.type;

          console.log(
            "[Add Listing] File optimized:",
            file.name,
            "Original:",
            (originalSize / 1024).toFixed(2) + "KB",
            "Optimized:",
            (optimizedSize / 1024).toFixed(2) + "KB"
          );

          // Start async upload immediately after optimization
          if (asyncUploadManager) {
            try {
              updateProcessingStatus("Uploading " + file.name + "...");
              const fileKey = await asyncUploadManager.addFileToQueue(
                optimizedFile,
                file
              );

              // Store the file key for tracking
              optimizedFile.asyncFileKey = fileKey;
              optimizedFile.asyncUploadStatus = "uploading";

              console.log(
                "[Add Listing] Started async upload for:",
                file.name,
                "Key:",
                fileKey
              );
            } catch (uploadError) {
              console.error(
                "[Add Listing] Async upload start failed:",
                file.name,
                uploadError
              );
              // Continue with regular flow
            }
          }

          accumulatedFilesList.push(optimizedFile);
          createAndDisplayPreview(optimizedFile, originalSize, optimizedSize);
          filesAddedThisBatchCount++;
        } catch (error) {
          console.error(
            "[Add Listing] Error optimizing image:",
            file.name,
            error
          );
          optimizationErrors++;

          // Fall back to original file if optimization fails
          console.log(
            "[Add Listing] Using original file as fallback for:",
            file.name
          );

          // Even for fallback, store original properties for consistency
          file.originalName = file.name;
          file.originalSize = file.size;
          file.originalType = file.type;

          // Start async upload for fallback file too
          if (asyncUploadManager) {
            try {
              const fileKey = await asyncUploadManager.addFileToQueue(file);
              file.asyncFileKey = fileKey;
              file.asyncUploadStatus = "uploading";
            } catch (uploadError) {
              console.error(
                "[Add Listing] Async upload start failed for fallback:",
                file.name,
                uploadError
              );
            }
          }

          accumulatedFilesList.push(file);
          createAndDisplayPreview(file);
          filesAddedThisBatchCount++;
        }
      }

      // Show optimization summary
      if (filesAddedThisBatchCount > 0) {
        updateActualFileInput(); // Refresh the actual file input

        if (totalSavings > 0) {
          const compressionPercent = (
            (totalSavings / totalOriginalSize) *
            100
          ).toFixed(1);
          showOptimizationSummary(
            filesAddedThisBatchCount,
            totalSavings,
            compressionPercent,
            optimizationErrors
          );
        }
      }

      console.log(
        "[Add Listing] Processed batch. Accumulated files count:",
        accumulatedFilesList.length
      );
    } catch (error) {
      console.error("[Add Listing] Error in batch processing:", error);
    } finally {
      // Hide processing indicator
      showImageProcessingIndicator(false);
    }
  }

  function showImageProcessingIndicator(show) {
    if (show) {
      if (!$("#image-processing-indicator").length) {
        const indicator = $(`
                    <div id="image-processing-indicator" class="image-processing-indicator">
                        <div class="processing-spinner"></div>
                        <div class="processing-text">Optimizing images...</div>
                        <div class="processing-status"></div>
                    </div>
                `);
        imagePreview.before(indicator);
      }
    } else {
      $("#image-processing-indicator").remove();
    }
  }

  function updateProcessingStatus(message) {
    $("#image-processing-indicator .processing-status").text(message);
  }

  function showOptimizationSummary(
    filesCount,
    totalSavings,
    compressionPercent,
    optimizationErrors
  ) {
    if (totalSavings > 0) {
      const summaryMessage = `✅ Optimized ${filesCount} image${
        filesCount > 1 ? "s" : ""
      }: Saved ${totalSavings.toFixed(
        1
      )} KB (${compressionPercent}% reduction)`;

      const summaryEl = $(
        `<div class="optimization-summary">${summaryMessage}</div>`
      );
      imagePreview.before(summaryEl);

      // Remove summary after 5 seconds
      setTimeout(() => {
        summaryEl.fadeOut(() => summaryEl.remove());
      }, 5000);
    }
  }

  function showErrorSummary(optimizationErrors, filesAddedThisBatchCount) {
    const errorMessage = `❌ ${optimizationErrors} image${
      optimizationErrors > 1 ? "s" : ""
    } were not optimized. Please check the images and try again.`;

    const errorEl = $(`<div class="error-summary">${errorMessage}</div>`);
    imagePreview.before(errorEl);

    // Remove summary after 5 seconds
    setTimeout(() => {
      errorEl.fadeOut(() => errorEl.remove());
    }, 5000);
  }

  function createAndDisplayPreview(file) {
    console.log("[Add Listing] Creating preview for:", file.name);
    const reader = new FileReader();
    reader.onload = function (e) {
      const previewItem = $("<div>").addClass("image-preview-item");

      // Add async file key if available
      if (file.asyncFileKey) {
        previewItem
          .addClass("image-preview")
          .attr("data-async-key", file.asyncFileKey);
      }

      const img = $("<img>").attr({ src: e.target.result, alt: file.name });

      const removeBtn = $("<div>")
        .addClass("remove-image")
        .html('<i class="fas fa-times"></i>')
        .on("click", function () {
          console.log("[Add Listing] Remove button clicked for:", file.name);

          // Remove from async system if applicable
          if (file.asyncFileKey && asyncUploadManager) {
            asyncUploadManager.removeImage(file.asyncFileKey).catch((error) => {
              console.error(
                "[Add Listing] Failed to remove from async system:",
                error
              );
            });
          }

          removeFileFromSelection(file.name);
          previewItem.remove();
        });

      previewItem.append(img).append(removeBtn);

      // Add initial upload status if async upload is starting
      if (file.asyncFileKey) {
        previewItem.append(
          '<div class="upload-status upload-pending">⏳ Uploading...</div>'
        );
      }

      imagePreview.append(previewItem);
      console.log("[Add Listing] Preview added to DOM for:", file.name);
    };
    reader.onerror = function () {
      console.error("[Add Listing] Error reading file for preview:", file.name);
    };
    reader.readAsDataURL(file);
  }

  function createAndDisplayPreviewWithStats(
    optimizedFile,
    originalFile,
    stats
  ) {
    console.log(
      "[Add Listing] Creating preview with stats for:",
      optimizedFile.name
    );
    const reader = new FileReader();
    reader.onload = function (e) {
      const previewItem = $("<div>").addClass("image-preview-item");
      const img = $("<img>").attr({
        src: e.target.result,
        alt: optimizedFile.name,
      });

      // Add compression stats tooltip
      const statsTooltip = $(`
                <div class="compression-stats" title="Original: ${stats.originalSize}KB | Optimized: ${stats.optimizedSize}KB | Saved: ${stats.savings}KB (${stats.compressionRatio}%)">
                    <span class="stats-icon">📊</span>
                    <span class="stats-text">-${stats.compressionRatio}%</span>
                </div>
            `);

      const removeBtn = $("<div>")
        .addClass("remove-image")
        .html('<i class="fas fa-times"></i>')
        .on("click", function () {
          console.log(
            "[Add Listing] Remove button clicked for:",
            optimizedFile.name
          );
          removeFileFromSelection(optimizedFile.name);
          previewItem.remove();
        });

      previewItem.append(img).append(statsTooltip).append(removeBtn);
      imagePreview.append(previewItem);
      console.log(
        "[Add Listing] Preview with stats added to DOM for:",
        optimizedFile.name
      );
    };
    reader.onerror = function () {
      console.error(
        "[Add Listing] Error reading file for preview:",
        optimizedFile.name
      );
    };
    reader.readAsDataURL(optimizedFile);
  }

  function removeFileFromSelection(fileNameToRemove) {
    console.log(
      "[Add Listing] Attempting to remove file from selection:",
      fileNameToRemove
    );
    accumulatedFilesList = accumulatedFilesList.filter(
      (file) => file.name !== fileNameToRemove
    );
    updateActualFileInput(); // Refresh the actual file input
    console.log(
      "[Add Listing] File removed. Accumulated files count:",
      accumulatedFilesList.length
    );
  }

  function updateActualFileInput() {
    const dataTransfer = new DataTransfer();
    accumulatedFilesList.forEach((file) => {
      try {
        dataTransfer.items.add(file);
      } catch (error) {
        console.error(
          "[Add Listing] Error adding file to DataTransfer:",
          file.name,
          error
        );
      }
    });
    try {
      fileInput[0].files = dataTransfer.files;
    } catch (error) {
      console.error(
        "[Add Listing] Error setting files on input element:",
        error
      );
    }
    console.log(
      "[Add Listing] Actual file input updated. Count:",
      fileInput[0].files.length
    );
  }

  // Initialize and test image optimizer
  function initializeImageOptimizer() {
    if (typeof ImageOptimizer !== "undefined") {
      const testOptimizer = new ImageOptimizer();
      console.log(
        "[Add Listing] ✅ Image optimization ready! Browser support:",
        testOptimizer.isSupported
      );
      console.log("[Add Listing] Optimization settings:", {
        maxWidth: testOptimizer.maxWidth,
        maxHeight: testOptimizer.maxHeight,
        quality: testOptimizer.quality,
        maxFileSize: testOptimizer.maxFileSize + "KB",
      });
    } else {
      console.error(
        "[Add Listing] ❌ ImageOptimizer class not found! Image optimization will not work."
      );
    }
  }

  // Test the optimizer on page load
  initializeImageOptimizer();

  /**
   * Async upload callback functions
   */
  function updateAsyncUploadProgress(fileKey, progress) {
    const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
    if ($preview.length) {
      let $progressBar = $preview.find(".upload-progress");
      if (!$progressBar.length) {
        $progressBar = $(
          '<div class="upload-progress"><div class="upload-progress-bar"></div><span class="upload-progress-text">0%</span></div>'
        );
        $preview.append($progressBar);
      }

      // Update CSS custom property for progress bar
      $progressBar
        .find(".upload-progress-bar")
        .css("--progress", progress + "%");
      $progressBar.find(".upload-progress-text").text(progress + "%");

      if (progress >= 100) {
        setTimeout(() => {
          $progressBar.fadeOut(() => $progressBar.remove());
        }, 1000);
      }
    }
  }

  function onAsyncUploadSuccess(fileKey, data) {
    const fileIndex = accumulatedFilesList.findIndex(
      (file) => file.asyncFileKey === fileKey
    );
    if (fileIndex !== -1) {
      accumulatedFilesList[fileIndex].asyncUploadStatus = "completed";
      accumulatedFilesList[fileIndex].attachmentId = data.attachment_id;

      const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
      $preview.find(".upload-status").remove();
      $preview.append(
        '<div class="upload-status upload-success">✓ Uploaded</div>'
      );

      setTimeout(() => {
        $preview.find(".upload-success").fadeOut(() => {
          $preview.find(".upload-success").remove();
        });
      }, 3000);

      console.log(
        "[Add Listing] Async upload completed for:",
        data.original_filename
      );
    }
  }

  function onAsyncUploadError(fileKey, error) {
    const fileIndex = accumulatedFilesList.findIndex(
      (file) => file.asyncFileKey === fileKey
    );
    if (fileIndex !== -1) {
      accumulatedFilesList[fileIndex].asyncUploadStatus = "failed";
      accumulatedFilesList[fileIndex].asyncUploadError = error.message;

      const $preview = $(`.image-preview[data-async-key="${fileKey}"]`);
      $preview.find(".upload-status").remove();
      $preview.append(
        '<div class="upload-status upload-error">✗ Upload failed</div>'
      );

      // Show fallback message below upload area
      showAsyncUploadFallbackMessage();

      console.error(
        "[Add Listing] Async upload failed for file key:",
        fileKey,
        error
      );
    }
  }

  function showAsyncUploadFallbackMessage() {
    // Only show if not already shown
    if ($(".async-upload-fallback-message").length === 0) {
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
    const fileIndex = accumulatedFilesList.findIndex(
      (file) => file.asyncFileKey === fileKey
    );
    if (fileIndex !== -1) {
      accumulatedFilesList.splice(fileIndex, 1);
      updateActualFileInput();
    }

    // Remove preview element
    $(`.image-preview[data-async-key="${fileKey}"]`).fadeOut(() => {
      $(`.image-preview[data-async-key="${fileKey}"]`).remove();
    });

    console.log("[Add Listing] Image removed from async system:", fileKey);
  }
});
