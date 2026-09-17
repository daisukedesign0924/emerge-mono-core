<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_get_cpts() {
	$c = get_option( 'emcore_cpts', array() );
	return is_array( $c ) ? $c : array();
}

function emcore_save_cpts( $all ) {
	update_option( 'emcore_cpts', $all, false );
}

/** Public URL parent used by both the fixed listing page and individual entries. */
function emcore_cpt_parent_slug( $cpt, $key = '' ) {
	$slug = ! empty( $cpt['parent_slug'] ) ? $cpt['parent_slug'] : ( ! empty( $cpt['slug'] ) ? $cpt['slug'] : $key );
	return sanitize_title( $slug );
}

/** Existing sites used a fixed listing page, so that remains the default. */
function emcore_cpt_listing_mode( $cpt ) {
	return ( isset( $cpt['listing_mode'] ) && 'archive' === $cpt['listing_mode'] ) ? 'archive' : 'fixed_page';
}

function emcore_register_cpts() {
	foreach ( emcore_get_cpts() as $key => $cpt ) {
		$parent_slug = emcore_cpt_parent_slug( $cpt, $key );
		$listing_mode = emcore_cpt_listing_mode( $cpt );
		$labels = array(
			'name'          => $cpt['label'],
			'singular_name' => ! empty( $cpt['singular'] ) ? $cpt['singular'] : $cpt['label'],
			'add_new_item'  => '新規追加',
			'edit_item'     => '編集',
		);
		register_post_type(
			$key,
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => 'archive' === $listing_mode ? $parent_slug : false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'             => array( 'slug' => $parent_slug, 'with_front' => false ),
				'menu_icon'           => ! empty( $cpt['icon'] ) ? $cpt['icon'] : 'dashicons-portfolio',
			)
		);
		if ( ! empty( $cpt['has_cat'] ) ) {
			register_taxonomy(
				$key . '_cat',
				$key,
				array(
					'label'      => $cpt['label'] . 'カテゴリ',
					'public'     => true,
					'show_ui'    => true,
					'hierarchical' => true,
				)
			);
		}
	}
}
add_action( 'init', 'emcore_register_cpts' );

function emcore_cpt_single_template_id( $post_type ) {
	$cpts = emcore_get_cpts();
	if ( empty( $cpts[ $post_type ]['single_tpl'] ) ) {
		return '';
	}
	return $cpts[ $post_type ]['single_tpl'];
}

function emcore_cpt_archive_template_id( $post_type ) {
	$cpts = emcore_get_cpts();
	if ( empty( $cpts[ $post_type ]['archive_tpl'] ) ) {
		return '';
	}
	return $cpts[ $post_type ]['archive_tpl'];
}
