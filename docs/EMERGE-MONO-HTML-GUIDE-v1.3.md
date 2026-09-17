# Emerge Mono HTML Guide v1.3

対象: Emerge Mono Core 1.28.0以降

## この文書をAIへ渡すときの指示

完成状態のHTMLを通常どおり制作してください。文章・画像・リンク・CSS・JavaScript・アニメーションを、変数や空欄へ置き換えないでください。

Emerge Mono Core用の変更は、原則として既存classへ目印classを足すだけです。元のclass、id、DOM構造、CSS、JavaScriptを削除・改名・再構成しないでください。

## 基本方針

- 文字・画像・背景画像・リンクにCore専用マーカーは不要です。
- 通常要素は、読み込み後にCoreのマークモードでクリックして編集可能にします。
- 枚数や項目数を増減する動的コンテンツだけ、下記の目印classを追加します。
- `data-em-*`はCoreが保存時に付与する内部属性です。既存HTMLにある場合は保持しますが、新規HTMLへ手作業で大量に付ける必要はありません。
- HTML単体をブラウザで開いても、完成デザインが見える状態を維持します。

## HTMLの基本構造

完全なHTML5文書、UTF-8、重複しないidを使用してください。共通部分の判別には通常の`header`、`main`、`footer`を使います。

```html
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ページタイトル</title>
</head>
<body>
  <header>...</header>
  <main>...</main>
  <footer>...</footer>
</body>
</html>
```

JavaScriptは対象要素が存在しないページでも停止しないようにしてください。入力フォームの各入力欄には一意の`name`を付けます。

## URL・スラッグ・内部リンク

サイト内リンクは、設置環境に依存しないルート相対パスで記述します。

```html
<a href="/">HOME</a>
<a href="/about/">私たちについて</a>
<a href="/news/sample-entry/">お知らせ詳細</a>
```

- `/about/`のように、公開時の最終スラッグから始まる形式を使用します。
- テスト環境のサブディレクトリ（例: `/sample-site/about/`）をHTMLへ埋め込みません。
- 同一サイトのURLを`https://example.com/about/`のような環境固有の絶対URLで固定しません。
- 外部サイトは`https://`から始まる絶対URL、ページ内移動は`#section-id`、メールと電話は`mailto:`・`tel:`を使用します。
- 固定ページ、投稿タイプ、システムページで同じ公開スラッグを使用しません。WordPressが自動で`-2`を付ける状態を完成扱いにしないでください。
- HTML作成時に、ページ一覧と投稿タイプ一覧を先に決めてください。まだ作成していないリンク先は、Coreの事前検査では警告になります。

Coreの事前検査はリンクを書き換えません。未作成リンク、設置先サブディレクトリを含むリンク、相対リンクを報告し、制作者が意図を確認します。

## 画像・フォント・その他の素材

HTML単体の制作中はローカル相対パスを使用できますが、Coreへ変更案を送る前に素材をWordPressへアップロードし、返されたURLへ置き換えます。

```html
<!-- 制作中 -->
<img src="images/hero.webp" alt="サービスの紹介">

<!-- Coreへ反映するとき -->
<img src="https://example.com/wp-content/uploads/2026/09/hero.webp" alt="サービスの紹介">
```

- `img[src]`だけでなく、`source[srcset]`、CSSの`url(...)`、OGP画像も確認します。
- 他社サイトの画像URLを直接使うホットリンクは行いません。許可済みCDNなど、意図した外部配信だけを使用します。
- `picture`を使う場合は各`source`とフォールバック用`img`を揃え、すべての画面幅で画像が表示されるようにします。
- 内容を伝える画像には具体的な`alt`を付け、装飾画像は`alt=""`にします。
- Coreは素材参照を自動で別URLへ変更しません。空の参照、未アップロードの相対素材、外部ドメイン素材を事前検査で報告します。

## 動的コンテンツとJavaScript

項目を追加・削除・並べ替えた後も、元の機能が動作する実装にします。

