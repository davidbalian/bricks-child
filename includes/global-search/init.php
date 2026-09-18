<?php
/**
 * AutoAgora global search.
 *
 * Provides an accessible typeahead, a small purpose-built search index, and a
 * deterministic natural-language parser that resolves vehicle intent into the
 * existing car filter URL contract.
 *
 * @package Bricks_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AUTOAGORA_GLOBAL_SEARCH_DB_VERSION = '1.0.0';
const AUTOAGORA_GLOBAL_SEARCH_REBUILD_HOOK = 'autoagora_global_search_rebuild_batch';
const AUTOAGORA_GLOBAL_SEARCH_REWRITE_VERSION = '1.0.0';

function autoagora_global_search_results_url( $query = '' ) {
	$home = function_exists( 'autoagora_localized_page_url' ) ? autoagora_localized_page_url() : home_url( '/' );
	$url  = trailingslashit( $home ) . 'search/';
	return '' !== (string) $query ? add_query_arg( 'q', sanitize_text_field( $query ), $url ) : $url;
}

function autoagora_global_search_register_route() {
	add_rewrite_rule( '^search/?$', 'index.php?autoagora_global_search_page=1', 'top' );
	if ( function_exists( 'pll_languages_list' ) ) {
		$languages = pll_languages_list( array( 'fields' => 'slug' ) );
		foreach ( (array) $languages as $language ) {
			$language = sanitize_key( $language );
			if ( $language ) {
				add_rewrite_rule( '^' . preg_quote( $language, '#' ) . '/search/?$', 'index.php?autoagora_global_search_page=1&lang=' . $language, 'top' );
			}
		}
	}
}
add_action( 'init', 'autoagora_global_search_register_route', 20 );

function autoagora_global_search_query_vars( array $vars ) {
	$vars[] = 'autoagora_global_search_page';
	return $vars;
}
add_filter( 'query_vars', 'autoagora_global_search_query_vars' );

function autoagora_global_search_maybe_flush_rewrites() {
	if ( get_option( 'autoagora_global_search_rewrite_version' ) === AUTOAGORA_GLOBAL_SEARCH_REWRITE_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'autoagora_global_search_rewrite_version', AUTOAGORA_GLOBAL_SEARCH_REWRITE_VERSION, false );
}
add_action( 'init', 'autoagora_global_search_maybe_flush_rewrites', 99 );

function autoagora_global_search_is_page() {
	return '1' === (string) get_query_var( 'autoagora_global_search_page' );
}

function autoagora_global_search_prepare_route() {
	if ( ! autoagora_global_search_is_page() ) {
		return;
	}
	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
}
add_action( 'template_redirect', 'autoagora_global_search_prepare_route', 0 );

function autoagora_global_search_template( $template ) {
	if ( ! autoagora_global_search_is_page() ) {
		return $template;
	}
	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	$search_template = __DIR__ . '/template-global-search.php';
	return file_exists( $search_template ) ? $search_template : $template;
}
add_filter( 'template_include', 'autoagora_global_search_template', 99 );

function autoagora_global_search_document_title( array $parts ) {
	if ( autoagora_global_search_is_page() ) {
		$query = isset( $_GET['q'] ) && is_scalar( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$parts['title'] = $query ? sprintf( __( 'Search results for “%s”', 'bricks-child' ), $query ) : __( 'Search AutoAgora', 'bricks-child' );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'autoagora_global_search_document_title', 40 );

function autoagora_global_search_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'autoagora_search_index';
}

function autoagora_global_search_table_exists( $refresh = false ) {
	static $exists = null;
	if ( null !== $exists && ! $refresh ) {
		return $exists;
	}
	global $wpdb;
	$table = autoagora_global_search_table_name();
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	return $exists;
}

/** Create/update the compact index and queue a non-blocking initial rebuild. */
function autoagora_global_search_maybe_install() {
	if ( get_option( 'autoagora_global_search_db_version' ) === AUTOAGORA_GLOBAL_SEARCH_DB_VERSION && autoagora_global_search_table_exists() ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = autoagora_global_search_table_name();
	$collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		object_type varchar(32) NOT NULL,
		object_id bigint(20) unsigned NOT NULL,
		language varchar(12) NOT NULL DEFAULT '',
		title varchar(255) NOT NULL,
		subtitle varchar(255) NOT NULL DEFAULT '',
		search_text longtext NOT NULL,
		url text NOT NULL,
		image_url text NOT NULL,
		score decimal(12,4) NOT NULL DEFAULT 0,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY object_language (object_type,object_id,language),
		KEY type_language (object_type,language),
		KEY updated_at (updated_at)
	) {$collate};";

	dbDelta( $sql );
	autoagora_global_search_table_exists( true );
	update_option( 'autoagora_global_search_db_version', AUTOAGORA_GLOBAL_SEARCH_DB_VERSION, false );
	update_option( 'autoagora_global_search_rebuild_state', array( 'type_index' => 0, 'page' => 1 ), false );

	if ( ! wp_next_scheduled( AUTOAGORA_GLOBAL_SEARCH_REBUILD_HOOK ) ) {
		wp_schedule_single_event( time() + 5, AUTOAGORA_GLOBAL_SEARCH_REBUILD_HOOK );
	}
}
add_action( 'after_setup_theme', 'autoagora_global_search_maybe_install', 30 );

function autoagora_global_search_language_for_post( $post_id ) {
	if ( function_exists( 'pll_get_post_language' ) ) {
		return sanitize_key( (string) pll_get_post_language( $post_id, 'slug' ) );
	}
	return '';
}

function autoagora_global_search_language_for_term( $term_id ) {
	if ( function_exists( 'pll_get_term_language' ) ) {
		return sanitize_key( (string) pll_get_term_language( $term_id, 'slug' ) );
	}
	return '';
}

function autoagora_global_search_current_language() {
	if ( function_exists( 'autoagora_current_language' ) ) {
		return sanitize_key( (string) autoagora_current_language() );
	}
	if ( function_exists( 'pll_current_language' ) ) {
		return sanitize_key( (string) pll_current_language( 'slug' ) );
	}
	return '';
}

function autoagora_global_search_normalize( $value ) {
	$value = remove_accents( wp_strip_all_tags( (string) $value ) );
	$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
	return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
}

