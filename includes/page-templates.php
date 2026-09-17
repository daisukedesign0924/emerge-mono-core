<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_get_templates() {
	$t = get_option( 'emcore_templates', array() );
	return is_array( $t ) ? $t : array();
}

function emcore_get_template( $id ) {
	$all = emcore_get_templates();
	return isset( $all[ $id ] ) ? $all[ $id ] : null;
}

function emcore_save_templates( $all ) {
	update_option( 'emcore_templates', $all, false );
}

function emcore_get_template_revisions( $id ) {
	$all = get_option( 'emcore_template_revisions', array() );
	return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : array();
}

function emcore_add_template_revision( $id, $template ) {
	if ( ! $id || empty( $template['html'] ) ) {
		return;
	}
	$all = get_option( 'emcore_template_revisions', array() );
	if ( ! isset( $all[ $id ] ) || ! is_array( $all[ $id ] ) ) {
		$all[ $id ] = array();
	}
	array_unshift( $all[ $id ], array(
		'time'     => current_time( 'mysql' ),
		'user'     => get_current_user_id(),
		'name'     => isset( $template['name'] ) ? $template['name'] : $id,
		'type'     => isset( $template['type'] ) ? $template['type'] : 'page',
		'html'     => $template['html'],
		'shared'   => isset( $template['shared'] ) ? $template['shared'] : array(),
	) );
	$all[ $id ] = array_slice( $all[ $id ], 0, 10 );
	update_option( 'emcore_template_revisions', $all, false );
}

/** Return route owners that would collide with a new public top-level slug. */
function emcore_public_slug_conflicts( $slug, $exclude_post_id = 0, $exclude_cpt_key = '', $exclude_system_key = '', $allow_page_for_cpt = false ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) { return array( '空のスラッグ' ); }
	$conflicts = array();
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ), 'name' => $slug, 'posts_per_page' => -1 ) );
	foreach ( $pages as $page ) {
		if ( ! $allow_page_for_cpt && (int) $page->ID !== (int) $exclude_post_id ) { $conflicts[] = '固定ページ「' . $page->post_title . '」'; }
	}
	foreach ( emcore_get_cpts() as $key => $cpt ) {
		$cpt_slug = function_exists( 'emcore_cpt_parent_slug' ) ? emcore_cpt_parent_slug( $cpt, $key ) : sanitize_title( $cpt['parent_slug'] ?? ( $cpt['slug'] ?? $key ) );
		$fixed_listing = function_exists( 'emcore_cpt_listing_mode' ) ? 'fixed_page' === emcore_cpt_listing_mode( $cpt ) : ( $cpt['listing_mode'] ?? 'fixed_page' ) !== 'archive';
		if ( $key !== $exclude_cpt_key && $cpt_slug === $slug && ! $fixed_listing ) { $conflicts[] = '投稿タイプ「' . ( $cpt['label'] ?? $key ) . '」'; }
	}
	if ( function_exists( 'emcore_system_pages_settings' ) ) {
		foreach ( emcore_system_pages_settings() as $key => $set ) {
			if ( $key !== $exclude_system_key && '404' !== $key && ! empty( $set['enabled'] ) && sanitize_title( $set['slug'] ?? '' ) === $slug ) { $conflicts[] = 'システムページ「' . $key . '」'; }
		}
	}
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $object ) {
		if ( in_array( $type, array( 'post', 'page', 'attachment' ), true ) || $type === $exclude_cpt_key || isset( emcore_get_cpts()[ $type ] ) ) { continue; }
		$rewrite = is_array( $object->rewrite ) ? sanitize_title( $object->rewrite['slug'] ?? '' ) : '';
		if ( $rewrite === $slug ) { $conflicts[] = '登録済み投稿タイプ「' . $object->labels->name . '」'; }
	}
	return array_values( array_unique( $conflicts ) );
}

function emcore_slug_conflict_message( $slug, $conflicts ) {
	return 'スラッグ「' . sanitize_title( $slug ) . '」は' . implode( '、', $conflicts ) . 'と重複します。別のスラッグを入力してください。';
}

/** Paths that can be resolved without making an external HTTP request. */
function emcore_known_public_paths() {
	$paths = array( '/' => true );
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ), 'posts_per_page' => -1 ) ) as $page ) {
		$uri = get_page_uri( $page ); if ( $uri ) { $paths[ '/' . trim( $uri, '/' ) . '/' ] = true; }
	}
	foreach ( emcore_get_cpts() as $key => $cpt ) {
		$slug = function_exists( 'emcore_cpt_parent_slug' ) ? emcore_cpt_parent_slug( $cpt, $key ) : ( $cpt['parent_slug'] ?? ( $cpt['slug'] ?? $key ) );
		$paths[ '/' . trim( $slug, '/' ) . '/' ] = true;
	}
	if ( function_exists( 'emcore_system_pages_settings' ) ) {
		foreach ( emcore_system_pages_settings() as $key => $set ) { if ( '404' !== $key && ! empty( $set['enabled'] ) && ! empty( $set['slug'] ) ) { $paths[ '/' . trim( $set['slug'], '/' ) . '/' ] = true; } }
	}
	return $paths;
}

function emcore_normalize_internal_path( $path ) {
	$path = '/' . trim( (string) $path, '/' );
	return '/' === $path ? '/' : $path . '/';
}

