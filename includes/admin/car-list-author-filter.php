<?php
/**
 * Author/dealership filter for the native Cars admin list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add an author dropdown to the Cars list table.
 *
 * WordPress already understands the `author` query parameter, so selecting a
 * user uses the native posts query without any custom query manipulation.
 *
 * @param string $post_type Current post type.
 */
function autoagora_render_car_author_filter( $post_type ) {
	if ( 'car' !== $post_type ) {
		return;
	}

	global $wpdb;

	$author_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT post_author
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_author > 0",
			'car'
		)
	);

	$author_ids = array_values( array_filter( array_map( 'absint', $author_ids ) ) );
	if ( empty( $author_ids ) ) {
		return;
	}

	$authors = get_users(
		array(
			'include' => $author_ids,
			'orderby' => 'display_name',
			'order'   => 'ASC',
		)
	);

	if ( empty( $authors ) ) {
		return;
	}

	$selected_author = isset( $_GET['author'] ) ? absint( wp_unslash( $_GET['author'] ) ) : 0;
	?>
	<label class="screen-reader-text" for="filter-by-car-author">
		<?php esc_html_e( 'Filter cars by author or dealership', 'bricks-child' ); ?>
	</label>
	<select name="author" id="filter-by-car-author">
		<option value="0"><?php esc_html_e( 'All authors / dealerships', 'bricks-child' ); ?></option>
		<?php foreach ( $authors as $author ) : ?>
			<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( $selected_author, $author->ID ); ?>>
				<?php echo esc_html( $author->display_name ?: $author->user_login ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'autoagora_render_car_author_filter' );
