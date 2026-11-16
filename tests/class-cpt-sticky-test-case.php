<?php
/**
 * Shared helpers for the CPT Sticky Posts tests.
 */

abstract class CPT_Sticky_Test_Case extends WP_UnitTestCase {

	/**
	 * The plugin creates its instance with `new CPT_Sticky_Posts()` and keeps no
	 * reference, so find it through the callback it hooked on init.
	 */
	protected function plugin(): CPT_Sticky_Posts {
		global $wp_filter;
		foreach ( $wp_filter['init']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof CPT_Sticky_Posts ) {
					return $callback['function'][0];
				}
			}
		}
		$this->fail( 'CPT_Sticky_Posts instance is not hooked on init.' );
	}

	/**
	 * The test library unregisters every meta key after each test, while on a
	 * real site the plugin registers them on every request. Register them again
	 * unless the test checks what the plugin registers by itself.
	 *
	 * @var bool
	 */
	protected $register_meta_in_set_up = true;

	public function set_up() {
		parent::set_up();
		if ( $this->register_meta_in_set_up ) {
			$this->plugin()->register_sticky_meta();
		}
	}

	/**
	 * Boots a fresh REST server, which fires rest_api_init (meta, field and
	 * query parameter registration happen there).
	 */
	protected function boot_rest_server(): WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		return rest_get_server();
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}
}
