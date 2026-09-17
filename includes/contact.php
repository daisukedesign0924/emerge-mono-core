<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Find Core-managed forms declared by imported templates. */
function emcore_get_managed_forms() {
	$forms = array();
	foreach ( emcore_get_templates() as $template_id => $template ) {
		$html = isset( $template['html'] ) ? $template['html'] : '';
		if ( ! $html || ! preg_match_all( '/<form\b([^>]*data-em-component=["\']form["\'][^>]*)>/i', $html, $matches ) ) { continue; }
		foreach ( $matches[1] as $attrs ) {
			if ( ! preg_match( '/data-em-key=["\']([a-z0-9_-]+)["\']/i', $attrs, $key_match ) ) { continue; }
			$key = sanitize_key( $key_match[1] );
			preg_match( '/data-em-label=["\']([^"\']+)["\']/i', $attrs, $label_match );
			$forms[ $key ] = array(
				'key'         => $key,
				'label'       => ! empty( $label_match[1] ) ? sanitize_text_field( $label_match[1] ) : $key,
				'template_id' => $template_id,
				'template'    => isset( $template['name'] ) ? $template['name'] : $template_id,
			);
		}
	}
	if ( function_exists( 'emcore_system_pages_settings' ) ) {
		$system = emcore_system_pages_settings();
		foreach ( array( 'contact' => 'お問い合わせ' ) as $type => $label ) {
			if ( ! empty( $system[ $type ]['enabled'] ) ) {
				$forms[ 'system-' . $type ] = array( 'key' => 'system-' . $type, 'label' => $label, 'template_id' => '', 'template' => 'システムページ' );
			}
		}
	}
	return $forms;
}

function emcore_form_settings( $key ) {
	$all = get_option( 'emcore_form_settings', array() );
	$set = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();
	return wp_parse_args( $set, array(
		'to'            => get_option( 'admin_email' ),
		'subject'       => '[' . get_bloginfo( 'name' ) . '] お問い合わせ',
		'success'       => '送信しました。お問い合わせありがとうございます。',
		'auto_reply'    => false,
		'reply_subject' => 'お問い合わせを受け付けました',
		'reply_body'    => "お問い合わせありがとうございます。\n内容を確認のうえご連絡します。",
	) );
}

function emcore_form_required_fields( $key ) {
	foreach ( emcore_get_templates() as $template ) {
		$html = isset( $template['html'] ) ? $template['html'] : '';
		if ( ! $html || ! class_exists( 'DOMDocument' ) ) { continue; }
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		if ( ! $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ) ) { continue; }
		$xpath = new DOMXPath( $dom );
		$forms = $xpath->query( '//form[@data-em-component="form" and @data-em-key="' . $key . '"]' );
		if ( ! $forms || ! $forms->length ) { continue; }
		$required = array();
		foreach ( $xpath->query( './/*[@required and @name]', $forms->item( 0 ) ) as $field ) { $required[] = $field->getAttribute( 'name' ); }
		libxml_clear_errors();
		return array_values( array_unique( $required ) );
	}
	return array();
}

/** Keep the imported design and route only explicitly managed forms through Core. */
add_filter( 'emerge_mono_document_html', function ( $html ) {
	if ( stripos( $html, 'data-em-component="form"' ) === false && stripos( $html, "data-em-component='form'" ) === false ) { return $html; }
	return preg_replace_callback( '/<form\b([^>]*data-em-component=["\']form["\'][^>]*)>/i', function ( $match ) {
		$attrs = $match[1];
		if ( ! preg_match( '/data-em-key=["\']([a-z0-9_-]+)["\']/i', $attrs, $key_match ) ) { return $match[0]; }
		$key = sanitize_key( $key_match[1] );
		$attrs = preg_replace( '/\saction=["\'][^"\']*["\']/i', '', $attrs );
		$attrs = preg_replace( '/\smethod=["\'][^"\']*["\']/i', '', $attrs );
		$hidden  = '<input type="hidden" name="action" value="emcore_submit_form">';
		$hidden .= '<input type="hidden" name="emcore_form_key" value="' . esc_attr( $key ) . '">';
		$hidden .= wp_nonce_field( 'emcore_submit_form_' . $key, 'emcore_form_nonce', true, false );
		$hidden .= '<div aria-hidden="true" style="position:absolute;left:-9999px"><label>Website<input type="text" name="emcore_website" tabindex="-1" autocomplete="off"></label></div>';
		$notice = '';
		if ( isset( $_GET['emcore_form'], $_GET['emcore_form_key'] ) && sanitize_key( wp_unslash( $_GET['emcore_form_key'] ) ) === $key ) {
			$status = sanitize_key( wp_unslash( $_GET['emcore_form'] ) );
			$settings = emcore_form_settings( $key );
			$message = $status === 'sent' ? $settings['success'] : '送信できませんでした。入力内容を確認して、もう一度お試しください。';
			$notice = '<div class="emcore-form-notice is-' . esc_attr( $status ) . '" role="status">' . esc_html( $message ) . '</div>';
		}
		return '<form' . $attrs . ' action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">' . $notice . $hidden;
	}, $html );
}, 20 );