- 項目数を固定値で持たず、実行時のDOMから取得します。
- 0件・1件・複数件の状態を想定し、対象がない場合もJavaScriptエラーを起こしません。
- 初回読込時だけ取得したNodeListに依存しません。イベント委譲または再初期化可能な関数を使用します。
- スライダーや外部ライブラリは、項目変更後に安全に再初期化できる構造にします。
- タブやアコーディオンの`id`、`aria-controls`、`aria-labelledby`は複製後も一意になるようにします。
- PC表示とモバイル表示で見た目だけが違うメニューは、同一内容・同一キーで管理します。

## 通常の文字・画像・リンク

専用classは付けません。完成見本をそのまま記述します。

```html
<h2>所属ライバー</h2>
<p>ここに完成状態の紹介文を入れます。</p>
<img src="images/sample.webp" alt="所属ライバーの見本画像">
<a href="/audition/">オーディションを見る</a>
```

Coreでは「マークモードをON → 対象をクリック → 編集可能にする」で設定できます。通常操作ではキー、保存先、適用範囲を入力する必要はありません。

## スライダー

スライド群を直接囲む要素へ`emcore-slider`、複製する各スライドへ`emcore-slider-item`を追加します。既存のスライダー用classは残します。

```html
<div class="swiper-wrapper emcore-slider">
  <div class="swiper-slide emcore-slider-item">
    <img src="images/slide-01.webp" alt="募集のお知らせ">
  </div>
  <div class="swiper-slide emcore-slider-item">
    <img src="images/slide-02.webp" alt="キャンペーンのお知らせ">
  </div>
</div>
```

矢印、ページネーション、装飾をスライド項目の中へ移動しないでください。JavaScriptではスライド数を固定値にせず、現在のDOMから取得してください。

## FAQ・アコーディオン

全体へ`emcore-accordion`、各項目へ`emcore-accordion-item`、質問へ`emcore-accordion-trigger`、回答へ`emcore-accordion-content`を追加します。

```html
<div class="faq-list emcore-accordion">
  <section class="faq-item emcore-accordion-item">
    <button class="faq-question emcore-accordion-trigger" type="button">応募条件はありますか？</button>
    <div class="faq-answer emcore-accordion-content">18歳以上の方が対象です。</div>
  </section>
</div>
```

開閉処理で使う既存class、`aria-expanded`、`aria-controls`などは保持します。

## フォーム

管理対象にする`form`へ`emcore-form`、各入力欄へ`emcore-form-field`を追加します。既存のデザインclassは残します。

```html
<form class="contact-form emcore-form">
  <label>お名前
    <input class="form-control emcore-form-field" type="text" name="name" required>
  </label>
  <label>メールアドレス
    <input class="form-control emcore-form-field" type="email" name="email" required>
  </label>
  <button type="submit">送信する</button>
</form>
```

送信先などの設定は、Coreでフォームを編集可能にして保存した後にForms画面で行います。

## 一般的な繰り返し要素

繰り返し全体へ`emcore-repeater`、複製する各項目へ`emcore-repeater-item`を追加します。これはCoreに構造を伝える目印です。用途に応じた項目内容は完成見本のまま残します。

```html
<div class="card-grid emcore-repeater">
  <article class="card emcore-repeater-item">...</article>
  <article class="card emcore-repeater-item">...</article>
</div>
```

項目内で変更する文字・画像・リンクには`emcore-repeater-field`を追加します。Coreは最初の完成見本から項目構成を取得します。

## リスト

応募条件、特徴、注意事項などの短文リストには、全体へ`emcore-list`、各項目へ`emcore-list-item`を追加します。

```html
<ul class="requirements emcore-list">
  <li class="requirement emcore-list-item">18歳以上の方</li>
  <li class="requirement emcore-list-item">継続して配信できる方</li>
</ul>
```

## ステップ・タイムライン

手順、導入フロー、沿革などには、全体へ`emcore-steps`、各項目へ`emcore-step-item`を追加します。番号、タイトル、説明へそれぞれ対応するclassを追加します。

