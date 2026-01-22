/**
 * Car Filters - State Manager & UI Controller
 *
 * Handles:
 * - State management across filter groups
 * - Event bus for filter synchronization
 * - Dropdown UI interactions
 * - AJAX filtering and redirect mode
 * - Number formatting for range inputs
 */

(function($) {
    'use strict';

    // Global state manager
    window.CarFilters = {
        groups: {},
        subscribers: {},
        debounceTimers: {},

        /**
         * Initialize a filter group
         */
        initGroup: function(group) {
            if (!this.groups[group]) {
                this.groups[group] = {
                    make: { value: '', slug: '' },
                    model: { value: '', slug: '' },
                    price_min: '',
                    price_max: '',
                    mileage_min: '',
                    mileage_max: '',
                    year_min: '',
                    year_max: '',
                    fuel_type: '',
                    body_type: '',
                    target: '',
                    mode: 'ajax',
                    redirectUrl: '/cars/'
                };
                this.subscribers[group] = [];
            }
            return this.groups[group];
        },

        /**
         * Get state for a group
         */
        getState: function(group) {
            return this.initGroup(group);
        },

        /**
         * Set state value and notify subscribers
         */
        setState: function(group, key, value, slug) {
            this.initGroup(group);

            if (key === 'make' || key === 'model') {
                this.groups[group][key] = { value: value, slug: slug || '' };
            } else {
                this.groups[group][key] = value;
            }

            this.notifySubscribers(group, key, value);
        },

        /**
         * Subscribe to state changes
         */
        subscribe: function(group, callback) {
            this.initGroup(group);
            this.subscribers[group].push(callback);
        },

        /**
         * Notify all subscribers of state change
         */
        notifySubscribers: function(group, key, value) {
            var state = this.groups[group];
            this.subscribers[group].forEach(function(callback) {
                callback(key, value, state);
            });
        },

        /**
         * Get all filter values for AJAX
         */
        getFilterData: function(group) {
            var state = this.getState(group);
            return {
                make: state.make.value,
                model: state.model.value,
                price_min: this.parseNumber(state.price_min),
                price_max: this.parseNumber(state.price_max),
                mileage_min: this.parseNumber(state.mileage_min),
                mileage_max: this.parseNumber(state.mileage_max),
                year_min: this.parseNumber(state.year_min),
                year_max: this.parseNumber(state.year_max),
                fuel_type: state.fuel_type,
                body_type: state.body_type
            };
        },

        /**
         * Build URL with filter parameters (JetSmartFilters-style pretty URLs)
         * Format: /cars/filter/make:bmw-m2/meta/price!range:10000_50000/
         */
        buildUrl: function(group, forRedirect) {
            var state = this.getState(group);
            var parts = [];

            // Taxonomy: make (and model combined as parent-child slug)
            if (state.make.slug) {
                var makeSlug = state.make.slug;
                if (state.model.slug) {
                    // Model slug is the full combined slug (e.g., "bmw-m2")
                    makeSlug = state.model.slug;
                }
                parts.push('make:' + makeSlug);
            }

            // Meta filters
            var metaParts = [];

            // Price range (only if at least one bound is set)
            var priceMin = this.parseNumber(state.price_min);
            var priceMax = this.parseNumber(state.price_max);
            if (priceMin || priceMax) {
                metaParts.push('price!range:' + (priceMin || 0) + '_' + (priceMax || 999999999));
            }

            // Mileage range
            var mileageMin = this.parseNumber(state.mileage_min);
            var mileageMax = this.parseNumber(state.mileage_max);
            if (mileageMin || mileageMax) {
                metaParts.push('mileage!range:' + (mileageMin || 0) + '_' + (mileageMax || 999999999));
            }

            // Year range
            var yearMin = this.parseNumber(state.year_min);
            var yearMax = this.parseNumber(state.year_max);
            if (yearMin || yearMax) {
                metaParts.push('year!range:' + (yearMin || 1900) + '_' + (yearMax || 2100));
            }

            // Simple meta filters
            if (state.fuel_type) metaParts.push('fuel_type:' + state.fuel_type);
            if (state.body_type) metaParts.push('body_type:' + state.body_type);

            if (metaParts.length) {
                parts.push('meta/' + metaParts.join(';'));
            }

            // Base URL: use redirect_url for redirect mode, current page for AJAX
            var baseUrl = forRedirect ? state.redirectUrl : this.getCurrentBasePath();

            // Ensure baseUrl ends without trailing slash
            baseUrl = baseUrl.replace(/\/+$/, '');

            // If no filters, return base URL without /filter/
            if (parts.length === 0) {
                return baseUrl + '/';
            }

            return baseUrl + '/filter/' + parts.join('/') + '/';
        },

        /**
         * Get current page's base path (e.g., /cars from /cars/filter/make:bmw/)
         */
        getCurrentBasePath: function() {
            var path = window.location.pathname;
            var match = path.match(/^(\/[^\/]+)\/filter\//);
            if (match) {
                return match[1]; // Return base path before /filter/
            }
            return path.replace(/\/+$/, ''); // Current path without trailing slash
        },

        /**
         * Parse pretty URL into filter state
         * Format: /cars/filter/make:bmw-m2/meta/price!range:10000_50000/
         */
        parseUrl: function() {
            var path = window.location.pathname;
            var match = path.match(/\/filter\/(.+?)\/?$/);
            if (!match) return null;

            var filterString = match[1];
            var result = {
                makeSlug: null,
                price_min: null,
                price_max: null,
                mileage_min: null,
                mileage_max: null,
                year_min: null,
                year_max: null,
                fuel_type: null,
                body_type: null
            };

            // Parse make:slug (could be make or make-model combined)
            var makeMatch = filterString.match(/make:([^\/]+)/);
            if (makeMatch) {
                result.makeSlug = makeMatch[1]; // Will be resolved by AJAX lookup
            }

            // Parse meta/field:value;field!range:min_max
            var metaMatch = filterString.match(/meta\/([^\/]+)/);
            if (metaMatch) {
                var metaParts = metaMatch[1].split(';');
                metaParts.forEach(function(part) {
                    if (part.indexOf('!range:') !== -1) {
                        // Range: price!range:10000_50000
                        var rangeParts = part.split('!range:');
                        var field = rangeParts[0];
                        var values = rangeParts[1].split('_');
                        var min = parseInt(values[0], 10);
                        var max = parseInt(values[1], 10);

                        // Only set if not placeholder values
                        if (min > 0) {
                            result[field + '_min'] = min;
                        }
                        if (max > 0 && max < 999999999 && (field !== 'year' || max < 2100)) {
                            result[field + '_max'] = max;
                        }
                    } else if (part.indexOf(':') !== -1) {
                        // Simple: fuel_type:Diesel
                        var simpleParts = part.split(':');
                        result[simpleParts[0]] = simpleParts[1];
                    }
                });
            }

            return result;
        },

        /**
         * Trigger AJAX filter request (debounced)
         */
        triggerFilter: function(group) {
            var self = this;
            var state = this.getState(group);

            // Clear existing timer
            if (this.debounceTimers[group]) {
                clearTimeout(this.debounceTimers[group]);
            }

            // Debounce AJAX requests
            this.debounceTimers[group] = setTimeout(function() {
                if (state.mode === 'redirect') {
                    // Redirect mode - navigate to URL
                    window.location.href = self.buildUrl(group, true);
                } else {
                    // AJAX mode - update listings
                    self.ajaxFilter(group);
                }
            }, 300);
        },

        /**
         * Perform AJAX filter request
         */
        ajaxFilter: function(group) {
            var state = this.getState(group);
            var target = state.target;

            if (!target) {
                console.warn('CarFilters: No target specified for group', group);
                return;
            }

            var $target = $('#' + target);
            if (!$target.length) {
                // Try finding by filter_group data attribute
                $target = $('.car-listings-container[data-filter-group="' + group + '"]');
            }

            if (!$target.length) {
                console.warn('CarFilters: Target not found', target);
                return;
            }

            var $wrapper = $target.find('.car-listings-wrapper');
            var listingAtts = $target.data('atts') || {};

            // Show loading state
            $wrapper.addClass('car-listings-loading');

            $.ajax({
                url: carFiltersConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'car_filters_filter_listings',
                    nonce: carFiltersConfig.nonce,
                    ...this.getFilterData(group),
                    listing_atts: JSON.stringify(listingAtts)
                },
                success: function(response) {
                    if (response.success) {
                        $wrapper.html(response.data.html);

                        // Update URL without reload (use current page path, not redirect URL)
                        var url = CarFilters.buildUrl(group, false);
                        history.pushState({ filters: CarFilters.getFilterData(group) }, '', url);

                        // Trigger event for other scripts
                        $(document).trigger('carFilters:updated', [group, response.data]);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CarFilters: AJAX error', error);
                },
                complete: function() {
                    $wrapper.removeClass('car-listings-loading');
                }
            });
        },

        /**
         * Parse formatted number (remove commas)
         */
        parseNumber: function(value) {
            if (!value) return '';
            return parseInt(String(value).replace(/,/g, ''), 10) || '';
        },

        /**
         * Format number with commas
         */
        formatNumber: function(value) {
            if (!value) return '';
            var num = this.parseNumber(value);
            return num ? num.toLocaleString() : '';
        }
    };

    /**
     * Dropdown Controller
     */
    var DropdownController = {
        init: function() {
            this.bindEvents();
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
            var slug = $option.data('slug') || '';
            var label = $option.clone().children('.car-filter-count').remove().end().text().trim();
            var filterType = $dropdown.data('filter-type');
            var group = $dropdown.data('group');

            // Update hidden select
            var $select = $dropdown.find('select');
            $select.val(value).trigger('change');

            // Update button text
            var $button = $dropdown.find('.car-filter-dropdown-button');
            var $text = $button.find('.car-filter-dropdown-text');

            if (value === '' || value === null) {
                $text.addClass('placeholder').text($select.find('option:first').text());
            } else {
                $text.removeClass('placeholder').text(label);
            }

            // Update selected state
            $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
            $option.addClass('selected');

            // Update state
            CarFilters.setState(group, filterType, value, slug);

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

    /**
     * Range Input Controller
     */
    var RangeController = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Format numbers on input
            $(document).on('input', '.car-filter-input', function() {
                self.formatInput($(this));
            });

            // Validate and update state on blur
            $(document).on('blur', '.car-filter-input', function() {
                self.validateAndUpdate($(this));
            });

            // Handle enter key
            $(document).on('keydown', '.car-filter-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                }
            });
        },

        formatInput: function($input) {
            var value = $input.val();
            // Remove non-numeric characters except for the cursor position
            var cursorPos = $input[0].selectionStart;
            var beforeCursor = value.substring(0, cursorPos);
            var commasBefore = (beforeCursor.match(/,/g) || []).length;

            // Clean and format
            var cleaned = value.replace(/[^0-9]/g, '');
            if (cleaned) {
                var formatted = parseInt(cleaned, 10).toLocaleString();
                $input.val(formatted);

                // Adjust cursor position
                var commasAfter = (formatted.substring(0, cursorPos).match(/,/g) || []).length;
                var newPos = cursorPos + (commasAfter - commasBefore);
                $input[0].setSelectionRange(newPos, newPos);
            }
        },

        validateAndUpdate: function($input) {
            var $wrapper = $input.closest('.car-filter-range');
            var filterType = $wrapper.data('filter-type');
            var group = $wrapper.data('group');

            var $minInput = $wrapper.find('.car-filter-input-min');
            var $maxInput = $wrapper.find('.car-filter-input-max');

            var minVal = CarFilters.parseNumber($minInput.val());
            var maxVal = CarFilters.parseNumber($maxInput.val());

            // Validate min <= max
            if (minVal && maxVal && minVal > maxVal) {
                if ($input.hasClass('car-filter-input-min')) {
                    $minInput.val(CarFilters.formatNumber(maxVal));
                    minVal = maxVal;
                } else {
                    $maxInput.val(CarFilters.formatNumber(minVal));
                    maxVal = minVal;
                }
            }

            // Update state
            CarFilters.setState(group, filterType + '_min', minVal ? String(minVal) : '');
            CarFilters.setState(group, filterType + '_max', maxVal ? String(maxVal) : '');
        }
    };

    /**
     * Model Filter Controller
     * Handles loading models when make changes
     */
    var ModelController = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Subscribe to make changes for each group
            $(document).on('carFilters:makeChanged', function(e, group, makeId) {
                self.loadModels(group, makeId);
            });
        },

        loadModels: function(group, makeId) {
            var $modelFilters = $('.car-filter-model[data-group="' + group + '"]');

            $modelFilters.each(function() {
                var $filter = $(this);
                var $dropdown = $filter.find('.car-filter-dropdown');
                var $button = $dropdown.find('.car-filter-dropdown-button');
                var $options = $dropdown.find('.car-filter-dropdown-options');
                var $select = $dropdown.find('select');

                var $search = $dropdown.find('.car-filter-dropdown-search');

                if (!makeId) {
                    // No make selected - disable model dropdown
                    $button.prop('disabled', true).addClass('car-filter-dropdown-disabled');
                    $dropdown.addClass('car-filter-dropdown-disabled');
                    $search.prop('disabled', true);
                    $options.html('<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="" data-slug="">All Models</button><div class="car-filter-no-results hidden">No matching results</div>');
                    $select.html('<option value="">All Models</option>').prop('disabled', true);
                    $button.find('.car-filter-dropdown-text').addClass('placeholder').text('All Models');

                    // Clear model state
                    CarFilters.setState(group, 'model', '', '');
                    return;
                }

                // Show loading state with animated dots
                $button.prop('disabled', true);
                var $buttonText = $button.find('.car-filter-dropdown-text');
                $options.html('<div class="car-filter-loading">Loading.</div>');
                $buttonText.text('Loading.');

                // Start loading animation
                var isLoading = true; // Track loading state to prevent race conditions
                var loadingDots = 1;
                var loadingInterval = setInterval(function() {
                    // Check if still loading and loading element exists to prevent race condition
                    if (!isLoading || !$options.find('.car-filter-loading').length) {
                        return;
                    }
                    loadingDots = (loadingDots % 3) + 1;
                    var loadingText = 'Loading' + '.'.repeat(loadingDots);
                    $options.find('.car-filter-loading').text(loadingText);
                    $buttonText.text(loadingText);
                }, 400);

                // Fetch models via AJAX
                $.ajax({
                    url: carFiltersConfig.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'car_filters_get_models',
                        nonce: carFiltersConfig.nonce,
                        make_term_id: makeId
                    },
                    success: function(response) {
                        // Stop loading state and clear animation BEFORE updating DOM
                        isLoading = false;
                        clearInterval(loadingInterval);
                        if (response.success && response.data) {
                            var html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="" data-slug="">All Models</button>';
                            var selectHtml = '<option value="">All Models</option>';

                            response.data.forEach(function(model) {
                                html += '<button type="button" class="car-filter-dropdown-option" role="option" data-value="' + model.term_id + '" data-slug="' + model.slug + '">' +
                                    model.name +
                                    '<span class="car-filter-count">(' + model.count + ')</span>' +
                                    '</button>';
                                selectHtml += '<option value="' + model.term_id + '" data-slug="' + model.slug + '">' + model.name + ' (' + model.count + ')</option>';
                            });

                            html += '<div class="car-filter-no-results hidden">No matching results</div>';

                            $options.html(html);
                            $select.html(selectHtml).prop('disabled', false);
                            $button.prop('disabled', false).removeClass('car-filter-dropdown-disabled');
                            $buttonText.addClass('placeholder').text('Select Model');
                            $dropdown.removeClass('car-filter-dropdown-disabled');
                            $search.prop('disabled', false);
                        }
                    },
                    error: function() {
                        // Stop loading state and clear animation BEFORE updating DOM
                        isLoading = false;
                        clearInterval(loadingInterval);
                        $options.html('<div class="car-filter-error">Failed to load models</div>');
                        $buttonText.addClass('placeholder').text('Select Model');
                    }
                });
            });
        }
    };

    /**
     * Search Button Controller
     */
    var SearchButtonController = {
        init: function() {
            $(document).on('click', '.car-filters-search-btn', function(e) {
                e.preventDefault();
                var group = $(this).data('group');
                CarFilters.triggerFilter(group);
            });
        }
    };

    /**
     * Initialize everything
     */
    $(document).ready(function() {
        DropdownController.init();
        RangeController.init();
        ModelController.init();
        SearchButtonController.init();

        // Initialize groups from DOM
        $('.car-filters-container, .car-filter').each(function() {
            var group = $(this).data('group');
            var mode = $(this).data('mode');
            var target = $(this).data('target');
            var redirectUrl = $(this).data('redirect-url');

            if (group) {
                var state = CarFilters.initGroup(group);
                if (mode) state.mode = mode;
                if (target) state.target = target;
                if (redirectUrl) state.redirectUrl = redirectUrl;
            }
        });

        // Set up make -> model dependency
        $('.car-filter').each(function() {
            var $filter = $(this);
            var group = $filter.data('group') || $filter.find('[data-group]').first().data('group');

            if (group) {
                CarFilters.subscribe(group, function(key, value, state) {
                    if (key === 'make') {
                        $(document).trigger('carFilters:makeChanged', [group, value]);
                    }
                });
            }
        });

        // Initialize state from URL (pretty URL or query params fallback)
        var parsedUrl = CarFilters.parseUrl();

        if (parsedUrl) {
            // Pretty URL detected - initialize from parsed data
            $('.car-filters-container').each(function() {
                var group = $(this).data('group');
                if (!group) return;

                // Set meta filter values
                ['price', 'mileage', 'year'].forEach(function(key) {
                    if (parsedUrl[key + '_min']) {
                        CarFilters.setState(group, key + '_min', String(parsedUrl[key + '_min']));
                    }
                    if (parsedUrl[key + '_max']) {
                        CarFilters.setState(group, key + '_max', String(parsedUrl[key + '_max']));
                    }
                });

                if (parsedUrl.fuel_type) {
                    CarFilters.setState(group, 'fuel_type', parsedUrl.fuel_type);
                }
                if (parsedUrl.body_type) {
                    CarFilters.setState(group, 'body_type', parsedUrl.body_type);
                }

                // Resolve make/model slug via AJAX
                if (parsedUrl.makeSlug) {
                    $.ajax({
                        url: carFiltersConfig.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'car_filters_resolve_slug',
                            nonce: carFiltersConfig.nonce,
                            slug: parsedUrl.makeSlug
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                var data = response.data;
                                if (data.make) {
                                    CarFilters.setState(group, 'make', data.make_term_id, data.make);

                                    // Trigger model loading
                                    $(document).trigger('carFilters:makeChanged', [group, data.make_term_id]);

                                    // If there's a model, set it after models are loaded
                                    if (data.model) {
                                        // Wait for models to load, then select model
                                        setTimeout(function() {
                                            CarFilters.setState(group, 'model', data.model_term_id, data.model);

                                            // Update model dropdown UI
                                            var $modelDropdowns = $('.car-filter-model[data-group="' + group + '"] .car-filter-dropdown');
                                            $modelDropdowns.each(function() {
                                                var $dropdown = $(this);
                                                var $option = $dropdown.find('.car-filter-dropdown-option[data-value="' + data.model_term_id + '"]');
                                                if ($option.length) {
                                                    $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
                                                    $option.addClass('selected');
                                                    $dropdown.find('.car-filter-dropdown-text')
                                                        .removeClass('placeholder')
                                                        .text($option.clone().children('.car-filter-count').remove().end().text().trim());
                                                    $dropdown.find('select').val(data.model_term_id);
                                                }
                                            });
                                        }, 500);
                                    }

                                    // Update make dropdown UI
                                    var $makeDropdowns = $('.car-filter-make[data-group="' + group + '"] .car-filter-dropdown');
                                    $makeDropdowns.each(function() {
                                        var $dropdown = $(this);
                                        var $option = $dropdown.find('.car-filter-dropdown-option[data-value="' + data.make_term_id + '"]');
                                        if ($option.length) {
                                            $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
                                            $option.addClass('selected');
                                            $dropdown.find('.car-filter-dropdown-text')
                                                .removeClass('placeholder')
                                                .text($option.clone().children('.car-filter-count').remove().end().text().trim());
                                            $dropdown.find('select').val(data.make_term_id);
                                        }
                                    });
                                }
                            }
                        }
                    });
                }

                // Update range input UIs
                ['price', 'mileage', 'year'].forEach(function(key) {
                    if (parsedUrl[key + '_min']) {
                        var $minInputs = $('.car-filter-' + key + '[data-group="' + group + '"] .car-filter-input-min');
                        $minInputs.val(CarFilters.formatNumber(parsedUrl[key + '_min']));
                    }
                    if (parsedUrl[key + '_max']) {
                        var $maxInputs = $('.car-filter-' + key + '[data-group="' + group + '"] .car-filter-input-max');
                        $maxInputs.val(CarFilters.formatNumber(parsedUrl[key + '_max']));
                    }
                });

                // Update simple filter dropdown UIs
                ['fuel_type', 'body_type'].forEach(function(key) {
                    if (parsedUrl[key]) {
                        var filterClass = key === 'fuel_type' ? 'fuel' : 'body';
                        var $dropdowns = $('.car-filter-' + filterClass + '[data-group="' + group + '"] .car-filter-dropdown');
                        $dropdowns.each(function() {
                            var $dropdown = $(this);
                            var $option = $dropdown.find('.car-filter-dropdown-option').filter(function() {
                                return $(this).data('slug') === parsedUrl[key] || $(this).data('value') === parsedUrl[key];
                            });
                            if ($option.length) {
                                $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
                                $option.addClass('selected');
                                $dropdown.find('.car-filter-dropdown-text')
                                    .removeClass('placeholder')
                                    .text($option.clone().children('.car-filter-count').remove().end().text().trim());
                                $dropdown.find('select').val($option.data('value'));
                            }
                        });
                    }
                });
            });
        } else {
            // Fallback: Initialize state from query parameters (backward compatibility)
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.toString()) {
                $('.car-filters-container').each(function() {
                    var group = $(this).data('group');
                    if (!group) return;

                    // Set state from URL
                    ['make', 'model', 'fuel_type', 'body_type'].forEach(function(key) {
                        if (urlParams.has(key)) {
                            CarFilters.setState(group, key, urlParams.get(key), urlParams.get(key));
                        }
                    });

                    ['price', 'mileage', 'year'].forEach(function(key) {
                        if (urlParams.has(key + '_min')) {
                            CarFilters.setState(group, key + '_min', urlParams.get(key + '_min'));
                        }
                        if (urlParams.has(key + '_max')) {
                            CarFilters.setState(group, key + '_max', urlParams.get(key + '_max'));
                        }
                    });
                });
            }
        }
    });

})(jQuery);
