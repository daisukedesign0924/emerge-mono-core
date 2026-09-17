<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_ajax_ok( $capability = 'edit_pages' ) {
	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error( array( 'message' => '権限がありません' ) );
	}
	check_ajax_referer( 'emcore_admin', 'nonce' );
}

function emcore_template_hints_from_html( $html, $fallback ) {
	$result = array( 'name' => sanitize_text_field( $fallback ), 'type' => 'page', 'post_type' => '' );
	if ( ! class_exists( 'DOMDocument' ) ) { return $result; }
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if ( ! $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ) ) { return $result; }
	$xpath = new DOMXPath( $dom );
	$body = $xpath->query( '//*[@data-em-part="body"]' );
	if ( $body && $body->length ) {
		$node = $body->item( 0 );
		$label = $node->getAttribute( 'data-em-label' );
		$declared = $node->getAttribute( 'data-em-template-type' );
		if ( $label !== '' ) { $result['name'] = sanitize_text_field( $label ); }
		if ( in_array( $declared, array( 'archive', 'single' ), true ) ) { $result['type'] = $declared; }
		$result['post_type'] = sanitize_key( $node->getAttribute( 'data-em-post-type' ) );
	}
	// Relaxed mode: filename and ordinary HTML structure are enough for a useful hint.
	$haystack = strtolower( $fallback . ' ' . ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $tm ) ? wp_strip_all_tags( $tm[1] ) : '' ) );
	if ( $result['type'] === 'page' ) {
		if ( preg_match( '/(?:archive|list|一覧)/i', $haystack ) || preg_match( '/\{\{#posts\}\}|data-em-component=["\']post-list/i', $html ) ) { $result['type'] = 'archive'; }
		elseif ( preg_match( '/(?:single|detail|個別)/i', $haystack ) || preg_match( '/\{\{(?:title|field:|content)/i', $html ) ) { $result['type'] = 'single'; }
	}
	if ( ! $result['post_type'] && preg_match( '/(?:^|[-_])(liver|news|works?|blog|projects?)(?:[-_]|$)/i', $fallback, $pm ) ) { $result['post_type'] = sanitize_key( $pm[1] ); }
	libxml_clear_errors();
	return $result;
}

add_action( 'wp_ajax_emcore_save_template', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$all  = emcore_get_templates();
	$id   = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'page';
	$html = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
	if ( ! in_array( $type, array( 'page', 'archive', 'single' ), true ) ) {
		$type = 'page';
	}
	if ( ! $id ) {
		$id = emcore_make_tpl_id();
	}
	$prev = isset( $all[ $id ] ) ? $all[ $id ] : array();
	if ( empty( $prev ) ) {
		$hints = emcore_template_hints_from_html( $html, $name );
		$html = emcore_prepare_import_html( $html, $type, $hints['post_type'] );
	}
	if ( ! empty( $prev['html'] ) && $prev['html'] !== $html ) {
		emcore_add_template_revision( $id, $prev );
	}
	$shared = isset( $prev['shared'] ) && is_array( $prev['shared'] ) ? $prev['shared'] : array();
	if ( isset( $_POST['shared'] ) ) {
		$raw = json_decode( wp_unslash( $_POST['shared'] ), true );
		if ( is_array( $raw ) ) {
			$shared = array_merge( $shared, $raw );
		}
	}
	if ( isset( $_POST['site_shared'] ) ) {
		$raw = json_decode( wp_unslash( $_POST['site_shared'] ), true );
		if ( is_array( $raw ) ) {
			$site = emcore_get_site_shared();
			emcore_save_site_shared( array_merge( $site, $raw ) );
		}
	}
	$all[ $id ] = array(
		'name'   => $name ? $name : ( $prev['name'] ?? $id ),
		'type'   => $type,
		'html'   => $html,
		'shared' => $shared,
	);
	if ( ! empty( $prev['asset_base'] ) ) {
		$all[ $id ]['asset_base'] = $prev['asset_base'];
	}
	foreach ( array( 'post_type_hint', 'import_file', 'source_hash' ) as $keep ) {
		if ( ! empty( $prev[ $keep ] ) ) { $all[ $id ][ $keep ] = $prev[ $keep ]; }
	}
	emcore_save_templates( $all );
	wp_send_json_success( array( 'message' => '保存しました', 'id' => $id ) );
} );

