<?php
/**
 * ONE-TIME Database Table Creation for Car Views
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your WordPress root directory
 * 2. Visit: yoursite.com/create-views-table.php
 * 3. See "SUCCESS" message
 * 4. DELETE THIS FILE immediately after
 * 
 * This is a one-time setup script, not permanent code.
 */

// Load WordPress properly
define('WP_USE_THEMES', false);
require_once('wp-load.php');

// Security check - only run if not already done
if (get_option('car_views_table_created') === 'yes') {
    die('✅ Table already exists! Delete this file.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'car_views';

// Create the table
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    car_id bigint(20) unsigned NOT NULL,
    user_ip_hash varchar(64) NOT NULL,
    user_id bigint(20) unsigned DEFAULT 0,
    view_date datetime DEFAULT CURRENT_TIMESTAMP,
    user_agent_hash varchar(64) NOT NULL,
    PRIMARY KEY (id),
    KEY car_id (car_id),
    KEY user_ip_hash (user_ip_hash),
    KEY view_date (view_date),
    UNIQUE KEY unique_view (car_id, user_ip_hash, user_agent_hash, DATE(view_date))
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$result = dbDelta($sql);

// Mark as created
update_option('car_views_table_created', 'yes');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Car Views Table Creation</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb; }
        .warning { background: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeaa7; }
        code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="success">
        <h2>✅ SUCCESS!</h2>
        <p><strong>Car views table created successfully!</strong></p>
        
        <h3>Table Details:</h3>
        <ul>
            <li><strong>Table name:</strong> <code><?php echo $table_name; ?></code></li>
            <li><strong>Columns:</strong> id, car_id, user_ip_hash, user_id, view_date, user_agent_hash</li>
            <li><strong>Status:</strong> Ready for tracking views</li>
        </ul>
        
        <h3>🗑️ IMPORTANT - Delete This File Now!</h3>
        <div class="warning">
            <p><strong>Security:</strong> Delete <code>create-views-table.php</code> from your website immediately!</p>
            <p>This file should only be run once and then removed.</p>
        </div>
        
        <h3>✅ Next Steps:</h3>
        <ol>
            <li>Delete this file: <code>create-views-table.php</code></li>
            <li>Add <code>[car_views_counter]</code> shortcode to your Bricks template</li>
            <li>Test view tracking on your car pages</li>
        </ol>
    </div>
</body>
</html> 