/** Non-destructive checks: the original HTML is never changed by this function. */
function emcore_diagnose_html( $html ) {
	$issues = array();
	$add = function ( $level, $code, $message ) use ( &$issues ) {
		$issues[] = array( 'level' => $level, 'code' => $code, 'message' => $message );
	};
	if ( trim( (string) $html ) === '' ) {
		$add( 'error', 'empty', 'HTMLが空です。' );
		return $issues;
	}
	if ( ! preg_match( '/<!doctype|<html|<body/i', $html ) ) {
		$add( 'warning', 'fragment', 'HTML文書ではなく断片として読み込まれています。' );
	}
	if ( preg_match( '/<script\b/i', $html ) ) {
		$add( 'warning', 'script', 'JavaScriptを含みます。管理プレビューでは停止しますが公開ページでは実行されます。' );
	}
	if ( preg_match( '/\son[a-z]+\s*=/i', $html ) ) {
		$add( 'warning', 'event-handler', 'onclick等のイベント属性を含みます。信頼できるHTMLか確認してください。' );
	}
	if ( preg_match( '/<(iframe|object|embed)\b/i', $html ) ) {
		$add( 'warning', 'embedded-content', '外部コンテンツを含みます。URLと提供元を確認してください。' );
	}
	if ( preg_match( '/<meta[^>]+http-equiv=["\']?refresh/i', $html ) ) {
		$add( 'error', 'redirect', '自動転送するmeta refreshが含まれています。' );
	}
	$fields = emcore_fields_from_html( $html );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	$relative_assets = array(); $external_assets = array(); $empty_assets = 0;
	foreach ( $xpath->query( '//img[@src] | //source[@src] | //script[@src] | //link[@href]' ) as $node ) {
		$attribute = $node->hasAttribute( 'src' ) ? 'src' : 'href'; $value = trim( $node->getAttribute( $attribute ) );
		if ( '' === $value ) { $empty_assets++; continue; }
		if ( preg_match( '#^(?:data:|blob:)#i', $value ) ) { continue; }
		if ( preg_match( '#^(?:\./|\.\./|images?/|assets?/)#i', $value ) ) { $relative_assets[] = $value; continue; }
		if ( '/' === $value[0] && 0 !== strpos( $value, '/wp-content/' ) ) { $relative_assets[] = $value; continue; }
		if ( preg_match( '#^https?://#i', $value ) && 0 !== strpos( $value, home_url( '/' ) ) ) { $external_assets[] = $value; }
	}
	foreach ( $xpath->query( '//img[@srcset] | //source[@srcset]' ) as $node ) {
		foreach ( explode( ',', $node->getAttribute( 'srcset' ) ) as $candidate ) {
			$value = trim( preg_split( '/\s+/', trim( $candidate ) )[0] ?? '' );
			if ( '' === $value ) { $empty_assets++; continue; }
			if ( preg_match( '#^(?:\./|\.\./|images?/|assets?/)#i', $value ) || ( '/' === $value[0] && 0 !== strpos( $value, '/wp-content/' ) ) ) { $relative_assets[] = $value; }
			elseif ( preg_match( '#^https?://#i', $value ) && 0 !== strpos( $value, home_url( '/' ) ) ) { $external_assets[] = $value; }
		}
	}
	if ( preg_match_all( '/url\(\s*(["\']?)(?!data:)([^)"\']+)\1\s*\)/i', $html, $css_urls ) ) {
		foreach ( $css_urls[2] as $value ) { $value = trim( $value ); if ( preg_match( '#^(?:\./|\.\./|images?/|assets?/)#i', $value ) ) { $relative_assets[] = $value; } elseif ( preg_match( '#^https?://#i', $value ) && 0 !== strpos( $value, home_url( '/' ) ) ) { $external_assets[] = $value; } }
	}
	if ( $empty_assets ) { $add( 'error', 'empty-asset', '参照先が空の画像・CSS・JavaScriptがあります。' ); }
	if ( $relative_assets ) { $add( 'warning', 'local-assets', '未アップロードのローカル素材参照が' . count( array_unique( $relative_assets ) ) . '件あります。Coreへ反映する前にメディアへアップロードしてURLを書き換えてください。' ); }
	if ( $external_assets ) { $add( 'warning', 'external-assets', '外部ドメインの素材参照が' . count( array_unique( $external_assets ) ) . '件あります。意図した外部配信または許可済みCDNか確認してください。' ); }
	$known_paths = emcore_known_public_paths(); $unresolved = array(); $environment_links = array(); $relative_links = array();
	$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	foreach ( $xpath->query( '//a[@href]' ) as $link ) {
		$href = trim( $link->getAttribute( 'href' ) );
		if ( '' === $href || '#' === $href || false !== strpos( $href, '{{' ) || preg_match( '#^(?:https?:|mailto:|tel:|sms:|data:|javascript:)#i', $href ) || '#' === $href[0] ) { continue; }
		if ( '/' !== $href[0] ) { $relative_links[] = $href; continue; }
		$path = (string) wp_parse_url( $href, PHP_URL_PATH );
		if ( $home_path && 0 === strpos( trim( $path, '/' ), $home_path . '/' ) ) { $environment_links[] = $path; $path = substr( trim( $path, '/' ), strlen( $home_path ) + 1 ); }
		$path = emcore_normalize_internal_path( $path );
		if ( preg_match( '#\.[a-z0-9]{2,8}/?$#i', $path ) || 0 === strpos( $path, '/wp-' ) || isset( $known_paths[ $path ] ) ) { continue; }
		$first = '/' . strtok( trim( $path, '/' ), '/' ) . '/';
		if ( ! isset( $known_paths[ $first ] ) ) { $unresolved[] = $path; }
	}
	if ( $relative_links ) { $add( 'warning', 'relative-internal-link', 'ルート相対パスへ変更する内部リンクがあります: ' . implode( '、', array_slice( array_unique( $relative_links ), 0, 5 ) ) ); }
	if ( $environment_links ) { $add( 'warning', 'environment-path', '現在の設置先サブディレクトリを含む内部リンクが' . count( array_unique( $environment_links ) ) . '件あります。配布HTMLでは /slug/ 形式にしてください。' ); }
	if ( $unresolved ) { $sample = implode( '、', array_slice( array_unique( $unresolved ), 0, 5 ) ); $add( 'warning', 'unresolved-internal-link', 'リンク先ページまたは投稿タイプを確認できない内部リンクがあります: ' . $sample ); }
	foreach ( $fields as $field ) {
		if ( $field['type'] !== 'text' ) { continue; }
		foreach ( $xpath->query( '//*[@data-em-key="' . $field['key'] . '"]' ) as $node ) {
			if ( $node instanceof DOMElement && $node->getElementsByTagName( 'img' )->length ) {
				$add( 'warning', 'text-container', '「' . $field['label'] . '」は画像を含む親要素です。文字だけの子要素を選んでください。' );
				break;
			}
		}
	}
	return apply_filters( 'emcore_html_diagnostics', $issues, $html );
}

