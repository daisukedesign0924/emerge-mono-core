<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a text-independent DOM fingerprint for a shared page region.
 * Active/current state classes are intentionally ignored between pages.
 */
function emcore_common_region_signature( $html, $region ) {
	if ( ! $html || ! class_exists( 'DOMDocument' ) ) { return ''; }
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$loaded = $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
	libxml_clear_errors();
	if ( ! $loaded ) { return ''; }
	$nodes = ( new DOMXPath( $dom ) )->query( '//' . $region );
	if ( ! $nodes || ! $nodes->length ) { return ''; }
	$parts = array();
	$walk = function ( $node ) use ( &$walk, &$parts ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType || count( $parts ) >= 180 ) { return; }
		$classes = preg_split( '/\s+/', strtolower( $node->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
		$classes = array_values( array_filter( $classes, function ( $class ) {
			return ! preg_match( '/(?:^|[-_])(active|current|selected|open)(?:$|[-_])/', $class );
		} ) );
		sort( $classes );
		$parts[] = strtolower( $node->nodeName ) . ( $classes ? '.' . implode( '.', array_slice( $classes, 0, 5 ) ) : '' );
		foreach ( $node->childNodes as $child ) { $walk( $child ); }
	};
	$walk( $nodes->item( 0 ) );
	return $parts ? md5( implode( '>', $parts ) ) : '';
}

function emcore_page_dashboard() {
	emcore_shell_start( 'Dashboard' );
	if ( ! current_user_can( emcore_design_capability() ) ) {
		echo '<div class="emcore-panel"><h2>コンテンツ編集</h2><p>左のCONTENTから編集するページまたは投稿を選んでください。</p></div>';
		emcore_shell_end();
		return;
	}
	$tpls = emcore_get_templates();
	$cpts = emcore_get_cpts();
	echo '<div data-emcore-workspace-content="customize">';
	echo '<section class="emcore-dashboard-intro"><span class="emcore-eyebrow">HTML TO WORDPRESS</span><h2>完成したデザインを、<br>安全に更新できるサイトへ。</h2><p>HTMLを取り込み、編集箇所だけを選んでクライアントへ渡せます。</p><div><a class="emcore-btn emcore-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=emcore-templates' ) ) . '">テンプレートを管理</a><a class="emcore-btn" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer">サイトを見る ↗</a></div></section>';
	echo '<div class="emcore-cards">';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-templates' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-media-code"></span><span>01</span></div><div class="emcore-card-title">HTML Templates</div><div class="emcore-card-num">' . count( $tpls ) . '</div><span class="emcore-card-link">テンプレート管理 →</span></a>';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-cpts' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-index-card"></span><span>02</span></div><div class="emcore-card-title">Post Types</div><div class="emcore-card-num">' . count( $cpts ) . '</div><span class="emcore-card-link">投稿タイプ管理 →</span></a>';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-html-rules' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-media-text"></span><span>03</span></div><div class="emcore-card-title">HTML Guide</div><div class="emcore-card-num">1.2</div><span class="emcore-card-link">ガイドと互換性チェック →</span></a>';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-forms' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-email-alt"></span><span>04</span></div><div class="emcore-card-title">Core Forms</div><div class="emcore-card-num">' . count( emcore_get_managed_forms() ) . '</div><span class="emcore-card-link">送信設定を管理 →</span></a>';
	echo '</div>';
	echo '<div class="emcore-panel" style="margin-top:24px"><h2>使い方</h2><ol class="emcore-steps">';
	echo '<li><strong>カスタマイズ</strong>：Templates に完成HTMLを登録し、プレビュー上で「変更できる場所」を指定します。</li>';
	echo '<li><strong>カスタマイズ</strong>：Post Types で投稿タイプを作り、一覧用・個別用テンプレートを紐付けます。</li>';
	echo '<li><strong>管理</strong>：ページや投稿タイプから、公開後のコンテンツを追加・編集します。</li>';
	echo '</ol></div></div>';
	$managed_pages = emcore_get_content_pages();
	echo '<div data-emcore-workspace-content="manage" hidden>';
	echo '<section class="emcore-dashboard-intro"><span class="emcore-eyebrow">SITE MANAGEMENT</span><h2>今日の更新を、<br>迷わず終わらせる。</h2><p>公開中のページ、共通コンテンツ、投稿をひとつの場所から管理します。</p><div><a class="emcore-btn emcore-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=emcore-pages' ) ) . '">ページを管理</a><a class="emcore-btn" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer">サイトを見る ↗</a></div></section>';
	echo '<div class="emcore-cards">';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-pages' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-admin-page"></span><span>01</span></div><div class="emcore-card-title">Pages</div><div class="emcore-card-num">' . count( $managed_pages ) . '</div><span class="emcore-card-link">ページ管理 →</span></a>';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-shared-header' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-align-wide"></span><span>02</span></div><div class="emcore-card-title">Header</div><div class="emcore-card-num">—</div><span class="emcore-card-link">共通内容を編集 →</span></a>';
	echo '<a class="emcore-card" href="' . esc_url( admin_url( 'admin.php?page=emcore-shared-footer' ) ) . '"><div class="emcore-card-head"><span class="dashicons dashicons-align-full-width"></span><span>03</span></div><div class="emcore-card-title">Footer</div><div class="emcore-card-num">—</div><span class="emcore-card-link">共通内容を編集 →</span></a>';
	echo '<a class="emcore-card" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer"><div class="emcore-card-head"><span class="dashicons dashicons-visibility"></span><span>04</span></div><div class="emcore-card-title">Site Status</div><div class="emcore-card-num">LIVE</div><span class="emcore-card-link">サイトを見る ↗</span></a>';
	echo '</div></div>';
	emcore_shell_end();
}

function emcore_page_pages() {
	emcore_shell_start( 'ページ管理' );
	$pages = emcore_get_content_pages();
	echo '<div class="emcore-section-heading"><div><span class="emcore-eyebrow">MANAGE / PAGES</span><h2>ページ管理</h2><p>Coreで作成した固定ページを、ひとつの画面から管理します。</p></div></div>';
	echo '<div class="emcore-table-wrap"><table class="emcore-table emcore-content-table"><thead><tr><th>ページ</th><th>テンプレート</th><th>公開状態</th><th>操作</th></tr></thead><tbody>';
	if ( ! $pages ) {
		echo '<tr><td colspan="4" class="emcore-empty"><strong>ページはまだありません</strong><span>TemplatesからHTMLを登録し、ページを作成してください。</span></td></tr>';
	} else {
		foreach ( $pages as $pg ) {
			$tpl_id = emcore_template_id_for_page( $pg );
			$tpl = $tpl_id ? emcore_get_template( $tpl_id ) : null;
			$status = 'publish' === $pg->post_status ? '公開' : '下書き';
			echo '<tr><td><strong class="emcore-content-title">' . esc_html( $pg->post_title ?: '(無題)' ) . '</strong><code>/' . esc_html( $pg->post_name ) . '/</code></td>';
			echo '<td>' . esc_html( $tpl['name'] ?? '未設定' ) . '</td><td><span class="emcore-status-badge is-' . esc_attr( $pg->post_status ) . '"><i></i>' . esc_html( $status ) . '</span></td>';
			echo '<td class="emcore-actions"><a class="emcore-btn emcore-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=emcore-content-edit&post_id=' . $pg->ID ) ) . '">編集</a><a class="emcore-btn emcore-btn-sm" href="' . esc_url( get_permalink( $pg ) ) . '" target="_blank" rel="noopener noreferrer">表示 ↗</a></td></tr>';
		}
	}
	echo '</tbody></table></div>';
	emcore_shell_end();
}

