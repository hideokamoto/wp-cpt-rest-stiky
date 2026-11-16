<?php
/**
 * Admin screen: list-table column, meta box rendering and saving from the edit screen.
 */
class AdminTest extends CPT_Sticky_Test_Case {

	protected static $editor_id;
	protected static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id     = $factory->user->create( array( 'role' => 'editor' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	public function test_list_table_column_filter_adds_sticky_column() {
		// Core fires this hook with a single argument (the columns array).
		$columns = apply_filters( 'manage_book_posts_columns', array( 'title' => 'Title' ) );

		$this->assertArrayHasKey( 'cpt_sticky', $columns );
		$this->assertArrayHasKey( 'title', $columns );
	}

	public function test_list_table_column_is_not_added_to_posts() {
		$columns = apply_filters( 'manage_post_posts_columns', array( 'title' => 'Title' ) );

		$this->assertArrayNotHasKey( 'cpt_sticky', $columns );
	}

	public function test_column_renders_icon_for_sticky_and_dash_otherwise() {
		$sticky = self::factory()->post->create( array( 'post_type' => 'book' ) );
		$plain  = self::factory()->post->create( array( 'post_type' => 'book' ) );
		update_post_meta( $sticky, '_cpt_is_sticky', '1' );

		ob_start();
		do_action( 'manage_book_posts_custom_column', 'cpt_sticky', $sticky );
		$this->assertStringContainsString( 'dashicons-sticky', ob_get_clean() );

		ob_start();
		do_action( 'manage_book_posts_custom_column', 'cpt_sticky', $plain );
		$this->assertSame( '—', ob_get_clean() );

		ob_start();
		do_action( 'manage_book_posts_custom_column', 'title', $sticky );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_meta_box_is_added_for_target_type_only() {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		do_action( 'add_meta_boxes', 'book', get_post( self::factory()->post->create( array( 'post_type' => 'book' ) ) ) );

		$this->assertArrayHasKey( 'cpt_sticky_post', $wp_meta_boxes['book']['side']['high'] );
		$this->assertArrayNotHasKey( 'post', $wp_meta_boxes );
	}

	public function test_meta_box_checkbox_reflects_saved_value() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );
		update_post_meta( $post_id, '_cpt_is_sticky', '1' );

		ob_start();
		$this->plugin()->render_sticky_meta_box( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="cpt_sticky_nonce_field"', $html );
		$this->assertMatchesRegularExpression( '/name="cpt_is_sticky"[^>]*checked/', $html );
	}

	public function test_save_with_valid_nonce_and_capability_updates_meta() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );
		wp_set_current_user( self::$editor_id );

		$_POST = array(
			'cpt_sticky_nonce_field' => wp_create_nonce( 'cpt_sticky_nonce' ),
			'cpt_is_sticky'          => '1',
		);
		$this->plugin()->save_sticky_meta( $post_id );
		$this->assertSame( '1', get_post_meta( $post_id, '_cpt_is_sticky', true ) );

		// Unchecked checkbox: the field is absent from the form submission.
		unset( $_POST['cpt_is_sticky'] );
		$this->plugin()->save_sticky_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, '_cpt_is_sticky', true ) );
	}

	public function test_save_without_valid_nonce_does_nothing() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );
		wp_set_current_user( self::$editor_id );

		$_POST = array( 'cpt_is_sticky' => '1' );
		$this->plugin()->save_sticky_meta( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );

		$_POST = array(
			'cpt_sticky_nonce_field' => 'invalid',
			'cpt_is_sticky'          => '1',
		);
		$this->plugin()->save_sticky_meta( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );
	}

	public function test_save_without_capability_does_nothing() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'book' ) );
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'cpt_sticky_nonce_field' => wp_create_nonce( 'cpt_sticky_nonce' ),
			'cpt_is_sticky'          => '1',
		);
		$this->plugin()->save_sticky_meta( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );
	}

	public function test_save_ignores_untargeted_post_types() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		wp_set_current_user( self::$editor_id );

		$_POST = array(
			'cpt_sticky_nonce_field' => wp_create_nonce( 'cpt_sticky_nonce' ),
			'cpt_is_sticky'          => '1',
		);
		$this->plugin()->save_sticky_meta( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_cpt_is_sticky' ) );
	}
}
