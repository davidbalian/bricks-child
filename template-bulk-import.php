<?php
/**
 * Template Name: Bulk Car Import
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include the bulk import functionality
require_once get_stylesheet_directory() . '/includes/user-manage-listings/bulk-fast-uploads/bulk-car-import.php';

get_header(); ?>

<div class="bricks-container">
    <div class="bricks-content">
        <?php
        if ( is_user_logged_in() ) {
            // Check for import results
            if ( isset( $_GET['import_complete'] ) && $_GET['import_complete'] == '1' ) {
                $imported_count = isset($_GET['imported_count']) ? intval($_GET['imported_count']) : 0;
                $error_count = isset($_GET['error_count']) ? intval($_GET['error_count']) : 0;
                ?>
                <div class="import-results-message">
                    <h2><?php esc_html_e( 'Bulk Import Complete!', 'bricks-child' ); ?></h2>
                    <p><strong><?php echo sprintf(esc_html__('%d cars imported successfully', 'bricks-child'), $imported_count); ?></strong></p>
                    <?php if ($error_count > 0): ?>
                        <p><strong><?php echo sprintf(esc_html__('%d errors occurred', 'bricks-child'), $error_count); ?></strong></p>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['import_errors'])): 
                        $errors = json_decode(urldecode($_GET['import_errors']), true);
                        if (!empty($errors)): ?>
                            <div class="import-errors">
                                <h3><?php esc_html_e('Error Details:', 'bricks-child'); ?></h3>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo esc_html($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <p><a href="<?php echo esc_url( home_url( '/my-listings/' ) ); ?>" class="button"><?php esc_html_e( 'View My Listings', 'bricks-child' ); ?></a></p>
                </div>
                <?php
            } elseif ( isset( $_GET['import_error'] ) ) {
                $error_type = sanitize_text_field($_GET['import_error']);
                ?>
                <div class="import-error-message">
                    <h2><?php esc_html_e( 'Import Error', 'bricks-child' ); ?></h2>
                    <?php
                    switch ($error_type) {
                        case 'nonce_failed':
                            echo '<p>' . esc_html__('Security check failed. Please try again.', 'bricks-child') . '</p>';
                            break;
                        case 'not_logged_in':
                            echo '<p>' . esc_html__('You need to be logged in to import cars.', 'bricks-child') . '</p>';
                            break;
                        case 'file_upload_failed':
                            echo '<p>' . esc_html__('File upload failed. Please check your file and try again.', 'bricks-child') . '</p>';
                            break;
                        case 'invalid_file_type':
                            echo '<p>' . esc_html__('Invalid file type. Please upload a CSV file.', 'bricks-child') . '</p>';
                            break;
                        default:
                            echo '<p>' . esc_html__('An unknown error occurred. Please try again.', 'bricks-child') . '</p>';
                    }
                    ?>
                </div>
                <?php
            }
            ?>
            
            <h1><?php esc_html_e( 'Bulk Car Import', 'bricks-child' ); ?></h1>
            <p class="bulk-import-description"><?php esc_html_e( 'Upload multiple car listings at once using a CSV file. This will save you significant time when adding many cars.', 'bricks-child' ); ?></p>
            
            <div class="bulk-import-steps">
                <h2><?php esc_html_e( 'How to Use Bulk Import', 'bricks-child' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Download the CSV template below', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'Fill in your car data in the spreadsheet (Excel, Google Sheets, etc.)', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'Save as CSV format', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'Upload the file using the form below', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'Review the results and add images to your listings individually if needed', 'bricks-child' ); ?></li>
                </ol>
            </div>
            
            <div class="template-download-section input-wrapper">
                <h2><?php esc_html_e( 'Step 1: Download Template', 'bricks-child' ); ?></h2>
                <p><?php esc_html_e( 'Download the CSV template with all required fields and an example row:', 'bricks-child' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=download_import_template' ) ); ?>" class="button template-download-btn">
                    <?php esc_html_e( 'Download CSV Template', 'bricks-child' ); ?>
                </a>
            </div>
            
            <div class="bulk-import-form-section input-wrapper">
                <h2><?php esc_html_e( 'Step 2: Upload Your CSV File', 'bricks-child' ); ?></h2>
                <form id="bulk-import-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'bulk_car_import_nonce', 'bulk_import_nonce' ); ?>
                    <input type="hidden" name="action" value="bulk_car_import">
                    
                    <div class="file-upload-section">
                        <label for="bulk_import_file"><?php esc_html_e( 'Select CSV File:', 'bricks-child' ); ?></label>
                        <input type="file" id="bulk_import_file" name="bulk_import_file" accept=".csv" required>
                        <p class="file-help-text"><?php esc_html_e( 'Only CSV files are supported. Maximum file size: 10MB', 'bricks-child' ); ?></p>
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="submit-button gradient-button"><?php esc_html_e( 'Import Cars', 'bricks-child' ); ?></button>
                    </div>
                </form>
            </div>
            
            <div class="bulk-import-tips input-wrapper">
                <h2><?php esc_html_e( 'Tips for Best Results', 'bricks-child' ); ?></h2>
                <ul>
                    <li><?php esc_html_e( 'Make sure all required fields are filled in', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'Use exact values from the dropdown options (e.g., "Automatic" not "Auto")', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'For extras and vehicle history, separate multiple items with commas', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'Check your data for typos before uploading', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'All imported cars will be set to "Pending" status for your review', 'bricks-child' ); ?></li>
                    <li><?php esc_html_e( 'You will need to add images to each listing individually after import', 'bricks-child' ); ?></li>
                </ul>
            </div>
            
            <?php
        } else {
            $login_url = wp_login_url( get_permalink() );
            echo '<div class="login-required-message">';
            echo '<h1>' . esc_html__( 'Please Log in to Access Bulk Import', 'bricks-child' ) . '</h1>';
            echo '<p><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log In', 'bricks-child' ) . '</a></p>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<style>
.bulk-import-steps ol {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #007cba;
}

.bulk-import-steps li {
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.template-download-btn {
    background: #28a745 !important;
    color: white !important;
    padding: 12px 24px;
    text-decoration: none !important;
    border-radius: 4px;
    display: inline-block;
    font-weight: 600;
}

.template-download-btn:hover {
    background: #218838 !important;
}

.import-results-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 1.5rem;
    border-radius: 4px;
    margin-bottom: 2rem;
}

.import-error-message {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 1.5rem;
    border-radius: 4px;
    margin-bottom: 2rem;
}

.import-errors {
    margin-top: 1rem;
    background: #fff;
    padding: 1rem;
    border-radius: 4px;
}

.import-errors ul {
    margin: 0;
    padding-left: 1.5rem;
}

.file-help-text {
    font-size: 0.875rem;
    color: #666;
    margin-top: 0.5rem;
}

.bulk-import-tips ul {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 1.5rem;
    border-radius: 4px;
    margin: 0;
}

.bulk-import-tips li {
    margin-bottom: 0.5rem;
}
</style>

<?php get_footer(); ?> 