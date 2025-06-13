// Car Specification Filters
// This file contains the JavaScript logic for updating car specification filter counts.

(function($) {
    if (typeof window.AutoAgoraSpecFilters === 'undefined') {
        window.AutoAgoraSpecFilters = {};
    }

    /**
     * Updates the display counts for various specification filters.
     * @param {object} filterCounts - An object where keys are filter_key (e.g., 'fuel_type')
     *                                and values are objects of option_value: count.
     */
    window.AutoAgoraSpecFilters.updateFilterCounts = function(filterCounts) {
        if (!filterCounts) return;

        // Update fuel type dropdown
        if (filterCounts.fuel_type) {
            this.updateMultiselectCounts('fuel_type', filterCounts.fuel_type);
        }

        // Update transmission dropdown (assuming it might become multiselect or needs similar DOM)
        // If it's a simple select, this might need adjustment or a different helper.
        // For now, let's assume it's handled like other selects or multiselects.
        if (filterCounts.transmission) {
            // If #filter-transmission is a simple select, needs a specific handler.
            // For now, using updateMultiselectCounts as a placeholder if it were checkbox based.
            // This part might need to be adjusted based on actual HTML structure of #filter-transmission
            // For a standard select: updateStandardSelectCounts('transmission', filterCounts.transmission);
            this.updateMultiselectCounts('transmission', filterCounts.transmission); // Placeholder
        }

        // Update body type dropdown
        if (filterCounts.body_type) {
            this.updateMultiselectCounts('body_type', filterCounts.body_type);
        }

        // Update drive type dropdown
        if (filterCounts.drive_type) {
            this.updateMultiselectCounts('drive_type', filterCounts.drive_type);
        }

        // Update exterior color dropdown
        if (filterCounts.exterior_color) {
            this.updateMultiselectCounts('exterior_color', filterCounts.exterior_color);
        }

        // Update interior color dropdown
        if (filterCounts.interior_color) {
            this.updateMultiselectCounts('interior_color', filterCounts.interior_color);
        }
        
        // Update extras
        if (filterCounts.extras) {
            this.updateMultiselectCounts('extras', filterCounts.extras);
        }

        // Update vehicle history
        if (filterCounts.vehiclehistory) { // Note: PHP key is 'vehicle_history'
            this.updateMultiselectCounts('vehiclehistory', filterCounts.vehiclehistory);
        }
        
        // Update simple select counts (example for availability, number_of_doors etc.)
        if (filterCounts.availability) {
            this.updateStandardSelectCounts('availability', filterCounts.availability);
        }
        if (filterCounts.number_of_doors) {
            this.updateStandardSelectCounts('number_of_doors', filterCounts.number_of_doors);
        }
        if (filterCounts.number_of_seats) {
            this.updateStandardSelectCounts('number_of_seats', filterCounts.number_of_seats);
        }
         if (filterCounts.is_antique) { // PHP key is 'is_antique', HTML id 'filter-isantique'
            this.updateStandardSelectCounts('isantique', filterCounts.is_antique);
        }


        // Update year range selects
        if (filterCounts.year) {
            this.updateRangeSelectCounts('year', filterCounts.year);
        }
        
        // Update price range selects (Not directly in filterCounts with this structure, but kept for pattern)
        // Price is usually a text input or specific range inputs, not individual counts per value.
        // This will likely be handled differently or not at all by a generic "counts" update.
        // If filterCounts.price exists and is structured like years, it would work:
        // if (filterCounts.price) {
        //     this.updateRangeSelectCounts('price', filterCounts.price);
        // }


        // Update engine capacity range selects
        if (filterCounts.engine_capacity) { // ACF key: engine_capacity, HTML id filter-engine-capacity-min/max
            this.updateRangeSelectCounts('engine_capacity', filterCounts.engine_capacity, 'L');
        }
        
        // Update HP range selects
        if (filterCounts.hp) {
            this.updateRangeSelectCounts('hp', filterCounts.hp, 'HP');
        }

        // Update mileage range selects
        if (filterCounts.mileage) {
            this.updateRangeSelectCounts('mileage', filterCounts.mileage, 'km');
        }
        
        // Update Number of Owners range selects
        if (filterCounts.number_of_owners) { // ACF key: number_of_owners, HTML id filter-numowners-min/max
             this.updateRangeSelectCounts('numowners', filterCounts.number_of_owners);
        }
    };

    /**
     * Helper function to update multiselect filter counts (checkbox based).
     * @param {string} filterKey - The data-filter-key attribute value.
     * @param {object} counts - Object of option_value: count.
     */
    window.AutoAgoraSpecFilters.updateMultiselectCounts = function(filterKey, counts) {
        const $container = $(`.multi-select-filter[data-filter-key="${filterKey}"]`);
        if ($container.length) {
            $container.find('li input[type="checkbox"]').each(function() {
                const $checkbox = $(this);
                const value = $checkbox.val();
                const count = counts[value] || 0;
                const $label = $checkbox.closest('label');
                let $countSpan = $label.find('.option-count');

                if ($countSpan.length === 0) {
                    // If count span doesn't exist, create and append it
                    $label.append(' <span class="option-count"></span>');
                    $countSpan = $label.find('.option-count');
                }
                $countSpan.text(`(${count})`);
                
                // Disable options with zero count unless currently checked
                if (count === 0 && !$checkbox.prop('checked')) {
                    $checkbox.prop('disabled', true).parent().addClass('disabled-option');
                } else {
                    $checkbox.prop('disabled', false).parent().removeClass('disabled-option');
                }
            });
        } else {
            console.warn(`[SpecFilters] Multiselect container not found for key: ${filterKey}`);
        }
    };

    /**
     * Helper function to update standard select dropdown counts.
     * @param {string} filterIdPrefix - The base ID of the filter select (e.g., 'transmission', 'availability').
     * @param {object} counts - Object of option_value: count.
     */
    window.AutoAgoraSpecFilters.updateStandardSelectCounts = function(filterIdPrefix, counts) {
        const $select = $(`#filter-${filterIdPrefix}`);
        if ($select.length) {
            $select.find('option').each(function() {
                const $option = $(this);
                const value = $option.val();
                
                if (value !== "") { // Skip "Any" or placeholder options
                    const count = counts[value] || 0;
                    let baseText = $option.data('base-text');
                    if (typeof baseText === 'undefined') {
                        baseText = $option.text().replace(/\s*\(\d+\)$/, '');
                        $option.data('base-text', baseText);
                    }
                    $option.text(`${baseText} (${count})`);

                    if (count === 0 && !$option.prop('selected')) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        } else {
             console.warn(`[SpecFilters] Standard select not found for ID prefix: filter-${filterIdPrefix}`);
        }
    };


    /**
     * Helper function to update range select filter counts (Min/Max dropdowns).
     * @param {string} rangeType - The type of range (e.g., 'year', 'mileage', 'engine_capacity').
     * @param {object} counts - Object of option_value: count.
     * @param {string} suffix - Optional suffix for display values (e.g., 'km', 'L').
     */
    window.AutoAgoraSpecFilters.updateRangeSelectCounts = function(rangeType, counts, suffix = '') {
        const $minSelect = $(`#filter-${rangeType}-min`);
        if ($minSelect.length) {
            $minSelect.find('option').each(function() {
                const $option = $(this);
                const value = $option.val();
                if (value !== '') { 
                    const count = counts[value] || 0;
                    let baseText = $option.data('base-text');
                    if (typeof baseText === 'undefined') {
                         // intelligent split: capture text before " (count)" or just text if no count
                        baseText = $option.text().replace(/\s*\(\d+\)$/, '').replace(suffix, '').trim();
                        $option.data('base-text', baseText);
                    }
                    $option.text(`${baseText}${suffix} (${count})`);
                    
                    if (count === 0 && !$option.prop('selected')) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        } else {
            console.warn(`[SpecFilters] Min select not found for range type: ${rangeType}`);
        }
        
        const $maxSelect = $(`#filter-${rangeType}-max`);
        if ($maxSelect.length) {
            $maxSelect.find('option').each(function() {
                const $option = $(this);
                const value = $option.val();
                if (value !== '') {
                    const count = counts[value] || 0;
                     let baseText = $option.data('base-text');
                    if (typeof baseText === 'undefined') {
                        baseText = $option.text().replace(/\s*\(\d+\)$/, '').replace(suffix, '').trim();
                        $option.data('base-text', baseText);
                    }
                    $option.text(`${baseText}${suffix} (${count})`);
                    
                    if (count === 0 && !$option.prop('selected')) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        } else {
            console.warn(`[SpecFilters] Max select not found for range type: ${rangeType}`);
        }
    };

})(jQuery); 