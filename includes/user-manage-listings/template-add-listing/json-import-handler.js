/**
 * JSON Import Handler for Add Listing Form
 * 
 * Handles JSON file upload and automatic form field population.
 * This script is only loaded for WordPress administrators (server-side check).
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Development mode detection
    const isDevelopment = window.isDevelopment || 
                         (window.location.hostname === 'localhost' || 
                          window.location.hostname.includes('staging') || 
                          window.location.search.includes('debug=true'));

    /**
     * JSON Import Handler Class
     * Handles JSON file parsing and form field population
     */
    class JsonImportHandler {
        constructor() {
            this.jsonData = null;
            this.init();
        }

        /**
         * Initialize event handlers
         */
        init() {
            // Only initialize if JSON import section exists (admin check)
            if ($('#json-import-section').length === 0) {
                if (isDevelopment) console.log('[JSON Import] Section not found - user is not admin');
                return;
            }

            if (isDevelopment) console.log('[JSON Import] Initializing...');

            // File input change handler
            $('#json-file-input').on('change', (e) => this.handleFileSelect(e));
            
            // Import button click handler
            $('#json-import-btn').on('click', () => {
                $('#json-file-input').click();
            });

            // Clear button handler
            $('#json-clear-btn').on('click', () => this.clearImport());

            // Prevent form submission if JSON import is in progress
            $('#add-car-listing-form').on('submit', (e) => {
                if (this.isImporting) {
                    e.preventDefault();
                    this.showStatus('Please wait for JSON import to complete.', 'error');
                    return false;
                }
            });
        }

        /**
         * Handle file selection
         */
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            if (!file.name.endsWith('.json')) {
                this.showStatus('Please select a valid JSON file.', 'error');
                return;
            }

            // Show file name
            $('#json-file-name').text(file.name).show();
            $('#json-clear-btn').show();
            this.showStatus('Reading JSON file...', 'info');

            // Read file
            const reader = new FileReader();
            reader.onload = (e) => this.handleFileLoad(e);
            reader.onerror = () => {
                this.showStatus('Error reading file. Please try again.', 'error');
            };
            reader.readAsText(file);
        }

        /**
         * Handle file load and parse JSON
         */
        handleFileLoad(event) {
            try {
                const jsonText = event.target.result;
                this.jsonData = JSON.parse(jsonText);
                
                if (isDevelopment) console.log('[JSON Import] Parsed JSON:', this.jsonData);

                // Validate JSON structure
                if (!this.validateJsonStructure(this.jsonData)) {
                    this.showStatus('Invalid JSON structure. Please check the file format.', 'error');
                    return;
                }

                // Populate form fields
                this.populateFormFields(this.jsonData);
                this.showStatus('JSON imported successfully! Please review and complete required fields (Make, Model, Availability, Location).', 'success');

            } catch (error) {
                if (isDevelopment) console.error('[JSON Import] Parse error:', error);
                this.showStatus('Invalid JSON format. Please check your file.', 'error');
            }
        }

        /**
         * Validate JSON structure has expected fields
         */
        validateJsonStructure(data) {
            if (!data || typeof data !== 'object') {
                return false;
            }

            // Check for at least some expected fields
            const expectedFields = ['year', 'mileage', 'price', 'engine_capacity', 'fuel_type'];
            const hasSomeFields = expectedFields.some(field => data.hasOwnProperty(field));
            
            return hasSomeFields;
        }

        /**
         * Populate form fields from JSON data
         */
        populateFormFields(data) {
            if (isDevelopment) console.log('[JSON Import] Populating form fields...');

            // Map JSON fields to form field IDs
            const fieldMapping = {
                'year': { type: 'dropdown', id: 'add-listing-year' },
                'mileage': { type: 'text', id: 'mileage' },
                'price': { type: 'text', id: 'price' },
                'engine_capacity': { type: 'dropdown', id: 'add-listing-engine-capacity' },
                'fuel_type': { type: 'dropdown', id: 'add-listing-fuel-type' },
                'transmission': { type: 'dropdown', id: 'add-listing-transmission' },
                'body_type': { type: 'dropdown', id: 'add-listing-body-type' },
                'exterior_color': { type: 'dropdown', id: 'add-listing-exterior-color' },
                'drive_type': { type: 'dropdown', id: 'add-listing-drive-type' },
                'number_of_doors': { type: 'dropdown', id: 'add-listing-doors' },
                'number_of_seats': { type: 'dropdown', id: 'add-listing-seats' },
                'interior_color': { type: 'dropdown', id: 'add-listing-interior-color' },
                'motuntil': { type: 'dropdown', id: 'add-listing-mot' },
                'hp': { type: 'text', id: 'hp' },
                'numowners': { type: 'text', id: 'numowners' },
                'description': { type: 'textarea', id: 'description' }
            };

            // Populate each field
            Object.keys(fieldMapping).forEach(jsonKey => {
                if (data.hasOwnProperty(jsonKey) && data[jsonKey] !== null && data[jsonKey] !== undefined && data[jsonKey] !== '') {
                    const mapping = fieldMapping[jsonKey];
                    const value = data[jsonKey];

                    if (mapping.type === 'dropdown') {
                        this.setDropdownValue(mapping.id, value);
                    } else if (mapping.type === 'text') {
                        this.setTextValue(mapping.id, value);
                    } else if (mapping.type === 'textarea') {
                        this.setTextareaValue(mapping.id, value);
                    }
                }
            });

            // Handle extras (array of checkboxes)
            if (data.extras && Array.isArray(data.extras) && data.extras.length > 0) {
                this.setCheckboxes('extras', data.extras);
            }

            // Handle vehicle history (array of checkboxes)
            if (data.vehiclehistory && Array.isArray(data.vehiclehistory) && data.vehiclehistory.length > 0) {
                this.setCheckboxes('vehiclehistory', data.vehiclehistory);
            }

            // Handle isantique checkbox
            if (data.isantique !== undefined) {
                const checkbox = $('#isantique');
                if (data.isantique === true || data.isantique === 1 || data.isantique === '1') {
                    checkbox.prop('checked', true);
                } else {
                    checkbox.prop('checked', false);
                }
            }

            if (isDevelopment) console.log('[JSON Import] Form fields populated');
        }

        /**
         * Set dropdown value (handles custom dropdown component)
         */
        setDropdownValue(fieldId, value) {
            const $wrapper = $('#' + fieldId + '-wrapper');
            if ($wrapper.length === 0) {
                if (isDevelopment) console.warn('[JSON Import] Dropdown wrapper not found:', fieldId);
                return;
            }

            const $select = $wrapper.find('select');
            if ($select.length === 0) {
                if (isDevelopment) console.warn('[JSON Import] Select element not found:', fieldId);
                return;
            }

            // Convert value to string for comparison
            const stringValue = String(value);

            // Check if value exists in options
            const optionExists = $select.find('option').filter(function() {
                return $(this).val() === stringValue;
            }).length > 0;

            if (optionExists) {
                $select.val(stringValue).trigger('change');
                
                // Also trigger custom dropdown update if AddListingDropdown exists
                if (typeof AddListingDropdown !== 'undefined' && AddListingDropdown) {
                    AddListingDropdown.setValue($wrapper, stringValue);
                }

                if (isDevelopment) console.log('[JSON Import] Set dropdown:', fieldId, '=', stringValue);
            } else {
                if (isDevelopment) console.warn('[JSON Import] Value not found in dropdown options:', fieldId, '=', stringValue);
            }
        }

        /**
         * Set text input value
         */
        setTextValue(fieldId, value) {
            const $input = $('#' + fieldId);
            if ($input.length > 0) {
                $input.val(String(value)).trigger('input');
                if (isDevelopment) console.log('[JSON Import] Set text:', fieldId, '=', value);
            }
        }

        /**
         * Set textarea value
         */
        setTextareaValue(fieldId, value) {
            const $textarea = $('#' + fieldId);
            if ($textarea.length > 0) {
                $textarea.val(String(value)).trigger('input');
                if (isDevelopment) console.log('[JSON Import] Set textarea:', fieldId);
            }
        }

        /**
         * Set checkbox values (for extras, vehiclehistory arrays)
         */
        setCheckboxes(name, values) {
            if (!Array.isArray(values)) return;

            values.forEach(value => {
                const checkboxId = name === 'extras' ? 'extra_' + value : 'vehiclehistory_' + value;
                const $checkbox = $('#' + checkboxId);
                
                if ($checkbox.length > 0) {
                    $checkbox.prop('checked', true);
                    if (isDevelopment) console.log('[JSON Import] Checked:', checkboxId);
                } else {
                    if (isDevelopment) console.warn('[JSON Import] Checkbox not found:', checkboxId);
                }
            });
        }

        /**
         * Clear import and reset form
         */
        clearImport() {
            this.jsonData = null;
            $('#json-file-input').val('');
            $('#json-file-name').hide().text('');
            $('#json-clear-btn').hide();
            this.hideStatus();
            
            if (isDevelopment) console.log('[JSON Import] Cleared');
        }

        /**
         * Show status message
         */
        showStatus(message, type = 'info') {
            const $status = $('#json-import-status');
            $status.removeClass('success error info')
                   .addClass(type)
                   .text(message)
                   .show();
        }

        /**
         * Hide status message
         */
        hideStatus() {
            $('#json-import-status').hide().removeClass('success error info').text('');
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize if user is admin (section exists)
        if ($('#json-import-section').length > 0) {
            window.jsonImportHandler = new JsonImportHandler();
            if (isDevelopment) console.log('[JSON Import] Handler initialized');
        }
    });

})(jQuery);

