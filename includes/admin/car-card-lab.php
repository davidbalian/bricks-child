<?php
/**
 * Admin-only visual lab for the reusable car card component.
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Car_Card_Lab {
    private const SLUG = 'autoagora-car-card-lab';

    public static function bootstrap() {
        $page = new self();
        add_action('admin_menu', array($page, 'register_menu'));
        add_action('admin_enqueue_scripts', array($page, 'enqueue_assets'));
        add_action('admin_head', array($page, 'render_noindex_meta'));
    }

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=car',
            __('Car Card Lab', 'bricks-child'),
            __('Car Card Lab', 'bricks-child'),
            'manage_options',
            self::SLUG,
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook_suffix) {
        if ($hook_suffix !== 'car_page_' . self::SLUG) {
            return;
        }

        wp_enqueue_style(
            'autoagora-card-lab-fonts',
            'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
            array(),
            null
        );
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
            array(),
            '6.7.2'
        );
        car_card_enqueue_assets();

        $base_path = __DIR__ . '/';
        $base_url = get_stylesheet_directory_uri() . '/includes/admin/';
        wp_enqueue_style(
            'autoagora-car-card-lab',
            $base_url . 'car-card-lab.css',
            array('car-card'),
            filemtime($base_path . 'car-card-lab.css')
        );
        wp_enqueue_script(
            'autoagora-car-card-lab',
            $base_url . 'car-card-lab.js',
            array(),
            filemtime($base_path . 'car-card-lab.js'),
            true
        );
    }

    public function render_noindex_meta() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'car_page_' . self::SLUG) {
            echo "<meta name=\"robots\" content=\"noindex,nofollow,noarchive\">\n";
        }
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'));
        }

        $listing_ids = get_posts(array(
            'post_type'              => 'car',
            'post_status'            => 'publish',
            'posts_per_page'         => 30,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'meta_key'               => 'car_images',
            'meta_compare'           => 'EXISTS',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ));

        if (!$listing_ids) {
            $listing_ids = get_posts(array(
                'post_type'      => 'car',
                'post_status'    => 'publish',
                'posts_per_page' => 30,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ));
        }

        $requested_id = isset($_GET['sample_car']) ? absint($_GET['sample_car']) : 0;
        $sample_id = $requested_id && in_array($requested_id, $listing_ids, true)
            ? $requested_id
            : (int) reset($listing_ids);

        $promotion_states = array(
            'none'     => __('Regular', 'bricks-child'),
            'priority' => __('AutoAgora Lift', 'bricks-child'),
            'showcase' => __('AutoAgora Showcase', 'bricks-child'),
        );
        $detail_states = array(
            'none'  => array('label' => __('No details pill', 'bricks-child'), 'full' => false, 'extra' => false),
            'extra' => array('label' => __('Extra Details', 'bricks-child'), 'full' => false, 'extra' => true),
            'full'  => array('label' => __('Full Details', 'bricks-child'), 'full' => true, 'extra' => false),
        );
        $signal_states = array(
            'none'          => array('label' => __('No signal pill', 'bricks-child'), 'band' => 'none', 'popular' => false),
            'popular'       => array('label' => __('Popular', 'bricks-child'), 'band' => 'none', 'popular' => true),
            'great'         => array('label' => __('Great Deal', 'bricks-child'), 'band' => 'great', 'popular' => false),
            'great-popular' => array('label' => __('Great Deal + Popular', 'bricks-child'), 'band' => 'great', 'popular' => true),
            'good'          => array('label' => __('Good Deal', 'bricks-child'), 'band' => 'good', 'popular' => false),
            'good-popular'  => array('label' => __('Good Deal + Popular', 'bricks-child'), 'band' => 'good', 'popular' => true),
            'fair'          => array('label' => __('Fair Deal', 'bricks-child'), 'band' => 'fair', 'popular' => false),
            'fair-popular'  => array('label' => __('Fair Deal + Popular', 'bricks-child'), 'band' => 'fair', 'popular' => true),
            'above'         => array('label' => __('Above typical', 'bricks-child'), 'band' => 'above', 'popular' => false),
            'above-popular' => array('label' => __('Above typical + Popular', 'bricks-child'), 'band' => 'above', 'popular' => true),
        );
        $combination_count = count($promotion_states) * count($detail_states) * count($signal_states);
        ?>
        <div class="wrap autoagora-card-lab">
            <h1><?php esc_html_e('Car Card Lab', 'bricks-child'); ?></h1>
            <p class="description">
                <?php
                echo esc_html(sprintf(
                    __('All %d valid presentation combinations are rendered below using one real listing. Preview overrides are temporary and never change listing data.', 'bricks-child'),
                    $combination_count
                ));
                ?>
            </p>

            <?php if (!$sample_id) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e('No published car listing is available to preview.', 'bricks-child'); ?></p></div>
            <?php else : ?>
                <form class="autoagora-card-lab__sample" method="get">
                    <input type="hidden" name="post_type" value="car">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label for="autoagora-card-lab-sample"><strong><?php esc_html_e('Sample listing', 'bricks-child'); ?></strong></label>
                    <select id="autoagora-card-lab-sample" name="sample_car">
                        <?php foreach ($listing_ids as $listing_id) : ?>
                            <option value="<?php echo esc_attr($listing_id); ?>" <?php selected($sample_id, $listing_id); ?>>
                                <?php echo esc_html(get_the_title($listing_id) . ' (#' . $listing_id . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button button-secondary" type="submit"><?php esc_html_e('Use listing', 'bricks-child'); ?></button>
                </form>

                <div class="autoagora-card-lab__toolbar" aria-label="<?php esc_attr_e('Preview filters', 'bricks-child'); ?>">
                    <?php $this->render_filter('promotion', __('Promotion outline', 'bricks-child'), $promotion_states); ?>
                    <?php $this->render_filter('details', __('Top details pill', 'bricks-child'), wp_list_pluck($detail_states, 'label')); ?>
                    <?php $this->render_filter('signal', __('Signal pills', 'bricks-child'), wp_list_pluck($signal_states, 'label')); ?>
                    <p class="autoagora-card-lab__visible" aria-live="polite"></p>
                </div>

                <div class="autoagora-card-lab__grid car-card-grid">
                    <?php foreach ($promotion_states as $promotion_key => $promotion_label) : ?>
                        <?php foreach ($detail_states as $detail_key => $detail) : ?>
                            <?php foreach ($signal_states as $signal_key => $signal) : ?>
                                <section
                                    class="autoagora-card-lab__variant"
                                    data-promotion="<?php echo esc_attr($promotion_key); ?>"
                                    data-details="<?php echo esc_attr($detail_key); ?>"
                                    data-signal="<?php echo esc_attr($signal_key); ?>"
                                >
                                    <div class="autoagora-card-lab__label">
                                        <strong><?php echo esc_html($promotion_label); ?></strong>
                                        <span><?php echo esc_html($detail['label'] . ' · ' . $signal['label']); ?></span>
                                    </div>
                                    <?php
                                    render_car_card($sample_id, array(
                                        'preview' => array(
                                            'promotion_tier'   => $promotion_key,
                                            'full_badge'       => $detail['full'],
                                            'extra_badge'      => $detail['extra'],
                                            'price_insight_band' => $signal['band'],
                                            'popular_badge'    => $signal['popular'],
                                        ),
                                    ));
                                    ?>
                                </section>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_filter($name, $label, array $options) {
        ?>
        <label>
            <span><?php echo esc_html($label); ?></span>
            <select data-card-lab-filter="<?php echo esc_attr($name); ?>">
                <option value="all"><?php esc_html_e('Show all', 'bricks-child'); ?></option>
                <?php foreach ($options as $value => $option_label) : ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }
}

AutoAgora_Car_Card_Lab::bootstrap();
