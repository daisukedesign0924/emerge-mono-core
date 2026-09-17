<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function emcore_mcp_records( $key ) { $v = get_option( $key, array() ); return is_array( $v ) ? $v : array(); }
function emcore_mcp_random() { return bin2hex( random_bytes( 32 ) ); }
function emcore_mcp_base64url( $bytes ) { return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); }

function emcore_mcp_render_authorization_page( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'approved'     => false,
		'device_label' => 'Codex',
	) );
	$approved = ! empty( $args['approved'] );
	$site_name = get_bloginfo( 'name' ) ?: wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$logo_url = EMCORE_URL . 'assets/img/icon-w.webp';
	?><!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo esc_html( $approved ? '接続を許可しました — Emerge Mono' : 'Codex接続 — Emerge Mono' ); ?></title>
		<style>
			:root{color-scheme:dark;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;background:#090a0b;color:#f5f5f5}
			*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 82% 8%,rgba(255,255,255,.08),transparent 28%),linear-gradient(135deg,#090a0b 0%,#111214 52%,#090a0b 100%);overflow-x:hidden}
			body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.2;background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);background-size:42px 42px}
			.em-auth-shell{position:relative;z-index:1;min-height:100vh;display:grid;grid-template-rows:auto 1fr auto}
			.em-auth-head{height:76px;display:flex;align-items:center;padding:0 clamp(24px,5vw,72px);border-bottom:1px solid rgba(255,255,255,.1);background:rgba(9,10,11,.66);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px)}
			.em-auth-logo{width:38px;height:38px;object-fit:contain}.em-auth-kicker{margin-left:16px;font:600 11px/1.2 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.18em;color:rgba(255,255,255,.48)}
			.em-auth-main{width:min(720px,calc(100% - 36px));margin:auto;padding:54px 0}.em-auth-card{position:relative;overflow:hidden;padding:clamp(28px,5vw,52px);border:1px solid rgba(255,255,255,.14);border-radius:26px;background:linear-gradient(145deg,rgba(255,255,255,.095),rgba(255,255,255,.035));box-shadow:0 32px 90px rgba(0,0,0,.48),inset 0 1px 0 rgba(255,255,255,.09);backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px)}
			.em-auth-card:after{content:"";position:absolute;width:250px;height:250px;border:1px solid rgba(255,255,255,.08);border-radius:50%;right:-120px;top:-140px;box-shadow:0 0 0 38px rgba(255,255,255,.025);pointer-events:none}
			.em-auth-eyebrow{margin:0 0 20px;font:600 11px/1.2 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.2em;color:rgba(255,255,255,.48)}h1{max-width:570px;margin:0;font-size:clamp(32px,5.2vw,54px);line-height:1.06;letter-spacing:-.045em}p{line-height:1.8;color:rgba(255,255,255,.67)}.em-auth-lead{margin:22px 0 30px;font-size:15px}
			.em-auth-route{display:grid;grid-template-columns:1fr auto 1fr;gap:14px;align-items:center;margin:30px 0;padding:18px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(0,0,0,.22)}.em-auth-node small{display:block;margin-bottom:7px;font:600 10px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.14em;color:rgba(255,255,255,.36)}.em-auth-node strong{display:block;font-size:14px;overflow-wrap:anywhere}.em-auth-arrow{color:rgba(255,255,255,.35)}
			.em-auth-note{display:flex;gap:10px;margin:0 0 30px;font-size:13px}.em-auth-dot{flex:0 0 auto;width:7px;height:7px;margin-top:9px;border-radius:50%;background:#fff;box-shadow:0 0 12px rgba(255,255,255,.8)}form{margin:0}.em-auth-button{appearance:none;border:1px solid #fff;border-radius:12px;padding:14px 22px;background:#fff;color:#0a0a0a;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 10px 30px rgba(0,0,0,.3);transition:transform .18s ease,background .18s ease}.em-auth-button:hover{transform:translateY(-1px);background:#e8e8e8}.em-auth-button:focus-visible{outline:3px solid rgba(255,255,255,.35);outline-offset:3px}
			.em-auth-success{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;margin-bottom:24px;border:1px solid rgba(255,255,255,.28);border-radius:50%;background:rgba(255,255,255,.1);font-size:22px}.em-auth-foot{padding:20px;text-align:center;font:500 10px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.14em;color:rgba(255,255,255,.3)}
			@media(max-width:560px){.em-auth-head{height:64px}.em-auth-logo{width:32px;height:32px}.em-auth-main{padding:26px 0}.em-auth-card{border-radius:20px}.em-auth-route{grid-template-columns:1fr}.em-auth-arrow{transform:rotate(90deg);justify-self:start}.em-auth-button{width:100%}}
		</style>
	</head>
	<body>
	<div class="em-auth-shell">
		<header class="em-auth-head"><img class="em-auth-logo" src="<?php echo esc_url( $logo_url ); ?>" alt=""><span class="em-auth-kicker">EMERGE MONO / SECURE CONNECTION</span></header>
		<main class="em-auth-main"><section class="em-auth-card">
			<?php if ( $approved ) : ?>
				<div class="em-auth-success" aria-hidden="true">✓</div><p class="em-auth-eyebrow">CONNECTION APPROVED</p><h1>接続を許可しました。</h1>
				<p class="em-auth-lead">認証情報はCodexが一度だけ安全に受け取ります。この画面を閉じてCodexへ戻ってください。</p>
				<button class="em-auth-button" type="button" onclick="window.close()">画面を閉じる</button>
			<?php else : ?>
				<p class="em-auth-eyebrow">CONNECTION REQUEST</p><h1>Codexとの接続を<br>許可しますか？</h1>
				<p class="em-auth-lead">Codexが、このWordPress内のEmerge Monoテンプレートを取得し、変更案を作成できるようになります。</p>
				<div class="em-auth-route">
					<div class="em-auth-node"><small>REQUEST FROM</small><strong>Codex</strong></div><span class="em-auth-arrow" aria-hidden="true">→</span><div class="em-auth-node"><small>CONNECT TO</small><strong><?php echo esc_html( $site_name ); ?></strong></div>
				</div>
				<p class="em-auth-note"><span class="em-auth-dot"></span><span>公開・反映は、Codex側であなたが明示的に承認した場合だけ実行されます。接続端末：<?php echo esc_html( $args['device_label'] ); ?></span></p>
				<form method="post"><?php wp_nonce_field( 'emcore_mcp_authorize_' . sanitize_key( $args['pairing_id'] ?? '' ) ); ?><input type="hidden" name="emcore_mcp_authorize" value="<?php echo esc_attr( $args['pairing_id'] ?? '' ); ?>"><button class="em-auth-button" type="submit">接続を許可</button></form>
			<?php endif; ?>
		</section></main>
		<footer class="em-auth-foot">EMERGE MONO CORE · DIRECT CONNECTION</footer>
	</div>
	</body></html><?php
	exit;
}

