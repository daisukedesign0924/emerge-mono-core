<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function emcore_codex_permission() {
	if ( preg_match( '/^Basic\s/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '' ) ) { return false; }
	if ( function_exists( 'emcore_mcp_authenticate_bearer' ) ) { emcore_mcp_authenticate_bearer(); }
	return is_user_logged_in() && current_user_can( emcore_design_capability() );
}

function emcore_codex_error( $message, $status = 400 ) {
	return new WP_Error( 'emcore_codex_error', $message, array( 'status' => $status ) );
}

function emcore_codex_request_html( WP_REST_Request $request ) {
	$compressed = (string) $request->get_param( 'html_gzip_base64' );
	if ( '' !== $compressed ) {
		// gzip keeps complete HTML payloads small enough to pass stricter shared-hosting WAFs.
		if ( strlen( $compressed ) > 3 * 1024 * 1024 ) { return emcore_codex_error( 'HTML圧縮転送データが上限を超えています。', 413 ); }
		$bytes = base64_decode( $compressed, true );
		if ( false === $bytes || ! function_exists( 'gzdecode' ) ) { return emcore_codex_error( 'HTML圧縮転送データを復元できません。' ); }
		$html = gzdecode( $bytes );
		if ( false === $html ) { return emcore_codex_error( 'HTML圧縮転送データを展開できません。' ); }
		return $html;
	}
	$encoded = (string) $request->get_param( 'html_base64' );
	if ( '' !== $encoded ) {
		// Complete HTML often contains strings that shared-hosting WAFs mistake for attacks.
		// Base64 is transport-only; all ordinary validation still runs after strict decoding.
		if ( strlen( $encoded ) > 3 * 1024 * 1024 ) { return emcore_codex_error( 'HTML転送データが上限を超えています。', 413 ); }
		$html = base64_decode( $encoded, true );
		if ( false === $html ) { return emcore_codex_error( 'HTML転送データを復元できません。' ); }
		return $html;
	}
	return (string) $request->get_param( 'html' );
}

function emcore_codex_clean_html( $html ) {
	$html = (string) $html;
	if ( strlen( $html ) > 2 * 1024 * 1024 ) { return emcore_codex_error( 'HTMLは2MBまでです。', 413 ); }
	if ( preg_match( '/<\?(?:php|=)/i', $html ) ) { return emcore_codex_error( 'PHPコードを含むHTMLは登録できません。' ); }
	foreach ( emcore_diagnose_html( $html ) as $issue ) {
		if ( ( $issue['level'] ?? '' ) === 'error' ) { return emcore_codex_error( $issue['message'] ); }
	}
	return $html;
}

function emcore_codex_proposals() {
	$value = get_option( 'emcore_codex_proposals', array() );
	$value = is_array( $value ) ? $value : array();
	$now = time();
	return array_filter( $value, function ( $item ) use ( $now ) { return ! empty( $item['created'] ) && (int) $item['created'] > $now - DAY_IN_SECONDS; } );
}

function emcore_codex_save_proposals( $items ) {
	$items = array_slice( $items, -20, 20, true );
	update_option( 'emcore_codex_proposals', $items, false );
}

function emcore_codex_template_summary( $id, $template ) {
	return array(
		'id' => $id, 'name' => $template['name'] ?? $id, 'type' => $template['type'] ?? 'page',
		'bytes' => strlen( $template['html'] ?? '' ), 'sha256' => hash( 'sha256', $template['html'] ?? '' ),
		'updated_fields' => count( emcore_fields_from_html( $template['html'] ?? '' ) ),
	);
}

add_action( 'rest_api_init', function () {
	$ns = 'emerge-mono/v1';
	register_rest_route( $ns, '/status', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => function () {
		return rest_ensure_response( array( 'connected' => true, 'core_version' => EMCORE_VERSION, 'html_guide' => '1.3', 'site_name' => get_bloginfo( 'name' ), 'site_url' => home_url( '/' ), 'user' => wp_get_current_user()->display_name ) );
	} ) );
	register_rest_route( $ns, '/guide', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => function () {
		$file = EMCORE_PATH . 'docs/EMERGE-MONO-HTML-GUIDE-v1.3.md';
		return rest_ensure_response( array( 'version' => '1.3', 'markdown' => is_readable( $file ) ? file_get_contents( $file ) : '' ) );
	} ) );
	register_rest_route( $ns, '/templates', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => function () {
		$out = array(); foreach ( emcore_get_templates() as $id => $tpl ) { $out[] = emcore_codex_template_summary( $id, $tpl ); } return rest_ensure_response( $out );
	} ) );
	register_rest_route( $ns, '/templates/(?P<id>[a-zA-Z0-9_-]+)', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => function ( WP_REST_Request $request ) {
		$id = sanitize_key( $request['id'] ); $tpl = emcore_get_template( $id ); if ( ! $tpl ) { return emcore_codex_error( 'テンプレートが見つかりません。', 404 ); }
		return rest_ensure_response( array_merge( emcore_codex_template_summary( $id, $tpl ), array( 'html' => $tpl['html'] ) ) );
	} ) );
	register_rest_route( $ns, '/templates/(?P<id>[a-zA-Z0-9_-]+)/revisions', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_list_revisions' ) );
	register_rest_route( $ns, '/templates/(?P<id>[a-zA-Z0-9_-]+)/restore', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_restore_revision' ) );
	register_rest_route( $ns, '/validate', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => function ( WP_REST_Request $request ) {
		$html = emcore_codex_request_html( $request ); if ( is_wp_error( $html ) ) { return $html; }
		return rest_ensure_response( array( 'diagnostics' => emcore_diagnose_html( $html ), 'compliance' => emcore_emhtml_compliance( $html ), 'sha256' => hash( 'sha256', $html ), 'bytes' => strlen( $html ) ) );
	} ) );
	register_rest_route( $ns, '/proposals', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_create_proposal' ) );
	register_rest_route( $ns, '/proposals/(?P<id>[a-zA-Z0-9_-]+)', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_get_proposal' ) );
	register_rest_route( $ns, '/proposals/(?P<id>[a-zA-Z0-9_-]+)', array( 'methods' => 'DELETE', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_discard_proposal' ) );
	register_rest_route( $ns, '/proposals/(?P<id>[a-zA-Z0-9_-]+)/apply', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_apply_proposal' ) );
	register_rest_route( $ns, '/pages', array( 'methods' => 'GET', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_list_pages' ) );
	register_rest_route( $ns, '/pages', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_create_page' ) );
	register_rest_route( $ns, '/media', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_codex_upload_media' ) );
	register_rest_route( $ns, '/mcp/pairings', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'emcore_mcp_create_pairing' ) );
	register_rest_route( $ns, '/mcp/pairings/(?P<id>[a-f0-9]{64})', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'emcore_mcp_poll_pairing' ) );
	register_rest_route( $ns, '/mcp/token', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'emcore_mcp_exchange_token' ) );
	register_rest_route( $ns, '/mcp/disconnect', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_mcp_disconnect_current' ) );
	register_rest_route( $ns, '/mcp', array( 'methods' => 'POST', 'permission_callback' => 'emcore_codex_permission', 'callback' => 'emcore_mcp_request' ) );
} );

function emcore_codex_create_proposal( WP_REST_Request $request ) {
	$html = emcore_codex_request_html( $request ); if ( is_wp_error( $html ) ) { return $html; }
	$html = emcore_codex_clean_html( $html ); if ( is_wp_error( $html ) ) { return $html; }
	$template_id = sanitize_key( $request->get_param( 'template_id' ) ); $current = $template_id ? emcore_get_template( $template_id ) : null;
	if ( $template_id && ! $current ) { return emcore_codex_error( '更新対象のテンプレートが見つかりません。', 404 ); }
	$type = sanitize_key( $request->get_param( 'type' ) ?: ( $current['type'] ?? 'page' ) ); if ( ! in_array( $type, array( 'page', 'archive', 'single' ), true ) ) { return emcore_codex_error( 'テンプレート種類が不正です。' ); }
	$id = 'change_' . substr( md5( wp_generate_uuid4() ), 0, 12 ); $items = emcore_codex_proposals();
	$items[ $id ] = array( 'id' => $id, 'template_id' => $template_id, 'name' => sanitize_text_field( $request->get_param( 'name' ) ?: ( $current['name'] ?? 'New Template' ) ), 'type' => $type, 'html' => $html, 'original_sha256' => hash( 'sha256', $current['html'] ?? '' ), 'created' => time(), 'user' => get_current_user_id() );
	emcore_codex_save_proposals( $items );
	return rest_ensure_response( array( 'proposal_id' => $id, 'mode' => $current ? 'update' : 'create', 'name' => $items[ $id ]['name'], 'original_bytes' => strlen( $current['html'] ?? '' ), 'proposed_bytes' => strlen( $html ), 'html_sha256' => hash( 'sha256', $html ), 'diagnostics' => emcore_diagnose_html( $html ), 'compliance' => emcore_emhtml_compliance( $html ), 'expires_at' => gmdate( 'c', time() + DAY_IN_SECONDS ), 'requires_confirmation' => true ) );
}

function emcore_codex_get_proposal( WP_REST_Request $request ) {
	$items = emcore_codex_proposals(); $id = sanitize_key( $request['id'] ); if ( empty( $items[ $id ] ) ) { return emcore_codex_error( '変更案が見つからないか、有効期限が切れています。', 404 ); }
	$item = $items[ $id ]; return rest_ensure_response( array( 'id' => $id, 'template_id' => $item['template_id'], 'name' => $item['name'], 'type' => $item['type'], 'html' => $item['html'], 'sha256' => hash( 'sha256', $item['html'] ), 'requires_confirmation' => true ) );
}

function emcore_codex_discard_proposal( WP_REST_Request $request ) {
	$items = emcore_codex_proposals(); $id = sanitize_key( $request['id'] );
	if ( empty( $items[ $id ] ) ) { return emcore_codex_error( '変更案が見つかりません。', 404 ); }
	unset( $items[ $id ] ); emcore_codex_save_proposals( $items );
	return rest_ensure_response( array( 'discarded' => true, 'proposal_id' => $id ) );
}

function emcore_codex_list_revisions( WP_REST_Request $request ) {
	$template_id = sanitize_key( $request['id'] );
	if ( ! emcore_get_template( $template_id ) ) { return emcore_codex_error( 'テンプレートが見つかりません。', 404 ); }
	$out = array();
	foreach ( emcore_get_template_revisions( $template_id ) as $index => $revision ) {
		$out[] = array( 'index' => $index, 'time' => $revision['time'] ?? '', 'user' => (int) ( $revision['user'] ?? 0 ), 'name' => $revision['name'] ?? $template_id, 'type' => $revision['type'] ?? 'page', 'bytes' => strlen( $revision['html'] ?? '' ), 'sha256' => hash( 'sha256', $revision['html'] ?? '' ) );
	}
	return rest_ensure_response( $out );
}

function emcore_codex_restore_revision( WP_REST_Request $request ) {
	if ( true !== filter_var( $request->get_param( 'confirm' ), FILTER_VALIDATE_BOOLEAN ) ) { return emcore_codex_error( 'confirm=trueによる明示的な承認が必要です。', 409 ); }
	$template_id = sanitize_key( $request['id'] ); $index = absint( $request->get_param( 'index' ) );
	$all = emcore_get_templates(); $revisions = emcore_get_template_revisions( $template_id );
	if ( empty( $all[ $template_id ] ) ) { return emcore_codex_error( 'テンプレートが見つかりません。', 404 ); }
	if ( ! isset( $revisions[ $index ] ) ) { return emcore_codex_error( '指定した履歴が見つかりません。', 404 ); }
	emcore_add_template_revision( $template_id, $all[ $template_id ] );
	$revision = $revisions[ $index ];
	$all[ $template_id ] = array_merge( $all[ $template_id ], array( 'name' => $revision['name'] ?? $all[ $template_id ]['name'], 'type' => $revision['type'] ?? $all[ $template_id ]['type'], 'html' => $revision['html'], 'shared' => $revision['shared'] ?? array() ) );
	emcore_save_templates( $all );
	return rest_ensure_response( array( 'restored' => true, 'template_id' => $template_id, 'sha256' => hash( 'sha256', $revision['html'] ) ) );
}

function emcore_codex_apply_proposal( WP_REST_Request $request ) {
	if ( true !== filter_var( $request->get_param( 'confirm' ), FILTER_VALIDATE_BOOLEAN ) ) { return emcore_codex_error( 'confirm=trueによる明示的な承認が必要です。', 409 ); }
	$items = emcore_codex_proposals(); $id = sanitize_key( $request['id'] ); if ( empty( $items[ $id ] ) ) { return emcore_codex_error( '変更案が見つかりません。', 404 ); }
	$item = $items[ $id ]; $all = emcore_get_templates(); $template_id = $item['template_id'];
	if ( $template_id ) {
		if ( empty( $all[ $template_id ] ) ) { return emcore_codex_error( 'テンプレートが削除されています。', 409 ); }
		if ( hash( 'sha256', $all[ $template_id ]['html'] ?? '' ) !== $item['original_sha256'] ) { return emcore_codex_error( '変更案作成後にテンプレートが更新されています。新しいHTMLを取得し直してください。', 409 ); }
		emcore_add_template_revision( $template_id, $all[ $template_id ] ); $all[ $template_id ]['html'] = $item['html']; $all[ $template_id ]['name'] = $item['name']; $all[ $template_id ]['type'] = $item['type'];
	} else {
		$template_id = emcore_make_tpl_id(); $hints = emcore_template_hints_from_html( $item['html'], $item['name'] );
		$all[ $template_id ] = array( 'name' => $item['name'], 'type' => $item['type'], 'html' => emcore_prepare_import_html( $item['html'], $item['type'], $hints['post_type'] ), 'shared' => array() );
	}
	emcore_save_templates( $all ); unset( $items[ $id ] ); emcore_codex_save_proposals( $items );
	return rest_ensure_response( array( 'applied' => true, 'template_id' => $template_id, 'message' => '承認された変更を反映しました。' ) );
}

function emcore_codex_list_pages() {
	$out = array(); foreach ( emcore_get_content_pages() as $page ) { $out[] = array( 'id' => $page->ID, 'title' => $page->post_title, 'slug' => $page->post_name, 'status' => $page->post_status, 'template_id' => emcore_template_id_for_page( $page ), 'url' => get_permalink( $page ) ); } return rest_ensure_response( $out );
}

function emcore_codex_create_page( WP_REST_Request $request ) {
	$template_id = sanitize_key( $request->get_param( 'template_id' ) ); if ( ! emcore_get_template( $template_id ) ) { return emcore_codex_error( 'テンプレートが見つかりません。', 404 ); }
	$title = sanitize_text_field( $request->get_param( 'title' ) ); $slug = sanitize_title( $request->get_param( 'slug' ) ); if ( ! $title || ! $slug ) { return emcore_codex_error( 'タイトルとスラッグが必要です。' ); }
	$conflicts = emcore_public_slug_conflicts( $slug ); if ( $conflicts ) { return emcore_codex_error( emcore_slug_conflict_message( $slug, $conflicts ), 409 ); }
	$status = $request->get_param( 'status' ) === 'publish' ? 'publish' : 'draft'; $post_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status ), true );
	if ( is_wp_error( $post_id ) ) { return $post_id; } update_post_meta( $post_id, '_emcore_tpl_id', $template_id );
	return rest_ensure_response( array( 'created' => true, 'page_id' => $post_id, 'status' => $status, 'url' => get_permalink( $post_id ), 'edit_url' => admin_url( 'admin.php?page=emcore-content-edit&post_id=' . $post_id ) ) );
}

function emcore_codex_upload_media( WP_REST_Request $request ) {
	$name = sanitize_file_name( $request->get_param( 'file_name' ) ); $mime = sanitize_mime_type( $request->get_param( 'mime_type' ) ); $encoded = (string) $request->get_param( 'content_base64' );
	if ( ! $name || ! $encoded ) { return emcore_codex_error( 'file_nameとcontent_base64が必要です。' ); }
	$bytes = base64_decode( $encoded, true ); if ( false === $bytes || strlen( $bytes ) > 8 * 1024 * 1024 ) { return emcore_codex_error( '画像データが不正、または8MBを超えています。', 413 ); }
	$allowed = get_allowed_mime_types(); if ( ! in_array( $mime, $allowed, true ) || strpos( $mime, 'image/' ) !== 0 ) { return emcore_codex_error( '許可されていない画像形式です。' ); }
	$upload = wp_upload_bits( $name, null, $bytes ); if ( ! empty( $upload['error'] ) ) { return emcore_codex_error( $upload['error'] ); }
	$checked = wp_check_filetype_and_ext( $upload['file'], $name, $allowed );
	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) || $checked['type'] !== $mime ) { wp_delete_file( $upload['file'] ); return emcore_codex_error( '画像の実体、拡張子、MIMEタイプが一致しません。', 415 ); }
	$attachment_id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ), 'post_status' => 'inherit' ), $upload['file'], 0, true );
	if ( is_wp_error( $attachment_id ) ) { return $attachment_id; }
	require_once ABSPATH . 'wp-admin/includes/image.php'; wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	return rest_ensure_response( array( 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ), 'file_name' => $name ) );
}
