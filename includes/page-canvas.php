<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_page_canvas_file( $template ) {
	if ( ! is_singular( 'page' ) ) {
		return $template;
	}
	$post = get_queried_object();
	if ( ! $post ) {
		return $template;
	}
	$tpl_id = get_post_meta( $post->ID, '_emcore_tpl_id', true );
	if ( ! $tpl_id ) {
		return $template;
	}
	$tpl = emcore_get_template( $tpl_id );
	if ( ! $tpl ) {
		return $template;
	}
	return EMCORE_PATH . 'includes/page-canvas-render.php';
}
add_filter( 'template_include', 'emcore_page_canvas_file', 99 );

function emcore_output_page_canvas() {
	$post   = get_queried_object();
	$tpl_id = get_post_meta( $post->ID, '_emcore_tpl_id', true );
	$tpl    = emcore_get_template( $tpl_id );
	$html   = $tpl['html'];
	$html = emcore_render_post_into_html( $html, $post );
	emcore_output_html( $html );
}
