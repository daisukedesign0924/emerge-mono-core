<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_archive_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'cpt' => '' ), $atts );
	$key  = sanitize_key( $atts['cpt'] );
	if ( ! $key || ! post_type_exists( $key ) ) {
		return '';
	}
	$tpl_id = emcore_cpt_archive_template_id( $key );
	$tpl    = $tpl_id ? emcore_get_template( $tpl_id ) : null;
	if ( ! $tpl ) {
		return '';
	}
	$posts = get_posts(
		array(
			'post_type'      => $key,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$html = $tpl['html'];
	$page = get_queried_object();
	if ( $page && isset( $page->post_type ) && $page->post_type === 'page' ) {
		$html = emcore_render_post_into_html( $html, $page );
	}
	$html = emcore_render_archive_loop( $html, $posts );
	return $html;
}
add_shortcode( 'emcore_archive', 'emcore_archive_shortcode' );

function emcore_archive_page_canvas( $template ) {
	if ( ! is_singular( 'page' ) ) {
		return $template;
	}
	$post = get_queried_object();
	if ( ! $post ) {
		return $template;
	}
	$cpt = get_post_meta( $post->ID, '_emcore_archive_cpt', true );
	if ( ! $cpt ) {
		return $template;
	}
	return EMCORE_PATH . 'includes/archive-canvas-render.php';
}
add_filter( 'template_include', 'emcore_archive_page_canvas', 98 );

function emcore_output_archive_canvas() {
	$post = get_queried_object();
	$cpt  = get_post_meta( $post->ID, '_emcore_archive_cpt', true );
	$tpl_id = emcore_cpt_archive_template_id( $cpt );
	$tpl    = $tpl_id ? emcore_get_template( $tpl_id ) : null;
	if ( ! $tpl ) {
		status_header( 404 );
		echo 'Archive template missing';
		exit;
	}
	$posts = get_posts(
		array(
			'post_type'      => $cpt,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$html = emcore_render_post_into_html( $tpl['html'], $post );
	$html = emcore_render_archive_loop( $html, $posts );
	emcore_output_html( $html );
}
