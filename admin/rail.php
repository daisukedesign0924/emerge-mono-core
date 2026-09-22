<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_rail_render() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	echo '<aside class="emcore-rail">';
	echo '<div class="emcore-rail-brand"><img class="emcore-brand-logo" src="' . esc_url( EMCORE_URL . 'assets/img/emerge-mono-wordmark-white.webp' ) . '" alt="Emerge Mono"></div>';
	echo '<nav class="emcore-rail-nav">';
	echo '<a class="' . esc_attr( $page === 'emerge-mono-core' ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=emerge-mono-core' ) ) . '"><span class="emcore-rail-icon dashicons dashicons-dashboard"></span><span>ダッシュボード</span></a>';
	echo '<div class="emcore-workspace-group" data-emcore-workspace-group="manage" hidden>';
	echo '<div class="emcore-rail-sec">MANAGE</div>';
	foreach ( array( 'emcore-pages' => array( 'ページ管理', 'dashicons-admin-page' ) ) as $slug => $info ) {
		$cls = ( $page === $slug || ( 'emcore-pages' === $slug && 'emcore-content-edit' === $page ) ) ? 'is-active' : '';
		echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="emcore-rail-icon dashicons ' . esc_attr( $info[1] ) . '"></span><span>' . esc_html( $info[0] ) . '</span></a>';
	}
	echo '<a class="' . esc_attr( $page === 'emcore-system-pages' ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=emcore-system-pages' ) ) . '"><span class="emcore-rail-icon dashicons dashicons-layout"></span><span>システムページ</span></a>';
	echo '<div class="emcore-rail-sec">CONTENT</div>';
	foreach ( array( 'header' => array( 'Header', 'dashicons-align-wide' ), 'footer' => array( 'Footer', 'dashicons-align-full-width' ) ) as $location => $info ) {
		$slug = 'emcore-shared-' . $location;
		echo '<a class="' . esc_attr( $page === $slug ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="emcore-rail-icon dashicons ' . esc_attr( $info[1] ) . '"></span><span>' . esc_html( $info[0] ) . '</span></a>';
	}
	foreach ( emcore_get_cpts() as $key => $cpt ) {
		$cls = ( $page === 'emcore-cpt-list' && isset( $_GET['cpt'] ) && $_GET['cpt'] === $key ) ? 'is-active' : '';
		echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( admin_url( 'admin.php?page=emcore-cpt-list&cpt=' . $key ) ) . '"><span class="emcore-rail-icon dashicons dashicons-index-card"></span><span>' . esc_html( $cpt['label'] ) . '（各投稿）</span></a>';
	}
	if ( defined( 'EMSEO_VERSION' ) ) {
		echo '<div class="emcore-rail-sec">ANALYSIS</div>';
		echo '<a class="' . esc_attr( $page === 'emerge-mono-seo' ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=emerge-mono-seo' ) ) . '"><span class="emcore-rail-icon dashicons dashicons-chart-area"></span><span>SEO / AI</span></a>';
	}
	echo '</div>';
	if ( current_user_can( emcore_design_capability() ) ) {
		echo '<div class="emcore-workspace-group" data-emcore-workspace-group="customize">';
		echo '<div class="emcore-rail-sec">DESIGN</div>';
		foreach ( array( 'emcore-templates' => array( 'Templates', 'dashicons-media-code' ), 'emcore-cpts' => array( 'Post Types', 'dashicons-index-card' ), 'emcore-html-rules' => array( 'HTMLルール', 'dashicons-media-text' ) ) as $slug => $info ) {
			$cls = ( $page === $slug ) ? 'is-active' : '';
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="emcore-rail-icon dashicons ' . esc_attr( $info[1] ) . '"></span><span>' . esc_html( $info[0] ) . '</span></a>';
		}
		echo '<a class="' . esc_attr( $page === 'emcore-forms' ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=emcore-forms' ) ) . '"><span class="emcore-rail-icon dashicons dashicons-email-alt"></span><span>Forms</span></a>';
		echo '<a class="' . esc_attr( $page === 'emcore-codex-connections' ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=emcore-codex-connections' ) ) . '"><span class="emcore-rail-icon dashicons dashicons-admin-links"></span><span>Codex接続</span></a>';
		echo '</div>';
	}
	echo '</nav>';
	echo '<div class="emcore-rail-foot">';
	echo '<a href="' . esc_url( admin_url() ) . '"><span class="dashicons dashicons-wordpress"></span><span>WP管理画面へ</span></a>';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span><span>サイトを見る</span></a>';
	echo '</div></aside>';
}
