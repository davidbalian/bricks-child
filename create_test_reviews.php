<?php
/**
 * TEST SCRIPT: Create Sample Reviews
 * 
 * This script creates multiple test reviews for a seller to test the review system.
 * Run this ONCE, then delete this file.
 * 
 * Usage: Place in root directory and visit: yoursite.com/create_test_reviews.php
 */

// Load WordPress
require_once('./wp-config.php');
require_once('./wp-load.php');
require_once('./includes/reviews/seller-reviews-database.php');

// Security check - only run if you're an admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an admin to run this script.');
}

// Configuration
$seller_id = 43; // CHANGE THIS TO YOUR TEST SELLER'S USER ID
$num_reviews = 15; // Number of reviews to create

echo "<h1>Creating Test Reviews</h1>";

// Sample review data
$sample_reviews = array(
    array('rating' => 5, 'comment' => 'Excellent service! Very professional and responsive.', 'contacted' => true),
    array('rating' => 4, 'comment' => 'Good experience overall. Quick to respond to messages.', 'contacted' => true),
    array('rating' => 5, 'comment' => 'Highly recommend! Car was exactly as described.', 'contacted' => true),
    array('rating' => 3, 'comment' => 'Average experience. Car was okay but could have been cleaner.', 'contacted' => false),
    array('rating' => 5, 'comment' => 'Amazing seller! Very honest and fair pricing.', 'contacted' => true),
    array('rating' => 4, 'comment' => 'Professional service. Would buy from again.', 'contacted' => true),
    array('rating' => 5, 'comment' => 'Perfect transaction. No issues at all!', 'contacted' => true),
    array('rating' => 2, 'comment' => 'Had some issues with communication but eventually resolved.', 'contacted' => true),
    array('rating' => 4, 'comment' => 'Good seller, car was as advertised.', 'contacted' => false),
    array('rating' => 5, 'comment' => 'Excellent! Very knowledgeable about cars.', 'contacted' => true),
    array('rating' => 4, 'comment' => 'Smooth transaction, reliable seller.', 'contacted' => true),
    array('rating' => 5, 'comment' => 'Outstanding service! Highly professional.', 'contacted' => true),
    array('rating' => 3, 'comment' => 'Decent experience. Nothing special but no major issues.', 'contacted' => false),
    array('rating' => 5, 'comment' => 'Best car buying experience ever! Thank you!', 'contacted' => true),
    array('rating' => 4, 'comment' => 'Very helpful and patient with all my questions.', 'contacted' => true),
);

// Sample reviewer names for realistic display
$sample_users = array(
    array('first_name' => 'Maria', 'last_name' => 'Georgiou', 'email' => 'maria.test@example.com'),
    array('first_name' => 'Andreas', 'last_name' => 'Christou', 'email' => 'andreas.test@example.com'),
    array('first_name' => 'Elena', 'last_name' => 'Dimitriou', 'email' => 'elena.test@example.com'),
    array('first_name' => 'Costas', 'last_name' => 'Loizou', 'email' => 'costas.test@example.com'),
    array('first_name' => 'Sofia', 'last_name' => 'Ioannou', 'email' => 'sofia.test@example.com'),
    array('first_name' => 'Michalis', 'last_name' => 'Antoniou', 'email' => 'michalis.test@example.com'),
    array('first_name' => 'Christina', 'last_name' => 'Pavlou', 'email' => 'christina.test@example.com'),
    array('first_name' => 'George', 'last_name' => 'Constantinou', 'email' => 'george.test@example.com'),
    array('first_name' => 'Anna', 'last_name' => 'Charalambous', 'email' => 'anna.test@example.com'),
    array('first_name' => 'Nikos', 'last_name' => 'Nicolaou', 'email' => 'nikos.test@example.com'),
    array('first_name' => 'Despina', 'last_name' => 'Panayiotou', 'email' => 'despina.test@example.com'),
    array('first_name' => 'Petros', 'last_name' => 'Stylianou', 'email' => 'petros.test@example.com'),
    array('first_name' => 'Katerina', 'last_name' => 'Theodorou', 'email' => 'katerina.test@example.com'),
    array('first_name' => 'Dimitris', 'last_name' => 'Karpasitis', 'email' => 'dimitris.test@example.com'),
    array('first_name' => 'Ioanna', 'last_name' => 'Hadjicosta', 'email' => 'ioanna.test@example.com'),
);

// Check if seller exists
if (!get_userdata($seller_id)) {
    die("Error: Seller with ID {$seller_id} not found. Please change the \$seller_id variable.");
}

echo "<p>Creating reviews for seller ID: {$seller_id}</p>";

$reviews_db = new SellerReviewsDatabase();
$created_count = 0;
$created_users = array();

for ($i = 0; $i < $num_reviews; $i++) {
    // Create or get test user
    $user_data = $sample_users[$i % count($sample_users)];
    $username = 'test_' . strtolower($user_data['first_name']) . '_' . $i;
    
    // Check if user already exists
    $user = get_user_by('login', $username);
    
    if (!$user) {
        // Create new test user
        $user_id = wp_create_user(
            $username,
            'testpassword123',
            $user_data['email']
        );
        
        if (is_wp_error($user_id)) {
            echo "<p>Error creating user {$username}: " . $user_id->get_error_message() . "</p>";
            continue;
        }
        
        // Set user meta for proper name display
        update_user_meta($user_id, 'first_name', $user_data['first_name']);
        update_user_meta($user_id, 'last_name', $user_data['last_name']);
        update_user_meta($user_id, 'email_verified', '1'); // Mark as verified
        
        $created_users[] = $username;
        echo "<p>Created test user: {$username} ({$user_data['first_name']} {$user_data['last_name']})</p>";
    } else {
        $user_id = $user->ID;
        // Make sure existing user is verified
        update_user_meta($user_id, 'email_verified', '1');
    }
    
    // Get review data
    $review_data = $sample_reviews[$i % count($sample_reviews)];
    
    // Submit review
    $result = $reviews_db->submit_review(
        $seller_id,
        $user_id,
        $review_data['rating'],
        $review_data['comment'],
        $review_data['contacted']
    );
    
    if ($result['success']) {
        // Auto-approve the review for testing
        global $wpdb;
        $review_id = $wpdb->insert_id;
        $reviews_db->approve_review($review_id);
        
        $created_count++;
        echo "<p>✅ Created and approved review #{$created_count} - {$review_data['rating']} stars by {$user_data['first_name']}</p>";
    } else {
        echo "<p>❌ Error creating review: {$result['message']}</p>";
    }
    
    // Add small delay to make review dates more realistic
    if ($i < $num_reviews - 1) {
        sleep(1);
    }
}

echo "<hr>";
echo "<h2>✅ Test Complete!</h2>";
echo "<p><strong>Created {$created_count} reviews for seller ID {$seller_id}</strong></p>";

if (!empty($created_users)) {
    echo "<h3>Test Users Created:</h3>";
    echo "<ul>";
    foreach ($created_users as $username) {
        echo "<li>{$username} (password: testpassword123)</li>";
    }
    echo "</ul>";
}

echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Visit your seller profile page to see the reviews</li>";
echo "<li>Check the admin dashboard to see the reviews</li>";
echo "<li>Test the review overlay and pagination</li>";
echo "<li><strong>DELETE THIS FILE when done testing!</strong></li>";
echo "</ul>";

echo "<hr>";
echo "<p><em>Remember to delete this file (create_test_reviews.php) when you're finished testing!</em></p>";
?> 