add_action( 'wp_ajax_emcore_diagnose_template', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$html = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
	wp_send_json_success( array( 'diagnostics' => emcore_diagnose_html( $html ), 'compliance' => emcore_emhtml_compliance( $html ) ) );
} );

add_action( 'wp_ajax_emcore_save_shared_content', function () {
	emcore_ajax_ok( 'edit_pages' );
	$raw = isset( $_POST['values'] ) ? json_decode( wp_unslash( $_POST['values'] ), true ) : array();
	if ( ! is_array( $raw ) ) { wp_send_json_error( array( 'message' => '保存データが不正です。' ) ); }
	$values = emcore_get_site_shared();
	foreach ( $raw as $key => $value ) {
		$key = sanitize_key( $key );
		if ( ! $key ) { continue; }
		if ( substr( $key, -6 ) === '__font' ) {
			$values[ $key ] = emcore_sanitize_font_family( $value );
		} else {
			$values[ $key ] = is_string( $value ) ? wp_kses_post( $value ) : '';
		}
	}
	emcore_save_site_shared( $values );
	wp_send_json_success( array( 'message' => '共通コンテンツを保存しました。' ) );
} );

add_action( 'wp_ajax_emcore_restore_template', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$id    = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	$index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
	$all   = emcore_get_templates();
	$revs  = emcore_get_template_revisions( $id );
	if ( ! isset( $all[ $id ], $revs[ $index ] ) ) {
		wp_send_json_error( array( 'message' => '履歴が見つかりません' ) );
	}
	emcore_add_template_revision( $id, $all[ $id ] );
	$all[ $id ] = array(
		'name' => $revs[ $index ]['name'], 'type' => $revs[ $index ]['type'],
		'html' => $revs[ $index ]['html'], 'shared' => $revs[ $index ]['shared'],
	);
	emcore_save_templates( $all );
	wp_send_json_success( array( 'message' => '選択した履歴へ復元しました' ) );
} );

add_action( 'wp_ajax_emcore_delete_template', function () {
	emcore_ajax_ok( emcore_design_capability() );
	if ( ! isset( $_POST['confirm_text'] ) || 'DELETE' !== wp_unslash( $_POST['confirm_text'] ) ) {
		wp_send_json_error( array( 'message' => '確認文字列が一致しないため削除しませんでした。' ) );
	}
	$id  = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	$force = isset( $_POST['force'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['force'] ) );
	$all = emcore_get_templates();
	if ( ! $id || ! isset( $all[ $id ] ) ) {
		wp_send_json_error( array( 'message' => 'テンプレートが見つかりません。' ) );
	}
	$usage = emcore_template_usage( $id );
	if ( ( $usage['pages'] || $usage['cpts'] ) && ! $force ) {
		$page_names = array();
		foreach ( $usage['pages'] as $page_id ) {
			$page_names[] = get_the_title( $page_id ) ?: '無題の固定ページ';
		}
		$cpts = emcore_get_cpts();
		$cpt_names = array();
		foreach ( $usage['cpts'] as $cpt_key ) {
			$cpt_names[] = isset( $cpts[ $cpt_key ]['label'] ) ? $cpts[ $cpt_key ]['label'] : $cpt_key;
		}
		wp_send_json_error( array(
			'message' => 'このテンプレートは使用中です。関連データを整理して削除しますか？',
			'requires_confirmation' => true,
			'pages' => $page_names,
			'cpts' => $cpt_names,
		) );
	}
	$trashed = 0;
	if ( $force ) {
		foreach ( $usage['pages'] as $page_id ) {
			if ( ! current_user_can( 'delete_post', $page_id ) ) {
				wp_send_json_error( array( 'message' => '関連固定ページをゴミ箱へ移動する権限がありません。' ) );
			}
			if ( get_post_status( $page_id ) !== 'trash' ) {
				if ( ! wp_trash_post( $page_id ) ) {
					wp_send_json_error( array( 'message' => '関連固定ページをゴミ箱へ移動できなかったため、テンプレートは削除していません。' ) );
				}
				$trashed++;
			}
		}
		$cpts = emcore_get_cpts();
		foreach ( $cpts as $cpt_key => &$cpt ) {
			if ( isset( $cpt['archive_tpl'] ) && $cpt['archive_tpl'] === $id ) { $cpt['archive_tpl'] = ''; }
			if ( isset( $cpt['single_tpl'] ) && $cpt['single_tpl'] === $id ) { $cpt['single_tpl'] = ''; }
		}
		unset( $cpt );
		emcore_save_cpts( $cpts );
	}
	unset( $all[ $id ] );
	emcore_save_templates( $all );
	wp_send_json_success( array( 'message' => $trashed ? 'テンプレートを削除し、関連固定ページをゴミ箱へ移動しました。' : 'テンプレートを削除しました。' ) );
} );