add_action( 'admin_post_emcore_submit_form', 'emcore_handle_form_submission' );
add_action( 'admin_post_nopriv_emcore_submit_form', 'emcore_handle_form_submission' );
function emcore_handle_form_submission() {
	$key      = isset( $_POST['emcore_form_key'] ) ? sanitize_key( wp_unslash( $_POST['emcore_form_key'] ) ) : '';
	$referer  = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$redirect = remove_query_arg( array( 'emcore_form', 'emcore_form_key' ), $referer );
	$fail = function () use ( $redirect, $key ) {
		wp_safe_redirect( add_query_arg( array( 'emcore_form' => 'error', 'emcore_form_key' => $key ), $redirect ) );
		exit;
	};
	if ( ! $key || ! isset( emcore_get_managed_forms()[ $key ] ) ) { $fail(); }
	if ( empty( $_POST['emcore_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['emcore_form_nonce'] ) ), 'emcore_submit_form_' . $key ) ) { $fail(); }
	if ( ! empty( $_POST['emcore_website'] ) ) { $fail(); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$rate_key = 'emcore_form_' . md5( $key . '|' . $ip );
	if ( get_transient( $rate_key ) ) { $fail(); }
	set_transient( $rate_key, 1, 20 );
	foreach ( emcore_form_required_fields( $key ) as $required ) {
		if ( ! isset( $_POST[ $required ] ) || ( is_scalar( $_POST[ $required ] ) && trim( (string) wp_unslash( $_POST[ $required ] ) ) === '' ) || ( is_array( $_POST[ $required ] ) && empty( array_filter( $_POST[ $required ] ) ) ) ) { $fail(); }
	}

	$ignored = array( 'action', 'emcore_form_key', 'emcore_form_nonce', 'emcore_website' );
	$lines = array(); $email = ''; $name = '';
	foreach ( array_slice( $_POST, 0, 50, true ) as $field => $raw ) {
		if ( in_array( $field, $ignored, true ) ) { continue; }
		$label = sanitize_text_field( str_replace( array( '_', '-' ), ' ', $field ) );
		if ( is_array( $raw ) ) {
			$raw = array_filter( $raw, 'is_scalar' );
			$value = implode( ', ', array_map( 'sanitize_text_field', wp_unslash( $raw ) ) );
		} else {
			$value = sanitize_textarea_field( wp_unslash( $raw ) );
		}
		$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 5000 ) : substr( $value, 0, 5000 );
		$lines[] = $label . ":\n" . $value;
		if ( preg_match( '/mail|email/i', $field ) && ! is_email( $value ) ) { $fail(); }
		if ( ! $email && is_email( $value ) ) { $email = sanitize_email( $value ); }
		if ( ! $name && preg_match( '/name|氏名|名前/i', $field ) ) { $name = sanitize_text_field( $value ); }
	}
	if ( ! $lines ) { $fail(); }
	$settings = emcore_form_settings( $key );
	$headers = $email ? array( 'Reply-To: ' . ( $name ? $name . ' ' : '' ) . '<' . $email . '>' ) : array();
	$sent = wp_mail( sanitize_email( $settings['to'] ), sanitize_text_field( $settings['subject'] ), implode( "\n\n", $lines ), $headers );
	if ( $sent && ! empty( $settings['auto_reply'] ) && $email ) {
		wp_mail( $email, sanitize_text_field( $settings['reply_subject'] ), sanitize_textarea_field( $settings['reply_body'] ) );
	}
	if ( ! $sent ) { $fail(); }
	wp_safe_redirect( add_query_arg( array( 'emcore_form' => 'sent', 'emcore_form_key' => $key ), $redirect ) );
	exit;
}

add_action( 'admin_post_emcore_save_forms', function () {
	if ( ! current_user_can( emcore_design_capability() ) ) { wp_die( '権限がありません。' ); }
	check_admin_referer( 'emcore_save_forms' );
	$known = emcore_get_managed_forms(); $saved = array();
	$posted = isset( $_POST['forms'] ) && is_array( $_POST['forms'] ) ? wp_unslash( $_POST['forms'] ) : array();
	foreach ( $known as $key => $form ) {
		$row = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();
		$saved[ $key ] = array(
			'to'            => isset( $row['to'] ) && is_email( $row['to'] ) ? sanitize_email( $row['to'] ) : get_option( 'admin_email' ),
			'subject'       => isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : '',
			'success'       => isset( $row['success'] ) ? sanitize_text_field( $row['success'] ) : '',
			'auto_reply'    => ! empty( $row['auto_reply'] ),
			'reply_subject' => isset( $row['reply_subject'] ) ? sanitize_text_field( $row['reply_subject'] ) : '',
			'reply_body'    => isset( $row['reply_body'] ) ? sanitize_textarea_field( $row['reply_body'] ) : '',
		);
	}
	update_option( 'emcore_form_settings', $saved, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'emcore-forms', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
} );
