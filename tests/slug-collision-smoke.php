<?php
/** Standalone checks for the shared public-slug collision guard. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/' );
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( (string) $value ) ), '-' ); }
function get_posts( $args = array() ) { return ( $args['name'] ?? '' ) === 'about' ? array( (object) array( 'ID' => 10, 'post_title' => 'About' ) ) : array(); }
function emcore_get_cpts() { return array(
	'emcpt_news' => array( 'label' => 'News', 'slug' => 'news', 'listing_mode' => 'archive' ),
	'emcpt_liver' => array( 'label' => 'Liver', 'slug' => 'liver', 'parent_slug' => 'liver', 'listing_mode' => 'fixed_page' ),
); }
function emcore_system_pages_settings() { return array( 'contact' => array( 'enabled' => true, 'slug' => 'contact' ), '404' => array( 'enabled' => true, 'slug' => '' ) ); }
function get_post_types( $args = array(), $output = 'names' ) { return array(); }
function apply_filters( $hook, $value ) { return $value; }
require dirname( __DIR__ ) . '/includes/page-templates.php';

$tests = array(
	'about' => '固定ページ',
	'news' => '投稿タイプ',
	'contact' => 'システムページ',
);
$failed = false;
foreach ( $tests as $slug => $expected ) {
	$result = emcore_public_slug_conflicts( $slug ); $text = implode( ' ', $result );
	if ( false === strpos( $text, $expected ) ) { fwrite( STDERR, "FAIL {$slug}: {$text}\n" ); $failed = true; } else { echo "PASS {$slug}: {$text}\n"; }
}
if ( emcore_public_slug_conflicts( 'unique-page' ) ) { fwrite( STDERR, "FAIL unique-page\n" ); $failed = true; } else { echo "PASS unique-page: no conflict\n"; }
if ( emcore_public_slug_conflicts( 'liver' ) ) { fwrite( STDERR, "FAIL liver fixed-page sharing\n" ); $failed = true; } else { echo "PASS liver: fixed-page parent may be shared\n"; }
exit( $failed ? 1 : 0 );