add_action( 'wp_ajax_emcore_create_page_from_tpl', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$tpl_id = isset( $_POST['tpl'] ) ? sanitize_key( wp_unslash( $_POST['tpl'] ) ) : '';
	$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Page';
	$slug   = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title( $title );
	$kind   = isset( $_POST['page_kind'] ) ? sanitize_key( wp_unslash( $_POST['page_kind'] ) ) : 'page';
	$tpl    = emcore_get_template( $tpl_id );
	if ( ! $tpl ) {
		wp_send_json_error( array( 'message' => 'テンプレートがありません' ) );
	}
	if ( ! $slug ) { wp_send_json_error( array( 'message' => 'スラッグを入力してください。' ) ); }
	$conflicts = emcore_public_slug_conflicts( $slug );
	if ( $conflicts ) { wp_send_json_error( array( 'message' => emcore_slug_conflict_message( $slug, $conflicts ) ) ); }
	$cpt_key = '';
	if ( $tpl['type'] === 'archive' || $kind === 'archive' ) {
		foreach ( emcore_get_cpts() as $key => $cpt ) {
			if ( ( ! empty( $cpt['archive_tpl'] ) && $cpt['archive_tpl'] === $tpl_id ) || ( ! empty( $tpl['post_type_hint'] ) && ( $key === $tpl['post_type_hint'] || emcore_cpt_parent_slug( $cpt, $key ) === $tpl['post_type_hint'] ) ) ) { $cpt_key = $key; break; }
		}
		if ( ! $cpt_key ) { wp_send_json_error( array( 'message' => '先にPost Typesで対応する投稿タイプを作成し、この一覧テンプレートを「一覧テンプレ」に割り当ててください。' ) ); }
	}
	$pid = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_status' => 'publish',
			'post_content'=> '',
		)
	);
	if ( is_wp_error( $pid ) ) {
		wp_send_json_error( array( 'message' => $pid->get_error_message() ) );
	}
	if ( $cpt_key ) { update_post_meta( $pid, '_emcore_archive_cpt', $cpt_key ); }
	else { update_post_meta( $pid, '_emcore_tpl_id', $tpl_id ); }
	wp_send_json_success(
		array(
			'message'  => $cpt_key ? '一覧用の固定ページを作成しました' : '固定ページを作成しました',
			'view_url' => get_permalink( $pid ),
			'edit_url' => admin_url( 'admin.php?page=emcore-content-edit&post_id=' . $pid ),
		)
	);
} );

