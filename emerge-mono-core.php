<?php
/**
 * Plugin Name: Emerge Mono - Core
 * Plugin URI:  https://github.com/daisukedesign0924/emerge-mono-core
 * Description: 完成HTMLを取り込み、制作側は編集箇所を指定、クライアントは基本フィールドだけで更新できるエンジン。
 * Version:     1.28.5
 * Author:      Emerge Mono
 * Text Domain: emerge-mono-core
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EMCORE_VERSION', '1.28.5' );
define( 'EMCORE_FILE', __FILE__ );
define( 'EMCORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'EMCORE_URL', plugin_dir_url( __FILE__ ) );

require_once EMCORE_PATH . 'includes/page-templates.php';
require_once EMCORE_PATH . 'includes/template-engine.php';
require_once EMCORE_PATH . 'includes/mcp.php';
require_once EMCORE_PATH . 'includes/codex-bridge.php';
require_once EMCORE_PATH . 'includes/cpt.php';
require_once EMCORE_PATH . 'includes/page-canvas.php';
require_once EMCORE_PATH . 'includes/archive-canvas.php';
require_once EMCORE_PATH . 'includes/single-template.php';
require_once EMCORE_PATH . 'includes/preview-editor.php';
require_once EMCORE_PATH . 'includes/contact.php';
require_once EMCORE_PATH . 'includes/privacy.php';
require_once EMCORE_PATH . 'includes/terms.php';
require_once EMCORE_PATH . 'includes/system-pages.php';
require_once EMCORE_PATH . 'includes/extension-registry.php';

if ( is_admin() ) {
	require_once EMCORE_PATH . 'admin/shell.php';
	require_once EMCORE_PATH . 'admin/rail.php';
	require_once EMCORE_PATH . 'admin/admin.php';
	require_once EMCORE_PATH . 'admin/pages.php';
}

register_activation_hook( __FILE__, 'emcore_activate' );
function emcore_activate() {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'emcore_manage_design' );
	}
	if ( function_exists( 'emcore_register_cpts' ) ) {
		emcore_register_cpts();
	}
	if ( function_exists( 'emcore_system_forms_install' ) ) { emcore_system_forms_install(); }
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'emcore_deactivate' );
function emcore_deactivate() {
	flush_rewrite_rules();
}

add_action( 'admin_init', 'emcore_maybe_upgrade' );
function emcore_maybe_upgrade() {
	$installed = get_option( 'emcore_version' );
	if ( $installed === EMCORE_VERSION ) {
		return;
	}
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'emcore_manage_design' );
	}
	if ( function_exists( 'emcore_system_forms_install' ) ) { emcore_system_forms_install(); }
	// Existing 1.5.x sites used edit_pages for design access. Preserve those users on first upgrade.
	if ( get_option( 'emcore_templates', array() ) && ! get_option( 'emcore_version' ) && function_exists( 'wp_roles' ) ) {
		foreach ( wp_roles()->roles as $role_name => $details ) {
			if ( ! empty( $details['capabilities']['edit_pages'] ) ) {
				$role = get_role( $role_name );
				if ( $role ) { $role->add_cap( 'emcore_manage_design' ); }
			}
		}
	}
	if ( $installed && version_compare( $installed, '1.16.0', '<' ) ) {
		$templates = emcore_get_templates();
		foreach ( $templates as &$template ) {
			$template['html'] = emcore_prepare_import_html( $template['html'] ?? '', $template['type'] ?? 'page', $template['post_type_hint'] ?? '' );
		}
		unset( $template );
		emcore_save_templates( $templates );
	}
	if ( $installed && version_compare( $installed, '1.16.0', '>=' ) && version_compare( $installed, '1.16.5', '<' ) ) {
		$templates = emcore_get_templates();
		foreach ( $templates as &$template ) {
			$template['html'] = emcore_repair_legacy_raw_html_blocks( $template['html'] ?? '' );
		}
		unset( $template );
		emcore_save_templates( $templates );
	}
	if ( $installed && version_compare( $installed, '1.16.6', '<' ) ) {
		$templates = emcore_get_templates();
		foreach ( $templates as &$template ) {
			$template['html'] = emcore_promote_head_styles_before_scripts( $template['html'] ?? '' );
		}
		unset( $template );
		emcore_save_templates( $templates );
	}
	if ( $installed && version_compare( $installed, '1.24.4', '<' ) && function_exists( 'emcore_release_removed_system_pages' ) ) {
		emcore_release_removed_system_pages();
	}
	if ( ! $installed || version_compare( $installed, '1.28.2', '<' ) ) {
		$cpts = emcore_get_cpts();
		foreach ( $cpts as $key => &$cpt ) {
			if ( empty( $cpt['parent_slug'] ) ) { $cpt['parent_slug'] = ! empty( $cpt['slug'] ) ? sanitize_title( $cpt['slug'] ) : $key; }
			if ( empty( $cpt['listing_mode'] ) ) { $cpt['listing_mode'] = 'fixed_page'; }
		}
		unset( $cpt );
		emcore_save_cpts( $cpts );
		flush_rewrite_rules( false );
	}
	update_option( 'emcore_version', EMCORE_VERSION, false );
}
