# Refresh Listing System

## Overview

The Refresh Listing System allows sellers to bump their car listings to the top of search results once every 7 days. This keeps the marketplace feeling active and dynamic by encouraging sellers to return regularly and maintain fresh, visible inventory.

## Features

- ✅ **7-Day Cooldown**: Sellers can refresh listings once every 7 days
- ✅ **Smart Visibility**: Updates the listing's "last modified" date to appear in "recently added" sections
- ✅ **Visual Feedback**: Shows countdown timer until next refresh is available
- ✅ **Tracking**: Maintains refresh count and history for each listing
- ✅ **Conditional Display**: Only available for published, unsold listings
- ✅ **Security**: Full nonce verification and user ownership validation
- ✅ **User Experience**: AJAX-powered with loading states and success/error messages

## Architecture

The system follows object-oriented principles with clear separation of concerns:

### Core Components

1. **RefreshListingManager.php** (Business Logic)
   - Manages refresh eligibility checks
   - Handles cooldown period calculations
   - Performs listing refresh operations
   - Tracks refresh history and counts

2. **RefreshListingAjaxHandler.php** (AJAX Operations)
   - Handles AJAX requests securely
   - Validates user permissions
   - Coordinates with manager for operations
   - Returns formatted JSON responses

3. **RefreshListingUI.php** (Frontend Display)
   - Renders refresh buttons with states
   - Displays refresh status badges
   - Shows countdown timers
   - Provides info tooltips

4. **refresh-listing.js** (Client-Side Logic)
   - Handles button click interactions
   - Manages AJAX requests
   - Updates UI dynamically
   - Shows loading states and messages

5. **refresh-listing.css** (Styling)
   - Button states (normal, disabled, loading)
   - Responsive design
   - Visual feedback animations
   - Status badges and tooltips

6. **init.php** (Initialization)
   - Bootstraps the system
   - Registers hooks
   - Initializes components

## File Structure

```
includes/user-manage-listings/refresh-listing/
├── RefreshListingManager.php          # Business logic
├── RefreshListingAjaxHandler.php      # AJAX handler
├── RefreshListingUI.php               # UI components
├── refresh-listing.js                 # JavaScript
├── refresh-listing.css                # Styles
├── init.php                           # Initialization
└── README.md                          # Documentation
```

## How It Works

### 1. Cooldown Management

```php
// Check if listing can be refreshed
$can_refresh = $refresh_manager->can_refresh($post_id);

// Get time until next refresh
$time_remaining = $refresh_manager->get_time_until_refresh($post_id);
```

### 2. Refresh Operation

When a seller clicks "Refresh Listing":

1. JavaScript sends AJAX request with car ID and nonce
2. AJAX handler verifies security and user ownership
3. Manager checks cooldown period eligibility
4. Post's `post_modified` date is updated to current time
5. Last refresh timestamp is stored in post meta
6. Refresh count is incremented
7. Success response updates UI

### 3. Display Logic

Refresh button is shown when:
- ✅ Listing status is "publish"
- ✅ Listing is NOT marked as sold
- ✅ User owns the listing

Button states:
- **Available**: Green button "Refresh Listing"
- **Cooldown**: Gray button "Available in X days"
- **Loading**: Blue button with spinner

## Meta Data

The system stores two custom post meta fields:

- `last_refresh_date`: MySQL timestamp of last refresh
- `refresh_count`: Total number of times refreshed

## Integration Points

### In functions.php
```php
require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/init.php';
```

### In my-listings.php
```php
// Initialize components
$refresh_manager = new RefreshListingManager();
$refresh_ui = new RefreshListingUI($refresh_manager);

// Display refresh button
echo $refresh_ui->render_refresh_button($post_id);

// Display refresh status
echo $refresh_ui->render_refresh_status($post_id);
```

## Security Features

1. **Nonce Verification**: All AJAX requests verified with WordPress nonces
2. **User Ownership**: Validates user owns the listing before refresh
3. **Post Status Check**: Only published listings can be refreshed
4. **Cooldown Enforcement**: Server-side validation prevents bypassing
5. **Input Sanitization**: All inputs sanitized and validated

## Customization

### Change Cooldown Period

In `RefreshListingManager.php`:
```php
const REFRESH_COOLDOWN_DAYS = 7; // Change to desired days
```

### Modify Button Text

In `RefreshListingUI.php`, update the `render_refresh_button()` method.

### Change Button Colors

In `refresh-listing.css`, modify the `.refresh-button` styles.

## Benefits

### For Sellers
- Maintain visibility without creating new listings
- Simple one-click refresh action
- Clear feedback on when next refresh is available
- Track refresh history

### For Marketplace
- Keeps listings fresh and active
- Encourages seller engagement
- Signals consistent activity to buyers
- Improves search engine perception
- No artificial inflation of listing counts

### For SEO
- Regular content updates
- Fresh "last modified" dates
- Active marketplace signals
- Better indexing frequency

## Testing

### Manual Testing Checklist

1. ✅ Published, unsold listing shows refresh button
2. ✅ Sold listing does NOT show refresh button
3. ✅ Pending listing does NOT show refresh button
4. ✅ Refresh updates listing's modified date
5. ✅ Cooldown period is enforced
6. ✅ Countdown timer displays correctly
7. ✅ Non-owner cannot refresh other's listings
8. ✅ AJAX errors handled gracefully
9. ✅ Loading states display properly
10. ✅ Success/error messages appear

### Test Scenarios

**Test Fresh Listing:**
```php
// Should show "Refresh Listing" button immediately
```

**Test After Refresh:**
```php
// Should show "Available in 7 days"
// Should increment refresh count
```

**Test After 7 Days:**
```php
// Should show "Refresh Listing" button again
```

## Troubleshooting

### Refresh Button Not Showing

1. Check listing status (must be "publish")
2. Check `is_sold` field (must be false/0)
3. Verify user ownership
4. Check for JavaScript errors in console

### AJAX Request Failing

1. Verify nonce creation in localized script
2. Check AJAX URL is correct
3. Ensure AJAX handler is registered
4. Check server error logs

### Cooldown Not Working

1. Verify `last_refresh_date` meta is saved
2. Check timezone settings
3. Ensure cooldown calculation is correct

## Future Enhancements

Potential improvements:

- [ ] Email notification when refresh available
- [ ] Automatic refresh scheduling
- [ ] Admin dashboard widget showing refresh stats
- [ ] Bulk refresh for multiple listings
- [ ] Custom cooldown per user role
- [ ] Refresh credits system
- [ ] Analytics tracking for refresh impact

## Version History

**v1.0.0** (October 2025)
- Initial release
- 7-day cooldown system
- AJAX-powered interface
- Full security implementation
- Responsive design
- Refresh tracking

## Support

For issues or questions:
1. Check this README
2. Review code comments
3. Check WordPress debug logs
4. Test in staging environment first

## License

Part of AutoAgora/Bricks Child Theme