/** Compatibility report for the relaxed HTML Guide. Source HTML is never modified. */
function emcore_emhtml_compliance( $html ) {
	$checks = array();
	$add = function ( $ok, $label, $message = '' ) use ( &$checks ) {
		$checks[] = array( 'ok' => (bool) $ok, 'label' => $label, 'message' => $message );
	};
	if ( ! class_exists( 'DOMDocument' ) || trim( (string) $html ) === '' ) {
		return array( 'score' => 0, 'status' => 'non-conformant', 'checks' => array( array( 'ok' => false, 'label' => 'HTML文書', 'message' => 'HTMLを入力してください。' ) ) );
	}
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$loaded = $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	if ( ! $loaded ) { return array( 'score' => 0, 'status' => 'non-conformant', 'checks' => array() ); }
	$xpath = new DOMXPath( $dom );
	$doctype = stripos( ltrim( $html ), '<!doctype html>' ) === 0;
	$add( $doctype, 'HTML5文書', '先頭に <!doctype html> が必要です。' );
	$add( $dom->getElementsByTagName( 'html' )->length === 1 && $dom->getElementsByTagName( 'body' )->length === 1, '完全なHTML文書', 'htmlとbodyが必要です。' );
	$add( $xpath->query( '//body//header | //*[@data-em-part="header"]' )->length >= 1, 'Header', '通常のheader要素を用意してください。' );
	$add( $xpath->query( '//body//main | //*[@data-em-part="body"] | //body/*[contains(@class,"detail") or contains(@class,"page-content")]' )->length >= 1, 'Body', '通常のmain要素、またはページ本文を囲む要素が必要です。' );
	$add( $xpath->query( '//body//footer | //*[@data-em-part="footer"]' )->length >= 1, 'Footer', '通常のfooter要素を用意してください。' );
	$keys = array(); $duplicate = false; $editable_ok = true;
	foreach ( $xpath->query( '//*[@data-em-editable]' ) as $node ) {
		$key = $node->getAttribute( 'data-em-key' );
		if ( $key === '' || $node->getAttribute( 'data-em-label' ) === '' ) { $editable_ok = false; }
		if ( $key !== '' && isset( $keys[ $key ] ) && $keys[ $key ] !== $node->getAttribute( 'data-em-editable' ) ) { $duplicate = true; }
		$keys[ $key ] = $node->getAttribute( 'data-em-editable' );
	}
	$add( $editable_ok, '編集項目', '編集項目にはdata-em-keyとdata-em-labelが必要です。' );
	$add( ! $duplicate, 'キーの一貫性', '同じキーを異なる種類の項目へ使用しないでください。' );
	$add( ! preg_match( '/<img\b(?![^>]*\balt=)[^>]*>/i', $html ), '画像の代替テキスト', '画像にはalt属性が必要です。' );
	$form_ok = true;
	foreach ( $xpath->query( '//form[@data-em-component="form" or contains(concat(" ", normalize-space(@class), " "), " emcore-form ")]' ) as $form ) {
		$submit = ( new DOMXPath( $form->ownerDocument ) )->query( './/button[@type="submit"] | .//input[@type="submit"]', $form );
		if ( ! $submit || ! $submit->length ) { $form_ok = false; }
		$fields = ( new DOMXPath( $form->ownerDocument ) )->query( './/input[not(@type="submit") and not(@type="button") and not(@type="reset")] | .//textarea | .//select', $form );
		foreach ( $fields as $field ) {
			if ( $field->getAttribute( 'name' ) === '' ) { $form_ok = false; break; }
		}
	}
	$add( $form_ok, 'フォーム', 'Coreフォームには送信ボタンと、各入力欄のname属性が必要です。' );
	$dynamic_ok = true;
	foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " emcore-slider ")]' ) as $component ) {
		if ( ! ( new DOMXPath( $component->ownerDocument ) )->query( './/*[contains(concat(" ", normalize-space(@class), " "), " emcore-slider-item ")]', $component )->length ) { $dynamic_ok = false; }
	}
	foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " emcore-accordion ")]' ) as $component ) {
		if ( ! ( new DOMXPath( $component->ownerDocument ) )->query( './/*[contains(concat(" ", normalize-space(@class), " "), " emcore-accordion-item ")]', $component )->length ) { $dynamic_ok = false; }
	}
	foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " emcore-menu ")]' ) as $component ) {
		if ( ! ( new DOMXPath( $component->ownerDocument ) )->query( './/*[contains(concat(" ", normalize-space(@class), " "), " emcore-menu-item ")]', $component )->length ) { $dynamic_ok = false; }
	}
	foreach ( array( 'list' => 'list-item', 'steps' => 'step-item', 'gallery' => 'gallery-item', 'tabs' => 'tab-item', 'table' => 'table-row', 'repeater' => 'repeater-item' ) as $root_class => $item_class ) {
		foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " emcore-' . $root_class . ' ")]' ) as $component ) {
			if ( ! $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " emcore-' . $item_class . ' ")]', $component )->length ) { $dynamic_ok = false; }
		}
	}
	$add( $dynamic_ok, '動的コンテンツ', '親要素と繰り返し項目の両方へCore用目印classを追加してください。' );
	$passed = count( array_filter( $checks, function ( $check ) { return $check['ok']; } ) );
	$score = $checks ? (int) round( 100 * $passed / count( $checks ) ) : 0;
	$status = $score === 100 ? 'conformant' : ( $score >= 60 ? 'partial' : 'non-conformant' );
	return array( 'score' => $score, 'status' => $status, 'checks' => $checks );
}

/** Remove active content only for the wp-admin preview. Public HTML is untouched. */
function emcore_safe_preview_html( $html ) {
	// The iframe omits allow-scripts/forms, so scripts and inline event handlers cannot execute.
	$html = preg_replace_callback( '/<(iframe|embed)\b([^>]*)>/i', function ( $m ) {
		return '<' . $m[1] . preg_replace( '/\ssrc=(["\'])(.*?)\1/i', ' data-emcore-src=$1$2$1', $m[2] ) . '>';
	}, $html );
	$html = preg_replace_callback( '/<object\b([^>]*)>/i', function ( $m ) {
		return '<object' . preg_replace( '/\sdata=(["\'])(.*?)\1/i', ' data-emcore-object-data=$1$2$1', $m[1] ) . '>';
	}, $html );
	$html = preg_replace( '/(<meta[^>]+)http-equiv=(["\']?)refresh\2/i', '$1data-emcore-http-equiv="refresh"', $html );
	return apply_filters( 'emcore_safe_preview_html', $html );
}

