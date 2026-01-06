# Car Filters Shortcode Documentation

A modular filter system for car listings. Use individual filter shortcodes anywhere on the page, or combine them with the unified `[car_filters]` shortcode.

---

## Quick Start

### Combined Shortcode (Recommended)

```
[car_filters filters="make,model,price,mileage" mode="ajax" target="my-listings"]
[car_listings id="my-listings" filter_group="main"]
```

### Individual Shortcodes

```
[car_filter_make group="main"]
[car_filter_model group="main"]
[car_filter_price group="main"]
[car_filter_mileage group="main"]
```

---

## Available Filter Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[car_filter_make]` | Dropdown for car brands (parent terms of `car_make` taxonomy) |
| `[car_filter_model]` | Dropdown for models (loads dynamically based on selected make) |
| `[car_filter_price]` | Min/max price range inputs |
| `[car_filter_mileage]` | Min/max mileage range inputs |
| `[car_filter_year]` | Min/max year range inputs |
| `[car_filter_fuel]` | Dropdown for fuel types |
| `[car_filter_body]` | Dropdown for body types |

---

## Combined Shortcode: `[car_filters]`

Renders multiple filters together with optional search button.

### Attributes

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `filters` | Comma-separated list | `make,model,price,mileage` | Which filters to display |
| `group` | Any string | `default` | Group name for state sync |
| `mode` | `ajax`, `redirect` | `ajax` | Filter behavior mode |
| `target` | Element ID | - | Target `[car_listings]` ID (for AJAX mode) |
| `redirect_url` | URL path | `/cars/` | Destination URL (for redirect mode) |
| `layout` | `horizontal`, `vertical`, `inline` | `horizontal` | Filter layout style |
| `show_button` | `true`, `false` | `true` | Show search button |
| `button_text` | Any string | `Search Cars` | Button label |

### Examples

**Homepage Search (redirect to results page):**
```
[car_filters filters="make,model,price,mileage" mode="redirect" redirect_url="/cars/"]
```

**Listings Page (AJAX filtering):**
```
[car_filters filters="make,model,price,mileage,year" mode="ajax" target="car-grid" layout="horizontal"]
[car_listings id="car-grid" filter_group="main"]
```

**Sidebar Filters (vertical layout):**
```
[car_filters filters="make,model,price,mileage,fuel,body" layout="vertical" mode="ajax" target="listings"]
```

**All Filters:**
```
[car_filters filters="make,model,price,mileage,year,fuel,body"]
```

---

## Individual Filter Attributes

### Common Attributes (all filters)

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `group` | Any string | `default` | Group name for state sync between filters |
| `mode` | `ajax`, `redirect` | `ajax` | Filter behavior |
| `target` | Element ID | - | Target listings ID |
| `redirect_url` | URL path | `/cars/` | Redirect destination |
| `label` | Any string | Varies | Label text above filter |

### Make Filter: `[car_filter_make]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `placeholder` | Any string | `All Brands` | Dropdown placeholder |
| `show_count` | `true`, `false` | `true` | Show car count per make |
| `show_popular` | `true`, `false` | `true` | Show "Most Popular" section |

```
[car_filter_make group="main" placeholder="Select Brand" show_popular="true"]
```

### Model Filter: `[car_filter_model]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `placeholder` | Any string | `All Models` | Dropdown placeholder |
| `show_count` | `true`, `false` | `true` | Show car count per model |

```
[car_filter_model group="main" placeholder="Select Model"]
```

**Note:** Model dropdown is disabled until a make is selected. Models load via AJAX.

### Price Filter: `[car_filter_price]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `min_placeholder` | Any string | `From` | Min input placeholder |
| `max_placeholder` | Any string | `To` | Max input placeholder |
| `currency` | Any string | `€` | Currency symbol in label |

```
[car_filter_price group="main" currency="$" label="Budget"]
```