function emcore_shared_field_default( $html, $key, $type ) {
	if ( ! class_exists( 'DOMDocument' ) ) { return ''; }
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if ( ! $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ) ) { return ''; }
	$nodes = ( new DOMXPath( $dom ) )->query( '//*[@data-em-key="' . sanitize_key( $key ) . '"]' );
	if ( ! $nodes->length ) { return ''; }
	$node = $nodes->item( 0 );
	if ( 'image' === $type ) { return $node->getAttribute( 'src' ); }
	if ( 'url' === $type ) { return $node->getAttribute( 'href' ); }
	if ( 'menu' === $type ) {
		$items = array();
		$xpath = new DOMXPath( $dom );
		$menu_items = $xpath->query( './/*[@data-em-menu-item="1"]', $node );
		foreach ( $menu_items as $item ) {
			$link = strtolower( $item->tagName ) === 'a' ? $item : $xpath->query( './/a[@href]', $item )->item( 0 );
			if ( ! $link instanceof DOMElement ) { continue; }
			$label_node = $xpath->query( './/*[@data-em-menu-label="1"]', $item )->item( 0 );
			if ( ! $label_node && $item->getAttribute( 'data-em-menu-label' ) === '1' ) { $label_node = $item; }
			$items[] = array( 'label' => trim( $label_node ? $label_node->textContent : $link->textContent ), 'url' => $link->getAttribute( 'href' ), 'target' => $link->getAttribute( 'target' ) );
		}
		return wp_json_encode( $items );
	}
	return trim( $node->textContent );
}

function emcore_collection_editor( $field, $value, $value_class = 'emcore-client-val' ) {
	$items = json_decode( (string) $value, true );
	if ( ! is_array( $items ) ) { $items = $field['defaults'] ?? array(); }
	$kind = $field['type'];
	$labels = array( 'list' => 'リスト項目', 'steps' => 'ステップ', 'gallery' => '画像', 'tabs' => 'タブ', 'table' => '行', 'repeater' => 'カード' );
	echo '<div class="emcore-collection-editor" data-kind="' . esc_attr( $kind ) . '" data-key="' . esc_attr( $field['key'] ) . '"><input type="hidden" class="' . esc_attr( $value_class ) . '" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( wp_json_encode( $items ) ) . '"><div class="emcore-collection-list">';
	foreach ( $items as $item ) {
		echo '<div class="emcore-collection-row" draggable="true">';
		if ( 'list' === $kind ) { echo '<input type="text" class="emcore-input" data-collection-field="text" placeholder="項目" value="' . esc_attr( $item['text'] ?? '' ) . '">'; }
		elseif ( 'steps' === $kind ) { echo '<input type="text" class="emcore-input" data-collection-field="title" placeholder="タイトル" value="' . esc_attr( $item['title'] ?? '' ) . '"><textarea class="emcore-textarea" data-collection-field="description" rows="2" placeholder="説明">' . esc_textarea( $item['description'] ?? '' ) . '</textarea>'; }
		elseif ( 'gallery' === $kind ) { echo '<input type="text" class="emcore-input" data-collection-field="image" placeholder="画像URL" value="' . esc_attr( $item['image'] ?? '' ) . '"><input type="text" class="emcore-input" data-collection-field="alt" placeholder="代替テキスト" value="' . esc_attr( $item['alt'] ?? '' ) . '"><input type="text" class="emcore-input" data-collection-field="url" placeholder="リンク先（任意）" value="' . esc_attr( $item['url'] ?? '' ) . '">'; }
		elseif ( 'tabs' === $kind ) { echo '<input type="text" class="emcore-input" data-collection-field="label" placeholder="タブ名" value="' . esc_attr( $item['label'] ?? '' ) . '"><textarea class="emcore-textarea" data-collection-field="content" rows="3" placeholder="内容">' . esc_textarea( $item['content'] ?? '' ) . '</textarea>'; }
		elseif ( 'table' === $kind ) { foreach ( (array) ( $item['cells'] ?? array() ) as $cell ) { echo '<input type="text" class="emcore-input" data-collection-cell="1" placeholder="セル" value="' . esc_attr( $cell ) . '">'; } }
		else { foreach ( (array) $item as $field_key => $field_value ) { echo '<label><span>' . esc_html( $field_key ) . '</span><input type="text" class="emcore-input" data-collection-field="' . esc_attr( $field_key ) . '" value="' . esc_attr( $field_value ) . '"></label>'; } }
		echo '<div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-collection-del">削除</button></div></div>';
	}
	echo '</div><button type="button" class="emcore-btn emcore-collection-add">' . esc_html( ( $labels[ $kind ] ?? '項目' ) . 'を追加' ) . '</button></div>';
}

