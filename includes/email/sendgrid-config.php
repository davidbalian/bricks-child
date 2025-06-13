<?php
/**
 * SendGrid Email Configuration
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load SendGrid library using its own autoloader
require_once get_stylesheet_directory() . '/vendor/sendgrid/sendgrid/sendgrid-php.php';

use SendGrid\Mail\Mail;

/**
 * Get SendGrid configuration (same pattern as Twilio)
 */
function get_sendgrid_config() {
    $api_key = defined('SENDGRID_API_KEY') ? SENDGRID_API_KEY : '';
    $from_email = defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : '';
    $from_name = defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : '';

    if (empty($api_key) || empty($from_email) || empty($from_name)) {
        error_log('SendGrid configuration is missing.');
        return false;
    }

    return array(
        'api_key' => $api_key,
        'from_email' => $from_email,
        'from_name' => $from_name
    );
}

/**
 * Send email via SendGrid (similar to how Twilio is used)
 */
function send_sendgrid_email($to_email, $subject, $html_content, $text_content = '') {
    $config = get_sendgrid_config();
    
    if (!$config) {
        return false;
    }

    try {
        $email = new Mail();
        $email->setFrom($config['from_email'], $config['from_name']);
        $email->setSubject($subject);
        $email->addTo($to_email);
        $email->addContent("text/html", $html_content);
        
        if (!empty($text_content)) {
            $email->addContent("text/plain", $text_content);
        }

        $sendgrid = new \SendGrid($config['api_key']);
        $response = $sendgrid->send($email);

        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            error_log("SendGrid email sent successfully to: " . $to_email);
            return true;
        } else {
            error_log("SendGrid error: " . $response->statusCode() . " - " . $response->body());
            return false;
        }

    } catch (Exception $e) {
        error_log('SendGrid send error: ' . $e->getMessage());
        return false;
    }
} 