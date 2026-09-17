=== Emerge Mono - Core ===
Contributors: emergemono
Stable tag: 1.28.4
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv2 or later

完成HTMLをWordPressで編集可能にするコアエンジン。

== Changelog ==

= 1.28.4 =
* WordPress Plugin Check が検出した出力処理を精査し、安全なHTML生成箇所を明示しました。
* テンプレート表示と管理画面の既存動作を維持したまま、公式チェックへの適合性を改善しました。

= 1.28.3 =
* Added complete GPL license metadata to the plugin header.
* Removed an unused translation directory declaration.
* Excluded development-only test files from release packages and Plugin Check scans.

= 1.28.2 =
* 投稿タイプに親スラッグと一覧ページ方式を追加しました。
* 固定ページ `/parent/` と個別投稿 `/parent/{slug}/` を安全に共存できるようにしました。

= 1.28.1 =
* Enforced pairing-code expiry and verified uploaded image contents against extension and MIME type.

= 1.28.0 =
* Added HTML Guide v1.3 rules for portable internal links, uploaded assets, responsive sources, and dynamic JavaScript.
* Added non-destructive preflight warnings for unresolved internal links, environment-specific paths, and asset references.
* Added shared slug collision checks for pages, Core post types, system pages, and Codex page creation.

= 1.27.4 =
* Added gzip-compressed Base64 transport for complete HTML on strict shared-hosting WAFs.
* Kept Base64 and ordinary HTML transport as backward-compatible fallbacks.

= 1.27.3 =
* Added Base64 transport for complete HTML sent by Emerge Mono for Codex.
* Prevented shared-hosting WAF false positives while preserving all decoded HTML validation and size limits.
* Kept backward compatibility with clients that send ordinary HTML parameters.

= 1.27.2 =
* Fixed low-contrast headings on the Codex connection management screen.
* Clarified previously saved identifiers as connection labels.
* Unified the revoke action with the Emerge Mono button style.

= 1.27.1 =
* Redesigned the Codex connection approval and completion screens in the Emerge Mono monochrome glass style.
* Clarified Codex as the request source and the current WordPress site as the connection destination.
* Moved the device label to secondary information to prevent it being mistaken for the site name.

= 1.27.0 =
* Added a self-hosted MCP endpoint with no Emerge Mono cloud dependency.
* Added browser approval, PKCE-style one-time pairing, per-device tokens and revocation.
* Added support for independently connecting multiple WordPress sites.

= 1.26.0 =
* Added the authenticated Emerge Mono for Codex bridge API.
* Added staged HTML proposals, validation, approval, media upload, and page creation.
* Removed the experimental automatic structure editor.

= 1.25.1 =
* Moved light structure editing from Manage page editing to its own Customize screen.
* Fixed the editor layout conflict with the SEO side panel.

= 1.25.0 =
* Added an experimental marker-free light structure editor for page repeat items.
* Supports text editing, duplication, deletion, and reordering while preserving the source design.

= 1.24.6 =
* Prevented dbDelta from altering the auto-increment form-log ID on every plugin update.
* Existing form logs and table structure are preserved without destructive migration.
* Escaped SQL LIKE table names correctly for WordPress table prefixes containing underscores.

= 1.24.5 =
* Rebuilt the Core contact UI from the Journal contact design language.
* Added a responsive two-column form, editorial heading, refined fields, consent styling and visible outline submit button.
* Added site-overridable CSS variables and automatic Privacy Policy linking.
* Added visible sent and error feedback to the generated contact page.

= 1.24.4 =
* Excludes page-specific hero content when a system page inherits an HTML shell.
* Without an explicit content slot, replaces everything between the body header and footer.
* Preserves the original main element attributes for layout compatibility.
* Simplified system pages to Contact and 404; designed legal and informational pages now use normal HTML templates.
* Keeps previously created pages and only removes their retired system-page association.

= 1.24.3 =
* Unified system-page save feedback with the compact status used by content editors.
* Combined save and creation feedback into one contextual message.
* Opens the system page that was just created instead of unrelated page panels.

= 1.24.2 =
* Moved form field controls into their related system page settings.
* Separated field definitions for Contact, Document Request and Booking forms.
* Fixed low-contrast form field headings in the monochrome admin UI.

= 1.24.1 =
* Added one-click creation of real WordPress pages from each routable system page.
* Uses the entered title and slug and links the created page to its Core system content.
* Added Edit Page and View Page actions after creation.

= 1.24.0 =
* Added Core system pages rendered inside an imported HTML page shell.
* Added Contact, Privacy Policy, Terms, 404, Search, Sent, Maintenance, Login, Document Request, Booking, FAQ, Cookie Policy and Commercial Law page types.
* Added automatic content-slot detection with data-em-slot="content", main and role="main" fallbacks.
* Added a configurable standalone form, mail routing, consent validation, rate limiting and submission logging.