function emcore_page_shared_content( $location ) {
	$location = in_array( $location, array( 'header', 'footer' ), true ) ? $location : 'header';
	$title = ucfirst( $location );
	$found = array();
	foreach ( emcore_get_templates() as $tpl_id => $tpl ) {
		foreach ( emcore_fields_from_html( $tpl['html'] ?? '' ) as $field ) {
			if ( ( $field['location'] ?? 'body' ) !== $location || ( $field['scope'] ?? 'item' ) !== 'site' ) { continue; }
			if ( ! isset( $found[ $field['key'] ] ) ) {
				$field['default'] = in_array( $field['type'], array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ? wp_json_encode( $field['defaults'] ?? array() ) : emcore_shared_field_default( $tpl['html'], $field['key'], $field['type'] );
				$field['templates'] = array();
				$found[ $field['key'] ] = $field;
			}
			$found[ $field['key'] ]['templates'][] = $tpl['name'] ?? $tpl_id;
		}
	}
	$values = emcore_get_site_shared();
	$font_choices = emcore_get_font_choices();
	emcore_shell_start( $title );
	echo '<div class="emcore-section-heading"><div><span class="emcore-eyebrow">GLOBAL CONTENT / ' . esc_html( strtoupper( $location ) ) . '</span><h2>' . esc_html( $title ) . '</h2><p>全ページで共通して使う' . esc_html( $title ) . 'の内容をまとめて編集します。</p></div></div>';
	if ( ! $found ) {
		echo '<div class="emcore-panel emcore-empty-state"><h3>編集項目はまだ設定されていません</h3><p>Templatesの「編集箇所を指定」で、編集先を「共通' . esc_html( $title ) . '」に設定するとここへ表示されます。</p></div>';
		emcore_shell_end(); return;
	}
	echo '<form id="emcore-shared-content-form" class="emcore-editor-form" data-location="' . esc_attr( $location ) . '"><div class="emcore-editor-main"><section class="emcore-panel">';
	foreach ( $found as $field ) {
		$value = array_key_exists( $field['key'], $values ) ? $values[ $field['key'] ] : $field['default'];
		echo '<div class="emcore-field"><label>' . esc_html( $field['label'] ) . '</label><p class="emcore-help">キー: <code>' . esc_html( $field['key'] ) . '</code> · 使用: ' . esc_html( implode( ', ', array_unique( $field['templates'] ) ) ) . '</p>';
		if ( 'image' === $field['type'] ) {
			echo '<div class="emcore-img-row"><input type="url" class="emcore-input emcore-shared-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '"><button type="button" class="emcore-btn emcore-pick-shared-image">画像を選択</button></div>';
		} elseif ( in_array( $field['type'], array( 'slides', 'accordion' ), true ) ) {
			echo '<textarea class="emcore-textarea emcore-shared-val" data-key="' . esc_attr( $field['key'] ) . '" rows="5">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'menu' === $field['type'] ) {
			$items = json_decode( (string) $value, true );
			if ( ! is_array( $items ) ) { $items = $field['defaults'] ?? array(); }
			echo '<p class="emcore-help">表示名とリンク先を編集し、項目を追加・削除・並び替えできます。同じキーのPC・モバイルメニューへ反映されます。</p>';
			echo '<div class="emcore-menu-editor" data-key="' . esc_attr( $field['key'] ) . '"><input type="hidden" class="emcore-shared-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( wp_json_encode( $items ) ) . '"><div class="emcore-menu-list">';
			foreach ( $items as $item ) {
				echo '<div class="emcore-menu-row" draggable="true"><input type="text" class="emcore-input emcore-menu-label" placeholder="表示名" value="' . esc_attr( $item['label'] ?? '' ) . '"><input type="text" class="emcore-input emcore-menu-url" placeholder="/page/ または https://..." value="' . esc_attr( $item['url'] ?? '' ) . '"><label class="emcore-check"><input type="checkbox" class="emcore-menu-target"' . checked( $item['target'] ?? '', '_blank', false ) . '> 新しいタブ</label><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-menu-del">削除</button></div></div>';
			}
			echo '</div><button type="button" class="emcore-btn emcore-menu-add">メニュー項目を追加</button></div>';
		} elseif ( in_array( $field['type'], array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ) {
			emcore_collection_editor( $field, $value, 'emcore-shared-val' );
		} else {
			echo '<input type="' . esc_attr( 'url' === $field['type'] ? 'url' : 'text' ) . '" class="emcore-input emcore-shared-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '">';
		}
		if ( ! empty( $field['font_editable'] ) ) {
			$font_key = $field['key'] . '__font';
			$current_font = emcore_sanitize_font_family( $values[ $font_key ] ?? '' );
			echo '<div class="emcore-font-control"><label>フォント</label><select class="emcore-select emcore-shared-val" data-key="' . esc_attr( $font_key ) . '"><option value="">元のフォント（HTML設定）</option>';
			foreach ( $font_choices as $family => $name ) {
				echo '<option value="' . esc_attr( $family ) . '"' . selected( $current_font, $family, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select><p class="emcore-help">WordPressの「外観 → フォント」で追加したフォントを使用できます。</p></div>';
		}
		echo '</div>';
	}
	echo '<div class="emcore-editor-actions"><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-save-shared-content">変更を保存</button><span id="emcore-shared-msg" class="emcore-msg"></span></div></section></div></form>';
	emcore_shell_end();
}

function emcore_page_html_rules() {
	if ( ! current_user_can( emcore_design_capability() ) ) { return; }
	$file = EMCORE_PATH . 'docs/EMERGE-MONO-HTML-GUIDE-v1.3.md';
	$spec = is_readable( $file ) ? file_get_contents( $file ) : '';
	$download = wp_nonce_url( admin_url( 'admin-post.php?action=emcore_download_spec' ), 'emcore_download_spec' );
	emcore_shell_start( 'HTMLルール' );
	echo '<section class="emcore-rules-hero"><span class="emcore-eyebrow">HTML GUIDE / 1.2</span><h2>完成HTMLは自由に。<br>動く場所だけ、目印を。</h2><p>文字・画像・リンクに専用コードは不要です。Core上でクリックして編集可能にします。枚数や項目数を増減する動的コンテンツだけ、既存classへ目印classを追加してください。</p><div class="emcore-rules-actions"><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-copy-spec">Markdownをコピー</button><a class="emcore-btn" href="' . esc_url( $download ) . '">.mdをダウンロード</a><span id="emcore-spec-msg" class="emcore-msg"></span></div></section>';
	echo '<div class="emcore-rules-grid">';
	echo '<section class="emcore-panel"><span class="emcore-eyebrow">01 / STATIC</span><h3>文字・画像はそのまま</h3><p>マークモードで対象をクリックし、「編集可能にする」を押すだけです。キーや保存先はCoreが内部設定します。</p><pre>&lt;h2&gt;完成見本の見出し&lt;/h2&gt;\n&lt;img src="sample.webp" alt="見本"&gt;</pre></section>';
	echo '<section class="emcore-panel"><span class="emcore-eyebrow">02 / STRUCTURE</span><h3>基本構造だけ明確に</h3><p><code>header</code>・<code>main</code>・<code>footer</code>を使うと、共通領域とページ本文を正しく判別できます。</p><pre>&lt;header&gt;...&lt;/header&gt;\n&lt;main&gt;...&lt;/main&gt;\n&lt;footer&gt;...&lt;/footer&gt;</pre></section>';
	echo '<section class="emcore-panel"><span class="emcore-eyebrow">03 / DYNAMIC</span><h3>動的要素だけ目印class</h3><p>既存classを消さずに、親と繰り返し項目へCore用classを追加します。</p><pre>&lt;div class="swiper emcore-slider"&gt;\n  &lt;div class="swiper-slide emcore-slider-item"&gt;...&lt;/div&gt;\n&lt;/div&gt;</pre></section>';
	echo '<section class="emcore-panel"><span class="emcore-eyebrow">04 / POST TYPE</span><h3>一覧と個別は完成見本で</h3><p>一覧は複製するカードを、個別は1件分の内容を見本入りで作ります。投稿との対応項目だけ属性で示します。</p><pre>data-em-repeat="posts"\ndata-em-post-field="title"</pre></section>';
	echo '</div>';
	echo '<section class="emcore-panel emcore-rule-checker"><div><span class="emcore-eyebrow">COMPATIBILITY CHECK</span><h3>Core互換性チェック</h3><p>HTMLファイルを選択するか、HTMLを貼り付けてください。チェックを実行するまで診断は行いません。</p></div><div class="emcore-rule-file-row"><label class="emcore-btn" for="emcore-rule-file">HTMLファイルを選択</label><input type="file" id="emcore-rule-file" accept=".html,.htm,text/html" hidden><span id="emcore-rule-file-name" class="emcore-msg" aria-live="polite"></span></div><textarea id="emcore-rule-html" class="emcore-textarea" rows="10" placeholder="ここにHTMLを貼り付け"></textarea><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-check-rules">チェックする</button><div id="emcore-rule-result"></div></section>';
	echo '<details class="emcore-panel emcore-spec-source"><summary>仕様書の全文を表示</summary><textarea id="emcore-spec-source" class="emcore-textarea" rows="28" readonly>' . esc_textarea( $spec ) . '</textarea></details>';
	emcore_shell_end();
}

function emcore_page_forms() {
	if ( ! current_user_can( emcore_design_capability() ) ) { return; }
	$forms = emcore_get_managed_forms();
	emcore_shell_start( 'Forms' );
	echo '<section class="emcore-dashboard-intro"><span class="emcore-eyebrow">CORE FORM ENGINE</span><h2>デザインはそのまま、<br>送信処理をCoreで管理。</h2><p>EMHTMLで宣言されたフォームの送信先、自動返信、完了メッセージを設定します。</p></section>';
	if ( isset( $_GET['updated'] ) ) { echo '<div class="emcore-panel"><p class="emcore-msg">フォーム設定を保存しました。</p></div>'; }
	if ( ! $forms ) {
		echo '<div class="emcore-panel"><h3>管理対象フォームはまだありません</h3><p>HTMLのform要素へ <code>data-em-component="form" data-em-key="contact-form" data-em-label="お問い合わせ"</code> を設定してテンプレートを登録してください。</p></div>';
		emcore_shell_end(); return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="emcore_save_forms">';
	wp_nonce_field( 'emcore_save_forms' );
	foreach ( $forms as $key => $form ) {
		$s = emcore_form_settings( $key );
		echo '<section class="emcore-panel" style="margin-bottom:20px">';
		echo '<span class="emcore-eyebrow">' . esc_html( strtoupper( $key ) ) . '</span><h3>' . esc_html( $form['label'] ) . '</h3><p>テンプレート: ' . esc_html( $form['template'] ) . '</p>';
		echo '<div class="emcore-field"><label>送信先メールアドレス</label><input class="emcore-input" type="email" required name="forms[' . esc_attr( $key ) . '][to]" value="' . esc_attr( $s['to'] ) . '"></div>';
		echo '<div class="emcore-field"><label>管理者向け件名</label><input class="emcore-input" type="text" name="forms[' . esc_attr( $key ) . '][subject]" value="' . esc_attr( $s['subject'] ) . '"></div>';
		echo '<div class="emcore-field"><label>送信完了メッセージ</label><input class="emcore-input" type="text" name="forms[' . esc_attr( $key ) . '][success]" value="' . esc_attr( $s['success'] ) . '"></div>';
		echo '<div class="emcore-field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="forms[' . esc_attr( $key ) . '][auto_reply]" value="1"' . checked( ! empty( $s['auto_reply'] ), true, false ) . '> 入力されたメールアドレスへ自動返信する</label></div>';
		echo '<div class="emcore-field"><label>自動返信の件名</label><input class="emcore-input" type="text" name="forms[' . esc_attr( $key ) . '][reply_subject]" value="' . esc_attr( $s['reply_subject'] ) . '"></div>';
		echo '<div class="emcore-field"><label>自動返信本文</label><textarea class="emcore-textarea" rows="6" name="forms[' . esc_attr( $key ) . '][reply_body]">' . esc_textarea( $s['reply_body'] ) . '</textarea></div>';
		echo '</section>';
	}
	echo '<button class="emcore-btn emcore-btn-primary" type="submit">フォーム設定を保存</button></form>';
	if ( function_exists( 'emcore_system_form_recent_log' ) ) {
		$logs = emcore_system_form_recent_log();
		echo '<section class="emcore-panel" style="margin-top:24px"><span class="emcore-eyebrow">INBOX / LATEST 30</span><h3>システムフォーム送信履歴</h3>';
		if ( ! $logs ) { echo '<p class="emcore-help">送信履歴はまだありません。</p>'; }
		else { echo '<div class="emcore-table-wrap"><table class="emcore-table"><thead><tr><th>日時</th><th>フォーム</th><th>メール</th><th>内容</th></tr></thead><tbody>'; foreach ( $logs as $log ) { $payload = json_decode( $log['payload'], true ); echo '<tr><td>' . esc_html( $log['created_at'] ) . '</td><td><code>' . esc_html( $log['form_key'] ) . '</code></td><td>' . esc_html( $log['email'] ) . '</td><td><details><summary>確認</summary><pre>' . esc_html( implode( "\n\n", is_array( $payload ) ? $payload : array() ) ) . '</pre></details></td></tr>'; } echo '</tbody></table></div>'; }
		echo '</section>';
	}
	emcore_shell_end();
}

function emcore_page_templates() {
	emcore_shell_start( 'Templates' );
	$tpls = emcore_get_templates();
	echo '<div class="emcore-toolbar"><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-new-tpl">+ 新規テンプレート</button></div>';
	echo '<div id="emcore-new-tpl-form" style="display:none;margin-bottom:20px">';
	echo '<section class="emcore-panel" style="margin-bottom:20px">';
	echo '<h3>新規テンプレート</h3>';
	echo '<div class="emcore-field"><label>名前</label><input type="text" id="emcore-tpl-name" class="emcore-input"></div>';
	echo '<div class="emcore-field"><label>種類</label><select id="emcore-tpl-type" class="emcore-select"><option value="page">固定ページ</option><option value="archive">一覧（投稿タイプ）</option><option value="single">個別（投稿タイプ）</option></select></div>';
	echo '<div class="emcore-field"><label>HTMLファイル</label><input type="file" id="emcore-tpl-file" class="emcore-input" accept=".html,.htm,text/html"><p class="emcore-help">.html を選ぶか、下に貼り付けてください。</p></div>';
	echo '<div class="emcore-field"><label>HTML</label><textarea id="emcore-tpl-html" class="emcore-textarea" rows="10"></textarea></div>';
	echo '<button type="button" class="emcore-btn emcore-btn-primary" id="emcore-tpl-save">保存</button> ';
	echo '<button type="button" class="emcore-btn" id="emcore-tpl-cancel">キャンセル</button><div id="emcore-tpl-msg" class="emcore-msg"></div></section>';
	echo '</div>';
	echo '<table class="emcore-table"><thead><tr><th>名前</th><th>種類</th><th>EMHTML</th><th></th></tr></thead><tbody>';
	if ( empty( $tpls ) ) {
		echo '<tr><td colspan="4" class="emcore-empty">まだありません</td></tr>';
	} else {
		foreach ( $tpls as $id => $t ) {
			$type_label = array( 'page' => '固定ページ', 'archive' => '一覧', 'single' => '個別' );
			$compliance = emcore_emhtml_compliance( $t['html'] ?? '' );
			$status_label = $compliance['status'] === 'conformant' ? '準拠' : ( $compliance['status'] === 'partial' ? '一部準拠' : '非準拠' );
			$type_badges = '<span class="emcore-badge">' . esc_html( $type_label[ $t['type'] ] ?? $t['type'] ) . '</span>';
			if ( $t['type'] === 'archive' ) { $type_badges .= ' <span class="emcore-badge">固定ページ</span>'; }
			$slug_hint = ! empty( $t['post_type_hint'] ) ? $t['post_type_hint'] : ( ! empty( $t['import_file'] ) ? sanitize_title( pathinfo( basename( $t['import_file'] ), PATHINFO_FILENAME ) ) : sanitize_title( $t['name'] ) );
			echo '<tr><td><strong>' . esc_html( $t['name'] ) . '</strong></td><td>' . $type_badges . '</td><td><span class="emcore-spec-badge is-' . esc_attr( $compliance['status'] ) . '">' . esc_html( $status_label . ' ' . $compliance['score'] ) . '</span></td><td class="emcore-actions">';
			echo '<a class="emcore-btn emcore-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=emcore-template-edit&id=' . $id ) ) . '">編集箇所を指定</a> ';
			if ( $t['type'] === 'page' ) {
				$linked = get_posts( array( 'post_type' => 'page', 'meta_key' => '_emcore_tpl_id', 'meta_value' => $id, 'posts_per_page' => 1, 'post_status' => 'any' ) );
				if ( $linked ) {
					echo '<a class="emcore-btn emcore-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=emcore-content-edit&post_id=' . $linked[0]->ID ) ) . '">内容を編集</a> ';
				} else {
					echo '<button type="button" class="emcore-btn emcore-btn-sm emcore-create-page" data-id="' . esc_attr( $id ) . '" data-name="' . esc_attr( $t['name'] ) . '" data-type="page" data-slug="' . esc_attr( $slug_hint ) . '">ページを作成</button> ';
				}
			}
			if ( $t['type'] === 'archive' ) {
				$linked = get_posts( array( 'post_type' => 'page', 'meta_key' => '_emcore_tpl_id', 'meta_value' => $id, 'posts_per_page' => 1, 'post_status' => 'any' ) );
				if ( ! $linked ) {
					$linked = get_posts( array( 'post_type' => 'page', 'meta_key' => '_emcore_archive_cpt', 'posts_per_page' => 20, 'post_status' => 'any' ) );
					$hit = array();
					foreach ( $linked as $lp ) {
						$ck = get_post_meta( $lp->ID, '_emcore_archive_cpt', true );
						if ( $ck && emcore_cpt_archive_template_id( $ck ) === $id ) {
							$hit[] = $lp;
						}
					}
					$linked = $hit;
				}
				if ( $linked ) {
					echo '<a class="emcore-btn emcore-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=emcore-content-edit&post_id=' . $linked[0]->ID ) ) . '">内容を編集</a> ';
				} else {
					echo '<button type="button" class="emcore-btn emcore-btn-sm emcore-create-page" data-id="' . esc_attr( $id ) . '" data-name="' . esc_attr( $t['name'] ) . '" data-type="archive" data-slug="' . esc_attr( $slug_hint ) . '">一覧ページを作成</button> ';
				}
			}
			echo '<button type="button" class="emcore-btn emcore-btn-sm emcore-btn-danger emcore-del-tpl" data-id="' . esc_attr( $id ) . '">削除</button></td></tr>';
		}
	}
	echo '</tbody></table>';
	emcore_shell_end();
}

