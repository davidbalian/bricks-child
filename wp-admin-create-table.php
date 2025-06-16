<?php
/**
 * ALTERNATIVE: Create Car Views Table via WordPress Admin
 * 
 * INSTRUCTIONS:
 * 1. Copy this ENTIRE code
 * 2. Go to WordPress Admin → Appearance → Theme Editor
 * 3. Select "functions.php" 
 * 4. Add this code at the BOTTOM of functions.php
 * 5. Visit any admin page (Dashboard, Posts, etc.)
 * 6. Look for green success message at top
 * 7. REMOVE this code from functions.php immediately after
 */

// One-time table creation via admin
add_action('admin_notices', function() {
    // Only run once
    if (get_option('car_views_table_created') === 'yes') {
        return;
    }
    
    // Create table
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_views';
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
    dbDelta($sql);
    
    // Mark as created
    update_option('car_views_table_created', 'yes');
    
    // Show success message
    echo '<div class="notice notice-success"><p><strong>✅ Car Views Table Created Successfully!</strong> You can now remove this code from functions.php</p></div>';
});

// END OF CODE TO COPY
?> 