function emcore_template_usage( $id ) {
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_emcore_tpl_id', 'meta_value' => $id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
	$cpts = array();
	foreach ( emcore_get_cpts() as $key => $cpt ) {
		if ( ( $cpt['archive_tpl'] ?? '' ) === $id || ( $cpt['single_tpl'] ?? '' ) === $id ) {
			$cpts[] = $key;
		}
	}
	return array( 'pages' => $pages, 'cpts' => $cpts );
}

function emcore_make_tpl_id() {
	return 'tpl_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 );
}

/**
 * Keep raw-text elements byte-for-byte intact while DOMDocument edits structure.
 * DOMDocument entity-encodes Unicode inside script/style, which changes JavaScript
 * string values (for example heart particles) and can expose source-like text.
 */
function emcore_protect_raw_html_blocks( $html, &$blocks ) {
	$blocks = array();
	return preg_replace_callback(
		'/<(script|style)\b[^>]*>.*?<\/\1\s*>/is',
		function ( $match ) use ( &$blocks ) {
			$key            = 'EMCORE_RAW_' . count( $blocks ) . '_' . substr( md5( $match[0] ), 0, 12 );
			$blocks[ $key ] = $match[0];
			return '<!--' . $key . '-->';
		},
		(string) $html
	);
}

function emcore_restore_raw_html_blocks( $html, $blocks ) {
	foreach ( (array) $blocks as $key => $raw ) {
		$html = str_replace( '<!--' . $key . '-->', $raw, $html );
	}
	return $html;
}

/** Repair templates imported by 1.16.0-1.16.4 before raw blocks were protected. */
function emcore_repair_legacy_raw_html_blocks( $html ) {
	return preg_replace_callback(
		'/<(script|style)\b([^>]*)>(.*?)<\/\1\s*>/is',
		function ( $match ) {
			return '<' . $match[1] . $match[2] . '>' . html_entity_decode( $match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '</' . $match[1] . '>';
		},
		(string) $html
	);
}

/**
 * Prevent an external, parser-blocking script in <head> from delaying inline CSS.
 * The style bytes and script bytes remain untouched; only their safe load order is corrected.
 */
function emcore_promote_head_styles_before_scripts( $html ) {
	return preg_replace_callback(
		'/<head\b([^>]*)>(.*?)<\/head\s*>/is',
		function ( $head ) {
			$inner = $head[2];
			if ( ! preg_match( '/<script\b[^>]*\bsrc\s*=/i', $inner, $script_match, PREG_OFFSET_CAPTURE ) ) { return $head[0]; }
			$script_at = $script_match[0][1];
			if ( ! preg_match_all( '/<style\b[^>]*>.*?<\/style\s*>/is', $inner, $styles, PREG_OFFSET_CAPTURE ) ) { return $head[0]; }
			$move = array();
			foreach ( $styles[0] as $style ) { if ( $style[1] > $script_at ) { $move[] = $style[0]; } }
			if ( ! $move ) { return $head[0]; }
			foreach ( $move as $style ) { $inner = preg_replace( '/' . preg_quote( $style, '/' ) . '/', '', $inner, 1 ); }
			$inner = preg_replace( '/(?=<script\b[^>]*\bsrc\s*=)/i', implode( "\n", $move ) . "\n", $inner, 1 );
			return '<head' . $head[1] . '>' . $inner . '</head>';
		},
		(string) $html,
		1
	);
}

/**
 * Add only structural hints Core needs. Visible copy, images and scripts are preserved.
 * Ordinary HTML remains the source of truth; data-em-* is an internal enhancement.
 */
function emcore_prepare_import_html( $html, $type = 'page', $post_type = '' ) {
	if ( ! class_exists( 'DOMDocument' ) || trim( (string) $html ) === '' ) { return $html; }
	$html = emcore_promote_head_styles_before_scripts( $html );
	$html = emcore_protect_raw_html_blocks( $html, $raw_blocks );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if ( ! $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ) ) { return emcore_restore_raw_html_blocks( $html, $raw_blocks ); }
	$xpath = new DOMXPath( $dom );
	$label = function ( $node, $fallback ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
		return $text !== '' && mb_strlen( $text ) <= 40 ? $text : $fallback;
	};
	$set = function ( $node, $attrs ) {
		if ( ! $node instanceof DOMElement ) { return; }
		foreach ( $attrs as $key => $value ) { if ( ! $node->hasAttribute( $key ) ) { $node->setAttribute( $key, $value ); } }
	};
	$header = $xpath->query( '//body//header[1]' );
	$footer = $xpath->query( '//body//footer[last()]' );
	$main   = $xpath->query( '//body//main[1]' );
	if ( ! $main->length ) { $main = $xpath->query( '//body/*[contains(concat(" ",normalize-space(@class)," ")," detail ") or contains(@class,"page-main") or contains(@class,"page-content")][1]' ); }
	if ( $header->length ) { $set( $header->item( 0 ), array( 'data-em-part' => 'header', 'data-em-part-id' => 'site-header', 'data-em-label' => 'ヘッダー' ) ); }
	if ( $footer->length ) { $set( $footer->item( 0 ), array( 'data-em-part' => 'footer', 'data-em-part-id' => 'site-footer', 'data-em-label' => 'フッター' ) ); }
	if ( $main->length ) {
		$body_type = $type === 'archive' ? 'archive' : ( $type === 'single' ? 'single' : 'page' );
		$attrs = array( 'data-em-part' => 'body', 'data-em-part-id' => sanitize_title( $post_type ? $post_type . '-' . $body_type : 'page-body' ), 'data-em-label' => $type === 'archive' ? '一覧' : ( $type === 'single' ? '個別ページ' : '本文' ), 'data-em-template-type' => $body_type );
		if ( $post_type ) { $attrs['data-em-post-type'] = sanitize_key( $post_type ); }
		$set( $main->item( 0 ), $attrs );
	}

	/*
	 * Do not guess editable content from visual class names. Guessing changed otherwise
	 * valid HTML and made the editor difficult to understand. Dynamic content is now
	 * discovered from additive emcore-* marker classes and confirmed by the user in
	 * the visual editor. Existing data-em-* attributes remain fully compatible.
	 */
	$out = $dom->saveHTML();
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $out );
	$out = emcore_restore_raw_html_blocks( $out, $raw_blocks );
	libxml_clear_errors();
	return $out;
}

