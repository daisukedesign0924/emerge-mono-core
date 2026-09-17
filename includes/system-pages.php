<?php
/** Core-owned pages rendered inside an imported HTML page shell. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function emcore_system_page_types() {
	return array(
		'contact'     => array( 'label' => 'お問い合わせ', 'slug' => 'contact', 'title' => 'お問い合わせ', 'body' => '必要事項をご入力のうえ送信してください。' ),
		'404'         => array( 'label' => '404', 'slug' => '', 'title' => 'ページが見つかりません', 'body' => 'URLが変更されたか、ページが削除された可能性があります。' ),
	);
}

function emcore_system_pages_settings() {
	$saved = get_option( 'emcore_system_pages', array() );
	$out = array();
	foreach ( emcore_system_page_types() as $key => $defaults ) {
		$row = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
		$out[ $key ] = wp_parse_args( $row, array(
			'enabled' => true,
			'slug' => $defaults['slug'], 'title' => $defaults['title'], 'body' => $defaults['body'],
			'template_id' => '', 'page_id' => 0, 'button_label' => 'ホームへ戻る', 'button_url' => home_url( '/' ),
		) );
	}
	return $out;
}

/** Keep previously generated pages, but return retired types to ordinary WordPress pages. */
function emcore_release_removed_system_pages() {
	$active = array_keys( emcore_system_page_types() );
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => '_emcore_system_page' ) );
	foreach ( $pages as $page ) { $type = sanitize_key( get_post_meta( $page->ID, '_emcore_system_page', true ) ); if ( $type && ! in_array( $type, $active, true ) ) { delete_post_meta( $page->ID, '_emcore_system_page' ); } }
}

function emcore_system_form_fields( $type = 'contact' ) {
	$type = 'contact';
	$defaults = array(
		array( 'key' => 'name', 'label' => 'お名前', 'type' => 'text', 'required' => true, 'placeholder' => '山田 太郎' ),
		array( 'key' => 'email', 'label' => 'メールアドレス', 'type' => 'email', 'required' => true, 'placeholder' => 'mail@example.com' ),
		array( 'key' => 'tel', 'label' => '電話番号', 'type' => 'tel', 'required' => false, 'placeholder' => '090-0000-0000' ),
		array( 'key' => 'message', 'label' => 'お問い合わせ内容', 'type' => 'textarea', 'required' => true, 'placeholder' => '' ),
	);
	$all = get_option( 'emcore_system_form_fields', array() );
	$legacy = get_option( 'emcore_system_contact_fields', array() );
	$fields = isset( $all[ $type ] ) && is_array( $all[ $type ] ) ? $all[ $type ] : ( 'contact' === $type && is_array( $legacy ) && $legacy ? $legacy : $defaults );
	return is_array( $fields ) && $fields ? $fields : $defaults;
}

function emcore_system_contact_fields() { return emcore_system_form_fields( 'contact' ); }

add_filter( 'query_vars', function ( $vars ) { $vars[] = 'emcore_system_page'; return $vars; } );
add_action( 'init', 'emcore_system_pages_rewrites', 20 );
function emcore_system_pages_rewrites() {
	foreach ( emcore_system_pages_settings() as $key => $set ) {
		if ( empty( $set['enabled'] ) || ! empty( $set['page_id'] ) || empty( $set['slug'] ) || '404' === $key ) { continue; }
		add_rewrite_rule( '^' . preg_quote( trim( $set['slug'], '/' ), '/' ) . '/?$', 'index.php?emcore_system_page=' . $key, 'top' );
	}
}

add_filter( 'template_include', function ( $template ) {
	$key = get_query_var( 'emcore_system_page' );
	$sets = emcore_system_pages_settings();
	if ( ! $key && is_singular( 'page' ) ) { $page = get_queried_object(); if ( $page instanceof WP_Post ) { $key = sanitize_key( get_post_meta( $page->ID, '_emcore_system_page', true ) ); if ( $key ) { set_query_var( 'emcore_system_page', $key ); } } }
	if ( $key && ! empty( $sets[ $key ]['enabled'] ) ) { return EMCORE_PATH . 'includes/system-page-render.php'; }
	if ( is_404() && ! empty( $sets['404']['enabled'] ) ) { set_query_var( 'emcore_system_page', '404' ); return EMCORE_PATH . 'includes/system-page-render.php'; }
	return $template;
}, 120 );

