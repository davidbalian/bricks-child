<?php
/**
 * Car Views Database Setup
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your website root
 * 2. Visit: yoursite.com/setup-car-views.php
 * 3. Delete this file after running
 */

// Get database config from wp-config.php
require_once('wp-config.php');

// Connect to database directly
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// Get table prefix
$table_prefix = isset($table_prefix) ? $table_prefix : 'wp_';
$table_name = $table_prefix . 'car_views';

// Check if table exists
$result = $mysqli->query("SHOW TABLES LIKE '$table_name'");
$table_exists = $result && $result->num_rows > 0;

if ($table_exists) {
    $status = 'exists';
    $message = 'Table already exists - no action needed';
} else {
    // Create table
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
    ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($mysqli->query($sql)) {
        // Verify creation
        $result = $mysqli->query("SHOW TABLES LIKE '$table_name'");
        $table_exists_now = $result && $result->num_rows > 0;
        
        if ($table_exists_now) {
            $status = 'created';
            $message = 'Table created successfully';
        } else {
            $status = 'error';
            $message = 'Table creation verified failed';
        }
    } else {
        $status = 'error';
        $message = 'SQL Error: ' . $mysqli->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Car Views Setup</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; padding: 20px; border-radius: 5px; color: #155724; }
        .exists { background: #cce5ff; padding: 20px; border-radius: 5px; color: #004085; }
        .error { background: #f8d7da; padding: 20px; border-radius: 5px; color: #721c24; }
        .warning { background: #fff3cd; padding: 20px; border-radius: 5px; color: #856404; }
        code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    
    <?php if ($status === 'created'): ?>
        <div class="success">
            <h2>✅ SUCCESS!</h2>
            <p><strong>Car views table created successfully!</strong></p>
        </div>
    <?php elseif ($status === 'exists'): ?>
        <div class="exists">
            <h2>ℹ️ TABLE EXISTS</h2>
            <p><strong>Car views table already exists - no action needed.</strong></p>
        </div>
    <?php else: ?>
        <div class="error">
            <h2>❌ ERROR</h2>
            <p><strong>Failed to create table.</strong> Check database permissions.</p>
        </div>
    <?php endif; ?>
    
    <h3>Database Info:</h3>
    <ul>
        <li><strong>Table name:</strong> <code><?php echo $table_name; ?></code></li>
        <li><strong>Status:</strong> <?php echo $message; ?></li>
        <li><strong>Database:</strong> <?php echo defined('DB_NAME') ? DB_NAME : 'Connected'; ?></li>
    </ul>
    
    <?php if ($status === 'created' || $status === 'exists'): ?>
        <div class="warning">
            <h3>🗑️ IMPORTANT</h3>
            <p><strong>Delete this file now:</strong> <code>setup-car-views.php</code></p>
            <p>Your car views system is ready to use!</p>
        </div>
        
        <h3>✅ Next Steps:</h3>
        <ol>
            <li>Delete this file: <code>setup-car-views.php</code></li>
            <li>Add <code>[car_views_counter]</code> to your Bricks template</li>
            <li>Test on a car page with <code>?car_id=####</code></li>
        </ol>
    <?php endif; ?>
    
</body>
</html> 