/** Render a useful admin preview while keeping stored HTML untouched. */
function emcore_template_preview_html( $template ) {
	$html = $template['html'] ?? '';
	$type = $template['type'] ?? 'page';
	if ( $type === 'archive' ) {
		$post_type = sanitize_key( $template['post_type_hint'] ?? '' );
		if ( ! $post_type ) {
			foreach ( emcore_get_cpts() as $key => $cpt ) { if ( ( $cpt['archive_tpl'] ?? '' ) === ( $template['id'] ?? '' ) ) { $post_type = $key; break; } }
		}
		$posts = $post_type && post_type_exists( $post_type ) ? get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 6 ) ) : array();
		if ( $posts ) { $html = emcore_render_archive_loop( $html, $posts ); }
		elseif ( preg_match( '/\{\{#posts\}\}(.*?)\{\{\/posts\}\}/s', $html, $match ) ) {
			$sample = str_replace( array( '{{post.title}}', '{{post.permalink}}', '{{post.excerpt}}', '{{post.date}}', '{{post.meta:name_en}}' ), array( 'サンプル名', '#', '投稿内容の見本です。', wp_date( 'Y.m.d' ), 'SAMPLE NAME' ), $match[1] );
			$svg = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 1200"><rect width="900" height="1200" fill="#ffdce5"/><text x="450" y="600" text-anchor="middle" fill="#ff6b8b" font-size="54">IMAGE</text></svg>' );
			$sample = str_replace( array( '{{post.thumbnail}}', '{{post.thumbnail_url}}' ), array( '<img src="' . esc_attr( $svg ) . '" alt="サンプル画像">', esc_attr( $svg ) ), $sample );
			$sample = preg_replace( '/\{\{post\.meta:[^}]+\}\}/', 'SAMPLE', $sample );
			$html = str_replace( $match[0], $sample, $html );
		}
	}
	return emcore_safe_preview_html( $html );
}

function emcore_field_presets() {
	return apply_filters( 'emcore_field_presets', array(
		'title'     => array( 'label' => 'タイトル', 'type' => 'text', 'store' => 'title' ),
		'content'   => array( 'label' => '本文', 'type' => 'textarea', 'store' => 'content' ),
		'thumbnail' => array( 'label' => '一覧用の画像', 'type' => 'image', 'store' => 'thumbnail' ),
		'standee'   => array( 'label' => 'このページの画像', 'type' => 'image', 'store' => 'meta' ),
		'reading'   => array( 'label' => 'よみがな', 'type' => 'text', 'store' => 'meta' ),
		'name_en'   => array( 'label' => '英語名', 'type' => 'text', 'store' => 'meta' ),
		'sns_x'     => array( 'label' => 'X のURL', 'type' => 'url', 'store' => 'meta' ),
		'sns_iriam' => array( 'label' => 'IRIAM のURL', 'type' => 'url', 'store' => 'meta' ),
		'logo'      => array( 'label' => 'ロゴ', 'type' => 'image', 'store' => 'meta' ),
		'excerpt'   => array( 'label' => '抜粋', 'type' => 'textarea', 'store' => 'meta' ),
	) );
}

function emcore_field_label( $key, $type = 'text' ) {
	$presets = emcore_field_presets();
	if ( isset( $presets[ $key ] ) ) {
		return $presets[ $key ]['label'];
	}
	if ( $type === 'image' || $type === 'bg' ) {
		return '画像（' . $key . '）';
	}
	if ( $type === 'href' || $type === 'url' ) {
		return 'リンク（' . $key . '）';
	}
	if ( in_array( $type, array( 'slides', 'slider' ), true ) ) {
		return 'スライダー（' . $key . '）';
	}
	if ( in_array( $type, array( 'accordion', 'faq' ), true ) ) {
		return 'FAQ／アコーディオン（' . $key . '）';
	}
	if ( 'menu' === $type ) {
		return 'メニュー（' . $key . '）';
	}
	$dynamic_labels = array( 'list' => 'リスト', 'steps' => 'ステップ', 'gallery' => 'ギャラリー', 'tabs' => 'タブ', 'table' => '表', 'repeater' => 'カード一覧' );
	if ( isset( $dynamic_labels[ $type ] ) ) { return $dynamic_labels[ $type ] . '（' . $key . '）'; }
	return $key;
}