= 1.23.2 =
* Replaced the blue and purple Spatial UI decoration with a monochrome palette.
* Kept the existing layout, behavior and semantic warning colors unchanged.

= 1.23.1 =
* Changed the sidebar to a full-height rail.
* Replaced the EM placeholder and brand text with the supplied Emerge Mono logo.
* Removed duplicate EMERGE MONO text from the page heading.

= 1.23.0 =
* Redesigned the Core admin shell with the Spatial UI direction.
* Added the Customize / Manage workspace switch to the top bar.
* Added workspace-aware sidebar groups and dashboard content.
* Removed the user initials badge from the top bar.
* Kept all operations mouse and touch based without global keyboard shortcuts.

= 1.22.1 =
* 同じ種類・キー・スコープを持つ動的コンテンツを一覧上で1件へ統合。
* PC用とモバイル用の共通メニューを「表示先2か所」として表示。
* 共通メニューの追加・削除・並べ替えをすべての表示先へ同期する既存描画を維持。

= 1.21.0 =
* Added list, steps, gallery, tabs, table and structured repeater components.
* Added item editing, insertion, deletion, ordering and public rendering for the new components.
* Added automatic step numbering and accessible tab relationship regeneration.
* Expanded HTML Guide v1.2 with standard dynamic component markers.

= 1.20.0 =
* Added repeatable menu components with editable labels, URLs, targets and ordering.
* Added automatic detection for emcore-menu and emcore-menu-item markers.
* Added synchronized rendering for desktop and mobile menus that share a key.
* Updated HTML Guide v1.2 with menu authoring rules.

= 1.19.7 =
* コンテンツ編集画面の「戻る」「サイトで見る」「更新する」を上部の同じ操作列へ統合。
* 狭い画面では操作ボタンを折り返し、横にはみ出さないよう改善。

= 1.19.6 =
* 簡易登録画面で、画像・文字・リンク・動的コンテンツの表示名を直接設定・変更できるよう改善。

= 1.19.5 =
* 簡易登録でもpicture要素のブレークポイント別画像を一括で引き継ぐよう修正。
* レスポンシブ画像の初期値が「未設定」になる問題を修正。
* 旧バージョンでimg要素へ登録された設定を、picture要素へ再登録できるよう改善。
* 文字・リンクの簡易登録でフォント変更を自動的に有効化。
* 詳細設定でも文字・リンクにWordPress標準フォントライブラリのフォントを設定可能。

= 1.19.4 =
* スライダー画像の増減時、2枚目以降へactive状態が複製されて全画像が一時的に並ぶ問題を修正。

= 1.19.3 =
* Added an Emerge Mono confirmation modal before leaving the template marker editor with unsaved changes.
* Added native browser warnings for reload, browser back and tab close operations.
* Avoids false warnings after a successful save.

= 1.19.2 =
* Run HTML diagnostics only from the explicit Core compatibility checker.
* Added local HTML file loading to the compatibility checker.
* Display compatibility checks and diagnostic notices together.
* Fixed unreadable specification source text in the dark admin interface.

= 1.19.1 =
* Unified the bundled HTML Guide filename, download source and dashboard version display as v1.2.
* Removed the misleading legacy v1.0 file path and stale v1.1 dashboard label.

= 1.19.0 =
* Simplified ordinary text, image and link marking to click-and-confirm.
* Generates internal field keys, type, destination and scope automatically, with optional advanced settings.
* Detects explicit slider, accordion, repeater and form marker classes without rewriting original design code.
* Dynamic components require only an editor-facing title before enabling them as one editable unit.
* Removed heuristic bulk marking of ordinary content to preserve completed HTML, CSS, JavaScript and animation.
* Added HTML Guide v1.2 while retaining compatibility with existing data-em-* markup.

= 1.18.4 =
* Emerge Mono SEO画面をCoreの共通ダッシュボード内へ統合。
* CoreのサイドメニューにANALYSIS / SEO・AIを追加。

= 1.18.3 =
* Reworked the content editor around the Journal two-column editing layout.
* Added a clear featured-image card with select, change and remove controls.
* Kept title and editable slug at the top and fixed narrow SEO panel overflow.

= 1.18.2 =
* 固定ページにもWordPressのアイキャッチ画像設定を追加。
* タイトルとURLスラッグを編集画面の先頭へ統合。
* Coreの横幅を活かしながら、Journalに合わせた2カラム編集UIへ改善。

= 1.18.1 =
* 投稿タイプ作成フォームの例を、特定用途の「ライバー」から一般的な「お知らせ」に変更。

= 1.18.0 =
* サイドメニューを運用中心に再編し、構築者向け機能をDESIGNへ移動。
* Journalと共通する「ページ管理」を追加。
* Header／Footerの共通コンテンツ編集画面を追加。
* 編集箇所に「編集先」を追加し、ページ・Header・Footerへ振り分け可能に変更。

