<?php
/**
 * Lightweight frontend translations for code-generated marketplace UI.
 *
 * @package Bricks Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the active frontend language slug.
 */
function autoagora_current_language() {
	if ( function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
		if ( is_string( $language ) && $language !== '' ) {
			return $language;
		}
	}

	return substr( determine_locale(), 0, 2 );
}

/**
 * Return code-owned translations for the active language.
 */
function autoagora_translation_catalog( $language = null ) {
	$language = $language ?: autoagora_current_language();
	static $loaded_catalogs = array();

	if ( isset( $loaded_catalogs[ $language ] ) ) {
		return $loaded_catalogs[ $language ];
	}

	$catalogs = array(
		'el' => get_stylesheet_directory() . '/includes/i18n/translations/el.php',
	);

	if ( ! isset( $catalogs[ $language ] ) || ! file_exists( $catalogs[ $language ] ) ) {
		$loaded_catalogs[ $language ] = array();
		return $loaded_catalogs[ $language ];
	}

	$translations = require $catalogs[ $language ];
	$loaded_catalogs[ $language ] = is_array( $translations ) ? $translations : array();
	return $loaded_catalogs[ $language ];
}

/**
 * Translate strings in the child theme's existing gettext calls.
 */
function autoagora_translate_child_theme_string( $translation, $text, $domain ) {
	if ( $domain !== 'bricks-child' ) {
		return $translation;
	}

	$catalog = autoagora_translation_catalog();
	return isset( $catalog[ $text ] ) ? $catalog[ $text ] : $translation;
}
// Temporarily disabled until the code-owned translation catalog is complete.
// add_filter( 'gettext_bricks-child', 'autoagora_translate_child_theme_string', 10, 3 );

/**
 * Translate singular/plural strings in the child theme.
 */
function autoagora_translate_child_theme_plural( $translation, $single, $plural, $number ) {
	$catalog = autoagora_translation_catalog();
	$key     = (int) $number === 1 ? $single : $plural;

	return isset( $catalog[ $key ] ) ? $catalog[ $key ] : $translation;
}
// Temporarily disabled until the code-owned translation catalog is complete.
// add_filter( 'ngettext_bricks-child', 'autoagora_translate_child_theme_plural', 10, 4 );

/**
 * Resolve a translated page URL while retaining an English fallback.
 */
function autoagora_localized_page_url( $english_slug = '' ) {
	$english_slug = trim( (string) $english_slug, '/' );
	$language = autoagora_current_language();

	if ( $english_slug === '' ) {
		if ( function_exists( 'pll_home_url' ) ) {
			$home_url = pll_home_url( $language );
			if ( is_string( $home_url ) && $home_url !== '' ) {
				return $home_url;
			}
		}
		return home_url( '/' );
	}

	$source_page = get_page_by_path( $english_slug );
	if ( $source_page instanceof WP_Post && function_exists( 'pll_get_post' ) ) {
		$translated_id = pll_get_post( $source_page->ID, $language );
		if ( $translated_id ) {
			return get_permalink( $translated_id );
		}
	}

	return home_url( '/' . $english_slug . '/' );
}