add_action( 'wp_ajax_emcore_cpt_create', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	if ( ! $label ) {
		wp_send_json_error( array( 'message' => '名前を入力してください' ) );
	}
	$singular = isset( $_POST['singular'] ) ? sanitize_text_field( wp_unslash( $_POST['singular'] ) ) : $label;
	$slug     = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title( $label );
	$icon     = isset( $_POST['icon'] ) ? sanitize_text_field( wp_unslash( $_POST['icon'] ) ) : 'dashicons-groups';
	$has_cat  = ! empty( $_POST['has_cat'] );
	$listing_mode = ( isset( $_POST['listing_mode'] ) && 'archive' === sanitize_key( wp_unslash( $_POST['listing_mode'] ) ) ) ? 'archive' : 'fixed_page';
	$key      = 'emcpt_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
	if ( ! $slug ) { wp_send_json_error( array( 'message' => 'URLスラッグを入力してください。' ) ); }
	$conflicts = emcore_public_slug_conflicts( $slug, 0, '', '', 'fixed_page' === $listing_mode );
	if ( $conflicts ) { wp_send_json_error( array( 'message' => emcore_slug_conflict_message( $slug, $conflicts ) ) ); }
	$all      = emcore_get_cpts();
	$all[ $key ] = array(
		'label'       => $label,
		'singular'    => $singular,
		'slug'        => $slug ? $slug : $key,
		'parent_slug' => $slug ? $slug : $key,
		'listing_mode' => $listing_mode,
		'icon'        => $icon,
		'has_cat'     => $has_cat,
		'filter_enabled' => $has_cat,
		'archive_tpl' => '',
		'single_tpl'  => '',
	);
	emcore_save_cpts( $all );
	flush_rewrite_rules();
	wp_send_json_success( array( 'message' => '投稿タイプを作成しました', 'key' => $key ) );
} );

add_action( 'wp_ajax_emcore_cpt_set_routing', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$parent_slug = isset( $_POST['parent_slug'] ) ? sanitize_title( wp_unslash( $_POST['parent_slug'] ) ) : '';
	$listing_mode = ( isset( $_POST['listing_mode'] ) && 'archive' === sanitize_key( wp_unslash( $_POST['listing_mode'] ) ) ) ? 'archive' : 'fixed_page';
	$all = emcore_get_cpts();
	if ( ! isset( $all[ $key ] ) ) { wp_send_json_error( array( 'message' => '投稿タイプがありません' ) ); }
	if ( ! $parent_slug ) { wp_send_json_error( array( 'message' => '親スラッグを入力してください。' ) ); }
	$conflicts = emcore_public_slug_conflicts( $parent_slug, 0, $key, '', 'fixed_page' === $listing_mode );
	if ( $conflicts ) { wp_send_json_error( array( 'message' => emcore_slug_conflict_message( $parent_slug, $conflicts ) ) ); }
	$all[ $key ]['slug'] = $parent_slug;
	$all[ $key ]['parent_slug'] = $parent_slug;
	$all[ $key ]['listing_mode'] = $listing_mode;
	emcore_save_cpts( $all );
	flush_rewrite_rules( false );
	wp_send_json_success( array( 'message' => 'URL構造を保存しました。', 'parent_slug' => $parent_slug, 'listing_mode' => $listing_mode ) );
} );

