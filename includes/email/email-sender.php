<?php
/**
 * Application Email Sender (Resend)
 * 
 * Provider-agnostic wrapper around the email sending service.
 * Currently implemented using Resend HTTP API.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get email sender configuration
 * 
 * Uses constants that should be defined in wp-config.php or a similar config:
 * - RESEND_API_KEY
 * - RESEND_FROM_EMAIL
 * - RESEND_FROM_NAME
 * 
 * @return array|false
 */
function get_email_sender_config() {
    $api_key    = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
    $from_email = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : '';
    $from_name  = defined('RESEND_FROM_NAME') ? RESEND_FROM_NAME : '';

    if (empty($api_key) || empty($from_email) || empty($from_name)) {
        error_log('Email sender configuration is missing (Resend).');
        return false;
    }

    return array(
        'api_key'    => $api_key,
        'from_email' => $from_email,
        'from_name'  => $from_name,
    );
}

/**
 * Send an email using the configured provider (Resend)
 * 
 * @param string $to_email
 * @param string $subject
 * @param string $html_content
 * @param string $text_content
 * @return bool
 */
function send_app_email($to_email, $subject, $html_content, $text_content = '') {
    $config = get_email_sender_config();

    if (!$config) {
        return false;
    }

    // Build "from" header/name
    $from = sprintf('%s <%s>', $config['from_name'], $config['from_email']);

    // Prepare payload for Resend HTTP API
    $body = array(
        'from'    => $from,
        'to'      => array($to_email),
        'subject' => $subject,
        'html'    => $html_content,
    );

    if (!empty($text_content)) {
        $body['text'] = $text_content;
    }

    $response = wp_remote_post(
        'https://api.resend.com/emails',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        )
    );

    if (is_wp_error($response)) {
        error_log('Resend send error: ' . $response->get_error_message());
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($status_code >= 200 && $status_code < 300) {
        error_log('Resend email sent successfully to: ' . $to_email);
        return true;
    }

    error_log('Resend error: ' . $status_code . ' - ' . $response_body);
    return false;
}