function emcore_page_template_edit() {
	$id  = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';
	$tpl = $id ? emcore_get_template( $id ) : null;
	if ( ! $tpl ) {
		emcore_shell_start( 'Template' );
		echo '<p>見つかりません。</p>';
		emcore_shell_end();
		return;
	}
	$fields = emcore_fields_from_html( $tpl['html'] );
	$revisions   = emcore_get_template_revisions( $id );
	$all_templates = emcore_get_templates();
	$common_regions = array( 'header' => 0, 'nav' => 0, 'footer' => 0 );
	$current_region_signatures = array();
	foreach ( array_keys( $common_regions ) as $region ) {
		$current_region_signatures[ $region ] = emcore_common_region_signature( $tpl['html'], $region );
	}
	$comparable_count = 0;
	foreach ( $all_templates as $compare_id => $compare_tpl ) {
		if ( $compare_id === $id || empty( $compare_tpl['html'] ) ) { continue; }
		$comparable_count++;
		foreach ( array_keys( $common_regions ) as $region ) {
			$signature = emcore_common_region_signature( $compare_tpl['html'], $region );
			if ( $signature && $current_region_signatures[ $region ] && hash_equals( $current_region_signatures[ $region ], $signature ) ) { $common_regions[ $region ]++; }
		}
	}
	emcore_shell_start( '編集箇所の指定: ' . $tpl['name'] );
	echo '<div class="emcore-page-nav">' . emcore_back_link( admin_url( 'admin.php?page=emcore-templates' ), 'Templates' ) . '</div>';
	echo '<div class="emcore-mark-toolbar">';
	echo '<button type="button" class="emcore-btn emcore-btn-auto" id="emcore-auto-detect">動的コンテンツを検出</button> ';
	echo '<button type="button" class="emcore-btn emcore-btn-primary" id="emcore-mark-toggle">マークモード: OFF</button> ';
	echo '<button type="button" class="emcore-btn" id="emcore-mark-save">マークを保存</button> ';
	echo '<button type="button" class="emcore-btn" id="emcore-mark-undo" disabled>元に戻す</button> ';
	echo '<button type="button" class="emcore-btn" id="emcore-mark-redo" disabled>やり直す</button> ';
	echo '<button type="button" class="emcore-btn" id="emcore-mark-reload">再読込</button>';
	echo '<button type="button" class="emcore-btn emcore-view-toggle" id="emcore-view-toggle">通常表示</button>';
	echo '<span class="emcore-bp" id="emcore-bp">';
	echo '<button type="button" class="emcore-bp-btn is-on" data-w="1280" title="デスクトップ 1280px"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 20h8M12 17v3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></button>';
	echo '<button type="button" class="emcore-bp-btn" data-w="900" title="ノートPC 900px"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="1.2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M2 18h20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></button>';
	echo '<button type="button" class="emcore-bp-btn" data-w="768" title="タブレット 768px"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="18.5" r="0.9" fill="currentColor"/></svg></button>';
	echo '<button type="button" class="emcore-bp-btn" data-w="375" title="スマホ 375px"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="18.5" r="0.8" fill="currentColor"/></svg></button>';
	echo '</span>';
	echo '<span id="emcore-mark-status" class="emcore-muted" style="margin-left:12px"></span>';
	echo '</div>';
	echo '<div class="emcore-panel emcore-auto-guide" style="margin:12px 0"><p><strong>文字・画像・リンク</strong>はマークモードをONにして対象をクリックし、「編集可能にする」を押すだけです。スライダー、アコーディオン、メニュー、リスト、ステップ、ギャラリー、タブ、表、カード一覧はHTML内の専用マーカーから自動検出できます。</p>';
	if ( $comparable_count ) {
		echo '<div class="emcore-common-summary"><span>共通部分の比較対象: ' . esc_html( $comparable_count ) . 'テンプレート</span>';
		foreach ( $common_regions as $region => $hits ) {
			echo '<span class="emcore-common-chip"><code>' . esc_html( $region ) . '</code> ' . esc_html( $hits ) . '/' . esc_html( $comparable_count ) . '</span>';
		}
		echo '</div>';
	} else {
		echo '<p class="emcore-help">他のテンプレートを追加すると、header・nav・footerをページ間で比較して共通項目として提案します。</p>';
	}
	echo '</div>';
	echo '<details class="emcore-panel emcore-component-panel" id="emcore-dynamic-components"><summary>動的コンテンツ <span id="emcore-component-count" class="emcore-diagnostic-count">確認中</span></summary><div id="emcore-component-list" class="emcore-component-list"><p class="emcore-muted">プレビューを解析しています…</p></div></details>';
	echo '<div class="emcore-editor-layout">';
	echo '<div class="emcore-preview-wrap"><iframe id="emcore-preview" class="emcore-preview-frame" title="preview"></iframe></div>';
	echo '<div class="emcore-editor-splitter" id="emcore-editor-splitter" role="separator" aria-label="プレビューとレイヤーの幅を変更" tabindex="0"></div>';
	echo '<div class="emcore-layer-panel"><div class="emcore-layer-head"><div><h3>レイヤー</h3><span id="emcore-layer-count" class="emcore-layer-count"></span></div><input type="search" id="emcore-layer-search" class="emcore-input" placeholder="レイヤーを検索"></div>';
	echo '<div class="emcore-layer-filters" id="emcore-layer-filters"><button type="button" class="is-on" data-filter="all">すべて</button><button type="button" data-filter="image">画像</button><button type="button" data-filter="text">文字</button><button type="button" data-filter="link">リンク</button><button type="button" data-filter="marked">編集可能</button></div>';
	echo '<div class="emcore-layer-tools"><label class="emcore-layer-toggle"><input type="checkbox" id="emcore-layer-all-templates"> 他のページも表示</label><select id="emcore-layer-key-filter" class="emcore-select"><option value="">すべてのキー</option></select></div>';
	echo '<div class="emcore-layer-tree" id="emcore-layer-list"><p class="emcore-muted" style="padding:12px">読み込み中…</p></div><div class="emcore-layer-popover" id="emcore-layer-popover" hidden></div></div>';
	echo '</div>';
	echo '<div class="emcore-panel" style="margin-top:16px"><h3>クライアントに出る項目</h3><ul class="emcore-list">';
	if ( empty( $fields ) ) {
		echo '<li class="emcore-muted">まだありません</li>';
	} else {
		foreach ( $fields as $f ) {
			echo '<li>' . esc_html( $f['label'] ) . ' <code>' . esc_html( $f['key'] ) . '</code></li>';
		}
	}
	echo '</ul></div>';
	if ( $revisions ) {
		echo '<details class="emcore-panel" style="margin-top:16px"><summary>変更履歴（直近10件）</summary><div class="emcore-history">';
		foreach ( $revisions as $index => $revision ) {
			echo '<div class="emcore-history-row"><span>' . esc_html( $revision['time'] ) . '</span><button type="button" class="emcore-btn emcore-btn-sm emcore-restore-tpl" data-id="' . esc_attr( $id ) . '" data-index="' . esc_attr( $index ) . '">この状態に戻す</button></div>';
		}
		echo '</div></details>';
	}
	echo '<details class="emcore-panel" style="margin-top:16px"><summary>HTMLソース（上級）</summary>';
	echo '<div class="emcore-field" style="margin-top:12px"><label>ファイルで差し替え</label><input type="file" id="emcore-edit-file" class="emcore-input" accept=".html,.htm,text/html"></div>';
	echo '<textarea id="emcore-edit-html" class="emcore-textarea" rows="12">' . esc_textarea( $tpl['html'] ) . '</textarea>';
	echo '<div style="margin-top:12px"><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-update-html" data-id="' . esc_attr( $id ) . '" data-name="' . esc_attr( $tpl['name'] ) . '" data-type="' . esc_attr( $tpl['type'] ) . '">HTMLを更新</button><div id="emcore-html-msg" class="emcore-msg"></div></div></details>';
	$preview_tpl = $tpl; $preview_tpl['id'] = $id;
	$other_templates = array(); foreach ( emcore_get_templates() as $other ) { if ( ($other['id']??'') !== $id ) $other_templates[] = array('id'=>$other['id']??'','name'=>$other['name']??'無題','html'=>$other['html']??''); }
	echo '<script>window.emcoreTplEdit=' . wp_json_encode( array( 'id' => $id, 'name' => $tpl['name'], 'type' => $tpl['type'], 'html' => $tpl['html'], 'previewHtml' => emcore_template_preview_html( $preview_tpl ), 'commonRegions' => $common_regions, 'comparableCount' => $comparable_count, 'otherTemplates' => $other_templates ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';</script>';
	emcore_shell_end();
}

function emcore_page_cpts() {
	emcore_shell_start( 'Post Types' );
	$cpts = emcore_get_cpts();
	$tpls = emcore_get_templates();
	echo '<div class="emcore-toolbar"><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-new-cpt">+ 投稿タイプを追加</button></div>';
	echo '<div class="emcore-panel" id="emcore-new-cpt-form" style="display:none;margin-bottom:20px">';
	echo '<div class="emcore-field"><label>名前（例: お知らせ）</label><input type="text" id="emcore-cpt-label" class="emcore-input" placeholder="お知らせ"></div>';
	echo '<div class="emcore-field"><label>単数名（例: お知らせ記事）</label><input type="text" id="emcore-cpt-singular" class="emcore-input" placeholder="お知らせ記事"></div>';
	echo '<div class="emcore-field"><label>親スラッグ</label><input type="text" id="emcore-cpt-slug" class="emcore-input" placeholder="liver"><p class="emcore-help">一覧を /liver/、個別ページを /liver/{投稿スラッグ}/ の形式にします。</p></div>';
	echo '<div class="emcore-field"><label>一覧ページの方式</label><select id="emcore-cpt-listing-mode" class="emcore-select"><option value="fixed_page">固定ページとして作成</option><option value="archive">自動一覧（投稿タイプアーカイブ）</option></select></div>';
	echo '<div class="emcore-field"><label><input type="checkbox" id="emcore-cpt-has-cat"> カテゴリを使う</label></div>';
	echo '<input type="hidden" id="emcore-cpt-icon" value="dashicons-groups">';
	echo '<button type="button" class="emcore-btn emcore-btn-primary" id="emcore-cpt-save">作成</button> ';
	echo '<button type="button" class="emcore-btn" id="emcore-cpt-cancel">キャンセル</button><div id="emcore-cpt-msg" class="emcore-msg"></div></div>';
	echo '<table class="emcore-table"><thead><tr><th>名前</th><th>URL構造</th><th>一覧テンプレ</th><th>個別テンプレ</th><th>一覧フィルター</th><th></th></tr></thead><tbody>';
	if ( empty( $cpts ) ) {
		echo '<tr><td colspan="6" class="emcore-empty">まだありません</td></tr>';
	} else {
		foreach ( $cpts as $key => $c ) {
			echo '<tr><td><strong>' . esc_html( $c['label'] ) . '</strong><br><code>' . esc_html( $key ) . '</code></td>';
			$parent_slug = emcore_cpt_parent_slug( $c, $key );
			$listing_mode = emcore_cpt_listing_mode( $c );
			echo '<td><label class="screen-reader-text" for="emcore-parent-' . esc_attr( $key ) . '">親スラッグ</label><input id="emcore-parent-' . esc_attr( $key ) . '" type="text" class="emcore-input emcore-cpt-parent-slug" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( $parent_slug ) . '">';
			echo '<select class="emcore-select emcore-cpt-listing-mode" data-key="' . esc_attr( $key ) . '"><option value="fixed_page"' . selected( $listing_mode, 'fixed_page', false ) . '>固定ページ</option><option value="archive"' . selected( $listing_mode, 'archive', false ) . '>自動一覧</option></select>';
			echo '<p class="emcore-help">/' . esc_html( $parent_slug ) . '/ ・ /' . esc_html( $parent_slug ) . '/{投稿}/</p></td>';
			foreach ( array( 'archive', 'single' ) as $which ) {
				echo '<td><select class="emcore-select emcore-link-tpl" data-key="' . esc_attr( $key ) . '" data-which="' . esc_attr( $which ) . '">';
				echo '<option value="">—</option>';
				foreach ( $tpls as $tid => $t ) {
					if ( $t['type'] !== $which ) {
						continue;
					}
					$sel = ( ! empty( $c[ $which . '_tpl' ] ) && $c[ $which . '_tpl' ] === $tid ) ? ' selected' : '';
					echo '<option value="' . esc_attr( $tid ) . '"' . $sel . '>' . esc_html( $t['name'] ) . '</option>';
				}
				echo '</select></td>';
			}
			$filter_on = ! isset( $c['filter_enabled'] ) || ! empty( $c['filter_enabled'] );
			echo '<td><label class="emcore-switch"><input type="checkbox" class="emcore-cpt-filter" data-key="' . esc_attr( $key ) . '"' . checked( $filter_on, true, false ) . ( empty( $c['has_cat'] ) ? ' disabled' : '' ) . '><span></span><b>' . ( empty( $c['has_cat'] ) ? 'カテゴリ未使用' : ( $filter_on ? 'ON' : 'OFF' ) ) . '</b></label></td>';
			echo '<td class="emcore-actions">';
			echo '<a class="emcore-btn emcore-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=emcore-cpt-list&cpt=' . $key ) ) . '">投稿一覧</a> ';
			if ( 'fixed_page' === $listing_mode ) { echo '<button type="button" class="emcore-btn emcore-btn-sm emcore-create-archive-page" data-key="' . esc_attr( $key ) . '">一覧ページを作成</button> '; }
			echo '<button type="button" class="emcore-btn emcore-btn-sm emcore-btn-danger emcore-del-cpt" data-key="' . esc_attr( $key ) . '">削除</button></td></tr>';
		}
	}
	echo '</tbody></table>';
	emcore_shell_end();
}

