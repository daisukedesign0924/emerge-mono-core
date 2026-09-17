<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'emcore_admin_menu' );
function emcore_admin_menu() {
	$design_cap = emcore_design_capability();
	add_menu_page( 'Emerge Mono', 'Emerge Mono', 'edit_pages', 'emerge-mono-core', 'emcore_page_dashboard', 'dashicons-art', 3 );
	add_submenu_page( 'emerge-mono-core', 'ページ管理', 'ページ管理', 'edit_pages', 'emcore-pages', 'emcore_page_pages' );
	add_submenu_page( 'emerge-mono-core', 'Header', 'Header', 'edit_pages', 'emcore-shared-header', function () { emcore_page_shared_content( 'header' ); } );
	add_submenu_page( 'emerge-mono-core', 'Footer', 'Footer', 'edit_pages', 'emcore-shared-footer', function () { emcore_page_shared_content( 'footer' ); } );
	add_submenu_page( 'emerge-mono-core', 'Templates', 'Templates', $design_cap, 'emcore-templates', 'emcore_page_templates' );
	add_submenu_page( 'emerge-mono-core', 'Post Types', 'Post Types', $design_cap, 'emcore-cpts', 'emcore_page_cpts' );
	add_submenu_page( 'emerge-mono-core', 'Forms', 'Forms', $design_cap, 'emcore-forms', 'emcore_page_forms' );
	add_submenu_page( 'emerge-mono-core', 'システムページ', 'システムページ', 'edit_pages', 'emcore-system-pages', 'emcore_page_system_pages' );
	add_submenu_page( 'emerge-mono-core', 'HTMLルール', 'HTMLルール', $design_cap, 'emcore-html-rules', 'emcore_page_html_rules' );
	add_submenu_page( 'emerge-mono-core', 'Codex接続', 'Codex接続', $design_cap, 'emcore-codex-connections', 'emcore_page_mcp_connections' );
	add_submenu_page( 'emerge-mono-core', 'Template Edit', 'Template Edit', $design_cap, 'emcore-template-edit', 'emcore_page_template_edit' );
	add_submenu_page( 'emerge-mono-core', 'Content Edit', 'Content Edit', 'edit_pages', 'emcore-content-edit', 'emcore_page_content_edit' );
	add_submenu_page( 'emerge-mono-core', 'CPT List', 'CPT List', 'edit_pages', 'emcore-cpt-list', 'emcore_page_cpt_list' );
	add_submenu_page( 'emerge-mono-core', 'CPT Edit', 'CPT Edit', 'edit_pages', 'emcore-cpt-edit', 'emcore_page_cpt_edit' );
}

add_action( 'admin_head', function () {
	echo '<style>
	#adminmenu .wp-submenu a[href="admin.php?page=emcore-template-edit"],
	#adminmenu .wp-submenu a[href="admin.php?page=emcore-content-edit"],
	#adminmenu .wp-submenu a[href="admin.php?page=emcore-cpt-list"],
	#adminmenu .wp-submenu a[href="admin.php?page=emcore-cpt-edit"]{display:none!important}
	</style>';
} );

add_action( 'admin_post_emcore_download_spec', function () {
	if ( ! current_user_can( emcore_design_capability() ) ) { wp_die( '権限がありません。' ); }
	check_admin_referer( 'emcore_download_spec' );
	$file = EMCORE_PATH . 'docs/EMERGE-MONO-HTML-GUIDE-v1.3.md';
	if ( ! is_readable( $file ) ) { wp_die( '仕様書が見つかりません。' ); }
	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="EMERGE-MONO-HTML-GUIDE-v1.3.md"' );
	readfile( $file );
	exit;
} );

add_filter( 'admin_body_class', function ( $classes ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return $page === 'emcore-template-edit' ? $classes . ' emcore-editor-mode' : $classes;
} );

/** Journal がない環境ではCoreがSEO管理画面のシリーズ外装を担当する。 */
function emcore_owns_seo_shell() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return 'emerge-mono-seo' === $page && defined( 'EMSEO_VERSION' ) && ! defined( 'EMONO_VERSION' );
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page !== 'emerge-mono-core' && strpos( $page, 'emcore-' ) !== 0 && ! emcore_owns_seo_shell() ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'emcore-shell', EMCORE_URL . 'admin/assets/shell.css', array(), EMCORE_VERSION );
	wp_enqueue_script( 'emcore-admin', EMCORE_URL . 'admin/assets/admin.js', array( 'jquery' ), EMCORE_VERSION, true );
	wp_localize_script(
		'emcore-admin',
		'emcoreAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'homeUrl' => trailingslashit( home_url( '/' ) ),
			'nonce'   => wp_create_nonce( 'emcore_admin' ),
			'i18n'    => array(
				'error'   => 'エラーが発生しました',
				'confirm' => '削除しますか？',
			),
			'presets' => emcore_field_presets(),
			'pages'   => array_map(
				function ( $pg ) {
					return array(
						'id'    => $pg->ID,
						'title' => $pg->post_title,
						'url'   => get_permalink( $pg ),
					);
				},
				get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 100 ) )
			),
			'siteShared' => emcore_get_site_shared(),
		)
	);
} );

add_action( 'admin_head', function () {
	$p = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( strpos( $p, 'emcore' ) === 0 || $p === 'emerge-mono-core' || emcore_owns_seo_shell() ) {
		echo '<style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}#wpfooter{display:none}html.wp-toolbar{padding-top:0}#wpadminbar{display:none}#adminmenumain{display:none}#wpcontent,#wpfooter{margin-left:0}</style>';
	}
} );
