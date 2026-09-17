<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Capability used by agency/design features. Filterable for custom roles. */
function emcore_design_capability() {
	return apply_filters( 'emcore_design_capability', 'emcore_manage_design' );
}

/** A stable document context for Emerge Mono add-ons (SEO/MEO/LLMO, etc.). */
function emcore_document_context( $post = null, $template_id = '', $kind = '' ) {
	$post = $post instanceof WP_Post ? $post : get_queried_object();
	$ctx  = array(
		'product'       => 'core',
		'content_type'  => $kind ? $kind : ( $post instanceof WP_Post ? $post->post_type : '' ),
		'post_id'       => $post instanceof WP_Post ? (int) $post->ID : 0,
		'template_id'   => sanitize_key( $template_id ),
		'title'         => $post instanceof WP_Post ? get_the_title( $post ) : get_bloginfo( 'name' ),
		'canonical'     => $post instanceof WP_Post ? get_permalink( $post ) : home_url( '/' ),
		'locale'        => get_locale(),
	);
	return apply_filters( 'emerge_mono_document_context', $ctx, $post );
}

function emcore_apply_document_extensions( $html, $context ) {
	$head = apply_filters( 'emerge_mono_document_head', '', $context );
	$body = apply_filters( 'emerge_mono_document_body_end', '', $context );
	if ( $head ) {
		$html = stripos( $html, '</head>' ) !== false ? str_ireplace( '</head>', $head . '</head>', $html ) : $head . $html;
	}
	if ( $body ) {
		$html = stripos( $html, '</body>' ) !== false ? str_ireplace( '</body>', $body . '</body>', $html ) : $html . $body;
	}
	return apply_filters( 'emerge_mono_document_html', $html, $context );
}
function emerge_mono_core_register_extension( $args ) {}