### Mileage Filter: `[car_filter_mileage]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `min_placeholder` | Any string | `From` | Min input placeholder |
| `max_placeholder` | Any string | `To` | Max input placeholder |
| `unit` | Any string | `km` | Unit label (km, miles) |

```
[car_filter_mileage group="main" unit="miles"]
```

### Year Filter: `[car_filter_year]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `min_placeholder` | Any string | `From` | Min input placeholder |
| `max_placeholder` | Any string | `To` | Max input placeholder |

```
[car_filter_year group="main" label="Year Range"]
```

### Fuel Type Filter: `[car_filter_fuel]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `placeholder` | Any string | `All Fuel Types` | Dropdown placeholder |
| `show_count` | `true`, `false` | `true` | Show car count per type |

```
[car_filter_fuel group="main" placeholder="Any Fuel"]
```

### Body Type Filter: `[car_filter_body]`

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `placeholder` | Any string | `All Body Types` | Dropdown placeholder |
| `show_count` | `true`, `false` | `true` | Show car count per type |

```
[car_filter_body group="main" placeholder="Any Body Style"]
```

---

## Filter Modes

### AJAX Mode (`mode="ajax"`)

Filters update the car listings on the same page without reload.

- Requires `target` attribute pointing to a `[car_listings]` ID
- URL updates via pushState (bookmarkable)
- Debounced requests (300ms) for performance

```
[car_filters mode="ajax" target="my-listings"]
[car_listings id="my-listings"]
```

### Redirect Mode (`mode="redirect"`)

Filters build a URL and navigate to a results page.

- Used for homepage search → results page flow
- URL format: `/cars/?make=bmw&model=3-series&price_min=5000&price_max=50000`

```
[car_filters mode="redirect" redirect_url="/cars/"]
```

---

## State Synchronization

Filters with the same `group` attribute automatically share state:

- Selecting BMW in one make dropdown updates all model dropdowns in the same group
- Works across the page, even if filters are in different locations
- Each group maintains independent state

```
<!-- Sidebar -->
[car_filter_make group="search"]
[car_filter_model group="search"]

<!-- Different location on page -->
[car_filter_price group="search"]

<!-- All three filters share state because group="search" -->
```

---

## Integration with Car Listings

### Linking Filters to Listings

Use the `target` attribute on filters and `id` attribute on listings:

```
[car_filters target="featured-cars" mode="ajax"]
[car_listings id="featured-cars"]
```

Or use `filter_group` for automatic connection:

```
[car_filters group="main" mode="ajax"]
[car_listings filter_group="main"]
```

### URL Parameter Support

The `[car_listings]` shortcode automatically reads URL parameters:

| Parameter | Example | Description |
|-----------|---------|-------------|
| `make` | `?make=bmw` | Filter by make slug |
| `model` | `?model=3-series` | Filter by model slug |
| `price_min` | `?price_min=5000` | Minimum price |
| `price_max` | `?price_max=50000` | Maximum price |
| `mileage_min` | `?mileage_min=0` | Minimum mileage |
| `mileage_max` | `?mileage_max=100000` | Maximum mileage |
| `year_min` | `?year_min=2020` | Minimum year |
| `year_max` | `?year_max=2024` | Maximum year |
| `fuel_type` | `?fuel_type=diesel` | Fuel type |
| `body_type` | `?body_type=suv` | Body type |

---

## Complete Examples

### Homepage Hero Search

```
[car_filters
  filters="make,model,price,mileage"
  mode="redirect"
  redirect_url="/cars/"
  layout="horizontal"
  button_text="Find Cars"
]
```

### Results Page with Sidebar Filters

```
<!-- Sidebar -->
<div class="sidebar">
  [car_filters
    filters="make,model,price,mileage,year,fuel,body"
    layout="vertical"
    mode="ajax"
    target="results"
    show_button="true"
  ]
</div>

<!-- Main Content -->
<div class="content">
  [car_listings id="results" posts_per_page="12" infinite_scroll="true"]
</div>
```