add_action( 'wp_ajax_emcore_cpt_delete', function () {
	emcore_ajax_ok( emcore_design_capability() );
	if ( ! isset( $_POST['confirm_text'] ) || 'DELETE' !== wp_unslash( $_POST['confirm_text'] ) ) {
		wp_send_json_error( array( 'message' => '確認文字列が一致しないため削除しませんでした。' ) );
	}
	$key   = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$force = ! empty( $_POST['force'] );
	$all   = emcore_get_cpts();
	if ( ! isset( $all[ $key ] ) ) {
		wp_send_json_error( array( 'message' => '投稿タイプが見つかりません。' ) );
	}
	$post_ids = get_posts(
		array(
			'post_type'      => $key,
			'post_status'    => get_post_stati(),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => get_post_stati(),
			'meta_key'       => '_emcore_archive_cpt',
			'meta_value'     => $key,
			'posts_per_page' => -1,
		)
	);

	if ( ( $post_ids || $pages ) && ! $force ) {
		wp_send_json_error(
			array(
				'message'               => '関連する記事または一覧ページが残っています。',
				'requires_confirmation' => true,
				'post_count'            => count( $post_ids ),
				'pages'                 => array_map(
					static function ( $page ) {
						return get_the_title( $page ) ? get_the_title( $page ) : '(無題の一覧ページ)';
					},
					$pages
				),
			)
		);
	}

	if ( $force ) {
		foreach ( array_merge( $post_ids, wp_list_pluck( $pages, 'ID' ) ) as $post_id ) {
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => '関連データを削除する権限がありません。' ) );
			}
		}
		foreach ( $pages as $page ) {
			delete_post_meta( $page->ID, '_emcore_archive_cpt' );
			delete_post_meta( $page->ID, '_emcore_tpl_id' );
			if ( 'trash' !== get_post_status( $page->ID ) ) {
				wp_trash_post( $page->ID );
			}
		}
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	unset( $all[ $key ] );
	emcore_save_cpts( $all );
	flush_rewrite_rules();
	wp_send_json_success(
		array(
			'message'       => '投稿タイプを削除しました。',
			'deleted_posts' => $force ? count( $post_ids ) : 0,
			'trashed_pages' => $force ? count( $pages ) : 0,
		)
	);
} );

add_action( 'wp_ajax_emcore_cpt_set_template', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$key   = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$which = isset( $_POST['which'] ) ? sanitize_key( wp_unslash( $_POST['which'] ) ) : '';
	$tpl   = isset( $_POST['tpl'] ) ? sanitize_key( wp_unslash( $_POST['tpl'] ) ) : '';
	$all   = emcore_get_cpts();
	if ( ! isset( $all[ $key ] ) ) {
		wp_send_json_error( array( 'message' => '投稿タイプがありません' ) );
	}
	if ( $which === 'archive' ) {
		$all[ $key ]['archive_tpl'] = $tpl;
	} else {
		$all[ $key ]['single_tpl'] = $tpl;
	}
	emcore_save_cpts( $all );
	wp_send_json_success( array( 'message' => '保存しました' ) );
} );

add_action( 'wp_ajax_emcore_cpt_set_filter', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$all = emcore_get_cpts();
	if ( ! isset( $all[ $key ] ) ) { wp_send_json_error( array( 'message' => '投稿タイプがありません' ) ); }
	$all[ $key ]['filter_enabled'] = ! empty( $_POST['enabled'] );
	emcore_save_cpts( $all );
	wp_send_json_success( array( 'message' => '保存しました' ) );
} );