function emcore_mcp_create_pairing( WP_REST_Request $request ) {
	$ip = sanitize_key( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ); $rate_key = 'emcore_pair_' . md5( $ip );
	$count = (int) get_transient( $rate_key ); if ( $count >= 10 ) { return emcore_codex_error( '接続要求が多すぎます。しばらく待ってください。', 429 ); }
	set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );
	$challenge = sanitize_text_field( $request->get_param( 'code_challenge' ) );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $challenge ) ) { return emcore_codex_error( 'code_challengeが不正です。' ); }
	$id = emcore_mcp_random(); $all = emcore_mcp_records( 'emcore_mcp_pairings' );
	$all[ $id ] = array( 'challenge' => $challenge, 'label' => sanitize_text_field( $request->get_param( 'device_label' ) ?: 'Codex' ), 'created' => time(), 'status' => 'pending' );
	update_option( 'emcore_mcp_pairings', array_slice( $all, -30, 30, true ), false );
	return rest_ensure_response( array( 'pairing_id' => $id, 'authorize_url' => admin_url( 'admin.php?emcore_mcp_authorize=' . $id ), 'expires_in' => 600 ) );
}

add_action( 'admin_init', function () {
	$id = sanitize_key( $_GET['emcore_mcp_authorize'] ?? $_POST['emcore_mcp_authorize'] ?? '' ); if ( ! $id ) { return; }
	if ( ! current_user_can( emcore_design_capability() ) ) { wp_die( 'この接続を許可する権限がありません。', 403 ); }
	$all = emcore_mcp_records( 'emcore_mcp_pairings' ); $item = $all[ $id ] ?? null;
	if ( ! $item || (int) $item['created'] < time() - 600 ) { wp_die( '接続要求の有効期限が切れています。' ); }
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		check_admin_referer( 'emcore_mcp_authorize_' . $id );
		$code = emcore_mcp_random(); $item['status'] = 'approved'; $item['code_hash'] = hash( 'sha256', $code ); $item['code_once'] = $code; $item['user'] = get_current_user_id(); $item['approved'] = time(); $all[ $id ] = $item;
		update_option( 'emcore_mcp_pairings', $all, false );
		emcore_mcp_render_authorization_page( array( 'approved' => true ) );
	}
	emcore_mcp_render_authorization_page( array( 'device_label' => $item['label'], 'pairing_id' => $id ) );
} );