function emcore_page_cpt_list() {
	$key = isset( $_GET['cpt'] ) ? sanitize_key( wp_unslash( $_GET['cpt'] ) ) : '';
	$cpts = emcore_get_cpts();
	if ( ! isset( $cpts[ $key ] ) ) {
		emcore_shell_start( '投稿' );
		echo '<p>見つかりません。</p>';
		emcore_shell_end();
		return;
	}
	$cpt = $cpts[ $key ];
	emcore_shell_start( $cpt['label'] );
	$posts = get_posts( array( 'post_type' => $key, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 100 ) );
	echo '<div class="emcore-toolbar emcore-list-toolbar"><div><span class="emcore-eyebrow">CONTENT / ' . esc_html( strtoupper( $key ) ) . '</span><p>' . esc_html( count( $posts ) ) . '件のコンテンツ</p></div><button type="button" class="emcore-btn emcore-btn-primary" id="emcore-new-post" data-cpt="' . esc_attr( $key ) . '">+ 新規追加</button></div>';
	echo '<div class="emcore-table-wrap"><table class="emcore-table emcore-content-table"><thead><tr><th>タイトル</th><th>公開状態</th><th>更新日</th><th>操作</th></tr></thead><tbody>';
	if ( empty( $posts ) ) {
		echo '<tr><td colspan="4" class="emcore-empty"><strong>コンテンツはまだありません</strong><span>「新規追加」から最初の1件を作成できます。</span></td></tr>';
	} else {
		foreach ( $posts as $p ) {
			$edit_url = admin_url( 'admin.php?page=emcore-cpt-edit&cpt=' . $key . '&id=' . $p->ID );
			$status_label = $p->post_status === 'publish' ? '公開' : '下書き';
			echo '<tr data-post-id="' . esc_attr( $p->ID ) . '"><td><strong class="emcore-content-title">' . esc_html( $p->post_title ? $p->post_title : '(無題)' ) . '</strong><code>#' . esc_html( $p->ID ) . '</code></td>';
			echo '<td><span class="emcore-status-badge is-' . esc_attr( $p->post_status ) . '"><i></i>' . esc_html( $status_label ) . '</span></td>';
			echo '<td><time datetime="' . esc_attr( get_post_modified_time( 'c', true, $p ) ) . '">' . esc_html( get_post_modified_time( 'Y.m.d H:i', false, $p ) ) . '</time></td>';
			echo '<td class="emcore-actions"><a class="emcore-btn emcore-btn-sm" href="' . esc_url( $edit_url ) . '">編集</a><a class="emcore-btn emcore-btn-sm" href="' . esc_url( get_permalink( $p ) ) . '" target="_blank" rel="noopener noreferrer">表示 ↗</a><button type="button" class="emcore-btn emcore-btn-sm emcore-btn-danger emcore-delete-post" data-id="' . esc_attr( $p->ID ) . '" data-title="' . esc_attr( $p->post_title ? $p->post_title : '無題' ) . '">削除</button></td></tr>';
		}
	}
	echo '</tbody></table></div>';
	emcore_shell_end();
}