add_action( 'wp_ajax_emcore_create_archive_page', function () {
	emcore_ajax_ok( emcore_design_capability() );
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$all = emcore_get_cpts();
	if ( ! isset( $all[ $key ] ) ) {
		wp_send_json_error( array( 'message' => '投稿タイプがありません' ) );
	}
	if ( 'fixed_page' !== emcore_cpt_listing_mode( $all[ $key ] ) ) {
		wp_send_json_error( array( 'message' => '自動一覧を使用中です。固定ページ方式に変更してから作成してください。' ) );
	}
	$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_emcore_archive_cpt', 'meta_value' => $key, 'posts_per_page' => 1 ) );
	if ( $existing ) {
		wp_send_json_success( array( 'message' => '既存の一覧ページを開きます', 'view_url' => get_permalink( $existing[0] ), 'existing' => true ) );
	}
	$parent_slug = emcore_cpt_parent_slug( $all[ $key ], $key );
	$existing_by_slug = get_page_by_path( $parent_slug, OBJECT, 'page' );
	if ( $existing_by_slug && 'trash' !== get_post_status( $existing_by_slug ) ) {
		update_post_meta( $existing_by_slug->ID, '_emcore_archive_cpt', $key );
		if ( ! empty( $all[ $key ]['archive_tpl'] ) ) { update_post_meta( $existing_by_slug->ID, '_emcore_tpl_id', $all[ $key ]['archive_tpl'] ); }
		wp_send_json_success( array( 'message' => '既存の固定ページを一覧ページとして設定しました', 'view_url' => get_permalink( $existing_by_slug ), 'existing' => true ) );
	}
	$conflicts = emcore_public_slug_conflicts( $parent_slug, 0, $key, '', true );
	if ( $conflicts ) { wp_send_json_error( array( 'message' => emcore_slug_conflict_message( $parent_slug, $conflicts ) ) ); }
	$pid = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_title'   => $all[ $key ]['label'],
			'post_name'    => $parent_slug,
			'post_status'  => 'publish',
			'post_content' => '',
		)
	);
	if ( is_wp_error( $pid ) ) {
		wp_send_json_error( array( 'message' => $pid->get_error_message() ) );
	}
	update_post_meta( $pid, '_emcore_archive_cpt', $key );
	if ( ! empty( $all[ $key ]['archive_tpl'] ) ) {
		update_post_meta( $pid, '_emcore_tpl_id', $all[ $key ]['archive_tpl'] );
	}
	wp_send_json_success(
		array(
			'message'  => '一覧ページを作成しました',
			'view_url' => get_permalink( $pid ),
		)
	);
} );