/** Preserve numeric punctuation/currency while normalizing user intent. */
function autoagora_global_search_normalize_query( $value ) {
	$value = remove_accents( wp_strip_all_tags( (string) $value ) );
	$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	$value = preg_replace( '/(?<=\d)[–—-](?=\d)/u', ' to ', $value );
	$value = str_replace( array( '-', '–', '—' ), ' ', $value );
	$value = preg_replace( '/[^\p{L}\p{N}.,€]+/u', ' ', $value );
	return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
}

function autoagora_global_search_post_image( $post_id, $post_type ) {
	if ( 'dealer_profile' === $post_type ) {
		return esc_url_raw( (string) get_post_meta( $post_id, 'dealer_logo_url', true ) );
	}

	$image = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
	if ( $image || 'car' !== $post_type ) {
		return $image ? esc_url_raw( $image ) : '';
	}

	$images = get_post_meta( $post_id, 'car_images', true );
	if ( is_string( $images ) ) {
		$images = maybe_unserialize( $images );
	}
	if ( ! is_array( $images ) || empty( $images ) ) {
		return '';
	}
	$first = reset( $images );
	if ( is_numeric( $first ) ) {
		$image = wp_get_attachment_image_url( (int) $first, 'thumbnail' );
	} elseif ( is_array( $first ) && ! empty( $first['sizes']['thumbnail'] ) ) {
		$image = $first['sizes']['thumbnail'];
	} elseif ( is_array( $first ) && ! empty( $first['url'] ) ) {
		$image = $first['url'];
	} elseif ( is_string( $first ) ) {
		$image = $first;
	}
	return $image ? esc_url_raw( $image ) : '';
}

function autoagora_global_search_delete_object( $object_type, $object_id ) {
	if ( ! autoagora_global_search_table_exists() ) {
		return;
	}
	global $wpdb;
	$deleted = $wpdb->delete(
		autoagora_global_search_table_name(),
		array( 'object_type' => sanitize_key( $object_type ), 'object_id' => absint( $object_id ) ),
		array( '%s', '%d' )
	);
	if ( $deleted ) {
		autoagora_global_search_touch_revision();
	}
}

function autoagora_global_search_index_row( array $row ) {
	if ( ! autoagora_global_search_table_exists() ) {
		return;
	}
	global $wpdb;
	$title       = wp_html_excerpt( sanitize_text_field( $row['title'] ), 250, '' );
	$subtitle    = wp_html_excerpt( sanitize_text_field( $row['subtitle'] ), 250, '' );
	$search_text = autoagora_global_search_normalize( $row['search_text'] );
	$search_text = function_exists( 'mb_substr' ) ? mb_substr( $search_text, 0, 12000 ) : substr( $search_text, 0, 12000 );
	$wpdb->replace(
		autoagora_global_search_table_name(),
		array(
			'object_type' => sanitize_key( $row['object_type'] ),
			'object_id'   => absint( $row['object_id'] ),
			'language'    => sanitize_key( $row['language'] ),
			'title'       => $title,
			'subtitle'    => $subtitle,
			'search_text' => $search_text,
			'url'         => esc_url_raw( $row['url'] ),
			'image_url'   => esc_url_raw( $row['image_url'] ),
			'score'       => (float) $row['score'],
			'updated_at'  => current_time( 'mysql', true ),
		),
		array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s' )
	);
	autoagora_global_search_touch_revision();
}

function autoagora_global_search_touch_revision() {
	static $touched = false;
	if ( $touched ) {
		return;
	}
	$touched = true;
	update_option( 'autoagora_global_search_index_revision', (string) microtime( true ), false );
}

/** Index only public, intentionally searchable content. */
function autoagora_global_search_index_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
		if ( $post ) {
			autoagora_global_search_delete_object( $post->post_type, $post_id );
		}
		return;
	}

	$allowed = array( 'car', 'dealer_profile', 'buyer_request', 'post', 'page' );
	if ( ! in_array( $post->post_type, $allowed, true ) ) {
		return;
	}

	if ( 'car' === $post->post_type && class_exists( 'ListingStateManager' ) && 'active' !== ListingStateManager::resolve_state( (int) $post_id ) ) {
		autoagora_global_search_delete_object( 'car', $post_id );
		return;
	}
	if ( 'dealer_profile' === $post->post_type && function_exists( 'autoagora_dealer_profile_has_public_quality' ) && ! autoagora_dealer_profile_has_public_quality( (int) $post_id ) ) {
		autoagora_global_search_delete_object( 'dealer_profile', $post_id );
		return;
	}

	$title      = get_the_title( $post_id );
	$subtitle   = '';
	$keywords   = array( $title );
	$score      = 0;
	$meta_keys  = array();

	if ( 'car' === $post->post_type ) {
		$meta_keys = array( 'make', 'model', 'year', 'mileage', 'price', 'car_city', 'car_district', 'fuel_type', 'transmission', 'body_type', 'drive_type', 'engine_capacity', 'hp', 'exterior_color', 'interior_color', 'number_of_doors', 'number_of_seats', 'extras', 'vehiclehistory', 'numowners', 'description' );
		$year      = get_post_meta( $post_id, 'year', true );
		$price     = get_post_meta( $post_id, 'price', true );
		$city      = get_post_meta( $post_id, 'car_city', true );
		$price_label = '';
		if ( is_numeric( $price ) ) {
			$price_label = function_exists( 'wc_price' )
				? html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) )
				: '€' . number_format_i18n( (float) $price );
		}
		$parts = array_filter( array( $year, $price_label, $city ) );
		$subtitle = implode( ' · ', $parts );
		$score    = (float) get_post_meta( $post_id, 'listing_rank_score', true );
		$terms    = wp_get_post_terms( $post_id, 'car_make', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $terms ) ) {
			$keywords = array_merge( $keywords, $terms );
		}
	} elseif ( 'buyer_request' === $post->post_type ) {
		$meta_keys = array( 'buyer_make', 'buyer_model', 'buyer_year', 'buyer_price', 'buyer_description' );
		$subtitle  = __( 'Buyer request', 'bricks-child' );
	} elseif ( 'dealer_profile' === $post->post_type ) {
		$meta_keys = array( 'dealer_city', 'dealer_district', 'dealer_short_description', 'dealer_services', 'dealer_languages' );
		$city      = get_post_meta( $post_id, 'dealer_city', true );
		$subtitle  = $city ? sprintf( __( 'Dealer in %s', 'bricks-child' ), $city ) : __( 'Dealership', 'bricks-child' );
	} else {
		$subtitle  = 'post' === $post->post_type ? __( 'Article', 'bricks-child' ) : __( 'Page', 'bricks-child' );
		$keywords[] = strip_shortcodes( $post->post_excerpt . ' ' . $post->post_content );
	}

	foreach ( $meta_keys as $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( is_array( $value ) ) {
			$value = implode( ' ', array_map( 'strval', $value ) );
		}
		$keywords[] = (string) $value;
	}

	autoagora_global_search_index_row(
		array(
			'object_type' => $post->post_type,
			'object_id'   => $post_id,
			'language'    => autoagora_global_search_language_for_post( $post_id ),
			'title'       => $title,
			'subtitle'    => $subtitle,
			'search_text' => implode( ' ', $keywords ),
			'url'         => get_permalink( $post_id ),
			'image_url'   => autoagora_global_search_post_image( $post_id, $post->post_type ),
			'score'       => $score,
		)
	);
}

