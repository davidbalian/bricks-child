# Seller Verification Shortcode

This shortcode displays a verification badge for users with the "dealership" role.

## Usage

```php
[dealership_verified user_id="123"]
```

## Parameters

- `user_id` (required): The ID of the user to check for dealership verification

## Examples

### In Bricks Builder
```php
[dealership_verified user_id="{author_id}"]
```

### In PHP Templates
```php
echo do_shortcode('[dealership_verified user_id="' . get_the_author_meta('ID') . '"]');
```

### In Car Listing Templates
```php
// Next to author name
<?php echo get_the_author(); ?>
[dealership_verified user_id="{author_id}"]
```

## Styling

The shortcode uses the following CSS classes:
- `.seller-verification-badge` - Main container
- `.verification-tick` - SVG tick icon
- `.verification-text` - "Verified Dealership" text

### Style Variations

Default style (green gradient):
```php
[dealership_verified user_id="123"]
```

Minimal style (light background):
```css
.seller-verification-badge.minimal {
    /* Light background with green text */
}
```

## How It Works

1. Takes a user ID parameter
2. Checks if the user has the "dealership" role
3. If yes, displays a green verification badge with tick icon
4. If no, returns empty string (nothing displayed)

## Files

- `seller-verification.php` - Main shortcode functionality
- `seller-verification.css` - Styling for the verification badge
- `README.md` - This documentation file

## Requirements

- WordPress user with "dealership" role
- User ID must be valid and exist in the database 