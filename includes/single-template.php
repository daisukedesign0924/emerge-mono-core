<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_single_canvas( $template ) {
	if ( ! is_singular() ) {
		return $template;
	}
	$post = get_queried_object();
	if ( ! $post || $post->post_type === 'page' || $post->post_type === 'post' ) {
		return $template;
	}
	$cpts = emcore_get_cpts();
	if ( ! isset( $cpts[ $post->post_type ] ) ) {
		return $template;
	}
	$tpl_id = emcore_cpt_single_template_id( $post->post_type );
	if ( ! $tpl_id || ! emcore_get_template( $tpl_id ) ) {
		return $template;
	}
	return EMCORE_PATH . 'includes/single-canvas-render.php';
}
add_filter( 'template_include', 'emcore_single_canvas', 99 );

function emcore_output_single_canvas() {
	$post   = get_queried_object();
	$tpl_id = emcore_cpt_single_template_id( $post->post_type );
	$tpl    = emcore_get_template( $tpl_id );
	$html = emcore_render_post_into_html( $tpl['html'], $post );
	emcore_output_html( $html );
}