function autoagora_global_search_term_url( $term ) {
	$base = function_exists( 'autoagora_localized_page_url' ) ? autoagora_localized_page_url( 'cars' ) : home_url( '/cars/' );
	if ( $term->parent ) {
		$parent = get_term( $term->parent, 'car_make' );
		$args   = array( 'model' => $term->slug );
		if ( $parent && ! is_wp_error( $parent ) ) {
			$args['make'] = $parent->slug;
		}
		$config = function_exists( 'autoagora_get_car_make_landing_config' ) ? autoagora_get_car_make_landing_config() : array();
		if ( isset( $config[ $term->slug ] ) ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
		return add_query_arg( $args, $base );
	}
	return add_query_arg( array( 'make' => $term->slug ), $base );
}

function autoagora_global_search_index_term( $term_id ) {
	$term = get_term( $term_id, 'car_make' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	$parent_name = '';
	if ( $term->parent ) {
		$parent = get_term( $term->parent, 'car_make' );
		$parent_name = $parent && ! is_wp_error( $parent ) ? $parent->name : '';
	}
	autoagora_global_search_index_row(
		array(
			'object_type' => $term->parent ? 'model' : 'make',
			'object_id'   => $term->term_id,
			'language'    => autoagora_global_search_language_for_term( $term->term_id ),
			'title'       => $term->name,
			'subtitle'    => $term->parent ? sprintf( __( '%s model', 'bricks-child' ), $parent_name ) : __( 'Car make', 'bricks-child' ),
			'search_text' => trim( $parent_name . ' ' . $term->name . ' ' . str_replace( '-', ' ', $term->slug ) ),
			'url'         => autoagora_global_search_term_url( $term ),
			'image_url'   => '',
			'score'       => (float) $term->count,
		)
	);
}

function autoagora_global_search_save_post_hook( $post_id, $post = null ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	autoagora_global_search_index_post( (int) $post_id );
}
add_action( 'save_post', 'autoagora_global_search_save_post_hook', 50, 2 );
add_action( 'acf/save_post', 'autoagora_global_search_save_post_hook', 50, 1 );

/**
 * Direct update_field()/update_post_meta() flows do not always emit
 * acf/save_post. Queue one final reindex at shutdown after all related fields
 * have been written, rather than replacing the index once per meta update.
 */
function autoagora_global_search_queue_meta_reindex( $meta_id, $post_id, $meta_key ) {
	$searchable_keys = array(
		'listing_state', 'listing_rank_score', 'make', 'model', 'year', 'mileage', 'price', 'car_city', 'car_district',
		'fuel_type', 'transmission', 'body_type', 'drive_type', 'engine_capacity', 'hp', 'exterior_color', 'interior_color',
		'number_of_doors', 'number_of_seats', 'extras', 'vehiclehistory', 'numowners', 'description',
		'buyer_make', 'buyer_model', 'buyer_year', 'buyer_price', 'buyer_description',
		'dealer_city', 'dealer_district', 'dealer_short_description', 'dealer_services', 'dealer_languages', 'dealer_logo_url',
	);
	if ( ! in_array( (string) $meta_key, $searchable_keys, true ) ) {
		return;
	}
	$GLOBALS['autoagora_global_search_reindex_ids'][ absint( $post_id ) ] = true;
}
add_action( 'added_post_meta', 'autoagora_global_search_queue_meta_reindex', 20, 3 );
add_action( 'updated_post_meta', 'autoagora_global_search_queue_meta_reindex', 20, 3 );
add_action( 'deleted_post_meta', 'autoagora_global_search_queue_meta_reindex', 20, 3 );

function autoagora_global_search_flush_meta_reindex_queue() {
	$ids = isset( $GLOBALS['autoagora_global_search_reindex_ids'] ) && is_array( $GLOBALS['autoagora_global_search_reindex_ids'] )
		? array_keys( $GLOBALS['autoagora_global_search_reindex_ids'] )
		: array();
	foreach ( $ids as $post_id ) {
		autoagora_global_search_index_post( absint( $post_id ) );
	}
}
add_action( 'shutdown', 'autoagora_global_search_flush_meta_reindex_queue', 5 );

function autoagora_global_search_deleted_post( $post_id ) {
	$post_type = get_post_type( $post_id );
	if ( $post_type ) {
		autoagora_global_search_delete_object( $post_type, $post_id );
	}
}
add_action( 'trashed_post', 'autoagora_global_search_deleted_post' );
add_action( 'before_delete_post', 'autoagora_global_search_deleted_post' );

function autoagora_global_search_set_terms( $object_id, $terms, $term_taxonomy_ids, $taxonomy ) {
	if ( 'car_make' === $taxonomy && 'car' === get_post_type( $object_id ) ) {
		autoagora_global_search_index_post( (int) $object_id );
	}
}
add_action( 'set_object_terms', 'autoagora_global_search_set_terms', 20, 4 );

function autoagora_global_search_term_changed( $term_id ) {
	autoagora_global_search_index_term( (int) $term_id );
}
add_action( 'created_car_make', 'autoagora_global_search_term_changed' );
add_action( 'edited_car_make', 'autoagora_global_search_term_changed' );

function autoagora_global_search_term_deleted( $term_id ) {
	autoagora_global_search_delete_object( 'make', $term_id );
	autoagora_global_search_delete_object( 'model', $term_id );
}
add_action( 'delete_car_make', 'autoagora_global_search_term_deleted' );

/** Build 100 records at a time to avoid a long deployment request. */
function autoagora_global_search_rebuild_batch() {
	if ( ! autoagora_global_search_table_exists() ) {
		return;
	}
	$types = array( 'car', 'dealer_profile', 'buyer_request', 'post', 'page', 'car_make_terms' );
	$state = get_option( 'autoagora_global_search_rebuild_state', array( 'type_index' => 0, 'page' => 1 ) );
	$index = isset( $state['type_index'] ) ? absint( $state['type_index'] ) : 0;
	$page  = isset( $state['page'] ) ? max( 1, absint( $state['page'] ) ) : 1;

	if ( ! isset( $types[ $index ] ) ) {
		delete_option( 'autoagora_global_search_rebuild_state' );
		update_option( 'autoagora_global_search_index_ready', time(), false );
		return;
	}

	if ( 'car_make_terms' === $types[ $index ] ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'car_make',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 100,
				'offset'     => ( $page - 1 ) * 100,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				autoagora_global_search_index_term( (int) $term_id );
			}
		}
		$next_state = ! is_wp_error( $terms ) && count( $terms ) === 100
			? array( 'type_index' => $index, 'page' => $page + 1 )
			: array( 'type_index' => $index + 1, 'page' => 1 );
		update_option( 'autoagora_global_search_rebuild_state', $next_state, false );
		wp_schedule_single_event( time() + 2, AUTOAGORA_GLOBAL_SEARCH_REBUILD_HOOK );
		return;
	}

	$query = new WP_Query(
		array(
			'post_type'              => $types[ $index ],
			'post_status'            => 'publish',
			'posts_per_page'         => 100,
			'paged'                  => $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		)
	);
	foreach ( $query->posts as $post_id ) {
		autoagora_global_search_index_post( (int) $post_id );
	}

	$next_state = count( $query->posts ) === 100
		? array( 'type_index' => $index, 'page' => $page + 1 )
		: array( 'type_index' => $index + 1, 'page' => 1 );
	update_option( 'autoagora_global_search_rebuild_state', $next_state, false );
	wp_schedule_single_event( time() + 2, AUTOAGORA_GLOBAL_SEARCH_REBUILD_HOOK );
}
add_action( AUTOAGORA_GLOBAL_SEARCH_REBUILD_HOOK, 'autoagora_global_search_rebuild_batch' );