```html
<div class="timeline emcore-steps">
  <article class="timeline-item emcore-step-item">
    <span class="timeline-number emcore-step-index">1</span>
    <h3 class="timeline-title emcore-step-title">応募</h3>
    <p class="timeline-text emcore-step-description">応募フォームから送信してください。</p>
  </article>
</div>
```

`emcore-step-index`は現在の並び順からCoreが1、2、3…と自動設定します。

## ギャラリー

画像一覧には、全体へ`emcore-gallery`、各画像項目へ`emcore-gallery-item`を追加します。項目内の最初の画像とリンクをCoreが取得します。

```html
<div class="photo-grid emcore-gallery">
  <a class="photo emcore-gallery-item" href="images/photo-01.webp">
    <img src="images/photo-01.webp" alt="イベント会場">
  </a>
</div>
```

## タブ

タブ全体へ`emcore-tabs`、ボタンと内容を含む単位へ`emcore-tab-item`、操作ボタンへ`emcore-tab-trigger`、内容へ`emcore-tab-content`を追加します。

```html
<div class="tabs emcore-tabs">
  <section class="tab-item emcore-tab-item">
    <button class="tab-button emcore-tab-trigger" type="button" role="tab">サービス</button>
    <div class="tab-panel emcore-tab-content" role="tabpanel">サービスの説明</div>
  </section>
</div>
```

Coreは追加・並べ替え時に`id`、`aria-controls`、`aria-labelledby`を再設定します。JavaScriptは固定IDへ依存せず、現在のDOMからタブを取得してください。

## 表・比較表

増減させる表へ`emcore-table`、各データ行へ`emcore-table-row`、編集するセルへ`emcore-table-cell`を追加します。固定見出し行へ`emcore-table-row`は付けません。

```html
<table class="price-table emcore-table">
  <thead><tr><th>プラン</th><th>料金</th></tr></thead>
  <tbody>
    <tr class="emcore-table-row"><td class="emcore-table-cell">ライト</td><td class="emcore-table-cell">5,000円</td></tr>
  </tbody>
</table>
```

## メニュー

項目を追加・削除・並べ替えできるメニュー全体へ`emcore-menu`、各メニュー項目へ`emcore-menu-item`を追加します。既存のナビゲーション用class、DOM構造、開閉処理、`aria-*`属性は残します。

各項目にはリンク要素を1つ入れてください。Coreはリンクの文字を表示名、`href`をリンク先として編集します。

リンク内にアイコンや装飾文字がある場合は、表示名だけを囲む要素へ`data-em-menu-label="1"`を付けると、その装飾を残したまま文字を変更できます。

```html
<a class="nav-cta emcore-menu-item" href="/audition/">
  <span aria-hidden="true">♥ </span>
  <span data-em-menu-label="1">AUDITION</span>
  <span aria-hidden="true"> →</span>
</a>
```

```html
<nav class="global-nav emcore-menu">
  <a class="global-nav-item emcore-menu-item" href="/">HOME</a>
  <a class="global-nav-item emcore-menu-item" href="/liver/">LIVER</a>
  <a class="global-nav-item emcore-menu-item" href="/contact/">CONTACT</a>
</nav>
```

`ul`と`li`を使用する場合は、複製する`li`へ`emcore-menu-item`を追加します。

```html
<nav class="global-nav emcore-menu">
  <ul>
    <li class="emcore-menu-item"><a href="/">HOME</a></li>
    <li class="emcore-menu-item"><a href="/about/">ABOUT</a></li>
  </ul>
</nav>
```

PC用とモバイル用に同じメニューが別々に存在する場合は、Coreで同じキーを設定すると1つのメニューデータから両方を更新できます。Coreが保存した同一の`data-em-key`は削除・変更しないでください。

パンくずリスト、記事内リンク、フッターの補助リンクなど、クライアントが項目数を変更しないナビゲーションへは`emcore-menu`を付けません。

## 投稿タイプの一覧ページ

一覧ページは、実際に表示できる見本カードを最低1件残してください。投稿データへ差し替えるカードだけ`data-em-repeat="posts"`で指定し、その内部の対応項目を`data-em-post-field`で示します。