function emcore_mcp_poll_pairing( WP_REST_Request $request ) {
	$id = sanitize_key( $request['id'] ); $all = emcore_mcp_records( 'emcore_mcp_pairings' ); $item = $all[ $id ] ?? null;
	if ( ! $item || (int) $item['created'] < time() - 600 ) { return emcore_codex_error( '接続要求が見つからないか期限切れです。', 404 ); }
	$out = array( 'status' => $item['status'] );
	if ( $item['status'] === 'approved' && ! empty( $item['code_once'] ) ) { $out['authorization_code'] = $item['code_once']; unset( $all[ $id ]['code_once'] ); update_option( 'emcore_mcp_pairings', $all, false ); }
	return rest_ensure_response( $out );
}

function emcore_mcp_exchange_token( WP_REST_Request $request ) {
	$id = sanitize_key( $request->get_param( 'pairing_id' ) ); $code = (string) $request->get_param( 'authorization_code' ); $verifier = (string) $request->get_param( 'code_verifier' );
	$all = emcore_mcp_records( 'emcore_mcp_pairings' ); $item = $all[ $id ] ?? null;
	if ( ! $item || (int) ( $item['created'] ?? 0 ) < time() - 600 || (int) ( $item['approved'] ?? 0 ) < time() - 600 || empty( $item['code_hash'] ) || ! hash_equals( $item['code_hash'], hash( 'sha256', $code ) ) || ! hash_equals( $item['challenge'], emcore_mcp_base64url( hash( 'sha256', $verifier, true ) ) ) ) { return emcore_codex_error( '接続コードを確認できないか、有効期限が切れています。', 401 ); }
	$token = emcore_mcp_random(); $token_id = substr( hash( 'sha256', $token ), 0, 16 ); $tokens = emcore_mcp_records( 'emcore_mcp_tokens' );
	$tokens[ $token_id ] = array( 'hash' => hash( 'sha256', $token ), 'user' => (int) $item['user'], 'label' => $item['label'], 'created' => time(), 'expires' => time() + 90 * DAY_IN_SECONDS, 'last_used' => 0 ); update_option( 'emcore_mcp_tokens', $tokens, false );
	unset( $all[ $id ] ); update_option( 'emcore_mcp_pairings', $all, false );
	return rest_ensure_response( array( 'access_token' => $token, 'token_type' => 'Bearer', 'site_id' => hash( 'sha256', home_url( '/' ) ), 'site_name' => get_bloginfo( 'name' ), 'site_url' => home_url( '/' ) ) );
}

function emcore_mcp_authenticate_bearer() {
	global $emcore_mcp_current_token_id;
	if ( is_user_logged_in() ) { return; } $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
	if ( ! preg_match( '/^Bearer\s+([a-f0-9]{64})$/i', $header, $m ) ) { return; }
	$hash = hash( 'sha256', $m[1] ); $id = substr( $hash, 0, 16 ); $tokens = emcore_mcp_records( 'emcore_mcp_tokens' );
	if ( empty( $tokens[ $id ]['hash'] ) || (int) ( $tokens[ $id ]['expires'] ?? 0 ) < time() || ! hash_equals( $tokens[ $id ]['hash'], $hash ) ) { return; }
	$emcore_mcp_current_token_id = $id; wp_set_current_user( (int) $tokens[ $id ]['user'] ); $tokens[ $id ]['last_used'] = time(); update_option( 'emcore_mcp_tokens', $tokens, false );
}

function emcore_mcp_disconnect_current() {
	global $emcore_mcp_current_token_id; if ( ! $emcore_mcp_current_token_id ) { return emcore_codex_error( '接続を特定できません。', 401 ); }
	$tokens = emcore_mcp_records( 'emcore_mcp_tokens' ); unset( $tokens[ $emcore_mcp_current_token_id ] ); update_option( 'emcore_mcp_tokens', $tokens, false );
	return rest_ensure_response( array( 'disconnected' => true ) );
}

