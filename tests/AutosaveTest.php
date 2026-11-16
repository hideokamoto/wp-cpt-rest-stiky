<?php
/**
 * Autosaves must not change the sticky flag.
 */
class AutosaveTest extends CPT_Sticky_Test_Case {

	public function test_rest_update_callback_skips_autosave_revisions() {
		$parent_id   = self::factory()->post->create( array( 'post_type' => 'book' ) );
		$autosave_id = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $parent_id,
				'post_name'   => "{$parent_id}-autosave-v1",
			)
		);
		$this->assertSame( $parent_id, wp_is_post_autosave( $autosave_id ) );

		$result = $this->plugin()->update_sticky_field( true, get_post( $autosave_id ) );

		$this->assertFalse( $result );
		$this->assertFalse( metadata_exists( 'post', $autosave_id, '_cpt_is_sticky' ) );
		$this->assertFalse( metadata_exists( 'post', $parent_id, '_cpt_is_sticky' ) );
	}

	public function test_rest_update_callback_updates_regular_posts() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );

		$this->plugin()->update_sticky_field( true, get_post( $post_id ) );

		$this->assertSame( '1', get_post_meta( $post_id, '_cpt_is_sticky', true ) );
	}

	/**
	 * DOING_AUTOSAVE is a constant, so this runs in its own process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_meta_box_save_skips_during_autosave() {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post_id   = self::factory()->post->create( array( 'post_type' => 'book' ) );
		wp_set_current_user( $editor_id );

		define( 'DOING_AUTOSAVE', true );
		$_POST = array(
			'cpt_sticky_nonce_field' => wp_create_nonce( 'cpt_sticky_nonce' ),
			'cpt_is_sticky'          => '1',
		);
		$this->plugin()->save_sticky_meta( $post_id );
		$_POST = array();

		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );
	}
}
