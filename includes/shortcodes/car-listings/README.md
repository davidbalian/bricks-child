# Car Listings Shortcode Documentation

A robust, customizable shortcode for displaying car listings anywhere on your WordPress site.

---

## Basic Usage

```
[car_listings]
```

This displays a grid of the 12 most recent car listings.

---

## Attributes

### Layout Options

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `layout` | `grid`, `carousel`, `vertical` | `grid` | Controls the display layout |

**Layout Details:**

- **grid** - Responsive CSS grid using `repeat(auto-fill, minmax(300px, 1fr))`. Cards automatically adjust to fill available width.
- **carousel** - Horizontal scrolling slider with scroll-snap. Cards are 300px wide.
- **vertical** - Single column layout, cards stacked one below another.

```
[car_listings layout="grid"]
[car_listings layout="carousel"]
[car_listings layout="vertical"]
```

---

### Pagination & Loading

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `posts_per_page` | Any number | `12` | Number of listings to display |
| `infinite_scroll` | `true`, `false` | `false` | Load more listings when scrolling |
| `offset` | Any number | `0` | Skip the first N listings |

```
[car_listings posts_per_page="8"]
[car_listings posts_per_page="6" infinite_scroll="true"]
[car_listings offset="3" posts_per_page="9"]
```

**Infinite Scroll Notes:**
- Uses IntersectionObserver for performance
- Automatically loads more when user scrolls near the bottom
- Shows a loading spinner while fetching
- Stops when all listings are loaded

---

### Filtering by Source

These attributes control which listings are shown. They can be combined.

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `featured` | `true`, `false` | `false` | Only show featured listings (ACF `is_featured` = true) |
| `favorites` | `true`, `false` | `false` | Only show current user's favorite listings |
| `user_id` | User ID number | - | Only show listings by a specific user |
| `author` | `current` | - | Only show current logged-in user's listings |
| `show_sold` | `true`, `false` | `false` | Include sold listings (hidden by default) |

```
[car_listings featured="true"]
[car_listings favorites="true"]
[car_listings user_id="123"]
[car_listings author="current"]
[car_listings show_sold="true"]
```

**Combining Filters:**

Filters can be combined for more specific results:

```
[car_listings featured="true" user_id="123"]
[car_listings favorites="true" show_sold="true"]
```

---

### Sorting

**Important:** Featured listings (`is_featured` = true) always appear first, regardless of sort order. The `orderby` attribute controls the secondary sort within featured and non-featured groups.

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `orderby` | `date`, `price`, `mileage`, `year` | `date` | Secondary sort field (after featured) |
| `order` | `DESC`, `ASC` | `DESC` | Sort direction |

```
[car_listings orderby="price" order="ASC"]
[car_listings orderby="year" order="DESC"]
[car_listings orderby="mileage" order="ASC"]
```

**Example Result Order:**
1. Featured car A (newest)
2. Featured car B (older)
3. Regular car C (newest)
4. Regular car D (older)

---

## Complete Examples

### Homepage Featured Cars Carousel

```
[car_listings layout="carousel" featured="true" posts_per_page="8"]
```

### All Listings Page with Infinite Scroll

```
[car_listings posts_per_page="12" infinite_scroll="true"]
```

### User's Favorites Page

```
[car_listings favorites="true" infinite_scroll="true"]
```

### User Dashboard - My Listings

```
[car_listings author="current" layout="vertical" show_sold="true"]
```

### Cheapest Cars First

```
[car_listings orderby="price" order="ASC" posts_per_page="20"]
```

### Newest Cars from Specific Dealer

```
[car_listings user_id="456" orderby="date" order="DESC"]
```

### Low Mileage Cars

```
[car_listings orderby="mileage" order="ASC" posts_per_page="10"]
```

### Featured Cars Grid (excluding sold)

```
[car_listings featured="true" posts_per_page="6" layout="grid"]
```

---

## Card Display

Each car card displays:

- **Image** - Featured image or first image from gallery
- **Title** - Make + Model (e.g., "Toyota Camry")
- **Meta** - Year | Mileage (e.g., "2020 | 45,000 km")
- **Price** - Formatted with Euro symbol (e.g., "€25,000")
- **Location** - City name
- **Sold Badge** - Red "SOLD" badge if applicable
- **Favorite Button** - Heart icon to save/unsave (uses existing `[favorite_button]` shortcode)

---

## CSS Classes

The shortcode outputs semantic HTML with BEM-style classes for easy styling:

### Container Classes

```css
.car-listings-container        /* Main wrapper */
.car-listings-grid             /* Added when layout="grid" */
.car-listings-carousel         /* Added when layout="carousel" */
.car-listings-vertical         /* Added when layout="vertical" */
.car-listings-wrapper          /* Contains all cards */
.car-listings-loader           /* Infinite scroll loader */
.car-listings-no-results       /* Empty state message */
```

### Card Classes

```css
.car-listings-card             /* Individual card */
.car-listings-card.is-sold     /* Card for sold listing */
.car-listings-card-link        /* Clickable link wrapper */
.car-listings-card-image       /* Image container */
.car-listings-card-content     /* Text content area */
.car-listings-card-title       /* Make + Model heading */
.car-listings-card-meta        /* Year and mileage */
.car-listings-card-price       /* Price display */
.car-listings-card-location    /* City name */
.car-listings-sold-badge       /* "SOLD" badge */
.car-listings-no-image         /* Placeholder when no image */
```

### Customization Example

```css
/* Change card background */
.car-listings-card {
    background: #f8f9fa;
}

/* Style the price */
.car-listings-card-price {
    color: #28a745;
    font-size: 24px;
}

/* Adjust grid gap */
.car-listings-grid .car-listings-wrapper {
    gap: 32px;
}

/* Custom carousel card width */
.car-listings-carousel .car-listings-card {
    flex: 0 0 350px;
}
```

---

## Data Attributes

The container element includes data attributes for JavaScript interaction:

```html
<div class="car-listings-container"
     data-layout="grid"
     data-infinite-scroll="true"
     data-page="1"
     data-max-pages="5"
     data-atts="{...}">
```

Each card includes the post ID:

```html
<article class="car-listings-card" data-post-id="123">
```

---

## Empty States

When no listings match the criteria, a simple message is displayed:

```
No car listings found.
```

This can be styled via `.car-listings-no-results`.

---

## Technical Notes

### Performance

- Assets (CSS/JS) are only loaded when the shortcode is present on a page
- Images use `loading="lazy"` for native lazy loading
- Infinite scroll uses IntersectionObserver (with scroll fallback for older browsers)

### Security

- All AJAX requests use nonce verification
- User inputs are sanitized with `intval()`, `sanitize_text_field()`, etc.
- Output is escaped with `esc_html()`, `esc_attr()`, `esc_url()`

### Dependencies

- jQuery (for AJAX and infinite scroll)
- Font Awesome (for favorite button icon)
- Existing `[favorite_button]` shortcode

### File Locations

```
/includes/shortcodes/car-listings/
    car-listings.php    # Main shortcode logic
    car-listings.css    # Styles
    car-listings.js     # Infinite scroll JS
    README.md           # This documentation
```

---

## Troubleshooting

### Listings not showing

1. Check that you have published cars with `post_type = 'car'`
2. If using `featured="true"`, ensure some cars have `is_featured` ACF field checked
3. If using `favorites="true"`, ensure user is logged in and has favorites saved

### Infinite scroll not working

1. Ensure `infinite_scroll="true"` is set
2. Check browser console for JavaScript errors
3. Verify there are more pages to load (check `data-max-pages` attribute)

### Styles not loading

1. Clear any caching plugins
2. Check that the CSS file exists at the correct path
3. Inspect the page source for the `car-listings-shortcode` stylesheet

### Favorites not appearing

- User must be logged in
- Favorites are stored in user meta `favorite_cars` as an array of post IDs