= 1.17.2 =
* 未設定レイヤーのキー自動入力を廃止し、「キーを設定してください」と表示。
* 空のキーでは編集箇所を登録できないよう入力チェックを追加。

= 1.17.1 =
* Fixed Core admin CSS leaking into Maintenance and other Emerge Mono plugin screens and hiding the WordPress admin bar.

= 1.17.0 =
* Added visible field keys, cross-template layers, and key filtering to the layer panel.
* Improved automatic image-key naming and added editor extension hooks.

= 1.16.7 =
* 複数ページ一括インポートを廃止。
* テンプレート名と種類を確認しながらHTMLを1件ずつ登録する構成へ統一。
* ZIPアップロード画面、JavaScript処理、サーバー側受付処理を削除。

= 1.16.6 =
* head内で外部JavaScriptより後ろにあるインラインCSSを先に適用するよう補正。
* 外部ライブラリの待機中にスライダー画像が縦並びで表示される問題を修正。
* 既存テンプレートにも更新時に読み込み順補正を自動適用。

= 1.16.5 =
* HTML自動判別時にscript・styleブロックをバイト単位で保持。
* スライダー・アコーディオン・投稿一覧のDOM処理後もアニメーションコードを保持。
* 1.16.0から1.16.4で文字参照へ変換された既存テンプレートを更新時に自動修復。

= 1.16.4 =
* ページ作成モーダルの公開URLをWordPressのサイトURLから生成。
* サブディレクトリに設置したWordPressでも、実際の公開先を正しく表示。

= 1.16.3 =
* Core内の全モーダルを、画面内ボタンでのみ閉じる仕様へ統一。
* 背景クリック、ドラッグ終了位置、Escape、Enterで閉じたり確定したりしないよう変更。
* テキスト選択操作中にモーダルが意図せず閉じる問題を修正。

= 1.16.2 =
* 固定ページ・投稿コンテンツ作成モーダルのEnter送信を廃止。
* 日本語IMEの変換確定で意図せずページが作成される問題を修正。
* 作成ボタンを明示的に押した場合だけデータを作成するよう変更。

= 1.16.1 =
* Macで作成したZIP内の__MACOSX、AppleDouble（._*）、.DS_Store、隠しファイルを除外。
* 4件のHTMLが不要なメタデータを含めて7件として登録される問題を修正。

= 1.16.0 =
* 完成状態の通常HTMLをそのまま読み込める緩和ルールへ変更。
* header、main／本文、footerと一般的な動的コンポーネントを自動認識。
* 投稿タイプ個別ページで見本画像・見本文を保持したまま実データへ差し替える方式を追加。
* 投稿一覧で完成見本カードを複製する属性方式と旧変数方式の両方に対応。
* 管理プレビューへ実投稿または安全なサンプルカードを表示。
* 同梱HTMLガイドをv1.1へ改訂。

= 1.15.0 =
* Core管理画面内のブラウザ標準prompt・confirm・alertを廃止。
* 入力、確認、完了、エラー、危険操作をEmerge Mono共通モーダルへ統一。
* 投稿タイプの新規コンテンツ作成でタイトルとURLスラッグを同時指定可能に変更。
* スラッグの入力検証と重複検出を追加。
* ZIP読込、HTML診断、履歴復元、自動検出、一覧ページ作成の確認UIを統一。
* テンプレート、投稿タイプ、コンテンツ削除のDELETE確認を専用モーダルへ統一。

= 1.14.1 =
* ページ作成時のブラウザ標準promptを専用モーダルへ変更。
* タイトルとスラッグの同時入力、公開URLプレビュー、画面内エラー表示を追加。
* キーボード操作と作成完了後のページ表示導線を追加。

= 1.14.0 =
* 一覧テンプレートへ「一覧」「固定ページ」の両タグを表示。
* 一覧テンプレートから一覧用固定ページを作成可能に変更。
* ページ作成時のタイトル・URLスラッグ指定と重複検出を追加。
* 一覧ページを対応投稿タイプへ紐付け、誤った空ページ作成を防止。

= 1.13.1 =
* 複数ページ一括インポートを「新規テンプレート」パネル内へ移動。
* ボタンを押すまで、すべてのアップロード欄を非表示にするよう修正。

= 1.13.0 =
* 「サイト一式ZIP」を「複数ページ一括インポート」へ変更。
* ZIP内の全HTMLを個別テンプレートとして一括登録。
* EMHTMLから名前、ページ種別、投稿タイプ候補を自動判定。
* 共通素材の展開と相対パス変換、元HTMLハッシュによる重複防止を追加。
* インポート時に固定ページや投稿タイプを自動作成しない安全な構成へ変更。