function autoagora_global_search_add_chip( array &$chips, $key, $value, $label ) {
	if ( isset( $chips[ $key ] ) ) {
		return;
	}
	$chips[ $key ] = array( 'key' => sanitize_key( $key ), 'value' => (string) $value, 'label' => (string) $label );
}

function autoagora_global_search_phrase_match( $haystack, $needle ) {
	return (bool) preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $needle, '/' ) . '(?![\p{L}\p{N}])/u', $haystack );
}

function autoagora_global_search_amount( $number, $thousands = '' ) {
	$value = (float) str_replace( array( ',', ' ' ), '', (string) $number );
	return (int) round( $value * ( $thousands ? 1000 : 1 ) );
}

/** Convert natural vehicle language into the existing browse query parameters. */
function autoagora_global_search_parse_query( $raw_query ) {
	$query  = autoagora_global_search_normalize_query( $raw_query );
	$params = array();
	$chips  = array();

	$terms = get_terms( array( 'taxonomy' => 'car_make', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		usort( $terms, static function ( $a, $b ) { return strlen( $b->name ) <=> strlen( $a->name ); } );
		$matched_model = null;
		foreach ( $terms as $term ) {
			if ( $term->parent && autoagora_global_search_phrase_match( $query, autoagora_global_search_normalize( $term->name ) ) ) {
				$matched_model = $term;
				break;
			}
		}
		if ( $matched_model ) {
			$parent = get_term( $matched_model->parent, 'car_make' );
			if ( $parent && ! is_wp_error( $parent ) ) {
				$params['make'] = $parent->slug;
				autoagora_global_search_add_chip( $chips, 'make', $parent->slug, $parent->name );
			}
			$params['model'] = $matched_model->slug;
			autoagora_global_search_add_chip( $chips, 'model', $matched_model->slug, $matched_model->name );
		} else {
			foreach ( $terms as $term ) {
				if ( ! $term->parent && autoagora_global_search_phrase_match( $query, autoagora_global_search_normalize( $term->name ) ) ) {
					$params['make'] = $term->slug;
					autoagora_global_search_add_chip( $chips, 'make', $term->slug, $term->name );
					break;
				}
			}
		}
	}

	$maps = array(
		'car_city' => array(
			'Nicosia' => array( 'nicosia', 'lefkosia', 'λευκωσια' ),
			'Limassol' => array( 'limassol', 'lemesos', 'λεμεσος' ),
			'Larnaca' => array( 'larnaca', 'larnaka', 'λαρνακα' ),
			'Paphos' => array( 'paphos', 'pafos', 'παφος' ),
			'Famagusta' => array( 'famagusta', 'ammochostos', 'αμμοχωστος' ),
		),
		'body_type' => array(
			'SUV' => array( 'suv', 'crossover' ), 'Hatchback' => array( 'hatchback', 'hatch' ),
			'Saloon' => array( 'saloon', 'sedan' ), 'Coupe' => array( 'coupe' ),
			'Convertible' => array( 'convertible', 'cabriolet' ), 'Estate' => array( 'estate', 'wagon' ),
			'Pickup' => array( 'pickup', 'pick up' ), 'MPV' => array( 'mpv', 'people carrier' ),
			'Camper' => array( 'camper', 'motorhome' ), 'Minibus' => array( 'minibus' ), 'Limousine' => array( 'limousine', 'limo' ),
			'Panel Van' => array( 'panel van' ), 'Window Van' => array( 'window van' ), 'Combi Van' => array( 'combi van' ),
		),
		'transmission' => array( 'Automatic' => array( 'automatic', 'auto' ), 'Manual' => array( 'manual' ) ),
		'drive_type' => array(
			'All-Wheel Drive' => array( 'all wheel drive', 'awd' ), '4-Wheel Drive' => array( '4 wheel drive', '4wd', '4x4' ),
			'Front-Wheel Drive' => array( 'front wheel drive', 'fwd' ), 'Rear-Wheel Drive' => array( 'rear wheel drive', 'rwd' ),
		),
		'extras' => array(
			'sunroof' => array( 'sunroof' ), 'panoramic_roof' => array( 'panoramic roof', 'pan roof' ),
			'rear_view_camera' => array( 'rear view camera', 'reversing camera' ), 'camera_360' => array( '360 camera', '360 degree camera' ),
			'leather_seats' => array( 'leather seats', 'leather interior' ), 'heated_seats' => array( 'heated seats' ),
			'apple_carplay' => array( 'apple carplay', 'carplay' ), 'android_auto' => array( 'android auto' ),
			'parking_sensors' => array( 'parking sensors' ), 'adaptive_cruise_control' => array( 'adaptive cruise' ),
		),
		'vehiclehistory' => array(
			'no_accidents' => array( 'no accidents', 'accident free' ), 'regular_maintenance' => array( 'regular maintenance', 'full service history' ),
			'clear_title' => array( 'clear title' ), 'no_known_issues' => array( 'no known issues' ),
			'no_modifications' => array( 'no modifications', 'unmodified' ), 'performance_upgrades' => array( 'performance upgrades', 'modified' ),
		),
	);

	foreach ( $maps as $param => $values ) {
		$matches = array();
		foreach ( $values as $value => $aliases ) {
			foreach ( $aliases as $alias ) {
				if ( autoagora_global_search_phrase_match( $query, autoagora_global_search_normalize( $alias ) ) ) {
					$matches[] = $value;
					break;
				}
			}
		}
		if ( $matches ) {
			$params[ $param ] = in_array( $param, array( 'fuel_type', 'body_type', 'drive_type', 'extras', 'vehiclehistory' ), true ) ? implode( ',', array_unique( $matches ) ) : reset( $matches );
			$label = implode( ', ', array_map( static function ( $value ) use ( $param ) { return 'extras' === $param ? ucwords( str_replace( '_', ' ', $value ) ) : $value; }, array_unique( $matches ) ) );
			autoagora_global_search_add_chip( $chips, $param, $params[ $param ], $label );
		}
	}

	$fuel_matches = array();
	if ( preg_match( '/\b(?:plug in hybrid|plugin hybrid|phev)\b/u', $query ) ) {
		$fuel_matches = array( 'Plug-in petrol', 'Plug-in diesel' );
	} elseif ( autoagora_global_search_phrase_match( $query, 'petrol hybrid' ) ) {
		$fuel_matches = array( 'Petrol hybrid' );
	} elseif ( autoagora_global_search_phrase_match( $query, 'diesel hybrid' ) ) {
		$fuel_matches = array( 'Diesel hybrid' );
	} elseif ( preg_match( '/\b(?:hybrid|υβριδικο)\b/u', $query ) ) {
		$fuel_matches = array( 'Petrol hybrid', 'Diesel hybrid' );
	} elseif ( preg_match( '/\b(?:electric|ev|ηλεκτρικο)\b/u', $query ) ) {
		$fuel_matches = array( 'Electric' );
	} elseif ( preg_match( '/\b(?:petrol|gasoline|βενζινη)\b/u', $query ) ) {
		$fuel_matches = array( 'Petrol' );
	} elseif ( preg_match( '/\b(?:diesel|πετρελαιο)\b/u', $query ) ) {
		$fuel_matches = array( 'Diesel' );
	} elseif ( autoagora_global_search_phrase_match( $query, 'hydrogen' ) ) {
		$fuel_matches = array( 'Hydrogen' );
	} elseif ( autoagora_global_search_phrase_match( $query, 'natural gas' ) ) {
		$fuel_matches = array( 'Natural Gas' );
	} elseif ( autoagora_global_search_phrase_match( $query, 'bi fuel' ) ) {
		$fuel_matches = array( 'Bi Fuel' );
	}
	if ( $fuel_matches ) {
		$params['fuel_type'] = implode( ',', $fuel_matches );
		autoagora_global_search_add_chip( $chips, 'fuel_type', $params['fuel_type'], implode( ', ', $fuel_matches ) );
	}

	$colours = array( 'black' => 'Black', 'white' => 'White', 'silver' => 'Silver', 'gray' => 'Gray', 'grey' => 'Gray', 'red' => 'Red', 'blue' => 'Blue', 'green' => 'Green', 'yellow' => 'Yellow', 'brown' => 'Brown', 'beige' => 'Beige', 'orange' => 'Orange', 'purple' => 'Purple', 'gold' => 'Gold', 'bronze' => 'Bronze' );
	foreach ( $colours as $alias => $colour ) {
		if ( ! autoagora_global_search_phrase_match( $query, $alias ) ) {
			continue;
		}
		$is_interior = (bool) preg_match( '/\b' . preg_quote( $alias, '/' ) . '\s+(?:interior|inside|seats?)\b/u', $query ) || (bool) preg_match( '/\binterior\s+' . preg_quote( $alias, '/' ) . '\b/u', $query );
		$colour_key = $is_interior ? 'interior_color' : 'exterior_color';
		$params[ $colour_key ] = $colour;
		autoagora_global_search_add_chip( $chips, $colour_key, $colour, sprintf( $is_interior ? __( '%s interior', 'bricks-child' ) : __( '%s exterior', 'bricks-child' ), $colour ) );
		break;
	}
	if ( preg_match( '/\b(?:antique|classic registration|registered classic)\b/u', $query ) ) {
		$params['isantique'] = '1';
		autoagora_global_search_add_chip( $chips, 'isantique', '1', __( 'Registered antique', 'bricks-child' ) );
	}
	if ( autoagora_global_search_phrase_match( $query, 'in transit' ) ) {
		$params['availability'] = 'In Transit';
		autoagora_global_search_add_chip( $chips, 'availability', 'In Transit', __( 'In Transit', 'bricks-child' ) );
	} elseif ( autoagora_global_search_phrase_match( $query, 'in stock' ) ) {
		$params['availability'] = 'In Stock';
		autoagora_global_search_add_chip( $chips, 'availability', 'In Stock', __( 'In Stock', 'bricks-child' ) );
	}

	$mileage_pattern = '/(?:(under|below|up to|max(?:imum)?|less than|over|above|from|more than)\s*)?([\d,.]+(?:\s+\d{3})*)\s*(k)?\s*(?:km|kilomet(?:er|re)s?)/u';
	if ( preg_match( $mileage_pattern, $query, $match ) ) {
		$mileage_bound = ! empty( $match[1] ) && in_array( $match[1], array( 'over', 'above', 'from', 'more than' ), true ) ? 'min' : 'max';
		$mileage_value = autoagora_global_search_amount( $match[2], $match[3] ?? '' );
		$params[ 'mileage_' . $mileage_bound ] = $mileage_value;
		$mileage_label = 'min' === $mileage_bound ? sprintf( __( 'From %s km', 'bricks-child' ), number_format_i18n( $mileage_value ) ) : sprintf( __( 'Up to %s km', 'bricks-child' ), number_format_i18n( $mileage_value ) );
		autoagora_global_search_add_chip( $chips, 'mileage_' . $mileage_bound, $mileage_value, $mileage_label );
	}
	$price_query = preg_replace( $mileage_pattern, ' ', $query );
	$price_bound = '';
	if ( preg_match( '/(?:under|below|up to|max(?:imum)?|less than)\s*(?:€|eur)?\s*([\d,.]+(?:\s+\d{3})*)\s*(k)?/u', $price_query, $match ) || preg_match( '/(?:€|eur)\s*([\d,.]+(?:\s+\d{3})*)\s*(k)?/u', $price_query, $match ) || preg_match( '/\b([\d,.]+)\s*(k)\b/u', $price_query, $match ) ) {
		$price_bound = 'max';
	} elseif ( preg_match( '/(?:over|above|from|min(?:imum)?|more than)\s*(?:€|eur)?\s*([\d,.]+(?:\s+\d{3})*)\s*(k)?/u', $price_query, $match ) ) {
		$price_bound = 'min';
	}
	if ( $price_bound ) {
		$price_thousands = $match[2] ?? '';
		$price_value = autoagora_global_search_amount( $match[1], $price_thousands );
		$looks_like_year = empty( $price_thousands ) && false === strpos( $match[0], '€' ) && false === strpos( $match[0], 'eur' ) && $price_value >= 1948 && $price_value <= 2040;
		if ( ! $looks_like_year ) {
			$params[ 'price_' . $price_bound ] = $price_value;
			$price_label = 'max' === $price_bound ? sprintf( __( 'Up to €%s', 'bricks-child' ), number_format_i18n( $price_value ) ) : sprintf( __( 'From €%s', 'bricks-child' ), number_format_i18n( $price_value ) );
			autoagora_global_search_add_chip( $chips, 'price_' . $price_bound, $price_value, $price_label );
		}
	}

	$current_year = (int) gmdate( 'Y' ) + 1;
	if ( preg_match( '/\b(19[4-9]\d|20[0-3]\d)\s*(?:to|-)\s*(19[4-9]\d|20[0-3]\d)\b/u', $query, $match ) ) {
		$params['year_min'] = min( (int) $match[1], (int) $match[2] );
		$params['year_max'] = max( (int) $match[1], (int) $match[2] );
		autoagora_global_search_add_chip( $chips, 'year', $params['year_min'] . '-' . $params['year_max'], $params['year_min'] . '–' . $params['year_max'] );
	} elseif ( preg_match( '/(?:newer than|after|from)\s*(19[4-9]\d|20[0-3]\d)/u', $query, $match ) ) {
		$params['year_min'] = min( $current_year, (int) $match[1] + ( false !== strpos( $match[0], 'newer than' ) || false !== strpos( $match[0], 'after' ) ? 1 : 0 ) );
		autoagora_global_search_add_chip( $chips, 'year_min', $params['year_min'], sprintf( __( 'From %d', 'bricks-child' ), $params['year_min'] ) );
	} elseif ( preg_match( '/(?:older than|before|up to)\s*(19[4-9]\d|20[0-3]\d)/u', $query, $match ) ) {
		$params['year_max'] = (int) $match[1] - ( false !== strpos( $match[0], 'older than' ) || false !== strpos( $match[0], 'before' ) ? 1 : 0 );
		autoagora_global_search_add_chip( $chips, 'year_max', $params['year_max'], sprintf( __( 'Up to %d', 'bricks-child' ), $params['year_max'] ) );
	} elseif ( preg_match( '/\b(19[4-9]\d|20[0-3]\d)\b/u', $query, $match ) ) {
		$year = (int) $match[1];
		if ( $year <= $current_year ) {
			$params['year_min'] = $year;
			$params['year_max'] = $year;
			autoagora_global_search_add_chip( $chips, 'year', $year, (string) $year );
		}
	}

	if ( preg_match( '/\b(\d(?:\.\d)?)\s*(?:l|litre|liter)\b/u', $query, $match ) ) {
		$params['engine_capacity_min'] = $match[1];
		$params['engine_capacity_max'] = $match[1];
		autoagora_global_search_add_chip( $chips, 'engine', $match[1], $match[1] . 'L' );
	}
	if ( preg_match( '/\b(\d{2,4})\s*(?:hp|bhp)\b/u', $query, $match ) ) {
		$params['hp_min'] = (int) $match[1];
		$params['hp_max'] = (int) $match[1];
		autoagora_global_search_add_chip( $chips, 'hp', $match[1], $match[1] . ' hp' );
	}
	if ( preg_match( '/\b([2-8])\s*(?:seat|seater|seats)\b/u', $query, $match ) ) {
		$params['number_of_seats'] = $match[1];
		autoagora_global_search_add_chip( $chips, 'seats', $match[1], sprintf( __( '%s seats', 'bricks-child' ), $match[1] ) );
	}
	if ( preg_match( '/\b([2-7])\s*(?:door|doors)\b/u', $query, $match ) ) {
		$params['number_of_doors'] = $match[1];
		autoagora_global_search_add_chip( $chips, 'doors', $match[1], sprintf( __( '%s doors', 'bricks-child' ), $match[1] ) );
	}
	if ( preg_match( '/\b([1-9])\s*(?:owner|owners)\b/u', $query, $match ) ) {
		$params['numowners_max'] = (int) $match[1];
		autoagora_global_search_add_chip( $chips, 'owners', $match[1], sprintf( _n( 'Up to %s owner', 'Up to %s owners', (int) $match[1], 'bricks-child' ), $match[1] ) );
	}

	$params['search_query'] = sanitize_text_field( $raw_query );
	if ( count( $params ) === 1 ) {
		$params['car_search'] = sanitize_text_field( $raw_query );
	}
	$base = function_exists( 'autoagora_localized_page_url' ) ? autoagora_localized_page_url( 'cars' ) : home_url( '/cars/' );

	return array(
		'query'      => sanitize_text_field( $raw_query ),
		'params'     => $params,
		'chips'      => array_values( $chips ),
		'search_url' => add_query_arg( $params, $base ),
	);
}

function autoagora_global_search_query_tokens( $query ) {
	$stop = array( 'a', 'an', 'and', 'car', 'cars', 'for', 'in', 'me', 'near', 'of', 'the', 'to', 'up', 'used', 'with', 'under', 'below', 'over', 'above', 'from', 'than', 'max', 'maximum', 'minimum', 'find', 'show' );
	$tokens = array_filter( explode( ' ', autoagora_global_search_normalize( $query ) ) );
	$tokens = array_values( array_filter( $tokens, static function ( $token ) use ( $stop ) { return strlen( $token ) > 1 && ! in_array( $token, $stop, true ); } ) );
	return array_slice( array_unique( $tokens ), 0, 8 );
}

function autoagora_global_search_index_results( $query, $limit = 24, $type = '' ) {
	if ( ! autoagora_global_search_table_exists() ) {
		return array();
	}
	$tokens = autoagora_global_search_query_tokens( $query );
	if ( ! $tokens ) {
		return array();
	}

	global $wpdb;
	$table      = autoagora_global_search_table_name();
	$conditions = array();
	$rank       = array();
	foreach ( $tokens as $token ) {
		$like         = '%' . $wpdb->esc_like( $token ) . '%';
		$prefix       = $wpdb->esc_like( $token ) . '%';
		$conditions[] = $wpdb->prepare( 'search_text LIKE %s', $like );
		$rank[]       = $wpdb->prepare( '(CASE WHEN title LIKE %s THEN 25 WHEN search_text LIKE %s THEN 5 ELSE 0 END)', $prefix, $like );
	}
	$where = '(' . implode( ' OR ', $conditions ) . ')';
	$lang  = autoagora_global_search_current_language();
	if ( $lang ) {
		$where .= $wpdb->prepare( " AND (language = %s OR language = '')", $lang );
	}
	if ( $type ) {
		$where .= $wpdb->prepare( ' AND object_type = %s', sanitize_key( $type ) );
	}
	$type_rank = "CASE object_type WHEN 'make' THEN 60 WHEN 'model' THEN 55 WHEN 'car' THEN 50 WHEN 'dealer_profile' THEN 35 WHEN 'buyer_request' THEN 25 WHEN 'post' THEN 15 ELSE 10 END";
	$sql = 'SELECT object_type, object_id, title, subtitle, url, image_url, score, (' . implode( ' + ', $rank ) . ") + {$type_rank} AS relevance FROM {$table} WHERE {$where} ORDER BY relevance DESC, score DESC, updated_at DESC LIMIT " . absint( $limit );
	return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

function autoagora_global_search_matching_car_ids( $query, $limit = 500 ) {
	$rows = autoagora_global_search_index_results( $query, $limit, 'car' );
	if ( $rows ) {
		return array_map( 'absint', wp_list_pluck( $rows, 'object_id' ) );
	}
	$fallback = new WP_Query( array( 'post_type' => 'car', 'post_status' => 'publish', 's' => sanitize_text_field( $query ), 'fields' => 'ids', 'posts_per_page' => min( 100, absint( $limit ) ), 'no_found_rows' => true ) );
	return array_map( 'absint', $fallback->posts );
}

function autoagora_global_search_ajax() {
	check_ajax_referer( 'autoagora_global_search', 'nonce' );
	$query = isset( $_GET['q'] ) && is_scalar( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$query = function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 100 ) : substr( $query, 0, 100 );
	if ( strlen( autoagora_global_search_normalize( $query ) ) < 2 ) {
		wp_send_json_success( array( 'query' => $query, 'groups' => array(), 'chips' => array(), 'search_url' => '' ) );
	}

	$parsed   = autoagora_global_search_parse_query( $query );
	$revision = (string) get_option( 'autoagora_global_search_index_revision', '0' );
	$cache_key = 'aag_gs_' . md5( autoagora_global_search_current_language() . '|' . $revision . '|' . autoagora_global_search_normalize( $query ) );
	$rows = get_transient( $cache_key );
	if ( false === $rows ) {
		$rows = autoagora_global_search_index_results( $query );
		set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );
	}

	$labels = array(
		'make' => __( 'Makes and models', 'bricks-child' ), 'model' => __( 'Makes and models', 'bricks-child' ),
		'car' => __( 'Cars', 'bricks-child' ), 'dealer_profile' => __( 'Dealerships', 'bricks-child' ),
		'buyer_request' => __( 'Buyer requests', 'bricks-child' ), 'post' => __( 'Articles and pages', 'bricks-child' ),
		'page' => __( 'Articles and pages', 'bricks-child' ),
	);
	$groups = array();
	foreach ( $rows as $row ) {
		$group = isset( $labels[ $row['object_type'] ] ) ? $labels[ $row['object_type'] ] : __( 'Other', 'bricks-child' );
		if ( ! isset( $groups[ $group ] ) ) {
			$groups[ $group ] = array();
		}
		if ( count( $groups[ $group ] ) >= 5 ) {
			continue;
		}
		$groups[ $group ][] = array(
			'type' => sanitize_key( $row['object_type'] ), 'title' => $row['title'], 'subtitle' => $row['subtitle'],
			'url' => esc_url_raw( $row['url'] ), 'image' => esc_url_raw( $row['image_url'] ),
		);
	}
	wp_send_json_success(
		array(
			'query' => $query, 'groups' => $groups, 'chips' => $parsed['chips'],
			'search_url' => $parsed['search_url'], 'global_url' => autoagora_global_search_results_url( $query ),
		)
	);
}
add_action( 'wp_ajax_autoagora_global_search', 'autoagora_global_search_ajax' );
add_action( 'wp_ajax_nopriv_autoagora_global_search', 'autoagora_global_search_ajax' );

/** Search/filter result combinations are useful UX, but not indexable landing pages. */
function autoagora_global_search_is_results_request() {
	$has_query = autoagora_global_search_is_page()
		|| ( isset( $_GET['search_query'] ) && is_scalar( $_GET['search_query'] ) && '' !== wp_unslash( $_GET['search_query'] ) )
		|| ( isset( $_GET['car_search'] ) && is_scalar( $_GET['car_search'] ) && '' !== wp_unslash( $_GET['car_search'] ) );
	return autoagora_global_search_is_page()
		|| ( $has_query && function_exists( 'autoagora_is_cars_browse_light_context' ) && autoagora_is_cars_browse_light_context() );
}

function autoagora_global_search_wp_robots( array $robots ) {
	if ( ! autoagora_global_search_is_results_request() ) {
		return $robots;
	}
	unset( $robots['index'], $robots['nofollow'] );
	$robots['noindex'] = true;
	$robots['follow']  = true;
	return $robots;
}
add_filter( 'wp_robots', 'autoagora_global_search_wp_robots', 30 );

function autoagora_global_search_wpseo_robots( $robots ) {
	return autoagora_global_search_is_results_request() ? 'noindex,follow' : $robots;
}
add_filter( 'wpseo_robots', 'autoagora_global_search_wpseo_robots', 30 );

function autoagora_global_search_rank_math_robots( $robots ) {
	if ( ! autoagora_global_search_is_results_request() ) {
		return $robots;
	}
	if ( is_array( $robots ) ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
		return $robots;
	}
	return 'noindex,follow';
}
add_filter( 'rank_math/frontend/robots', 'autoagora_global_search_rank_math_robots', 30 );

function autoagora_global_search_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}
	$path = get_stylesheet_directory() . '/includes/global-search/';
	$url  = get_stylesheet_directory_uri() . '/includes/global-search/';
	wp_enqueue_style( 'autoagora-global-search', $url . 'global-search.css', array( 'bricks-child-theme-css' ), filemtime( $path . 'global-search.css' ) );
	wp_enqueue_script( 'autoagora-global-search', $url . 'global-search.js', array(), filemtime( $path . 'global-search.js' ), true );
	wp_localize_script(
		'autoagora-global-search',
		'autoagoraGlobalSearch',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'autoagora_global_search' ),
			'minChars' => 2, 'recentLabel' => __( 'Recent searches', 'bricks-child' ),
			'suggestedLabel' => __( 'Search cars', 'bricks-child' ), 'noResults' => __( 'No direct matches yet. Press Enter to search all cars.', 'bricks-child' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'autoagora_global_search_enqueue_assets', 31 );

