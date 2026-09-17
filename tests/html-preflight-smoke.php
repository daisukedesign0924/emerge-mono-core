<?php
/** Standalone smoke test for HTML diagnostics; no WordPress database is required. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/' );
function sanitize_title( $value ) { $value = strtolower( trim( (string) $value ) ); return trim( preg_replace( '/[^a-z0-9_-]+/', '-', $value ), '-' ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function get_posts( $args = array() ) {
	$paths = array_filter( array_map( 'trim', explode( ',', (string) getenv( 'EMCORE_TEST_PATHS' ) ) ) ); $posts = array();
	foreach ( $paths as $index => $path ) { $posts[] = (object) array( 'ID' => $index + 1, 'post_title' => $path, 'post_name' => basename( trim( $path, '/' ) ), 'test_uri' => trim( $path, '/' ) ); }
	return $posts;
}
function get_page_uri( $page ) { return $page->test_uri ?? ''; }
function get_post_types( $args = array(), $output = 'names' ) { return array(); }
function emcore_get_cpts() { return array(); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function apply_filters( $hook, $value ) { return $value; }
require dirname( __DIR__ ) . '/includes/page-templates.php';

$files = array_slice( $argv, 1 );
if ( ! $files ) { fwrite( STDERR, "Usage: php html-preflight-smoke.php file.html [...]\n" ); exit( 2 ); }
$failed = false;
foreach ( $files as $file ) {
	$html = file_get_contents( $file );
	if ( false === $html ) { fwrite( STDERR, "Cannot read: {$file}\n" ); $failed = true; continue; }
	$diagnostics = emcore_diagnose_html( $html );
	$compliance = emcore_emhtml_compliance( $html );
	$errors = array_values( array_filter( $diagnostics, function ( $issue ) { return 'error' === ( $issue['level'] ?? '' ); } ) );
	echo basename( $file ) . "\n";
	echo '  compliance: ' . $compliance['score'] . ' (' . $compliance['status'] . ")\n";
	foreach ( $diagnostics as $issue ) { echo '  ' . strtoupper( $issue['level'] ) . ' [' . $issue['code'] . '] ' . $issue['message'] . "\n"; }
	if ( $errors ) { $failed = true; }
}
exit( $failed ? 1 : 0 );
