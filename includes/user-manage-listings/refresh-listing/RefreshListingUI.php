<?php
/**
 * Refresh Listing UI
 * 
 * Handles frontend display for listing refresh functionality
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class RefreshListingUI
 * 
 * Manages UI components for refreshing car listings
 */
class RefreshListingUI {
    
    /**
     * The refresh listing manager instance
     * 
     * @var RefreshListingManager
     */
    private $manager;
    
    /**
     * Constructor
     * 
     * @param RefreshListingManager $manager The manager instance
     */
    public function __construct(RefreshListingManager $manager) {
        $this->manager = $manager;
    }
    
    /**
     * Render refresh button for a listing
     * 
     * @param int $post_id The listing post ID
     * @return string HTML button markup
     */
    public function render_refresh_button($post_id) {
        if (!$post_id) {
            return '';
        }
        
        $can_refresh = $this->manager->can_refresh($post_id);
        $time_remaining = $this->manager->get_time_until_refresh($post_id);
        $refresh_count = $this->manager->get_refresh_count($post_id);
        
        $button_class = $can_refresh ? 'button refresh-button' : 'button refresh-button disabled';
        $button_disabled = $can_refresh ? '' : ' disabled';
        $icon_class = 'fas fa-sync-alt';
        
        ob_start();
        ?>
        <button class="<?php echo esc_attr($button_class); ?>" 
                data-car-id="<?php echo esc_attr($post_id); ?>"
                data-can-refresh="<?php echo $can_refresh ? '1' : '0'; ?>"
                <?php echo $button_disabled; ?>>
            <i class="<?php echo esc_attr($icon_class); ?>"></i>
            <?php if ($can_refresh): ?>
                Refresh Listing
            <?php else: ?>
                Available in <?php echo esc_html($time_remaining); ?>
            <?php endif; ?>
        </button>
        <?php if ($refresh_count > 0): ?>
            <span class="refresh-info" title="Total refreshes: <?php echo esc_attr($refresh_count); ?>">
                <i class="fas fa-info-circle"></i>
                Refreshed <?php echo esc_html($refresh_count); ?> time<?php echo $refresh_count > 1 ? 's' : ''; ?>
            </span>
        <?php endif; ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render refresh status badge
     * 
     * @param int $post_id The listing post ID
     * @return string HTML badge markup
     */
    public function render_refresh_status($post_id) {
        if (!$post_id) {
            return '';
        }
        
        $last_refresh = $this->manager->get_last_refresh_date($post_id);
        
        if (!$last_refresh) {
            return '';
        }
        
        $human_time = human_time_diff(strtotime($last_refresh), current_time('timestamp'));
        
        ob_start();
        ?>
        <span class="refresh-status">
            <i class="fas fa-clock"></i>
            Last refreshed <?php echo esc_html($human_time); ?> ago
        </span>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render refresh info tooltip
     * 
     * @return string HTML tooltip markup
     */
    public function render_refresh_info_tooltip() {
        ob_start();
        ?>
        <div class="refresh-info-tooltip">
            <i class="fas fa-question-circle"></i>
            <div class="tooltip-content">
                <h4>About Refresh Listing</h4>
                <p>Refreshing your listing moves it to the top of search results and makes it appear as "recently updated".</p>
                <ul>
                    <li>Available once every 7 days</li>
                    <li>Updates listing's "last modified" date</li>
                    <li>Increases visibility to buyers</li>
                    <li>Only available for published, unsold listings</li>
                </ul>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get refresh button data attributes
     * 
     * @param int $post_id The listing post ID
     * @return array Data attributes for button
     */
    public function get_button_data($post_id) {
        return array(
            'car-id' => $post_id,
            'can-refresh' => $this->manager->can_refresh($post_id) ? '1' : '0',
            'next-refresh' => $this->manager->get_next_refresh_date($post_id),
            'time-remaining' => $this->manager->get_time_until_refresh($post_id)
        );
    }
}

