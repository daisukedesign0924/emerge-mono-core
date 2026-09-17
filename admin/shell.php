<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_shell_start( $title ) {
	$site_name = get_bloginfo( 'name' );
	echo '<div class="emcore-shell">';
	emcore_rail_render();
	echo '<div class="emcore-main">';
	echo '<div class="emcore-topbar">';
	echo '<div class="emcore-crumb"><h1>' . esc_html( $title ) . '</h1></div>';
	echo '<div class="emcore-topbar-spacer"></div>';
	echo '<a class="emcore-topbar-site" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-admin-home"></span>' . esc_html( $site_name ) . '</a>';
	echo '<span class="emcore-topbar-pill"><span class="emcore-topbar-dot"></span>LIVE</span>';
	if ( current_user_can( emcore_design_capability() ) ) {
		echo '<div class="emcore-workspace-switch" role="group" aria-label="表示するワークスペース">';
		echo '<button type="button" data-emcore-workspace-button="customize">カスタマイズ</button>';
		echo '<button type="button" data-emcore-workspace-button="manage">管理</button>';
		echo '</div>';
	}
	echo '</div>';
	echo '<div class="emcore-body">';
}

function emcore_back_link( $url, $label ) {
	return '<a class="emcore-back-link" href="' . esc_url( $url ) . '"><span aria-hidden="true">←</span>' . esc_html( preg_replace( '/^←\s*/u', '', $label ) ) . '</a>';
}

function emcore_shell_end() {
	echo '</div></div></div>';
}
