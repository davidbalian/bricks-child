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
        
        $button_class = $can_refresh ? 'btn btn-success refresh-button' : 'btn btn-success refresh-button';
        $button_disabled = $can_refresh ? '' : ' disabled';
        $icon_class = 'fas fa-sync-alt';
        $button_text = $can_refresh
            ? __('Refresh Listing', 'bricks-child')
            : sprintf(__('Available in %s', 'bricks-child'), $time_remaining);
        
        ob_start();
        ?>
        <button class="<?php echo esc_attr($button_class); ?>" 
                data-car-id="<?php echo esc_attr($post_id); ?>"
                data-can-refresh="<?php echo $can_refresh ? '1' : '0'; ?>"
                <?php echo $button_disabled; ?>>
            <i class="<?php echo esc_attr($icon_class); ?>"></i>
            <span class="refresh-button-text"><?php echo esc_html($button_text); ?></span>
        </button>
        <?php if ($refresh_count > 0): ?>
            <span class="refresh-info" title="<?php echo esc_attr(sprintf(__('Total refreshes: %d', 'bricks-child'), $refresh_count)); ?>">
                <i class="fas fa-info-circle"></i>
                <?php echo esc_html(sprintf(_n('Refreshed %d time', 'Refreshed %d times', $refresh_count, 'bricks-child'), $refresh_count)); ?>
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
            <?php echo esc_html(sprintf(__('Last refreshed %s ago', 'bricks-child'), $human_time)); ?>
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
                <h4><?php esc_html_e('About Refresh Listing', 'bricks-child'); ?></h4>
                <p><?php esc_html_e('Refreshing your listing moves it to the top of search results and makes it appear as "recently updated".', 'bricks-child'); ?></p>
                <ul>
                    <li><?php esc_html_e('Available once every 7 days', 'bricks-child'); ?></li>
                    <li><?php esc_html_e("Updates listing's last modified date", 'bricks-child'); ?></li>
                    <li><?php esc_html_e('Increases visibility to buyers', 'bricks-child'); ?></li>
                    <li><?php esc_html_e('Only available for published, unsold listings', 'bricks-child'); ?></li>
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