= 1.12.0 =
* HTMLフォームの自動検出とCoreフォーム指定を追加。
* Forms画面で送信先、件名、完了メッセージ、自動返信を設定可能に変更。
* nonce、ハニーポット、連続送信制限、サーバー側入力検証を追加。
* 元のフォームデザインを維持し、未指定の外部フォームには干渉しない構成に変更。
* EMHTML仕様へフォームコンポーネントを追加。

= 1.11.0 =
* 投稿タイプごとのカテゴリフィルターON／OFFを追加。
* EMHTMLの一覧フィルター宣言を追加し、準拠チェックへ反映。
* postsループのカードへカテゴリ情報を付与し、一覧絞り込みへ接続。
* 1つの画像フィールドを背景画像にも同期できるプレースホルダーを追加。

= 1.10.0 =
* サイドメニューに「HTMLルール」を追加。
* AIへそのまま渡せるEmerge Mono HTML Specification v1.0を同梱。
* 仕様書のコピー・Markdownダウンロード機能を追加。
* EMHTML準拠チェックと100点スコアを追加。
* Templates一覧に準拠状態を表示。
* 準拠HTMLのBody名とテンプレート種別を読込フォームへ自動反映。

= 1.9.0 =
* フルスクリーンエディターをプレビュー60％・レイヤー40％へ変更し、ドラッグによる幅調整を追加。
* 画像サムネイルとホバー拡大プレビューを追加。
* 文字の実内容、リンク先、ファイル名をレイヤーに表示。
* HEADER／NAV／MAIN／SECTION／FOOTER単位のグループ表示を追加。
* 検索と種類別フィルターを追加。
* プレビューとレイヤー間の双方向ハイライトと移動を追加。

= 1.8.1 =
* 「編集箇所の指定」をビューポート固定のフルスクリーンエディターへ変更。
* ツールバー、プレビュー、レイヤーを画面内に固定し、ページ全体の縦スクロールを削減。
* 通常表示との切り替えボタンを追加。
* HTML診断を折りたたみ式にし、本文を白文字中心の高コントラスト表示へ改善。

= 1.8.0 =
* スライダー構造を自動検出し、画像単体ではなくスライダー全体を編集候補として提案。
* スライダー画像の追加・変更・削除・ドラッグ／ボタン並び替えに対応。
* FAQ／アコーディオンを自動検出し、質問と回答の増減・並び替えに対応。
* 自動判別できないHTMLでは従来の手動指定を利用可能。

= 1.7.7 =
* 文字の編集箇所ごとに、WordPress登録フォントの変更を許可できる設定を追加。
* 元のHTMLフォントを初期値として保持し、選択時だけフォントを上書き。
* WordPress Font LibraryのフォントフェイスをCoreのHTML出力へ反映。

= 1.7.6 =
* 投稿や一覧ページが残る投稿タイプも、内容を再確認して削除できるよう改善。
* 関連する一覧固定ページはゴミ箱へ移動し、管理メニューとの紐付けを解除。
* 関連記事は明示的な再確認後に完全削除し、メディアライブラリの画像は保持。

= 1.7.5 =
* 投稿タイプの記事一覧を、編集・表示・削除が操作欄に並ぶ管理UIへ刷新。
* 公開状態の日本語バッジ、更新日、件数表示、空状態を追加。
* 記事のDELETE確認付きゴミ箱移動を追加。

= 1.7.4 =
* テンプレートと投稿タイプの削除に、大文字DELETEの完全一致確認を追加。

= 1.7.3 =
* 使用中テンプレートを、関連先を確認したうえで安全に削除できるよう改善。
* 関連固定ページはゴミ箱へ移動し、投稿タイプは割り当てだけ解除して記事を保持。

= 1.7.2 =
* CONTENT欄の固定ページ名を、ページタイトルではなく登録時のテンプレート名で表示。

= 1.7.1 =
* 独立HTML出力時のWordPress管理バーアイコン表示を修正。
* ダッシュボード、上部バー、サイドメニュー、戻るボタンのUIをJournalと統一。

= 1.7.0 =
* API不要の編集候補自動検出を追加。
* 複数テンプレートのDOM構造からheader・nav・footerの共通部分を検出。
* 自動候補は未保存状態で提示し、人が確認・修正してから保存する安全な流れに変更。

= 1.6.2 =
* WordPress 7.1で隠し管理ページにDeprecated警告が出る問題を修正。

= 1.6.1 =
* マーク編集iframeの互換問題を修正し、安全なDOMプレビューへ変更。

= 1.6.0 =
* 安全な管理プレビューとHTML診断を追加。
* Undo / Redo、テンプレート履歴、削除保護を追加。
* サイト一式ZIP、明示的な一覧カード、レスポンシブ画像に対応。
* 制作権限と共通拡張フックを追加。
