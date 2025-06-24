jQuery(document).ready(function($) {
    // PRODUCTION SAFETY: Only log in development environments
const isDevelopment = window.location.hostname === 'localhost' || 
                     window.location.hostname.includes('staging') ||
                     window.location.search.includes('debug=true');

if (isDevelopment) console.log('[Quick Add] Form enhancement loaded');
    
    // Add quick action buttons to the add listing form
    if ($('#add-car-listing-form').length) {
        addQuickActionButtons();
    }
    
    function addQuickActionButtons() {
        // Create quick actions container
        const quickActionsHtml = `
            <div class="quick-actions-section input-wrapper">
                <h2>⚡ Quick Actions</h2>
                <div class="quick-actions-buttons">
                    <button type="button" class="btn btn-secondary" id="save-template-btn">
                        Save as Template
                    </button>
                    <button type="button" class="btn btn-secondary" id="load-template-btn">
                        Load Template
                    </button>
                    <button type="button" class="btn btn-secondary" id="duplicate-form-btn">
                        Duplicate Form
                    </button>
                    <button type="button" class="btn btn-secondary" id="clear-form-btn">
                        Clear Form
                    </button>
                </div>
                
                <div id="template-controls" style="display: none; margin-top: 1rem;">
                    <div class="template-save-section">
                        <input type="text" id="template-name" placeholder="Enter template name..." class="form-control" style="width: 200px; display: inline-block; margin-right: 10px;">
                        <button type="button" class="btn btn-primary" id="confirm-save-template">Save</button>
                        <button type="button" class="btn btn-secondary" id="cancel-save-template">Cancel</button>
                    </div>
                </div>
                
                <div id="template-load-section" style="display: none; margin-top: 1rem;">
                    <select id="template-selector" class="form-control" style="width: 200px; display: inline-block; margin-right: 10px;">
                        <option value="">Select a template...</option>
                    </select>
                    <button type="button" class="btn btn-primary" id="confirm-load-template">Load</button>
                    <button type="button" class="btn btn-secondary" id="cancel-load-template">Cancel</button>
                </div>
            </div>
        `;
        
        // Insert after the images section
        $('.add-listing-images-section').after(quickActionsHtml);
        
        // Load existing templates
        loadUserTemplates();
        
        // Bind events
        bindQuickActionEvents();
    }
    
    function bindQuickActionEvents() {
        // Save template button
        $('#save-template-btn').on('click', function() {
            $('#template-controls').toggle();
            $('#template-load-section').hide();
        });
        
        // Load template button
        $('#load-template-btn').on('click', function() {
            $('#template-load-section').toggle();
            $('#template-controls').hide();
            loadUserTemplates();
        });
        
        // Duplicate form button
        $('#duplicate-form-btn').on('click', function() {
            if (confirm('This will open a new tab with the current form data. Continue?')) {
                duplicateCurrentForm();
            }
        });
        
        // Clear form button
        $('#clear-form-btn').on('click', function() {
            if (confirm('This will clear all form data. Are you sure?')) {
                clearForm();
            }
        });
        
        // Confirm save template
        $('#confirm-save-template').on('click', function() {
            const templateName = $('#template-name').val().trim();
            if (!templateName) {
                alert('Please enter a template name');
                return;
            }
            saveTemplate(templateName);
        });
        
        // Cancel save template
        $('#cancel-save-template').on('click', function() {
            $('#template-controls').hide();
            $('#template-name').val('');
        });
        
        // Confirm load template
        $('#confirm-load-template').on('click', function() {
            const templateName = $('#template-selector').val();
            if (!templateName) {
                alert('Please select a template');
                return;
            }
            loadTemplate(templateName);
        });
        
        // Cancel load template
        $('#cancel-load-template').on('click', function() {
            $('#template-load-section').hide();
        });
    }
    
    function saveTemplate(templateName) {
        const formData = getFormData();
        
        $.ajax({
            url: quickAddData.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_quick_template',
                nonce: quickAddData.nonce,
                template_name: templateName,
                ...formData
            },
            success: function(response) {
                if (response.success) {
                    alert('Template saved successfully!');
                    $('#template-controls').hide();
                    $('#template-name').val('');
                    loadUserTemplates();
                } else {
                    alert('Error saving template: ' + response.data);
                }
            },
            error: function() {
                alert('Error saving template. Please try again.');
            }
        });
    }
    
    function loadTemplate(templateName) {
        $.ajax({
            url: quickAddData.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_quick_template',
                nonce: quickAddData.nonce,
                template_name: templateName
            },
            success: function(response) {
                if (response.success) {
                    populateForm(response.data);
                    $('#template-load-section').hide();
                    alert('Template loaded successfully!');
                } else {
                    alert('Error loading template: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading template. Please try again.');
            }
        });
    }
    
    function loadUserTemplates() {
        // This would typically be an AJAX call to get user templates
        // For now, we'll populate the select when templates are available
        const $selector = $('#template-selector');
        $selector.find('option:not(:first)').remove();
        
        // Add saved templates (this would come from server)
        // Example templates for demonstration
        const exampleTemplates = ['BMW Sedans', 'Audi SUVs', 'Mercedes Coupes'];
        exampleTemplates.forEach(function(template) {
            $selector.append(`<option value="${template}">${template}</option>`);
        });
    }
    
    function getFormData() {
        const formData = {};
        
        // Get all form inputs except images and description
        $('#add-car-listing-form').find('select, input[type="text"], input[type="number"]').each(function() {
            const $this = $(this);
            const name = $this.attr('name');
            const value = $this.val();
            
            if (name && value && name !== 'description' && name !== 'mileage' && name !== 'price' && name !== 'year') {
                formData[name] = value;
            }
        });
        
        // Get checkboxes
        $('#add-car-listing-form').find('input[type="checkbox"]:checked').each(function() {
            const $this = $(this);
            const name = $this.attr('name');
            
            if (name) {
                if (name.endsWith('[]')) {
                    const key = name.replace('[]', '');
                    if (!formData[key]) formData[key] = [];
                    formData[key].push($this.val());
                } else {
                    formData[name] = $this.val();
                }
            }
        });
        
        return formData;
    }
    
    function populateForm(data) {
        Object.keys(data).forEach(function(key) {
            const value = data[key];
            const $field = $(`[name="${key}"]`);
            
            if ($field.length) {
                if ($field.is('select') || $field.is('input[type="text"]') || $field.is('input[type="number"]')) {
                    $field.val(value);
                } else if ($field.is('input[type="checkbox"]')) {
                    if (Array.isArray(value)) {
                        value.forEach(function(v) {
                            $(`[name="${key}[]"][value="${v}"]`).prop('checked', true);
                        });
                    } else {
                        $field.prop('checked', true);
                    }
                }
            }
        });
        
        // Trigger change events to update dependent fields
        $('#make').trigger('change');
        $('#fuel_type').trigger('change');
    }
    
    function duplicateCurrentForm() {
        const formData = getFormData();
        const queryParams = new URLSearchParams();
        
        Object.keys(formData).forEach(function(key) {
            if (Array.isArray(formData[key])) {
                formData[key].forEach(function(value) {
                    queryParams.append(key + '[]', value);
                });
            } else {
                queryParams.append(key, formData[key]);
            }
        });
        
        const currentUrl = window.location.href.split('?')[0];
        const newUrl = currentUrl + '?' + queryParams.toString();
        
        window.open(newUrl, '_blank');
    }
    
    function clearForm() {
        $('#add-car-listing-form')[0].reset();
        $('#image-preview').empty();
        
        // Clear any dynamically populated fields
        $('#model').empty().append('<option value="">Select Model</option>');
        
        // Reset engine capacity if it was locked
        const engineCapacitySelect = $('#engine_capacity');
        if (engineCapacitySelect.hasClass('electric-locked')) {
            engineCapacitySelect.removeClass('electric-locked').prop('disabled', false);
            engineCapacitySelect.find('option[value="0.0"]').remove();
        }
        
        if (isDevelopment) console.log('[Quick Add] Form cleared');
    }
    
    // Auto-populate form from URL parameters (for duplication)
    function populateFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const formData = {};
        
        for (const [key, value] of urlParams) {
            if (key.endsWith('[]')) {
                const cleanKey = key.replace('[]', '');
                if (!formData[cleanKey]) formData[cleanKey] = [];
                formData[cleanKey].push(value);
            } else {
                formData[key] = value;
            }
        }
        
        if (Object.keys(formData).length > 0) {
            // Small delay to ensure form is fully loaded
            setTimeout(function() {
                populateForm(formData);
                if (isDevelopment) console.log('[Quick Add] Form populated from URL parameters');
            }, 500);
        }
    }
    
    // Initialize URL population if parameters exist
    populateFromUrl();
    
    // Add keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save template
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            $('#save-template-btn').click();
        }
        
        // Ctrl+L to load template
        if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            $('#load-template-btn').click();
        }
        
        // Ctrl+D to duplicate
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            $('#duplicate-form-btn').click();
        }
    });
}); 