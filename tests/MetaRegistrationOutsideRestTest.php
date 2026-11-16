<?php
/**
 * The `_cpt_is_sticky` meta outside REST requests (admin, WP-CLI, cron).
 */
class MetaRegistrationOutsideRestTest extends CPT_Sticky_Test_Case {

	protected $register_meta_in_set_up = false;

	/**
	 * The meta must be registered on every request, not only REST requests, so
	 * the sanitize callback also applies to the classic editor, WP-CLI and
	 * other code calling update_post_meta(). Runs in its own process because
	 * other tests boot the REST server, which would register it anyway.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_meta_is_registered_outside_rest_requests() {
		$this->assertFalse( did_action( 'rest_api_init' ) > 0, 'rest_api_init must not have fired in this process' );
		$this->assertTrue( registered_meta_key_exists( 'post', '_cpt_is_sticky', 'book' ) );

		// e.g. `wp post meta update <id> _cpt_is_sticky false`
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );
		update_post_meta( $post_id, '_cpt_is_sticky', 'false' );
		$this->assertSame( '', get_post_meta( $post_id, '_cpt_is_sticky', true ) );
	}
}