### Individual Filters in Custom Layout

```
<div class="filter-row">
  [car_filter_make group="custom" label="Brand"]
  [car_filter_model group="custom" label="Model"]
</div>
<div class="filter-row">
  [car_filter_price group="custom" label="Price Range"]
  [car_filter_year group="custom" label="Year"]
</div>
<button class="car-filters-search-btn" data-group="custom">Search</button>

[car_listings filter_group="custom"]
```

---

## CSS Classes

### Container Classes

```css
.car-filters-container          /* Main wrapper */
.car-filters-horizontal         /* Horizontal layout */
.car-filters-vertical           /* Vertical layout */
.car-filters-inline             /* Inline layout */
.car-filters-wrapper            /* Contains filter items */
.car-filters-item               /* Individual filter wrapper */
.car-filters-button-wrapper     /* Search button wrapper */
.car-filters-search-btn         /* Search button */
```

### Filter Classes

```css
.car-filter                     /* Individual filter */
.car-filter-make                /* Make filter specific */
.car-filter-model               /* Model filter specific */
.car-filter-price               /* Price filter specific */
.car-filter-label               /* Filter label */
```

### Dropdown Classes

```css
.car-filter-dropdown            /* Dropdown wrapper */
.car-filter-dropdown.open       /* Open state */
.car-filter-dropdown-button     /* Dropdown trigger */
.car-filter-dropdown-menu       /* Dropdown panel */
.car-filter-dropdown-search     /* Search input */
.car-filter-dropdown-options    /* Options container */
.car-filter-dropdown-option     /* Individual option */
.car-filter-dropdown-option.selected  /* Selected option */
.car-filter-section-header      /* "Most Popular" header */
.car-filter-count               /* Count badge */
```

### Range Input Classes

```css
.car-filter-range               /* Range filter wrapper */
.car-filter-range-inputs        /* Inputs container */
.car-filter-input-wrapper       /* Single input wrapper */
.car-filter-input-label         /* Input label (From/To) */
.car-filter-input               /* Text input */
```

---

## Technical Notes

### Performance

- Assets only load when shortcode is present
- AJAX requests are debounced (300ms)
- Meta data cached for 5 minutes
- Make/model counts use optimized SQL queries

### State Management

The JavaScript `CarFilters` object manages state:

```javascript
CarFilters.getState('group')           // Get current filter values
CarFilters.setState('group', 'make', value, slug)  // Update state
CarFilters.subscribe('group', callback) // Listen for changes
CarFilters.triggerFilter('group')       // Execute filter
```

### File Locations

```
/includes/shortcodes/car-filters/
    car-filters.php         # Main loader & combined shortcode
    car-filters.css         # Styles
    car-filters.js          # State manager & UI
    README.md               # This documentation
    filters/
        filter-base.php     # Shared functions
        filter-make.php     # Make shortcode
        filter-model.php    # Model shortcode
        filter-price.php    # Price shortcode
        filter-mileage.php  # Mileage shortcode
        filter-year.php     # Year shortcode
        filter-fuel.php     # Fuel type shortcode
        filter-body.php     # Body type shortcode
```

---

## Troubleshooting

### Filters not affecting listings

1. Ensure `target` attribute matches `[car_listings]` ID
2. Or use matching `group`/`filter_group` attributes
3. Check browser console for JavaScript errors

### Model dropdown stays disabled

1. Make sure a make is selected first
2. Check that models exist for the selected make
3. Verify AJAX is working (check Network tab)

### URL not updating

1. Confirm `mode="ajax"` is set
2. Check browser console for errors
3. Verify JavaScript is loaded

### Infinite scroll ignores filters

1. Filters are passed via URL parameters
2. Make sure you're using the latest version
3. Check that filters are in the URL when scrolling

### Styles don't match homepage

1. Ensure `car-filters.css` is loaded
2. Clear cache if using caching plugin
3. Check for CSS specificity conflicts
