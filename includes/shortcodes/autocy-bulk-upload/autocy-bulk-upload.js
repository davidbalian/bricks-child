(function($) {
    'use strict';

    const state = {
        phase: 1,
        uploadManager: null,
        optimizer: null,
        imageMap: new Map(), // originalFilename -> { attachmentId, url }
        uploadedCount: 0,
        uploadingCount: 0,
        errorCount: 0,
        location: null,
        listings: [],
        sliderIndex: 0,
        submitting: false,
        results: [],
    };

    // =========================================================================
    // Initialization
    // =========================================================================

    $(document).ready(function() {
        initPhase1();
        bindNavigation();
    });

    // =========================================================================
    // Phase 1: Image Upload + Location
    // =========================================================================

    function initPhase1() {
        // Init ImageOptimizer
        state.optimizer = new window.ImageOptimizer();

        // Init AsyncUploadManager and override callbacks
        state.uploadManager = new window.AsyncUploadManager();

        state.uploadManager.onUploadSuccess = function(fileKey, data) {
            state.imageMap.set(data.original_filename, {
                attachmentId: data.attachment_id,
                url: data.attachment_url,
            });
            state.uploadingCount--;
            state.uploadedCount++;
            updateUploadStats();
            checkPhase1Gate();
        };

        state.uploadManager.onUploadError = function(fileKey, error) {
            state.uploadingCount--;
            state.errorCount++;
            updateUploadStats();
            checkPhase1Gate();
        };

        // Override getFormType for bulk upload context
        state.uploadManager.getFormType = function() {
            return 'bulk_upload';
        };

        // Drag and drop
        const uploadArea = document.getElementById('bu-upload-area');
        const fileInput = document.getElementById('bu-file-input');

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('bu-dragover');
        });

        uploadArea.addEventListener('dragleave', function() {
            uploadArea.classList.remove('bu-dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('bu-dragover');
            handleFiles(Array.from(e.dataTransfer.files));
        });

        fileInput.addEventListener('change', function() {
            handleFiles(Array.from(this.files));
            this.value = '';
        });

        // Location: observe hidden fields written by location-picker.js
        const locationForm = document.getElementById('bu-location-form');
        const observer = new MutationObserver(function() {
            readLocationFromForm(locationForm);
        });
        observer.observe(locationForm, { childList: true, subtree: true, attributes: true });

        // Continue button
        $('#bu-continue-btn').on('click', function() {
            if (!this.disabled) goToPhase(2);
        });
    }

    async function handleFiles(files) {
        const imageFiles = files.filter(function(f) {
            return f.type.startsWith('image/');
        });
        if (imageFiles.length === 0) return;

        for (const file of imageFiles) {
            try {
                state.uploadingCount++;
                updateUploadStats();

                const optimized = await state.optimizer.optimizeImage(file);
                await state.uploadManager.addFileToQueue(optimized, file);
            } catch (err) {
                // Duplicate or other error - decrement uploading
                state.uploadingCount--;
                if (err.message && err.message.indexOf('Duplicate') === -1) {
                    state.errorCount++;
                }
                updateUploadStats();
            }
        }
    }

    function updateUploadStats() {
        const statsEl = document.getElementById('bu-upload-stats');
        statsEl.style.display = '';

        document.getElementById('bu-uploaded-count').textContent = state.uploadedCount;

        const uploadingWrap = document.getElementById('bu-uploading-count');
        const uploadingNum = document.getElementById('bu-uploading-num');
        if (state.uploadingCount > 0) {
            uploadingWrap.style.display = '';
            uploadingNum.textContent = state.uploadingCount;
        } else {
            uploadingWrap.style.display = 'none';
        }

        const errorWrap = document.getElementById('bu-error-count');
        const errorNum = document.getElementById('bu-error-num');
        if (state.errorCount > 0) {
            errorWrap.style.display = '';
            errorNum.textContent = state.errorCount;
        } else {
            errorWrap.style.display = 'none';
        }
    }

    function readLocationFromForm(form) {
        const city = form.querySelector('input[name="car_city"]');
        const district = form.querySelector('input[name="car_district"]');
        const lat = form.querySelector('input[name="car_latitude"]');
        const lng = form.querySelector('input[name="car_longitude"]');
        const addr = form.querySelector('input[name="car_address"]');

        if (lat && lng && lat.value && lng.value) {
            state.location = {
                car_city: city ? city.value : '',
                car_district: district ? district.value : '',
                car_latitude: lat.value,
                car_longitude: lng.value,
                car_address: addr ? addr.value : '',
            };

            // Update display
            var locationInput = document.getElementById('location');
            if (locationInput && state.location.car_address) {
                locationInput.value = state.location.car_address;
            }

            checkPhase1Gate();
        }
    }

    function checkPhase1Gate() {
        const ready = state.uploadedCount > 0 && state.uploadingCount === 0 && state.location !== null;
        document.getElementById('bu-continue-btn').disabled = !ready;
    }

    // =========================================================================
    // Phase 2: CSV Parsing & Slider
    // =========================================================================

    function initPhase2() {
        $('#bu-csv-input').off('change').on('change', function() {
            const file = this.files[0];
            if (!file) return;
            document.getElementById('bu-csv-filename').textContent = file.name;
            parseCSV(file);
        });
    }

    function parseCSV(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const rows = parseCSVText(text);
            if (rows.length < 2) {
                alert('CSV file appears empty or has no data rows.');
                return;
            }

            const headers = rows[0].map(function(h) { return h.trim().toLowerCase(); });
            state.listings = [];

            for (let i = 1; i < rows.length; i++) {
                if (rows[i].length === 0 || (rows[i].length === 1 && rows[i][0].trim() === '')) continue;

                const listing = {};
                for (let j = 0; j < headers.length; j++) {
                    listing[headers[j]] = (rows[i][j] || '').trim();
                }

                // Match images
                listing._images = matchImages(listing.image_folder || '');
                // Validate
                listing._errors = validateListing(listing);

                state.listings.push(listing);
            }

            state.sliderIndex = 0;
            updateSummary();
            renderSliderCard();
            document.getElementById('bu-summary').style.display = '';
            document.getElementById('bu-slider').style.display = '';
            checkPhase2Gate();
        };
        reader.readAsText(file);
    }

    /**
     * Parse CSV text handling quoted fields with commas and newlines
     */
    function parseCSVText(text) {
        const rows = [];
        let current = [];
        let field = '';
        let inQuotes = false;

        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            const next = text[i + 1];

            if (inQuotes) {
                if (ch === '"' && next === '"') {
                    field += '"';
                    i++; // skip escaped quote
                } else if (ch === '"') {
                    inQuotes = false;
                } else {
                    field += ch;
                }
            } else {
                if (ch === '"') {
                    inQuotes = true;
                } else if (ch === ',') {
                    current.push(field);
                    field = '';
                } else if (ch === '\r' && next === '\n') {
                    current.push(field);
                    field = '';
                    rows.push(current);
                    current = [];
                    i++; // skip \n
                } else if (ch === '\n') {
                    current.push(field);
                    field = '';
                    rows.push(current);
                    current = [];
                } else {
                    field += ch;
                }
            }
        }

        // Last field/row
        if (field || current.length > 0) {
            current.push(field);
            rows.push(current);
        }

        return rows;
    }

    function matchImages(imageFolder) {
        if (!imageFolder) return [];

        const prefix = imageFolder + '_';
        const matched = [];

        state.imageMap.forEach(function(data, filename) {
            if (filename.indexOf(prefix) === 0) {
                matched.push({
                    filename: filename,
                    attachmentId: data.attachmentId,
                    url: data.url,
                });
            }
        });

        // Sort by filename for consistent order
        matched.sort(function(a, b) {
            return a.filename.localeCompare(b.filename);
        });

        return matched;
    }

    function validateListing(listing) {
        const errors = [];
        if (!listing.make) errors.push('Missing make');
        if (!listing.model) errors.push('Missing model');
        if (!listing.year) errors.push('Missing year');
        if (!listing.price) errors.push('Missing price');
        if (listing._images.length < 2) errors.push('Less than 2 matched images (' + listing._images.length + ' found)');
        return errors;
    }

    function updateSummary() {
        const total = state.listings.length;
        let valid = 0;
        let withErrors = 0;

        state.listings.forEach(function(l) {
            if (l._errors.length === 0) valid++;
            else withErrors++;
        });

        document.getElementById('bu-total-listings').textContent = total;
        document.getElementById('bu-valid-count').textContent = valid;
        document.getElementById('bu-error-listing-count').textContent = withErrors;
    }

    function renderSliderCard() {
        const listing = state.listings[state.sliderIndex];
        if (!listing) return;

        const total = state.listings.length;
        document.getElementById('bu-slider-counter').textContent = 'Listing ' + (state.sliderIndex + 1) + ' of ' + total;
        document.getElementById('bu-prev-btn').disabled = state.sliderIndex === 0;
        document.getElementById('bu-next-btn').disabled = state.sliderIndex >= total - 1;

        const hasErrors = listing._errors.length > 0;
        const card = document.getElementById('bu-listing-card');

        let html = '';

        if (hasErrors) {
            html += '<div class="bu-card-banner bu-card-error">' + listing._errors.map(escapeHtml).join(' | ') + '</div>';
        }

        const title = (listing.year || '') + ' ' + (listing.make || '') + ' ' + (listing.model || '');
        const priceFormatted = listing.price ? '€' + Number(listing.price).toLocaleString() : 'N/A';

        html += '<div class="bu-card-header">';
        html += '<span class="bu-card-title">' + escapeHtml(title.trim()) + '</span>';
        html += '<span class="bu-card-price">' + priceFormatted + '</span>';
        html += '</div>';

        // Images
        if (listing._images.length > 0) {
            html += '<div class="bu-card-images">';
            listing._images.forEach(function(img) {
                html += '<img src="' + escapeHtml(img.url) + '" alt="' + escapeHtml(img.filename) + '" class="bu-card-thumb">';
            });
            html += '</div>';
        } else {
            html += '<div class="bu-card-no-images">No matched images</div>';
        }

        // Detail grid
        const fields = [
            ['Make', listing.make],
            ['Model', listing.model],
            ['Year', listing.year],
            ['Price', listing.price],
            ['Mileage', listing.mileage],
            ['Fuel Type', listing.fuel_type],
            ['Transmission', listing.transmission],
            ['Engine', listing.engine_capacity ? listing.engine_capacity + 'L' : ''],
            ['Body Type', listing.body_type],
            ['Drive Type', listing.drive_type],
            ['Ext. Color', listing.exterior_color],
            ['Int. Color', listing.interior_color],
            ['Doors', listing.number_of_doors],
            ['Seats', listing.number_of_seats],
            ['Availability', listing.availability],
            ['Image Folder', listing.image_folder],
        ];

        html += '<div class="bu-card-details">';
        fields.forEach(function(pair) {
            if (pair[1]) {
                html += '<div class="bu-detail-item"><span class="bu-detail-label">' + escapeHtml(pair[0]) + '</span><span class="bu-detail-value">' + escapeHtml(pair[1]) + '</span></div>';
            }
        });
        html += '</div>';

        // Extras
        if (listing.extras) {
            html += '<div class="bu-card-extras"><strong>Extras:</strong> ' + escapeHtml(listing.extras) + '</div>';
        }

        // Description
        if (listing.description) {
            html += '<div class="bu-card-description"><strong>Description:</strong> ' + escapeHtml(listing.description) + '</div>';
        }

        // Location
        if (state.location) {
            html += '<div class="bu-card-location"><strong>Location:</strong> ' + escapeHtml(state.location.car_address || state.location.car_city) + '</div>';
        }

        card.innerHTML = html;
        card.className = 'bu-listing-card' + (hasErrors ? ' bu-card-has-errors' : '');
    }

    function checkPhase2Gate() {
        const validCount = state.listings.filter(function(l) { return l._errors.length === 0; }).length;
        document.getElementById('bu-submit-btn').disabled = validCount === 0;
    }

    // =========================================================================
    // Navigation
    // =========================================================================

    function bindNavigation() {
        // Slider arrows
        $('#bu-prev-btn').on('click', function() {
            if (state.sliderIndex > 0) {
                state.sliderIndex--;
                renderSliderCard();
            }
        });

        $('#bu-next-btn').on('click', function() {
            if (state.sliderIndex < state.listings.length - 1) {
                state.sliderIndex++;
                renderSliderCard();
            }
        });

        // Back button
        $('#bu-back-btn').on('click', function() {
            goToPhase(1);
        });

        // Submit button
        $('#bu-submit-btn').on('click', function() {
            if (!this.disabled) startSubmission();
        });
    }

    function goToPhase(phase) {
        state.phase = phase;
        document.querySelectorAll('.bu-phase').forEach(function(el) {
            el.classList.toggle('active', parseInt(el.dataset.phase) === phase);
        });

        if (phase === 2) initPhase2();
    }

    // =========================================================================
    // Phase 3: Submission
    // =========================================================================

    async function startSubmission() {
        const validListings = state.listings.filter(function(l) { return l._errors.length === 0; });
        if (validListings.length === 0) return;

        state.submitting = true;
        state.results = [];
        goToPhase(3);

        // Beforeunload warning
        window.addEventListener('beforeunload', beforeUnloadHandler);

        const total = validListings.length;
        const progressFill = document.getElementById('bu-progress-fill');
        const progressText = document.getElementById('bu-progress-text');
        const resultsEl = document.getElementById('bu-results');
        resultsEl.innerHTML = '';
        let successCount = 0;
        let failCount = 0;

        for (let i = 0; i < total; i++) {
            const listing = validListings[i];
            const title = (listing.year || '') + ' ' + (listing.make || '') + ' ' + (listing.model || '');

            progressText.textContent = (i + 1) + ' / ' + total;
            progressFill.style.width = Math.round(((i) / total) * 100) + '%';

            try {
                const result = await createListing(listing);
                successCount++;
                resultsEl.innerHTML += '<div class="bu-result bu-result-success">&#10003; ' + escapeHtml(title.trim()) + ' (ID: ' + result.post_id + ')</div>';
                state.results.push({ success: true, title: title, post_id: result.post_id });
            } catch (err) {
                failCount++;
                resultsEl.innerHTML += '<div class="bu-result bu-result-error">&#10007; ' + escapeHtml(title.trim()) + ' — ' + escapeHtml(err.message || 'Unknown error') + '</div>';
                state.results.push({ success: false, title: title, error: err.message });
            }
        }

        // Done
        progressFill.style.width = '100%';
        progressText.textContent = total + ' / ' + total;
        state.submitting = false;
        window.removeEventListener('beforeunload', beforeUnloadHandler);

        // Mark session completed to prevent cleanup on page leave
        state.uploadManager.markSessionCompleted();

        // Show done summary
        var doneActions = document.getElementById('bu-done-actions');
        doneActions.style.display = '';
        document.getElementById('bu-done-summary').textContent = successCount + ' listings created successfully' + (failCount > 0 ? ', ' + failCount + ' failed' : '') + '.';
    }

    function createListing(listing) {
        return new Promise(function(resolve, reject) {
            const data = new FormData();
            data.append('action', 'autocy_bulk_upload_create_listing');
            data.append('nonce', autocyBulkUploadConfig.nonce);

            // Car fields
            const fields = ['make', 'model', 'year', 'price', 'mileage', 'fuel_type', 'transmission',
                'engine_capacity', 'body_type', 'drive_type', 'exterior_color', 'interior_color',
                'number_of_doors', 'number_of_seats', 'description', 'extras', 'availability'];

            fields.forEach(function(f) {
                if (listing[f]) data.append(f, listing[f]);
            });

            // Location
            if (state.location) {
                data.append('car_city', state.location.car_city);
                data.append('car_district', state.location.car_district);
                data.append('car_latitude', state.location.car_latitude);
                data.append('car_longitude', state.location.car_longitude);
                data.append('car_address', state.location.car_address);
            }

            // Attachment IDs
            listing._images.forEach(function(img) {
                data.append('attachment_ids[]', img.attachmentId);
            });

            $.ajax({
                url: autocyBulkUploadConfig.ajaxUrl,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data && response.data.message ? response.data.message : 'Server error'));
                    }
                },
                error: function(xhr, status, err) {
                    reject(new Error(err || 'Network error'));
                }
            });
        });
    }

    function beforeUnloadHandler(e) {
        if (state.submitting) {
            e.preventDefault();
            e.returnValue = 'Listing creation is in progress. Are you sure you want to leave?';
            return e.returnValue;
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