/** Font families registered through theme.json or the WordPress Font Library. */
function emcore_get_font_choices() {
	$choices = array();
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$groups = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$walk = function ( $items ) use ( &$walk, &$choices ) {
			foreach ( (array) $items as $item ) {
				if ( isset( $item['fontFamily'] ) && is_string( $item['fontFamily'] ) ) {
					$family = trim( wp_strip_all_tags( $item['fontFamily'] ) );
					$name   = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : $family;
					if ( $family !== '' ) { $choices[ $family ] = $name; }
				} elseif ( is_array( $item ) ) {
					$walk( $item );
				}
			}
		};
		$walk( $groups );
	}
	if ( post_type_exists( 'wp_font_family' ) ) {
		foreach ( get_posts( array( 'post_type' => 'wp_font_family', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $font ) {
			$family = trim( wp_strip_all_tags( $font->post_title ) );
			if ( $family !== '' && ! isset( $choices[ $family ] ) ) { $choices[ $family ] = $family; }
		}
	}
	return apply_filters( 'emcore_font_choices', $choices );
}

function emcore_sanitize_font_family( $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	if ( $value === '' ) { return ''; }
	return isset( emcore_get_font_choices()[ $value ] ) ? $value : '';
}

/**
 * Unique editable fields from template HTML (marks).
 *
 * @return array[] { key, type, label, store }
 */
function emcore_fields_from_html( $html ) {
	$fields = array();
	if ( ! $html ) {
		return $fields;
	}
	$presets = emcore_field_presets();
	if ( preg_match_all( '/data-em-editable=["\']([^"\']+)["\'][^>]*data-em-key=["\']([^"\']+)["\']/i', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) {
			$type = sanitize_key( $row[1] );
			$key  = sanitize_key( $row[2] );
			if ( ! $key ) {
				continue;
			}
			if ( isset( $fields[ $key ] ) ) {
				continue;
			}
			$store = isset( $presets[ $key ]['store'] ) ? $presets[ $key ]['store'] : 'meta';
			$itype = $type;
			if ( $type === 'bg' ) {
				$itype = 'image';
			}
			if ( in_array( $type, array( 'href', 'url', 'link' ), true ) ) {
				$itype = 'url';
			}
			if ( in_array( $type, array( 'slides', 'slider' ), true ) ) {
				$itype = 'slides';
			}
			if ( in_array( $type, array( 'accordion', 'faq' ), true ) ) {
				$itype = 'accordion';
			}
			if ( 'menu' === $type ) {
				$itype = 'menu';
			}
			$fields[ $key ] = array(
				'key'   => $key,
				'type'  => $itype,
				'mark'  => $type,
				'label' => emcore_field_label( $key, $type ),
				'scope' => 'item',
				'store' => $store,
			);
		}
	}
	if ( preg_match_all( '/data-em-label=["\']([^"\']+)["\'][^>]*data-em-key=["\']([^"\']+)["\']/i', $html, $lb, PREG_SET_ORDER ) ) {
		foreach ( $lb as $row ) {
			$l = sanitize_text_field( $row[1] );
			$k = sanitize_key( $row[2] );
			if ( $k && $l && isset( $fields[ $k ] ) ) {
				$fields[ $k ]['label'] = $l;
			}
		}
	}
	if ( preg_match_all( '/data-em-key=["\']([^"\']+)["\'][^>]*data-em-label=["\']([^"\']+)["\']/i', $html, $lb2, PREG_SET_ORDER ) ) {
		foreach ( $lb2 as $row ) {
			$k = sanitize_key( $row[1] );
			$l = sanitize_text_field( $row[2] );
			if ( $k && $l && isset( $fields[ $k ] ) ) {
				$fields[ $k ]['label'] = $l;
			}
		}
	}
	if ( preg_match_all( '/data-em-key=["\']([^"\']+)["\'][^>]*data-em-editable=["\']([^"\']+)["\']/i', $html, $m2, PREG_SET_ORDER ) ) {
		foreach ( $m2 as $row ) {
			$key  = sanitize_key( $row[1] );
			$type = sanitize_key( $row[2] );
			if ( ! $key || isset( $fields[ $key ] ) ) {
				continue;
			}
			$store = isset( $presets[ $key ]['store'] ) ? $presets[ $key ]['store'] : 'meta';
			$itype = $type;
			if ( $type === 'bg' ) {
				$itype = 'image';
			}
			if ( in_array( $type, array( 'href', 'url', 'link' ), true ) ) {
				$itype = 'url';
			}
			if ( in_array( $type, array( 'slides', 'slider' ), true ) ) {
				$itype = 'slides';
			}
			if ( in_array( $type, array( 'accordion', 'faq' ), true ) ) {
				$itype = 'accordion';
			}
			if ( 'menu' === $type ) {
				$itype = 'menu';
			}
			$fields[ $key ] = array(
				'key'   => $key,
				'type'  => $itype,
				'mark'  => $type,
				'label' => emcore_field_label( $key, $type ),
				'scope' => 'item',
				'store' => $store,
			);
		}
	}
	if ( preg_match_all( '/data-em-key=["\']([^"\']+)["\'][^>]*data-em-scope=["\']([^"\']+)["\']/i', $html, $sc, PREG_SET_ORDER ) ) {
		foreach ( $sc as $row ) {
			$k = sanitize_key( $row[1] );
			$s = sanitize_key( $row[2] );
			if ( $k && isset( $fields[ $k ] ) && in_array( $s, array( 'item', 'template', 'site' ), true ) ) {
				$fields[ $k ]['scope'] = $s;
			}
		}
	}
	if ( preg_match_all( '/data-em-scope=["\']([^"\']+)["\'][^>]*data-em-key=["\']([^"\']+)["\']/i', $html, $sc2, PREG_SET_ORDER ) ) {
		foreach ( $sc2 as $row ) {
			$s = sanitize_key( $row[1] );
			$k = sanitize_key( $row[2] );
			if ( $k && isset( $fields[ $k ] ) && in_array( $s, array( 'item', 'template', 'site' ), true ) ) {
				$fields[ $k ]['scope'] = $s;
			}
		}
	}
	if ( strpos( $html, 'data-em-responsive' ) !== false ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
		foreach ( ( new DOMXPath( $dom ) )->query( '//*[@data-em-responsive and @data-em-key]' ) as $node ) {
			$key = sanitize_key( $node->getAttribute( 'data-em-key' ) );
			if ( ! isset( $fields[ $key ] ) ) { continue; }
			$variants = json_decode( html_entity_decode( $node->getAttribute( 'data-em-variants' ), ENT_QUOTES, 'UTF-8' ), true );
			if ( ! is_array( $variants ) ) {
				$variants = array( 'default' => '', 'sources' => array() );
			}
			if ( ! isset( $variants['sources'] ) || ! is_array( $variants['sources'] ) ) { $variants['sources'] = array(); }
			if ( ! isset( $variants['default'] ) ) { $variants['default'] = ''; }
			$picture = strtolower( $node->tagName ) === 'picture' ? $node : $node->parentNode;
			if ( $picture instanceof DOMElement && strtolower( $picture->tagName ) === 'picture' ) {
				foreach ( $picture->getElementsByTagName( 'source' ) as $source ) {
					$media = $source->getAttribute( 'media' );
					if ( $media && empty( $variants['sources'][ $media ] ) ) { $variants['sources'][ $media ] = $source->getAttribute( 'srcset' ); }
				}
				$imgs = $picture->getElementsByTagName( 'img' );
				if ( empty( $variants['default'] ) && $imgs->length ) { $variants['default'] = $imgs->item( 0 )->getAttribute( 'src' ); }
			}
			$fields[ $key ]['responsive'] = true;
			$fields[ $key ]['variants'] = $variants;
		}
	}
	if ( strpos( $html, 'data-em-font-editable' ) !== false ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
		foreach ( ( new DOMXPath( $dom ) )->query( '//*[@data-em-font-editable="1" and @data-em-key]' ) as $node ) {
			$key = sanitize_key( $node->getAttribute( 'data-em-key' ) );
			if ( isset( $fields[ $key ] ) && in_array( $fields[ $key ]['type'], array( 'text', 'textarea', 'url', 'href' ), true ) ) {
				$fields[ $key ]['font_editable'] = true;
			}
		}
	}
	if ( preg_match( '/data-em-editable=["\'](?:slides|accordion|menu|list|steps|gallery|tabs|table|repeater)["\']/i', $html ) ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
		$xpath = new DOMXPath( $dom );
		foreach ( $fields as $key => &$field ) {
			$nodes = $xpath->query( '//*[@data-em-key="' . $key . '"]' );
			if ( ! $nodes->length ) { continue; }
			$box = $nodes->item( 0 );
			if ( $field['type'] === 'slides' ) {
				$defaults = array();
				foreach ( $box->getElementsByTagName( 'img' ) as $img ) {
					$src = $img->getAttribute( 'src' );
					if ( $src !== '' ) { $defaults[] = $src; }
				}
				$field['defaults'] = array_values( array_unique( $defaults ) );
			} elseif ( $field['type'] === 'accordion' ) {
				$defaults = array();
				$items = $xpath->query( './/*[@data-em-accordion-item="1"]', $box );
				if ( ! $items->length ) { $items = $box->childNodes; }
				foreach ( $items as $item ) {
					if ( ! $item instanceof DOMElement ) { continue; }
					$q = $xpath->query( './/*[@data-em-accordion-question="1"]', $item );
					$a = $xpath->query( './/*[@data-em-accordion-answer="1"]', $item );
					if ( $q->length && $a->length ) {
						$defaults[] = array( 'question' => trim( $q->item( 0 )->textContent ), 'answer' => trim( $a->item( 0 )->textContent ) );
					}
				}
				$field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'menu' ) {
				$defaults = array();
				$items = $xpath->query( './/*[@data-em-menu-item="1"]', $box );
				if ( ! $items->length ) { $items = $xpath->query( './/a[@href]', $box ); }
				foreach ( $items as $item ) {
					if ( ! $item instanceof DOMElement ) { continue; }
					$link = strtolower( $item->tagName ) === 'a' ? $item : $xpath->query( './/a[@href]', $item )->item( 0 );
					if ( ! $link instanceof DOMElement ) { continue; }
					$label_node = $xpath->query( './/*[@data-em-menu-label="1"]', $item )->item( 0 );
					if ( ! $label_node && $item->getAttribute( 'data-em-menu-label' ) === '1' ) { $label_node = $item; }
					$defaults[] = array(
						'label'  => trim( $label_node ? $label_node->textContent : $link->textContent ),
						'url'    => $link->getAttribute( 'href' ),
						'target' => $link->getAttribute( 'target' ),
					);
				}
				$field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'list' ) {
				$defaults = array(); foreach ( $xpath->query( './/*[@data-em-list-item="1"]', $box ) as $item ) { $text = $item->getAttribute( 'data-em-list-text' ) === '1' ? $item : $xpath->query( './/*[@data-em-list-text="1"]', $item )->item( 0 ); $defaults[] = array( 'text' => trim( ( $text ?: $item )->textContent ) ); } $field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'steps' ) {
				$defaults = array(); foreach ( $xpath->query( './/*[@data-em-step-item="1"]', $box ) as $item ) { $title = $xpath->query( './/*[@data-em-step-title="1"]', $item )->item( 0 ); $desc = $xpath->query( './/*[@data-em-step-description="1"]', $item )->item( 0 ); $defaults[] = array( 'title' => trim( $title ? $title->textContent : '' ), 'description' => trim( $desc ? $desc->textContent : '' ) ); } $field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'gallery' ) {
				$defaults = array(); foreach ( $xpath->query( './/*[@data-em-gallery-item="1"]', $box ) as $item ) { $img = $xpath->query( './/*[@data-em-gallery-image="1"]', $item )->item( 0 ); $link = $xpath->query( './/*[@data-em-gallery-link="1"]', $item )->item( 0 ); $defaults[] = array( 'image' => $img ? $img->getAttribute( 'src' ) : '', 'alt' => $img ? $img->getAttribute( 'alt' ) : '', 'url' => $link ? $link->getAttribute( 'href' ) : '' ); } $field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'tabs' ) {
				$defaults = array(); foreach ( $xpath->query( './/*[@data-em-tab-item="1"]', $box ) as $item ) { $trigger = $xpath->query( './/*[@data-em-tab-trigger="1"]', $item )->item( 0 ); $content = $xpath->query( './/*[@data-em-tab-content="1"]', $item )->item( 0 ); $defaults[] = array( 'label' => trim( $trigger ? $trigger->textContent : '' ), 'content' => trim( $content ? $content->textContent : '' ) ); } $field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'table' ) {
				$defaults = array(); foreach ( $xpath->query( './/*[@data-em-table-row="1"]', $box ) as $row ) { $cells = array(); foreach ( $xpath->query( './/*[@data-em-table-cell="1"]', $row ) as $cell ) { $cells[] = trim( $cell->textContent ); } $defaults[] = array( 'cells' => $cells ); } $field['defaults'] = $defaults;
			} elseif ( $field['type'] === 'repeater' ) {
				$defaults = array(); foreach ( $xpath->query( './/*[@data-em-repeater-item="1"]', $box ) as $item ) { $values = array(); $i = 0; foreach ( $xpath->query( './/*[@data-em-repeater-field]', $item ) as $node ) { $field_key = sanitize_key( $node->getAttribute( 'data-em-repeater-field' ) ) ?: 'field_' . (++$i); $tag = strtolower( $node->tagName ); $values[ $field_key ] = $tag === 'img' ? $node->getAttribute( 'src' ) : ( $tag === 'a' ? $node->getAttribute( 'href' ) : trim( $node->textContent ) ); } $defaults[] = $values; } $field['defaults'] = $defaults;
			}
		}
		unset( $field );
	}
	// 管理画面上の編集先。旧テンプレートはDOM上の位置から補完する。
	if ( class_exists( 'DOMDocument' ) && $fields ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		if ( $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ) ) {
			$xpath = new DOMXPath( $dom );
			foreach ( $fields as $key => &$field ) {
				$nodes = $xpath->query( '//*[@data-em-key="' . $key . '"]' );
				if ( ! $nodes->length ) { continue; }
				$node = $nodes->item( 0 );
				$location = sanitize_key( $node->getAttribute( 'data-em-location' ) );
				if ( ! in_array( $location, array( 'header', 'footer', 'body' ), true ) ) {
					$location = 'body';
					for ( $parent = $node; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
						$tag = strtolower( $parent->tagName );
						if ( 'header' === $tag || 'footer' === $tag ) { $location = $tag; break; }
					}
				}
				$field['location'] = $location;
			}
			unset( $field );
		}
		libxml_clear_errors();
	}
	return apply_filters( 'emcore_template_fields', $fields, $html );
}

