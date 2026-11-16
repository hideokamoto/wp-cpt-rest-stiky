<?php
/**
 * PHPUnit bootstrap for the wp-env tests-cli container.
 *
 * wp-env mounts the WordPress core test library at /wordpress-phpunit and sets
 * WP_TESTS_DIR / WP_PHPUNIT__TESTS_CONFIG for it. The test library installs a
 * fresh database for every run, so plugins activated in wp-env are not loaded
 * here; the plugin is loaded by hand on muplugins_loaded instead.
 */

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$wp_tests_dir}. Run the tests inside wp-env (npm run test).\n" );
	exit( 1 );
}

// The WordPress test library requires the PHPUnit Polyfills (1.1 or later).
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/cpt-rest-stiky.php';
	}
);

/*
 * Custom post types used by the tests. They are registered on init at the
 * default priority, before the plugin collects its target post types (init:20),
 * just like a theme or another plugin would register them on a real site.
 */
tests_add_filter(
	'init',
	static function () {
		// Target: public and shown in REST.
		register_post_type(
			'book',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
			)
		);
		// Not a target: not public.
		register_post_type(
			'internal_note',
			array(
				'public'       => false,
				'show_in_rest' => true,
			)
		);
		// Not a target: not shown in REST.
		register_post_type(
			'legacy_item',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);
	}
);

require $wp_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/class-cpt-sticky-test-case.php';
