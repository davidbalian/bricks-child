<?php
/**
 * Legal Pages Functionality
 * 
 * Handles Terms of Service and Privacy Policy pages
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AutoAgora_Legal_Pages {
    
    public function __construct() {
        // Only handle styling - pages already exist
        add_action('wp_enqueue_scripts', array($this, 'enqueue_legal_styles'));
    }
    
    /**
     * Get Terms of Service content
     */
    private function get_terms_of_service_content() {
        return '
        <div class="legal-content">
            <div class="legal-header">
                <h1>Terms of Service</h1>
                <p class="last-updated">Last updated: ' . date('F j, Y') . '</p>
            </div>
            
            <div class="legal-section">
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing and using AutoAgora ("the Service"), you accept and agree to be bound by the terms and provision of this agreement.</p>
            </div>
            
            <div class="legal-section">
                <h2>2. Description of Service</h2>
                <p>AutoAgora is an online platform that facilitates the buying and selling of vehicles. We provide a marketplace where users can list, browse, and connect regarding vehicle transactions.</p>
            </div>
            
            <div class="legal-section">
                <h2>3. User Responsibilities</h2>
                <h3>3.1 Account Registration</h3>
                <p>Users must provide accurate and complete information when creating an account and maintain the confidentiality of their login credentials.</p>
                
                <h3>3.2 Listing Requirements</h3>
                <p>Users posting vehicle listings must:</p>
                <ul>
                    <li>Provide accurate vehicle information</li>
                    <li>Use genuine photographs</li>
                    <li>Comply with all applicable laws</li>
                    <li>Not engage in fraudulent activities</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>4. Prohibited Activities</h2>
                <p>Users are prohibited from:</p>
                <ul>
                    <li>Posting false or misleading information</li>
                    <li>Using the service for illegal activities</li>
                    <li>Harassing other users</li>
                    <li>Attempting to circumvent security measures</li>
                    <li>Posting spam or unsolicited communications</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>5. Transaction Responsibility</h2>
                <p>AutoAgora facilitates connections between buyers and sellers but is not a party to any transactions. Users are solely responsible for:</p>
                <ul>
                    <li>Verifying vehicle condition and information</li>
                    <li>Negotiating terms and prices</li>
                    <li>Completing legal documentation</li>
                    <li>Payment arrangements</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>6. Intellectual Property</h2>
                <p>The AutoAgora platform, including its design, features, and content, is protected by copyright and other intellectual property rights. Users may not reproduce, distribute, or create derivative works without permission.</p>
            </div>
            
            <div class="legal-section">
                <h2>7. Privacy</h2>
                <p>Your privacy is important to us. Please review our <a href="/privacy-policy">Privacy Policy</a> to understand how we collect, use, and protect your information.</p>
            </div>
            
            <div class="legal-section">
                <h2>8. Disclaimers</h2>
                <p>AutoAgora provides the service "as is" without warranties of any kind. We do not guarantee:</p>
                <ul>
                    <li>The accuracy of vehicle listings</li>
                    <li>The reliability of users</li>
                    <li>Uninterrupted service availability</li>
                    <li>The outcome of any transactions</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>9. Limitation of Liability</h2>
                <p>AutoAgora shall not be liable for any direct, indirect, incidental, or consequential damages arising from the use of our service or any transactions conducted through our platform.</p>
            </div>
            
            <div class="legal-section">
                <h2>10. Termination</h2>
                <p>We reserve the right to terminate or suspend accounts that violate these terms or engage in prohibited activities.</p>
            </div>
            
            <div class="legal-section">
                <h2>11. Changes to Terms</h2>
                <p>We may update these terms from time to time. Continued use of the service after changes constitutes acceptance of the new terms.</p>
            </div>
            
            <div class="legal-section">
                <h2>12. Contact Information</h2>
                <p>For questions about these Terms of Service, please contact us at: <a href="mailto:legal@autoagora.com">legal@autoagora.com</a></p>
            </div>
        </div>';
    }
    
    /**
     * Get Privacy Policy content
     */
    private function get_privacy_policy_content() {
        return '
        <div class="legal-content">
            <div class="legal-header">
                <h1>Privacy Policy</h1>
                <p class="last-updated">Last updated: ' . date('F j, Y') . '</p>
            </div>
            
            <div class="legal-section">
                <h2>1. Information We Collect</h2>
                <h3>1.1 Personal Information</h3>
                <p>We collect information you provide directly to us, such as:</p>
                <ul>
                    <li>Name and contact information</li>
                    <li>Account credentials</li>
                    <li>Vehicle listing information</li>
                    <li>Communication preferences</li>
                </ul>
                
                <h3>1.2 Automatically Collected Information</h3>
                <p>We automatically collect certain information when you use our service:</p>
                <ul>
                    <li>Device information and IP address</li>
                    <li>Browser type and version</li>
                    <li>Usage patterns and preferences</li>
                    <li>Location data (with permission)</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>2. How We Use Your Information</h2>
                <p>We use collected information to:</p>
                <ul>
                    <li>Provide and improve our services</li>
                    <li>Process vehicle listings and transactions</li>
                    <li>Communicate with users</li>
                    <li>Ensure platform security</li>
                    <li>Comply with legal obligations</li>
                    <li>Send relevant updates and notifications</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>3. Information Sharing</h2>
                <p>We may share your information in the following circumstances:</p>
                <ul>
                    <li><strong>With other users:</strong> Profile and listing information is visible to other users</li>
                    <li><strong>Service providers:</strong> Third-party services that help us operate our platform</li>
                    <li><strong>Legal compliance:</strong> When required by law or to protect rights and safety</li>
                    <li><strong>Business transfers:</strong> In connection with mergers or acquisitions</li>
                </ul>
                
                <p><strong>We do not sell your personal information to third parties.</strong></p>
            </div>
            
            <div class="legal-section">
                <h2>4. Cookies and Tracking</h2>
                <p>We use cookies and similar technologies to:</p>
                <ul>
                    <li>Remember your preferences</li>
                    <li>Analyze site usage</li>
                    <li>Improve user experience</li>
                    <li>Provide personalized content</li>
                </ul>
                
                <p>You can control cookie settings through your browser, but some features may not function properly if cookies are disabled.</p>
            </div>
            
            <div class="legal-section">
                <h2>5. Data Security</h2>
                <p>We implement appropriate security measures to protect your information, including:</p>
                <ul>
                    <li>Encryption of sensitive data</li>
                    <li>Secure server infrastructure</li>
                    <li>Regular security audits</li>
                    <li>Access controls for our staff</li>
                </ul>
                
                <p>However, no system is completely secure, and we cannot guarantee absolute security.</p>
            </div>
            
            <div class="legal-section">
                <h2>6. Your Rights and Choices</h2>
                <p>You have the right to:</p>
                <ul>
                    <li>Access and update your personal information</li>
                    <li>Delete your account and associated data</li>
                    <li>Opt out of marketing communications</li>
                    <li>Request data portability</li>
                    <li>Object to certain data processing</li>
                </ul>
                
                <p>To exercise these rights, please contact us using the information provided below.</p>
            </div>
            
            <div class="legal-section">
                <h2>7. Data Retention</h2>
                <p>We retain your information for as long as necessary to provide our services and comply with legal obligations. Deleted accounts may be retained in anonymized form for analytical purposes.</p>
            </div>
            
            <div class="legal-section">
                <h2>8. Third-Party Services</h2>
                <p>Our platform may integrate with third-party services (such as payment processors, map services, or social media platforms). These services have their own privacy policies, and we encourage you to review them.</p>
            </div>
            
            <div class="legal-section">
                <h2>9. Children\'s Privacy</h2>
                <p>Our service is not intended for children under 16. We do not knowingly collect personal information from children under 16. If you believe we have collected such information, please contact us immediately.</p>
            </div>
            
            <div class="legal-section">
                <h2>10. International Data Transfers</h2>
                <p>Your information may be processed and stored in countries other than your own. We ensure appropriate safeguards are in place for international data transfers.</p>
            </div>
            
            <div class="legal-section">
                <h2>11. Changes to This Privacy Policy</h2>
                <p>We may update this Privacy Policy from time to time. We will notify you of significant changes through the platform or via email.</p>
            </div>
            
            <div class="legal-section">
                <h2>12. Contact Us</h2>
                <p>If you have questions about this Privacy Policy or our data practices, please contact us at:</p>
                <ul>
                    <li>Email: <a href="mailto:privacy@autoagora.com">privacy@autoagora.com</a></li>
                    <li>Address: [Your Business Address]</li>
                </ul>
            </div>
        </div>';
    }
    
    /**
     * Enqueue legal page styles
     */
    public function enqueue_legal_styles() {
        if (is_page('terms-of-service') || is_page('privacy-policy')) {
            wp_enqueue_style(
                'legal-pages-style',
                get_stylesheet_directory_uri() . '/includes/legal/legal-pages.css',
                array(),
                ASTRA_CHILD_THEME_VERSION
            );
        }
    }
}

// Initialize the class
new AutoAgora_Legal_Pages();