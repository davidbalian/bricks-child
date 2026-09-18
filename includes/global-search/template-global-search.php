<?php
/**
 * Virtual /search/ results template.
 *
 * @package Bricks_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$query  = isset( $_GET['q'] ) && is_scalar( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$query  = function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 100 ) : substr( $query, 0, 100 );
$parsed = $query ? autoagora_global_search_parse_query( $query ) : array( 'chips' => array(), 'search_url' => '' );
$rows   = $query ? autoagora_global_search_index_results( $query, 100 ) : array();
$labels = array(
	'make' => __( 'Makes and models', 'bricks-child' ), 'model' => __( 'Makes and models', 'bricks-child' ),
	'car' => __( 'Cars', 'bricks-child' ), 'dealer_profile' => __( 'Dealerships', 'bricks-child' ),
	'buyer_request' => __( 'Buyer requests', 'bricks-child' ), 'post' => __( 'Articles and pages', 'bricks-child' ),
	'page' => __( 'Articles and pages', 'bricks-child' ),
);
$groups = array();
foreach ( $rows as $row ) {
	$group = isset( $labels[ $row['object_type'] ] ) ? $labels[ $row['object_type'] ] : __( 'Other', 'bricks-child' );
	$groups[ $group ][] = $row;
}

get_header();
?>
<main class="aag-search-page" id="brx-content">
	<div class="aag-search-page__inner">
		<header class="aag-search-page__header">
			<h1><?php echo $query ? esc_html( sprintf( __( 'Search results for “%s”', 'bricks-child' ), $query ) ) : esc_html__( 'Search AutoAgora', 'bricks-child' ); ?></h1>
			<?php echo do_shortcode( '[autoagora_global_search context="default"]' ); ?>
		</header>

		<?php if ( $query && ! empty( $parsed['search_url'] ) ) : ?>
			<a class="aag-search-page__cars-cta" href="<?php echo esc_url( $parsed['search_url'] ); ?>">
				<span class="aag-search-page__cars-icon"><?php echo autoagora_global_search_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span>
					<strong><?php esc_html_e( 'Search matching car inventory', 'bricks-child' ); ?></strong>
					<?php if ( ! empty( $parsed['chips'] ) ) : ?>
						<span class="aag-global-search__chips">
							<?php foreach ( $parsed['chips'] as $chip ) : ?>
								<span class="aag-global-search__chip"><?php echo esc_html( $chip['label'] ); ?></span>
							<?php endforeach; ?>
						</span>
					<?php else : ?>
						<span><?php esc_html_e( 'Browse active listings ranked by Best Match.', 'bricks-child' ); ?></span>
					<?php endif; ?>
				</span>
			</a>
		<?php endif; ?>

		<?php if ( ! $query ) : ?>
			<p class="aag-search-page__empty"><?php esc_html_e( 'Enter a make, model, dealership, buyer request or topic above.', 'bricks-child' ); ?></p>
		<?php elseif ( empty( $groups ) ) : ?>
			<p class="aag-search-page__empty"><?php esc_html_e( 'No direct content matches were found. Try the matching car inventory search above or use fewer words.', 'bricks-child' ); ?></p>
		<?php else : ?>
			<nav class="aag-search-page__jump-links" aria-label="<?php esc_attr_e( 'Search result sections', 'bricks-child' ); ?>">
				<?php foreach ( array_keys( $groups ) as $group_name ) : ?>
					<a href="#<?php echo esc_attr( sanitize_title( $group_name ) ); ?>"><?php echo esc_html( $group_name ); ?> <span><?php echo esc_html( count( $groups[ $group_name ] ) ); ?></span></a>
				<?php endforeach; ?>
			</nav>
			<div class="aag-search-page__groups">
				<?php foreach ( $groups as $group_name => $items ) : ?>
					<section class="aag-search-page__group" id="<?php echo esc_attr( sanitize_title( $group_name ) ); ?>">
						<h2><?php echo esc_html( $group_name ); ?> <span><?php echo esc_html( count( $items ) ); ?></span></h2>
						<div class="aag-search-page__grid">
							<?php foreach ( $items as $item ) : ?>
								<a class="aag-search-page__result" href="<?php echo esc_url( $item['url'] ); ?>">
									<span class="aag-search-page__thumb<?php echo ! empty( $item['image_url'] ) ? ' has-image' : ''; ?>">
										<?php if ( ! empty( $item['image_url'] ) ) : ?>
											<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="" loading="lazy">
										<?php else : ?>
											<?php echo autoagora_global_search_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php endif; ?>
									</span>
									<span><strong><?php echo esc_html( $item['title'] ); ?></strong><?php if ( $item['subtitle'] ) : ?><small><?php echo esc_html( $item['subtitle'] ); ?></small><?php endif; ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