function emcore_system_page_shell( $key, $set ) {
	$tpl = ! empty( $set['template_id'] ) ? emcore_get_template( $set['template_id'] ) : null;
	$tpl_id = ! empty( $set['template_id'] ) ? $set['template_id'] : '';
	if ( ! $tpl ) {
		foreach ( emcore_get_templates() as $candidate_id => $candidate ) { if ( ( $candidate['type'] ?? 'page' ) === 'page' ) { $tpl = $candidate; $tpl_id = $candidate_id; break; } }
	}
	return $tpl && ! empty( $tpl['html'] ) ? emcore_apply_shared_to_html( $tpl['html'], $tpl_id ) : '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title></title></head><body><main data-em-slot="content"></main></body></html>';
}

function emcore_system_page_content( $key, $set ) {
	$title = '<p class="emcore-system-kicker">' . ( 'contact' === $key ? 'CONTACT' : 'NOT FOUND' ) . '</p><h1 class="emcore-system-title">' . esc_html( $set['title'] ) . '</h1>';
	$intro = $set['body'] !== '' ? '<div class="emcore-system-body">' . wpautop( wp_kses_post( $set['body'] ) ) . '</div>' : '';
	if ( 'contact' === $key ) {
		$form_key = 'system-' . $key;
		$notice = ''; if ( isset( $_GET['form'] ) ) { $status = sanitize_key( wp_unslash( $_GET['form'] ) ); if ( in_array( $status, array( 'sent', 'error' ), true ) ) { $notice = '<div class="emcore-system-notice is-' . esc_attr( $status ) . '" role="status">' . ( 'sent' === $status ? '送信しました。お問い合わせありがとうございます。' : '送信できませんでした。入力内容を確認して、もう一度お試しください。' ) . '</div>'; } }
		$form = $notice . '<form class="emcore-system-form" data-em-system-form="' . esc_attr( $form_key ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		$form .= '<input type="hidden" name="action" value="emcore_submit_system_form"><input type="hidden" name="emcore_form_key" value="' . esc_attr( $form_key ) . '">' . wp_nonce_field( 'emcore_submit_system_form_' . $form_key, 'emcore_form_nonce', true, false );
		$form .= '<div class="emcore-hp" aria-hidden="true"><label>Website<input name="emcore_website" tabindex="-1" autocomplete="off"></label></div>';
		foreach ( emcore_system_form_fields( $key ) as $field ) {
			$type = in_array( $field['type'] ?? '', array( 'text', 'email', 'tel', 'textarea' ), true ) ? $field['type'] : 'text';
			$form .= '<div class="emcore-system-field"><label for="emcore-field-' . esc_attr( sanitize_key( $field['key'] ) ) . '">' . esc_html( $field['label'] ?? $field['key'] ) . ( ! empty( $field['required'] ) ? ' <em aria-label="必須">*</em>' : '<small>任意</small>' ) . '</label>';
			$attrs = ' id="emcore-field-' . esc_attr( sanitize_key( $field['key'] ) ) . '" name="' . esc_attr( sanitize_key( $field['key'] ) ) . '" placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '"' . ( ! empty( $field['required'] ) ? ' required' : '' );
			$form .= 'textarea' === $type ? '<textarea rows="6"' . $attrs . '></textarea>' : '<input type="' . esc_attr( $type ) . '"' . $attrs . '>';
			$form .= '</div>';
		}
		$privacy = get_page_by_path( 'privacy-policy' ); $privacy_text = $privacy ? '<a href="' . esc_url( get_permalink( $privacy ) ) . '" target="_blank" rel="noopener">プライバシーポリシー</a>に同意する' : 'プライバシーポリシーに同意する';
		$form .= '<label class="emcore-system-consent"><input type="checkbox" name="privacy_consent" value="1" required><span>' . $privacy_text . ' <em aria-label="必須">*</em></span></label><button type="submit"><span>送信する</span><span aria-hidden="true">→</span></button></form>';
		return '<section class="emcore-system-page emcore-system-' . esc_attr( $key ) . '"><div class="emcore-system-inner"><header class="emcore-system-heading">' . $title . $intro . '</header>' . $form . '</div></section>';
	}
	$button = '404' === $key ? '<p><a class="emcore-system-button" href="' . esc_url( $set['button_url'] ) . '">' . esc_html( $set['button_label'] ) . '</a></p>' : '';
	return '<section class="emcore-system-page emcore-system-' . esc_attr( $key ) . '"><div class="emcore-system-inner"><header class="emcore-system-heading">' . $title . $intro . '</header>' . $button . '</div></section>';
}

function emcore_replace_content_slot( $html, $content ) {
	if ( ! class_exists( 'DOMDocument' ) ) { return preg_replace( '/<main\b[^>]*>.*?<\/main>/is', '<main data-em-slot="content">' . $content . '</main>', $html, 1 ); }
	$html = emcore_protect_raw_html_blocks( $html, $raw );
	libxml_use_internal_errors( true ); $dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ); $xp = new DOMXPath( $dom );
	$nodes = $xp->query( '//*[@data-em-slot="content"]' );
	$target = $nodes->length ? $nodes->item( 0 ) : null;
	if ( ! $target ) {
		$header = $xp->query( '//body/header[1]' )->item( 0 ); $footer = $xp->query( '//body/footer[1]' )->item( 0 );
		if ( $header && $footer && $header->parentNode === $footer->parentNode ) {
			$old_main = null; $cursor = $header->nextSibling;
			while ( $cursor && $cursor !== $footer ) { if ( $cursor instanceof DOMElement && 'main' === strtolower( $cursor->tagName ) ) { $old_main = $cursor; break; } $cursor = $cursor->nextSibling; }
			$target = $dom->createElement( 'main' );
			if ( $old_main ) { foreach ( iterator_to_array( $old_main->attributes ) as $attribute ) { $target->setAttribute( $attribute->nodeName, $attribute->nodeValue ); } }
			$cursor = $header->nextSibling; while ( $cursor && $cursor !== $footer ) { $next = $cursor->nextSibling; $cursor->parentNode->removeChild( $cursor ); $cursor = $next; }
			$footer->parentNode->insertBefore( $target, $footer );
		}
	}
	if ( ! $target ) { $nodes = $xp->query( '//main[1]' ); if ( ! $nodes->length ) { $nodes = $xp->query( '//*[@role="main"][1]' ); } $target = $nodes->length ? $nodes->item( 0 ) : null; }
	if ( ! $target ) { $body = $xp->query( '//body' )->item( 0 ); $target = $dom->createElement( 'main' ); $body->appendChild( $target ); }
	while ( $target->firstChild ) { $target->removeChild( $target->firstChild ); }
	$fragment_doc = new DOMDocument(); $fragment_doc->loadHTML( '<?xml encoding="utf-8"><div id="emcore-fragment">' . $content . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
	$fragment = ( new DOMXPath( $fragment_doc ) )->query( '//*[@id="emcore-fragment"]' )->item( 0 );
	foreach ( iterator_to_array( $fragment->childNodes ) as $child ) { $target->appendChild( $dom->importNode( $child, true ) ); }
	$target->setAttribute( 'data-em-slot', 'content' );
	$link = $dom->createElement( 'link' ); $link->setAttribute( 'rel', 'stylesheet' ); $link->setAttribute( 'href', EMCORE_URL . 'assets/css/system-pages.css?ver=' . rawurlencode( EMCORE_VERSION ) );
	$head = $xp->query( '//head' )->item( 0 ); if ( $head ) { $head->appendChild( $link ); }
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $dom->saveHTML() ); libxml_clear_errors();
	return emcore_restore_raw_html_blocks( $out, $raw );
}

