<?php
/**
 * Email Sender Test (Resend)
 * 
 * Temporary test endpoint to verify Resend integration via send_app_email().
 * 
 * Usage: add ?test_email_sender=1 to any page URL while logged in as admin.
 * Example: https://your-site.com/?test_email_sender=1
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load email sender helper
require_once get_stylesheet_directory() . '/includes/email/email-sender.php';

/**
 * Test email sender functionality
 * Add ?test_email_sender=1 to any page URL to test
 */
function test_email_sender() {
    if (!isset($_GET['test_email_sender']) || $_GET['test_email_sender'] !== '1') {
        return;
    }

    // Only allow admins to test
    if (!current_user_can('administrator')) {
        wp_die('Access denied.');
    }

    // Change this to your email if needed
    $test_email    = get_option('leonidaskotidis4@gmail.com');
    $subject       = 'Email Sender Test (Resend) from AutoAgora';
    $html_content  = '
        <html>
        <body>
            <h2>Email Sender Test Successful!</h2>
            <p>This email was sent from your AutoAgora website using the Resend-backed email sender.</p>
            <p><strong>Configuration appears to be working.</strong></p>
            <p>You can now rely on this sender for verification emails.</p>
        </body>
        </html>';

    $text_content = 'Email Sender Test Successful! Configuration appears to be working.';

    $result = send_app_email($test_email, $subject, $html_content, $text_content);

    if ($result) {
        echo '<div style="background: green; color: white; padding: 20px; margin: 20px;">';
        echo '<h3>✅ SUCCESS!</h3>';
        echo '<p>Test email sent successfully to: ' . esc_html($test_email) . '</p>';
        echo '<p>Check your email inbox (and spam folder).</p>';
        echo '</div>';
    } else {
        echo '<div style="background: red; color: white; padding: 20px; margin: 20px;">';
        echo '<h3>❌ FAILED!</h3>';
        echo '<p>Email could not be sent. Check your Resend configuration and logs.</p>';
        echo '</div>';
    }

    exit; // Stop page loading after test
}
add_action('init', 'test_email_sender');


