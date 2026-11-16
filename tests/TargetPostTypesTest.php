<?php
/**
 * Which post types the plugin works on.
 */
class TargetPostTypesTest extends CPT_Sticky_Test_Case {

	public function test_public_rest_custom_post_type_is_targeted() {
		$this->assertTrue( has_filter( 'manage_book_posts_columns' ) !== false );
		$this->assertTrue( has_action( 'manage_book_posts_custom_column' ) !== false );
	}

	public function test_builtin_non_public_and_non_rest_types_are_not_targeted() {
		foreach ( array( 'post', 'page', 'internal_note', 'legacy_item' ) as $post_type ) {
			$this->assertFalse(
				has_filter( "manage_{$post_type}_posts_columns", array( $this->plugin(), 'add_sticky_column' ) ),
				"{$post_type} should not get the sticky column"
			);
		}
	}

	public function test_target_types_can_be_overridden_by_filter() {
		$override = static function () {
			return array( 'legacy_item' );
		};
		add_filter( 'cpt_sticky_posts_target_types', $override );

		$plugin = new CPT_Sticky_Posts();
		$plugin->set_target_post_types();

		$this->assertSame( 10, has_filter( 'manage_legacy_item_posts_columns', array( $plugin, 'add_sticky_column' ) ) );
		$this->assertFalse( has_filter( 'manage_book_posts_columns', array( $plugin, 'add_sticky_column' ) ) );
	}
}