function emcore_output_system_page() {
	$key = sanitize_key( get_query_var( 'emcore_system_page' ) ); $sets = emcore_system_pages_settings();
	if ( empty( $sets[ $key ] ) ) { return; }
	if ( '404' === $key ) { status_header( 404 ); nocache_headers(); }
	$html = emcore_system_page_shell( $key, $sets[ $key ] );
	$html = emcore_replace_content_slot( $html, emcore_system_page_content( $key, $sets[ $key ] ) );
	$html = preg_replace( '/<title>.*?<\/title>/is', '<title>' . esc_html( $sets[ $key ]['title'] ) . ' — ' . esc_html( get_bloginfo( 'name' ) ) . '</title>', $html, 1 );
	emcore_output_html( $html );
}

add_action( 'admin_post_emcore_submit_system_form', 'emcore_submit_system_form' );
add_action( 'admin_post_nopriv_emcore_submit_system_form', 'emcore_submit_system_form' );
function emcore_submit_system_form() {
	$key = isset( $_POST['emcore_form_key'] ) ? sanitize_key( wp_unslash( $_POST['emcore_form_key'] ) ) : '';
	$fail = function () { wp_safe_redirect( add_query_arg( 'form', 'error', wp_get_referer() ?: home_url( '/' ) ) ); exit; };
	if ( 'system-contact' !== $key ) { $fail(); }
	if ( empty( $_POST['emcore_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['emcore_form_nonce'] ) ), 'emcore_submit_system_form_' . $key ) || ! empty( $_POST['emcore_website'] ) || empty( $_POST['privacy_consent'] ) ) { $fail(); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; $rate = 'emcore_sys_form_' . md5( $key . '|' . $ip );
	if ( get_transient( $rate ) ) { $fail(); }
	$lines = array(); $email = '';
	$type = str_replace( 'system-', '', $key );
	foreach ( emcore_system_form_fields( $type ) as $field ) {
		$fkey = sanitize_key( $field['key'] ); $raw = $_POST[ $fkey ] ?? ''; $value = is_scalar( $raw ) ? sanitize_textarea_field( wp_unslash( $raw ) ) : '';
		if ( ! empty( $field['required'] ) && '' === trim( $value ) ) { $fail(); }
		if ( 'email' === ( $field['type'] ?? '' ) ) { if ( ! is_email( $value ) ) { $fail(); } $email = sanitize_email( $value ); }
		$lines[] = sanitize_text_field( $field['label'] ) . ":\n" . $value;
	}
	$settings = emcore_form_settings( $key ); $headers = $email ? array( 'Reply-To: <' . $email . '>' ) : array();
	if ( ! wp_mail( sanitize_email( $settings['to'] ), sanitize_text_field( $settings['subject'] ), implode( "\n\n", $lines ), $headers ) ) { $fail(); }
	if ( ! empty( $settings['auto_reply'] ) && $email ) { wp_mail( $email, sanitize_text_field( $settings['reply_subject'] ), sanitize_textarea_field( $settings['reply_body'] ) ); }
	global $wpdb; $wpdb->insert( $wpdb->prefix . 'emcore_form_log', array( 'form_key' => $key, 'email' => $email, 'payload' => wp_json_encode( $lines, JSON_UNESCAPED_UNICODE ), 'ip_hash' => hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 'status' => 'unread', 'created_at' => current_time( 'mysql' ) ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
	set_transient( $rate, 1, MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'form', 'sent', wp_get_referer() ?: home_url( '/' ) ) ); exit;
}

function emcore_system_forms_install() {
	global $wpdb; $table = $wpdb->prefix . 'emcore_form_log';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	if ( $exists === $table ) { return; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php'; $charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, form_key varchar(80) NOT NULL, email varchar(190) NOT NULL DEFAULT '', payload longtext NOT NULL, ip_hash char(64) NOT NULL DEFAULT '', status varchar(20) NOT NULL DEFAULT 'unread', created_at datetime NOT NULL, PRIMARY KEY (id), KEY form_key (form_key), KEY created_at (created_at)) {$charset};" );
}

add_action( 'admin_post_emcore_save_system_pages', function () {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( '権限がありません。' ); }
	check_admin_referer( 'emcore_save_system_pages' ); $posted = isset( $_POST['system'] ) && is_array( $_POST['system'] ) ? wp_unslash( $_POST['system'] ) : array(); $saved = array();
	$current = emcore_system_pages_settings(); $can_design = current_user_can( emcore_design_capability() );
	foreach ( emcore_system_page_types() as $key => $defaults ) { $row = isset( $posted[ $key ] ) ? $posted[ $key ] : array(); $saved[ $key ] = array( 'enabled' => $can_design ? ! empty( $row['enabled'] ) : ! empty( $current[ $key ]['enabled'] ), 'slug' => $can_design ? sanitize_title( $row['slug'] ?? $defaults['slug'] ) : $current[ $key ]['slug'], 'title' => sanitize_text_field( $row['title'] ?? $defaults['title'] ), 'body' => wp_kses_post( $row['body'] ?? '' ), 'template_id' => $can_design ? sanitize_key( $row['template_id'] ?? '' ) : $current[ $key ]['template_id'], 'page_id' => absint( $current[ $key ]['page_id'] ?? 0 ), 'button_label' => sanitize_text_field( $row['button_label'] ?? 'ホームへ戻る' ), 'button_url' => esc_url_raw( $row['button_url'] ?? home_url( '/' ) ) ); }
	if ( $can_design ) {
		foreach ( $saved as $saved_key => $saved_page ) {
			if ( '404' === $saved_key || empty( $saved_page['enabled'] ) ) { continue; }
			$conflicts = emcore_public_slug_conflicts( $saved_page['slug'], absint( $saved_page['page_id'] ), '', $saved_key );
			if ( $conflicts ) { wp_die( esc_html( emcore_slug_conflict_message( $saved_page['slug'], $conflicts ) ), 'Emerge Mono', array( 'response' => 409, 'back_link' => true ) ); }
		}
	}
	$all_fields = get_option( 'emcore_system_form_fields', array() ); $posted_groups = isset( $_POST['form_fields'] ) && is_array( $_POST['form_fields'] ) ? wp_unslash( $_POST['form_fields'] ) : array();
	foreach ( array( 'contact' ) as $form_type ) { if ( ! isset( $posted_groups[ $form_type ] ) || ! is_array( $posted_groups[ $form_type ] ) ) { continue; } $fields = array(); foreach ( array_slice( $posted_groups[ $form_type ], 0, 30 ) as $field ) { if ( ! is_array( $field ) ) { continue; } $fkey = sanitize_key( $field['key'] ?? '' ); if ( ! $fkey ) { continue; } $fields[] = array( 'key' => $fkey, 'label' => sanitize_text_field( $field['label'] ?? $fkey ), 'type' => in_array( $field['type'] ?? '', array( 'text', 'email', 'tel', 'textarea' ), true ) ? $field['type'] : 'text', 'required' => ! empty( $field['required'] ), 'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ) ); } if ( $fields ) { $all_fields[ $form_type ] = $fields; } }
	update_option( 'emcore_system_form_fields', $all_fields, false );
	foreach ( $saved as $saved_key => &$saved_page ) { $page_id = absint( $saved_page['page_id'] ); if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) { $update = array( 'ID' => $page_id, 'post_title' => $saved_page['title'] ); if ( $can_design ) { $update['post_name'] = $saved_page['slug']; } wp_update_post( wp_slash( $update ) ); $saved_page['slug'] = get_post_field( 'post_name', $page_id ); update_post_meta( $page_id, '_emcore_system_page', $saved_key ); } } unset( $saved_page );
	$create_key = isset( $_POST['emcore_create_system_page'] ) ? sanitize_key( wp_unslash( $_POST['emcore_create_system_page'] ) ) : '';
	if ( $create_key && isset( $saved[ $create_key ] ) && '404' !== $create_key ) {
		$existing = absint( $saved[ $create_key ]['page_id'] );
		if ( ! $existing || 'trash' === get_post_status( $existing ) || get_post_type( $existing ) !== 'page' ) {
			$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $saved[ $create_key ]['title'], 'post_name' => $saved[ $create_key ]['slug'], 'post_content' => '', 'meta_input' => array( '_emcore_system_page' => $create_key ) ), true );
			if ( is_wp_error( $page_id ) ) { wp_die( esc_html( $page_id->get_error_message() ) ); }
			$saved[ $create_key ]['page_id'] = absint( $page_id ); $saved[ $create_key ]['slug'] = get_post_field( 'post_name', $page_id ); $saved[ $create_key ]['enabled'] = true;
		}
	}
	update_option( 'emcore_system_pages', $saved, false ); flush_rewrite_rules( false );
	$args = array( 'page' => 'emcore-system-pages', 'updated' => '1' ); if ( $create_key && ! empty( $saved[ $create_key ]['page_id'] ) ) { $args['created'] = $create_key; }
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
} );

function emcore_page_system_pages() {
	if ( ! current_user_can( 'edit_pages' ) ) { return; } $sets = emcore_system_pages_settings(); $types = emcore_system_page_types(); $templates = emcore_get_templates(); $can_design = current_user_can( emcore_design_capability() );
	emcore_shell_start( 'システムページ' ); echo '<section class="emcore-dashboard-intro"><span class="emcore-eyebrow">PAGE SHELL / SYSTEM CONTENT</span><h2>サイトの外観を継承して、<br>必要なページをCoreで管理。</h2><p>選択したHTMLテンプレートのヘッダー・背景・フッターを保ち、ページ固有の内容をCore管理コンテンツへ差し替えます。</p></section>';
	$created_key = isset( $_GET['created'] ) ? sanitize_key( wp_unslash( $_GET['created'] ) ) : '';
	if ( isset( $_GET['updated'] ) ) { $message = $created_key && isset( $types[ $created_key ] ) ? $types[ $created_key ]['label'] . 'の固定ページを作成して公開しました。' : '保存しました。'; echo '<div class="emcore-page-nav emcore-editor-page-nav emcore-system-status"><div class="emcore-editor-page-actions"><div class="emcore-msg is-ok" role="status">' . esc_html( $message ) . '</div></div></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="emcore_save_system_pages">'; wp_nonce_field( 'emcore_save_system_pages' );
	foreach ( $types as $key => $meta ) { $s = $sets[ $key ]; $is_open = $created_key ? $created_key === $key : 'contact' === $key; echo '<details class="emcore-panel emcore-system-settings" style="margin-bottom:16px"' . ( $is_open ? ' open' : '' ) . '><summary><strong>' . esc_html( $meta['label'] ) . '</strong><span class="emcore-help">' . ( $s['enabled'] ? '有効' : '無効' ) . '</span></summary><div class="emcore-system-settings-body">';
		if ( $can_design ) { echo '<label class="emcore-field"><span><input type="checkbox" name="system[' . esc_attr( $key ) . '][enabled]" value="1"' . checked( $s['enabled'], true, false ) . '> このページを有効にする</span></label>';
		if ( '404' !== $key ) { echo '<div class="emcore-field"><label>URLスラッグ</label><input class="emcore-input" name="system[' . esc_attr( $key ) . '][slug]" value="' . esc_attr( $s['slug'] ) . '"></div>'; }
		echo '<div class="emcore-field"><label>デザイン元テンプレート</label><select class="emcore-select" name="system[' . esc_attr( $key ) . '][template_id]"><option value="">最初の固定ページテンプレートを自動使用</option>'; foreach ( $templates as $id => $tpl ) { if ( ( $tpl['type'] ?? 'page' ) === 'page' ) { echo '<option value="' . esc_attr( $id ) . '"' . selected( $s['template_id'], $id, false ) . '>' . esc_html( $tpl['name'] ?? $id ) . '</option>'; } } echo '</select></div>'; }
		echo '<div class="emcore-field"><label>見出し</label><input class="emcore-input" name="system[' . esc_attr( $key ) . '][title]" value="' . esc_attr( $s['title'] ) . '"></div><div class="emcore-field"><label>本文</label><textarea class="emcore-textarea" rows="5" name="system[' . esc_attr( $key ) . '][body]">' . esc_textarea( $s['body'] ) . '</textarea></div>';
		if ( 'contact' === $key ) { echo '<div class="emcore-system-form-fields"><span class="emcore-eyebrow">FORM FIELDS</span><h3>フォームフィールド</h3><p>お問い合わせで使用する入力項目です。追加・削除・並べ替えができます。</p><div class="emcore-system-fields" data-form-type="contact">'; foreach ( emcore_system_form_fields( 'contact' ) as $index => $field ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper escapes every field value while assembling trusted admin markup.
			echo emcore_system_field_admin_row( 'contact', $index, $field );
		} echo '</div><button type="button" class="emcore-btn emcore-system-field-add" data-form-type="contact">＋ フィールドを追加</button></div>'; }
		if ( '404' === $key ) { echo '<div class="emcore-field"><label>ボタン文言</label><input class="emcore-input" name="system[' . esc_attr( $key ) . '][button_label]" value="' . esc_attr( $s['button_label'] ) . '"></div><div class="emcore-field"><label>ボタンURL</label><input class="emcore-input" type="url" name="system[' . esc_attr( $key ) . '][button_url]" value="' . esc_attr( $s['button_url'] ) . '"></div>'; }
		if ( '404' !== $key ) { $page_id = absint( $s['page_id'] ); if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) { echo '<div class="emcore-system-page-actions"><a class="emcore-btn" href="' . esc_url( get_edit_post_link( $page_id ) ) . '">固定ページを編集</a><a class="emcore-btn" href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank" rel="noopener noreferrer">ページを表示 ↗</a></div>'; } else { echo '<button class="emcore-btn emcore-btn-primary" type="submit" name="emcore_create_system_page" value="' . esc_attr( $key ) . '">固定ページを作成</button><p class="emcore-help">入力したタイトルとスラッグで固定ページを作成し、このシステム内容を割り当てます。</p>'; } }
		echo '</div></details>'; }
	echo '<button class="emcore-btn emcore-btn-primary" type="submit">すべて保存</button></form>'; emcore_shell_end();
}

function emcore_system_form_recent_log( $limit = 30 ) {
	global $wpdb; $table = $wpdb->prefix . 'emcore_form_log';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return array(); }
	return $wpdb->get_results( $wpdb->prepare( "SELECT id, form_key, email, payload, status, created_at FROM {$table} ORDER BY id DESC LIMIT %d", absint( $limit ) ), ARRAY_A );
}

function emcore_system_field_admin_row( $type, $index, $field ) {
	$name = 'form_fields[' . sanitize_key( $type ) . '][' . absint( $index ) . ']'; $out = '<div class="emcore-system-field-row">';
	$out .= '<input class="emcore-input" name="' . $name . '[label]" value="' . esc_attr( $field['label'] ?? '' ) . '" placeholder="表示名"><input class="emcore-input" name="' . $name . '[key]" value="' . esc_attr( $field['key'] ?? '' ) . '" placeholder="field_key">';
	$out .= '<select class="emcore-select" name="' . $name . '[type]">'; foreach ( array( 'text' => 'テキスト', 'email' => 'メール', 'tel' => '電話番号', 'textarea' => '複数行' ) as $value => $label ) { $out .= '<option value="' . $value . '"' . selected( $field['type'] ?? 'text', $value, false ) . '>' . $label . '</option>'; } $out .= '</select>';
	$out .= '<input class="emcore-input" name="' . $name . '[placeholder]" value="' . esc_attr( $field['placeholder'] ?? '' ) . '" placeholder="プレースホルダー"><label><input type="checkbox" name="' . $name . '[required]" value="1"' . checked( ! empty( $field['required'] ), true, false ) . '> 必須</label><button type="button" class="emcore-btn emcore-system-field-up">↑</button><button type="button" class="emcore-btn emcore-system-field-down">↓</button><button type="button" class="emcore-btn emcore-system-field-delete">削除</button></div>';
	return $out;
}