function autoagora_global_search_icon( $name ) {
	if ( 'close' === $name ) {
		return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>';
	}
	return '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>';
}

function autoagora_global_search_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'context' => 'default', 'placeholder' => __( 'Try “Toyota hybrid under €20,000 in Limassol”', 'bricks-child' ) ), $atts, 'autoagora_global_search' );
	$context = in_array( $atts['context'], array( 'default', 'header', 'hero', 'overlay', 'browse', '404' ), true ) ? $atts['context'] : 'default';
	$id      = wp_unique_id( 'aag-global-search-' );
	$value   = isset( $_GET['q'] ) && is_scalar( $_GET['q'] )
		? sanitize_text_field( wp_unslash( $_GET['q'] ) )
		: ( isset( $_GET['search_query'] ) && is_scalar( $_GET['search_query'] )
		? sanitize_text_field( wp_unslash( $_GET['search_query'] ) )
		: ( isset( $_GET['car_search'] ) && is_scalar( $_GET['car_search'] ) ? sanitize_text_field( wp_unslash( $_GET['car_search'] ) ) : '' ) );
	$action  = autoagora_global_search_results_url();
	ob_start();
	?>
	<form class="aag-global-search aag-global-search--<?php echo esc_attr( $context ); ?>" role="search" method="get" action="<?php echo esc_url( $action ); ?>" data-aag-global-search>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Search AutoAgora', 'bricks-child' ); ?></label>
		<div class="aag-global-search__control">
			<span class="aag-global-search__icon"><?php echo autoagora_global_search_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<input id="<?php echo esc_attr( $id ); ?>" class="aag-global-search__input" type="search" name="q" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" autocomplete="off" spellcheck="false" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-results">
			<button class="aag-global-search__clear" type="button" aria-label="<?php esc_attr_e( 'Clear search', 'bricks-child' ); ?>"<?php echo $value ? '' : ' hidden'; ?>><?php echo autoagora_global_search_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<button class="aag-global-search__submit btn btn-primary" type="submit"><span><?php esc_html_e( 'Search', 'bricks-child' ); ?></span><?php echo autoagora_global_search_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</div>
		<div class="aag-global-search__results" id="<?php echo esc_attr( $id ); ?>-results" role="listbox" hidden>
			<div class="aag-global-search__status" aria-live="polite"></div>
			<div class="aag-global-search__results-content"></div>
		</div>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'autoagora_global_search', 'autoagora_global_search_shortcode' );

