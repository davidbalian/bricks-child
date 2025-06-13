# Legal Compliance Features

This folder contains all the legal compliance features for AutoAgora, including Terms of Service, Privacy Policy, and Cookie Consent Banner.

## Features Included

### 1. Terms of Service Page
- **URL**: `/terms-of-service`
- **Auto-created**: Yes
- **Template**: `page-legal.php`
- **Content**: Professional terms covering vehicle marketplace operations

### 2. Privacy Policy Page
- **URL**: `/privacy-policy`
- **Auto-created**: Yes
- **Template**: `page-legal.php`
- **Content**: GDPR-compliant privacy policy
- **WordPress Integration**: Automatically sets as site's privacy policy page

### 3. Cookie Consent Banner
- **GDPR Compliant**: Yes
- **Features**:
  - Accept All / Necessary Only / Customize options
  - Granular cookie controls (Analytics, Marketing, Functional)
  - Responsive design
  - Accessibility features
  - AJAX-powered preferences saving

## How It Works

### Automatic Setup
When you activate the theme, the legal pages functionality will:
1. Create Terms of Service page (if it doesn't exist)
2. Create Privacy Policy page (if it doesn't exist)
3. Set Privacy Policy as WordPress default
4. Enable cookie consent banner on all pages

### Cookie Consent Banner
The banner appears at the bottom of the page for new visitors and includes:
- **Accept All**: Enables all cookies
- **Necessary Only**: Only essential cookies
- **Customize**: Opens modal with granular controls

### Customization Options

#### Updating Legal Content
1. Go to WordPress Admin > Pages
2. Edit "Terms of Service" or "Privacy Policy"
3. Update content as needed
4. Save changes

#### Customizing Cookie Categories
Edit `cookie-consent.php` to modify:
- Cookie categories
- Default settings
- Expiration times

#### Styling Customization
Modify these files:
- `legal-pages.css` - Legal page styling
- `cookie-consent.css` - Cookie banner styling

## Developer Usage

### Check Cookie Preferences in JavaScript
```javascript
// Check if analytics cookies are allowed
if (window.AutoAgoraCookies && window.AutoAgoraCookies.isAllowed('analytics')) {
    // Load analytics scripts
    gtag('config', 'GA_MEASUREMENT_ID');
}

// Get all preferences
const preferences = window.AutoAgoraCookies.getPreferences();
```

### Check Cookie Preferences in PHP
```php
// Check if marketing cookies are allowed
if (AutoAgora_Cookie_Consent::is_cookie_allowed('marketing')) {
    // Load marketing scripts
}

// Get all preferences
$preferences = AutoAgora_Cookie_Consent::get_cookie_preferences();
```

### Listen for Cookie Updates
```javascript
$(document).on('cookieConsentUpdated', function(event, preferences) {
    console.log('Cookie preferences updated:', preferences);
    // Reload scripts based on new preferences
});
```

## Legal Compliance Notes

### GDPR Compliance
- ✅ Explicit consent required
- ✅ Granular cookie controls
- ✅ Easy withdrawal of consent
- ✅ Clear privacy policy
- ✅ Data processing transparency

### Cookie Categories
- **Necessary**: Always enabled (session, security)
- **Analytics**: Google Analytics, site statistics
- **Marketing**: Advertising, retargeting
- **Functional**: User preferences, enhanced features

## File Structure
```
includes/legal/
├── legal-pages.php      # Legal pages creation and management
├── legal-pages.css      # Legal pages styling
├── cookie-consent.php   # Cookie consent functionality
├── cookie-consent.css   # Cookie banner styling
├── cookie-consent.js    # Cookie banner JavaScript
└── README.md           # This file
```

## Important Notes

1. **Email Addresses**: Update contact emails in the legal content
2. **Business Address**: Add your actual business address
3. **Regular Updates**: Review and update legal content regularly
4. **Legal Review**: Have legal professionals review content
5. **Testing**: Test cookie functionality across different browsers

## Customization Examples

### Adding New Cookie Category
1. Update `cookie-consent.php` modal HTML
2. Add handling in `cookie-consent.js`
3. Update preference storage logic

### Changing Cookie Expiration
Modify the `setcookie()` calls in `cookie-consent.php`:
```php
// Change from 1 year to 6 months
setcookie('autoagora_cookie_consent', 'accepted', time() + (180 * 24 * 60 * 60), '/');
```

### Custom Legal Page Template
1. Create new template file (e.g., `page-custom-legal.php`)
2. Update page template assignment in `legal-pages.php`
3. Customize layout and styling

This implementation provides a solid foundation for legal compliance while remaining flexible for customization. 