function emcore_extract_editables( $html ) {
	$grouped = array(
		'texts'  => array(),
		'images' => array(),
		'bgs'    => array(),
		'links'  => array(),
	);
	foreach ( emcore_fields_from_html( $html ) as $f ) {
		$item = array( 'type' => $f['mark'], 'key' => $f['key'] );
		if ( $f['mark'] === 'image' ) {
			$grouped['images'][] = $item;
		} elseif ( $f['mark'] === 'bg' ) {
			$grouped['bgs'][] = $item;
		} elseif ( $f['mark'] === 'href' ) {
			$grouped['links'][] = $item;
		} else {
			$grouped['texts'][] = $item;
		}
	}
	return $grouped;
}

function emcore_get_post_field_value( $post, $field ) {
	$key   = $field['key'];
	$store = $field['store'];
	if ( $store === 'title' ) {
		return $post->post_title;
	}
	if ( $store === 'content' ) {
		return $post->post_content;
	}
	if ( $store === 'thumbnail' ) {
		$id = get_post_thumbnail_id( $post );
		return $id ? wp_get_attachment_url( $id ) : '';
	}
	$meta = get_post_meta( $post->ID, '_emcore_' . $key, true );
	if ( $meta === '' && in_array( $key, array( 'reading', 'name_en', 'sns_x', 'sns_iriam' ), true ) ) {
		$meta = get_post_meta( $post->ID, $key, true );
	}
	if ( ( $field['type'] ?? '' ) === 'slides' ) {
		if ( is_array( $meta ) ) {
			return wp_json_encode( $meta );
		}
		if ( is_string( $meta ) && $meta !== '' ) {
			return $meta;
		}
		return wp_json_encode( isset( $field['defaults'] ) ? $field['defaults'] : array() );
	}
	if ( ( $field['type'] ?? '' ) === 'accordion' ) {
		if ( is_string( $meta ) && $meta !== '' ) { return $meta; }
		return wp_json_encode( isset( $field['defaults'] ) ? $field['defaults'] : array() );
	}
	if ( ( $field['type'] ?? '' ) === 'menu' ) {
		if ( is_string( $meta ) && $meta !== '' ) { return $meta; }
		return wp_json_encode( isset( $field['defaults'] ) ? $field['defaults'] : array() );
	}
	if ( in_array( ( $field['type'] ?? '' ), array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ) {
		if ( is_string( $meta ) && $meta !== '' ) { return $meta; }
		return wp_json_encode( isset( $field['defaults'] ) ? $field['defaults'] : array() );
	}
	if ( ( $field['type'] ?? '' ) === 'image' && ! empty( $field['responsive'] ) && $meta === '' ) {
		return wp_json_encode( isset( $field['variants'] ) ? $field['variants'] : array( 'default' => '', 'sources' => array() ) );
	}
	return is_string( $meta ) || is_numeric( $meta ) ? (string) $meta : '';
}

function emcore_get_post_field_image_id( $post, $field ) {
	if ( $field['store'] === 'thumbnail' ) {
		return (int) get_post_thumbnail_id( $post );
	}
	return (int) get_post_meta( $post->ID, '_emcore_' . $field['key'] . '_id', true );
}


function emcore_get_content_pages() {
	$by_id = array();
	$q1 = get_posts( array( 'post_type' => 'page', 'meta_key' => '_emcore_tpl_id', 'posts_per_page' => 50, 'post_status' => 'any' ) );
	$q2 = get_posts( array( 'post_type' => 'page', 'meta_key' => '_emcore_archive_cpt', 'posts_per_page' => 50, 'post_status' => 'any' ) );
	foreach ( array_merge( $q1, $q2 ) as $pg ) {
		$by_id[ $pg->ID ] = $pg;
	}
	return array_values( $by_id );
}

function emcore_template_id_for_page( $post ) {
	$tpl_id = get_post_meta( $post->ID, '_emcore_tpl_id', true );
	if ( $tpl_id ) {
		return $tpl_id;
	}
	$cpt = get_post_meta( $post->ID, '_emcore_archive_cpt', true );
	if ( $cpt ) {
		return emcore_cpt_archive_template_id( $cpt );
	}
	return '';
}


function emcore_get_site_shared() {
	$s = get_option( 'emcore_site_shared', array() );
	return is_array( $s ) ? $s : array();
}
function emcore_save_site_shared( $arr ) {
	update_option( 'emcore_site_shared', is_array( $arr ) ? $arr : array(), false );
}
function emcore_get_tpl_shared( $tpl_id ) {
	$tpl = emcore_get_template( $tpl_id );
	if ( ! $tpl || empty( $tpl['shared'] ) || ! is_array( $tpl['shared'] ) ) {
		return array();
	}
	return $tpl['shared'];
}