function autoagora_global_search_render_browse_strip() {
	if ( ! function_exists( 'autoagora_is_cars_browse_light_context' ) || ! autoagora_is_cars_browse_light_context() ) {
		return;
	}
	echo '<div class="aag-global-search-browse-strip"><div class="aag-global-search-browse-strip__inner">';
	echo do_shortcode( '[autoagora_global_search context="browse" placeholder="' . esc_attr__( 'Search make, model, price, city or features', 'bricks-child' ) . '"]' );
	echo '</div></div>';
}
add_action( 'wp_body_open', 'autoagora_global_search_render_browse_strip', 6 );

function autoagora_global_search_render_overlay() {
	if ( is_admin() ) {
		return;
	}
	?>
	<div class="aag-global-search-overlay" data-aag-search-overlay hidden>
		<div class="aag-global-search-overlay__backdrop" data-aag-search-close></div>
		<div class="aag-global-search-overlay__dialog" role="dialog" aria-modal="true" aria-labelledby="aag-search-overlay-title">
			<div class="aag-global-search-overlay__header">
				<h2 id="aag-search-overlay-title"><?php esc_html_e( 'Search AutoAgora', 'bricks-child' ); ?></h2>
				<button type="button" class="aag-global-search-overlay__close" data-aag-search-close aria-label="<?php esc_attr_e( 'Close search', 'bricks-child' ); ?>"><?php echo autoagora_global_search_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
			<?php echo do_shortcode( '[autoagora_global_search context="overlay"]' ); ?>
			<p class="aag-global-search-overlay__hint"><?php esc_html_e( 'Search by make, model, budget, year, mileage, city, fuel, body type or features.', 'bricks-child' ); ?></p>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'autoagora_global_search_render_overlay', 5 );
