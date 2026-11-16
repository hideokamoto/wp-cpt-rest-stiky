<?php
/**
 * REST collection parameters `sticky` and `sticky_first`.
 *
 * Fixture (TESTING.md): four books with different meta states.
 */
class RestQueryTest extends CPT_Sticky_Test_Case {

	protected static $sticky;
	protected static $not_sticky;
	protected static $no_meta;
	protected static $empty_meta;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// The sticky post is neither the newest/oldest nor first by title, so ordering bugs show up.
		self::$not_sticky = $factory->post->create( array( 'post_type' => 'book', 'post_title' => 'B Not Sticky', 'post_date' => '2024-01-01 00:00:00' ) );
		self::$sticky     = $factory->post->create( array( 'post_type' => 'book', 'post_title' => 'Z Sticky', 'post_date' => '2024-01-02 00:00:00' ) );
		self::$no_meta    = $factory->post->create( array( 'post_type' => 'book', 'post_title' => 'C No Meta', 'post_date' => '2024-01-03 00:00:00' ) );
		self::$empty_meta = $factory->post->create( array( 'post_type' => 'book', 'post_title' => 'D Empty Meta', 'post_date' => '2024-01-04 00:00:00' ) );

		// Raw values as an old version of the plugin or WP-CLI would have stored them.
		add_post_meta( self::$sticky, '_cpt_is_sticky', '1' );
		add_post_meta( self::$not_sticky, '_cpt_is_sticky', '0' );
		add_post_meta( self::$empty_meta, '_cpt_is_sticky', '' );

		// Unrelated meta, so a JOIN on all postmeta rows would show up as wrong results.
		foreach ( array( self::$sticky, self::$not_sticky, self::$no_meta, self::$empty_meta ) as $id ) {
			add_post_meta( $id, '_other_meta', 'zzz' );
		}
	}

	public function set_up() {
		parent::set_up();
		$this->boot_rest_server();
	}

	private function get_ids( array $params ): array {
		$request = new WP_REST_Request( 'GET', '/wp/v2/book' );
		$request->set_query_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		return wp_list_pluck( $response->get_data(), 'id' );
	}

	public function test_collection_params_are_registered() {
		$routes = rest_get_server()->get_routes();
		$args   = $routes['/wp/v2/book'][0]['args'];

		$this->assertArrayHasKey( 'sticky', $args );
		$this->assertSame( 'boolean', $args['sticky']['type'] );
		$this->assertArrayHasKey( 'sticky_first', $args );
		$this->assertFalse( $args['sticky_first']['default'] );
	}

	public function test_default_order_is_unchanged() {
		$this->assertSame(
			array( self::$empty_meta, self::$no_meta, self::$sticky, self::$not_sticky ),
			$this->get_ids( array() )
		);
	}

	public function test_sticky_true_returns_only_sticky_posts() {
		$this->assertSame( array( self::$sticky ), $this->get_ids( array( 'sticky' => 'true' ) ) );
	}

	public function test_sticky_false_returns_non_sticky_posts_including_posts_without_meta() {
		$this->assertSame(
			array( self::$empty_meta, self::$no_meta, self::$not_sticky ),
			$this->get_ids( array( 'sticky' => 'false' ) )
		);
	}

	public function test_sticky_filter_respects_include_and_exclude() {
		$this->assertSame(
			array( self::$sticky ),
			$this->get_ids( array( 'sticky' => 'true', 'include' => array( self::$sticky, self::$no_meta ) ) )
		);
		$this->assertSame(
			array(),
			$this->get_ids( array( 'sticky' => 'true', 'exclude' => array( self::$sticky ) ) )
		);
		$this->assertSame(
			array( self::$empty_meta, self::$not_sticky ),
			$this->get_ids( array( 'sticky' => 'false', 'exclude' => array( self::$no_meta ) ) )
		);
	}

	/**
	 * Core's own sticky list (the `sticky_posts` option, used for normal posts)
	 * has nothing to do with this plugin's flag.
	 */
	public function test_sticky_filter_ignores_core_sticky_posts_option() {
		update_option( 'sticky_posts', array( self::$no_meta ) );

		$this->assertSame( array( self::$sticky ), $this->get_ids( array( 'sticky' => 'true' ) ) );
		$this->assertSame(
			array( self::$empty_meta, self::$no_meta, self::$not_sticky ),
			$this->get_ids( array( 'sticky' => 'false' ) )
		);
	}

	public function test_sticky_first_puts_sticky_post_first_and_keeps_all_posts() {
		$this->assertSame(
			array( self::$sticky, self::$empty_meta, self::$no_meta, self::$not_sticky ),
			$this->get_ids( array( 'sticky_first' => 'true' ) )
		);
	}

	public function test_sticky_first_keeps_requested_order_for_the_rest() {
		$this->assertSame(
			array( self::$sticky, self::$not_sticky, self::$no_meta, self::$empty_meta ),
			$this->get_ids( array( 'sticky_first' => 'true', 'orderby' => 'title', 'order' => 'asc' ) )
		);
		$this->assertSame(
			array( self::$sticky, self::$not_sticky, self::$no_meta, self::$empty_meta ),
			$this->get_ids( array( 'sticky_first' => 'true', 'order' => 'asc' ) )
		);
	}

	public function test_sticky_first_with_pagination() {
		$this->assertSame(
			array( self::$sticky, self::$empty_meta ),
			$this->get_ids( array( 'sticky_first' => 'true', 'per_page' => 2, 'page' => 1 ) )
		);
		$this->assertSame(
			array( self::$no_meta, self::$not_sticky ),
			$this->get_ids( array( 'sticky_first' => 'true', 'per_page' => 2, 'page' => 2 ) )
		);
	}

	public function test_sticky_first_with_search() {
		$this->assertSame(
			array( self::$no_meta ),
			$this->get_ids( array( 'sticky_first' => 'true', 'search' => 'No Meta' ) )
		);
	}

	public function test_sticky_first_false_does_not_change_order() {
		$this->assertSame(
			array( self::$empty_meta, self::$no_meta, self::$sticky, self::$not_sticky ),
			$this->get_ids( array( 'sticky_first' => 'false' ) )
		);
	}

	public function test_sticky_first_works_with_sticky_false() {
		$this->assertSame(
			array( self::$empty_meta, self::$no_meta, self::$not_sticky ),
			$this->get_ids( array( 'sticky_first' => 'true', 'sticky' => 'false' ) )
		);
	}

	public function test_sticky_set_through_rest_is_found_by_sticky_true() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$request = new WP_REST_Request( 'POST', '/wp/v2/book/' . self::$no_meta );
		$request->set_body_params( array( 'sticky' => true ) );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );

		$this->assertSame( array( self::$no_meta, self::$sticky ), $this->get_ids( array( 'sticky' => 'true' ) ) );
	}
}
