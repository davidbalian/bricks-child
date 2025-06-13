<?php
/**
 * SendGrid Test File - Remove this after testing
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load SendGrid configuration
require_once get_stylesheet_directory() . '/includes/email/sendgrid-config.php';

/**
 * Test SendGrid functionality
 * Add ?test_sendgrid=1 to any page URL to test
 */
function test_sendgrid_email() {
    if (isset($_GET['test_sendgrid']) && $_GET['test_sendgrid'] == '1') {
        // Only allow admins to test
        if (!current_user_can('administrator')) {
            wp_die('Access denied.');
        }

        $test_email = 'lkotidis43@gmail.com'; // Send to your email instead of admin
        $subject = 'SendGrid Test Email from AutoAgora';
        $html_content = '
        <html>
        <body>
            <h2>SendGrid Test Successful!</h2>
            <p>This email was sent from your AutoAgora website using SendGrid.</p>
            <p><strong>Configuration is working correctly.</strong></p>
            <p>You can now proceed with email verification setup.</p>
        </body>
        </html>';
        
        $text_content = 'SendGrid Test Successful! Configuration is working correctly.';

        $result = send_sendgrid_email($test_email, $subject, $html_content, $text_content);

        if ($result) {
            echo '<div style="background: green; color: white; padding: 20px; margin: 20px;">';
            echo '<h3>✅ SUCCESS!</h3>';
            echo '<p>Test email sent successfully to: ' . esc_html($test_email) . '</p>';
            echo '<p>Check your email inbox (and spam folder).</p>';
            echo '</div>';
        } else {
            echo '<div style="background: red; color: white; padding: 20px; margin: 20px;">';
            echo '<h3>❌ FAILED!</h3>';
            echo '<p>Email could not be sent. Check your SendGrid configuration.</p>';
            echo '</div>';
        }

        exit; // Stop page loading after test
    }
}
add_action('init', 'test_sendgrid_email'); 