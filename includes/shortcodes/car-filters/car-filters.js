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
                    redirectUrl: '/cars/',
                    resultsBaseUrl: '/cars/',
                    landingMakeSlug: '',
                    landingModelSlug: ''
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
            $(document).trigger('carFilters:stateChanged', [group, key]);
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

        appendNonMakeParams: function(params, state) {
            var priceMin = this.parseNumber(state.price_min);
            var priceMax = this.parseNumber(state.price_max);
            if (priceMin) params.set('price_min', priceMin);
            if (priceMax) params.set('price_max', priceMax);

            var mileageMin = this.parseNumber(state.mileage_min);
            var mileageMax = this.parseNumber(state.mileage_max);
            if (mileageMin) params.set('mileage_min', mileageMin);
            if (mileageMax) params.set('mileage_max', mileageMax);

            var yearMin = this.parseNumber(state.year_min);
            var yearMax = this.parseNumber(state.year_max);
            if (yearMin) params.set('year_min', yearMin);
            if (yearMax) params.set('year_max', yearMax);

            if (state.fuel_type) params.set('fuel_type', state.fuel_type);
            if (state.body_type) params.set('body_type', state.body_type);
        },

        hasLandingContext: function(state) {
            return !!(state && (state.landingMakeSlug || state.landingModelSlug));
        },

        matchesLandingContext: function(state) {
            if (!this.hasLandingContext(state)) {
                return false;
            }

            var makeMatches = state.make.slug === state.landingMakeSlug;
            var modelMatches = state.landingModelSlug
                ? state.model.slug === state.landingModelSlug
                : !state.model.slug;

            return makeMatches && modelMatches;
        },

        /**
         * Cars listing URL using query params: /cars/?make=…&model=…&price_min=…
         * @param {boolean} forRedirect Kept for API compatibility; same output as buildResultsUrl.
         */
        buildUrl: function(group, forRedirect) {
            return this.buildResultsUrl(group);
        },

        /**
         * Destination for leaving SEO landing pages / redirect mode: /cars/ with ?make=&model= etc.
         * @param {string} group Filter group id
         * @param {Object} [extras] Optional: orderby, order, paged
         */
        buildResultsUrl: function(group, extras) {
            extras = extras || {};
            var state = this.getState(group);
            var params = new URLSearchParams();
            var baseUrl = (state.resultsBaseUrl || state.redirectUrl || '/cars/').replace(/\/+$/, '') + '/';

            if (state.make.slug) {
                params.set('make', state.make.slug);
            }
            if (state.model.slug) {
                params.set('model', state.model.slug);
            }

            this.appendNonMakeParams(params, state);

            // Use cars_* to avoid WordPress core consuming orderby/order as main-query vars on /cars/ pages.
            if (extras.orderby) {
                params.set('cars_orderby', extras.orderby);
            }
            if (extras.order) {
                params.set('cars_order', extras.order);
            }
            var paged = parseInt(extras.paged, 10);
            if (paged > 1) {
                params.set('paged', paged);
            }

            var qs = params.toString();
            return qs ? baseUrl + '?' + qs : baseUrl;
        },

        /**
         * Parse URL query parameters into filter state
         */
        parseUrl: function() {
            var params = new URLSearchParams(window.location.search);
            var pathname = window.location.pathname.replace(/\/+$/, '');
            var prettyMatch = pathname.match(/\/cars\/filter\/make:([^/]+)$/);

            if (!params.toString() && !prettyMatch) return null;

            var result = {
                makeSlug: prettyMatch ? decodeURIComponent(prettyMatch[1]) : (params.get('model') || params.get('make') || null),
                price_min: params.get('price_min') ? parseInt(params.get('price_min'), 10) : null,
                price_max: params.get('price_max') ? parseInt(params.get('price_max'), 10) : null,
                mileage_min: params.get('mileage_min') ? parseInt(params.get('mileage_min'), 10) : null,
                mileage_max: params.get('mileage_max') ? parseInt(params.get('mileage_max'), 10) : null,
                year_min: params.get('year_min') ? parseInt(params.get('year_min'), 10) : null,
                year_max: params.get('year_max') ? parseInt(params.get('year_max'), 10) : null,
                fuel_type: params.get('fuel_type') || null,
                body_type: params.get('body_type') || null
            };

            // Return null if nothing was actually set
            var hasAny = Object.keys(result).some(function(k) { return result[k] !== null; });
            return hasAny ? result : null;
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
                    window.location.href = self.buildResultsUrl(group);
                } else if (self.hasLandingContext(state)) {
                    // SEO landing pages: always continue browsing on /cars/ with the same filters
                    window.location.href = self.buildResultsUrl(group);
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

                        var url = CarFilters.buildResultsUrl(group);
                        history.pushState({ filters: CarFilters.getFilterData(group) }, '', url);

                        // Trigger event for other scripts
                        $(document).trigger('carFilters:updated', [group, response.data]);
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'abort') return;
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
            var isMultiSelect = $dropdown.data('multiselect') === true || $dropdown.data('multiselect') === 'true';

            if (isMultiSelect) {
                this._selectMulti($dropdown, $option, value, filterType, group);
                return;
            }

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

        _selectMulti: function($dropdown, $option, value, filterType, group) {
            var $button = $dropdown.find('.car-filter-dropdown-button');
            var $text = $button.find('.car-filter-dropdown-text');
            var placeholder = $dropdown.find('select option:first').text();

            // Clicking "All" clears everything
            if (value === '' || value === null || value === undefined || String(value) === '') {
                $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
                $dropdown.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
                $text.addClass('placeholder').text(placeholder);
                CarFilters.setState(group, filterType, '');
                return;
            }

            // Toggle this option
            $option.toggleClass('selected');

            // Deselect "All" option
            $dropdown.find('.car-filter-dropdown-option[data-value=""]').removeClass('selected');

            // Build comma-separated value from all selected options
            var selectedValues = [];
            $dropdown.find('.car-filter-dropdown-option.selected').each(function() {
                var v = $(this).data('value');
                if (v !== '' && v !== null && v !== undefined && String(v) !== '') {
                    selectedValues.push(String(v));
                }
            });

            // If nothing selected, revert to "All"
            if (selectedValues.length === 0) {
                $dropdown.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
                $text.addClass('placeholder').text(placeholder);
                CarFilters.setState(group, filterType, '');
                return;
            }

            // Update button text
            if (selectedValues.length === 1) {
                var $sel = $dropdown.find('.car-filter-dropdown-option.selected[data-value!=""]').first();
                var singleLabel = $sel.clone().children('.car-filter-count').remove().end().text().trim();
                $text.removeClass('placeholder').text(singleLabel);
            } else {
                $text.removeClass('placeholder').text(selectedValues.length + ' selected');
            }

            // Update state with comma-separated string
            CarFilters.setState(group, filterType, selectedValues.join(','));
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

            // Skip comma formatting for year inputs
            var filterType = $input.closest('.car-filter-range').data('filter-type');
            if (filterType === 'year') {
                var cleaned = value.replace(/[^0-9]/g, '');
                $input.val(cleaned);
                return;
            }

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
     * Handles disabling model dropdown when no make is selected.
     * Model population is handled by CascadeController.
     */
    var ModelController = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            $(document).on('carFilters:makeChanged', function(e, group, makeId) {
                self.handleMakeChange(group, makeId);
            });
        },

        handleMakeChange: function(group, makeId) {
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

                // Show loading state — CascadeController will populate
                $button.prop('disabled', true);
                var $buttonText = $button.find('.car-filter-dropdown-text');
                $options.html('<div class="car-filter-loading">Loading.</div>');
                $buttonText.text('Loading.');

                var loadingDots = 1;
                $dropdown.data('loadingInterval', setInterval(function() {
                    if (!$options.find('.car-filter-loading').length) return;
                    loadingDots = (loadingDots % 3) + 1;
                    var loadingText = 'Loading' + '.'.repeat(loadingDots);
                    $options.find('.car-filter-loading').text(loadingText);
                    $buttonText.text(loadingText);
                }, 400));
            });
        }
    };

    /**
     * Cascade Controller
     * Fetches available options for all dropdowns whenever any filter changes.
     * Uses the "exclude-self" pattern so a filter never hides its own selected value.
     */
    var CascadeController = {
        _updating: false,
        _debounceTimer: null,
        _currentXhr: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;
            $(document).on('carFilters:stateChanged', function(e, group, key) {
                if (self._updating) return;
                self.fetchAvailableOptions(group);
            });
        },

        fetchAvailableOptions: function(group) {
            var self = this;

            // Debounce
            if (this._debounceTimer) {
                clearTimeout(this._debounceTimer);
            }

            this._debounceTimer = setTimeout(function() {
                // Abort in-flight request
                if (self._currentXhr) {
                    self._currentXhr.abort();
                }

                var filterData = CarFilters.getFilterData(group);

                self._currentXhr = $.ajax({
                    url: carFiltersConfig.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'car_filters_get_available_options',
                        nonce: carFiltersConfig.nonce,
                        make: filterData.make,
                        model: filterData.model,
                        price_min: filterData.price_min,
                        price_max: filterData.price_max,
                        mileage_min: filterData.mileage_min,
                        mileage_max: filterData.mileage_max,
                        year_min: filterData.year_min,
                        year_max: filterData.year_max,
                        fuel_type: filterData.fuel_type,
                        body_type: filterData.body_type
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            self.updateDropdowns(group, response.data);
                        }
                    },
                    complete: function() {
                        self._currentXhr = null;
                    }
                });
            }, 150);
        },

        updateDropdowns: function(group, data) {
            this._updating = true;

            this.updateDropdown(group, 'make', data.makes, 'term_id', 'name', 'slug', 'All Brands');
            this.updateDropdown(group, 'fuel_type', data.fuel_type, 'value', 'label', 'slug', 'All Fuel Types');
            this.updateDropdown(group, 'body_type', data.body_type, 'value', 'label', 'slug', 'All Body Types');

            if (data.models !== null) {
                this.rebuildModelDropdown(group, data.models);
            }

            this._updating = false;
        },

        /**
         * Show/hide existing dropdown options based on available values.
         * If selected option becomes unavailable, auto-clear to "All".
         */
        updateDropdown: function(group, filterType, items, valueKey, labelKey, slugKey, placeholder) {
            var filterClass = filterType;
            if (filterType === 'fuel_type') filterClass = 'fuel';
            if (filterType === 'body_type') filterClass = 'body';

            var $filters = $('.car-filter-' + filterClass + '[data-group="' + group + '"]');
            if (!$filters.length) return;

            // Build lookup of available values with counts
            var available = {};
            if (items) {
                items.forEach(function(item) {
                    available[String(item[valueKey])] = item;
                });
            }

            var state = CarFilters.getState(group);
            var isMultiSelect = (filterType === 'fuel_type' || filterType === 'body_type');
            var currentValue, currentValues;

            if (filterType === 'make' || filterType === 'model') {
                currentValue = String(state[filterType].value);
                currentValues = currentValue ? [currentValue] : [];
            } else {
                currentValue = String(state[filterType] || '');
                currentValues = currentValue ? currentValue.split(',') : [];
            }

            $filters.each(function() {
                var $dropdown = $(this).find('.car-filter-dropdown');
                var $options = $dropdown.find('.car-filter-dropdown-option');
                var unavailableSelected = [];

                $options.each(function() {
                    var $opt = $(this);
                    var val = String($opt.data('value'));

                    if (val === '' || val === 'undefined') {
                        // "All" option — always visible
                        return;
                    }

                    if (available[val]) {
                        $opt.removeClass('hidden cascade-hidden');
                        // Update count
                        var $count = $opt.find('.car-filter-count');
                        if ($count.length) {
                            $count.text('(' + available[val].count + ')');
                        } else {
                            $opt.append('<span class="car-filter-count">(' + available[val].count + ')</span>');
                        }
                    } else {
                        $opt.addClass('hidden cascade-hidden');
                        if (isMultiSelect) {
                            if (currentValues.indexOf(val) !== -1) {
                                unavailableSelected.push(val);
                                $opt.removeClass('selected');
                            }
                        } else {
                            if (val === currentValue) {
                                unavailableSelected.push(val);
                            }
                        }
                    }
                });

                // Auto-clear unavailable selections
                if (unavailableSelected.length > 0) {
                    if (isMultiSelect) {
                        // Remove unavailable values from selection
                        var remaining = currentValues.filter(function(v) {
                            return unavailableSelected.indexOf(v) === -1;
                        });
                        var newValue = remaining.join(',');
                        var $text = $dropdown.find('.car-filter-dropdown-text');

                        if (remaining.length === 0) {
                            $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
                            $dropdown.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
                            $text.addClass('placeholder').text(placeholder);
                        } else if (remaining.length === 1) {
                            var $sel = $dropdown.find('.car-filter-dropdown-option.selected[data-value!=""]').first();
                            var singleLabel = $sel.clone().children('.car-filter-count').remove().end().text().trim();
                            $text.removeClass('placeholder').text(singleLabel);
                        } else {
                            $text.removeClass('placeholder').text(remaining.length + ' selected');
                        }

                        CarFilters.setState(group, filterType, newValue);
                    } else {
                        var $allOption = $dropdown.find('.car-filter-dropdown-option[data-value=""]');
                        $dropdown.find('.car-filter-dropdown-option').removeClass('selected');
                        $allOption.addClass('selected');
                        $dropdown.find('.car-filter-dropdown-text').addClass('placeholder').text(placeholder);
                        $dropdown.find('select').val('');

                        if (filterType === 'make' || filterType === 'model') {
                            CarFilters.setState(group, filterType, '', '');
                        } else {
                            CarFilters.setState(group, filterType, '');
                        }
                    }
                }
            });
        },

        /**
         * Full rebuild for model dropdown since models are dynamically loaded.
         */
        rebuildModelDropdown: function(group, models) {
            var $modelFilters = $('.car-filter-model[data-group="' + group + '"]');

            $modelFilters.each(function() {
                var $filter = $(this);
                var $dropdown = $filter.find('.car-filter-dropdown');
                var $button = $dropdown.find('.car-filter-dropdown-button');
                var $options = $dropdown.find('.car-filter-dropdown-options');
                var $select = $dropdown.find('select');
                var $search = $dropdown.find('.car-filter-dropdown-search');

                // Clear loading animation if present
                var loadingInterval = $dropdown.data('loadingInterval');
                if (loadingInterval) {
                    clearInterval(loadingInterval);
                    $dropdown.removeData('loadingInterval');
                }

                var state = CarFilters.getState(group);
                var currentModelValue = String(state.model.value);

                var html = '<button type="button" class="car-filter-dropdown-option selected" role="option" data-value="" data-slug="">All Models</button>';
                var selectHtml = '<option value="">All Models</option>';
                var selectedModelExists = false;

                models.forEach(function(model) {
                    var isSelected = String(model.term_id) === currentModelValue;
                    if (isSelected) selectedModelExists = true;

                    html += '<button type="button" class="car-filter-dropdown-option' + (isSelected ? ' selected' : '') + '" role="option" data-value="' + model.term_id + '" data-slug="' + model.slug + '">' +
                        model.name +
                        '<span class="car-filter-count">(' + model.count + ')</span>' +
                        '</button>';
                    selectHtml += '<option value="' + model.term_id + '" data-slug="' + model.slug + '"' + (isSelected ? ' selected' : '') + '>' + model.name + ' (' + model.count + ')</option>';
                });

                html += '<div class="car-filter-no-results hidden">No matching results</div>';

                $options.html(html);
                $select.html(selectHtml).prop('disabled', false);
                $button.prop('disabled', false).removeClass('car-filter-dropdown-disabled');
                $dropdown.removeClass('car-filter-dropdown-disabled');
                $search.prop('disabled', false);

                if (selectedModelExists) {
                    var $selectedOpt = $options.find('.car-filter-dropdown-option.selected[data-value!=""]');
                    var modelName = $selectedOpt.clone().children('.car-filter-count').remove().end().text().trim();
                    $button.find('.car-filter-dropdown-text').removeClass('placeholder').text(modelName);
                    $select.val(currentModelValue);
                    // Deselect "All"
                    $options.find('.car-filter-dropdown-option[data-value=""]').removeClass('selected');
                } else {
                    $button.find('.car-filter-dropdown-text').addClass('placeholder').text('Select Model');

                    // Clear model if it was set but no longer available
                    if (currentModelValue) {
                        CarFilters.setState(group, 'model', '', '');
                    }
                }
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

    var DomStateSeeder = {
        seed: function() {
            $('.car-filter').each(function() {
                var $filter = $(this);
                var group = $filter.data('group');
                var filterType = $filter.data('filter-type');

                if (!group || !filterType) {
                    return;
                }

                var state = CarFilters.getState(group);

                if (filterType === 'make' || filterType === 'model') {
                    var $selectedOption = $filter.find('select option:selected');
                    state[filterType] = {
                        value: $selectedOption.val() || '',
                        slug: $selectedOption.data('slug') || ''
                    };
                    return;
                }

                if (filterType === 'fuel_type' || filterType === 'body_type') {
                    var values = [];
                    $filter.find('.car-filter-dropdown-option.selected').each(function() {
                        var value = $(this).data('value');
                        if (value !== '' && value !== undefined && value !== null) {
                            values.push(String(value));
                        }
                    });
                    state[filterType] = values.join(',');
                    return;
                }

                if (filterType === 'price' || filterType === 'mileage' || filterType === 'year') {
                    state[filterType + '_min'] = $filter.find('.car-filter-input-min').val() || '';
                    state[filterType + '_max'] = $filter.find('.car-filter-input-max').val() || '';
                }
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
        CascadeController.init();
        SearchButtonController.init();

        // Initialize groups from DOM
        $('.car-filters-container, .car-filter').each(function() {
            var group = $(this).data('group');
            var mode = $(this).data('mode');
            var target = $(this).data('target');
            var redirectUrl = $(this).data('redirect-url');
            var resultsBaseUrl = $(this).data('results-base-url');
            var landingMakeSlug = $(this).data('landing-make-slug');
            var landingModelSlug = $(this).data('landing-model-slug');

            if (group) {
                var state = CarFilters.initGroup(group);
                if (mode) state.mode = mode;
                if (target) state.target = target;
                if (redirectUrl) state.redirectUrl = redirectUrl;
                if (resultsBaseUrl) state.resultsBaseUrl = resultsBaseUrl;
                if (landingMakeSlug) state.landingMakeSlug = landingMakeSlug;
                if (landingModelSlug) state.landingModelSlug = landingModelSlug;
            }
        });

        DomStateSeeder.seed();

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

                                            // Trigger filter after model is set
                                            CarFilters.triggerFilter(group);
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

                                    // Trigger filter after make is set (only if no model to wait for)
                                    if (!data.model) {
                                        CarFilters.triggerFilter(group);
                                    }
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

                // Update multi-select filter dropdown UIs (fuel_type, body_type)
                ['fuel_type', 'body_type'].forEach(function(key) {
                    if (parsedUrl[key]) {
                        var filterClass = key === 'fuel_type' ? 'fuel' : 'body';
                        var values = parsedUrl[key].split(',');
                        var $dropdowns = $('.car-filter-' + filterClass + '[data-group="' + group + '"] .car-filter-dropdown');
                        $dropdowns.each(function() {
                            var $dropdown = $(this);
                            // Deselect "All" option
                            $dropdown.find('.car-filter-dropdown-option[data-value=""]').removeClass('selected');

                            var matchCount = 0;
                            var lastMatchLabel = '';
                            values.forEach(function(val) {
                                var $option = $dropdown.find('.car-filter-dropdown-option').filter(function() {
                                    return $(this).data('slug') === val || String($(this).data('value')) === val;
                                });
                                if ($option.length) {
                                    $option.addClass('selected');
                                    matchCount++;
                                    lastMatchLabel = $option.clone().children('.car-filter-count').remove().end().text().trim();
                                }
                            });

                            var $text = $dropdown.find('.car-filter-dropdown-text');
                            if (matchCount === 1) {
                                $text.removeClass('placeholder').text(lastMatchLabel);
                            } else if (matchCount > 1) {
                                $text.removeClass('placeholder').text(matchCount + ' selected');
                            }
                        });
                    }
                });
            });

            // Trigger filter for non-make params (price, mileage, year, fuel, body)
            if (!parsedUrl.makeSlug) {
                $('.car-filters-container').each(function() {
                    var group = $(this).data('group');
                    if (group) {
                        CarFilters.triggerFilter(group);
                    }
                });
            }
        }

        // Fire initial cascade fetch for each group to sync dropdown options
        $('.car-filters-container').each(function() {
            var group = $(this).data('group');
            if (group) {
                CascadeController.fetchAvailableOptions(group);
            }
        });
    });

})(jQuery);
