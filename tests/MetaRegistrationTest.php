<?php
/**
 * Registration of the `_cpt_is_sticky` meta and the REST `sticky` field, and who may change them.
 */
class MetaRegistrationTest extends CPT_Sticky_Test_Case {

	protected static $editor_id;
	protected static $author_id;
	protected static $other_author_id;
	protected static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id       = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_id       = $factory->user->create( array( 'role' => 'author' ) );
		self::$other_author_id = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber_id   = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();
		$this->boot_rest_server();
	}

	public function test_meta_is_registered_as_boolean_and_shown_in_rest() {
		$keys = get_registered_meta_keys( 'post', 'book' );

		$this->assertArrayHasKey( '_cpt_is_sticky', $keys );
		$this->assertSame( 'boolean', $keys['_cpt_is_sticky']['type'] );
		$this->assertTrue( $keys['_cpt_is_sticky']['single'] );
		$this->assertFalse( $keys['_cpt_is_sticky']['default'] );
		$this->assertNotEmpty( $keys['_cpt_is_sticky']['show_in_rest'] );
	}

	public function test_meta_is_not_registered_for_untargeted_types() {
		$this->assertArrayNotHasKey( '_cpt_is_sticky', get_registered_meta_keys( 'post', 'post' ) );
		$this->assertArrayNotHasKey( '_cpt_is_sticky', get_registered_meta_keys( 'post', 'legacy_item' ) );
	}

	public function test_auth_callback_follows_edit_post_capability() {
		$own_post   = self::factory()->post->create( array( 'post_type' => 'book', 'post_author' => self::$author_id ) );
		$other_post = self::factory()->post->create( array( 'post_type' => 'book', 'post_author' => self::$other_author_id ) );

		wp_set_current_user( self::$editor_id );
		$this->assertTrue( current_user_can( 'edit_post_meta', $other_post, '_cpt_is_sticky' ) );

		wp_set_current_user( self::$author_id );
		$this->assertTrue( current_user_can( 'edit_post_meta', $own_post, '_cpt_is_sticky' ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $other_post, '_cpt_is_sticky' ) );

		wp_set_current_user( self::$subscriber_id );
		$this->assertFalse( current_user_can( 'edit_post_meta', $own_post, '_cpt_is_sticky' ) );
	}

	public function test_sanitize_callback_stores_booleans() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );

		update_post_meta( $post_id, '_cpt_is_sticky', 'false' );
		$this->assertSame( '', get_post_meta( $post_id, '_cpt_is_sticky', true ) );

		update_post_meta( $post_id, '_cpt_is_sticky', 'yes' );
		$this->assertSame( '1', get_post_meta( $post_id, '_cpt_is_sticky', true ) );

		update_post_meta( $post_id, '_cpt_is_sticky', 0 );
		$this->assertSame( '', get_post_meta( $post_id, '_cpt_is_sticky', true ) );
	}

	public function test_rest_response_contains_sticky_field() {
		$sticky = self::factory()->post->create( array( 'post_type' => 'book' ) );
		$plain  = self::factory()->post->create( array( 'post_type' => 'book' ) );
		update_post_meta( $sticky, '_cpt_is_sticky', '1' );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/wp/v2/book/{$sticky}" ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['sticky'] );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/wp/v2/book/{$plain}" ) );
		$this->assertFalse( $response->get_data()['sticky'] );
	}

	public function test_editor_can_set_sticky_through_rest() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book', 'post_author' => self::$author_id ) );
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', "/wp/v2/book/{$post_id}" );
		$request->set_body_params( array( 'sticky' => true ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['sticky'] );
		$this->assertSame( '1', get_post_meta( $post_id, '_cpt_is_sticky', true ) );

		$request = new WP_REST_Request( 'POST', "/wp/v2/book/{$post_id}" );
		$request->set_body_params( array( 'sticky' => 'false' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['sticky'] );
		$this->assertSame( '', get_post_meta( $post_id, '_cpt_is_sticky', true ) );
	}

	public function test_author_cannot_set_sticky_on_someone_elses_post_through_rest() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book', 'post_author' => self::$other_author_id ) );
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', "/wp/v2/book/{$post_id}" );
		$request->set_body_params( array( 'sticky' => true ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );
	}

	public function test_author_can_set_meta_on_own_post_through_rest_meta() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book', 'post_author' => self::$author_id ) );
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', "/wp/v2/book/{$post_id}" );
		$request->set_body_params( array( 'meta' => array( '_cpt_is_sticky' => true ) ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['meta']['_cpt_is_sticky'] );
		$this->assertTrue( $response->get_data()['sticky'] );
	}

	public function test_anonymous_request_cannot_set_sticky() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', "/wp/v2/book/{$post_id}" );
		$request->set_body_params( array( 'sticky' => true ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );
	}
}