add_action( 'wp_ajax_emcore_save_post_fields', function () {
	emcore_ajax_ok();
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$cpt     = isset( $_POST['cpt'] ) ? sanitize_key( wp_unslash( $_POST['cpt'] ) ) : '';
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => '投稿を保存できません' ) );
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( array( 'message' => '投稿が見つかりません' ) );
	}
	if ( $cpt === 'page' ) {
		if ( $post->post_type !== 'page' ) {
			wp_send_json_error( array( 'message' => '固定ページではありません' ) );
		}
		$tpl_id = emcore_template_id_for_page( $post );
		if ( $tpl_id ) {
			update_post_meta( $post_id, '_emcore_tpl_id', $tpl_id );
		}
	} else {
		if ( $post->post_type !== $cpt ) {
			wp_send_json_error( array( 'message' => '投稿が見つかりません' ) );
		}
		$tpl_id = emcore_cpt_single_template_id( $cpt );
	}
	$tpl = $tpl_id ? emcore_get_template( $tpl_id ) : null;
	$fields = $tpl ? emcore_fields_from_html( $tpl['html'] ) : array();

	$update = array( 'ID' => $post_id );
	$raw    = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : '{}';
	$data   = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : $post->post_status;
	if ( ! in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ) {
		$status = 'publish';
	}
	$update['post_status'] = $status;
	if ( isset( $data['post_name'] ) ) {
		$slug = sanitize_title( $data['post_name'] );
		if ( $slug === '' ) {
			wp_send_json_error( array( 'message' => 'URLスラッグを入力してください' ) );
		}
		$update['post_name'] = wp_unique_post_slug( $slug, $post_id, $status, $post->post_type, $post->post_parent );
	}

	foreach ( $fields as $field ) {
		$key = $field['key'];
		$val = isset( $data[ $key ] ) ? $data[ $key ] : '';
		if ( ! empty( $field['font_editable'] ) ) {
			$font = emcore_sanitize_font_family( isset( $data[ $key . '__font' ] ) ? $data[ $key . '__font' ] : '' );
			if ( $font === '' ) { delete_post_meta( $post_id, '_emcore_' . $key . '_font' ); }
			else { update_post_meta( $post_id, '_emcore_' . $key . '_font', $font ); }
		}
		if ( $field['store'] === 'title' ) {
			$update['post_title'] = sanitize_text_field( $val );
		} elseif ( $field['store'] === 'content' ) {
			$update['post_content'] = wp_kses_post( $val );
		} elseif ( $field['store'] === 'thumbnail' ) {
			$id = isset( $data[ $key . '_id' ] ) ? absint( $data[ $key . '_id' ] ) : 0;
			if ( $id ) {
				set_post_thumbnail( $post_id, $id );
			} elseif ( $val ) {
				update_post_meta( $post_id, '_emcore_' . $key, esc_url_raw( $val ) );
			}
		} else {
			if ( $field['type'] === 'slides' ) {
				$arr = json_decode( $val, true );
				if ( ! is_array( $arr ) ) {
					$arr = array_filter( array_map( 'trim', explode( "
", (string) $val ) ) );
				}
				$arr = array_values( array_map( 'esc_url_raw', $arr ) );
				update_post_meta( $post_id, '_emcore_' . $key, wp_json_encode( $arr ) );
			} elseif ( $field['type'] === 'accordion' ) {
				$arr = json_decode( (string) $val, true );
				$clean = array();
				foreach ( is_array( $arr ) ? $arr : array() as $item ) {
					if ( ! is_array( $item ) ) { continue; }
					$question = sanitize_text_field( $item['question'] ?? '' );
					$answer   = sanitize_textarea_field( $item['answer'] ?? '' );
					if ( $question !== '' || $answer !== '' ) { $clean[] = array( 'question' => $question, 'answer' => $answer ); }
				}
				update_post_meta( $post_id, '_emcore_' . $key, wp_json_encode( $clean ) );
			} elseif ( $field['type'] === 'menu' ) {
				$arr = json_decode( (string) $val, true );
				$clean = array();
				foreach ( is_array( $arr ) ? $arr : array() as $item ) {
					if ( ! is_array( $item ) ) { continue; }
					$label = sanitize_text_field( $item['label'] ?? '' );
					$url = esc_url_raw( $item['url'] ?? '' );
					$target = ( $item['target'] ?? '' ) === '_blank' ? '_blank' : '';
					if ( '' !== $label || '' !== $url ) { $clean[] = compact( 'label', 'url', 'target' ); }
				}
				update_post_meta( $post_id, '_emcore_' . $key, wp_json_encode( $clean ) );
			} elseif ( in_array( $field['type'], array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ) {
				$arr = json_decode( (string) $val, true ); $clean = array();
				foreach ( is_array( $arr ) ? $arr : array() as $item ) {
					if ( ! is_array( $item ) ) { continue; }
					if ( $field['type'] === 'list' ) { $clean[] = array( 'text' => sanitize_text_field( $item['text'] ?? '' ) ); }
					elseif ( $field['type'] === 'steps' ) { $clean[] = array( 'title' => sanitize_text_field( $item['title'] ?? '' ), 'description' => sanitize_textarea_field( $item['description'] ?? '' ) ); }
					elseif ( $field['type'] === 'gallery' ) { $clean[] = array( 'image' => esc_url_raw( $item['image'] ?? '' ), 'alt' => sanitize_text_field( $item['alt'] ?? '' ), 'url' => esc_url_raw( $item['url'] ?? '' ) ); }
					elseif ( $field['type'] === 'tabs' ) { $clean[] = array( 'label' => sanitize_text_field( $item['label'] ?? '' ), 'content' => sanitize_textarea_field( $item['content'] ?? '' ) ); }
					elseif ( $field['type'] === 'table' ) { $clean[] = array( 'cells' => array_map( 'sanitize_text_field', (array) ( $item['cells'] ?? array() ) ) ); }
					else { $row = array(); foreach ( $item as $field_key => $field_value ) { $row[ sanitize_key( $field_key ) ] = is_string( $field_value ) ? sanitize_textarea_field( $field_value ) : ''; } $clean[] = $row; }
				}
				update_post_meta( $post_id, '_emcore_' . $key, wp_json_encode( $clean ) );
			} elseif ( $field['type'] === 'image' ) {
				$variants = json_decode( (string) $val, true );
				if ( is_array( $variants ) ) {
					$clean = array( 'default' => esc_url_raw( $variants['default'] ?? '' ), 'sources' => array() );
					foreach ( (array) ( $variants['sources'] ?? array() ) as $media => $src ) {
						$clean['sources'][ sanitize_text_field( $media ) ] = esc_url_raw( $src );
					}
					update_post_meta( $post_id, '_emcore_' . $key, wp_json_encode( $clean ) );
				} else {
					update_post_meta( $post_id, '_emcore_' . $key, esc_url_raw( $val ) );
				}
			} elseif ( $field['type'] === 'url' ) {
				update_post_meta( $post_id, '_emcore_' . $key, esc_url_raw( $val ) );
			} else {
				update_post_meta( $post_id, '_emcore_' . $key, sanitize_textarea_field( $val ) );
			}
			if ( isset( $data[ $key . '_id' ] ) ) {
				update_post_meta( $post_id, '_emcore_' . $key . '_id', absint( $data[ $key . '_id' ] ) );
			}
		}
	}

	if ( empty( $update['post_title'] ) && isset( $data['title'] ) ) {
		$update['post_title'] = sanitize_text_field( $data['title'] );
	}
	if ( ! empty( $data['thumbnail_id'] ) ) {
		set_post_thumbnail( $post_id, absint( $data['thumbnail_id'] ) );
	}
	if ( isset( $_POST['cats'] ) && $post->post_type !== 'page' ) {
		$tax = $post->post_type . '_cat';
		if ( taxonomy_exists( $tax ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', wp_unslash( $_POST['cats'] ) ) ) );
			wp_set_object_terms( $post_id, $ids, $tax );
		}
	}
	wp_update_post( $update );
	do_action( 'emcore_after_save_post_fields', $post_id, $_POST );
	wp_send_json_success(
		array(
			'message'  => '保存しました',
			'view_url' => get_permalink( $post_id ),
		)
	);
} );

add_action( 'wp_ajax_emcore_delete_post', function () {
	emcore_ajax_ok();
	if ( ! isset( $_POST['confirm_text'] ) || 'DELETE' !== wp_unslash( $_POST['confirm_text'] ) ) {
		wp_send_json_error( array( 'message' => '確認文字列が一致しないため削除しませんでした。' ) );
	}
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$post = $post_id ? get_post( $post_id ) : null;
	$cpts = emcore_get_cpts();
	if ( ! $post || ! isset( $cpts[ $post->post_type ] ) || ! current_user_can( 'delete_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'このコンテンツを削除する権限がありません。' ) );
	}
	if ( ! wp_trash_post( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'ゴミ箱へ移動できませんでした。' ) );
	}
	wp_send_json_success( array( 'message' => 'ゴミ箱へ移動しました。' ) );
} );

add_action( 'wp_ajax_emcore_create_post', function () {
	emcore_ajax_ok();
	$cpt   = isset( $_POST['cpt'] ) ? sanitize_key( wp_unslash( $_POST['cpt'] ) ) : '';
	$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '無題';
	$slug  = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	if ( ! post_type_exists( $cpt ) ) {
		wp_send_json_error( array( 'message' => '投稿タイプがありません' ) );
	}
	if ( ! $title || ! $slug ) {
		wp_send_json_error( array( 'message' => 'タイトルとURLスラッグを入力してください。' ) );
	}
	if ( get_page_by_path( $slug, OBJECT, $cpt ) ) {
		wp_send_json_error( array( 'message' => '同じURLスラッグのコンテンツがすでにあります。' ) );
	}
	$pid = wp_insert_post(
		array(
			'post_type'   => $cpt,
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_status' => 'draft',
		)
	);
	if ( is_wp_error( $pid ) ) {
		wp_send_json_error( array( 'message' => $pid->get_error_message() ) );
	}
	wp_send_json_success(
		array(
			'edit_url' => admin_url( 'admin.php?page=emcore-cpt-edit&cpt=' . $cpt . '&id=' . $pid ),
		)
	);
} );
