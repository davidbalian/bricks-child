<?php
/**
 * Cookie Consent Banner
 * 
 * GDPR compliant cookie consent functionality
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AutoAgora_Cookie_Consent {
    
    public function __construct() {
        add_action('wp_footer', array($this, 'display_cookie_banner'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_set_cookie_consent', array($this, 'handle_consent_ajax'));
        add_action('wp_ajax_nopriv_set_cookie_consent', array($this, 'handle_consent_ajax'));
    }
    
    /**
     * Display cookie consent banner
     */
    public function display_cookie_banner() {
        // Don't show banner if user has already given consent
        if (isset($_COOKIE['autoagora_cookie_consent']) && $_COOKIE['autoagora_cookie_consent'] === 'accepted') {
            return;
        }
        
        ?>
        <div id="cookie-consent-banner" class="cookie-consent-banner">
            <div class="cookie-consent-content">
                <div class="cookie-consent-text">
                    <h3>🍪 We use cookies</h3>
                    <p>We use cookies to enhance your browsing experience, analyze site traffic, and personalize content. By continuing to use our site, you consent to our use of cookies.</p>
                    <p><a href="/privacy-policy" target="_blank">Learn more in our Privacy Policy</a></p>
                </div>
                <div class="cookie-consent-actions">
                    <button id="accept-all-cookies" class="cookie-btn cookie-btn-accept">Accept All</button>
                    <button id="accept-necessary-cookies" class="cookie-btn cookie-btn-necessary">Necessary Only</button>
                    <button id="customize-cookies" class="cookie-btn cookie-btn-customize">Customize</button>
                </div>
            </div>
        </div>
        
        <!-- Cookie Customization Modal -->
        <div id="cookie-customize-modal" class="cookie-modal" style="display: none;">
            <div class="cookie-modal-content">
                <div class="cookie-modal-header">
                    <h3>Cookie Preferences</h3>
                    <button class="cookie-modal-close">&times;</button>
                </div>
                <div class="cookie-modal-body">
                    <div class="cookie-category">
                        <div class="cookie-category-header">
                            <h4>Necessary Cookies</h4>
                            <span class="cookie-always-on">Always On</span>
                        </div>
                        <p>These cookies are essential for the website to function properly. They enable core functionality such as security, network management, and accessibility.</p>
                    </div>
                    
                    <div class="cookie-category">
                        <div class="cookie-category-header">
                            <h4>Analytics Cookies</h4>
                            <label class="cookie-toggle">
                                <input type="checkbox" id="analytics-cookies" checked>
                                <span class="cookie-slider"></span>
                            </label>
                        </div>
                        <p>These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.</p>
                    </div>
                    
                    <div class="cookie-category">
                        <div class="cookie-category-header">
                            <h4>Marketing Cookies</h4>
                            <label class="cookie-toggle">
                                <input type="checkbox" id="marketing-cookies" checked>
                                <span class="cookie-slider"></span>
                            </label>
                        </div>
                        <p>These cookies track your online activity to help advertisers deliver more relevant advertising or to limit how many times you see an ad.</p>
                    </div>
                    
                    <div class="cookie-category">
                        <div class="cookie-category-header">
                            <h4>Functional Cookies</h4>
                            <label class="cookie-toggle">
                                <input type="checkbox" id="functional-cookies" checked>
                                <span class="cookie-slider"></span>
                            </label>
                        </div>
                        <p>These cookies enable enhanced functionality and personalization, such as remembering your preferences and settings.</p>
                    </div>
                </div>
                <div class="cookie-modal-footer">
                    <button id="save-cookie-preferences" class="cookie-btn cookie-btn-accept">Save Preferences</button>
                    <button id="accept-all-modal" class="cookie-btn cookie-btn-secondary">Accept All</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'cookie-consent-style',
            get_stylesheet_directory_uri() . '/includes/legal/cookie-consent.css',
            array(),
            BRICKS_CHILD_THEME_VERSION
        );
        
        wp_enqueue_script(
            'cookie-consent-script',
            get_stylesheet_directory_uri() . '/includes/legal/cookie-consent.js',
            array('jquery'),
            BRICKS_CHILD_THEME_VERSION,
            true
        );
        
        wp_localize_script('cookie-consent-script', 'cookieConsentAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cookie_consent_nonce'),
            'action' => 'set_cookie_consent'
        ));
    }
    
    /**
     * Handle AJAX consent submission
     */
    public function handle_consent_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cookie_consent_nonce')) {
            wp_die('Security check failed');
        }
        
        $consent_type = sanitize_text_field($_POST['consent_type']);
        $preferences = array();
        
        if ($consent_type === 'customize') {
            $preferences = array(
                'necessary' => true, // Always true
                'analytics' => isset($_POST['analytics']) ? (bool)$_POST['analytics'] : false,
                'marketing' => isset($_POST['marketing']) ? (bool)$_POST['marketing'] : false,
                'functional' => isset($_POST['functional']) ? (bool)$_POST['functional'] : false
            );
        } elseif ($consent_type === 'all') {
            $preferences = array(
                'necessary' => true,
                'analytics' => true,
                'marketing' => true,
                'functional' => true
            );
        } else {
            $preferences = array(
                'necessary' => true,
                'analytics' => false,
                'marketing' => false,
                'functional' => false
            );
        }
        
        // Set cookie (expires in 1 year)
        setcookie('autoagora_cookie_consent', 'accepted', time() + (365 * 24 * 60 * 60), '/');
        setcookie('autoagora_cookie_preferences', json_encode($preferences), time() + (365 * 24 * 60 * 60), '/');
        
        wp_send_json_success(array(
            'message' => 'Cookie preferences saved successfully',
            'preferences' => $preferences
        ));
    }
    
    /**
     * Check if specific cookie type is allowed
     */
    public static function is_cookie_allowed($type) {
        if (!isset($_COOKIE['autoagora_cookie_preferences'])) {
            return false;
        }
        
        $preferences = json_decode($_COOKIE['autoagora_cookie_preferences'], true);
        return isset($preferences[$type]) ? $preferences[$type] : false;
    }
    
    /**
     * Get cookie preferences
     */
    public static function get_cookie_preferences() {
        if (!isset($_COOKIE['autoagora_cookie_preferences'])) {
            return array(
                'necessary' => true,
                'analytics' => false,
                'marketing' => false,
                'functional' => false
            );
        }
        
        return json_decode($_COOKIE['autoagora_cookie_preferences'], true);
    }
}

// Initialize the class
new AutoAgora_Cookie_Consent(); 