function emcore_render_client_editor( $post, $tpl, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'cpt'       => $post->post_type,
			'show_thumb'=> ( $post->post_type !== 'page' ),
			'force_thumb'=> false,
			'back_url'  => admin_url( 'admin.php?page=emcore-pages' ),
			'back_label'=> '← 戻る',
			'title'     => '編集',
		)
	);
	$tpl_id = '';
	if ( $post->post_type === 'page' ) {
		$tpl_id = get_post_meta( $post->ID, '_emcore_tpl_id', true );
	}
	$fields = $tpl ? emcore_fields_from_html( $tpl['html'] ) : array();
	emcore_shell_start( $args['title'] );
	echo '<div class="emcore-page-nav emcore-editor-page-nav">' . emcore_back_link( $args['back_url'], $args['back_label'] );
	if ( $tpl ) {
		echo '<div class="emcore-editor-page-actions">';
		echo '<div id="emcore-client-msg" class="emcore-msg"></div>';
		echo '<a class="emcore-btn emcore-view-link" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener noreferrer">サイトで見る ↗</a>';
		echo '<button type="button" class="emcore-btn emcore-btn-primary" id="emcore-save-client" data-cpt="' . esc_attr( $args['cpt'] ) . '" data-id="' . esc_attr( $post->ID ) . '">更新する</button>';
		echo '</div>';
	}
	echo '</div>';
	if ( ! $tpl ) {
		echo '<div class="emcore-panel"><p>テンプレートが未設定です。</p></div>';
		emcore_shell_end();
		return;
	}
	if ( empty( $fields ) ) {
		$edit = $tpl_id ? $tpl_id : '';
		echo '<div class="emcore-panel"><p>編集箇所がまだありません。制作側が「編集箇所を指定」でマークしてください。</p></div>';
	}
	echo '<form id="emcore-client-form" class="emcore-panel emcore-journal-editor">';
	$has_thumb = false;
	$has_title = false;
	foreach ( $fields as $field ) {
		if ( $field['store'] === 'thumbnail' || $field['key'] === 'thumbnail' ) {
			$has_thumb = true;
		}
		if ( $field['store'] === 'title' ) {
			$has_title = true;
		}
	}
	echo '<div class="emcore-field emcore-title-field"><label>タイトル</label><input type="text" class="emcore-input emcore-client-val" data-key="title" value="' . esc_attr( $post->post_title ) . '">';
	echo '<label class="emcore-slug-label">URL（スラッグ）</label><div class="emcore-slug-field"><span>' . esc_html( trailingslashit( home_url() ) ) . '</span><input type="text" class="emcore-input emcore-client-val" data-key="post_name" value="' . esc_attr( $post->post_name ) . '"><span>/</span></div><p class="emcore-help">半角英数字・ハイフン・アンダーバーが使用できます。</p></div>';
	if ( $args['show_thumb'] && ( $args['force_thumb'] || ! $has_thumb ) ) {
		$tid  = (int) get_post_thumbnail_id( $post );
		$turl = $tid ? wp_get_attachment_url( $tid ) : '';
		$is_page = $post->post_type === 'page';
		echo '<div class="emcore-field emcore-side-card emcore-featured-card"><div class="emcore-side-title">' . ( $is_page ? 'アイキャッチ画像' : '一覧用の画像（アイキャッチ）' ) . '</div><p class="emcore-help">' . ( $is_page ? 'SNSシェアやページ一覧などで使用できます。本文内の画像とは別です。' : '一覧ページのカードに使います。個別ページの画像とは別です。' ) . '</p>';
		echo '<div class="emcore-img-row">';
		echo '<input type="hidden" class="emcore-client-val" data-key="thumbnail" value="' . esc_attr( $turl ) . '">';
		echo '<input type="hidden" class="emcore-client-id" data-key="thumbnail" value="' . esc_attr( $tid ) . '">';
		echo '<button type="button" class="emcore-img-preview emcore-pick-client-image" data-key="thumbnail" aria-label="アイキャッチ画像を選択">';
		echo $turl ? '<img src="' . esc_url( $turl ) . '" alt=""><span class="emcore-thumb-overlay">変更</span>' : '<span class="emcore-thumb-placeholder"><span class="dashicons dashicons-format-image"></span><b>クリックして画像を選択</b></span>';
		echo '</button><div class="emcore-thumb-actions"><button type="button" class="emcore-btn emcore-pick-client-image" data-key="thumbnail">' . ( $turl ? '画像を変更' : '画像を選択' ) . '</button><button type="button" class="emcore-btn emcore-remove-client-image" data-key="thumbnail"' . ( $turl ? '' : ' hidden' ) . '>画像を削除</button></div></div></div>';
	}
	foreach ( $fields as $field ) {
		$scope = isset( $field['scope'] ) ? $field['scope'] : 'item';
		if ( $scope !== 'item' ) {
			continue;
		}
		if ( $field['store'] === 'title' ) { continue; }
		if ( $post->post_type === 'page' && $args['force_thumb'] && ( $field['store'] === 'thumbnail' || $field['key'] === 'thumbnail' ) ) { continue; }
		$val = emcore_get_post_field_value( $post, $field );
		$iid = emcore_get_post_field_image_id( $post, $field );
		echo '<div class="emcore-field"><label>' . esc_html( $field['label'] ) . '</label>';
		if ( $field['type'] === 'slides' ) {
			$urls = json_decode( (string) $val, true );
			if ( ! is_array( $urls ) ) { $urls = array(); }
			echo '<p class="emcore-help">画像を追加・削除できます。1枚目のスライドデザインが複製されます。</p>';
			echo '<div class="emcore-slides" data-key="' . esc_attr( $field['key'] ) . '">';
			echo '<input type="hidden" class="emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( is_string( $val ) ? $val : wp_json_encode( $urls ) ) . '">';
			echo '<div class="emcore-slides-list">';
			foreach ( $urls as $u ) {
				if ( ! $u ) { continue; }
				echo '<div class="emcore-slide-item" draggable="true"><img src="' . esc_url( $u ) . '" alt=""><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-slide-replace">変更</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-slide-del">削除</button></div></div>';
			}
			echo '</div><button type="button" class="emcore-btn emcore-slide-add" data-key="' . esc_attr( $field['key'] ) . '">画像を追加</button></div>';
		} elseif ( $field['type'] === 'accordion' ) {
			$items = json_decode( (string) $val, true );
			if ( ! is_array( $items ) ) { $items = array(); }
			echo '<p class="emcore-help">質問と回答をセットで追加・削除・並び替えできます。元のHTMLのデザインと開閉動作を使用します。</p>';
			echo '<div class="emcore-accordion-editor" data-key="' . esc_attr( $field['key'] ) . '"><input type="hidden" class="emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( wp_json_encode( $items ) ) . '"><div class="emcore-accordion-list">';
			foreach ( $items as $item ) {
				echo '<div class="emcore-accordion-row" draggable="true"><input type="text" class="emcore-input emcore-accordion-question" placeholder="質問" value="' . esc_attr( $item['question'] ?? '' ) . '"><textarea class="emcore-textarea emcore-accordion-answer" rows="3" placeholder="回答">' . esc_textarea( $item['answer'] ?? '' ) . '</textarea><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-accordion-del">削除</button></div></div>';
			}
			echo '</div><button type="button" class="emcore-btn emcore-accordion-add">FAQを追加</button></div>';
		} elseif ( $field['type'] === 'menu' ) {
			$items = json_decode( (string) $val, true );
			if ( ! is_array( $items ) ) { $items = $field['defaults'] ?? array(); }
			echo '<p class="emcore-help">表示名とリンク先を編集し、項目を追加・削除・並び替えできます。</p>';
			echo '<div class="emcore-menu-editor" data-key="' . esc_attr( $field['key'] ) . '"><input type="hidden" class="emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( wp_json_encode( $items ) ) . '"><div class="emcore-menu-list">';
			foreach ( $items as $item ) {
				echo '<div class="emcore-menu-row" draggable="true"><input type="text" class="emcore-input emcore-menu-label" placeholder="表示名" value="' . esc_attr( $item['label'] ?? '' ) . '"><input type="text" class="emcore-input emcore-menu-url" placeholder="/page/ または https://..." value="' . esc_attr( $item['url'] ?? '' ) . '"><label class="emcore-check"><input type="checkbox" class="emcore-menu-target"' . checked( $item['target'] ?? '', '_blank', false ) . '> 新しいタブ</label><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-menu-del">削除</button></div></div>';
			}
			echo '</div><button type="button" class="emcore-btn emcore-menu-add">メニュー項目を追加</button></div>';
		} elseif ( in_array( $field['type'], array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ) {
			emcore_collection_editor( $field, $val );
		} elseif ( $field['type'] === 'image' ) {
			$is_responsive = ! empty( $field['responsive'] );
			$variants = $is_responsive ? emcore_parse_image_variants( $val ) : array();
			if ( $is_responsive && ( empty( $variants['sources'] ) || ! is_array( $variants['sources'] ) ) ) {
				$variants = isset( $field['variants'] ) ? $field['variants'] : array( 'default' => $val, 'sources' => array() );
			}
			$display_url = $is_responsive ? ( $variants['default'] ?? '' ) : $val;
			echo '<div class="emcore-img-row">';
			echo '<input type="hidden" class="emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $is_responsive ? wp_json_encode( $variants ) : $val ) . '">';
			echo '<input type="hidden" class="emcore-client-id" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $iid ) . '">';
			echo '<div class="emcore-img-preview" data-key="' . esc_attr( $field['key'] ) . '">';
			echo $display_url ? '<img src="' . esc_url( $display_url ) . '" alt="">' : '<span class="emcore-muted">未設定</span>';
			echo '</div><div class="emcore-thumb-actions"><button type="button" class="emcore-btn emcore-pick-client-image" data-key="' . esc_attr( $field['key'] ) . '" data-responsive="' . esc_attr( $is_responsive ? '1' : '0' ) . '">' . ( $display_url ? '画像を変更' : '画像を選択' ) . '</button><button type="button" class="emcore-btn emcore-remove-client-image" data-key="' . esc_attr( $field['key'] ) . '" data-responsive="' . esc_attr( $is_responsive ? '1' : '0' ) . '"' . ( $display_url ? '' : ' hidden' ) . '>画像を削除</button></div></div>';
			if ( $is_responsive ) {
				echo '<div class="emcore-responsive-images" data-key="' . esc_attr( $field['key'] ) . '"><p class="emcore-help">画面幅ごとの画像URL</p>';
				echo '<label>標準</label><input type="url" class="emcore-input emcore-responsive-src" data-media="default" value="' . esc_attr( $variants['default'] ?? '' ) . '">';
				foreach ( (array) ( $variants['sources'] ?? array() ) as $media => $src ) {
					echo '<label>' . esc_html( $media ) . '</label><input type="url" class="emcore-input emcore-responsive-src" data-media="' . esc_attr( $media ) . '" value="' . esc_attr( $src ) . '">';
				}
				echo '</div>';
			}
		} elseif ( $field['type'] === 'textarea' || $field['store'] === 'content' ) {
			echo '<textarea class="emcore-textarea emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '" rows="6">' . esc_textarea( $val ) . '</textarea>';
		} else {
			$type = $field['type'] === 'url' ? 'url' : 'text';
			echo '<input type="' . esc_attr( $type ) . '" class="emcore-input emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $val ) . '">';
		}
		if ( ! empty( $field['font_editable'] ) ) {
			$current_font = emcore_sanitize_font_family( get_post_meta( $post->ID, '_emcore_' . $field['key'] . '_font', true ) );
			echo '<div class="emcore-font-control"><label>フォント</label><select class="emcore-select emcore-client-val" data-key="' . esc_attr( $field['key'] ) . '__font"><option value="">元のフォント（HTML設定）</option>';
			foreach ( emcore_get_font_choices() as $family => $name ) {
				echo '<option value="' . esc_attr( $family ) . '"' . selected( $current_font, $family, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select><p class="emcore-help">フォントはWordPressのフォント管理から追加できます。</p></div>';
		}
		echo '</div>';
	}
	$tax = $post->post_type . '_cat';
	if ( $post->post_type !== 'page' && taxonomy_exists( $tax ) ) {
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		$have  = wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			echo '<div class="emcore-field emcore-side-card"><label>カテゴリ</label><div class="emcore-cat">';
			foreach ( $terms as $term ) {
				$on = in_array( $term->term_id, $have, true ) ? ' checked' : '';
				echo '<label style="display:inline-flex;gap:6px;margin-right:12px"><input type="checkbox" class="emcore-cat" value="' . esc_attr( $term->term_id ) . '"' . $on . '> ' . esc_html( $term->name ) . '</label>';
			}
			echo '</div></div>';
		}
	}
	do_action( 'emcore_client_editor_after_fields', $post, $tpl, $args );
	echo '<div class="emcore-field emcore-side-card emcore-status-card"><label>公開設定</label><select id="emcore-client-status" class="emcore-select">';
	foreach ( array( 'publish' => '公開', 'draft' => '下書き' ) as $st => $lb ) {
		echo '<option value="' . esc_attr( $st ) . '"' . selected( $post->post_status, $st, false ) . '>' . esc_html( $lb ) . '</option>';
	}
	echo '</select></div></form>';
	echo '<details class="emcore-panel" style="margin-top:20px"><summary>プレビュー（確認用）</summary>';
	echo '<div class="emcore-preview-wrap" style="margin-top:12px"><iframe class="emcore-preview-frame" src="' . esc_url( get_permalink( $post ) ) . '" title="preview"></iframe></div></details>';
	emcore_shell_end();
}

function emcore_page_cpt_edit() {
	$key  = isset( $_GET['cpt'] ) ? sanitize_key( wp_unslash( $_GET['cpt'] ) ) : '';
	$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$cpts = emcore_get_cpts();
	$post = $id ? get_post( $id ) : null;
	if ( ! isset( $cpts[ $key ] ) || ! $post || $post->post_type !== $key ) {
		emcore_shell_start( '編集' );
		echo '<p>見つかりません。一覧から開き直してください。</p>';
		emcore_shell_end();
		return;
	}
	$tpl_id = emcore_cpt_single_template_id( $key );
	$tpl    = $tpl_id ? emcore_get_template( $tpl_id ) : null;
	emcore_render_client_editor(
		$post,
		$tpl,
		array(
			'cpt'        => $key,
			'show_thumb' => true,
			'back_url'   => admin_url( 'admin.php?page=emcore-cpt-list&cpt=' . $key ),
			'back_label' => '← ' . $cpts[ $key ]['label'] . '一覧',
			'title'      => '編集: ' . $cpts[ $key ]['label'],
		)
	);
}

function emcore_page_content_edit() {
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
	$tpl_id  = isset( $_GET['tpl'] ) ? sanitize_key( wp_unslash( $_GET['tpl'] ) ) : '';
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post && $tpl_id ) {
		$found = get_posts( array( 'post_type' => 'page', 'meta_key' => '_emcore_tpl_id', 'meta_value' => $tpl_id, 'posts_per_page' => 1, 'post_status' => 'any' ) );
		$post  = $found ? $found[0] : null;
	}
	if ( $post ) {
		$tpl_id = emcore_template_id_for_page( $post );
	}
	$tpl = $tpl_id ? emcore_get_template( $tpl_id ) : null;
	if ( ! $post || ! $tpl ) {
		emcore_shell_start( '固定ページを編集' );
		echo '<div class="emcore-page-nav">' . emcore_back_link( admin_url( 'admin.php?page=emcore-templates' ), 'Templates' ) . '</div>';
		echo '<div class="emcore-panel"><p>先に Templates で「ページを作成」してください。</p></div>';
		emcore_shell_end();
		return;
	}
	emcore_render_client_editor(
		$post,
		$tpl,
		array(
			'cpt'        => 'page',
			'show_thumb' => true,
			'force_thumb'=> true,
			'back_url'   => admin_url( 'admin.php?page=emcore-pages' ),
			'back_label' => '← ページ管理',
			'title'      => '編集: ' . ( $post->post_title ? $post->post_title : '固定ページ' ),
		)
	);
}
