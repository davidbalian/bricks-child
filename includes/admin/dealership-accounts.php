<?php
/**
 * Simple Dealership Account Creation System
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create a dealership account - EXACT same process as normal registration
 */
function create_dealership_account($phone_number, $dealership_name, $password) {
    // Validate required fields
    if (empty($phone_number) || empty($dealership_name) || empty($password)) {
        return new WP_Error('missing_fields', 'Phone number, dealership name, and password are required.');
    }
    
    // Check if phone exists (EXACT same check as normal registration)
    $user_by_phone = get_users(array(
        'meta_key' => 'phone_number',
        'meta_value' => $phone_number,
        'number' => 1,
        'count_total' => false,
    ));
    if (!empty($user_by_phone)) {
        return new WP_Error('phone_exists', 'This phone number is already registered.');
    }
    
    // Use whole dealership name as first name (no splitting)
    $first_name = trim($dealership_name);
    $last_name = ''; // Leave last name empty
    
    // --- EXACT same user creation as normal registration ---
    $username = sanitize_user($phone_number);
    if (username_exists($username)) {
        $username = sanitize_user('user_' . $phone_number . '_' . wp_rand(100, 999));
    }
    $email = 'phone_user_' . time() . '@example.com'; // Placeholder email
    
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        return $user_id;
    }
    
    // EXACT same meta update as normal registration
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $last_name);
    update_user_meta($user_id, 'phone_number', $phone_number); // This is KEY!
    
    // Set role and display name
    wp_update_user(array(
        'ID' => $user_id, 
        'role' => 'dealership',
        'display_name' => $dealership_name // Set display name to full dealership name
    ));
    
    return array(
        'user_id' => $user_id,
        'username' => $username,
        'password' => $password,
        'phone_number' => $phone_number,
        'dealership_name' => $dealership_name
    );
}



/**
 * Add dealership admin menu
 */
function add_dealership_admin_menu() {
    add_menu_page(
        'Dealerships',
        'Dealerships',
        'manage_options',
        'dealerships',
        'dealership_admin_page',
        'dashicons-groups',
        30
    );
}
add_action('admin_menu', 'add_dealership_admin_menu');

/**
 * Dealership admin page
 */
function dealership_admin_page() {
    // Handle form submission
    if (isset($_POST['action']) && $_POST['action'] === 'create_dealership' && wp_verify_nonce($_POST['dealership_nonce'], 'create_dealership')) {
        handle_create_dealership_form();
    }
    
    // Get existing dealerships
    $dealerships = get_users(array('role' => 'dealership'));
    
    ?>
    <div class="wrap">
        <h1>Dealership Management</h1>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['message'])); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="create-dealership-section">
            <h2>Create New Dealership Account</h2>
            <form method="post" action="">
                <?php wp_nonce_field('create_dealership', 'dealership_nonce'); ?>
                <input type="hidden" name="action" value="create_dealership">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="phone_number">Phone Number *</label></th>
                        <td><input type="tel" id="phone_number" name="phone_number" class="regular-text" placeholder="+353871234567" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dealership_name">Dealership Name *</label></th>
                        <td>
                            <input type="text" id="dealership_name" name="dealership_name" class="regular-text" placeholder="Dublin Auto Sales" required>
                            <p class="description">Business name that will be used as the account name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="password">Password *</label></th>
                        <td>
                            <input type="text" id="password" name="password" class="regular-text" required>
                            <p class="description">Create a password for the dealership account.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Create Dealership Account'); ?>
            </form>
        </div>
        
        <div class="existing-dealerships">
            <h2>Existing Dealerships</h2>
            <?php if (empty($dealerships)): ?>
                <p>No dealerships found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Dealership Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dealerships as $dealership): ?>
                                                 <tr>
                             <td><?php echo esc_html($dealership->first_name . ' ' . $dealership->last_name); ?></td>
                             <td><?php echo esc_html(get_user_meta($dealership->ID, 'phone_number', true)); ?></td>
                             <td><?php echo esc_html($dealership->user_email); ?></td>
                             <td><?php echo esc_html(date('Y-m-d', strtotime($dealership->user_registered))); ?></td>
                             <td>
                                 <a href="<?php echo get_edit_user_link($dealership->ID); ?>" class="button button-small">Edit User</a>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Handle create dealership form submission
 */
function handle_create_dealership_form() {
    $phone_number = sanitize_text_field($_POST['phone_number']);
    $dealership_name = sanitize_text_field($_POST['dealership_name']);
    $password = sanitize_text_field($_POST['password']);
    
    $result = create_dealership_account($phone_number, $dealership_name, $password);
    
    if (is_wp_error($result)) {
        $error = urlencode($result->get_error_message());
        wp_redirect(admin_url("admin.php?page=dealerships&error=$error"));
    } else {
        $message = "Dealership account created! Username: {$result['username']}, Password: {$result['password']}, Phone: {$result['phone_number']}";
        $message = urlencode($message);
        wp_redirect(admin_url("admin.php?page=dealerships&message=$message"));
    }
    exit;
} 