```html
<main>
  <section class="member-grid">
    <a class="member-card emcore-repeater-item" data-em-repeat="posts" href="/member/sample/">
      <img data-em-post-field="thumbnail" src="images/sample-member.webp" alt="サンプルメンバー">
      <h2 data-em-post-field="title">サンプルメンバー</h2>
      <p data-em-post-field="meta:name_en">SAMPLE MEMBER</p>
    </a>
  </section>
</main>
```

主な`data-em-post-field`:

- `title`: 投稿タイトル
- `permalink`: 投稿URL（リンク要素で使用）
- `thumbnail`: アイキャッチ画像
- `excerpt`: 抜粋
- `date`: 公開日
- `meta:任意のキー`: Core投稿編集画面の追加項目

旧形式の`{{#posts}}...{{/posts}}`も互換機能として利用できます。

## 投稿タイプの個別ページ

架空または実在する1件分の文章と画像を入れ、完成デザインとして作ります。画像を消したり、画面全体を変数だけにしたりしないでください。読み込み後、差し替える場所をCoreのマークモードで指定します。

```html
<main class="member-detail">
  <img class="member-visual" src="images/sample-member.webp" alt="サンプルメンバー">
  <h1 class="member-name">サンプルメンバー</h1>
  <p class="member-reading">さんぷるめんばー</p>
  <p class="member-profile">プロフィール本文の完成見本です。</p>
</main>
```

## 従来属性との互換性

## システムページの本文スロット

Core管理のお問い合わせと404を同じサイトデザインで表示する場合は、差し替えてよい本文領域へ`data-em-slot="content"`を付けることを推奨します。プライバシーポリシーや利用規約など、固有のページデザインを持つものは通常のHTMLテンプレートとして作成します。

```html
<body>
  <header>...</header>
  <main data-em-slot="content">
    <!-- 通常ページの完成見本 -->
  </main>
  <footer>...</footer>
</body>
```

Coreはシステムページの表示時に、この要素の中身だけをCore管理コンテンツへ差し替えます。要素自身のclass、背景、余白などは残るため、サイト全体のデザインを継承できます。

マーカーがない場合は、`header`と`footer`の間をページ固有領域として差し替えます。これにより、`main`の外側にあるヒーロー画像などもシステムページには引き継がれません。headerとfooterを判別できない場合は、`main`、`role="main"`の順で自動検出します。複雑なHTMLでは、誤検出を避けるためマーカーを明示してください。

すでに次の属性を含むHTMLは、そのまま保持して読み込めます。

- `data-em-editable`
- `data-em-key`
- `data-em-label`
- `data-em-component`
- `data-em-repeat`
- `data-em-post-field`
- `data-em-part`
- `data-em-slot`

新方式では動的要素の目印classを検出し、Coreの確認操作後に必要な`data-em-*`を自動付与します。

## 最終チェック

- HTML単体で完成デザイン、画像、文章、アニメーションが表示される。
- 元のclass、id、CSS、JavaScriptを壊していない。
- `header`、`main`、`footer`が判別できる。
- システムページへ利用するHTMLでは、本文領域に`data-em-slot="content"`がある。
- 通常の文字・画像・リンクへ不要なCore属性を付けていない。
- スライダー、アコーディオン、メニュー、リスト、ステップ、ギャラリー、タブ、表、カード一覧など、増減する要素だけに目印classを付けた。
- 投稿一覧には複製する見本カードがある。
- 投稿個別ページには1件分の完成見本がある。
- フォーム入力欄に一意の`name`があり、送信ボタンがある。
- 内部リンクが`/slug/`形式で、テスト環境のサブディレクトリやドメインに固定されていない。
- 固定ページ、投稿タイプ、システムページの公開スラッグが重複していない。
- Coreへ反映するHTMLにローカル相対素材が残っておらず、外部素材は意図した配信元だけである。
- 動的コンテンツを0件・1件・複数件にしてもJavaScriptが停止しない。