function emcore_page_mcp_connections() {
	if ( ! current_user_can( emcore_design_capability() ) ) { return; }
	emcore_shell_start( 'Codex接続' ); $tokens = emcore_mcp_records( 'emcore_mcp_tokens' );
	echo '<div class="emcore-card emcore-connection-card"><p class="emcore-kicker">DIRECT CONNECTION</p><h2>このWordPressへ接続しているCodex</h2><p>接続はEmerge Mono公式サーバーを経由しません。トークンは90日で失効し、いつでも解除できます。</p>';
	if ( ! $tokens ) { echo '<p>接続中の端末はありません。</p>'; }
	foreach ( $tokens as $id => $token ) {
		$user = get_userdata( (int) ( $token['user'] ?? 0 ) );
		echo '<div class="emcore-card emcore-connection-item" style="margin-top:16px"><span class="emcore-connection-label">接続ラベル</span><strong>' . esc_html( $token['label'] ?? 'Codex' ) . '</strong><p>' . esc_html( $user ? $user->display_name : '不明なユーザー' ) . ' ／ 最終利用: ' . esc_html( ! empty( $token['last_used'] ) ? wp_date( 'Y-m-d H:i', $token['last_used'] ) : '未使用' ) . ' ／ 期限: ' . esc_html( wp_date( 'Y-m-d', $token['expires'] ?? 0 ) ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="emcore_revoke_mcp"><input type="hidden" name="token_id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'emcore_revoke_mcp_' . $id ); echo '<button class="emcore-btn" type="submit">接続を解除</button></form></div>';
	}
	echo '</div>'; emcore_shell_end();
}

add_action( 'admin_post_emcore_revoke_mcp', function () {
	$id = sanitize_key( $_POST['token_id'] ?? '' ); if ( ! current_user_can( emcore_design_capability() ) ) { wp_die( '権限がありません。' ); }
	check_admin_referer( 'emcore_revoke_mcp_' . $id ); $tokens = emcore_mcp_records( 'emcore_mcp_tokens' ); unset( $tokens[ $id ] ); update_option( 'emcore_mcp_tokens', $tokens, false );
	wp_safe_redirect( admin_url( 'admin.php?page=emcore-codex-connections&revoked=1' ) ); exit;
} );

function emcore_mcp_request( WP_REST_Request $request ) {
	$body = $request->get_json_params(); $method = $body['method'] ?? ''; $id = $body['id'] ?? null;
	if ( $method === 'initialize' ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'protocolVersion' => '2025-06-18', 'capabilities' => array( 'tools' => (object) array() ), 'serverInfo' => array( 'name' => 'Emerge Mono Core', 'version' => EMCORE_VERSION ), 'instructions' => 'Operate only on this WordPress site. Validate and create a proposal before applying HTML. Never apply without explicit user approval.' ) ) ); }
	if ( $method === 'notifications/initialized' ) { return new WP_REST_Response( null, 202 ); }
	if ( $method === 'tools/list' ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'tools' => emcore_mcp_tools() ) ) ); }
	if ( $method === 'tools/call' ) { return emcore_mcp_call( $id, $body['params']['name'] ?? '', $body['params']['arguments'] ?? array() ); }
	return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => -32601, 'message' => 'Method not found' ) ) );
}

function emcore_mcp_tools() {
	$object = array( 'type' => 'object', 'properties' => (object) array() );
	return array(
		array( 'name' => 'status', 'description' => 'Get this Emerge Mono Core site identity.', 'inputSchema' => $object, 'annotations' => array( 'readOnlyHint' => true ) ),
		array( 'name' => 'list_templates', 'description' => 'List HTML templates on this site.', 'inputSchema' => $object, 'annotations' => array( 'readOnlyHint' => true ) ),
		array( 'name' => 'get_template', 'description' => 'Get one complete HTML template.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'template_id' => array( 'type' => 'string' ) ), 'required' => array( 'template_id' ) ), 'annotations' => array( 'readOnlyHint' => true ) )
	);
}

function emcore_mcp_call( $id, $name, $args ) {
	if ( $name === 'status' ) { $data = array( 'site_id' => hash( 'sha256', home_url( '/' ) ), 'site_name' => get_bloginfo( 'name' ), 'site_url' => home_url( '/' ), 'core_version' => EMCORE_VERSION ); }
	elseif ( $name === 'list_templates' ) { $data = array(); foreach ( emcore_get_templates() as $key => $tpl ) { $data[] = emcore_codex_template_summary( $key, $tpl ); } }
	elseif ( $name === 'get_template' ) { $key = sanitize_key( $args['template_id'] ?? '' ); $tpl = emcore_get_template( $key ); if ( ! $tpl ) { $data = array( 'error' => 'Template not found' ); } else { $data = array_merge( emcore_codex_template_summary( $key, $tpl ), array( 'html' => $tpl['html'] ) ); } }
	else { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => -32602, 'message' => 'Unknown tool' ) ) ); }
	return rest_ensure_response( array(
		'jsonrpc' => '2.0',
		'id' => $id,
		'result' => array(
			'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ),
			'structuredContent' => $data,
		),
	) );
}
